import { spawnSync } from 'node:child_process';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const runner = path.join(
	path.dirname(fileURLToPath(import.meta.url)),
	'smoke-wordpress-run.mjs'
);
const retryableStartupError =
	'Could not write to "/internal/shared/consts.json": Invalid argument.';

for (let attempt = 1; attempt <= 2; attempt += 1) {
	const result = spawnSync(process.execPath, [runner], {
		cwd: process.cwd(),
		encoding: 'utf8',
		env: process.env,
		timeout: 150_000,
	});
	const stdout = result.stdout ?? '';
	const stderr = result.stderr ?? '';
	const combined = stdout + stderr;

	if (result.status === 0) {
		process.stdout.write(stdout);
		process.stderr.write(stderr);
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
