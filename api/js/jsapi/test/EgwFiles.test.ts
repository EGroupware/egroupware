/**
 * Tests for egw_files.js ("files" module) - MODULE_WND_LOCAL.
 *
 * No prior test coverage. See EgwFilesHarness for how it's loaded and why
 * includeJS()'s actual dynamic import() isn't exercised.
 *
 * Note: MODULE_WND_LOCAL factories run eagerly for any window that already
 * has a module slot (see egw_core.ts's mergeWndLocalModule) - so the ROOT
 * window's 'files' instance scans its document the moment egw_files.js
 * loads (during harness setup), before any test-specific fixture can be
 * added. Tests that need controlled initial <script>/<link> tags use a
 * fresh child window (env.createWindow()) and populate its document BEFORE
 * the first egw(app, childWindow) call, which is what triggers the scan
 * for that window.
 */
import {assert} from "@open-wc/testing";
import {createEgwFilesEnv, EgwFilesEnv} from "./EgwFilesHarness";

describe('egw_files.js (files)', () =>
{
	let env : EgwFilesEnv;

	beforeEach(async() =>
	{
		env = await createEgwFilesEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw('someapp', env.window);
		assert.isFunction(instance.includeJS);
		assert.isFunction(instance.included);
		assert.isFunction(instance.includeCSS);
	});

	describe('initial scan of already-present tags', () =>
	{
		it('the root window instance already knows about a <script> tag present before it loaded', () =>
		{
			// EgwFilesHarness loads jQuery via a real <script src="/vendor/.../jquery.min.js">
			// before egw_files.js itself - the constructor scan should have picked it up.
			// The tag resolves to the real dev-server origin, not the configured
			// (fake) webserverUrl, so strip_egw_url() leaves it as a full absolute
			// URL rather than stripping it down to a relative path. An
			// about:blank iframe's own location.origin isn't reliable for this -
			// it inherits the top-level test page's URL as the base for
			// resolving the relative script src, so use THIS page's origin.
			const instance = env.egw('someapp', env.window);
			const absoluteUrl = window.location.origin + '/vendor/bower-asset/jquery/dist/jquery.min.js';
			assert.isTrue(instance.included(absoluteUrl));
		});

		it('strips a timestamp query parameter from a scanned <script src>', () =>
		{
			const win = env.createWindow();
			const script = win.document.createElement('script');
			script.src = 'https://example.test/some/file.js?123456';
			win.document.head.appendChild(script);

			const instance = env.egw('someapp', win);
			assert.isTrue(instance.included('some/file.js'));
		});

		it('strips the egw webserverUrl prefix from a scanned absolute <script src>', () =>
		{
			const win = env.createWindow();
			const script = win.document.createElement('script');
			script.src = 'https://example.test/some/file.js';
			win.document.head.appendChild(script);

			const instance = env.egw('someapp', win);
			assert.isTrue(instance.included('some/file.js'));
		});

		it('expands a bundle URL into its constituent files, replacing .min.js with .js', () =>
		{
			const win = env.createWindow();
			const script = win.document.createElement('script');
			script.src = 'https://example.test/phpgwapi/inc/min/?b=abc&f=some/a.min.js,some/b.js';
			win.document.head.appendChild(script);

			const instance = env.egw('someapp', win);
			assert.isTrue(instance.included('some/a.js'));
			assert.isTrue(instance.included('some/b.js'));
		});

		it('scans already-present CSS <link> tags too', () =>
		{
			const win = env.createWindow();
			const link = win.document.createElement('link');
			link.type = 'text/css';
			link.href = 'https://example.test/some/style.css';
			win.document.head.appendChild(link);

			const instance = env.egw('someapp', win);
			assert.isTrue(instance.included('some/style.css'));
		});

		it('a window with nothing pre-loaded starts with an empty known-files list', () =>
		{
			const win = env.createWindow();
			const instance = env.egw('someapp', win);
			assert.isFalse(instance.included('some/never-loaded.js'));
		});
	});

	describe('included()', () =>
	{
		it('returns false for a file that was never included, and does not mark it', () =>
		{
			const instance = env.egw('someapp', env.window);
			assert.isFalse(instance.included('never/seen.js'));
			assert.isFalse(instance.included('never/seen.js'), 'without _add_if_not, a second call must still report false');
		});

		it('with _add_if_not=true: false on first call, true on every call after', () =>
		{
			const instance = env.egw('someapp', env.window);
			assert.isFalse(instance.included('newly/added.js', true));
			assert.isTrue(instance.included('newly/added.js'));
			assert.isTrue(instance.included('newly/added.js'));
		});

		it('does not differentiate between file.js and file.min.js', () =>
		{
			const win = env.createWindow();
			const script = win.document.createElement('script');
			script.src = 'https://example.test/some/file.min.js';
			win.document.head.appendChild(script);

			const instance = env.egw('someapp', win);
			assert.isTrue(instance.included('some/file.js'));
		});
	});

	describe('includeCSS()', () =>
	{
		it('creates a <link> element for a not-yet-included CSS file', () =>
		{
			const instance = env.egw('someapp', env.window);
			instance.includeCSS('some/new-style.css');

			const link = env.window.document.querySelector('link[href="https://example.test/some/new-style.css"]');
			assert.isNotNull(link);
			assert.equal((link as HTMLLinkElement).rel, 'stylesheet');
		});

		it('marks the file as included, so a second call does not add a duplicate <link>', () =>
		{
			const instance = env.egw('someapp', env.window);
			instance.includeCSS('some/new-style.css');
			instance.includeCSS('some/new-style.css');

			const links = env.window.document.querySelectorAll('link[href="https://example.test/some/new-style.css"]');
			assert.equal(links.length, 1);
		});

		it('does not add a <link> for a CSS file that was already present before the module loaded', () =>
		{
			const win = env.createWindow();
			const preexisting = win.document.createElement('link');
			preexisting.type = 'text/css';
			preexisting.href = 'https://example.test/already/there.css';
			win.document.head.appendChild(preexisting);

			const instance = env.egw('someapp', win);
			instance.includeCSS('already/there.css');

			const links = win.document.querySelectorAll('link[href="https://example.test/already/there.css"]');
			assert.equal(links.length, 1, 'only the original pre-existing <link>, no duplicate added');
		});

		it('accepts an array of CSS files', () =>
		{
			const instance = env.egw('someapp', env.window);
			instance.includeCSS(['some/a.css', 'some/b.css']);

			assert.isNotNull(env.window.document.querySelector('link[href="https://example.test/some/a.css"]'));
			assert.isNotNull(env.window.document.querySelector('link[href="https://example.test/some/b.css"]'));
		});
	});
});
