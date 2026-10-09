/* SPDX-License-Identifier: GPL-2.0-or-later */
import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outputDirectory = path.join(root, 'wordpress-org-assets');
const browser = [
	process.env.TPEU_BROWSER_BIN,
	'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
	'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
	'/usr/bin/google-chrome',
	'/usr/bin/chromium',
].find((candidate) => candidate && existsSync(candidate));

if (!browser) {
	throw new Error('Set TPEU_BROWSER_BIN to a Chromium-compatible browser.');
}

const commonCss = [
	'*{box-sizing:border-box}',
	'html,body{margin:0;width:100%;height:100%;overflow:hidden}',
	'body{font-family:Inter,Segoe UI,Arial,sans-serif;background:#f0fdf4;color:#111827}',
	'.mark{display:grid;place-items:center;background:#15803d;color:#fff;border-radius:22%;box-shadow:0 12px 30px rgba(21,128,61,.24)}',
	'.bracket{width:64%;height:64%}',
	'.bracket path{fill:none;stroke:currentColor;stroke-width:7;stroke-linecap:round;stroke-linejoin:round}',
	'.shell{height:100%;display:grid;grid-template-rows:58px 1fr;background:#f6f7f7}',
	'.adminbar{display:flex;align-items:center;gap:18px;padding:0 24px;background:#1d2327;color:#fff;font-size:14px}',
	'.wpdot{display:grid;place-items:center;width:30px;height:30px;border:2px solid #fff;border-radius:50%;font-weight:700}',
	'.workspace{display:grid;grid-template-columns:180px 1fr 310px;min-height:0}',
	'.nav{padding:24px 18px;background:#23282d;color:#dcdcde}',
	'.nav strong{display:block;color:#fff;margin-bottom:22px}',
	'.nav span{display:block;padding:9px 10px;border-radius:5px;margin:3px 0}',
	'.nav .active{background:#15803d;color:#fff}',
	'.canvas{padding:30px;background:#fff;overflow:hidden}',
	'.sidebar{padding:24px;background:#fff;border-left:1px solid #dcdcde;overflow:hidden}',
	'h1,h2,h3,p{margin-top:0}',
	'h1{font-size:29px;margin-bottom:22px}',
	'h2{font-size:21px}',
	'.muted{color:#646970}',
	'.block{border:1px solid #949494;border-radius:4px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06)}',
	'.blockhead{display:flex;align-items:center;gap:12px;margin-bottom:16px}',
	'.mini{width:34px;height:34px;border-radius:7px}',
	'.pill{display:inline-block;padding:5px 9px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700}',
	'.tabs{display:flex;gap:8px;margin-bottom:14px}',
	'.tab{padding:8px 12px;border:1px solid #c3c4c7;border-radius:4px;background:#fff}',
	'.tab.active{background:#15803d;border-color:#15803d;color:#fff}',
	'.field{margin:0 0 17px}',
	'.field label{display:block;font-weight:600;margin-bottom:6px}',
	'.input{height:38px;border:1px solid #8c8f94;border-radius:3px;padding:9px 10px;background:#fff}',
	'.check{display:flex;align-items:center;gap:8px;margin:10px 0}',
	'.box{width:17px;height:17px;border:1px solid #2271b1;border-radius:2px;background:#2271b1;position:relative}',
	'.box:after{content:"";position:absolute;left:4px;top:1px;width:5px;height:9px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}',
	'.preview{border:1px solid #dcdcde;border-radius:6px;overflow:hidden}',
	'.preview-title{padding:16px 18px;background:#15803d;color:#fff;font-weight:750}',
	'table{width:100%;border-collapse:collapse;font-size:14px}',
	'th,td{padding:11px 12px;border-bottom:1px solid #e5e7eb;text-align:left}',
	'th{background:#f0fdf4;color:#166534;font-size:12px;text-transform:uppercase;letter-spacing:.03em}',
	'.score{font-weight:800;text-align:center}',
	'.time{font-variant-numeric:tabular-nums;color:#475569}',
	'.notice{padding:14px 16px;border-left:4px solid #15803d;background:#f0fdf4;margin-bottom:20px}',
	'.button{display:inline-block;padding:9px 14px;border-radius:4px;background:#2271b1;color:#fff;font-weight:600}',
	'.code{padding:13px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;font-family:Consolas,monospace;font-size:13px}',
].join('');

const bracket =
	'<svg class="bracket" viewBox="0 0 100 100" aria-hidden="true"><path d="M18 16h20v20H18m20-10h14v24h12m18-34H62v20h20M18 64h20v20H18m20-10h14V50m30 14H62v20h20"/></svg>';
const mark = (className = 'mark') =>
	'<div class="' + className + '">' + bracket + '</div>';
const page = (content, extraCss = '') =>
	'<!doctype html><html><head><meta charset="utf-8"><style>' +
	commonCss +
	extraCss +
	'</style></head><body>' +
	content +
	'</body></html>';

const banner = page(
	'<main class="banner"><div class="identity">' +
		mark('mark brandmark') +
		'<div><div class="eyebrow">WORDPRESS PLUGIN</div><h1>Turnierplan.eu</h1><p>Turniertabellen und Spielpläne direkt einbetten</p></div></div><div class="cards"><div class="card"><span>1</span><b>FC Nord</b><strong>12</strong></div><div class="card"><span>2</span><b>SV Grün</b><strong>9</strong></div><div class="fixture"><small>LIVE</small><b>FC Nord</b><em>2 : 1</em><b>SV Grün</b></div></div></main>',
	'.banner{height:100%;display:flex;align-items:center;justify-content:space-between;padding:8% 7%;background:linear-gradient(120deg,#052e16 0%,#15803d 58%,#4ade80 140%);color:#fff}.identity{display:flex;align-items:center;gap:4vw;max-width:68%}.brandmark{width:17vw;height:17vw;max-width:154px;max-height:154px;min-width:76px;min-height:76px;background:#fff;color:#15803d;box-shadow:none}.eyebrow{font-size:clamp(9px,1.6vw,22px);font-weight:800;letter-spacing:.16em;color:#bbf7d0}.banner h1{font-size:clamp(32px,6vw,84px);line-height:.95;margin:6px 0 10px}.banner p{font-size:clamp(13px,2.2vw,30px);margin:0;color:#dcfce7}.cards{width:31%;min-width:220px}.card,.fixture{display:grid;grid-template-columns:28px 1fr auto;align-items:center;gap:8px;padding:10px 13px;margin:8px 0;border-radius:10px;background:rgba(255,255,255,.94);color:#14532d;box-shadow:0 10px 22px rgba(0,0,0,.16);font-size:clamp(10px,1.5vw,20px)}.card span{display:grid;place-items:center;width:24px;height:24px;border-radius:50%;background:#dcfce7}.fixture{grid-template-columns:auto 1fr auto 1fr;text-align:center}.fixture small{color:#dc2626;font-weight:900}.fixture em{font-style:normal;font-weight:900}'
);

const icon = page(
	'<main class="icon">' + mark('mark iconmark') + '</main>',
	'.icon{display:grid;place-items:center;width:100%;height:100%;background:#f0fdf4}.iconmark{width:82%;height:82%;border-radius:23%;box-shadow:0 8px 20px rgba(21,128,61,.22)}'
);

const adminFrame = (title, main, sidebar) =>
	'<main class="shell"><header class="adminbar"><span class="wpdot">W</span><strong>Turnierplan.eu</strong><span>Website bearbeiten</span></header><section class="workspace"><nav class="nav"><strong>WordPress</strong><span>Dashboard</span><span>Beiträge</span><span class="active">Turnierplan</span><span>Einstellungen</span></nav><article class="canvas"><h1>' +
	title +
	'</h1>' +
	main +
	'</article><aside class="sidebar">' +
	sidebar +
	'</aside></section></main>';

const standings = page(
	adminFrame(
		'Turniertabelle einbetten',
		'<div class="block"><div class="blockhead">' +
			mark('mark mini') +
			'<div><strong>Turnierplan.eu</strong><div class="muted">Sommer Cup 2026</div></div><span class="pill">Bereit</span></div><div class="tabs"><span class="tab active">Tabelle</span><span class="tab">Spielplan</span></div><div class="preview"><div class="preview-title">Sommer Cup 2026 · Gruppe A</div><table><thead><tr><th>Platz</th><th>Team</th><th>Sp.</th><th>Tore</th><th>Punkte</th></tr></thead><tbody><tr><td>1</td><td><b>FC Nord</b></td><td>4</td><td>12:4</td><td><b>10</b></td></tr><tr><td>2</td><td><b>SV Grün</b></td><td>4</td><td>9:5</td><td><b>8</b></td></tr><tr><td>3</td><td>TSV Süd</td><td>4</td><td>7:8</td><td>4</td></tr><tr><td>4</td><td>VfB West</td><td>4</td><td>3:14</td><td>0</td></tr></tbody></table></div></div>',
		'<h2>Block</h2><div class="field"><label>Öffentliches Turnier</label><div class="input">sommer-cup-2026</div></div><div class="field"><label>Ansicht</label><div class="input">Turniertabelle</div></div><div class="field"><label>Sprache</label><div class="input">Deutsch</div></div><h3>Sichtbare Angaben</h3><div class="check"><i class="box"></i>Teamlogos</div><div class="check"><i class="box"></i>Spiele</div><div class="check"><i class="box"></i>Punkte</div>'
	)
);

const matches = page(
	adminFrame(
		'Spielplan einbetten',
		'<div class="block"><div class="blockhead">' +
			mark('mark mini') +
			'<div><strong>Turnierplan.eu</strong><div class="muted">Sommer Cup 2026</div></div><span class="pill">Live</span></div><div class="tabs"><span class="tab">Tabelle</span><span class="tab active">Spielplan</span></div><div class="preview"><div class="preview-title">Spielplan · Gruppe A</div><table><thead><tr><th>Zeit</th><th>Begegnung</th><th>Feld</th><th>Ergebnis</th></tr></thead><tbody><tr><td class="time">10:00</td><td><b>FC Nord</b> – SV Grün</td><td>1</td><td class="score">2 : 1</td></tr><tr><td class="time">10:25</td><td>TSV Süd – VfB West</td><td>2</td><td class="score">0 : 0</td></tr><tr><td class="time">10:50</td><td>SV Grün – TSV Süd</td><td>1</td><td class="score">–</td></tr><tr><td class="time">11:15</td><td>VfB West – FC Nord</td><td>2</td><td class="score">–</td></tr></tbody></table></div></div>',
		'<h2>Filter</h2><div class="field"><label>Gruppe</label><div class="input">Gruppe A</div></div><div class="field"><label>Spiele von / bis</label><div class="input">1 bis 12</div></div><div class="field"><label>Darstellung</label><div class="input">Kompakt</div></div><h3>Sichtbare Angaben</h3><div class="check"><i class="box"></i>Uhrzeit</div><div class="check"><i class="box"></i>Spielfeld</div><div class="check"><i class="box"></i>Live-Status</div>'
	)
);

const settings = page(
	adminFrame(
		'Turnierplan.eu Einstellungen',
		'<div class="notice"><strong>Externer Dienst ist ausgeschaltet.</strong><p style="margin:6px 0 0">Es werden keine Metadaten abgerufen und keine Turnier-Frames angezeigt.</p></div><div class="block"><h2>Verbindung zu Turnierplan.eu</h2><p>Nach der Freigabe lädt WordPress kleine Metadaten für Konfiguration und Vorschau. Veröffentlichte Seiten laden den gewählten Turnier-Frame direkt von Turnierplan.eu.</p><p><a>Datenschutz bei Turnierplan.eu</a> · <a>Nutzungsbedingungen</a></p><div class="check"><i style="width:17px;height:17px;border:1px solid #8c8f94"></i> Externen Dienst aktivieren</div><span class="button">Änderungen speichern</span></div>',
		'<h2>Standardeinstellungen</h2><div class="field"><label>Ausgabesprache</label><div class="input">Automatisch</div></div><div class="field"><label>Farbschema</label><div class="input">Automatisch</div></div><div class="field"><label>Dichte</label><div class="input">Komfortabel</div></div><h3>Deinstallation</h3><div class="check"><i style="width:17px;height:17px;border:1px solid #8c8f94"></i>Daten beim Löschen entfernen</div>'
	)
);

const preset = page(
	adminFrame(
		'Preset bearbeiten',
		'<div class="block"><div class="blockhead">' +
			mark('mark mini') +
			'<div><strong>Vereinsseite · Sommer Cup</strong><div class="muted">Wiederverwendbare Einbettung</div></div><span class="pill">Veröffentlicht</span></div><div class="field"><label>Turnier</label><div class="input">sommer-cup-2026</div></div><div class="field"><label>Ansicht</label><div class="input">Spielplan</div></div><h3>Shortcode</h3><div class="code">[turnierplan preset="87"]</div><p style="margin:14px 0 0"><span class="button">Shortcode kopieren</span></p></div>',
		'<h2>Preset</h2><p class="muted">Änderungen gelten an allen Stellen, die dieses veröffentlichte Preset verwenden.</p><div class="field"><label>Sprache</label><div class="input">Deutsch</div></div><div class="field"><label>Farbschema</label><div class="input">Automatisch</div></div><div class="check"><i class="box"></i>Links in neuem Tab</div><div class="check"><i class="box"></i>Branding anzeigen</div>'
	)
);

const targets = [
	['banner-772x250.png', 772, 250, banner],
	['banner-1544x500.png', 1544, 500, banner],
	['icon-128x128.png', 128, 128, icon],
	['icon-256x256.png', 256, 256, icon],
	['screenshot-1.png', 1200, 900, standings],
	['screenshot-2.png', 1200, 900, matches],
	['screenshot-3.png', 1200, 900, settings],
	['screenshot-4.png', 1200, 900, preset],
];
const temporary = await mkdtemp(
	path.join(os.tmpdir(), 'tpeu-directory-assets-')
);

try {
	await mkdir(outputDirectory, { recursive: true });

	for (const [name, width, height, html] of targets) {
		const source = path.join(temporary, name + '.html');
		const output = path.join(outputDirectory, name);
		await writeFile(source, html, 'utf8');
		const result = spawnSync(
			browser,
			[
				'--headless=new',
				'--disable-gpu',
				'--hide-scrollbars',
				'--no-first-run',
				'--force-device-scale-factor=1',
				'--window-size=' + width + ',' + height,
				'--screenshot=' + output,
				'--user-data-dir=' + path.join(temporary, name + '-profile'),
				pathToFileURL(source).href,
			],
			{ encoding: 'utf8', timeout: 30_000 }
		);

		if (result.status !== 0) {
			throw new Error(
				result.stderr || 'Browser asset render failed for ' + name + '.'
			);
		}

		const png = await readFile(output);
		const actualWidth = png.readUInt32BE(16);
		const actualHeight = png.readUInt32BE(20);

		if (actualWidth !== width || actualHeight !== height) {
			throw new Error(
				name +
					' has unexpected dimensions ' +
					actualWidth +
					'x' +
					actualHeight +
					'.'
			);
		}
	}

	process.stdout.write(
		'WordPress.org directory assets built: ' +
			targets.length +
			' PNG files.\n'
	);
} finally {
	await rm(temporary, { recursive: true, force: true });
}
