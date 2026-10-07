import metadata from '../../blocks/embed/block.json';
import { normalizeEmbedConfig } from './embed-config';

const { Button, Notice, RangeControl, SelectControl, TextControl, ToggleControl } =
	wp.components;
const { render, useEffect, useMemo, useState } = wp.element;
const { __ } = wp.i18n;
const ServerSideRender =
	wp.serverSideRender.default ?? wp.serverSideRender;
const settings = window.TurnierplanEUPresetSettings ?? {
	serviceEnabled: false,
	setupDefaults: {},
};
const defaults = metadata.attributes.config.default;
const booleanOptions = [
	['showBranding', __('Branding anzeigen', 'turnierplan-eu')],
	['openLinksInNewTab', __('Links in neuem Tab öffnen', 'turnierplan-eu')],
	['showTeamLogos', __('Teamlogos anzeigen', 'turnierplan-eu')],
	['showPlayed', __('Gespielte Partien anzeigen', 'turnierplan-eu')],
	['showWinsDrawsLosses', __('Siege, Remis und Niederlagen anzeigen', 'turnierplan-eu')],
	['showScoreBalance', __('Bilanz anzeigen', 'turnierplan-eu')],
	['showPoints', __('Punkte anzeigen', 'turnierplan-eu')],
	['enableGroupNavigation', __('Gruppennavigation erlauben', 'turnierplan-eu')],
	['showMatchNumber', __('Spielnummer anzeigen', 'turnierplan-eu')],
	['showTime', __('Uhrzeit anzeigen', 'turnierplan-eu')],
	['showField', __('Feld oder Platz anzeigen', 'turnierplan-eu')],
	['showGroup', __('Gruppe anzeigen', 'turnierplan-eu')],
	['showRound', __('Runde anzeigen', 'turnierplan-eu')],
	['showReferee', __('Schiedsrichter anzeigen', 'turnierplan-eu')],
	['showLiveState', __('Live-Status anzeigen', 'turnierplan-eu')],
	['showExtraTime', __('Verlängerung anzeigen', 'turnierplan-eu')],
	['showPenaltyResult', __('Entscheidungsdetails anzeigen', 'turnierplan-eu')],
];

function PresetEditor() {
	const input = document.getElementById('tpeu-preset-config');
	let stored = {};

	try {
		stored = JSON.parse(input?.value || '{}');
	} catch {
		stored = {};
	}

	const [config, setConfig] = useState({
		...defaults,
		...settings.setupDefaults,
		...stored,
	});
	const [copied, setCopied] = useState(false);
	const shortcode = document.getElementById('tpeu-preset-shortcode')?.value ?? '';
	const validation = useMemo(() => {
		try {
			return { config: normalizeEmbedConfig(config), error: null };
		} catch (error) {
			return { config: null, error };
		}
	}, [config]);

	useEffect(() => {
		if (input !== null) {
			input.value = JSON.stringify(validation.config ?? config);
		}
	}, [config, input, validation.config]);

	const update = (patch) => setConfig((value) => ({ ...value, ...patch }));
	const nullable = (value) => (value.trim() === '' ? null : value.trim());
	const nullableNumber = (value) =>
		value === '' ? null : Number.parseInt(value, 10);
	const copyShortcode = async () => {
		await navigator.clipboard.writeText(shortcode);
		setCopied(true);
		window.setTimeout(() => setCopied(false), 2000);
	};

	return (
		<div className="tpeu-preset-editor">
			<div className="tpeu-preset-editor__configuration">
				{new URLSearchParams(window.location.search).has('tpeu_preset_error') && (
					<Notice status="error" isDismissible={false}>
						{__('Die Konfiguration war ungültig. Das Preset wurde als Entwurf gespeichert.', 'turnierplan-eu')}
					</Notice>
				)}
				<TextControl
					label={__('Turnier-ID, Slug oder URL', 'turnierplan-eu')}
					value={config.tournamentRef}
					onChange={(tournamentRef) => update({ tournamentRef })}
				/>
				<div className="tpeu-preset-editor__row">
					<SelectControl
						label={__('Ansicht', 'turnierplan-eu')}
						value={config.view}
						options={[
							{ label: __('Turniertabelle', 'turnierplan-eu'), value: 'standings' },
							{ label: __('Spielplan', 'turnierplan-eu'), value: 'matches' },
						]}
						onChange={(view) => update({
							view,
							...(view === 'standings' ? { participant: null, matchFrom: null, matchTo: null, dateFrom: null, dateTo: null } : {}),
						})}
					/>
					<SelectControl
						label={__('Sprache', 'turnierplan-eu')}
						value={config.language}
						options={[
							{ label: __('Automatisch', 'turnierplan-eu'), value: 'auto' },
							{ label: __('Deutsch', 'turnierplan-eu'), value: 'de' },
							{ label: __('Englisch', 'turnierplan-eu'), value: 'en' },
						]}
						onChange={(language) => update({ language })}
					/>
				</div>
				<div className="tpeu-preset-editor__row">
					<TextControl
						label={__('Gruppen-ID (optional)', 'turnierplan-eu')}
						value={config.group ?? ''}
						onChange={(value) => update({ group: nullable(value) })}
					/>
					{config.view === 'matches' && (
						<TextControl
							label={__('Teilnehmer-ID (optional)', 'turnierplan-eu')}
							value={config.participant ?? ''}
							onChange={(value) => update({ participant: nullable(value) })}
						/>
					)}
				</div>
				{config.view === 'matches' && (
					<>
						<div className="tpeu-preset-editor__row">
							<TextControl label={__('Spielnummer von', 'turnierplan-eu')} type="number" value={config.matchFrom ?? ''} onChange={(value) => update({ matchFrom: nullableNumber(value) })} />
							<TextControl label={__('Spielnummer bis', 'turnierplan-eu')} type="number" value={config.matchTo ?? ''} onChange={(value) => update({ matchTo: nullableNumber(value) })} />
						</div>
						<div className="tpeu-preset-editor__row">
							<TextControl label={__('Datum von', 'turnierplan-eu')} type="date" value={config.dateFrom ?? ''} onChange={(value) => update({ dateFrom: value === '' ? null : value })} />
							<TextControl label={__('Datum bis', 'turnierplan-eu')} type="date" value={config.dateTo ?? ''} onChange={(value) => update({ dateTo: value === '' ? null : value })} />
						</div>
						<SelectControl
							label={__('Datum anzeigen', 'turnierplan-eu')}
							value={config.showDate}
							options={[
								{ label: __('Automatisch', 'turnierplan-eu'), value: 'auto' },
								{ label: __('Anzeigen', 'turnierplan-eu'), value: 'show' },
								{ label: __('Ausblenden', 'turnierplan-eu'), value: 'hide' },
							]}
							onChange={(showDate) => update({ showDate })}
						/>
					</>
				)}
				<div className="tpeu-preset-editor__row">
					<SelectControl
						label={__('Farbschema', 'turnierplan-eu')}
						value={config.theme}
						options={['auto', 'light', 'dark'].map((value) => ({ label: value, value }))}
						onChange={(theme) => update({ theme })}
					/>
					<SelectControl
						label={__('Dichte', 'turnierplan-eu')}
						value={config.density}
						options={['comfortable', 'compact'].map((value) => ({ label: value, value }))}
						onChange={(density) => update({ density })}
					/>
				</div>
				<TextControl
					label={__('Akzentfarbe (z. B. #2255AA)', 'turnierplan-eu')}
					value={config.accentColor ?? ''}
					onChange={(accentColor) => update({ accentColor: accentColor === '' ? null : accentColor.toUpperCase() })}
				/>
				<RangeControl
					label={__('Mindesthöhe', 'turnierplan-eu')}
					min={160}
					max={2000}
					value={config.minHeight}
					onChange={(minHeight) => update({ minHeight, maxHeight: Math.max(config.maxHeight, minHeight) })}
				/>
				<RangeControl
					label={__('Maximalhöhe', 'turnierplan-eu')}
					min={300}
					max={8000}
					value={config.maxHeight}
					onChange={(maxHeight) => update({ maxHeight, minHeight: Math.min(config.minHeight, maxHeight) })}
				/>
				<div className="tpeu-preset-editor__toggles">
					{booleanOptions.map(([field, label]) => (
						<ToggleControl key={field} label={label} checked={Boolean(config[field])} onChange={(value) => update({ [field]: value })} />
					))}
				</div>
				{validation.error !== null && (
					<Notice status="error" isDismissible={false}>
						{__('Bitte korrigiere die Konfiguration, bevor du veröffentlichst.', 'turnierplan-eu')}
					</Notice>
				)}
				{shortcode !== '' && (
					<Button variant="secondary" onClick={copyShortcode}>
						{copied ? __('Shortcode kopiert', 'turnierplan-eu') : __('Shortcode kopieren', 'turnierplan-eu')}
					</Button>
				)}
			</div>
			<div className="tpeu-preset-editor__preview">
				<h3>{__('Vorschau', 'turnierplan-eu')}</h3>
				{!settings.serviceEnabled && <Notice status="warning" isDismissible={false}>{__('Der externe Dienst ist noch nicht freigegeben.', 'turnierplan-eu')}</Notice>}
				{validation.config !== null && settings.serviceEnabled ? (
					<ServerSideRender block="turnierplan-eu/embed" attributes={{ initialized: true, presetId: 0, config: validation.config }} />
				) : (
					<p>{__('Nach einer gültigen Turnierreferenz erscheint hier die Vorschau.', 'turnierplan-eu')}</p>
				)}
			</div>
		</div>
	);
}

const root = document.getElementById('tpeu-preset-editor-root');
if (root !== null) {
	render(<PresetEditor />, root);
}
