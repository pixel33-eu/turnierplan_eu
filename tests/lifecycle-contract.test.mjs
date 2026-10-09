import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const repositoryRoot = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	'..'
);
const uninstall = await readFile(
	path.join(repositoryRoot, 'uninstall.php'),
	'utf8'
);

if (
	!uninstall.includes("defined( 'WP_UNINSTALL_PLUGIN' )") ||
	!uninstall.includes('Uninstaller::run()')
) {
	throw new Error(
		'uninstall.php must guard direct access and delegate to the site-aware uninstaller.'
	);
}

const phpFiles = [];
const collectPhpFiles = async (directory) => {
	for (const entry of await readdir(directory, { withFileTypes: true })) {
		const target = path.join(directory, entry.name);
		if (entry.isDirectory()) {
			await collectPhpFiles(target);
		} else if (entry.name.endsWith('.php')) {
			phpFiles.push(target);
		}
	}
};

await collectPhpFiles(path.join(repositoryRoot, 'src'));

for (const phpFile of phpFiles) {
	const source = await readFile(phpFile, 'utf8');
	const relative = path.relative(repositoryRoot, phpFile);

	if (
		source.includes('set_transient(') &&
		!relative.endsWith(
			path.join('src', 'cache', 'class-transient-registry.php')
		)
	) {
		throw new Error(`Untracked transient write found in ${relative}.`);
	}
}

const uninstaller = await readFile(
	path.join(repositoryRoot, 'src', 'class-uninstaller.php'),
	'utf8'
);

if (uninstaller.includes('$wpdb') || /\bLIKE\b/u.test(uninstaller)) {
	throw new Error('Uninstall cleanup must not use broad direct database queries.');
}

process.stdout.write(
	'Lifecycle contract test passed: guarded entry point, tracked transients, no broad uninstall query.\n'
);
