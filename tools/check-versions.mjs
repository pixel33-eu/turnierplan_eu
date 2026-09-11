import { readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const repositoryRoot = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	'..'
);

const readText = async (relativePath) =>
	readFile(path.join(repositoryRoot, relativePath), 'utf8');

const version = (await readText('VERSION')).trim();
const pluginFile = await readText('turnierplan-eu.php');
const readme = await readText('readme.txt');
const packageJson = JSON.parse(await readText('package.json'));
const composerJson = JSON.parse(await readText('composer.json'));
const requirementsFile = await readText('src/class-requirements.php');

const sources = {
	'plugin header': pluginFile.match(/^ \* Version:\s+(.+)$/m)?.[1].trim(),
	'plugin constant': pluginFile.match(
		/define\(\s*'TPEU_VERSION',\s*'([^']+)'\s*\)/
	)?.[1],
	'package.json': packageJson.version,
	'readme stable tag': readme.match(/^Stable tag:\s+(.+)$/m)?.[1].trim(),
};

const mismatches = Object.entries(sources).filter(
	([, candidate]) => candidate !== version
);

if (mismatches.length > 0) {
	for (const [source, candidate] of mismatches) {
		process.stderr.write(
			`Version mismatch in ${source}: expected ${version}, found ${
				candidate ?? 'nothing'
			}\n`
		);
	}

	process.exitCode = 1;
} else {
	const wordpressMinimum = requirementsFile.match(
		/MINIMUM_WORDPRESS\s*=\s*'([^']+)'/
	)?.[1];
	const phpMinimum = requirementsFile.match(
		/MINIMUM_PHP\s*=\s*'([^']+)'/
	)?.[1];
	const minimumSources = {
		WordPress: {
			required: wordpressMinimum,
			values: {
				'plugin header': pluginFile
					.match(/^ \* Requires at least:\s+(.+)$/m)?.[1]
					.trim(),
				'readme.txt': readme
					.match(/^Requires at least:\s+(.+)$/m)?.[1]
					.trim(),
			},
		},
		PHP: {
			required: phpMinimum,
			values: {
				'composer.json': composerJson.require.php?.replace(/^>=/, ''),
				'plugin header': pluginFile
					.match(/^ \* Requires PHP:\s+(.+)$/m)?.[1]
					.trim(),
				'readme.txt': readme
					.match(/^Requires PHP:\s+(.+)$/m)?.[1]
					.trim(),
			},
		},
	};

	for (const [runtime, minimum] of Object.entries(minimumSources)) {
		for (const [source, candidate] of Object.entries(minimum.values)) {
			if (candidate !== minimum.required) {
				throw new Error(
					`${runtime} minimum mismatch in ${source}: expected ${minimum.required}, found ${candidate}`
				);
			}
		}
	}

	process.stdout.write(
		`Version check passed: ${version} is consistent in ${
			Object.keys(sources).length + 1
		} sources; WordPress ${wordpressMinimum} and PHP ${phpMinimum} minimums are consistent.\n`
	);
}
