/* SPDX-License-Identifier: GPL-2.0-or-later */
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const directory = path.join(root, 'wordpress-org-assets');
const expected = new Map([
	['banner-772x250.png', [772, 250]],
	['banner-1544x500.png', [1544, 500]],
	['icon-128x128.png', [128, 128]],
	['icon-256x256.png', [256, 256]],
	['screenshot-1.png', [1200, 900]],
	['screenshot-2.png', [1200, 900]],
	['screenshot-3.png', [1200, 900]],
	['screenshot-4.png', [1200, 900]],
]);
const pngSignature = Buffer.from([
	0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a,
]);

for (const [file, [expectedWidth, expectedHeight]] of expected) {
	const contents = await readFile(path.join(directory, file));

	if (!contents.subarray(0, 8).equals(pngSignature)) {
		throw new Error(`${file} is not a PNG file.`);
	}

	const width = contents.readUInt32BE(16);
	const height = contents.readUInt32BE(20);

	if (width !== expectedWidth || height !== expectedHeight) {
		throw new Error(
			`${file} must be ${expectedWidth}x${expectedHeight}, found ${width}x${height}.`
		);
	}
}

process.stdout.write(
	`WordPress.org directory assets passed: ${expected.size} PNG files with exact dimensions.\n`
);
