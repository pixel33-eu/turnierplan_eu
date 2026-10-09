/* SPDX-License-Identifier: GPL-2.0-or-later */
/* eslint-disable no-bitwise -- ZIP headers and CRC-32 require unsigned bit operations. */
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import {
	lstat,
	mkdir,
	readFile,
	readdir,
	rm,
	writeFile,
} from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { deflateRawSync } from 'node:zlib';
import { fileURLToPath } from 'node:url';

const repositoryRoot = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	'..'
);
const pluginSlug = 'turnierplan-eu';
const version = (
	await readFile(path.join(repositoryRoot, 'VERSION'), 'utf8')
).trim();
const distributionDirectory = path.join(repositoryRoot, 'dist');
const archivePath = path.join(
	distributionDirectory,
	`${pluginSlug}-${version}.zip`
);
const runtimePaths = [
	'LICENSE',
	'assets/css',
	'blocks',
	'build',
	'languages',
	'readme.txt',
	'src',
	'turnierplan-eu.php',
	'uninstall.php',
];

const crcTable = Array.from({ length: 256 }, (_, index) => {
	let value = index;

	for (let bit = 0; bit < 8; bit += 1) {
		value = (value & 1) === 1 ? 0xedb88320 ^ (value >>> 1) : value >>> 1;
	}

	return value >>> 0;
});

const crc32 = (data) => {
	let value = 0xffffffff;

	for (const byte of data) {
		value = crcTable[(value ^ byte) & 0xff] ^ (value >>> 8);
	}

	return (value ^ 0xffffffff) >>> 0;
};

const git = (...arguments_) =>
	execFileSync('git', arguments_, {
		cwd: repositoryRoot,
		encoding: 'utf8',
	}).trim();

const releaseTimestamp = () => {
	const sourceEpoch = process.env.SOURCE_DATE_EPOCH;
	const seconds =
		sourceEpoch === undefined
			? Number.parseInt(git('show', '-s', '--format=%ct', 'HEAD'), 10)
			: Number.parseInt(sourceEpoch, 10);

	if (!Number.isInteger(seconds) || seconds < 315532800) {
		throw new Error(
			'SOURCE_DATE_EPOCH or the Git commit time must be on or after 1980-01-01.'
		);
	}

	return new Date(seconds * 1000);
};

const dosTimestamp = (date) => ({
	date:
		((date.getUTCFullYear() - 1980) << 9) |
		((date.getUTCMonth() + 1) << 5) |
		date.getUTCDate(),
	time:
		(date.getUTCHours() << 11) |
		(date.getUTCMinutes() << 5) |
		Math.floor(date.getUTCSeconds() / 2),
});

const collectFiles = async (relativePath) => {
	const absolutePath = path.join(repositoryRoot, relativePath);
	const stats = await lstat(absolutePath);

	if (stats.isSymbolicLink()) {
		throw new Error(
			`Release input may not be a symbolic link: ${relativePath}`
		);
	}

	if (stats.isFile()) {
		return [relativePath.replaceAll('\\', '/')];
	}

	if (!stats.isDirectory()) {
		throw new Error(`Release input is not a regular file: ${relativePath}`);
	}

	const entries = await readdir(absolutePath, { withFileTypes: true });
	const files = [];

	for (const entry of entries.sort((left, right) =>
		left.name.localeCompare(right.name, 'en')
	)) {
		files.push(
			...(await collectFiles(path.join(relativePath, entry.name)))
		);
	}

	return files;
};

const createArchive = async (relativeFiles, timestamp) => {
	const localParts = [];
	const centralParts = [];
	let offset = 0;
	const dos = dosTimestamp(timestamp);

	for (const relativeFile of relativeFiles) {
		const archiveName = `${pluginSlug}/${relativeFile}`;
		const name = Buffer.from(archiveName, 'utf8');
		const contents = await readFile(
			path.join(repositoryRoot, relativeFile)
		);
		const compressed = deflateRawSync(contents, { level: 9 });
		const checksum = crc32(contents);
		const localHeader = Buffer.alloc(30);

		localHeader.writeUInt32LE(0x04034b50, 0);
		localHeader.writeUInt16LE(20, 4);
		localHeader.writeUInt16LE(0x0800, 6);
		localHeader.writeUInt16LE(8, 8);
		localHeader.writeUInt16LE(dos.time, 10);
		localHeader.writeUInt16LE(dos.date, 12);
		localHeader.writeUInt32LE(checksum, 14);
		localHeader.writeUInt32LE(compressed.length, 18);
		localHeader.writeUInt32LE(contents.length, 22);
		localHeader.writeUInt16LE(name.length, 26);
		localParts.push(localHeader, name, compressed);

		const centralHeader = Buffer.alloc(46);
		centralHeader.writeUInt32LE(0x02014b50, 0);
		centralHeader.writeUInt16LE(0x0314, 4);
		centralHeader.writeUInt16LE(20, 6);
		centralHeader.writeUInt16LE(0x0800, 8);
		centralHeader.writeUInt16LE(8, 10);
		centralHeader.writeUInt16LE(dos.time, 12);
		centralHeader.writeUInt16LE(dos.date, 14);
		centralHeader.writeUInt32LE(checksum, 16);
		centralHeader.writeUInt32LE(compressed.length, 20);
		centralHeader.writeUInt32LE(contents.length, 24);
		centralHeader.writeUInt16LE(name.length, 28);
		centralHeader.writeUInt32LE((0o100644 * 0x10000) >>> 0, 38);
		centralHeader.writeUInt32LE(offset, 42);
		centralParts.push(centralHeader, name);

		offset += localHeader.length + name.length + compressed.length;
	}

	const centralDirectory = Buffer.concat(centralParts);
	const end = Buffer.alloc(22);

	end.writeUInt32LE(0x06054b50, 0);
	end.writeUInt16LE(relativeFiles.length, 8);
	end.writeUInt16LE(relativeFiles.length, 10);
	end.writeUInt32LE(centralDirectory.length, 12);
	end.writeUInt32LE(offset, 16);

	return Buffer.concat([...localParts, centralDirectory, end]);
};

const requiredFiles = [
	'LICENSE',
	'assets/css/embed.css',
	'blocks/embed/block.json',
	'build/block-editor.asset.php',
	'build/block-editor.js',
	'build/index.asset.php',
	'build/index.js',
	'languages/turnierplan-eu.pot',
	'readme.txt',
	'src/class-autoloader.php',
	'turnierplan-eu.php',
	'uninstall.php',
];

const files = (
	await Promise.all(
		runtimePaths.map((runtimePath) => collectFiles(runtimePath))
	)
)
	.flat()
	.sort((left, right) => left.localeCompare(right, 'en'));

for (const requiredFile of requiredFiles) {
	if (!files.includes(requiredFile)) {
		throw new Error(`Required runtime file is missing: ${requiredFile}`);
	}
}

const forbidden = files.filter((file) =>
	/(^|\/)(?:\.env|node_modules|vendor|tests|tools|docs)(?:\/|$)/u.test(file)
);

if (forbidden.length > 0) {
	throw new Error(
		`Development files entered the release: ${forbidden.join(', ')}`
	);
}

if (process.argv.includes('--require-tag')) {
	const expectedTag = `v${version}`;
	const exactTag = git('describe', '--tags', '--exact-match', 'HEAD');
	const dirty = git('status', '--porcelain');

	if (exactTag !== expectedTag || dirty !== '') {
		throw new Error(
			`Tagged releases require clean tag ${expectedTag}; found ${exactTag || 'no tag'}${dirty === '' ? '' : ' with local changes'}.`
		);
	}
}

const timestamp = releaseTimestamp();
const archive = await createArchive(files, timestamp);

if (process.argv.includes('--verify-reproducible')) {
	const secondArchive = await createArchive(files, timestamp);

	if (!archive.equals(secondArchive)) {
		throw new Error(
			'Two release builds from the same source were not identical.'
		);
	}
}

const checksum = createHash('sha256').update(archive).digest('hex');
const commit = git('rev-parse', 'HEAD');
const dirty = git('status', '--porcelain') !== '';
const manifest = {
	archive: path.basename(archivePath),
	commit,
	dirty,
	files: files.map((file) => `${pluginSlug}/${file}`),
	pluginSlug,
	sha256: checksum,
	sourceDateEpoch: Math.floor(timestamp.getTime() / 1000),
	version,
};

await rm(distributionDirectory, { recursive: true, force: true });
await mkdir(distributionDirectory, { recursive: true });
await writeFile(archivePath, archive);
await writeFile(
	`${archivePath}.sha256`,
	`${checksum}  ${path.basename(archivePath)}\n`
);
await writeFile(
	path.join(distributionDirectory, `${pluginSlug}-${version}.manifest.json`),
	`${JSON.stringify(manifest, null, 2)}\n`
);

process.stdout.write(
	`Release package built: ${path.relative(repositoryRoot, archivePath)} (${files.length} files, SHA-256 ${checksum}).${dirty ? ' Source tree has local changes.' : ''}\n`
);
