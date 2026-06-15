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
		dir => dir !== 'kdots' && // skip kdots for now
			fs.existsSync(`${dir}/js`) &&
			fs.existsSync(`${dir}/js/test`) &&
			fs.statSync(`${dir}/js/test`).isDirectory(),
	)

const cliFileArgs = process.argv
	.slice(2)
	.filter(arg => arg && !arg.startsWith('-'));

const defaultGroups =
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
		})
	);

export default {
	nodeResolve: true,
	exclude: ['**/node_modules/**'],
	testRunnerHtml: testRunnerImport => `
		<html lang="en-US">
			<body>
				<div id="egw_script_id" data-url="test.com"></div>
				<script type="module">
					// CI/test environments can expose POSIX locale tags that Intl rejects.
					// Make sure the document has a lang for shoelace / library localization to find
					document.documentElement.lang = 'en-US';
					Object.defineProperty(window.navigator, 'language', {value: 'en-US', configurable: true});
					Object.defineProperty(window.navigator, 'languages', {value: ['en-US'], configurable: true});
				</script>
				<script type="module">
					import '${testRunnerImport}';
				</script>
			</body>
		</html>
	`,
	filterBrowserLogs(log)
	{
		// Silence some warnings we don't care about
		const text = log && typeof log.args[0] === 'string' ? log.args[0] : '';
		if (text.includes('Lit is in dev mode.') || text.includes('Multiple versions of Lit loaded.'))
		{
			return false;
		}
		return true;
	},
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
	...(cliFileArgs.length ? {files: cliFileArgs} : {groups: defaultGroups}),

	plugins: [
		{
			name: "mock-modules",
			resolveImport({source})
			{
				// map dompurify requests to package ESM build so browser ESM gets default export
				if (source === 'dompurify' || source.startsWith('dompurify/'))
				{
					return '/node_modules/dompurify/dist/purify.es.mjs';
				}
				if (source.includes('Resumable/resumable'))
				{
					return '/api/js/etemplate/Et2File/test/ResumableStub.ts';
				}
				if (source.includes('shortcut-buttons-flatpickr'))
				{
					return './test/FlatpickrShortcutPluginStub.js';
				}
				else if (source.includes('scrollPlugin'))
				{
					return './test/FlatpickrScrollPluginStub.js';
				}

				const mockModule = {
					"diff2html/lib/types": "/api/js/etemplate/test/Diff2HtmlTypesStub.ts",
				};
				return mockModule[source];
			}
		},
		// Handles typescript
		esbuildPlugin({ts: true})
	],
};