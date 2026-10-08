import metadata from '../../blocks/embed/block.json';
import { normalizeEmbedConfig } from './embed-config';

const { Button, Notice, RangeControl, SelectControl, TextControl, ToggleControl } =
	wp.components;
const { render, useEffect, useMemo, useRef, useState } = wp.element;
const { __ } = wp.i18n;
const ServerSideRender =
	wp.serverSideRender.default ?? wp.serverSideRender;
const settings = window.TurnierplanEUPresetSettings ?? {
	serviceEnabled: false,
	setupDefaults: {},
};
const defaults = metadata.attributes.config.default;
const booleanOptions = [
	['showBranding', __('Show branding', 'turnierplan-eu')],
	['openLinksInNewTab', __('Open links in a new tab', 'turnierplan-eu')],
	['showTeamLogos', __('Show team logos', 'turnierplan-eu')],
	['showPlayed', __('Show matches played', 'turnierplan-eu')],
	['showWinsDrawsLosses', __('Show wins, draws, and losses', 'turnierplan-eu')],
	['showScoreBalance', __('Show score balance', 'turnierplan-eu')],
	['showPoints', __('Show points', 'turnierplan-eu')],
	['enableGroupNavigation', __('Enable group navigation', 'turnierplan-eu')],
	['showMatchNumber', __('Show match number', 'turnierplan-eu')],
	['showTime', __('Show time', 'turnierplan-eu')],
	['showField', __('Show field or court', 'turnierplan-eu')],
	['showGroup', __('Show group', 'turnierplan-eu')],
	['showRound', __('Show round', 'turnierplan-eu')],
	['showReferee', __('Show referee', 'turnierplan-eu')],
	['showLiveState', __('Show live status', 'turnierplan-eu')],
	['showExtraTime', __('Show extra time', 'turnierplan-eu')],
	['showPenaltyResult', __('Show decision details', 'turnierplan-eu')],
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
	const [copyError, setCopyError] = useState(false);
	const savedError = new URLSearchParams(window.location.search).has(
		'tpeu_preset_error'
	);
	const savedErrorFocus = useRef(null);
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

	useEffect(() => {
		if (savedError) {
			savedErrorFocus.current?.focus();
		}
	}, [savedError]);

	const update = (patch) => setConfig((value) => ({ ...value, ...patch }));
	const nullable = (value) => (value.trim() === '' ? null : value.trim());
	const nullableNumber = (value) =>
		value === '' ? null : Number.parseInt(value, 10);
	const copyShortcode = async () => {
		setCopied(false);
		setCopyError(false);

		try {
			if (navigator.clipboard?.writeText) {
				await navigator.clipboard.writeText(shortcode);
			} else {
				const helper = document.createElement('textarea');
				helper.value = shortcode;
				helper.setAttribute('readonly', '');
				helper.style.position = 'fixed';
				helper.style.opacity = '0';
				document.body.appendChild(helper);
				helper.select();
				const successful = document.execCommand('copy');
				helper.remove();

				if (!successful) {
					throw new Error('Copy command failed.');
				}
			}

			setCopied(true);
			window.setTimeout(() => setCopied(false), 2000);
		} catch {
			setCopyError(true);
		}
	};

	return (
		<div className="tpeu-preset-editor">
			<div className="tpeu-preset-editor__configuration">
				{savedError && (
					<div ref={savedErrorFocus} tabIndex="-1">
						<Notice status="error" isDismissible={false}>
							{__('The configuration was invalid. The preset was saved as a draft.', 'turnierplan-eu')}
						</Notice>
					</div>
				)}
				<TextControl
					label={__('Tournament ID, slug, or URL', 'turnierplan-eu')}
					value={config.tournamentRef}
					onChange={(tournamentRef) => update({ tournamentRef })}
				/>
				<div className="tpeu-preset-editor__row">
					<SelectControl
						label={__('View', 'turnierplan-eu')}
						value={config.view}
						options={[
							{ label: __('Standings', 'turnierplan-eu'), value: 'standings' },
							{ label: __('Schedule', 'turnierplan-eu'), value: 'matches' },
						]}
						onChange={(view) => update({
							view,
							...(view === 'standings' ? { participant: null, matchFrom: null, matchTo: null, dateFrom: null, dateTo: null } : {}),
						})}
					/>
					<SelectControl
						label={__('Language', 'turnierplan-eu')}
						value={config.language}
						options={[
							{ label: __('Automatic', 'turnierplan-eu'), value: 'auto' },
							{ label: __('German', 'turnierplan-eu'), value: 'de' },
							{ label: __('English', 'turnierplan-eu'), value: 'en' },
						]}
						onChange={(language) => update({ language })}
					/>
				</div>
				<div className="tpeu-preset-editor__row">
					<TextControl
						label={__('Group ID (optional)', 'turnierplan-eu')}
						value={config.group ?? ''}
						onChange={(value) => update({ group: nullable(value) })}
					/>
					{config.view === 'matches' && (
						<TextControl
							label={__('Participant ID (optional)', 'turnierplan-eu')}
							value={config.participant ?? ''}
							onChange={(value) => update({ participant: nullable(value) })}
						/>
					)}
				</div>
				{config.view === 'matches' && (
					<>
						<div className="tpeu-preset-editor__row">
							<TextControl label={__('First match number', 'turnierplan-eu')} type="number" value={config.matchFrom ?? ''} onChange={(value) => update({ matchFrom: nullableNumber(value) })} />
							<TextControl label={__('Last match number', 'turnierplan-eu')} type="number" value={config.matchTo ?? ''} onChange={(value) => update({ matchTo: nullableNumber(value) })} />
						</div>
						<div className="tpeu-preset-editor__row">
							<TextControl label={__('Start date', 'turnierplan-eu')} type="date" value={config.dateFrom ?? ''} onChange={(value) => update({ dateFrom: value === '' ? null : value })} />
							<TextControl label={__('End date', 'turnierplan-eu')} type="date" value={config.dateTo ?? ''} onChange={(value) => update({ dateTo: value === '' ? null : value })} />
						</div>
						<SelectControl
							label={__('Show date', 'turnierplan-eu')}
							value={config.showDate}
							options={[
								{ label: __('Automatic', 'turnierplan-eu'), value: 'auto' },
								{ label: __('Show', 'turnierplan-eu'), value: 'show' },
								{ label: __('Hide', 'turnierplan-eu'), value: 'hide' },
							]}
							onChange={(showDate) => update({ showDate })}
						/>
					</>
				)}
				<div className="tpeu-preset-editor__row">
					<SelectControl
						label={__('Color scheme', 'turnierplan-eu')}
						value={config.theme}
						options={[
							{ label: __('Automatic', 'turnierplan-eu'), value: 'auto' },
							{ label: __('Light', 'turnierplan-eu'), value: 'light' },
							{ label: __('Dark', 'turnierplan-eu'), value: 'dark' },
						]}
						onChange={(theme) => update({ theme })}
					/>
					<SelectControl
						label={__('Density', 'turnierplan-eu')}
						value={config.density}
						options={[
							{ label: __('Comfortable', 'turnierplan-eu'), value: 'comfortable' },
							{ label: __('Compact', 'turnierplan-eu'), value: 'compact' },
						]}
						onChange={(density) => update({ density })}
					/>
				</div>
				<TextControl
					label={__('Accent color (e.g. #2255AA)', 'turnierplan-eu')}
					value={config.accentColor ?? ''}
					onChange={(accentColor) => update({ accentColor: accentColor === '' ? null : accentColor.toUpperCase() })}
				/>
				<RangeControl
					label={__('Minimum height', 'turnierplan-eu')}
					min={160}
					max={2000}
					value={config.minHeight}
					onChange={(minHeight) => update({ minHeight, maxHeight: Math.max(config.maxHeight, minHeight) })}
				/>
				<RangeControl
					label={__('Maximum height', 'turnierplan-eu')}
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
						{__('Correct the configuration before publishing.', 'turnierplan-eu')}
					</Notice>
				)}
				{shortcode !== '' && (
					<Button variant="secondary" onClick={copyShortcode}>
						{__('Copy shortcode', 'turnierplan-eu')}
					</Button>
				)}
				<p className="screen-reader-text" aria-live="polite">
					{copied && __('Shortcode copied', 'turnierplan-eu')}
					{copyError && __('The shortcode could not be copied.', 'turnierplan-eu')}
				</p>
			</div>
			<div className="tpeu-preset-editor__preview">
				<h3>{__('Preview', 'turnierplan-eu')}</h3>
				{!settings.serviceEnabled && <Notice status="warning" isDismissible={false}>{__('The external service has not been enabled yet.', 'turnierplan-eu')}</Notice>}
				{validation.config !== null && settings.serviceEnabled ? (
					<ServerSideRender block="turnierplan-eu/embed" attributes={{ initialized: true, presetId: 0, config: validation.config }} />
				) : (
					<p>{__('The preview appears here after a valid tournament reference is entered.', 'turnierplan-eu')}</p>
				)}
			</div>
		</div>
	);
}

const root = document.getElementById('tpeu-preset-editor-root');
if (root !== null) {
	render(<PresetEditor />, root);
}
