import { createTurnierplanEmbedController } from './embed-parent';

const EMBED_SELECTOR = '[data-tpeu-embed]';

const setState = (wrapper, state) => {
	for (const element of wrapper.querySelectorAll('[data-tpeu-state]')) {
		element.hidden = element.dataset.tpeuState !== state;
	}

	wrapper.dataset.tpeuStatus = state;
};

const positiveInteger = (value) => {
	if (!/^[1-9][0-9]*$/.test(value ?? '')) {
		return null;
	}

	const number = Number.parseInt(value, 10);
	return Number.isSafeInteger(number) ? number : null;
};

export function createTurnierplanFrontend(options = {}) {
	const hostWindow = options.hostWindow ?? window;
	const rootDocument = options.rootDocument ?? document;
	const createController =
		options.createController ?? createTurnierplanEmbedController;
	const embeds = new Map();
	let documentObserver = null;

	const destroyWrapper = (wrapper) => {
		const entry = embeds.get(wrapper);

		if (entry === undefined) {
			return;
		}

		entry.visibilityObserver?.disconnect();
		entry.controller.destroy();
		embeds.delete(wrapper);
		delete wrapper.dataset.tpeuInitialized;
	};

	const initializeWrapper = (wrapper) => {
		if (embeds.has(wrapper) || wrapper.dataset.tpeuInitialized === 'true') {
			return;
		}

		const iframe = wrapper.querySelector('[data-tpeu-frame]');
		const instance = wrapper.dataset.tpeuInstance;
		const expectedOrigin = wrapper.dataset.tpeuOrigin;
		const minHeight = positiveInteger(wrapper.dataset.tpeuMinHeight);
		const maxHeight = positiveInteger(wrapper.dataset.tpeuMaxHeight);

		if (
			iframe === null ||
			instance === undefined ||
			expectedOrigin === undefined ||
			minHeight === null ||
			maxHeight === null
		) {
			setState(wrapper, 'error');
			return;
		}

		let controller;

		try {
			controller = createController({
				iframe,
				instance,
				expectedOrigin,
				minHeight,
				maxHeight,
				hostWindow,
				onLoading: () => setState(wrapper, 'loading'),
				onReady: () => setState(wrapper, 'ready'),
				onStatus: ({ state }) => setState(wrapper, state),
				onReadyTimeout: () => setState(wrapper, 'timeout'),
			});
		} catch {
			setState(wrapper, 'error');
			return;
		}

		wrapper.dataset.tpeuInitialized = 'true';
		let visibilityObserver = null;

		if (typeof hostWindow.IntersectionObserver === 'function') {
			visibilityObserver = new hostWindow.IntersectionObserver(
				(entries) => {
					if (entries.some((entry) => entry.isIntersecting)) {
						controller.markRequested();
						visibilityObserver?.disconnect();
						visibilityObserver = null;
					}
				},
				{ rootMargin: '300px' }
			);
			visibilityObserver.observe(iframe);
		} else {
			controller.markRequested();
		}

		embeds.set(wrapper, { controller, visibilityObserver });
	};

	const findWrappers = (root) => {
		if (
			typeof root.matches === 'function' &&
			root.matches(EMBED_SELECTOR)
		) {
			initializeWrapper(root);
		}

		if (typeof root.querySelectorAll === 'function') {
			for (const wrapper of root.querySelectorAll(EMBED_SELECTOR)) {
				initializeWrapper(wrapper);
			}
		}
	};

	const removeWrappers = (root) => {
		if (
			typeof root.matches === 'function' &&
			root.matches(EMBED_SELECTOR)
		) {
			destroyWrapper(root);
		}

		if (typeof root.querySelectorAll === 'function') {
			for (const wrapper of root.querySelectorAll(EMBED_SELECTOR)) {
				destroyWrapper(wrapper);
			}
		}
	};

	const start = () => {
		findWrappers(rootDocument);

		if (
			documentObserver === null &&
			typeof hostWindow.MutationObserver === 'function' &&
			rootDocument.documentElement
		) {
			documentObserver = new hostWindow.MutationObserver((mutations) => {
				for (const mutation of mutations) {
					for (const node of mutation.addedNodes) {
						findWrappers(node);
					}

					for (const node of mutation.removedNodes) {
						removeWrappers(node);
					}
				}
			});
			documentObserver.observe(rootDocument.documentElement, {
				childList: true,
				subtree: true,
			});
		}
	};

	const destroy = () => {
		documentObserver?.disconnect();
		documentObserver = null;

		for (const wrapper of [...embeds.keys()]) {
			destroyWrapper(wrapper);
		}
	};

	return Object.freeze({ destroy, initialize: findWrappers, start });
}

export const turnierplanEmbedFrontend = Object.freeze({
	create: createTurnierplanFrontend,
});

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
	const frontend = createTurnierplanFrontend();

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', frontend.start, {
			once: true,
		});
	} else {
		frontend.start();
	}
}
