import { turnierplanEmbedProtocol } from './embed-parent';
import { turnierplanEmbedConfig } from './embed-config';
import { turnierplanEmbedFrontend } from './embed-frontend';

if (typeof window !== 'undefined') {
	window.TurnierplanEU = {
		...window.TurnierplanEU,
		embedConfig: turnierplanEmbedConfig,
		embedFrontend: turnierplanEmbedFrontend,
		embedProtocol: turnierplanEmbedProtocol,
	};
}

export {
	turnierplanEmbedConfig,
	turnierplanEmbedFrontend,
	turnierplanEmbedProtocol,
};
