import { turnierplanEmbedProtocol } from './embed-parent';
import { turnierplanEmbedConfig } from './embed-config';

if (typeof window !== 'undefined') {
	window.TurnierplanEU = {
		...window.TurnierplanEU,
		embedConfig: turnierplanEmbedConfig,
		embedProtocol: turnierplanEmbedProtocol,
	};
}

export { turnierplanEmbedConfig, turnierplanEmbedProtocol };
