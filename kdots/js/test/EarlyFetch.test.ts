import {assert} from "@open-wc/testing";
import * as sinon from "sinon";

/**
 * kdots/js/early-fetch.js starts the first app's index request from the page head and hands the
 * pending response to the one egw.request() POST with the same url and body, exactly once.
 */
describe('early-fetch.js', () =>
{
	const URL_ = '/egroupware/json.php?menuaction=infolog.kdots_framework.ajax_exec.template.infolog.infolog_ui.index';
	const BODY = '{"request":{"parameters":["index.php?menuaction=infolog.infolog_ui.index&ajax=true"]}}';
	let original : typeof fetch;
	let source : string;
	let stub : sinon.SinonStub;

	before(async() =>
	{
		source = await (await fetch(new URL('../early-fetch.js', import.meta.url).href)).text();
	});
	beforeEach(() =>
	{
		original = window.fetch;
		stub = sinon.stub();
		stub.callsFake(async() => new Response('{}', {status: 200}));
		window.fetch = stub as any;
	});
	afterEach(() =>
	{
		window.fetch = original;
	});

	/** Run the script like the browser does: a classic script element with data attributes */
	const run = (maxAge? : number) =>
	{
		const script = document.createElement('script');
		script.dataset.url = URL_;
		script.dataset.body = BODY;
		if(maxAge !== undefined)
		{
			script.dataset.maxAge = String(maxAge);
		}
		script.textContent = source;
		document.head.appendChild(script);
		script.remove();
	};
	const post = (url = URL_, body = BODY) => window.fetch(url, {method: 'POST', body});

	it('sends the early request at once', () =>
	{
		run();
		assert.equal(stub.callCount, 1);
		assert.equal(stub.firstCall.args[1].body, BODY);
		assert.equal(stub.firstCall.args[1].method, 'POST');
	});

	it('hands the response to the matching request without a second one', async() =>
	{
		run();
		const response = await post();
		assert.equal(response.status, 200);
		assert.equal(stub.callCount, 1, 'no second request');
	});

	it('hands it over only once', async() =>
	{
		run();
		await post();
		await post();
		assert.equal(stub.callCount, 2, 'second request goes to the server');
	});

	it('a different request passes through and leaves the early one alone', async() =>
	{
		run();
		await post(URL_ + '&x=1');
		await post(URL_, '{}');
		assert.equal(stub.callCount, 3, 'early + 2 passed through');
		await post();
		assert.equal(stub.callCount, 3, 'early one still handed over afterwards');
	});

	it('falls back to a normal request if the early one failed', async() =>
	{
		stub.onFirstCall().callsFake(async() => {throw new TypeError('network');});
		run();
		const response = await post();
		assert.equal(response.status, 200);
		assert.equal(stub.callCount, 2);
	});

	it('falls back to a normal request on an error status', async() =>
	{
		stub.onFirstCall().callsFake(async() => new Response('', {status: 500}));
		run();
		const response = await post();
		assert.equal(response.status, 200);
		assert.equal(stub.callCount, 2);
	});

	it('does not use a stale response', async() =>
	{
		run(0);
		await new Promise(resolve => setTimeout(resolve, 5));
		await post();
		assert.equal(stub.callCount, 2);
	});
});
