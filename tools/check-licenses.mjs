import { readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const repositoryRoot = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	'..'
);
const packageJson = JSON.parse(
	await readFile(path.join(repositoryRoot, 'package.json'), 'utf8')
);
const allowedLicenses = new Set([
	'Apache-2.0',
	'BSD-2-Clause',
	'BSD-3-Clause',
	'GPL-2.0',
	'GPL-2.0-or-later',
	'ISC',
	'MIT',
]);

if (packageJson.dependencies !== undefined) {
	throw new Error(
		'Runtime npm dependencies require a separate release review.'
	);
}

for (const dependency of Object.keys(packageJson.devDependencies)) {
	const dependencyMetadata = JSON.parse(
		await readFile(
			path.join(
				repositoryRoot,
				'node_modules',
				...dependency.split('/'),
				'package.json'
			),
			'utf8'
		)
	);
	const licenses = Array.isArray(dependencyMetadata.license)
		? dependencyMetadata.license
		: [dependencyMetadata.license];

	if (
		licenses.some(
			(license) =>
				typeof license !== 'string' || !allowedLicenses.has(license)
		)
	) {
		throw new Error(
			`Unreviewed license for ${dependency}: ${licenses.join(', ')}`
		);
	}
}

process.stdout.write(
	`License check passed: ${
		Object.keys(packageJson.devDependencies).length
	} direct development dependencies reviewed; no runtime dependencies.\n`
);
