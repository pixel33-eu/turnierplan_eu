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
const requiredProperties = [
	'name',
	'version',
	'description',
	'author',
	'license',
	'keywords',
	'homepage',
	'repository',
	'bugs',
	'engines',
	'devDependencies',
	'scripts',
];

for (const property of requiredProperties) {
	if (packageJson[property] === undefined) {
		throw new Error(`package.json is missing ${property}.`);
	}
}

if (packageJson.private !== true) {
	throw new Error('The development package must remain private.');
}

if (packageJson.license !== 'GPL-2.0-or-later') {
	throw new Error('The package license must be GPL-2.0-or-later.');
}

for (const [dependency, version] of Object.entries(
	packageJson.devDependencies
)) {
	if (!/^\d+\.\d+\.\d+$/.test(version)) {
		throw new Error(
			`Development dependency ${dependency} must use an exact version.`
		);
	}
}

process.stdout.write(
	`Package metadata check passed: ${requiredProperties.length} required properties and exact development versions.\n`
);
