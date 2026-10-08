import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const browser = [
	process.env.TPEU_BROWSER_BIN,
	'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
	'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find((candidate) => candidate && existsSync(candidate));

if (!browser) {
	throw new Error('Set TPEU_BROWSER_BIN to a Chromium-compatible browser.');
}

const [embedCss, blockCss, presetCss, demoSource] = await Promise.all([
	readFile(path.join(root, 'assets/css/embed.css'), 'utf8'),
	readFile(path.join(root, 'assets/css/block-editor.css'), 'utf8'),
	readFile(path.join(root, 'assets/css/preset-editor.css'), 'utf8'),
	readFile(path.join(root, 'tests/fixtures/accessibility-demo.json'), 'utf8'),
]);
const demo = JSON.parse(demoSource);
const widths = demo.viewports;
const longScenario = demo.scenarios['long-content'];
const escapeHtml = (value) =>
	value
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;');
const frameDocument = `<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>${embedCss}\n${blockCss}\n${presetCss}\nhtml,body{margin:0;padding:0}</style></head>
<body>
<main class="tpeu-block-editor">
<div class="tpeu-block-editor__heading"><strong>${escapeHtml(longScenario.tournament)}</strong><a href="#target">Open full tournament</a></div>
<div class="tpeu-block-editor__example"><table><tbody><tr><td>1</td><td>${escapeHtml(longScenario.participants[0].name)}</td><td>12</td><td>37</td></tr></tbody></table></div>
<div class="tpeu-embed"><p class="tpeu-embed__status" role="status">No content is currently available for this selection.</p><p class="tpeu-embed__fallback"><a href="#target">Open a tournament with a very long fictional reference on Turnierplan.eu</a></p></div>
<section class="tpeu-preset-editor"><div class="tpeu-preset-editor__configuration">Configuration with a deliberately long fictional tournament name</div><div class="tpeu-preset-editor__preview">Preview</div></section>
</main>
<script>addEventListener('load',()=>{const client=document.documentElement.clientWidth;const offenders=[...document.querySelectorAll('*')].filter((node)=>node.getBoundingClientRect().right>client+0.5).map((node)=>({node:node.className||node.tagName,right:Math.round(node.getBoundingClientRect().right),scroll:node.scrollWidth}));parent.postMessage({viewport:innerWidth,client,scroll:document.documentElement.scrollWidth,offenders},'*')});</script>
</body></html>`;
const serializedFrame = JSON.stringify(frameDocument).replaceAll(
	'</script',
	'<\\/script'
);
const page = `<!doctype html><html><body><div id="frames"></div><pre id="result">pending</pre><script>
const widths=${JSON.stringify(widths)};const values=[];
addEventListener('message',(event)=>{values.push(event.data);if(values.length===widths.length){document.getElementById('result').textContent=JSON.stringify(values.sort((a,b)=>a.viewport-b.viewport));}});
for(const width of widths){const frame=document.createElement('iframe');frame.width=String(width);frame.height='900';frame.srcdoc=${serializedFrame};document.getElementById('frames').appendChild(frame);}
</script></body></html>`;
const temporary = await mkdtemp(path.join(os.tmpdir(), 'tpeu-responsive-'));

try {
	const htmlPath = path.join(temporary, 'responsive.html');
	await writeFile(htmlPath, page);
	const result = spawnSync(
		browser,
		[
			'--headless=new',
			'--disable-gpu',
			'--no-first-run',
			`--user-data-dir=${path.join(temporary, 'profile')}`,
			'--window-size=1600,1200',
			'--virtual-time-budget=2000',
			'--dump-dom',
			pathToFileURL(htmlPath).href,
		],
		{ encoding: 'utf8', timeout: 30_000 }
	);

	if (result.status !== 0) {
		throw new Error(
			result.stderr || `Browser exited with ${result.status}.`
		);
	}

	const encoded = result.stdout.match(
		/<pre id="result">([^<]+)<\/pre>/u
	)?.[1];
	if (!encoded || encoded === 'pending') {
		const marker = result.stdout.indexOf('id="result"');
		throw new Error(
			`The responsive browser did not return measurements: ${result.stdout.slice(marker, marker + 500)}`
		);
	}

	const measurements = JSON.parse(encoded.replaceAll('&quot;', '"'));
	for (const measurement of measurements) {
		if (measurement.scroll > measurement.client) {
			throw new Error(
				`Page overflow at ${measurement.viewport}px: ${measurement.scroll}px > ${measurement.client}px. ${JSON.stringify(measurement.offenders)}`
			);
		}
	}

	process.stdout.write(
		`Responsive browser check passed: ${measurements.map(({ viewport }) => `${viewport}px`).join(', ')}.\n`
	);
} finally {
	await rm(temporary, { recursive: true, force: true });
}
