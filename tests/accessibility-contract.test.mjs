import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = (path) =>
	readFile(new URL(`../${path}`, import.meta.url), 'utf8');
const [
	renderer,
	embedCss,
	blockCss,
	presetCss,
	blockEditor,
	presetEditor,
	demoSource,
] = await Promise.all([
	read('src/render/class-embed-renderer.php'),
	read('assets/css/embed.css'),
	read('assets/css/block-editor.css'),
	read('assets/css/preset-editor.css'),
	read('assets/src/block-editor.jsx'),
	read('assets/src/preset-editor.jsx'),
	read('tests/fixtures/accessibility-demo.json'),
]);
const demo = JSON.parse(demoSource);

assert.match(renderer, /role="status" aria-live="polite" aria-atomic="true"/);
assert.match(renderer, /<iframe[^>]+title="%12\$s"/);
assert.match(embedCss, /max-inline-size:\s*100%/);
assert.match(embedCss, /min-inline-size:\s*0/);
assert.match(embedCss, /:focus-visible/);
assert.match(embedCss, /prefers-reduced-motion:\s*reduce/);
assert.match(blockCss, /overflow-x:\s*auto/);
assert.match(presetCss, /grid-template-columns:\s*1fr/);
assert.match(blockEditor, /errorFocus\.current\?\.focus\(\)/);
assert.match(blockEditor, /aria-label=\{__\('Accent color'/);
assert.match(presetEditor, /savedErrorFocus\.current\?\.focus\(\)/);
assert.match(presetEditor, /aria-live="polite"/);
assert.deepEqual(demo.viewports, [320, 375, 768, 1440]);
assert.equal(demo.scenarios['long-content'].participants[0].logo, null);
assert.equal(demo.scenarios['empty-standings'].participants.length, 0);
assert.ok(demo.scenarios['long-content'].participants[0].name.length > 60);

process.stdout.write('Accessibility and responsive contracts passed.\n');
