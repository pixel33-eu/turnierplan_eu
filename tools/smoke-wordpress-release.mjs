/* SPDX-License-Identifier: GPL-2.0-or-later */
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const repositoryRoot = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	'..'
);
const version = (
	await readFile(path.join(repositoryRoot, 'VERSION'), 'utf8')
).trim();

const archive = path.join(
	repositoryRoot,
	'dist',
	`turnierplan-eu-${version}.zip`
);
const runner = path.join(repositoryRoot, 'tools', 'smoke-wordpress-run.mjs');
const fixtureDirectory = path.join(repositoryRoot, 'tests', 'fixtures');
const temporaryDirectory = await mkdtemp(
	path.join(os.tmpdir(), 'turnierplan-eu-release-')
);

try {
	const build = spawnSync(
		process.execPath,
		[path.join(repositoryRoot, 'tools', 'build-release.mjs')],
		{
			cwd: repositoryRoot,
			encoding: 'utf8',
			timeout: 120_000,
		}
	);

	process.stdout.write(build.stdout ?? '');
	process.stderr.write(build.stderr ?? '');

	if (build.status !== 0) {
		process.exitCode = build.status ?? 1;
	} else {
		const extractCommand = process.platform === 'win32' ? 'tar' : 'unzip';
		const extractArguments =
			process.platform === 'win32'
				? ['-xf', archive, '-C', temporaryDirectory]
				: ['-q', archive, '-d', temporaryDirectory];
		const extract = spawnSync(extractCommand, extractArguments, {
			cwd: repositoryRoot,
			encoding: 'utf8',
			timeout: 30_000,
		});

		if (extract.status !== 0) {
			throw new Error(
				`Release archive extraction failed.\n${extract.stdout ?? ''}${extract.stderr ?? ''}`
			);
		}

		const smoke = spawnSync(process.execPath, [runner], {
			cwd: repositoryRoot,
			encoding: 'utf8',
			env: {
				...process.env,
				TPEU_SMOKE_BLUEPRINT: 'playground-release-blueprint.json',
				TPEU_SMOKE_FIXTURE_DIR: fixtureDirectory,
				TPEU_SMOKE_LABEL:
					'WordPress 6.5, PHP 8.3, fresh release ZIP install',
				TPEU_SMOKE_PLUGIN_DIR: path.join(
					temporaryDirectory,
					'turnierplan-eu'
				),
				TPEU_SMOKE_PORT: '8894',
			},
			timeout: 180_000,
		});

		process.stdout.write(smoke.stdout ?? '');
		process.stderr.write(smoke.stderr ?? '');
		process.exitCode = smoke.status ?? 1;
	}
} finally {
	await rm(temporaryDirectory, { recursive: true, force: true });
}
