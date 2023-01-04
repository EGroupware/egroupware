/**
 * This is the configuration file for automatic TypeScript testing
 *
 * It uses "web-test-runner" to run the tests, which are written using
 * Mocha (https://mochajs.org/) &  Chai Assertion Library (https://www.chaijs.com/api/assert/)
 * Playwright (https://playwright.dev/docs/intro) runs the tests in actual browsers.
 *
 * Trouble getting tests to run?  Try manually compiling TypeScript (source & tests), that seems to help.
 */

import fs from 'fs';
import {playwrightLauncher} from '@web/test-runner-playwright';
import {esbuildPlugin} from '@web/dev-server-esbuild';

// Add any test files in app/js/test/
const appJS = fs.readdirSync('.')
	.filter(
		dir => fs.existsSync(`${dir}/js`) && fs.existsSync(`${dir}/js/test`) && fs.statSync(`${dir}/js/test`).isDirectory(),
	)


export default {
	nodeResolve: true,
	coverageConfig: {
		report: true,
		reportDir: 'coverage',
		threshold: {
			statements: 90,
			branches: 65,
			functions: 80,
			lines: 90,
		},
	},
	testFramework: {
		config: {
			timeout: '3000',
		},
	},
	browsers: [
		playwrightLauncher({product: 'firefox', concurrency: 1}),
		playwrightLauncher({product: 'chromium'}),
		// Dependant on specific versions of shared libraries (libicuuc.so.66, latest is .67)
		//playwrightLauncher({ product: 'webkit' }),
	],
	groups:
		[
			{
				name: 'api',
				files: 'api/js/etemplate/**/test/*.test.ts'
			}
		].concat(
			appJS.map(app =>
				{
					return {
						name: app,
						files: `${app}/js/**/*.test.ts`
					}
				}
			)
		),

	plugins: [
		// Handles typescript
		esbuildPlugin({ts: true})
	],
};
