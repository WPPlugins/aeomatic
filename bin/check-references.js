#!/usr/bin/env node
/**
 * Cross-reference every `AEOMATIC_*::member` usage against its declaration.
 *
 * This exists because the bug it catches has shipped twice: the Entity
 * Scanner's signal collector called a non-existent
 * AEOMATIC_Content_Extractor::extract_text() (fixed in 1.1.7), and the Content
 * readiness analyzer called the same missing method (fixed in 1.1.9). Both were
 * wrapped in method_exists() guards, so they failed silently and scored content
 * wrongly rather than erroring — invisible without a check like this.
 *
 * Regex-based, so treat output as candidates, not proof. References guarded by
 * method_exists() / defined() are reported separately: a guard is a deliberate
 * soft dependency, but if the guarded target does not exist the fallback path
 * is always taken, which is usually a bug rather than a design.
 *
 * Run `node bin/check-references.js --self-test` to confirm it still detects a
 * missing member.
 *
 * Exit code 0 = no unguarded dangling references, 1 = at least one.
 *
 * @package AEOmatic
 */
'use strict';

const fs = require('fs');
const path = require('path');

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
 * @param {Array<{path:string, src:string}>} files
 */
function analyze(files) {
	const declared = new Set();   // "CLASS::member"
	const classNames = new Set();

	for (const { src } of files) {
		const cm = /^(?:final\s+|abstract\s+)?(?:class|interface|trait)\s+(AEOMATIC_[A-Za-z0-9_]+)/m.exec(src);
		if (!cm) continue;
		const cls = cm[1];
		classNames.add(cls);

		for (const m of src.matchAll(/^\s*(?:public|protected|private|static|final|abstract|\s)*function\s+([A-Za-z0-9_]+)\s*\(/gm)) {
			declared.add(cls + '::' + m[1]);
		}
		for (const m of src.matchAll(/^\s*(?:public|protected|private)?\s*const\s+([A-Z0-9_]+)/gm)) {
			declared.add(cls + '::' + m[1]);
		}
		for (const m of src.matchAll(/^\s*(?:public|protected|private)\s+static\s+\$?([A-Za-z0-9_]+)/gm)) {
			declared.add(cls + '::$' + m[1]);
		}
	}

	const missing = [];
	const guarded = [];

	for (const { path: file, src } of files) {
		const lines = src.split('\n');
		lines.forEach((line, idx) => {
			for (const m of line.matchAll(/\b(AEOMATIC_[A-Za-z0-9_]+)::([A-Za-z0-9_]+)/g)) {
				const [full, cls, member] = m;
				if (!classNames.has(cls)) continue; // not a class we declare
				const key = cls + '::' + member;
				if (declared.has(key)) continue;

				const ctx = lines.slice(Math.max(0, idx - 3), idx + 1).join('\n');
				const isGuarded =
					ctx.includes(`method_exists( '${cls}'`) ||
					ctx.includes(`method_exists( ${cls}`) ||
					ctx.includes(`defined( '${key}'`);

				const entry = `${file}:${idx + 1}  ${full}`;
				(isGuarded ? guarded : missing).push(entry);
			}
		});
	}

	return {
		missing: [...new Set(missing)],
		guarded: [...new Set(guarded)],
		classCount: classNames.size,
		memberCount: declared.size,
	};
}

function selfTest() {
	const good = [{
		path: 'a.php',
		src: "<?php\nclass AEOMATIC_Thing {\n\tconst FOO = 1;\n\tpublic static function bar() {}\n}\nAEOMATIC_Thing::bar();\nAEOMATIC_Thing::FOO;\n",
	}];
	const bad = [{
		path: 'a.php',
		src: "<?php\nclass AEOMATIC_Thing {\n\tpublic static function bar() {}\n}\nAEOMATIC_Thing::not_real();\n",
	}];

	const g = analyze(good);
	const b = analyze(bad);
	const gOk = g.missing.length === 0;
	const bOk = b.missing.length === 1;

	console.log(`  ${gOk ? 'PASS' : 'FAIL'}  accepts declared members`);
	console.log(`  ${bOk ? 'PASS' : 'FAIL'}  detects missing member${bOk ? ' — ' + b.missing[0] : ''}`);
	const ok = gOk && bOk;
	console.log(ok ? '\nself-test OK' : '\nSELF-TEST FAILED — do not trust this checker');
	return ok;
}

function main(argv) {
	if (argv.includes('--self-test')) return selfTest() ? 0 : 1;

	const root = path.resolve(__dirname, '..');
	const files = collectPhpFiles(root).map((p) => ({
		path: path.relative(root, p),
		src: fs.readFileSync(p, 'utf8'),
	}));

	const { missing, guarded, classCount, memberCount } = analyze(files);
	const quiet = process.env.AEOMATIC_BUILD_QUIET === '1';

	if (!quiet) {
		console.log(`scanned ${files.length} files, ${classCount} classes, ${memberCount} declared members`);
	}

	if (missing.length) {
		console.log('\nDANGLING references (no matching declaration):');
		missing.forEach((m) => console.log('  ' + m));
	} else if (!quiet) {
		console.log('no dangling references');
	}

	if (guarded.length) {
		console.log('\nGuarded references whose target does not exist');
		console.log('(the fallback path is always taken — usually a bug, not a design):');
		guarded.forEach((m) => console.log('  ' + m));
	}

	return missing.length ? 1 : 0;
}

if (require.main === module) {
	process.exit(main(process.argv.slice(2)));
}

module.exports = { analyze, collectPhpFiles };
