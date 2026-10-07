import metadata from '../../blocks/embed/block.json';
import {
	changeView,
	configurationWarnings,
	createMetadataLoader,
	initialMetadataState,
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
	date: { field: 'showDate', label: __('Datum', 'turnierplan-eu') },
	extra_time: {
		field: 'showExtraTime',
		label: __('Verlängerung', 'turnierplan-eu'),
	},
	field: { field: 'showField', label: __('Feld oder Platz', 'turnierplan-eu') },
	group: { field: 'showGroup', label: __('Gruppe', 'turnierplan-eu') },
	group_navigation: {
		field: 'enableGroupNavigation',
		label: __('Gruppennavigation', 'turnierplan-eu'),
	},
	live_state: {
		field: 'showLiveState',
		label: __('Live-Status', 'turnierplan-eu'),
	},
	match_number: {
		field: 'showMatchNumber',
		label: __('Spielnummer', 'turnierplan-eu'),
	},
	penalty_result: {
		field: 'showPenaltyResult',
		label: __('Entscheidungsdetails', 'turnierplan-eu'),
	},
	played: { field: 'showPlayed', label: __('Gespielte Partien', 'turnierplan-eu') },
	points: { field: 'showPoints', label: __('Punkte', 'turnierplan-eu') },
	referee: { field: 'showReferee', label: __('Schiedsrichter', 'turnierplan-eu') },
	round: { field: 'showRound', label: __('Runde', 'turnierplan-eu') },
	score_balance: {
		field: 'showScoreBalance',
		label: __('Tor-, Satz- oder Punktebilanz', 'turnierplan-eu'),
	},
	team_logos: { field: 'showTeamLogos', label: __('Teamlogos', 'turnierplan-eu') },
	time: { field: 'showTime', label: __('Uhrzeit', 'turnierplan-eu') },
	wins_draws_losses: {
		field: 'showWinsDrawsLosses',
		label: __('Siege, Remis und Niederlagen', 'turnierplan-eu'),
	},
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
			{__('Lokales Beispiel: Turniertabelle', 'turnierplan-eu')}
		</p>
		<table>
			<thead>
				<tr>
					<th>{__('Platz', 'turnierplan-eu')}</th>
					<th>{__('Team', 'turnierplan-eu')}</th>
					<th>{__('Spiele', 'turnierplan-eu')}</th>
					<th>{__('Punkte', 'turnierplan-eu')}</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>1</td>
					<td>{__('Beispielverein Nord', 'turnierplan-eu')}</td>
					<td>3</td>
					<td>7</td>
				</tr>
				<tr>
					<td>2</td>
					<td>{__('Beispielverein Süd', 'turnierplan-eu')}</td>
					<td>3</td>
					<td>5</td>
				</tr>
			</tbody>
		</table>
		<p>{__('Dieses Beispiel lädt keine externen Daten.', 'turnierplan-eu')}</p>
	</div>
);

const errorMessage = (error) =>
	typeof error?.message === 'string' && error.message !== ''
		? error.message
		: __('Die Turniermetadaten konnten nicht geladen werden.', 'turnierplan-eu');

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
	const loader = useRef(null);
	const autoLoaded = useRef(false);
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
					error: new Error(__('Bitte gib eine Turnierreferenz ein.', 'turnierplan-eu')),
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
			attributes.initialized &&
			config.schemaVersion === 1 &&
			config.tournamentRef !== '' &&
			editorSettings.serviceEnabled
		) {
			autoLoaded.current = true;
			loadMetadata(config.tournamentRef, config.language, config);
		}
	}, [attributes.initialized, config, loadMetadata]);

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
				? __('Spielplan', 'turnierplan-eu')
				: __('Turniertabelle', 'turnierplan-eu'),
		value: id,
	}));

	const languageOptions = [
		{ label: __('Automatisch', 'turnierplan-eu'), value: 'auto' },
		...(state.metadata?.supported_languages ?? []).map((language) => ({
			label: language,
			value: language,
		})),
	].filter(
		(option, index, options) =>
			options.findIndex(({ value }) => value === option.value) === index
	);

	const connectPanel = (
		<PanelBody title={__('Turnier', 'turnierplan-eu')} initialOpen>
			<TextControl
				label={__('Turnier-ID, Slug oder URL', 'turnierplan-eu')}
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
					? __('Turnier verbinden', 'turnierplan-eu')
					: __('Verbindung aktualisieren', 'turnierplan-eu')}
			</Button>
			<SelectControl
				label={__('Sprache', 'turnierplan-eu')}
				value={config.language}
				options={languageOptions}
				onChange={languageChanged}
			/>
		</PanelBody>
	);

	const inspector = (
		<InspectorControls>
			{connectPanel}
			<PanelBody title={__('Darstellung', 'turnierplan-eu')}>
				<SelectControl
					label={__('Farbschema', 'turnierplan-eu')}
					value={config.theme}
					options={[
						{ label: __('Automatisch', 'turnierplan-eu'), value: 'auto' },
						{ label: __('Hell', 'turnierplan-eu'), value: 'light' },
						{ label: __('Dunkel', 'turnierplan-eu'), value: 'dark' },
					]}
					onChange={(theme) => updateConfig({ theme })}
				/>
				<SelectControl
					label={__('Dichte', 'turnierplan-eu')}
					value={config.density}
					options={[
						{ label: __('Komfortabel', 'turnierplan-eu'), value: 'comfortable' },
						{ label: __('Kompakt', 'turnierplan-eu'), value: 'compact' },
					]}
					onChange={(density) => updateConfig({ density })}
				/>
				<p>{__('Akzentfarbe', 'turnierplan-eu')}</p>
				<ColorPalette
					clearable
					value={config.accentColor ?? undefined}
					onChange={(accentColor) =>
						updateConfig({ accentColor: accentColor?.toUpperCase() ?? null })
					}
				/>
			</PanelBody>
			{activeView !== null && (
				<PanelBody title={__('Angezeigte Informationen', 'turnierplan-eu')}>
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
							label={__('Datum anzeigen', 'turnierplan-eu')}
							value={config.showDate}
							options={[
								{ label: __('Automatisch', 'turnierplan-eu'), value: 'auto' },
								{ label: __('Anzeigen', 'turnierplan-eu'), value: 'show' },
								{ label: __('Ausblenden', 'turnierplan-eu'), value: 'hide' },
							]}
							onChange={(showDate) => updateConfig({ showDate })}
						/>
					)}
				</PanelBody>
			)}
			{activeView !== null &&
				(filters.has('match_number_range') || filters.has('date_range')) && (
					<PanelBody title={__('Weitere Filter', 'turnierplan-eu')}>
						{filters.has('match_number_range') && (
							<>
								<TextControl
									label={__('Spielnummer von', 'turnierplan-eu')}
									type="number"
									value={config.matchFrom ?? ''}
									onChange={(value) => setInteger('matchFrom', value, 'matchTo')}
								/>
								<TextControl
									label={__('Spielnummer bis', 'turnierplan-eu')}
									type="number"
									value={config.matchTo ?? ''}
									onChange={(value) => setInteger('matchTo', value, 'matchFrom')}
								/>
							</>
						)}
						{filters.has('date_range') && (
							<>
								<TextControl
									label={__('Datum von', 'turnierplan-eu')}
									type="date"
									value={config.dateFrom ?? ''}
									onChange={(value) => setDate('dateFrom', value, 'dateTo')}
								/>
								<TextControl
									label={__('Datum bis', 'turnierplan-eu')}
									type="date"
									value={config.dateTo ?? ''}
									onChange={(value) => setDate('dateTo', value, 'dateFrom')}
								/>
							</>
						)}
					</PanelBody>
				)}
			<PanelBody title={__('Erweitert', 'turnierplan-eu')} initialOpen={false}>
				<RangeControl
					label={__('Mindesthöhe', 'turnierplan-eu')}
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
					label={__('Maximalhöhe', 'turnierplan-eu')}
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
					label={__('Links in neuem Tab öffnen', 'turnierplan-eu')}
					checked={config.openLinksInNewTab}
					onChange={(openLinksInNewTab) => updateConfig({ openLinksInNewTab })}
				/>
				{state.metadata?.branding.policy === 'optional' && (
					<ToggleControl
						label={__('Turnierplan.eu-Branding anzeigen', 'turnierplan-eu')}
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
							'Gib eine öffentliche Turnier-ID, einen Slug oder eine Turnierplan.eu-URL ein.',
							'turnierplan-eu'
						)}
					>
						<TextControl
							label={__('Turnier-ID, Slug oder URL', 'turnierplan-eu')}
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
								{__('Turnier verbinden', 'turnierplan-eu')}
							</Button>
							<Button
								variant="tertiary"
								onClick={() => setShowExample((visible) => !visible)}
							>
								{showExample
									? __('Beispiel schließen', 'turnierplan-eu')
									: __('Beispiel ansehen', 'turnierplan-eu')}
							</Button>
						</div>
						<p className="tpeu-block-editor__service-note">
							{__(
								'Beim Verbinden ruft der WordPress-Server öffentliche Metadaten von Turnierplan.eu ab.',
								'turnierplan-eu'
							)}
						</p>
						{!editorSettings.serviceEnabled && (
							<Notice status="warning" isDismissible={false}>
								{__(
									'Der externe Dienst muss zuerst unter Einstellungen → Turnierplan.eu freigegeben werden.',
									'turnierplan-eu'
								)}
							</Notice>
						)}
						{state.status === 'error' && (
							<Notice status="error" isDismissible={false}>
								{errorMessage(state.error)}
							</Notice>
						)}
						{!schemaSupported && (
							<Notice status="error" isDismissible={false}>
								<p>
									{__(
										'Diese Konfigurationsversion wird nicht unterstützt und muss zurückgesetzt werden.',
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
									{__('Konfiguration zurücksetzen', 'turnierplan-eu')}
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
								mobile: __('Mobil', 'turnierplan-eu'),
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
								tournament.state
							)}
						</p>
					</div>
					<ExternalLink href={tournament.public_url}>
						{__('Vollständiges Turnier öffnen', 'turnierplan-eu')}
					</ExternalLink>
				</div>
				<div className="tpeu-block-editor__controls">
					<SelectControl
						label={__('Ansicht', 'turnierplan-eu')}
						value={config.view}
						options={viewOptions}
						onChange={setView}
					/>
					{filters.has('group') && (
						<SelectControl
							label={__('Gruppe', 'turnierplan-eu')}
							value={config.group ?? ''}
							options={[
								{ label: __('Alle Gruppen', 'turnierplan-eu'), value: '' },
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
							label={__('Teilnehmer', 'turnierplan-eu')}
							value={config.participant ?? ''}
							options={[
								{ label: __('Alle Teilnehmer', 'turnierplan-eu'), value: '' },
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
						label={__('Sprache', 'turnierplan-eu')}
						value={config.language}
						options={languageOptions}
						onChange={languageChanged}
					/>
				</div>
				<div className="tpeu-block-editor__status" aria-live="polite">
					{configWarnings.includes('view') && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'Die zuvor gewählte Ansicht ist nicht verfügbar. Eine verfügbare Ansicht wurde ausgewählt.',
								'turnierplan-eu'
							)}
						</Notice>
					)}
					{configWarnings.includes('group') && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'Die gespeicherte Gruppe ist nicht mehr verfügbar und wurde auf alle Gruppen zurückgesetzt.',
								'turnierplan-eu'
							)}
						</Notice>
					)}
					{configWarnings.includes('participant') && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'Der gespeicherte Teilnehmer ist nicht mehr verfügbar und wurde zurückgesetzt.',
								'turnierplan-eu'
							)}
						</Notice>
					)}
					{(state.status === 'loading' || state.status === 'refreshing') && (
						<><Spinner /> {__('Turnier wird aktualisiert …', 'turnierplan-eu')}</>
					)}
					{state.status === 'stale' && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'Die letzte erfolgreiche Antwort wird angezeigt, weil Turnierplan.eu vorübergehend nicht erreichbar ist.',
								'turnierplan-eu'
							)}
						</Notice>
					)}
					{state.status === 'error' && (
						<Notice status="error" isDismissible={false}>
							{errorMessage(state.error)}
						</Notice>
					)}
					{tournament.state === 'completed' && (
						<Notice status="info" isDismissible={false}>
							{__(
								'Das Turnier ist beendet und weiterhin öffentlich verfügbar.',
								'turnierplan-eu'
							)}
						</Notice>
					)}
					<small>
						{sprintf(
							/* translators: %s: ISO timestamp supplied by Turnierplan.eu. */
							__('Metadaten aktualisiert: %s', 'turnierplan-eu'),
							tournament.updated_at
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
