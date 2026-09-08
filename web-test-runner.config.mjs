/**
 * This is the configuration file for automatic TypeScript testing
 *
 * It uses "web-test-runner" to run the tests, which are written using
 * Mocha (https://mochajs.org/) &  Chai Assertion Library (https://www.chaijs.com/api/assert/)
 * Playwright (https://playwright.dev/docs/intro) runs the tests in actual browsers.
 *
 * Test groups are discovered from each app with any *.test.ts file somewhere under js/
 * (not necessarily directly in js/test/ - eg. api's tests live in per-widget
 * js/etemplate/&lt;widget&gt;/test/ subdirectories) and are named after that app.
 *
 * - every group:   `npm run jstest`
 * - one app:       `npm run jstest -- --group api`
 * - one file/glob: `npm run jstest -- api/js/etemplate/MyWidget/test/MyWidget.test.ts`
 *
 * Note the `--group`: a bare app name (`npm run jstest -- api`) is NOT a group selector, it is a
 * path, and the runner would glob the whole api/ directory.  See the comment on cliFiles below.
 *
 * Trouble getting tests to run?  Try manually compiling TypeScript (source & tests), that seems to help.
 */

import fs from 'fs';
import path from 'path';
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
		dir => fs.existsSync(`${dir}/js`) &&
			fs.statSync(`${dir}/js`).isDirectory() &&
			hasTestFile(`${dir}/js`),
	)

const testGroups = appJS.map(app => ({
	name: app,
	files: `${app}/js/**/*.test.ts`,
}));
const groupFiles = Object.fromEntries(testGroups.map(({name, files}) => [name, files]));
groupFiles.default = groupFiles.api;

// Flags that consume the following argument as their value, so we do not mistake that value
// for a file to test.  Taken from @web/test-runner's own option list (dist/config/readCliArgs.js).
const VALUE_FLAGS = new Set([
	'--files', '--root-dir', '--concurrent-browsers', '--concurrency', '--config', '--port',
	'--groups', '--group', '--browsers', '--esbuild-target'
]);

// Positional arguments only - file paths or globs for a targeted run.
//
// Group names deliberately do NOT belong here: @web/test-runner declares its own `files` option
// as the CLI's defaultOption, and parseConfig() merges cliArgs *after* this config, so a bare
// positional always overwrites whatever `files` we set.  Passing a group name that way used to
// expand to the group's glob here and then get clobbered back to the bare name, which the runner
// globbed as a *directory* - pulling in every .php/.svg/.xet/.map under it as a "test file".
// Group selection therefore goes through the runner's own --group flag; its value must not be
// collected here, or we would fall into the `files` branch below and export no groups at all.
// (Broke in @web/test-runner 1.0, see bde6bcf403.)
const cliFiles = [];
for(let i = 2; i < process.argv.length; i++)
{
	const arg = process.argv[i];
	if(!arg)
	{
		continue;
	}
	if(arg.startsWith('-'))
	{
		// "--group api" consumes the next argument; "--group=api" does not
		if(!arg.includes('=') && VALUE_FLAGS.has(arg))
		{
			i++;
		}
		continue;
	}
	if(groupFiles[arg])
	{
		throw new Error(
			`"${arg}" is a test group, not a file. Use: npm run jstest -- --group ${arg}\n` +
			`Available groups: ${Object.keys(groupFiles).join(', ')}`
		);
	}
	cliFiles.push(arg);
}

export default {
	nodeResolve: true,
	exclude: ['**/node_modules/**'],
	testRunnerHtml: testRunnerImport => `<!doctype html>
		<html lang="en-US">
			<body>
				<div id="egw_script_id" data-url="test.com"></div>
				<!-- Classic script, so it runs before the deferred modules below - same order as a
				     real page.  Legacy et2 code and bundled vendor libraries (eg. cropper, pulled
				     in by Et2Avatar) still expect the jQuery global to already be there. -->
				<script src="/vendor/bower-asset/jquery/dist/jquery.min.js"></script>
				<script type="module">
					// CI/test environments can expose POSIX locale tags that Intl rejects.
					// Make sure the document has a lang for shoelace / library localization to find
					document.documentElement.lang = 'en-US';
					Object.defineProperty(window.navigator, 'language', {value: 'en-US', configurable: true});
					Object.defineProperty(window.navigator, 'languages', {value: ['en-US'], configurable: true});
					// egw.js redirects the page to ?cd=popup at module scope when it can't find a
					// framework object, which under web-test-runner reloads the page mid-run and
					// aborts the whole test file ("Tests were interrupted because the page was
					// reloaded").  Nothing here needs a real framework, we only need the check to
					// find *something*, so give it an inert stub.  Do NOT instead fake the
					// data-include list egw.js also consults - it actually imports those files.
					window.framework = window.framework || {};
					if(!window.egw)
					{
						const egwFallback = function() { return window.egw || egwFallback; };
						Object.assign(egwFallback, {
							// egw_core.ts only builds the real egw object (the one with extend(),
							// so every egw module can register itself) out of an existing
							// window.egw marked prefsOnly - that is how Api\Framework::header()
							// seeds it in a real page.  Without it, importing anything that pulls
							// in an egw module dies on "egw.extend is not a function".
							prefsOnly: true,
							webserverUrl: "",
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
	// Either an explicit set of files, or the groups - never both: parseConfig() hands a top-level
	// `files` to any group that lacks one and then drops it, so exporting `groups` alongside an
	// explicit glob silently runs every group instead of the glob.  --group works because its
	// value is not collected as a positional above, leaving cliFiles empty.
	...(cliFiles.length ? {files: cliFiles} : {groups: testGroups}),

	plugins: [
		// must run before esbuildPlugin, so it can synthesize the legacy et2_widget_*.ts
		// shims (eg. et2_widget_selectbox, pulled in by et2_extension_nextmatch.ts) that
		// no longer exist on disk - see rollup-legacy-widget-shim.mjs for the rollup side
		legacyWidgetShimDevServerPlugin(),
		{
			// A local `npm run build` leaves gitignored bundles next to their sources - eg.
			// api/js/etemplate/etemplate2.js beside etemplate2.ts - and web-test-runner's
			// node-resolve tries .js before .ts, so an extensionless import silently pulls in the
			// last build instead of the source.  That is not just stale code: the bundle defines
			// every widget's custom element itself, so the class under test loses the
			// customElements.define() race and the test ends up exercising the build.  CI has no
			// such files, so preferring the .ts sibling is also what makes local runs match CI.
			name: "prefer-ts-source",
			resolveImport({source, context})
			{
				if(!source.startsWith(".") || path.extname(source))
				{
					return;
				}
				const importer = path.join(process.cwd(), path.dirname(context.path));
				return fs.existsSync(path.join(importer, `${source}.ts`)) ? `${source}.ts` : undefined;
			}
		},
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
