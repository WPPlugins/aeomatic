#!/usr/bin/env node
/**
 * Structural check for PHP files.
 *
 * NOT a parser and NOT a substitute for `php -l`. It exists because this
 * project's build machine has no PHP binary, and shipping a file with an
 * unbalanced brace is the single cheapest way to break an activation.
 *
 * It tokenises enough to skip strings, comments and heredocs, then verifies:
 *   - {} () [] are balanced inside PHP code regions
 *   - no unterminated string or block comment at EOF
 *   - alternative-syntax control keywords in view files have matching
 *     endif / endforeach / endfor / endwhile / endswitch
 *
 * Run `node bin/check-structure.js --self-test` to confirm the checker still
 * detects the four error classes it claims to. Do not trust a pass without it.
 *
 * Usage:
 *   node bin/check-structure.js                # every .php file in the plugin
 *   node bin/check-structure.js path/to/a.php  # specific files
 *   node bin/check-structure.js --self-test
 *
 * Exit code 0 = clean, 1 = at least one file failed.
 *
 * @package AEOmatic
 */
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');

const SKIP_DIRS = new Set(['.git', '.claude', '.vercel', 'node_modules', 'vendor', 'bin']);

function collectPhpFiles(dir, out = []) {
	for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
		if (SKIP_DIRS.has(entry.name)) continue;
		const full = path.join(dir, entry.name);
		if (entry.isDirectory()) collectPhpFiles(full, out);
		else if (entry.name.endsWith('.php')) out.push(full);
	}
	return out;
}

/**
 * @param {string} src PHP source.
 * @returns {string[]} Human-readable problems; empty means clean.
 */
function checkSource(src) {
	const errs = [];
	const lineAt = (idx) => src.slice(0, idx).split('\n').length;

	const PAIRS = { '}': '{', ')': '(', ']': '[' };
	const stack = [];
	const alt = [];

	let i = 0;
	let inPhp = false;

	while (i < src.length) {
		if (!inPhp) {
			const open = src.indexOf('<?', i);
			if (open === -1) break;
			if (src.startsWith('<?php', open)) i = open + 5;
			else if (src.startsWith('<?=', open)) i = open + 3;
			else i = open + 2;
			inPhp = true;
			continue;
		}

		const c = src[i];

		if (c === '?' && src[i + 1] === '>') {
			inPhp = false;
			i += 2;
			continue;
		}

		// Line comments. `#[` opens an attribute, not a comment.
		if ((c === '/' && src[i + 1] === '/') || c === '#') {
			if (c === '#' && src[i + 1] === '[') { i += 2; continue; }
			let j = i;
			while (j < src.length && src[j] !== '\n') {
				if (src[j] === '?' && src[j + 1] === '>') break; // ?> ends a line comment
				j++;
			}
			i = j;
			continue;
		}

		if (c === '/' && src[i + 1] === '*') {
			const end = src.indexOf('*/', i + 2);
			if (end === -1) { errs.push(`unterminated /* comment opened at line ${lineAt(i)}`); break; }
			i = end + 2;
			continue;
		}

		// Heredoc / nowdoc.
		if (src.startsWith('<<<', i)) {
			const m = /^<<<[ \t]*(['"]?)([A-Za-z_][A-Za-z0-9_]*)\1\r?\n/.exec(src.slice(i));
			if (m) {
				const label = m[2];
				const rest = src.slice(i + m[0].length);
				const found = new RegExp(`^[ \\t]*${label}\\b`, 'm').exec(rest);
				if (!found) { errs.push(`unterminated heredoc ${label} at line ${lineAt(i)}`); break; }
				i += m[0].length + found.index + found[0].length;
				continue;
			}
		}

		if (c === "'" || c === '"') {
			let j = i + 1;
			let closed = false;
			while (j < src.length) {
				if (src[j] === '\\') { j += 2; continue; }
				if (src[j] === c) { closed = true; break; }
				j++;
			}
			if (!closed) { errs.push(`unterminated ${c} string at line ${lineAt(i)}`); break; }
			i = j + 1;
			continue;
		}

		if (c === '{' || c === '(' || c === '[') {
			stack.push({ ch: c, line: lineAt(i) });
			i++;
			continue;
		}
		if (c === '}' || c === ')' || c === ']') {
			const top = stack.pop();
			if (!top) { errs.push(`unmatched '${c}' at line ${lineAt(i)}`); break; }
			if (top.ch !== PAIRS[c]) {
				errs.push(`'${c}' at line ${lineAt(i)} closes '${top.ch}' opened at line ${top.line}`);
				break;
			}
			i++;
			continue;
		}

		const kw = /^(endif|endforeach|endfor|endwhile|endswitch|elseif|else|if|foreach|for|while|switch)\b/
			.exec(src.slice(i));
		if (kw && /[^A-Za-z0-9_$>]/.test(src[i - 1] || ' ')) {
			const word = kw[1];
			if (word.startsWith('end')) {
				const want = word.slice(3);
				const top = alt.pop();
				if (!top) errs.push(`'${word}' with no opener at line ${lineAt(i)}`);
				else if (top.word !== want) {
					errs.push(`'${word}' at line ${lineAt(i)} closes '${top.word}' opened at line ${top.line}`);
				}
			} else if (['if', 'foreach', 'for', 'while', 'switch'].includes(word)) {
				// Colon form or brace form? Scan past the condition's parens.
				let j = i + word.length;
				while (j < src.length && /\s/.test(src[j])) j++;
				if (src[j] === '(') {
					let depth = 0;
					while (j < src.length) {
						if (src[j] === '(') depth++;
						else if (src[j] === ')') { depth--; if (depth === 0) { j++; break; } }
						j++;
					}
					while (j < src.length && /\s/.test(src[j])) j++;
					if (src[j] === ':') alt.push({ word, line: lineAt(i) });
				}
			}
			i += word.length;
			continue;
		}

		i++;
	}

	if (stack.length) {
		const t = stack[stack.length - 1];
		errs.push(`unclosed '${t.ch}' opened at line ${t.line}`);
	}
	if (alt.length) {
		const t = alt[alt.length - 1];
		errs.push(`unclosed alternative-syntax '${t.word}' opened at line ${t.line}`);
	}

	return errs;
}

function selfTest() {
	const cases = [
		['unclosed brace', '<?php\nclass A {\n\tfunction b() {\n\t\treturn 1;\n}\n'],
		['unclosed endif', '<?php if ( true ) : ?>\n<p>hi</p>\n'],
		['unterminated string', '<?php $x = "oops;\n'],
		['unterminated comment', '<?php\n/* open\n'],
	];
	const clean = '<?php\nclass A {\n\tpublic function b(): int {\n\t\treturn 1;\n\t}\n}\n';

	let ok = true;
	for (const [label, src] of cases) {
		const errs = checkSource(src);
		const detected = errs.length > 0;
		console.log(`  ${detected ? 'PASS' : 'FAIL'}  detects ${label}${detected ? ' — ' + errs[0] : ''}`);
		if (!detected) ok = false;
	}
	const cleanErrs = checkSource(clean);
	const cleanOk = cleanErrs.length === 0;
	console.log(`  ${cleanOk ? 'PASS' : 'FAIL'}  accepts valid source${cleanOk ? '' : ' — ' + cleanErrs[0]}`);
	if (!cleanOk) ok = false;

	console.log(ok ? '\nself-test OK' : '\nSELF-TEST FAILED — do not trust this checker');
	return ok;
}

function main(argv) {
	if (argv.includes('--self-test')) return selfTest() ? 0 : 1;

	const root = path.resolve(__dirname, '..');
	const targets = argv.length ? argv : collectPhpFiles(root);
	const quiet = process.env.AEOMATIC_BUILD_QUIET === '1';

	let bad = 0;
	for (const f of targets) {
		const errs = checkSource(fs.readFileSync(f, 'utf8'));
		const rel = path.relative(root, f) || f;
		if (errs.length) {
			bad++;
			console.log('FAIL ' + rel);
			errs.forEach((e) => console.log('       ' + e));
		} else if (!quiet) {
			console.log('ok   ' + rel);
		}
	}
	console.log(`${targets.length - bad}/${targets.length} structurally clean`);
	return bad ? 1 : 0;
}

if (require.main === module) {
	process.exit(main(process.argv.slice(2)));
}

module.exports = { checkSource, collectPhpFiles };
