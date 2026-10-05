import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";
import {MailCompose} from "../compose";

// see ComposeWithPresetLongBody.test.ts for why app.ts is loaded through its explicit source path
const APP_SOURCE = '/mail/js/app.ts';

/**
 * Regression coverage for ApiHandler.php's REST compose endpoint (found live 2026-10-05: "REST
 * mail-compose doesn't fill recipients", then "the REST API should NOT use the preference about
 * which identity to use, but the specified identity ... as documented") - proves every field the
 * REST API documents (doc/REST-CalDAV-CardDAV/Mail.md) actually reaches the compose window's own
 * content, for BOTH a plain new compose AND a reply (the `replyEml` case, which used to go through
 * a different, broken push mechanism that silently dropped the ENTIRE preset - see
 * ApiHandler.php's own `post()` comment).
 *
 * `MailCompose`'s own constructor is lightweight (just stores `explicitBootstrap`/`isJmapMode` -
 * `bootstrapCompose()` only actually fires later from `setEtemplate()`, once a real etemplate
 * loads) - stubbing `bootstrapClientSideTemplate()` away means `setEtemplate()` is never called,
 * so `bootstrapPromise` stays its own default resolved promise and none of `MailCompose`'s
 * reply/signature bootstrapping runs for real here (already covered by
 * MailComposeBootstrapRace.test.ts) - this file only proves bootstrapComposePopup()'s OWN
 * preset-merge logic and its `applyPreset*()` hand-off, for both the `from=''` and `from='reply'`
 * shapes ApiHandler.php's two cases produce.
 */
describe('MailApp.bootstrapComposePopup() - REST preset fields reach the compose window', () =>
{
	let MailAppClass : typeof MailApp;
	let app : MailApp;
	let bootstrapTemplateStub : sinon.SinonStub;
	let getComposeToolbarDataStub : sinon.SinonStub;
	let applyPresetBodyStub : sinon.SinonStub;
	let applyPresetAttachmentContentStub : sinon.SinonStub;
	let applyPresetAttachmentUrlsStub : sinon.SinonStub;

	before(async function()
	{
		// Chromium can need more than the global 3s timeout to load mail's complete widget tree
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		applyPresetBodyStub = sinon.stub(MailCompose.prototype, 'applyPresetBody');
		applyPresetAttachmentContentStub = sinon.stub(MailCompose.prototype, 'applyPresetAttachmentContent').resolves();
		applyPresetAttachmentUrlsStub = sinon.stub(MailCompose.prototype, 'applyPresetAttachmentUrls').resolves();

		const egw : any = {
			preference: () => '',
			request: () => Promise.resolve({}),
			message: sinon.spy(),
		};
		(<any>window).egw = egw;

		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {appname: 'mail', egw});
		// MailApp.jmap is a prototype getter over the real MailJmap - shadow it, same pattern
		// MailAppSetComposeVfsFiles.test.ts already uses for the sibling `compose` getter
		Object.defineProperty(app, 'jmap', {value: {hasComposePrepareHook: async() => false}, configurable: true});
		(<any>window).app = app;

		getComposeToolbarDataStub = sinon.stub(app as any, 'getComposeToolbarData').resolves({actions: {}, sel_options: {}, content: {}});
		bootstrapTemplateStub = sinon.stub(app as any, 'bootstrapClientSideTemplate').resolves();
	});

	afterEach(() =>
	{
		sinon.restore();
	});

	/**
	 * The REAL shape ApiHandler.php's $preset ends up as for a REST compose request carrying
	 * every documented field at once - NOT an idealized wishlist: `body` is always pre-converted
	 * to html server-side when the caller didn't send `bodyHtml` explicitly (Html::convertTextToHtml()),
	 * so there is deliberately no separate `bodyMimeType` key here (that field only exists for
	 * OTHER, non-REST callers - see bootstrapComposePopup()'s own preset docblock) - applyPresetBody()
	 * must be called with its `sourceMimeType` left undefined/defaulted for a REST-originated preset.
	 */
	const REST_PRESET = {
		to: ['to@example.com'],
		cc: ['cc@example.com'],
		bcc: ['bcc@example.com'],
		replyto: 'replyto@example.com',
		subject: 'Test subject',
		priority: 1,
		body: '<p>Hello body</p>',
		mimeType: 'html',
		identity: '123',
		skipPredefinedAddresses: true,
		attachmentContents: [{name: 'invite.ics', type: 'text/calendar', content: 'QkVHSU46VkNBTEVOREFS'}],
		attachmentUrls: [{name: 'report.pdf', type: 'application/pdf', url: '/mail/attachments/tok123', size: 4096}],
	};

	function assertFullPresetReachedCompose()
	{
		assert.isTrue(bootstrapTemplateStub.calledOnce);
		const content = bootstrapTemplateStub.firstCall.args[1].content;

		assert.deepEqual(content.to, ['to@example.com']);
		assert.deepEqual(content.cc, ['cc@example.com']);
		assert.deepEqual(content.bcc, ['bcc@example.com']);
		assert.deepEqual(content.replyto, ['replyto@example.com'], "replyto must reach compose, wrapped into an array for its et2-email widget");
		assert.equal(content.subject, 'Test subject');
		assert.equal(content.priority, 1);
		assert.equal(content.mailaccount, '7:123', "mailaccount must combine the resolved account with the REST-specified identity");
		assert.equal(content.mimeType, 'html');
		assert.equal(content.is_html, true);

		assert.isTrue(applyPresetBodyStub.calledOnceWith('<p>Hello body</p>', undefined));
		assert.isTrue(applyPresetAttachmentContentStub.calledOnceWith(REST_PRESET.attachmentContents));
		assert.isTrue(applyPresetAttachmentUrlsStub.calledOnceWith(REST_PRESET.attachmentUrls));

		// ticket #125621 - a REST compose must tell getComposeToolbarData() to skip the
		// "predefined compose addresses" account preference, or the user's own personal default
		// Cc/Bcc ends up silently joined alongside the REST caller's own explicit recipients.
		assert.isTrue(getComposeToolbarDataStub.calledOnceWith('7', true));
	}

	it('new compose (ApiHandler.php\'s plain case, no replyEml): every REST field reaches the compose window', async() =>
	{
		await app.bootstrapComposePopup('', '', '7', '', '', '',
			{name: 'mail.compose', url: '', etemplate_exec_id: 'exec1'}, REST_PRESET as any);

		assertFullPresetReachedCompose();
		assert.strictEqual((<any>window).app._compose['explicitBootstrap'].from, '',
			"a plain new compose must not carry a from/reply identity");
		assert.strictEqual((<any>window).app._compose['explicitBootstrap'].sourceId, '');
	});

	it('reply (ApiHandler.php\'s replyEml case): every REST field STILL reaches the compose window, alongside the reply identity', async() =>
	{
		await app.bootstrapComposePopup('reply', '42:INBOX:99', '7', '', '', '',
			{name: 'mail.compose', url: '', etemplate_exec_id: 'exec2'}, REST_PRESET as any);

		// this is the exact combination that used to silently drop the ENTIRE preset (found live
		// 2026-10-05) - the old egw.open()-based push bracket-flattened $preset into a query
		// string compose.php's json_decode() couldn't parse, so from/id survived (plain scalars)
		// but every preset field below died with it.
		assertFullPresetReachedCompose();
		assert.strictEqual((<any>window).app._compose['explicitBootstrap'].from, 'reply',
			"the reply identity must ALSO be carried, not replaced by the preset");
		assert.strictEqual((<any>window).app._compose['explicitBootstrap'].sourceId, '42:INBOX:99');
	});

	it('uses the ActiveProfileID-derived account\'s own default identity when the preset has none (non-REST callers)', async() =>
	{
		const {identity, ...presetWithoutIdentity} = REST_PRESET;
		await app.bootstrapComposePopup('', '', '7', '', '', '',
			{name: 'mail.compose', url: '', etemplate_exec_id: 'exec3'}, presetWithoutIdentity as any);

		const content = bootstrapTemplateStub.firstCall.args[1].content;
		assert.isUndefined(content.mailaccount, "no identity in the preset means mailaccount is left to getComposeToolbarData()'s own server-side default, not forced");
	});
});
