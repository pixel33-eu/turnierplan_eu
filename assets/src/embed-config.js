const SERVICE_ORIGIN = 'https://www.turnierplan.eu';
const CANONICAL_REFERENCE = /^trn_[0-9a-hjkmnp-tv-z]{26}$/;
const NUMERIC_REFERENCE = /^[1-9][0-9]{0,19}$/;
const SLUG_REFERENCE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const GROUP_REFERENCE = /^grp_[0-9a-hjkmnp-tv-z]{26}$/;
const PARTICIPANT_REFERENCE = /^ptc_[0-9a-hjkmnp-tv-z]{26}$/;
const LANGUAGE = /^[a-z]{2,3}(?:-[A-Z]{2})?$/;
const ISO_DATE = /^[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])$/;
const UUID_V4 =
	/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

export const embedContractDefaults = Object.freeze({
	schemaVersion: 1,
	view: 'standings',
	language: 'auto',
	group: null,
	participant: null,
	matchFrom: null,
	matchTo: null,
	dateFrom: null,
	dateTo: null,
	theme: 'auto',
	density: 'comfortable',
	accentColor: null,
	showBranding: true,
	openLinksInNewTab: true,
	minHeight: 240,
	maxHeight: 4000,
	showTeamLogos: true,
	showPlayed: true,
	showWinsDrawsLosses: true,
	showScoreBalance: true,
	showPoints: true,
	enableGroupNavigation: true,
	showMatchNumber: true,
	showDate: 'auto',
	showTime: true,
	showField: true,
	showGroup: true,
	showRound: true,
	showReferee: true,
	showLiveState: true,
	showExtraTime: true,
	showPenaltyResult: true,
});

const setupDefaultFields = new Set([
	'view',
	'language',
	'theme',
	'density',
	'accentColor',
	'showBranding',
	'openLinksInNewTab',
	'minHeight',
	'maxHeight',
	'showTeamLogos',
	'showPlayed',
	'showWinsDrawsLosses',
	'showScoreBalance',
	'showPoints',
	'enableGroupNavigation',
	'showMatchNumber',
	'showDate',
	'showTime',
	'showField',
	'showGroup',
	'showRound',
	'showReferee',
	'showLiveState',
	'showExtraTime',
	'showPenaltyResult',
]);
const booleanFields = [
	'showBranding',
	'openLinksInNewTab',
	'showTeamLogos',
	'showPlayed',
	'showWinsDrawsLosses',
	'showScoreBalance',
	'showPoints',
	'enableGroupNavigation',
	'showMatchNumber',
	'showTime',
	'showField',
	'showGroup',
	'showRound',
	'showReferee',
	'showLiveState',
	'showExtraTime',
	'showPenaltyResult',
];
const showFields = Object.freeze({
	standings: Object.freeze({
		team_logos: 'showTeamLogos',
		played: 'showPlayed',
		wins_draws_losses: 'showWinsDrawsLosses',
		score_balance: 'showScoreBalance',
		points: 'showPoints',
		group_navigation: 'enableGroupNavigation',
	}),
	matches: Object.freeze({
		match_number: 'showMatchNumber',
		time: 'showTime',
		field: 'showField',
		group: 'showGroup',
		round: 'showRound',
		referee: 'showReferee',
		live_state: 'showLiveState',
		extra_time: 'showExtraTime',
		penalty_result: 'showPenaltyResult',
	}),
});

export class EmbedConfigError extends TypeError {
	constructor(field, message) {
		super(message);
		this.name = 'EmbedConfigError';
		this.field = field;
	}
}

const fail = (field, message) => {
	throw new EmbedConfigError(field, message);
};
const isRecord = (value) =>
	typeof value === 'object' && value !== null && !Array.isArray(value);
const enumValue = (field, value, allowed) => {
	if (typeof value !== 'string' || !allowed.includes(value)) {
		fail(field, 'Configuration value is not allowed.');
	}

	return value;
};
const boundedInteger = (field, value, minimum, maximum) => {
	if (!Number.isInteger(value) || value < minimum || value > maximum) {
		fail(field, 'Configuration integer is outside its allowed range.');
	}

	return value;
};
const nullableInteger = (field, value, minimum, maximum) =>
	value === null ? null : boundedInteger(field, value, minimum, maximum);
const nullableReference = (field, value, pattern) => {
	if (value === null) {
		return null;
	}

	if (typeof value !== 'string' || !pattern.test(value.toLowerCase())) {
		fail(field, 'Filter reference is malformed.');
	}

	return value.toLowerCase();
};
const normalizedLanguage = (value) => {
	if (typeof value !== 'string') {
		fail('language', 'Language must be a string.');
	}

	if (value.toLowerCase() === 'auto') {
		return 'auto';
	}

	const parts = value.split('-');
	const language = [
		parts[0].toLowerCase(),
		...(parts[1] === undefined ? [] : [parts[1].toUpperCase()]),
	].join('-');

	if (parts.length > 2 || !LANGUAGE.test(language)) {
		fail('language', 'Language code is malformed.');
	}

	return language;
};
const nullableDate = (field, value) => {
	if (value === null) {
		return null;
	}

	if (typeof value !== 'string' || !ISO_DATE.test(value)) {
		fail(field, 'Date must use YYYY-MM-DD.');
	}

	const date = new Date(`${value}T00:00:00.000Z`);

	if (
		Number.isNaN(date.getTime()) ||
		date.toISOString().slice(0, 10) !== value
	) {
		fail(field, 'Date is not a real calendar day.');
	}

	return value;
};

export function normalizeTournamentReference(input) {
	if (typeof input !== 'string') {
		fail('tournamentRef', 'Tournament reference must be a string.');
	}

	const trimmed = input.trim();

	if (
		trimmed.length === 0 ||
		trimmed.length > 2048 ||
		/[\u0000-\u001f\u007f]/.test(trimmed)
	) {
		fail('tournamentRef', 'Tournament reference is empty or malformed.');
	}

	if (trimmed.includes('://')) {
		let parsed;
		const authority = trimmed.match(/^https:\/\/([^/?#]+)/i)?.[1] ?? '';

		try {
			parsed = new URL(trimmed);
		} catch {
			fail('tournamentRef', 'Tournament URL is malformed.');
		}

		if (
			authority.toLowerCase() !== 'www.turnierplan.eu' ||
			parsed.protocol !== 'https:' ||
			parsed.hostname !== 'www.turnierplan.eu' ||
			parsed.port !== '' ||
			parsed.username !== '' ||
			parsed.password !== '' ||
			parsed.hash !== ''
		) {
			fail('tournamentRef', 'Tournament URL is not allowed.');
		}

		const route = parsed.pathname.match(/^\/t\/([^/]+)$/);

		if (route !== null) {
			if (parsed.search !== '' || route[1].includes('%')) {
				fail(
					'tournamentRef',
					'Tournament URL contains unsupported components.'
				);
			}

			return normalizeTournamentReference(route[1]);
		}

		const legacy = parsed.search.match(/^\?id=([1-9][0-9]{0,19})$/);

		if (parsed.pathname === '/live.php' && legacy !== null) {
			return legacy[1];
		}

		fail('tournamentRef', 'Tournament URL path or query is not supported.');
	}

	const reference = trimmed.toLowerCase();
	const numeric = NUMERIC_REFERENCE.test(reference);

	if (/^[0-9]+$/.test(reference) && !numeric) {
		fail('tournamentRef', 'Numeric tournament reference is malformed.');
	}

	if (
		reference.length > 100 ||
		(!CANONICAL_REFERENCE.test(reference) &&
			!numeric &&
			!SLUG_REFERENCE.test(reference))
	) {
		fail(
			'tournamentRef',
			'Tournament reference has an unsupported format.'
		);
	}

	return reference;
}

export function normalizeEmbedConfig(input) {
	if (!isRecord(input)) {
		fail('config', 'Configuration must be an object.');
	}

	const migrated = {
		...input,
		schemaVersion: input.schemaVersion ?? 1,
	};
	const allowed = new Set([
		...Object.keys(embedContractDefaults),
		'tournamentRef',
	]);
	const unknown = Object.keys(migrated).find((field) => !allowed.has(field));

	if (unknown !== undefined) {
		fail(unknown, 'Unknown configuration field.');
	}

	if (migrated.schemaVersion !== 1) {
		fail('schemaVersion', 'Unsupported configuration schema version.');
	}

	if (!Object.prototype.hasOwnProperty.call(migrated, 'tournamentRef')) {
		fail('tournamentRef', 'Tournament reference is required.');
	}

	const config = {
		...embedContractDefaults,
		...migrated,
	};
	config.tournamentRef = normalizeTournamentReference(config.tournamentRef);
	config.view = enumValue('view', config.view, ['standings', 'matches']);
	config.language = normalizedLanguage(config.language);
	config.group = nullableReference('group', config.group, GROUP_REFERENCE);
	config.participant = nullableReference(
		'participant',
		config.participant,
		PARTICIPANT_REFERENCE
	);
	config.matchFrom = nullableInteger(
		'matchFrom',
		config.matchFrom,
		1,
		999999
	);
	config.matchTo = nullableInteger('matchTo', config.matchTo, 1, 999999);
	config.dateFrom = nullableDate('dateFrom', config.dateFrom);
	config.dateTo = nullableDate('dateTo', config.dateTo);
	config.theme = enumValue('theme', config.theme, ['auto', 'light', 'dark']);
	config.density = enumValue('density', config.density, [
		'comfortable',
		'compact',
	]);
	config.showDate = enumValue('showDate', config.showDate, [
		'auto',
		'show',
		'hide',
	]);
	config.minHeight = boundedInteger('minHeight', config.minHeight, 160, 2000);
	config.maxHeight = boundedInteger('maxHeight', config.maxHeight, 300, 8000);

	if (
		config.accentColor !== null &&
		(typeof config.accentColor !== 'string' ||
			!/^#[0-9a-f]{6}$/i.test(config.accentColor))
	) {
		fail('accentColor', 'Accent color is malformed.');
	}

	config.accentColor = config.accentColor?.toUpperCase() ?? null;

	for (const field of booleanFields) {
		if (typeof config[field] !== 'boolean') {
			fail(field, 'Configuration flag must be boolean.');
		}
	}

	if (
		config.matchFrom !== null &&
		config.matchTo !== null &&
		config.matchFrom > config.matchTo
	) {
		fail('matchTo', 'Match range is reversed.');
	}

	if (
		config.dateFrom !== null &&
		config.dateTo !== null &&
		config.dateFrom > config.dateTo
	) {
		fail('dateTo', 'Date range is reversed.');
	}

	if (config.minHeight > config.maxHeight) {
		fail('maxHeight', 'Maximum height must not be below minimum height.');
	}

	if (config.view === 'standings') {
		for (const field of [
			'participant',
			'matchFrom',
			'matchTo',
			'dateFrom',
			'dateTo',
		]) {
			if (config[field] !== null) {
				fail(field, 'This filter is only available for matches.');
			}
		}
	}

	const ordered = {
		schemaVersion: 1,
		tournamentRef: config.tournamentRef,
	};

	for (const field of Object.keys(embedContractDefaults).slice(1)) {
		ordered[field] = config[field];
	}

	return Object.freeze(ordered);
}

export function newEmbedConfig(tournamentReference, setupDefaults = {}) {
	if (!isRecord(setupDefaults)) {
		fail('setupDefaults', 'Setup defaults must be an object.');
	}

	const unknown = Object.keys(setupDefaults).find(
		(field) => !setupDefaultFields.has(field)
	);

	if (unknown !== undefined) {
		fail(unknown, 'Unknown setup default.');
	}

	return normalizeEmbedConfig({
		...setupDefaults,
		schemaVersion: 1,
		tournamentRef: tournamentReference,
	});
}

export function normalizeEmbedSelection(input) {
	if (!isRecord(input)) {
		fail('selection', 'Embed selection must be an object.');
	}

	const unknown = Object.keys(input).find(
		(field) => !['presetId', 'config'].includes(field)
	);

	if (unknown !== undefined) {
		fail(unknown, 'Unknown selection field.');
	}

	const presetId = input.presetId ?? 0;

	if (!Number.isInteger(presetId) || presetId < 0) {
		fail('presetId', 'Preset ID must be a non-negative integer.');
	}

	if (presetId > 0) {
		if (input.config !== undefined && input.config !== null) {
			fail(
				'config',
				'Preset and inline configuration cannot be combined.'
			);
		}

		return Object.freeze({ presetId, config: null });
	}

	return Object.freeze({
		presetId: 0,
		config: normalizeEmbedConfig(input.config),
	});
}

export function buildEmbedFrameUrl(configInput, instance, parentOrigin) {
	const config = normalizeEmbedConfig(configInput);

	if (typeof instance !== 'string' || !UUID_V4.test(instance)) {
		fail('instance', 'Embed instance must be a lowercase UUID v4.');
	}

	let parent;

	try {
		parent = new URL(parentOrigin);
	} catch {
		fail('parentOrigin', 'Parent origin is malformed.');
	}

	if (
		parent.protocol !== 'https:' ||
		parent.origin !== parentOrigin ||
		parent.username !== '' ||
		parent.password !== '' ||
		parent.pathname !== '/' ||
		parent.search !== '' ||
		parent.hash !== ''
	) {
		fail('parentOrigin', 'Parent origin must be an exact HTTPS origin.');
	}

	const url = new URL(
		`/embed/v1/tournaments/${encodeURIComponent(config.tournamentRef)}`,
		SERVICE_ORIGIN
	);
	url.searchParams.set('view', config.view);
	url.searchParams.set('lang', config.language);

	for (const [field, parameter] of [
		['group', 'group'],
		['participant', 'participant'],
		['matchFrom', 'match_from'],
		['matchTo', 'match_to'],
		['dateFrom', 'date_from'],
		['dateTo', 'date_to'],
	]) {
		if (config[field] !== null) {
			url.searchParams.set(parameter, String(config[field]));
		}
	}

	url.searchParams.set('theme', config.theme);
	url.searchParams.set('density', config.density);

	if (config.accentColor !== null) {
		url.searchParams.set(
			'accent',
			config.accentColor.slice(1).toLowerCase()
		);
	}

	const fields = showFields[config.view];
	const tokens = Object.entries(fields)
		.filter(([, field]) => config[field])
		.map(([token]) => token);

	if (tokens.length !== Object.keys(fields).length) {
		url.searchParams.set('show', tokens.join(','));
	}

	if (config.view === 'matches') {
		url.searchParams.set('date', config.showDate);
	}

	url.searchParams.set('branding', config.showBranding ? 'show' : 'hide');
	url.searchParams.set(
		'links',
		config.openLinksInNewTab ? 'new-tab' : 'same-tab'
	);
	url.searchParams.set('instance', instance);
	url.searchParams.set('parent_origin', parentOrigin);

	return url.toString();
}

export const turnierplanEmbedConfig = Object.freeze({
	buildFrameUrl: buildEmbedFrameUrl,
	contractDefaults: embedContractDefaults,
	newConfig: newEmbedConfig,
	normalize: normalizeEmbedConfig,
	normalizeReference: normalizeTournamentReference,
	normalizeSelection: normalizeEmbedSelection,
});
