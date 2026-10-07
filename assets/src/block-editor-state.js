export const initialMetadataState = Object.freeze({
	error: null,
	metadata: null,
	status: 'empty',
	wasStale: false,
});

export function metadataReducer(state, action) {
	switch (action.type) {
		case 'loading':
			return {
				...state,
				error: null,
				status: state.metadata === null ? 'loading' : 'refreshing',
			};
		case 'success':
			return {
				error: null,
				metadata: action.metadata,
				status: action.stale ? 'stale' : 'success',
				wasStale: Boolean(action.stale),
			};
		case 'error':
			return {
				...state,
				error: action.error,
				status: 'error',
			};
		case 'reset':
			return initialMetadataState;
		default:
			return state;
	}
}

const viewDefinition = (metadata, view) =>
	metadata?.views?.find(({ id }) => id === view) ?? null;

const hasReference = (items, reference) =>
	reference === null || items.some(({ id }) => id === reference);

export function participantsForGroup(metadata, group) {
	const participants = metadata?.participants ?? [];

	if (group === null) {
		return participants;
	}

	return participants.filter(({ group_ids: groupIds }) =>
		groupIds.includes(group)
	);
}

export function configurationWarnings(config, metadata) {
	const definition = viewDefinition(metadata, config.view);

	if (definition === null) {
		return ['view'];
	}

	const warnings = [];
	const filters = new Set(definition.filters);
	const groupExists = hasReference(metadata.groups ?? [], config.group);

	if (config.group !== null && (!filters.has('group') || !groupExists)) {
		warnings.push('group');
	}

	const participantExists = hasReference(
		participantsForGroup(metadata, groupExists ? config.group : null),
		config.participant
	);

	if (
		config.participant !== null &&
		(!filters.has('participant') || !participantExists)
	) {
		warnings.push('participant');
	}

	return warnings;
}

export function changeView(config, view, metadata) {
	const definition = viewDefinition(metadata, view);

	if (definition === null) {
		return config;
	}

	const filters = new Set(definition.filters);
	const groups = metadata.groups ?? [];
	const group =
		filters.has('group') && hasReference(groups, config.group)
			? config.group
			: null;
	const participants = participantsForGroup(metadata, group);

	return {
		...config,
		view,
		group,
		participant:
			filters.has('participant') &&
			hasReference(participants, config.participant)
				? config.participant
				: null,
		matchFrom: filters.has('match_number_range') ? config.matchFrom : null,
		matchTo: filters.has('match_number_range') ? config.matchTo : null,
		dateFrom: filters.has('date_range') ? config.dateFrom : null,
		dateTo: filters.has('date_range') ? config.dateTo : null,
	};
}

export function reconcileMetadata(config, metadata) {
	const availableViews = metadata.views.map(({ id }) => id);
	const view = availableViews.includes(config.view)
		? config.view
		: availableViews[0];
	let next = changeView(
		{
			...config,
			tournamentRef: metadata.tournament.ref,
		},
		view,
		metadata
	);

	if (metadata.branding.policy === 'required') {
		next = { ...next, showBranding: true };
	} else if (metadata.branding.policy === 'hidden') {
		next = { ...next, showBranding: false };
	}

	return next;
}

export function inlineConfigForPreset(presets, presetId, fallbackConfig) {
	const selected = presets.find(({ id }) => id === presetId);

	return selected?.config ?? fallbackConfig;
}

export function createMetadataLoader(apiFetch, AbortControllerClass) {
	const Controller =
		AbortControllerClass ?? globalThis.AbortController ?? null;
	let controller = null;
	let sequence = 0;

	const cancel = () => {
		sequence += 1;
		controller?.abort();
		controller = null;
	};

	const load = async (options) => {
		controller?.abort();
		controller = Controller === null ? null : new Controller();
		const request = ++sequence;

		try {
			const data = await apiFetch({
				...options,
				...(controller === null ? {} : { signal: controller.signal }),
			});

			return request === sequence
				? { data, status: 'success' }
				: { status: 'ignored' };
		} catch (error) {
			if (request !== sequence || error?.name === 'AbortError') {
				return { status: 'ignored' };
			}

			return { error, status: 'error' };
		}
	};

	return Object.freeze({ cancel, load });
}
