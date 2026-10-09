import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const repositoryRoot = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	'..'
);
const playgroundPackage = require.resolve('@wp-playground/cli/package.json');
const playgroundCli = path.join(
	path.dirname(playgroundPackage),
	'wp-playground.js'
);
const blueprint = path.join(
	repositoryRoot,
	'tests',
	'fixtures',
	'playground-multisite-blueprint.json'
);
const retryableStartupError =
	'Could not write to "/internal/shared/consts.json": Invalid argument.';

for (let attempt = 1; attempt <= 2; attempt += 1) {
	const result = spawnSync(
		process.execPath,
		[
			playgroundCli,
			'run-blueprint',
			'--site-url',
			'http://localhost',
			'--php',
			'8.3',
			'--wp',
			'https://wordpress.org/wordpress-6.5.zip',
			'--blueprint',
			blueprint,
			'--mount-dir',
			repositoryRoot,
			'/wordpress/wp-content/plugins/turnierplan-eu',
		],
		{
			cwd: repositoryRoot,
			encoding: 'utf8',
			env: process.env,
			timeout: 150_000,
		}
	);
	const stdout = result.stdout ?? '';
	const stderr = result.stderr ?? '';
	const combined = stdout + stderr;

	if (result.status === 0) {
		process.stdout.write(stdout);
		process.stderr.write(stderr);
		process.stdout.write(
			'WordPress multisite lifecycle smoke test passed.\n'
		);
		break;
	}

	if (attempt === 1 && combined.includes(retryableStartupError)) {
		process.stderr.write(
			'WordPress Playground hit a transient Windows startup error; retrying once.\n'
		);
		continue;
	}

	process.stdout.write(stdout);
	process.stderr.write(stderr);
	process.exitCode = result.status ?? 1;
	break;
}
