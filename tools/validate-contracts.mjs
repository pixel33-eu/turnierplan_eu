import { existsSync } from 'node:fs';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';

const repositoryRoot = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	'..'
);
const contractRoot = path.join(repositoryRoot, 'docs', 'contracts', 'v1');

const readJson = async (filename) =>
	JSON.parse(await readFile(filename, 'utf8'));

const listFiles = async (directory, excluded = new Set()) => {
	const files = [];

	for (const entry of await readdir(directory, { withFileTypes: true })) {
		if (excluded.has(entry.name)) {
			continue;
		}

		const target = path.join(directory, entry.name);

		if (entry.isDirectory()) {
			files.push(...(await listFiles(target, excluded)));
		} else {
			files.push(target);
		}
	}

	return files;
};

const schemaFile = (name) =>
	path.join(contractRoot, 'schemas', `${name}.schema.json`);
const exampleFile = (group, name) =>
	path.join(contractRoot, 'examples', group, name);

const ajv = new Ajv2020({
	allErrors: true,
	strict: true,
	strictRequired: false,
});
addFormats(ajv);

const compileSchema = async (name) =>
	ajv.compile(await readJson(schemaFile(name)));

const validators = {
	'embed-config': await compileSchema('embed-config'),
	'embed-message': await compileSchema('embed-message'),
	'error-response': await compileSchema('error-response'),
	'metadata-response': await compileSchema('metadata-response'),
};

const positiveChecks = [
	['embed-config', 'embed-config-standings.json'],
	['embed-config', 'embed-config-matches.json'],
	['metadata-response', 'metadata-success.json'],
	['error-response', 'error-tournament-not-found.json'],
	['embed-message', 'message-ready.json'],
	['embed-message', 'message-resize.json'],
	['embed-message', 'message-status-stale.json'],
];

const negativeChecks = [
	['embed-config', 'embed-config-external-url.json'],
	['metadata-response', 'metadata-private-field.json'],
	['embed-message', 'message-wrong-type.json'],
];

for (const [schema, filename] of positiveChecks) {
	const valid = validators[schema](
		await readJson(exampleFile('valid', filename))
	);

	if (!valid) {
		throw new Error(
			`Valid contract example rejected (${filename}): ${JSON.stringify(
				validators[schema].errors
			)}`
		);
	}
}

for (const [schema, filename] of negativeChecks) {
	const valid = validators[schema](
		await readJson(exampleFile('invalid', filename))
	);

	if (valid) {
		throw new Error(`Invalid contract example accepted: ${filename}`);
	}
}

const metadata = await readJson(exampleFile('valid', 'metadata-success.json'));

if (!metadata.supported_languages.includes(metadata.response_language)) {
	throw new Error('response_language is missing from supported_languages.');
}

if (
	!metadata.supported_languages.includes(metadata.tournament.default_language)
) {
	throw new Error('default_language is missing from supported_languages.');
}

const groupIds = new Set(metadata.groups.map((group) => group.id));

for (const participant of metadata.participants) {
	for (const groupId of participant.group_ids) {
		if (!groupIds.has(groupId)) {
			throw new Error(`Participant references unknown group: ${groupId}`);
		}
	}
}

const viewIds = metadata.views.map((view) => view.id);

if (new Set(viewIds).size !== viewIds.length) {
	throw new Error('A metadata view occurs more than once.');
}

const allowedFilters = {
	standings: ['group'],
	matches: ['group', 'participant', 'match_number_range', 'date_range'],
};
const allowedOptions = {
	standings: [
		'team_logos',
		'played',
		'wins_draws_losses',
		'score_balance',
		'points',
		'group_navigation',
	],
	matches: [
		'match_number',
		'date',
		'time',
		'field',
		'group',
		'round',
		'referee',
		'live_state',
		'extra_time',
		'penalty_result',
	],
};

for (const view of metadata.views) {
	for (const filter of view.filters) {
		if (!allowedFilters[view.id].includes(filter)) {
			throw new Error(`Filter ${filter} is invalid for ${view.id}.`);
		}
	}

	for (const option of view.options) {
		if (!allowedOptions[view.id].includes(option)) {
			throw new Error(`Option ${option} is invalid for ${view.id}.`);
		}
	}
}

if (!metadata.tournament.public_url.endsWith(metadata.tournament.ref)) {
	throw new Error('public_url and tournament.ref do not match.');
}

if (
	['completed', 'cancelled'].includes(metadata.tournament.state) &&
	metadata.tournament.refresh_interval_seconds !== null
) {
	throw new Error(
		'A terminal tournament must not define a polling interval.'
	);
}

if (
	metadata.branding.policy === 'required' &&
	metadata.branding.default_visible !== true
) {
	throw new Error('Required branding must be visible by default.');
}

if (
	metadata.branding.policy === 'hidden' &&
	metadata.branding.default_visible !== false
) {
	throw new Error('Hidden branding must not be visible by default.');
}

const validExampleDirectory = path.join(contractRoot, 'examples', 'valid');
const configFiles = (await readdir(validExampleDirectory)).filter(
	(filename) =>
		filename.startsWith('embed-config-') && filename.endsWith('.json')
);

for (const filename of configFiles) {
	const config = await readJson(path.join(validExampleDirectory, filename));

	if (
		config.matchFrom !== null &&
		config.matchTo !== null &&
		config.matchFrom > config.matchTo
	) {
		throw new Error(`matchFrom is after matchTo: ${filename}`);
	}

	if (
		config.dateFrom !== null &&
		config.dateTo !== null &&
		config.dateFrom > config.dateTo
	) {
		throw new Error(`dateFrom is after dateTo: ${filename}`);
	}

	if (config.minHeight > config.maxHeight) {
		throw new Error(`minHeight exceeds maxHeight: ${filename}`);
	}
}

const markdownFiles = (
	await listFiles(
		repositoryRoot,
		new Set(['.git', 'build', 'node_modules', 'vendor'])
	)
).filter((filename) => filename.endsWith('.md'));
const missingLinks = [];
const localLinkPattern = /\]\((?!https?:\/\/|mailto:|#)([^)#]+)(?:#[^)]*)?\)/g;

for (const markdownFile of markdownFiles) {
	const markdown = await readFile(markdownFile, 'utf8');

	for (const match of markdown.matchAll(localLinkPattern)) {
		let relativeTarget = match[1].trim().replace(/^<|>$/g, '');

		try {
			relativeTarget = decodeURIComponent(relativeTarget);
		} catch {
			// Keep the original path so the missing-link report identifies it.
		}

		const target = path.resolve(path.dirname(markdownFile), relativeTarget);

		if (!existsSync(target)) {
			missingLinks.push(
				`${path.relative(
					repositoryRoot,
					markdownFile
				)}: ${relativeTarget}`
			);
		}
	}
}

if (missingLinks.length > 0) {
	throw new Error(`Missing local Markdown links: ${missingLinks.join(', ')}`);
}

const jsonFiles = (await listFiles(contractRoot)).filter((filename) =>
	filename.endsWith('.json')
);

for (const filename of jsonFiles) {
	await readJson(filename);
}

process.stdout.write(
	`Contract validation passed: ${jsonFiles.length} JSON files, ${positiveChecks.length} valid examples, ${negativeChecks.length} expected failures, semantic relations and local links.\n`
);
