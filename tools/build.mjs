import { build, context } from 'esbuild';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const repositoryRoot = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	'..'
);
const outputDirectory = path.join(repositoryRoot, 'build');
const watch = process.argv.includes('--watch');
const version = (
	await readFile(path.join(repositoryRoot, 'VERSION'), 'utf8')
).trim();
const options = {
	entryPoints: {
		index: path.join(repositoryRoot, 'assets', 'src', 'index.js'),
		'block-editor': path.join(
			repositoryRoot,
			'assets',
			'src',
			'block-editor.jsx'
		),
		'preset-editor': path.join(
			repositoryRoot,
			'assets',
			'src',
			'preset-editor.jsx'
		),
	},
	bundle: true,
	format: 'iife',
	jsxFactory: 'wp.element.createElement',
	jsxFragment: 'wp.element.Fragment',
	minify: !watch,
	outdir: outputDirectory,
	platform: 'browser',
	sourcemap: watch,
	target: ['es2020'],
};

await mkdir(outputDirectory, { recursive: true });
await writeFile(
	path.join(outputDirectory, 'index.asset.php'),
	`<?php return array( 'dependencies' => array(), 'version' => '${version}' );\n`,
	'utf8'
);
await writeFile(
	path.join(outputDirectory, 'block-editor.asset.php'),
	`<?php return array( 'dependencies' => array( 'wp-api-fetch', 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ), 'version' => '${version}' );\n`,
	'utf8'
);
await writeFile(
	path.join(outputDirectory, 'preset-editor.asset.php'),
	`<?php return array( 'dependencies' => array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ), 'version' => '${version}' );\n`,
	'utf8'
);

if (watch) {
	const buildContext = await context(options);
	await buildContext.watch();
	process.stdout.write('Watching JavaScript sources for changes.\n');

	const shutDown = async () => {
		await buildContext.dispose();
		process.exit(0);
	};

	process.once('SIGINT', shutDown);
	process.once('SIGTERM', shutDown);
} else {
	await build(options);
	process.stdout.write(`JavaScript build passed for version ${version}.\n`);
}
