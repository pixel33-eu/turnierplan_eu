import { spawn, spawnSync } from 'node:child_process';
import path from 'node:path';
import process from 'node:process';
import { createRequire } from 'node:module';
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
	'playground-smoke-blueprint.json'
);
const requestedPort = Number.parseInt(
	process.env.TPEU_SMOKE_PORT ?? '8890',
	10
);

if (!Number.isInteger(requestedPort) || requestedPort < 1024) {
	throw new Error('TPEU_SMOKE_PORT must be an integer of at least 1024.');
}

const baseUrl = `http://127.0.0.1:${requestedPort}`;
const output = [];
let exitCode = null;

const child = spawn(
	process.execPath,
	[
		playgroundCli,
		'server',
		'--port',
		String(requestedPort),
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
		stdio: ['ignore', 'pipe', 'pipe'],
	}
);

const collectOutput = (chunk) => {
	output.push(chunk.toString());

	if (output.length > 200) {
		output.shift();
	}
};

child.stdout.on('data', collectOutput);
child.stderr.on('data', collectOutput);
child.once('exit', (code) => {
	exitCode = code;
});

const delay = (milliseconds) =>
	new Promise((resolve) => {
		setTimeout(resolve, milliseconds);
	});

const fetchAdminPage = async (pathname) => {
	const loginResponse = await fetch(`${baseUrl}${pathname}`, {
		redirect: 'manual',
	});
	const setCookies = loginResponse.headers.getSetCookie();
	const cookie = setCookies
		.map((header) => header.split(';', 1)[0])
		.join('; ');

	if (loginResponse.status === 200) {
		return {
			body: await loginResponse.text(),
			php: loginResponse.headers.get('x-powered-by'),
			cookie,
		};
	}

	if (cookie === '') {
		throw new Error('WordPress did not provide an automatic login cookie.');
	}

	const adminResponse = await fetch(`${baseUrl}${pathname}`, {
		headers: { cookie },
		redirect: 'follow',
	});

	if (!adminResponse.ok) {
		throw new Error(
			`WordPress admin request returned HTTP ${adminResponse.status}.`
		);
	}

	return {
		body: await adminResponse.text(),
		php: adminResponse.headers.get('x-powered-by'),
		cookie,
	};
};

const stopChild = () => {
	if (child.exitCode !== null || child.pid === undefined) {
		return;
	}

	if (process.platform === 'win32') {
		spawnSync('taskkill', ['/pid', String(child.pid), '/T', '/F'], {
			stdio: 'ignore',
		});
	} else {
		child.kill('SIGTERM');
	}
};

try {
	const deadline = Date.now() + 120_000;
	let pluginPage;

	while (Date.now() < deadline) {
		if (exitCode !== null) {
			throw new Error(
				`WordPress Playground exited early with code ${exitCode}.\n${output.join(
					''
				)}`
			);
		}

		try {
			pluginPage = await fetchAdminPage('/wp-admin/plugins.php');
			break;
		} catch {
			await delay(500);
		}
	}

	if (pluginPage === undefined) {
		throw new Error(
			`WordPress Playground did not become ready in 120 seconds.\n${output.join(
				''
			)}`
		);
	}

	if (!pluginPage.php?.startsWith('PHP/8.3.')) {
		throw new Error(`Expected PHP 8.3, received ${pluginPage.php}.`);
	}

	if (!/\bversion-6-5\b/.test(pluginPage.body)) {
		throw new Error('The smoke environment is not running WordPress 6.5.');
	}

	if (
		!/<tr class="active"[^>]+data-plugin="turnierplan-eu\/turnierplan-eu\.php"/.test(
			pluginPage.body
		)
	) {
		throw new Error('The Turnierplan.eu plugin is not active.');
	}

	if (!/Version 0\.1\.0/.test(pluginPage.body)) {
		throw new Error('The active plugin does not report version 0.1.0.');
	}

	if (/Fatal error/i.test(pluginPage.body)) {
		throw new Error('The plugin page contains a PHP fatal error.');
	}

	const optionsResponse = await fetch(`${baseUrl}/wp-admin/options.php`, {
		headers: { cookie: pluginPage.cookie },
		redirect: 'follow',
	});
	const optionsPage = await optionsResponse.text();

	if (
		!/name="tpeu_plugin_version"/.test(optionsPage) ||
		!/name="tpeu_plugin_version"[^>]+value="0\.1\.0"/.test(optionsPage)
	) {
		throw new Error(
			'The activation hook did not store plugin version 0.1.0.'
		);
	}

	process.stdout.write(
		'WordPress smoke test passed: WordPress 6.5, PHP 8.3, plugin active, version option stored, no fatal error.\n'
	);
} finally {
	stopChild();
}
