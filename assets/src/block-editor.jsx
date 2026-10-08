import metadata from '../../blocks/embed/block.json';
import {
	changeView,
	configurationWarnings,
	createMetadataLoader,
	formatMetadataTimestamp,
	initialMetadataState,
	inlineConfigForPreset,
	metadataReducer,
	participantsForGroup,
	reconcileMetadata,
} from './block-editor-state';

const { apiFetch } = wp;
const { InspectorControls, BlockControls, useBlockProps } = wp.blockEditor;
const { registerBlockType } = wp.blocks;
const {
	Button,
	ColorPalette,
	ExternalLink,
	Notice,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} = wp.components;
const {
	useCallback,
	useEffect,
	useMemo,
	useReducer,
	useRef,
	useState,
} = wp.element;
const { __, sprintf } = wp.i18n;
const ServerSideRender =
	wp.serverSideRender.default ?? wp.serverSideRender;
const defaults = metadata.attributes.config.default;
const editorSettings = window.TurnierplanEUBlockSettings ?? {
	serviceEnabled: false,
	setupDefaults: {},
};

const previewWidths = Object.freeze({
	desktop: '100%',
	mobile: '375px',
	tablet: '782px',
});

const optionFields = Object.freeze({
	date: { field: 'showDate', label: __('Date', 'turnierplan-eu') },
	extra_time: {
		field: 'showExtraTime',
		label: __('Extra time', 'turnierplan-eu'),
	},
	field: { field: 'showField', label: __('Field or court', 'turnierplan-eu') },
	group: { field: 'showGroup', label: __('Group', 'turnierplan-eu') },
	group_navigation: {
		field: 'enableGroupNavigation',
		label: __('Group navigation', 'turnierplan-eu'),
	},
	live_state: {
		field: 'showLiveState',
		label: __('Live status', 'turnierplan-eu'),
	},
	match_number: {
		field: 'showMatchNumber',
		label: __('Match number', 'turnierplan-eu'),
	},
	penalty_result: {
		field: 'showPenaltyResult',
		label: __('Decision details', 'turnierplan-eu'),
	},
	played: { field: 'showPlayed', label: __('Matches played', 'turnierplan-eu') },
	points: { field: 'showPoints', label: __('Points', 'turnierplan-eu') },
	referee: { field: 'showReferee', label: __('Referee', 'turnierplan-eu') },
	round: { field: 'showRound', label: __('Round', 'turnierplan-eu') },
	score_balance: {
		field: 'showScoreBalance',
		label: __('Goal, set, or point balance', 'turnierplan-eu'),
	},
	team_logos: { field: 'showTeamLogos', label: __('Team logos', 'turnierplan-eu') },
	time: { field: 'showTime', label: __('Time', 'turnierplan-eu') },
	wins_draws_losses: {
		field: 'showWinsDrawsLosses',
		label: __('Wins, draws, and losses', 'turnierplan-eu'),
	},
});

const tournamentStateLabels = Object.freeze({
	cancelled: __('Cancelled', 'turnierplan-eu'),
	completed: __('Completed', 'turnierplan-eu'),
	live: __('Live', 'turnierplan-eu'),
	upcoming: __('Upcoming', 'turnierplan-eu'),
});

const useDebouncedValue = (value, delay) => {
	const [debounced, setDebounced] = useState(value);

	useEffect(() => {
		const timeout = window.setTimeout(() => setDebounced(value), delay);
		return () => window.clearTimeout(timeout);
	}, [value, delay]);

	return debounced;
};

const LocalExample = () => (
	<div className="tpeu-block-editor__example">
		<p className="tpeu-block-editor__example-title">
			{__('Local example: tournament standings', 'turnierplan-eu')}
		</p>
		<table>
			<thead>
				<tr>
					<th>{__('Rank', 'turnierplan-eu')}</th>
					<th>{__('Team', 'turnierplan-eu')}</th>
					<th>{__('Played', 'turnierplan-eu')}</th>
					<th>{__('Points', 'turnierplan-eu')}</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>1</td>
					<td>{__('Example Club North', 'turnierplan-eu')}</td>
					<td>3</td>
					<td>7</td>
				</tr>
				<tr>
					<td>2</td>
					<td>{__('Example Club South', 'turnierplan-eu')}</td>
					<td>3</td>
					<td>5</td>
				</tr>
			</tbody>
		</table>
		<p>{__('This example does not load external data.', 'turnierplan-eu')}</p>
	</div>
);

const errorMessage = (error) =>
	typeof error?.message === 'string' && error.message !== ''
		? error.message
		: __('The tournament metadata could not be loaded.', 'turnierplan-eu');

function Edit({ attributes, setAttributes }) {
	const config = useMemo(
		() => ({ ...defaults, ...(attributes.config ?? {}) }),
		[attributes.config]
	);
	const blockProps = useBlockProps({ className: 'tpeu-block-editor' });
	const [referenceInput, setReferenceInput] = useState(config.tournamentRef);
	const [state, dispatch] = useReducer(metadataReducer, initialMetadataState);
	const [showExample, setShowExample] = useState(false);
	const [previewDevice, setPreviewDevice] = useState('desktop');
	const [configWarnings, setConfigWarnings] = useState([]);
	const [presets, setPresets] = useState([]);
	const [presetError, setPresetError] = useState(null);
	const loader = useRef(null);
	const autoLoaded = useRef(false);
	const errorFocus = useRef(null);
	const debouncedConfig = useDebouncedValue(config, 250);

	if (loader.current === null) {
		loader.current = createMetadataLoader(apiFetch);
	}

	useEffect(() => {
		if (!attributes.initialized) {
			setAttributes({
				config: { ...config, ...editorSettings.setupDefaults },
				initialized: true,
			});
		}
	}, [attributes.initialized, config, setAttributes]);

	useEffect(() => () => loader.current?.cancel(), []);

	useEffect(() => {
		if (state.status === 'error') {
			errorFocus.current?.focus();
		}
	}, [state.status]);

	useEffect(() => {
		let active = true;
		apiFetch({ path: '/turnierplan-eu/v1/presets' })
			.then((response) => {
				if (active) {
					setPresets(Array.isArray(response?.presets) ? response.presets : []);
				}
			})
			.catch((error) => active && setPresetError(error));
		return () => {
			active = false;
		};
	}, []);

	const updateConfig = useCallback(
		(patch) => setAttributes({ config: { ...config, ...patch } }),
		[config, setAttributes]
	);

	const loadMetadata = useCallback(
		async (
			reference = referenceInput,
			language = config.language,
			baseConfig = config
		) => {
			const value = reference.trim();

			if (value === '') {
				dispatch({
					error: new Error(__('Enter a tournament reference.', 'turnierplan-eu')),
					type: 'error',
				});
				return;
			}

			dispatch({ type: 'loading' });
			setShowExample(false);
			const result = await loader.current.load({
				data: { language, reference: value },
				method: 'POST',
				path: '/turnierplan-eu/v1/metadata/resolve',
			});

			if (result.status === 'ignored') {
				return;
			}

			if (result.status === 'error') {
				dispatch({ error: result.error, type: 'error' });
				return;
			}

			setConfigWarnings(
				configurationWarnings(baseConfig, result.data.metadata)
			);
			const nextConfig = reconcileMetadata(baseConfig, result.data.metadata);
			setAttributes({ config: nextConfig, initialized: true, presetId: 0 });
			setReferenceInput(nextConfig.tournamentRef);
			dispatch({
				metadata: result.data.metadata,
				stale: Boolean(result.data.cache?.stale),
				type: 'success',
			});
		},
		[config, referenceInput, setAttributes]
	);

	useEffect(() => {
		if (
			!autoLoaded.current &&
			(attributes.presetId ?? 0) === 0 &&
			attributes.initialized &&
			config.schemaVersion === 1 &&
			config.tournamentRef !== '' &&
			editorSettings.serviceEnabled
		) {
			autoLoaded.current = true;
			loadMetadata(config.tournamentRef, config.language, config);
		}
	}, [attributes.initialized, config, loadMetadata]);

	const selectPreset = (value) => {
		const presetId = Number.parseInt(value, 10);

		if (presetId > 0) {
			setAttributes({ initialized: true, presetId });
			dispatch({ type: 'reset' });
			return;
		}

		const copiedConfig = inlineConfigForPreset(
			presets,
			attributes.presetId,
			config
		);
		setAttributes({ config: copiedConfig, initialized: true, presetId: 0 });
		setReferenceInput(copiedConfig.tournamentRef ?? '');
	};

	const tournament = state.metadata?.tournament ?? null;
	const schemaSupported = config.schemaVersion === 1;
	const activeView = state.metadata?.views.find(({ id }) => id === config.view) ?? null;
	const filters = new Set(activeView?.filters ?? []);
	const availableParticipants = participantsForGroup(state.metadata, config.group);

	const setView = (view) => {
		setConfigWarnings((warnings) => warnings.filter((warning) => warning !== 'view'));
		if (state.metadata === null) {
			updateConfig({ view });
			return;
		}

		setAttributes({ config: changeView(config, view, state.metadata) });
	};

	const setGroup = (group) => {
		setConfigWarnings((warnings) =>
			warnings.filter(
				(warning) => warning !== 'group' && warning !== 'participant'
			)
		);
		const value = group === '' ? null : group;
		const participants = participantsForGroup(state.metadata, value);
		const participant = participants.some(({ id }) => id === config.participant)
			? config.participant
			: null;
		updateConfig({ group: value, participant });
	};

	const setInteger = (field, value, relatedField) => {
		if (value === '') {
			updateConfig({ [field]: null });
			return;
		}

		const number = Number.parseInt(value, 10);

		if (!Number.isSafeInteger(number) || number < 1 || number > 999999) {
			return;
		}

		const patch = { [field]: number };

		if (
			relatedField !== undefined &&
			config[relatedField] !== null &&
			((field === 'matchFrom' && number > config[relatedField]) ||
				(field === 'matchTo' && number < config[relatedField]))
		) {
			patch[relatedField] = number;
		}

		updateConfig(patch);
	};

	const setDate = (field, value, relatedField) => {
		const normalized = value === '' ? null : value;
		const patch = { [field]: normalized };

		if (
			normalized !== null &&
			config[relatedField] !== null &&
			((field === 'dateFrom' && normalized > config[relatedField]) ||
				(field === 'dateTo' && normalized < config[relatedField]))
		) {
			patch[relatedField] = normalized;
		}

		updateConfig(patch);
	};

	const languageChanged = (language) => {
		const nextConfig = { ...config, language };
		setAttributes({ config: nextConfig });

		if (state.metadata !== null) {
			loadMetadata(config.tournamentRef, language, nextConfig);
		}
	};

	const viewOptions = (state.metadata?.views ?? [
		{ id: 'standings' },
		{ id: 'matches' },
	]).map(({ id }) => ({
		label:
			id === 'matches'
				? __('Schedule', 'turnierplan-eu')
				: __('Standings', 'turnierplan-eu'),
		value: id,
	}));

	const languageOptions = [
		{ label: __('Automatic', 'turnierplan-eu'), value: 'auto' },
		...(state.metadata?.supported_languages ?? []).map((language) => ({
			label: language,
			value: language,
		})),
	].filter(
		(option, index, options) =>
			options.findIndex(({ value }) => value === option.value) === index
	);

	const connectPanel = (
		<PanelBody title={__('Tournament', 'turnierplan-eu')} initialOpen>
			<TextControl
				label={__('Tournament ID, slug, or URL', 'turnierplan-eu')}
				value={referenceInput}
				onChange={setReferenceInput}
			/>
			<Button
				variant="secondary"
				disabled={
					!editorSettings.serviceEnabled ||
					!schemaSupported ||
					state.status === 'loading'
				}
				onClick={() => loadMetadata()}
			>
				{state.metadata === null
					? __('Connect tournament', 'turnierplan-eu')
					: __('Refresh connection', 'turnierplan-eu')}
			</Button>
			<SelectControl
				label={__('Language', 'turnierplan-eu')}
				value={config.language}
				options={languageOptions}
				onChange={languageChanged}
			/>
		</PanelBody>
	);

	const presetPanel = (
		<PanelBody title={__('Reusable preset', 'turnierplan-eu')}>
			<SelectControl
				label={__('Source', 'turnierplan-eu')}
				value={String(attributes.presetId ?? 0)}
				options={[
					{ label: __('Inline configuration', 'turnierplan-eu'), value: '0' },
					...presets.map(({ id, title }) => ({ label: title, value: String(id) })),
				]}
				onChange={selectPreset}
			/>
			{presetError !== null && (
				<Notice status="warning" isDismissible={false}>
					{__('The preset list could not be loaded.', 'turnierplan-eu')}
				</Notice>
			)}
			{(attributes.presetId ?? 0) > 0 && (
				<p>{__('Changes to the preset automatically apply everywhere it is used. Switching to inline copies the current values once.', 'turnierplan-eu')}</p>
			)}
		</PanelBody>
	);

	if ((attributes.presetId ?? 0) > 0) {
		return (
			<>
				<InspectorControls>{presetPanel}</InspectorControls>
				<div {...blockProps}>
					<ServerSideRender
						block="turnierplan-eu/embed"
						attributes={{ presetId: attributes.presetId, initialized: true }}
					/>
				</div>
			</>
		);
	}

	const inspector = (
		<InspectorControls>
			{presetPanel}
			{connectPanel}
			<PanelBody title={__('Appearance', 'turnierplan-eu')}>
				<SelectControl
					label={__('Color scheme', 'turnierplan-eu')}
					value={config.theme}
					options={[
						{ label: __('Automatic', 'turnierplan-eu'), value: 'auto' },
						{ label: __('Light', 'turnierplan-eu'), value: 'light' },
						{ label: __('Dark', 'turnierplan-eu'), value: 'dark' },
					]}
					onChange={(theme) => updateConfig({ theme })}
				/>
				<SelectControl
					label={__('Density', 'turnierplan-eu')}
					value={config.density}
					options={[
						{ label: __('Comfortable', 'turnierplan-eu'), value: 'comfortable' },
						{ label: __('Compact', 'turnierplan-eu'), value: 'compact' },
					]}
					onChange={(density) => updateConfig({ density })}
				/>
				<p>{__('Accent color', 'turnierplan-eu')}</p>
				<ColorPalette
					aria-label={__('Accent color', 'turnierplan-eu')}
					clearable
					value={config.accentColor ?? undefined}
					onChange={(accentColor) =>
						updateConfig({ accentColor: accentColor?.toUpperCase() ?? null })
					}
				/>
			</PanelBody>
			{activeView !== null && (
				<PanelBody title={__('Displayed information', 'turnierplan-eu')}>
					{activeView.options.map((option) => {
						const definition = optionFields[option];

						if (definition === undefined || option === 'date') {
							return null;
						}

						return (
							<ToggleControl
								key={option}
								label={definition.label}
								checked={Boolean(config[definition.field])}
								onChange={(value) =>
									updateConfig({ [definition.field]: value })
								}
							/>
						);
					})}
					{activeView.options.includes('date') && (
						<SelectControl
							label={__('Show date', 'turnierplan-eu')}
							value={config.showDate}
							options={[
								{ label: __('Automatic', 'turnierplan-eu'), value: 'auto' },
								{ label: __('Show', 'turnierplan-eu'), value: 'show' },
								{ label: __('Hide', 'turnierplan-eu'), value: 'hide' },
							]}
							onChange={(showDate) => updateConfig({ showDate })}
						/>
					)}
				</PanelBody>
			)}
			{activeView !== null &&
				(filters.has('match_number_range') || filters.has('date_range')) && (
					<PanelBody title={__('Additional filters', 'turnierplan-eu')}>
						{filters.has('match_number_range') && (
							<>
								<TextControl
									label={__('First match number', 'turnierplan-eu')}
									type="number"
									value={config.matchFrom ?? ''}
									onChange={(value) => setInteger('matchFrom', value, 'matchTo')}
								/>
								<TextControl
									label={__('Last match number', 'turnierplan-eu')}
									type="number"
									value={config.matchTo ?? ''}
									onChange={(value) => setInteger('matchTo', value, 'matchFrom')}
								/>
							</>
						)}
						{filters.has('date_range') && (
							<>
								<TextControl
									label={__('Start date', 'turnierplan-eu')}
									type="date"
									value={config.dateFrom ?? ''}
									onChange={(value) => setDate('dateFrom', value, 'dateTo')}
								/>
								<TextControl
									label={__('End date', 'turnierplan-eu')}
									type="date"
									value={config.dateTo ?? ''}
									onChange={(value) => setDate('dateTo', value, 'dateFrom')}
								/>
							</>
						)}
					</PanelBody>
				)}
			<PanelBody title={__('Advanced', 'turnierplan-eu')} initialOpen={false}>
				<RangeControl
					label={__('Minimum height', 'turnierplan-eu')}
					min={160}
					max={2000}
					value={config.minHeight}
					onChange={(minHeight) => {
						if (Number.isInteger(minHeight)) {
							updateConfig({
								maxHeight: Math.max(config.maxHeight, minHeight),
								minHeight,
							});
						}
					}}
				/>
				<RangeControl
					label={__('Maximum height', 'turnierplan-eu')}
					min={300}
					max={8000}
					value={config.maxHeight}
					onChange={(maxHeight) => {
						if (Number.isInteger(maxHeight)) {
							updateConfig({
								maxHeight,
								minHeight: Math.min(config.minHeight, maxHeight),
							});
						}
					}}
				/>
				<ToggleControl
					label={__('Open links in a new tab', 'turnierplan-eu')}
					checked={config.openLinksInNewTab}
					onChange={(openLinksInNewTab) => updateConfig({ openLinksInNewTab })}
				/>
				{state.metadata?.branding.policy === 'optional' && (
					<ToggleControl
						label={__('Show Turnierplan.eu branding', 'turnierplan-eu')}
						checked={config.showBranding}
						onChange={(showBranding) => updateConfig({ showBranding })}
					/>
				)}
			</PanelBody>
		</InspectorControls>
	);

	if (state.metadata === null) {
		return (
			<>
				{inspector}
				<div {...blockProps}>
					<Placeholder
						icon="chart-bar"
						label={__('Turnierplan.eu', 'turnierplan-eu')}
						instructions={__(
							'Enter a public tournament ID, slug, or Turnierplan.eu URL.',
							'turnierplan-eu'
						)}
					>
						<TextControl
							label={__('Tournament ID, slug, or URL', 'turnierplan-eu')}
							value={referenceInput}
							onChange={setReferenceInput}
						/>
						<div className="tpeu-block-editor__actions">
							<Button
								variant="primary"
								disabled={
									!editorSettings.serviceEnabled ||
									!schemaSupported ||
									state.status === 'loading'
								}
								onClick={() => loadMetadata()}
							>
								{state.status === 'loading' && <Spinner />}
								{__('Connect tournament', 'turnierplan-eu')}
							</Button>
							<Button
								variant="tertiary"
								onClick={() => setShowExample((visible) => !visible)}
							>
								{showExample
									? __('Close example', 'turnierplan-eu')
									: __('View example', 'turnierplan-eu')}
							</Button>
						</div>
						<p className="tpeu-block-editor__service-note">
							{__(
								'When connecting, the WordPress server retrieves public metadata from Turnierplan.eu.',
								'turnierplan-eu'
							)}
						</p>
						{!editorSettings.serviceEnabled && (
							<Notice status="warning" isDismissible={false}>
								{__(
									'The external service must first be enabled under Settings → Turnierplan.eu.',
									'turnierplan-eu'
								)}
							</Notice>
						)}
						{state.status === 'error' && (
							<div ref={errorFocus} tabIndex="-1">
								<Notice status="error" isDismissible={false}>
									{errorMessage(state.error)}
								</Notice>
							</div>
						)}
						{!schemaSupported && (
							<Notice status="error" isDismissible={false}>
								<p>
									{__(
										'This configuration version is unsupported and must be reset.',
										'turnierplan-eu'
									)}
								</p>
								<Button
									variant="secondary"
									onClick={() => {
										setAttributes({
											config: { ...defaults, ...editorSettings.setupDefaults },
											initialized: true,
											presetId: 0,
										});
										setReferenceInput('');
										dispatch({ type: 'reset' });
									}}
								>
									{__('Reset configuration', 'turnierplan-eu')}
								</Button>
							</Notice>
						)}
						{showExample && <LocalExample />}
					</Placeholder>
				</div>
			</>
		);
	}

	return (
		<>
			{inspector}
			<BlockControls>
				<ToolbarGroup>
					{viewOptions.map((option) => (
						<ToolbarButton
							key={option.value}
							isPressed={config.view === option.value}
							onClick={() => setView(option.value)}
						>
							{option.label}
						</ToolbarButton>
					))}
				</ToolbarGroup>
				<ToolbarGroup>
					{['desktop', 'tablet', 'mobile'].map((device) => (
						<ToolbarButton
							key={device}
							isPressed={previewDevice === device}
							onClick={() => setPreviewDevice(device)}
						>
							{{
								desktop: __('Desktop', 'turnierplan-eu'),
								mobile: __('Mobile', 'turnierplan-eu'),
								tablet: __('Tablet', 'turnierplan-eu'),
							}[device]}
						</ToolbarButton>
					))}
				</ToolbarGroup>
			</BlockControls>
			<div {...blockProps}>
				<div className="tpeu-block-editor__heading">
					<div>
						<strong>{tournament.title}</strong>
						<p>
							{sprintf(
								/* translators: %s: tournament state. */
								__('Status: %s', 'turnierplan-eu'),
								tournamentStateLabels[tournament.state] ?? tournament.state
							)}
						</p>
					</div>
					<ExternalLink href={tournament.public_url}>
						{__('Open full tournament', 'turnierplan-eu')}
					</ExternalLink>
				</div>
				<div className="tpeu-block-editor__controls">
					<SelectControl
						label={__('View', 'turnierplan-eu')}
						value={config.view}
						options={viewOptions}
						onChange={setView}
					/>
					{filters.has('group') && (
						<SelectControl
							label={__('Group', 'turnierplan-eu')}
							value={config.group ?? ''}
							options={[
								{ label: __('All groups', 'turnierplan-eu'), value: '' },
								...state.metadata.groups.map(({ id, label }) => ({
									label,
									value: id,
								})),
							]}
							onChange={setGroup}
						/>
					)}
					{filters.has('participant') && (
						<SelectControl
							label={__('Participant', 'turnierplan-eu')}
							value={config.participant ?? ''}
							options={[
								{ label: __('All participants', 'turnierplan-eu'), value: '' },
								...availableParticipants.map(({ id, label }) => ({
									label,
									value: id,
								})),
							]}
							onChange={(participant) =>
								updateConfig({ participant: participant || null })
							}
						/>
					)}
					<SelectControl
						label={__('Language', 'turnierplan-eu')}
						value={config.language}
						options={languageOptions}
						onChange={languageChanged}
					/>
				</div>
				<div className="tpeu-block-editor__status" aria-live="polite">
					{configWarnings.includes('view') && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'The previously selected view is unavailable. An available view was selected.',
								'turnierplan-eu'
							)}
						</Notice>
					)}
					{configWarnings.includes('group') && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'The saved group is no longer available and was reset to all groups.',
								'turnierplan-eu'
							)}
						</Notice>
					)}
					{configWarnings.includes('participant') && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'The saved participant is no longer available and was reset.',
								'turnierplan-eu'
							)}
						</Notice>
					)}
					{(state.status === 'loading' || state.status === 'refreshing') && (
						<><Spinner /> {__('Updating tournament…', 'turnierplan-eu')}</>
					)}
					{state.status === 'stale' && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'The last successful response is shown because Turnierplan.eu is temporarily unavailable.',
								'turnierplan-eu'
							)}
						</Notice>
					)}
					{state.status === 'error' && (
						<div ref={errorFocus} tabIndex="-1">
							<Notice status="error" isDismissible={false}>
								{errorMessage(state.error)}
							</Notice>
						</div>
					)}
					{tournament.state === 'completed' && (
						<Notice status="info" isDismissible={false}>
							{__(
								'The tournament has ended and remains publicly available.',
								'turnierplan-eu'
							)}
						</Notice>
					)}
					<small>
						{sprintf(
							/* translators: %s: localized metadata update date and time. */
							__('Metadata updated: %s', 'turnierplan-eu'),
							formatMetadataTimestamp(
								tournament.updated_at,
								document.documentElement.lang || undefined
							)
						)}
					</small>
				</div>
				<div
					className="tpeu-block-editor__preview"
					style={{ maxWidth: previewWidths[previewDevice] }}
				>
					<ServerSideRender
						block="turnierplan-eu/embed"
						attributes={{
							config: debouncedConfig,
							initialized: true,
							presetId: 0,
						}}
						httpMethod="POST"
					/>
				</div>
			</div>
		</>
	);
}

registerBlockType(metadata, {
	edit: Edit,
	save: () => null,
});
