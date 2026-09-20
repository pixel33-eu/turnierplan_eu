import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';

const sourceUrl = new URL('../assets/src/embed-config.js', import.meta.url);
const source = await readFile(sourceUrl, 'utf8');
const moduleUrl = `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const config = await import(moduleUrl);
const reference = 'trn_01k4f6y7m8n9p0q1r2s3t4v5wx';
const instance = '550e8400-e29b-41d4-a716-446655440000';

assert.equal(
	config.normalizeTournamentReference(
		'https://www.turnierplan.eu/live.php?id=12345',
	),
	'12345',
);
assert.equal(
	config.normalizeTournamentReference(
		'https://WWW.TURNIERPLAN.EU/t/Sommer-Cup-2026',
	),
	'sommer-cup-2026',
);

for (const value of [
	'http://www.turnierplan.eu/t/cup',
	'https://example.org/t/cup',
	'https://user@www.turnierplan.eu/t/cup',
	'https://www.turnierplan.eu:443/t/cup',
	'https://www.turnierplan.eu:444/t/cup',
	'https://www.turnierplan.eu/t/cup?x=1',
	'https://www.turnierplan.eu/t/cup%252fsecret',
	'0123',
	'cup\nnext',
]) {
	assert.throws(() => config.normalizeTournamentReference(value));
}

const normalized = config.normalizeEmbedConfig({
	tournamentRef: reference,
	view: 'matches',
	language: 'DE-de',
	dateFrom: '2026-09-12',
	dateTo: '2026-09-13',
	matchFrom: 4,
	matchTo: 12,
	accentColor: '#16a34a',
});

assert.equal(normalized.schemaVersion, 1);
assert.equal(normalized.language, 'de-DE');
assert.equal(normalized.accentColor, '#16A34A');
assert.equal(normalized.minHeight, 240);
assert.equal(normalized.maxHeight, 4000);
assert.equal(Object.isFrozen(normalized), true);

for (const [field, values] of [
	['matchTo', {view: 'matches', matchFrom: 12, matchTo: 4}],
	['dateTo', {view: 'matches', dateFrom: '2026-09-20', dateTo: '2026-09-19'}],
	['maxHeight', {minHeight: 900, maxHeight: 800}],
	['dateFrom', {view: 'matches', dateFrom: '2026-02-30'}],
	['showBranding', {showBranding: 'false'}],
	['participant', {participant: 'ptc_01k4f71bcde2fgh3jkm4npq5rs'}],
]) {
	assert.throws(
		() => config.normalizeEmbedConfig({tournamentRef: reference, ...values}),
		(error) => error.field === field,
	);
}

const existing = config.normalizeEmbedConfig({tournamentRef: reference});
const newlyCreated = config.newEmbedConfig(reference, {
	theme: 'dark',
	minHeight: 360,
});
assert.equal(existing.theme, 'auto');
assert.equal(existing.minHeight, 240);
assert.equal(newlyCreated.theme, 'dark');
assert.equal(newlyCreated.minHeight, 360);

assert.throws(() =>
	config.normalizeEmbedSelection({
		presetId: 87,
		config: {tournamentRef: reference},
	}),
);
assert.deepEqual(config.normalizeEmbedSelection({presetId: 87}), {
	presetId: 87,
	config: null,
});

const frameUrl = config.buildEmbedFrameUrl(
	{
		tournamentRef: 'sommer-cup-2026',
		view: 'matches',
		language: 'de',
		group: 'grp_01k4f70bcde2fgh3jkm4npq5rs',
		participant: 'ptc_01k4f71bcde2fgh3jkm4npq5rs',
		matchFrom: 4,
		matchTo: 12,
		dateFrom: '2026-09-12',
		dateTo: '2026-09-13',
		theme: 'dark',
		density: 'compact',
		accentColor: '#16A34A',
		showField: false,
		showReferee: false,
		showPenaltyResult: false,
		showDate: 'show',
		showBranding: false,
		openLinksInNewTab: false,
	},
	instance,
	'https://verein.example',
);

assert.equal(
	frameUrl,
	'https://www.turnierplan.eu/embed/v1/tournaments/sommer-cup-2026' +
		'?view=matches&lang=de' +
		'&group=grp_01k4f70bcde2fgh3jkm4npq5rs' +
		'&participant=ptc_01k4f71bcde2fgh3jkm4npq5rs' +
		'&match_from=4&match_to=12&date_from=2026-09-12&date_to=2026-09-13' +
		'&theme=dark&density=compact&accent=16a34a' +
		'&show=match_number%2Ctime%2Cgroup%2Cround%2Clive_state%2Cextra_time' +
		'&date=show&branding=hide&links=same-tab' +
		'&instance=550e8400-e29b-41d4-a716-446655440000' +
		'&parent_origin=https%3A%2F%2Fverein.example',
);

for (const [badInstance, badOrigin] of [
	[instance.toUpperCase(), 'https://verein.example'],
	[instance, 'http://verein.example'],
	[instance, 'https://verein.example/path'],
]) {
	assert.throws(() =>
		config.buildEmbedFrameUrl(
			{tournamentRef: '12345'},
			badInstance,
			badOrigin,
		),
	);
}

console.log('embed-config.test: OK');
