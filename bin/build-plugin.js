#!/usr/bin/env node
/**
 * Package the plugin for distribution.
 *
 * Every gate below corresponds to a defect that has actually shipped:
 *
 *   G1 version drift   The plugin header, AEOMATIC_VERSION, readme.txt's
 *                      Stable tag and the top changelog heading must agree.
 *   G2 dev files       1.1.8 shipped .claude/settings.local.json, .gitignore
 *                      and .vercel/project.json (containing Vercel project and
 *                      org IDs) into a public download. Allow-list, never
 *                      copy-then-prune.
 *   G3 no wrapper      1.1.9's first build put files at the archive root, so
 *                      WordPress installed it as a second plugin folder
 *                      instead of updating the existing one.
 *   G4 backslashes     Compress-Archive (PS 5.1) and .NET Framework's
 *                      ZipFile::CreateFromDirectory both write Windows
 *                      separators into entry names. PHP's unzip treats a
 *                      backslash as part of the filename, so the archive
 *                      extracts as a flat pile of oddly-named files. This
 *                      script writes the ZIP itself to guarantee '/'.
 *   G5 broken PHP      No PHP binary on the build machine, so the structural
 *                      and reference checkers run first and block the build.
 *
 * Usage:
 *   node bin/build-plugin.js                    # -> ../aeomatic-<version>.zip
 *   node bin/build-plugin.js --out DIR
 *   node bin/build-plugin.js --skip-checks      # emergency escape hatch
 *
 * Exit code 0 = archive written, 1 = a gate failed and nothing was written.
 *
 * @package AEOmatic
 */
'use strict';

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const ROOT = path.resolve(__dirname, '..');
const SLUG = 'aeomatic';

/** G2: only these ship. Anything not listed is absent by construction. */
const INCLUDE = [
	'admin',
	'assets',
	'blocks',
	'includes',
	'languages',
	'aeomatic.php',
	'readme.txt',
	'uninstall.php',
	'README.md',
];

/** Belt-and-braces: refuse to package these even if the allow-list widens. */
const FORBIDDEN = [/(^|\/)\.claude(\/|$)/, /(^|\/)\.vercel(\/|$)/, /(^|\/)\.git(\/|$)/, /(^|\/)\.gitignore$/, /(^|\/)node_modules(\/|$)/, /(^|\/)bin(\/|$)/];

const REQUIRED_IN_ARCHIVE = [
	`${SLUG}/aeomatic.php`,
	`${SLUG}/readme.txt`,
	`${SLUG}/uninstall.php`,
	`${SLUG}/includes/class-aeomatic-plugin.php`,
];

function fail(msg) {
	console.error('BUILD FAILED: ' + msg);
	process.exit(1);
}

// ── G1: version consistency ────────────────────────────────────────────────

function readVersions() {
	const mainSrc = fs.readFileSync(path.join(ROOT, 'aeomatic.php'), 'utf8');
	const readmeSrc = fs.readFileSync(path.join(ROOT, 'readme.txt'), 'utf8');

	const header = /^\s*\*\s*Version:\s*(\S+)\s*$/m.exec(mainSrc);
	const constant = /define\(\s*'AEOMATIC_VERSION'\s*,\s*'([^']+)'\s*\)/.exec(mainSrc);
	const stable = /^Stable tag:\s*(\S+)\s*$/m.exec(readmeSrc);

	const changelogIdx = readmeSrc.indexOf('== Changelog ==');
	let changelog = null;
	if (changelogIdx !== -1) {
		changelog = /^=\s*(\S+)\s*=\s*$/m.exec(readmeSrc.slice(changelogIdx));
	}

	return {
		header: header && header[1],
		constant: constant && constant[1],
		stable: stable && stable[1],
		changelog: changelog && changelog[1],
	};
}

function gateVersions() {
	const v = readVersions();
	const entries = Object.entries(v);

	for (const [k, val] of entries) {
		if (!val) fail(`could not read the version from: ${k}`);
	}
	const unique = [...new Set(entries.map(([, val]) => val))];
	if (unique.length !== 1) {
		console.error('BUILD FAILED: version mismatch across release locations');
		entries.forEach(([k, val]) => console.error(`  ${k.padEnd(10)} ${val}`));
		process.exit(1);
	}
	const version = unique[0];
	if (!/^\d+\.\d+\.\d+$/.test(version)) {
		fail(`version "${version}" is not X.Y.Z — an unreleased placeholder must not be packaged`);
	}
	console.log(`G1 version      : ${version} (header, constant, stable tag, changelog all agree)`);
	return version;
}

// ── G5: static checks ──────────────────────────────────────────────────────

function gateChecks() {
	process.env.AEOMATIC_BUILD_QUIET = '1';

	const structure = require('./check-structure.js');
	const references = require('./check-references.js');

	const phpFiles = structure.collectPhpFiles(ROOT);
	let broken = 0;
	for (const f of phpFiles) {
		const errs = structure.checkSource(fs.readFileSync(f, 'utf8'));
		if (errs.length) {
			broken++;
			console.error(`  ${path.relative(ROOT, f)}: ${errs[0]}`);
		}
	}
	if (broken) fail(`${broken} PHP file(s) failed the structural check`);
	console.log(`G5 structure    : ${phpFiles.length}/${phpFiles.length} PHP files clean`);

	const refFiles = references.collectPhpFiles(ROOT).map((p) => ({
		path: path.relative(ROOT, p),
		src: fs.readFileSync(p, 'utf8'),
	}));
	const { missing, guarded } = references.analyze(refFiles);
	if (missing.length) {
		missing.forEach((m) => console.error('  ' + m));
		fail(`${missing.length} dangling AEOMATIC_*:: reference(s)`);
	}
	if (guarded.length) {
		console.log('G5 references   : WARNING — guarded references with no target:');
		guarded.forEach((m) => console.log('     ' + m));
	} else {
		console.log('G5 references   : no dangling references');
	}
}

// ── G2: staging via allow-list ─────────────────────────────────────────────

function collectShippedFiles() {
	const out = [];

	function walk(absDir, relDir) {
		for (const entry of fs.readdirSync(absDir, { withFileTypes: true })) {
			const abs = path.join(absDir, entry.name);
			const rel = relDir ? relDir + '/' + entry.name : entry.name;
			if (FORBIDDEN.some((re) => re.test(rel))) continue;
			if (entry.isDirectory()) walk(abs, rel);
			else if (entry.isFile()) out.push({ abs, rel });
		}
	}

	for (const item of INCLUDE) {
		const abs = path.join(ROOT, item);
		if (!fs.existsSync(abs)) {
			console.log(`   (absent, skipped) ${item}`);
			continue;
		}
		if (fs.statSync(abs).isDirectory()) walk(abs, item);
		else out.push({ abs, rel: item });
	}

	for (const { rel } of out) {
		if (FORBIDDEN.some((re) => re.test(rel))) fail(`forbidden path slipped into the archive: ${rel}`);
	}

	out.sort((a, b) => (a.rel < b.rel ? -1 : a.rel > b.rel ? 1 : 0));
	console.log(`G2 dev files    : excluded (allow-list of ${INCLUDE.length}, ${out.length} files staged)`);
	return out;
}

// ── G3 + G4: write the ZIP ourselves ───────────────────────────────────────

const CRC_TABLE = (() => {
	const t = new Int32Array(256);
	for (let n = 0; n < 256; n++) {
		let c = n;
		for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
		t[n] = c;
	}
	return t;
})();

function crc32(buf) {
	let c = -1;
	for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
	return (c ^ -1) >>> 0;
}

function writeZip(files, outPath) {
	// Fixed timestamp keeps rebuilds byte-identical, so a diff between two
	// archives means the source actually changed.
	const DOS_TIME = 0;
	const DOS_DATE = ((2026 - 1980) << 9) | (1 << 5) | 1;

	const locals = [];
	const central = [];
	let offset = 0;
	const names = [];

	for (const { abs, rel } of files) {
		// G3: every entry is prefixed with the slug folder.
		// G4: '/' is forced, never path.sep.
		const name = SLUG + '/' + rel.split(path.sep).join('/');
		if (name.includes('\\')) fail(`backslash in entry name: ${name}`);
		names.push(name);

		const raw = fs.readFileSync(abs);
		const deflated = zlib.deflateRawSync(raw, { level: 9 });
		const useDeflate = deflated.length < raw.length;
		const data = useDeflate ? deflated : raw;
		const method = useDeflate ? 8 : 0;
		const crc = crc32(raw);
		const nameBuf = Buffer.from(name, 'utf8');

		const lh = Buffer.alloc(30);
		lh.writeUInt32LE(0x04034b50, 0);
		lh.writeUInt16LE(20, 4);
		lh.writeUInt16LE(0x0800, 6); // UTF-8 names
		lh.writeUInt16LE(method, 8);
		lh.writeUInt16LE(DOS_TIME, 10);
		lh.writeUInt16LE(DOS_DATE, 12);
		lh.writeUInt32LE(crc, 14);
		lh.writeUInt32LE(data.length, 18);
		lh.writeUInt32LE(raw.length, 22);
		lh.writeUInt16LE(nameBuf.length, 26);
		lh.writeUInt16LE(0, 28);
		locals.push(lh, nameBuf, data);

		const ch = Buffer.alloc(46);
		ch.writeUInt32LE(0x02014b50, 0);
		ch.writeUInt16LE(0x031e, 4); // made by UNIX -> separators read as POSIX
		ch.writeUInt16LE(20, 6);
		ch.writeUInt16LE(0x0800, 8);
		ch.writeUInt16LE(method, 10);
		ch.writeUInt16LE(DOS_TIME, 12);
		ch.writeUInt16LE(DOS_DATE, 14);
		ch.writeUInt32LE(crc, 16);
		ch.writeUInt32LE(data.length, 20);
		ch.writeUInt32LE(raw.length, 24);
		ch.writeUInt16LE(nameBuf.length, 28);
		ch.writeUInt16LE(0, 30);
		ch.writeUInt16LE(0, 32);
		ch.writeUInt16LE(0, 34);
		ch.writeUInt16LE(0, 36);
		ch.writeUInt32LE(0o644 << 16, 38);
		ch.writeUInt32LE(offset, 42);
		central.push(ch, nameBuf);

		offset += lh.length + nameBuf.length + data.length;
	}

	for (const required of REQUIRED_IN_ARCHIVE) {
		if (!names.includes(required)) fail(`required entry missing from archive: ${required}`);
	}
	const roots = [...new Set(names.map((n) => n.split('/')[0]))];
	if (roots.length !== 1 || roots[0] !== SLUG) {
		fail(`archive must have exactly one root named "${SLUG}", got: ${roots.join(', ')}`);
	}
	console.log(`G3 wrapper      : single root "${SLUG}/"`);
	console.log('G4 separators   : all entry names use "/"');

	const centralBuf = Buffer.concat(central);
	const eocd = Buffer.alloc(22);
	eocd.writeUInt32LE(0x06054b50, 0);
	eocd.writeUInt16LE(0, 4);
	eocd.writeUInt16LE(0, 6);
	eocd.writeUInt16LE(names.length, 8);
	eocd.writeUInt16LE(names.length, 10);
	eocd.writeUInt32LE(centralBuf.length, 12);
	eocd.writeUInt32LE(offset, 16);
	eocd.writeUInt16LE(0, 20);

	fs.writeFileSync(outPath, Buffer.concat([...locals, centralBuf, eocd]));
	return names.length;
}

// ── main ───────────────────────────────────────────────────────────────────

function main(argv) {
	const outIdx = argv.indexOf('--out');
	const outDir = outIdx !== -1 && argv[outIdx + 1]
		? path.resolve(argv[outIdx + 1])
		: path.resolve(ROOT, '..');

	console.log('Packaging AEOmatic\n');

	const version = gateVersions();

	if (argv.includes('--skip-checks')) {
		console.log('G5 checks       : SKIPPED (--skip-checks) — do not ship this build');
	} else {
		gateChecks();
	}

	const files = collectShippedFiles();
	if (!files.length) fail('nothing staged');

	if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });
	const outPath = path.join(outDir, `${SLUG}-${version}.zip`);

	if (fs.existsSync(outPath)) {
		console.log(`\nNOTE: overwriting existing ${path.basename(outPath)}`);
	}

	const count = writeZip(files, outPath);
	const kb = (fs.statSync(outPath).size / 1024).toFixed(1);

	console.log('\nAll gates passed.');
	console.log(`  archive : ${outPath}`);
	console.log(`  entries : ${count}`);
	console.log(`  size    : ${kb} KB`);
	console.log('\nStill unverified by this script: runtime behaviour. Install on a');
	console.log('staging site before release — see README.md, Testing instructions.');
	return 0;
}

if (require.main === module) {
	process.exit(main(process.argv.slice(2)));
}
