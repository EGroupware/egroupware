/**
 * This is the configuration file for automatic TypeScript testing
 *
 * It uses "web-test-runner" to run the tests, which are written using
 * Mocha (https://mochajs.org/) &  Chai Assertion Library (https://www.chaijs.com/api/assert/)
 * Playwright (https://playwright.dev/docs/intro) runs the tests in actual browsers.
 *
 * Test groups are discovered from each app with any *.test.ts file somewhere under js/
 * (not necessarily directly in js/test/ - eg. api's tests live in per-widget
 * js/etemplate/&lt;widget&gt;/test/ subdirectories) and are named after that app.  Use
 * `npm run jstest` to run every group, or select an app with `npm run jstest -- <app>`
 * (for example, `npm run jstest -- api`). Pass a test file or glob instead to run it
 * directly, for example:
 * `npm run jstest -- api/js/etemplate/MyWidget/test/MyWidget.test.ts`.
 *
 * Trouble getting tests to run?  Try manually compiling TypeScript (source & tests), that seems to help.
 */

import fs from 'fs';
import {playwrightLauncher} from '@web/test-runner-playwright';
import {esbuildPlugin} from '@web/dev-server-esbuild';
import {legacyWidgetShimDevServerPlugin} from './api/js/etemplate/webtest-legacy-widget-shim.mjs';

// True if a *.test.ts file exists anywhere under dir (recursing into subdirectories),
// so an app is discovered regardless of how deep its test files are nested.
function hasTestFile(dir)
{
	let entries;
	try
	{
		entries = fs.readdirSync(dir, {withFileTypes: true});
	}
	catch(e)
	{
		return false;
	}
	return entries.some(entry => entry.isDirectory() ?
		hasTestFile(`${dir}/${entry.name}`) :
		entry.name.endsWith('.test.ts'));
}

// Add any app with a *.test.ts file somewhere under js/
const appJS = fs.readdirSync('.')
	.filter(
		dir => dir !== 'kdots' && // skip kdots for now
			fs.existsSync(`${dir}/js`) &&
			fs.statSync(`${dir}/js`).isDirectory() &&
			hasTestFile(`${dir}/js`),
	)

const testGroups = appJS.map(app => ({
	name: app,
	files: `${app}/js/**/*.test.ts`,
}));
const groupFiles = Object.fromEntries(testGroups.map(({name, files}) => [name, files]));
groupFiles.default = groupFiles.api;

// A positional group name expands to its test glob; other positional arguments
// remain file paths or globs for targeted test runs.
const cliFiles = process.argv
	.slice(2)
	.filter(arg => arg && !arg.startsWith('-'))
	.map(arg => groupFiles[arg] ?? arg);

export default {
	nodeResolve: true,
	exclude: ['**/node_modules/**'],
	testRunnerHtml: testRunnerImport => `<!doctype html>
		<html lang="en-US">
			<body>
				<div id="egw_script_id" data-url="test.com"></div>
				<script type="module">
					// CI/test environments can expose POSIX locale tags that Intl rejects.
					// Make sure the document has a lang for shoelace / library localization to find
					document.documentElement.lang = 'en-US';
					Object.defineProperty(window.navigator, 'language', {value: 'en-US', configurable: true});
					Object.defineProperty(window.navigator, 'languages', {value: ['en-US'], configurable: true});
					if(!window.egw)
					{
						const egwFallback = function() { return window.egw || egwFallback; };
						Object.assign(egwFallback, {
							lang: label => label,
							debug: () => {},
							image: () => "",
							link: link => link,
							open_link: () => {},
							tooltipBind: () => {},
							tooltipUnbind: () => {},
							preference: () => null,
							set_preference: () => {},
							app_name: () => "api",
							uid: () => "test"
						});
						window.egw = egwFallback;
					}
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
		playwrightLauncher({product: 'chromium', concurrency: 1}),
		// Dependant on specific versions of shared libraries (libicuuc.so.66, latest is .67)
		//playwrightLauncher({ product: 'webkit' }),
	],
	...(cliFiles.length ? {files: cliFiles} : {groups: testGroups}),

	plugins: [
		// must run before esbuildPlugin, so it can synthesize the legacy et2_widget_*.ts
		// shims (eg. et2_widget_selectbox, pulled in by et2_extension_nextmatch.ts) that
		// no longer exist on disk - see rollup-legacy-widget-shim.mjs for the rollup side
		legacyWidgetShimDevServerPlugin(),
		{
			name: "mock-modules",
			resolveImport({source})
			{
				// map dompurify requests to package ESM build so browser ESM gets default export
				if (source === 'dompurify' || source.startsWith('dompurify/'))
				{
					return '/node_modules/dompurify/dist/purify.es.mjs';
				}
				if (source === 'tinymce')
				{
					return '/api/js/etemplate/Et2HtmlArea/test/TinyMceStub.ts';
				}
				if (source.startsWith('tinymce/'))
				{
					return '/api/js/etemplate/Et2HtmlArea/test/TinyMceSideEffectStub.ts';
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
					// diff2html's own ESM build imports the Node-only @profoundlogic/hogan package
					// (no browser field, no dist/ despite its own comment) - see Diff2HtmlStub.ts's
					// own docblock for why a stub is needed here rather than the real package.
					"diff2html": "/api/js/etemplate/test/Diff2HtmlStub.ts",
				};
				return mockModule[source];
			}
		},
		// Handles typescript
		// .css as text mirrors the rollup load hook, so unsafeCSS() imports work in tests too
		esbuildPlugin({ts: true, tsconfig: 'tsconfig.json', loaders: {'.css': 'text'}})
	],
};
