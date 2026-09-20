const MESSAGE_TYPE = 'turnierplan.eu/embed';
const MESSAGE_VERSION = 1;
const SERVICE_ORIGIN = 'https://www.turnierplan.eu';
const UUID_V4_PATTERN =
	/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
const LANGUAGE_PATTERN = /^[a-z]{2,3}(?:-[A-Z]{2})?$/;
const VIEWS = new Set(['standings', 'matches']);
const STATUS_STATES = new Set(['loading', 'ready', 'empty', 'stale', 'error']);
const STATUS_CODES = new Set([
	'filter_ignored',
	'refresh_failed',
	'invalid_reference',
	'invalid_parameter',
	'tournament_not_found',
	'view_unavailable',
	'rate_limited',
	'temporarily_unavailable',
]);

const isRecord = (value) =>
	typeof value === 'object' && value !== null && !Array.isArray(value);

const hasExactKeys = (value, required, optional = []) => {
	if (!isRecord(value)) {
		return false;
	}

	const keys = Object.keys(value);
	const allowed = new Set([...required, ...optional]);

	return (
		required.every((key) => keys.includes(key)) &&
		keys.every((key) => allowed.has(key))
	);
};

const isHeight = (value) =>
	Number.isInteger(value) && value >= 160 && value <= 8000;

const isWarning = (warning) =>
	hasExactKeys(warning, ['code', 'parameter']) &&
	warning.code === 'filter_ignored' &&
	['group', 'participant'].includes(warning.parameter);

const isReadyPayload = (payload) =>
	hasExactKeys(payload, [
		'view',
		'resolved_language',
		'height',
		'warnings',
	]) &&
	VIEWS.has(payload.view) &&
	typeof payload.resolved_language === 'string' &&
	LANGUAGE_PATTERN.test(payload.resolved_language) &&
	isHeight(payload.height) &&
	Array.isArray(payload.warnings) &&
	payload.warnings.length <= 2 &&
	payload.warnings.every(isWarning);

const isResizePayload = (payload) =>
	hasExactKeys(payload, ['height']) && isHeight(payload.height);

const isStatusPayload = (payload) => {
	if (!hasExactKeys(payload, ['state'], ['code'])) {
		return false;
	}

	if (!STATUS_STATES.has(payload.state)) {
		return false;
	}

	if (payload.code !== undefined && !STATUS_CODES.has(payload.code)) {
		return false;
	}

	return (
		!['stale', 'error'].includes(payload.state) ||
		payload.code !== undefined
	);
};

export function isTurnierplanEmbedMessage(value) {
	if (
		!hasExactKeys(value, [
			'type',
			'version',
			'instance',
			'event',
			'payload',
		]) ||
		value.type !== MESSAGE_TYPE ||
		value.version !== MESSAGE_VERSION ||
		typeof value.instance !== 'string' ||
		!UUID_V4_PATTERN.test(value.instance)
	) {
		return false;
	}

	switch (value.event) {
		case 'ready':
			return isReadyPayload(value.payload);
		case 'resize':
			return isResizePayload(value.payload);
		case 'status':
			return isStatusPayload(value.payload);
		default:
			return false;
	}
}

const normalizeOrigin = (origin) => {
	let parsed;

	try {
		parsed = new URL(origin);
	} catch {
		throw new TypeError('The expected Turnierplan.eu origin is invalid.');
	}

	if (parsed.protocol !== 'https:' || parsed.origin !== origin) {
		throw new TypeError('The expected Turnierplan.eu origin is invalid.');
	}

	return parsed.origin;
};

const safeCallback = (callback, value) => {
	try {
		callback(value);
	} catch {
		// A consumer callback must not break the shared message listener.
	}
};

export function createTurnierplanEmbedController(options) {
	if (!isRecord(options)) {
		throw new TypeError('Embed controller options are required.');
	}

	const {
		iframe,
		instance,
		expectedOrigin = SERVICE_ORIGIN,
		minHeight = 240,
		maxHeight = 4000,
		readyTimeoutMilliseconds = 10000,
		hostWindow = window,
		onLoading = () => {},
		onReady = () => {},
		onStatus = () => {},
		onReadyTimeout = () => {},
	} = options;

	if (
		!iframe ||
		typeof iframe.addEventListener !== 'function' ||
		typeof iframe.removeEventListener !== 'function' ||
		!iframe.style
	) {
		throw new TypeError('A valid iframe is required.');
	}

	if (typeof instance !== 'string' || !UUID_V4_PATTERN.test(instance)) {
		throw new TypeError('A lowercase UUID v4 instance is required.');
	}

	if (
		!Number.isInteger(minHeight) ||
		!Number.isInteger(maxHeight) ||
		minHeight < 160 ||
		maxHeight > 8000 ||
		minHeight > maxHeight
	) {
		throw new RangeError('The iframe height boundaries are invalid.');
	}

	if (
		!Number.isInteger(readyTimeoutMilliseconds) ||
		readyTimeoutMilliseconds < 1000 ||
		readyTimeoutMilliseconds > 60000
	) {
		throw new RangeError('The ready timeout is invalid.');
	}

	const origin = normalizeOrigin(expectedOrigin);
	let readyTimer = null;
	let readyReceived = false;
	let destroyed = false;
	let removalObserver = null;

	const clearReadyTimer = () => {
		if (readyTimer !== null) {
			hostWindow.clearTimeout(readyTimer);
			readyTimer = null;
		}
	};
	const applyHeight = (height) => {
		const boundedHeight = Math.max(minHeight, Math.min(maxHeight, height));
		const nextHeight = `${boundedHeight}px`;

		if (iframe.style.height !== nextHeight) {
			iframe.style.height = nextHeight;
		}
	};
	const startReadyTimer = () => {
		if (destroyed || readyReceived || readyTimer !== null) {
			return;
		}

		safeCallback(onLoading, { state: 'loading' });
		readyTimer = hostWindow.setTimeout(() => {
			readyTimer = null;

			if (!readyReceived && !destroyed) {
				safeCallback(onReadyTimeout, { state: 'loading' });
			}
		}, readyTimeoutMilliseconds);
	};
	const onMessage = (event) => {
		if (
			destroyed ||
			event.origin !== origin ||
			event.source !== iframe.contentWindow ||
			!isTurnierplanEmbedMessage(event.data) ||
			event.data.instance !== instance
		) {
			return;
		}

		const { payload } = event.data;

		if (event.data.event === 'ready') {
			readyReceived = true;
			clearReadyTimer();
			applyHeight(payload.height);
			safeCallback(onReady, payload);
			return;
		}

		if (event.data.event === 'resize') {
			applyHeight(payload.height);
			return;
		}

		safeCallback(onStatus, payload);
	};
	const destroy = () => {
		if (destroyed) {
			return;
		}

		destroyed = true;
		clearReadyTimer();
		hostWindow.removeEventListener('message', onMessage);
		iframe.removeEventListener('load', startReadyTimer);
		removalObserver?.disconnect();
		removalObserver = null;
	};

	hostWindow.addEventListener('message', onMessage);
	iframe.addEventListener('load', startReadyTimer);

	if (
		typeof hostWindow.MutationObserver === 'function' &&
		iframe.ownerDocument?.documentElement
	) {
		removalObserver = new hostWindow.MutationObserver(() => {
			if (!iframe.isConnected) {
				destroy();
			}
		});
		removalObserver.observe(iframe.ownerDocument.documentElement, {
			childList: true,
			subtree: true,
		});
	}

	return Object.freeze({
		destroy,
		markRequested: startReadyTimer,
	});
}

export const turnierplanEmbedProtocol = Object.freeze({
	createController: createTurnierplanEmbedController,
	isMessage: isTurnierplanEmbedMessage,
	messageType: MESSAGE_TYPE,
	version: MESSAGE_VERSION,
});
