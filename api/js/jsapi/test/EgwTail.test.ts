/**
 * Tests for egw_tail.js - the admin log-tail page's standalone
 * DOMContentLoaded page-controller script (not an egw.extend() module).
 *
 * No test coverage existed before this file. See EgwTailHarness for how
 * it's loaded and stubbed - egw.json() is fully test-controlled (no real
 * PHP backend exists in this environment), and DOMContentLoaded is fired
 * synthetically since the harness iframe's document has already loaded by
 * the time the script is injected.
 */
import {assert} from "@open-wc/testing";
import {createEgwTailEnv, EgwTailEnv} from "./EgwTailHarness";

function wait(env : EgwTailEnv, ms : number) : Promise<void>
{
	return new Promise(resolve => env.window.setTimeout(resolve, ms));
}

describe('egw_tail.js', () =>
{
	let env : EgwTailEnv;

	afterEach(() =>
	{
		if (env) env.destroy();
	});

	it('resizes the log element based on window dimensions, regardless of filename', async() =>
	{
		env = await createEgwTailEnv();
		env.fireDomContentLoaded();

		assert.equal(env.log.style.width, '980px');
		assert.equal(env.log.style.height, '736px');
	});

	it('re-resizes the log element on a window "resize" event', async() =>
	{
		env = await createEgwTailEnv();
		env.fireDomContentLoaded();

		(env.window as any).egw_getWindowInnerWidth = () => 500;
		(env.window as any).egw_getWindowInnerHeight = () => 400;
		env.window.dispatchEvent(new (env.window as any).Event('resize'));

		assert.equal(env.log.style.width, '490px');
		assert.equal(env.log.style.height, '368px');
	});

	it('does not start polling when there is no filename', async() =>
	{
		env = await createEgwTailEnv();
		env.fireDomContentLoaded();

		assert.equal(env.jsonCalls.length, 0);
	});

	it('starts polling (ajax_chunk) immediately when a filename is present', async() =>
	{
		env = await createEgwTailEnv({filename: 'access.log'});
		env.fireDomContentLoaded();

		assert.equal(env.jsonCalls.length, 1);
		assert.equal(env.jsonCalls[0].menuaction, 'api.EGroupware\\Api\\Json\\Tail.ajax_chunk');
		assert.deepEqual(env.jsonCalls[0].parameters, ['access.log', 0]);
	});

	describe('button_log()', () =>
	{
		it('clear_log clears the log text without an ajax_delete call', async() =>
		{
			env = await createEgwTailEnv();
			env.fireDomContentLoaded();
			env.log.textContent = 'some content';

			env.clearLogBtn.onclick.call(env.clearLogBtn);

			assert.equal(env.log.textContent, '');
			assert.equal(env.jsonCalls.length, 0);
		});

		it('purge_log sends ajax_delete with keep-file=false and clears the log text', async() =>
		{
			env = await createEgwTailEnv({filename: 'access.log'});
			env.fireDomContentLoaded();
			env.jsonCalls.length = 0; // discard the auto-started ajax_chunk poll
			env.log.textContent = 'some content';

			env.purgeLogBtn.onclick.call(env.purgeLogBtn);

			assert.equal(env.jsonCalls.length, 1);
			assert.equal(env.jsonCalls[0].menuaction, 'api.EGroupware\\Api\\Json\\Tail.ajax_delete');
			assert.deepEqual(env.jsonCalls[0].parameters, ['access.log', false]);
			assert.equal(env.log.textContent, '');
		});

		it('empty_log sends ajax_delete with keep-file=true and clears the log text', async() =>
		{
			env = await createEgwTailEnv({filename: 'access.log'});
			env.fireDomContentLoaded();
			env.jsonCalls.length = 0;
			env.log.textContent = 'some content';

			env.emptyLogBtn.onclick.call(env.emptyLogBtn);

			assert.equal(env.jsonCalls.length, 1);
			assert.deepEqual(env.jsonCalls[0].parameters, ['access.log', true]);
			assert.equal(env.log.textContent, '');
		});

		it('download_log opens a link with the encoded filename, without touching the log text', async() =>
		{
			env = await createEgwTailEnv({filename: 'a b/c.log'});
			env.fireDomContentLoaded();
			env.log.textContent = 'keep me';

			env.downloadLogBtn.onclick.call(env.downloadLogBtn);

			assert.equal(env.openLinkCalls.length, 1);
			assert.include(env.openLinkCalls[0], encodeURIComponent('a b/c.log'));
			assert.equal(env.log.textContent, 'keep me');
			assert.equal(env.jsonCalls.length, 1, 'only the auto-started poll, no delete call');
		});
	});

	describe('refresh_log() callback handling', () =>
	{
		it('appends new content, escaping only "<" (KNOWN QUIRK: ">" is left as-is), and advances log_tail_start', async() =>
		{
			env = await createEgwTailEnv({filename: 'access.log'});
			env.fireDomContentLoaded();
			const [call] = env.jsonCalls;

			call.callback({length: 1, next: 42, content: '<script>', size: '10', writable: true});

			// content is set via textContent (not innerHTML), so the un-escaped ">"
			// is harmless here - but the escaping itself is asymmetric on its face.
			assert.equal(env.log.textContent, '&lt;script>');
		});

		it('a second chunk request uses the advanced log_tail_start', async() =>
		{
			env = await createEgwTailEnv({filename: 'access.log'});
			env.fireDomContentLoaded();
			env.jsonCalls[0].callback({length: 1, next: 42, content: 'abc', size: '10', writable: true});

			await wait(env, 260); // more-data reschedule is 200ms

			assert.equal(env.jsonCalls.length, 2);
			assert.deepEqual(env.jsonCalls[1].parameters, ['access.log', 42]);
		});

		it('hides the download button when size is false', async() =>
		{
			env = await createEgwTailEnv({filename: 'access.log'});
			env.fireDomContentLoaded();
			env.jsonCalls[0].callback({length: 0, next: 0, content: '', size: false, writable: true});

			assert.equal(env.downloadLogBtn.style.display, 'none');
		});

		it('shows the download button with a size-annotated title when size is present', async() =>
		{
			env = await createEgwTailEnv({filename: 'access.log'});
			env.fireDomContentLoaded();
			env.jsonCalls[0].callback({length: 0, next: 0, content: '', size: '10 bytes', writable: true});

			assert.equal(env.downloadLogBtn.style.display, 'block');
			assert.equal(env.downloadLogBtn.getAttribute('title'), 'Size10 bytes');
		});

		it('hides purge/empty buttons when the log is not writable', async() =>
		{
			env = await createEgwTailEnv({filename: 'access.log'});
			env.fireDomContentLoaded();
			env.jsonCalls[0].callback({length: 0, next: 0, content: '', size: '10', writable: false});

			assert.equal(env.purgeLogBtn.style.display, 'none');
			assert.equal(env.emptyLogBtn.style.display, 'none');
		});

		it('shows purge/empty buttons when the log is writable', async() =>
		{
			env = await createEgwTailEnv({filename: 'access.log'});
			env.fireDomContentLoaded();
			env.jsonCalls[0].callback({length: 0, next: 0, content: '', size: '10', writable: true});

			assert.equal(env.purgeLogBtn.style.display, 'block');
			assert.equal(env.emptyLogBtn.style.display, 'block');
		});
	});
});
