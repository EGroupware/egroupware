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
 * - one browser:   `JSTEST_BROWSERS=chromium npm run jstest`
 * - one shard:     `JSTEST_SHARD=1/2 npm run jstest`   (every 2nd test file; CI uses this)
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

// Every *.test.ts under dir, recursing into subdirectories, so an app is discovered - and a
// shard filled - regardless of how deep its test files are nested.
function collectTestFiles(dir)
{
	let entries;
	try
	{
		entries = fs.readdirSync(dir, {withFileTypes: true});
	}
	catch(e)
	{
		return [];
	}
	return entries.flatMap(entry => entry.isDirectory() ?
		collectTestFiles(`${dir}/${entry.name}`) :
		entry.name.endsWith('.test.ts') ? [`${dir}/${entry.name}`] : []);
}

// Add any app with a *.test.ts file somewhere under js/
const appJS = fs.readdirSync('.')
	.filter(
		dir => fs.existsSync(`${dir}/js`) &&
			fs.statSync(`${dir}/js`).isDirectory() &&
			collectTestFiles(`${dir}/js`).length > 0,
	)

const testGroups = appJS.map(app => ({
	name: app,
	files: `${app}/js/**/*.test.ts`,
}));
const groupFiles = Object.fromEntries(testGroups.map(({name, files}) => [name, files]));
groupFiles.default = groupFiles.api;

// Which browsers to run, as a comma separated list.  CI gives each one its own job so they run on
// separate runners instead of sharing one (.github/workflows/testing.yml), and a quick local pass
// in a single browser is often enough:  JSTEST_BROWSERS=chromium npm run jstest
//
// @web/test-runner's own --browsers flag is no use here: it only works together with --playwright,
// which throws as soon as the config defines browsers itself - which this one has to, for the
// per-launcher options further down.
const PLAYWRIGHT_PRODUCTS = ['chromium', 'firefox', 'webkit'];
const browserNames = (process.env.JSTEST_BROWSERS || 'firefox,chromium')
	.split(',')
	.map(name => name.trim().toLowerCase())
	.filter(Boolean);
for(const name of browserNames)
{
	if(!PLAYWRIGHT_PRODUCTS.includes(name))
	{
		throw new Error(`JSTEST_BROWSERS: "${name}" is not a Playwright product.\n` +
			`Available: ${PLAYWRIGHT_PRODUCTS.join(', ')}`);
	}
}

// JSTEST_SHARD=<n>/<total> runs only this shard's share of the test files, so CI can spread the
// suite over several runners.  The list comes from the same walk the groups are built from, so
// there is no second copy to keep in sync with this config.  The split is by FILE because a file
// is one browser page, and the page - not the test in it - is what the suite's time goes on: only
// ~105s of a ~334s run is test code (see the launcher comment below).
//
// Ignored for a run with explicit files/globs: those are already a narrower selection, and a glob
// cannot be split this way without expanding it first.
function shardFiles(spec)
{
	const [index, total] = String(spec).split('/').map(part => Number(part.trim()));
	if(!Number.isInteger(index) || !Number.isInteger(total) || total < 1 || index < 1 || index > total)
	{
		throw new Error(`JSTEST_SHARD must be "<n>/<total>" with 1 <= n <= total - got "${spec}"`);
	}
	// Sorted, so every shard of the same run numbers the files identically and each file is picked
	// up by exactly one of them.
	const all = appJS.flatMap(app => collectTestFiles(`${app}/js`)).sort();
	const files = all.filter((file, i) => i % total === index - 1);
	if(!files.length)
	{
		throw new Error(`JSTEST_SHARD=${spec} selects none of the ${all.length} test files`);
	}
	return files;
}

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
		// A shard is a flat file list and is exported as `files`, so there are no groups left for
		// --group to find - it would fail with "Could not find any group named x" instead of
		// saying what actually went wrong.
		if((arg === '--group' || arg.startsWith('--group=')) && process.env.JSTEST_SHARD)
		{
			throw new Error(
				`JSTEST_SHARD=${process.env.JSTEST_SHARD} cannot be combined with --group: a shard ` +
				`is a list of files, --group selects whole apps.  Use one or the other.`
			);
		}
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
	// concurrency: 1 is a deliberate, measured choice - but not for the reason given here before.
	//
	// Running several pages at once does NOT background them.  Playwright headless pages are
	// separate offscreen targets, not tabs competing for one foreground slot: measured with 8
	// running at once, in both products, every page reports visibilityState "visible" with
	// document.hasFocus() true, requestAnimationFrame at a full 60fps, ResizeObserver delivering
	// and no setTimeout clamping.  A backgrounded tab WOULD be a real problem here - tests that
	// assert an ABSENCE of work (Et2Datagrid.idleSettle.test.ts: "an idle grid performs 0 update
	// cycles") would pass while the defect is fully present, and checking that rows rendered does
	// not catch it (with both APIs stubbed out, 24 rows still render) - it just is not what this
	// setting is protecting against.
	//
	// What raising it actually buys is CPU contention, for very little time.  Full runs of this
	// suite (244 files, 3578 tests, both products), 2026-09-25:
	//
	//   cores  concurrency   wall    result
	//    12        1         334s    green
	//    12        2         326s    green
	//    12        4         286s    10 failed
	//     4        1         338s    green         <- what a GitHub runner has
	//     4        2         319s    13 failed
	//
	// 12 cores and 4 cores take the same wall clock at concurrency 1, with 60-80% of the CPU idle
	// throughout: the suite is not CPU-bound.  Only ~105s of that ~334s is test code - the rest is
	// per-session page startup and module import (~1.2s per file per browser), which overlaps
	// poorly.  So there is no 2x sitting here, while the tests contention does break are the
	// real-timer ones: the addressbook nextmatch files blow their 15s timeout,
	// Et2LazyLoadController misses its IntersectionObserver callback, and idleSettle's own
	// "locked" control arm stops being quiet.  Push it further and the rAF-polling helpers in
	// Et2Datagrid.test.ts hang to the mocha timeout, which looks exactly like a product regression
	// (see the note there - it cost two debugging sessions).
	//
	// To make the suite faster, give it more machines rather than more pages per machine:
	// JSTEST_BROWSERS and JSTEST_SHARD above split it across runners that do not share a CPU, and
	// each of those pieces is still a concurrency-1 run.  Measured on 4 cores: firefox alone 285s
	// and chromium alone 157s (vs 338s for the two together), and half the files in firefox 145s /
	// 152s - near enough linear.
	//
	// Also measured and rejected: http2: true + protocol: 'https:', to multiplex the module
	// requests - 478s, 41% slower.  TLS on localhost costs more than multiplexing saves.
	//
	// webkit is left out: it depends on specific versions of shared libraries (libicuuc.so.66,
	// latest is .67).  JSTEST_BROWSERS=webkit will still try, if that ever gets sorted out.
	browsers: browserNames.map(product => playwrightLauncher({product, concurrency: 1})),
	// Either an explicit set of files, or the groups - never both: parseConfig() hands a top-level
	// `files` to any group that lacks one and then drops it, so exporting `groups` alongside an
	// explicit glob silently runs every group instead of the glob.  --group works because its
	// value is not collected as a positional above, leaving cliFiles empty.  A shard is a plain
	// list of files, so it goes in the same slot.
	...(cliFiles.length ? {files: cliFiles} :
		process.env.JSTEST_SHARD ? {files: shardFiles(process.env.JSTEST_SHARD)} :
			{groups: testGroups}),

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
				// openpgp's own types/exports map ("./lightweight") isn't resolved by this
				// dev-server's resolver either (same class of gap as dompurify above, and as
				// tsconfig.json's own classic "node" moduleResolution - see mail/js/openpgp.d.ts) -
				// point straight at the resolved file, matching Rollup's own production resolution
				if (source === 'openpgp/lightweight')
				{
					return '/node_modules/openpgp/dist/lightweight/openpgp.min.mjs';
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
