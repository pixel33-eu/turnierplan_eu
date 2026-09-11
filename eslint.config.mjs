import wordpress from '@wordpress/eslint-plugin';

export default [
	{
		ignores: [ 'build/**', 'node_modules/**', 'vendor/**' ],
	},
	...wordpress.configs.recommended,
	{
		files: [ 'tools/**/*.mjs' ],
		languageOptions: {
			globals: {
				process: 'readonly',
			},
		},
	},
];
