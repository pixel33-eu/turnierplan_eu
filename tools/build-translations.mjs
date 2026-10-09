import { createHash } from 'node:crypto';
import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const languageDirectory = path.join(root, 'languages');
const poPath = path.join(languageDirectory, 'turnierplan-eu-de_DE.po');
const version = (await readFile(path.join(root, 'VERSION'), 'utf8')).trim();
const gettextPattern = /(?:__|esc_html__|translate)\(\s*'((?:\\.|[^'])*)'/gu;

const walk = async (directory, extensions) => {
	const files = [];

	for (const entry of await readdir(directory, { withFileTypes: true })) {
		const fullPath = path.join(directory, entry.name);

		if (entry.isDirectory()) {
			files.push(...(await walk(fullPath, extensions)));
		} else if (extensions.has(path.extname(entry.name))) {
			files.push(fullPath);
		}
	}

	return files;
};

const decodeSourceString = (value) =>
	value
		.replaceAll('\\\\', '\u0000')
		.replaceAll("\\'", "'")
		.replaceAll('\\n', '\n')
		.replaceAll('\u0000', '\\');

const extractStrings = async (file) => {
	const source = await readFile(file, 'utf8');
	return [...source.matchAll(gettextPattern)].map((match) =>
		decodeSourceString(match[1])
	);
};

const parsePo = (source) => {
	const translations = new Map();
	let id = null;
	let value = null;
	let field = null;

	const finish = () => {
		if (id !== null && value !== null) {
			translations.set(id, value);
		}
		id = null;
		value = null;
		field = null;
	};

	for (const line of source.split(/\r?\n/u)) {
		if (line.startsWith('msgid ')) {
			finish();
			id = JSON.parse(line.slice(6));
			field = 'id';
		} else if (line.startsWith('msgstr ')) {
			value = JSON.parse(line.slice(7));
			field = 'value';
		} else if (line.startsWith('"')) {
			const part = JSON.parse(line);
			if (field === 'id') {
				id += part;
			} else if (field === 'value') {
				value += part;
			}
		} else if (line === '') {
			finish();
		}
	}

	finish();
	return translations;
};

const poQuote = (value) => JSON.stringify(value);

const createPot = (strings) => {
	const lines = [
		'msgid ""',
		'msgstr ""',
		`"Project-Id-Version: Turnierplan.eu ${version}\\n"`,
		'"Report-Msgid-Bugs-To: https://github.com/pixel33-eu/turnierplan_eu/issues\\n"',
		'"POT-Creation-Date: 2026-10-08 00:00+0200\\n"',
		'"MIME-Version: 1.0\\n"',
		'"Content-Type: text/plain; charset=UTF-8\\n"',
		'"Content-Transfer-Encoding: 8bit\\n"',
		'"X-Generator: Turnierplan.eu translation build\\n"',
	];

	for (const string of [...strings].sort((left, right) =>
		left.localeCompare(right, 'en')
	)) {
		lines.push('', `msgid ${poQuote(string)}`, 'msgstr ""');
	}

	return `${lines.join('\n')}\n`;
};

const compileMo = (translations) => {
	const entries = [...translations.entries()].sort(([left], [right]) =>
		Buffer.from(left).compare(Buffer.from(right))
	);
	const count = entries.length;
	const originalsOffset = 28;
	const translationsOffset = originalsOffset + count * 8;
	const stringsOffset = translationsOffset + count * 8;
	const originalBuffers = entries.map(([id]) => Buffer.from(id, 'utf8'));
	const translationBuffers = entries.map(([, value]) =>
		Buffer.from(value, 'utf8')
	);
	const originalLength = originalBuffers.reduce(
		(sum, item) => sum + item.length + 1,
		0
	);
	const totalLength =
		stringsOffset +
		originalLength +
		translationBuffers.reduce((sum, item) => sum + item.length + 1, 0);
	const output = Buffer.alloc(totalLength);

	output.writeUInt32LE(0x950412de, 0);
	output.writeUInt32LE(0, 4);
	output.writeUInt32LE(count, 8);
	output.writeUInt32LE(originalsOffset, 12);
	output.writeUInt32LE(translationsOffset, 16);
	output.writeUInt32LE(0, 20);
	output.writeUInt32LE(0, 24);

	let originalCursor = stringsOffset;
	let translationCursor = stringsOffset + originalLength;

	for (let index = 0; index < count; index += 1) {
		const original = originalBuffers[index];
		const translation = translationBuffers[index];
		output.writeUInt32LE(original.length, originalsOffset + index * 8);
		output.writeUInt32LE(originalCursor, originalsOffset + index * 8 + 4);
		original.copy(output, originalCursor);
		originalCursor += original.length + 1;
		output.writeUInt32LE(
			translation.length,
			translationsOffset + index * 8
		);
		output.writeUInt32LE(
			translationCursor,
			translationsOffset + index * 8 + 4
		);
		translation.copy(output, translationCursor);
		translationCursor += translation.length + 1;
	}

	return output;
};

const createJed = (source, strings, translations) => {
	const messages = {
		'': {
			domain: 'messages',
			lang: 'de_DE',
			'plural-forms': 'nplurals=2; plural=(n != 1);',
		},
	};

	for (const string of [...strings].sort((left, right) =>
		left.localeCompare(right, 'en')
	)) {
		messages[string] = [translations.get(string)];
	}

	return `${JSON.stringify(
		{
			'translation-revision-date': '2026-10-08 00:00+0200',
			generator: 'Turnierplan.eu translation build',
			source,
			domain: 'messages',
			locale_data: { messages },
		},
		null,
		2
	)}\n`;
};

const phpFiles = await walk(path.join(root, 'src'), new Set(['.php']));
phpFiles.push(path.join(root, 'turnierplan-eu.php'));
const jsFiles = await walk(
	path.join(root, 'assets', 'src'),
	new Set(['.js', '.jsx'])
);
const strings = new Set();

for (const file of [...phpFiles, ...jsFiles]) {
	for (const string of await extractStrings(file)) {
		strings.add(string);
	}
}

const block = JSON.parse(
	await readFile(path.join(root, 'blocks', 'embed', 'block.json'), 'utf8')
);
for (const string of [
	block.title,
	block.description,
	...block.keywords,
	...block.variations.flatMap((variation) => [
		variation.title,
		variation.description,
	]),
]) {
	strings.add(string);
}

const pluginSource = await readFile(
	path.join(root, 'turnierplan-eu.php'),
	'utf8'
);
for (const field of ['Plugin Name', 'Description']) {
	const value = pluginSource.match(
		new RegExp(`^ \\* ${field}:\\s+(.+)$`, 'mu')
	)?.[1];
	if (value !== undefined) {
		strings.add(value.trim());
	}
}

const translations = parsePo(await readFile(poPath, 'utf8'));
const missing = [...strings].filter(
	(string) => !translations.has(string) || translations.get(string) === ''
);
const stale = [...translations.keys()].filter(
	(string) => string !== '' && !strings.has(string)
);
const nonEnglish = [...strings].filter((string) => /[äöüÄÖÜß]/u.test(string));

if (missing.length > 0 || stale.length > 0 || nonEnglish.length > 0) {
	throw new Error(
		[
			missing.length > 0
				? `Missing German translations: ${missing.join(' | ')}`
				: '',
			stale.length > 0
				? `Stale German translations: ${stale.join(' | ')}`
				: '',
			nonEnglish.length > 0
				? `Non-English source strings: ${nonEnglish.join(' | ')}`
				: '',
		]
			.filter(Boolean)
			.join('\n')
	);
}

await mkdir(languageDirectory, { recursive: true });
await writeFile(
	path.join(languageDirectory, 'turnierplan-eu.pot'),
	createPot(strings)
);
await writeFile(
	path.join(languageDirectory, 'turnierplan-eu-de_DE.mo'),
	compileMo(translations)
);

for (const [handle, relativeSource] of [
	['tpeu-block-editor', 'assets/src/block-editor.jsx'],
	['tpeu-preset-editor', 'assets/src/preset-editor.jsx'],
]) {
	const absoluteSource = path.join(root, ...relativeSource.split('/'));
	const scriptStrings = new Set(await extractStrings(absoluteSource));
	await writeFile(
		path.join(languageDirectory, `turnierplan-eu-de_DE-${handle}.json`),
		createJed(relativeSource, scriptStrings, translations)
	);

	const hash = createHash('md5').update(relativeSource).digest('hex');
	await writeFile(
		path.join(languageDirectory, `turnierplan-eu-de_DE-${hash}.json`),
		createJed(relativeSource, scriptStrings, translations)
	);
}

process.stdout.write(
	`Translation build passed: ${strings.size} English originals, complete de_DE PHP and JavaScript catalogs.\n`
);
