import process from 'node:process';

const parseVersion = (value) =>
	value
		.replace(/^v/, '')
		.split('.', 3)
		.map((part) => Number.parseInt(part, 10));
const isAtLeast = (actual, required) => {
	for (let index = 0; index < required.length; index++) {
		if (actual[index] > required[index]) {
			return true;
		}

		if (actual[index] < required[index]) {
			return false;
		}
	}

	return true;
};

const nodeVersion = parseVersion(process.version);
const nodeSupported =
	(nodeVersion[0] === 20 && isAtLeast(nodeVersion, [20, 19, 0])) ||
	(nodeVersion[0] >= 22 && isAtLeast(nodeVersion, [22, 13, 0]));

if (!nodeSupported) {
	throw new Error(
		`Node ${process.version} is unsupported. Use Node 20.19 or Node 22.13 and newer.`
	);
}

const npmVersionMatch =
	process.env.npm_config_user_agent?.match(/^npm\/([^ ]+)/);

if (npmVersionMatch === undefined) {
	throw new Error('Run the engine check through npm.');
}

const npmVersion = parseVersion(npmVersionMatch[1]);

if (!isAtLeast(npmVersion, [10, 8, 2])) {
	throw new Error('npm 10.8.2 or newer is required.');
}

process.stdout.write(
	`Engine check passed: Node ${process.version}, npm ${npmVersion.join('.')}.\n`
);
