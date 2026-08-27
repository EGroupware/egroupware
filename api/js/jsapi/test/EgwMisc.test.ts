/**
 * Tests for egw_config.js, egw_lang.js and egw_images.js - all MODULE_GLOBAL,
 * self-contained (each only imports egw_core.js), and mostly plain
 * getter/setter state with one or two genuinely interesting pieces of
 * logic each (lang()'s placeholder replacement, image()'s lookup
 * precedence). See EgwMiscHarness for the minimal shared setup.
 *
 * preferences/user/links moved to their own, more thorough test files
 * (EgwPreferences.test.ts, EgwUser.test.ts, EgwLinks.test.ts) since they
 * interact with each other and need a real (controllable) network layer.
 *
 * NOT covered (documented residual risk): config's install_mailto_handler()
 * (real dialog/localStorage side effects, gated on a URL query param) and
 * lang's langRequire()/langRequireApp() (dynamic import() side effects).
 */
import {assert} from "@open-wc/testing";
import {createEgwMiscEnv, EgwMiscEnv} from "./EgwMiscHarness";

describe('egw_config.js / egw_lang.js / egw_images.js', () =>
{
	let env : EgwMiscEnv;

	beforeEach(async() =>
	{
		env = await createEgwMiscEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods from every module', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.config);
		assert.isFunction(instance.lang);
		assert.isFunction(instance.image);
	});

	describe('config', () =>
	{
		it('round-trips config values, keyed by app (default "phpgwapi")', () =>
		{
			env.egw().set_configs({phpgwapi: {max_lang_time: 123}});
			assert.equal(env.egw().config('max_lang_time'), 123);
		});

		it('returns null for an unknown app, and undefined for an unknown key in a known app', () =>
		{
			assert.isNull(env.egw().config('whatever', 'nosuchapp'));
			env.egw().set_configs({someapp: {}});
			assert.isUndefined(env.egw().config('missing_key', 'someapp'));
		});
	});

	describe('lang', () =>
	{
		it('falls back to the original message when no translation is found', () =>
		{
			assert.equal(env.egw().lang('Hello'), 'Hello');
		});

		it('matches a registered translation case-insensitively', () =>
		{
			env.egw().set_lang_arr('common', {'hello': 'Hallo'});
			assert.equal(env.egw().lang('Hello'), 'Hallo');
		});

		it('replaces %1 with the second argument when exactly one extra arg is given', () =>
		{
			env.egw().set_lang_arr('common', {'welcome %1': 'Willkommen %1'});
			assert.equal(env.egw().lang('welcome %1', 'Ralf'), 'Willkommen Ralf');
		});

		it('replaces multiple %N placeholders safely, even if an argument itself contains "%2"', () =>
		{
			env.egw().set_lang_arr('common', {'%1 of %2': '%1 von %2'});
			assert.equal(env.egw().lang('%1 of %2', 'a%2b', 'c'), 'a%2b von c');
		});

		it('returns an empty string for null/undefined messages', () =>
		{
			assert.equal(env.egw().lang(null as any), '');
			assert.equal(env.egw().lang(undefined as any), '');
		});

		it('returns non-string, truthy messages unchanged', () =>
		{
			const obj : any = {foo: 'bar'};
			assert.strictEqual(env.egw().lang(obj), obj);
		});
	});

	describe('images', () =>
	{
		it('returns null before set_images() has ever been called', () =>
		{
			assert.isNull(env.egw().image('anything'));
		});

		it('prefers a vfs-mapped image over everything else', () =>
		{
			env.egw().set_images({vfs: {my_vfs_icon: '/vfs/path/icon.png'}});
			assert.equal(env.egw().image('my_vfs_icon'), 'https://example.test/vfs/path/icon.png');
		});

		it('resolves via the global override map for a non-"navbar" name', () =>
		{
			// webserverUrl and the stored bootstrap path are simply
			// concatenated with no separator - real server-supplied image
			// maps store paths with a leading "/", which the test fixture
			// must match too.
			env.egw().set_images({
				global: {'infolog/some_icon': 'shared/icon'},
				bootstrap: {'shared/icon': '/bootstrap/icon.svg'}
			});
			assert.equal(env.egw().image('some_icon', 'infolog'), 'https://example.test/bootstrap/icon.svg');
		});

		it('KNOWN QUIRK: "navbar" images consult the global override map only when the app is exactly "api"', () =>
		{
			// `images.global !== undefined && (_name !== 'navbar' || _app === 'api')`
			// - the SAME global override entry resolves for app "api" but is
			// silently skipped for every other app.
			env.egw().set_images({
				global: {navbar: 'shared/navbar'},
				bootstrap: {'shared/navbar': '/bootstrap/navbar.svg'}
			});

			assert.equal(env.egw().image('navbar', 'api'), 'https://example.test/bootstrap/navbar.svg');
			assert.isNull(env.egw().image('navbar', 'infolog'), 'the identical override is skipped for any other app');
		});

		it('falls back to a plain app-specific image when nothing else matches', () =>
		{
			env.egw().set_images({infolog: {edit: '/infolog/edit.png'}});
			assert.equal(env.egw().image('edit', 'infolog'), 'https://example.test/infolog/edit.png');
		});

		it('strips a known image extension and retries when the exact name is not found', () =>
		{
			env.egw().set_images({infolog: {edit: '/infolog/edit.png'}});
			assert.equal(env.egw().image('edit.png', 'infolog'), 'https://example.test/infolog/edit.png');
		});

		it('returns null (and does not throw) when nothing at all matches', () =>
		{
			env.egw().set_images({});
			assert.isNull(env.egw().image('totally-unknown-image', 'nosuchapp'));
		});

		it('image_element() creates an <img> with src/alt set', () =>
		{
			const img = env.egw().image_element('http://x/y.png', 'alt text');
			assert.equal(img.tagName, 'IMG');
			assert.include(img.src, 'y.png');
			assert.equal(img.alt, 'alt text');
		});
	});

	describe('mime_icon', () =>
	{
		it('treats an empty/falsy mime type the same as "unknown"', () =>
		{
			env.egw().set_images({etemplate: {mime128_unknown: '/mime/unknown.png'}});
			assert.equal(env.egw().mime_icon(''), 'https://example.test/mime/unknown.png');
		});

		it('remaps httpd/unix-directory (Vfs::DIR_MIME_TYPE) to the generic directory icon', () =>
		{
			env.egw().set_images({etemplate: {mime128_directory: '/mime/dir.png'}});
			assert.equal(env.egw().mime_icon('httpd/unix-directory'), 'https://example.test/mime/dir.png');
		});

		it('uses an app-specific navbar icon for a directory directly under /apps/<name> or /templates/<name>', () =>
		{
			// _mime becomes 'egw/infolog', which routes through image('navbar', 'infolog') -
			// per the "navbar" KNOWN QUIRK above, a non-"api" app skips the global override
			// map entirely and reads images['infolog']['navbar'] directly.
			env.egw().set_images({infolog: {navbar: '/navbar/infolog.png'}});
			assert.equal(env.egw().mime_icon('httpd/unix-directory', '/apps/infolog'),
				'https://example.test/navbar/infolog.png');

			assert.isNull(env.egw().mime_icon('httpd/unix-directory', '/apps/infolog/subdir'),
				'the app-directory remap only applies to a direct 3-segment /apps/<name> path, not a nested one');
		});

		it('resolves a registered main/sub mime icon when no path is given', () =>
		{
			// without a _path, the thumbnail/svg fast paths below never trigger (they all
			// require typeof _path == "string"), so this exercises the plain icon lookup
			env.egw().set_images({etemplate: {mime128_application_pdf: '/mime/pdf.png'}});
			assert.equal(env.egw().mime_icon('application/pdf'), 'https://example.test/mime/pdf.png');
		});

		it('falls back to the main-type-only icon when no exact main/sub icon is registered', () =>
		{
			env.egw().set_images({etemplate: {mime128_text: '/mime/text.png'}});
			assert.equal(env.egw().mime_icon('text/some-obscure-subtype'), 'https://example.test/mime/text.png');
		});

		it('resolves a legacy/aliased mime type through mime_alias_map (eg. text/vcard -> text/x-vcard)', () =>
		{
			env.egw().set_images({etemplate: {'mime128_text_x-vcard': '/mime/vcard.png'}});
			assert.equal(env.egw().mime_icon('text/vcard'), 'https://example.test/mime/vcard.png');
		});

		it('falls back to the generic "unknown" icon when nothing at all matches', () =>
		{
			env.egw().set_images({etemplate: {mime128_unknown: '/mime/unknown.png'}});
			assert.equal(env.egw().mime_icon('application/totally-unregistered-subtype'),
				'https://example.test/mime/unknown.png');
		});

		it('resolves image/svg+xml as a direct webdav URL when a path is given, bypassing the icon lookup entirely', () =>
		{
			assert.equal(env.egw().mime_icon('image/svg+xml', '/home/demo/icon.svg'),
				'https://example.test/webdav.php/home/demo/icon.svg');
		});

		it('routes previewable raster-image/PDF/office mime types through the thumbnail service once a path is given', () =>
		{
			// this branch is the one genuinely gated on `typeof _path == "string"` (unlike the
			// no-path test above for the same application/pdf mime type) - it calls this.link(),
			// which isn't provided by egw_config/egw_lang/egw_images alone, so it's stubbed here
			// rather than added to the shared harness (no other test in this file needs it).
			const instance = env.egw() as any;
			instance.link = (url : string, params : any) => url + '?path=' + params.path;

			assert.equal(instance.mime_icon('application/pdf', '/home/demo/report.pdf'),
				'/api/thumbnail.php?path=/home/demo/report.pdf');
			assert.equal(instance.mime_icon('image/jpeg', '/home/demo/photo.jpg'),
				'/api/thumbnail.php?path=/home/demo/photo.jpg');
			assert.equal(instance.mime_icon('application/vnd.oasis.opendocument.text', '/home/demo/letter.odt'),
				'/api/thumbnail.php?path=/home/demo/letter.odt',
				'OpenDocument types must use the thumbnail service too, not just PDF/raster images');
		});

		it('does NOT route a non-previewable type (eg. a generic archive) through the thumbnail service, even with a path', () =>
		{
			const instance = env.egw() as any;
			instance.link = () => { throw new Error('link() must not be called for a non-previewable mime type'); };
			env.egw().set_images({etemplate: {mime128_application_zip: '/mime/zip.png'}});

			assert.equal(instance.mime_icon('application/zip', '/home/demo/archive.zip'),
				'https://example.test/mime/zip.png');
		});
	});
});
