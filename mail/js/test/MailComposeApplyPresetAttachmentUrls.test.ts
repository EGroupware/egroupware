import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail tests do, before compose.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailCompose} from "../compose";
import {sameOriginUrl} from "../../../api/js/jsapi/egw_utils";

/**
 * MailCompose.applyPresetAttachmentUrls() (tickets #125601/#125621): mail's own REST API
 * (ApiHandler::prepareAttachments()) references a REST-uploaded attachment by its existing
 * "/mail/attachments/<token>" url + name/type/size, rather than inlining the whole file as base64
 * the way applyPresetAttachmentContent() does for calendar's freshly-generated .ics (which really
 * has nothing else to reference). This fetches the referenced bytes itself and uploads them via
 * the same JMAP-blob pipeline (MailJmap.uploadAttachment()) a user's own drag-and-drop attach
 * already uses, merging the result via carryForwardAttachments() - same jmapBlobId-tagged shape
 * either way.
 *
 * Root cause this replaces: prepareAttachments() used to base64-encode the referenced temp file
 * into `attachmentContents` - fine for a short calendar .ics, but a real-world attachment (found
 * live: a customer's 128KB PDF) made the preset - which travels a server push and then a browser
 * form-POST back to compose.php - large enough to risk silent truncation somewhere along that
 * path. A lightweight reference (this function) avoids that entirely, regardless of attachment
 * size.
 *
 * Setup: bare MailCompose (constructor only sets `this.app`/isJmapMode), `et2.getWidgetById()`
 * only ever asked for 'mailaccount' (currentProfileID()'s own read) - same minimal fake other
 * compose.ts unit tests (eg. MailComposeApplyPresetFilemode.test.ts) already use.
 *
 * Found live via ticket #125621 (2026-10-05): the `url` prepareAttachments() sends can come back
 * with the WRONG scheme (`http:` under at least one reverse-proxy setup, even though this page
 * itself loaded over `https:` - config_webserver_url is commonly just a bare path, so the server
 * has to guess scheme/host from request headers) - the browser then silently blocks the fetch as
 * mixed content. Every `fetch()` call below now goes through `sameOriginUrl()` first (re-bases
 * onto THIS page's own origin - see its own docblock in egw_utils.ts), so the stubbed `fetch()`
 * calls/assertions below expect `sameOriginUrl(url)`, not the bare `url` string.
 */
describe('MailCompose.applyPresetAttachmentUrls()', () =>
{
	let compose : MailCompose;
	let uploadAttachment : sinon.SinonStub;
	let carryForwardAttachments : sinon.SinonStub;
	let fetchStub : sinon.SinonStub;
	let originalFetch : typeof fetch;

	beforeEach(() =>
	{
		uploadAttachment = sinon.stub().callsFake(async(profileID : string, blob : Blob, name : string, type : string) =>
			({blobId: 'blob-' + name, name, type, size: blob.size}));
		const app : any = {jmap: {uploadAttachment}};
		compose = new MailCompose(app);
		(compose as any).et2 = {
			getWidgetById: (id : string) => id === 'mailaccount' ? {get_value: () => '42'} : undefined,
		};
		carryForwardAttachments = sinon.stub(compose, 'carryForwardAttachments' as any);

		originalFetch = window.fetch;
		fetchStub = sinon.stub();
		(<any>window).fetch = fetchStub;
	});

	afterEach(() =>
	{
		(<any>window).fetch = originalFetch;
	});

	function okResponse(content : string, type : string) : any
	{
		return {ok: true, blob: async() => new Blob([content], {type})};
	}

	it('fetches each reference, uploads it as a real JMAP blob, and carries the result forward', async() =>
	{
		fetchStub.withArgs(sameOriginUrl('/mail/attachments/report--abc123')).resolves(okResponse('hello pdf', 'application/pdf'));

		await compose.applyPresetAttachmentUrls([
			{name: 'report.pdf', type: 'application/pdf', url: '/mail/attachments/report--abc123', size: 9},
		]);

		assert.isTrue(fetchStub.calledOnceWith(sameOriginUrl('/mail/attachments/report--abc123'), {credentials: 'same-origin'}));
		assert.isTrue(uploadAttachment.calledOnce);
		const [profileID, blob, name, type] = uploadAttachment.firstCall.args;
		assert.equal(profileID, '42', 'must upload against the compose popup\'s own current account');
		assert.equal(name, 'report.pdf');
		assert.equal(type, 'application/pdf');
		assert.equal(await blob.text(), 'hello pdf', 'the fetched bytes must reach uploadAttachment() unchanged');

		assert.isTrue(carryForwardAttachments.calledOnce);
		const [uploaded, profileIDArg] = carryForwardAttachments.firstCall.args;
		assert.equal(profileIDArg, '42');
		assert.deepEqual(uploaded, [{blobId: 'blob-report.pdf', name: 'report.pdf', type: 'application/pdf', size: 9}]);
	});

	it('handles several references, keeping them in order', async() =>
	{
		fetchStub.withArgs(sameOriginUrl('/mail/attachments/first--a')).resolves(okResponse('AAA', 'text/plain'));
		fetchStub.withArgs(sameOriginUrl('/mail/attachments/second--b')).resolves(okResponse('BBBB', 'text/plain'));

		await compose.applyPresetAttachmentUrls([
			{name: 'first.txt', type: 'text/plain', url: '/mail/attachments/first--a', size: 3},
			{name: 'second.txt', type: 'text/plain', url: '/mail/attachments/second--b', size: 4},
		]);

		assert.equal(uploadAttachment.callCount, 2);
		assert.equal(uploadAttachment.firstCall.args[2], 'first.txt');
		assert.equal(uploadAttachment.secondCall.args[2], 'second.txt');
		const [uploaded] = carryForwardAttachments.firstCall.args;
		assert.deepEqual(uploaded.map((u : any) => u.name), ['first.txt', 'second.txt']);
	});

	it('does nothing for an empty list - no fetch, no upload, nothing carried forward', async() =>
	{
		await compose.applyPresetAttachmentUrls([]);

		assert.isFalse(fetchStub.called);
		assert.isFalse(uploadAttachment.called);
		assert.isFalse(carryForwardAttachments.called);
	});

	it('rejects when a referenced attachment can no longer be fetched (eg. expired temp file)', async() =>
	{
		fetchStub.withArgs(sameOriginUrl('/mail/attachments/gone--x')).resolves({ok: false, status: 404, statusText: 'Not Found'});

		let error : any;
		try
		{
			await compose.applyPresetAttachmentUrls([
				{name: 'gone.pdf', type: 'application/pdf', url: '/mail/attachments/gone--x', size: 1},
			]);
		}
		catch (e)
		{
			error = e;
		}

		assert.isDefined(error, 'a failed fetch must not be swallowed into an empty/silent compose');
		assert.include(error.message, 'gone.pdf');
		assert.isFalse(uploadAttachment.called);
		assert.isFalse(carryForwardAttachments.called);
	});

	it('re-bases a URL with the wrong scheme/host onto this page\'s own origin before fetching (ticket #125621)', async() =>
	{
		fetchStub.withArgs(location.origin + '/mail/attachments/report--abc123')
			.resolves(okResponse('hello pdf', 'application/pdf'));

		await compose.applyPresetAttachmentUrls([
			// a server guess gone wrong: wrong scheme AND a different host entirely - same class of
			// bug either way, both must be discarded in favor of this page's own location.origin
			{name: 'report.pdf', type: 'application/pdf', url: 'http://other-host.example/mail/attachments/report--abc123', size: 9},
		]);

		assert.isTrue(fetchStub.calledOnceWith(location.origin + '/mail/attachments/report--abc123', {credentials: 'same-origin'}));
		assert.isTrue(uploadAttachment.calledOnce, 'must still succeed - the mismatched scheme/host must not reach fetch() at all');
	});
});
