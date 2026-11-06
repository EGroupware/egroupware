/**
 * Tests for egw_lang.js ("lang" module) - specifically the url langRequire() builds for
 * /api/lang.php, and the "no language yet" case that used to make that url unusable.
 *
 * Behaviour under test
 * --------------------
 * langRequire() turns each {app, lang} entry into an `/api/lang.php?app=&lang=&etag=` module
 * url and dynamically imports it. api/lang.php validates `lang` against
 * /^[a-z]{2}(-[a-z]{2})?$/i and answers a plain-text error for anything else, so an entry
 * whose `lang` is missing must NOT be turned into `lang=undefined` - the import of that
 * non-module response throws and the window ends up with no translations for that app at all.
 * Entries that DO carry a language (everything the server sends via Etemplate's `langRequire`)
 * must still be used verbatim.
 *
 * Setup strategy
 * --------------
 * The real, unmodified egw_lang.ts is loaded into a throwaway iframe by
 * EgwLinksPrefsUserHarness. The import inside langRequire() is a bare `import(src)` with no
 * seam to stub, so the url is observed where it is unambiguous: the iframe's own resource
 * timing. `webserverUrl` points at a path that does not exist, so each import fails fast with
 * a 404 (which resource timing still records) rather than hitting a real lang.php.
 *
 * Pass criteria
 * -------------
 * Exactly one /api/lang.php request per requested app, with the expected `lang=` in its query
 * string. A failure where `lang=undefined` shows up is the product bug; a failure where no
 * /api/lang.php entry is recorded at all is an environment/harness issue (see
 * waitForLangRequests() below, which distinguishes the two by timing out rather than
 * asserting on an empty list).
 *
 * Environment-sensitive constraints
 * ---------------------------------
 * Resource timing is same-origin only - that is why webserverUrl is a path, not an absolute
 * url to another host. The imports are expected to reject; each call site swallows that.
 */
import {assert} from "@open-wc/testing";
import {createEgwLinksPrefsUserEnv, EgwLinksPrefsUserEnv} from "./EgwLinksPrefsUserHarness";

/** Not a real directory - every /api/lang.php import below is meant to 404 */
const WEBSERVER_URL = '/__egw_lang_test__';

describe('egw_lang.js (lang)', () =>
{
	let env : EgwLinksPrefsUserEnv;

	beforeEach(async() =>
	{
		env = await createEgwLinksPrefsUserEnv({webserverUrl: WEBSERVER_URL});
	});

	afterEach(() =>
	{
		env.destroy();
	});

	/**
	 * Query strings of the /api/lang.php requests this env has made so far
	 */
	function langRequests() : string[]
	{
		return (env.window as any).performance.getEntriesByType('resource')
			.map(e => e.name)
			.filter(name => name.includes(WEBSERVER_URL + '/api/lang.php'))
			.map(name => name.substring(name.indexOf('?')));
	}

	/**
	 * Wait until `_count` lang.php requests have been recorded
	 *
	 * A rejected import settles before the browser has necessarily written its resource-timing
	 * entry, so poll rather than read once. Giving up throws, which keeps "the request was
	 * never made" (harness/environment problem) distinguishable from "the request had the
	 * wrong lang" (the product bug the assertions below are about).
	 */
	async function waitForLangRequests(_count : number) : Promise<string[]>
	{
		for(let i = 0; i < 100; ++i)
		{
			const requests = langRequests();
			if (requests.length >= _count) return requests;
			await new Promise(resolve => setTimeout(resolve, 20));
		}
		throw new Error('no /api/lang.php request recorded - harness problem, not the code under test');
	}

	it('uses the language given by the caller', async() =>
	{
		await env.egw().langRequire(env.window, [{app: 'common', lang: 'de'}]).catch(() => {});

		const requests = await waitForLangRequests(1);
		assert.equal(requests.length, 1);
		assert.include(requests[0], 'app=common');
		assert.include(requests[0], 'lang=de');
	});

	it('falls back to the document language, instead of "undefined", when the caller has none', async() =>
	{
		// what Api\Framework::_get_header() renders server-side, and the only language
		// available before /api/user.php has set the preferences
		env.window.document.documentElement.lang = 'de';

		// exactly what langRequireApp() passes on when egw.preference('lang') is not loaded yet
		await env.egw().langRequire(env.window, [{app: 'common', lang: undefined}]).catch(() => {});

		const requests = await waitForLangRequests(1);
		assert.notInclude(requests[0], 'lang=undefined');
		assert.include(requests[0], 'lang=de');
	});

	it('reads the document language from a <meta name="language"> too', async() =>
	{
		const meta = env.window.document.createElement('meta');
		meta.setAttribute('name', 'language');
		meta.setAttribute('content', 'fr');
		env.window.document.head.appendChild(meta);

		await env.egw().langRequire(env.window, [{app: 'common'}]).catch(() => {});

		assert.include((await waitForLangRequests(1))[0], 'lang=fr');
	});

	it('falls back to "en" when the document names no language', async() =>
	{
		await env.egw().langRequire(env.window, [{app: 'common'}]).catch(() => {});

		assert.include((await waitForLangRequests(1))[0], 'lang=en');
	});

	/**
	 * The trigger for all of the above: egw.preference('lang') answers `undefined` - without
	 * requesting anything - until /api/user.php has run egw.set_preferences(..., 'common'),
	 * because egw_preferences.js pre-seeds its "common" slot and so never takes its own
	 * "not loaded -> query the server" path for it. Pinned here because langRequireApp()
	 * feeds exactly this value into langRequire().
	 */
	it('documents why a caller can have no language: common preferences read before they are loaded', () =>
	{
		assert.isUndefined(env.egw().preference('lang'));
		assert.equal(env.jsonCalls.length, 0, 'no request was sent to load them, either');

		env.egw().set_preferences({lang: 'de'}, 'common');
		assert.equal(env.egw().preference('lang'), 'de');
	});

	it('skips an app whose translations are already loaded', async() =>
	{
		env.egw().set_lang_arr('common', {hello: 'hallo'});

		await env.egw().langRequire(env.window, [{app: 'common', lang: 'de'}, {app: 'infolog', lang: 'de'}])
			.catch(() => {});

		const requests = await waitForLangRequests(1);
		assert.equal(requests.length, 1, 'only the not-yet-loaded app is requested');
		assert.include(requests[0], 'app=infolog');
	});
});
