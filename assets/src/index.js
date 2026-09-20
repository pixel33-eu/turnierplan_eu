import { turnierplanEmbedProtocol } from './embed-parent';

if (typeof window !== 'undefined') {
	window.TurnierplanEU = {
		...window.TurnierplanEU,
		embedProtocol: turnierplanEmbedProtocol,
	};
}

export { turnierplanEmbedProtocol };
