import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Regression coverage for MailApp.renderMessageInto()'s partID handling - ticket #125561's own
 * follow-up (ralf, live: "I can open the forwarded eml, but it does not show the original
 * attachment, but the eml again"). Viewing a message/rfc822 sub-part (a forward-as-attachment's
 * own carried message) previously always showed the CONTAINING message's own attachmentsBlock
 * (typically just the carried message itself, since that row's own on-demand attachment fetches
 * only ever fire when attachmentsBlock is still unresolved - which it never is for the containing
 * row here) - clicking that one "attachment" just reopened the same view again, forever.
 *
 * renderMessageInto() now fetches a fresh, partID-scoped attachmentsBlock whenever partID is
 * given, and deliberately never writes it back to egw.dataStoreUID()/mutates the passed-in `data`
 * object - that cache entry is keyed by the CONTAINING message's own uid and shared with the
 * message list/preview pane for THAT message.
 */
function createMailApp(request : sinon.SinonStub, attachmentsBlockWidget : any)
{
	const app = Object.create(MailApp.prototype) as MailApp;
	Object.assign(app, {
		egw : {request, preference : () => 'onlyname'},
		et2 : {getWidgetById : (id : string) => id === 'attachmentsBlock' ? attachmentsBlockWidget : undefined},
		// shadowed on the instance (same "shadow the prototype method" trick
		// MailAppDisplayPartEnvelope.test.ts already uses) - isolates the partID branch itself from
		// unrelated machinery (real URL resolution needs a working this.jmap, view-action wiring)
		setupViewAttachmentActions : sinon.spy(),
		resolveAttachmentViewUrls : sinon.stub().resolves(false),
	});
	return app;
}

describe("MailApp.renderMessageInto() - message/rfc822 sub-part partID handling", () =>
{
	let dataStoreUID : sinon.SinonSpy;

	beforeEach(() =>
	{
		dataStoreUID = sinon.spy();
		(<any>window).egw = {dataStoreUID, dataGetUIDdata : () => ({data : {}}), preference : () => 'onlyname'};
	});

	it("fetches ajax_fetchAttachments scoped to the given partID, not the containing message's own attachments endpoint", () =>
	{
		const request = sinon.stub().resolves({attachmentsBlock : []});
		const app = createMailApp(request, undefined);
		const containingData = {uid : 'mail::5::42::b:1', attachmentsBlock : [{filename : 'forwarded.eml'}]};

		app.renderMessageInto({set_value : sinon.spy()}, 'mail::5::42::b:1', containingData, '2');

		assert.isTrue(request.calledOnceWith('mail.EGroupware\\Mail\\Ui.ajax_fetchAttachments', ['mail::5::42::b:1', null, '2']));
	});

	it("renders the sub-part's OWN attachments once resolved, never the containing message's", async() =>
	{
		const request = sinon.stub().resolves({attachmentsBlock : [{filename : 'egw error.JPG', type : 'image/jpeg'}]});
		const template = {set_value : sinon.spy()};
		const app = createMailApp(request, undefined);
		const containingData = {uid : 'mail::5::42::b:1', attachmentsBlock : [{filename : 'forwarded.eml'}]};

		app.renderMessageInto(template, 'mail::5::42::b:1', containingData, '2');
		await new Promise((resolve) => setTimeout(resolve, 0));
		await new Promise((resolve) => setTimeout(resolve, 0));

		const calls = template.set_value.getCalls().map((c) => c.args[0].content.attachmentsBlock);
		// first (synchronous) render shows an empty/loading list, never the containing message's own
		assert.deepEqual(calls[0], []);
		// once the fetch resolves, the sub-part's own real attachment is what's actually rendered
		const finalCall = template.set_value.lastCall.args[0].content;
		assert.deepEqual(finalCall.attachmentsBlock, [{filename : 'egw error.JPG', type : 'image/jpeg'}]);
	});

	it("never mutates the containing message's own cached data object, and never writes it back to egw.dataStoreUID()", async() =>
	{
		const request = sinon.stub().resolves({attachmentsBlock : [{filename : 'egw error.JPG', type : 'image/jpeg'}]});
		const app = createMailApp(request, undefined);
		const containingData = {uid : 'mail::5::42::b:1', attachmentsBlock : [{filename : 'forwarded.eml'}]};

		app.renderMessageInto({set_value : sinon.spy()}, 'mail::5::42::b:1', containingData, '2');
		await new Promise((resolve) => setTimeout(resolve, 0));
		await new Promise((resolve) => setTimeout(resolve, 0));

		assert.deepEqual(containingData.attachmentsBlock, [{filename : 'forwarded.eml'}],
			"the containing message's own cached row object must survive untouched - it's shared with the message list/preview pane for that message");
		assert.isFalse(dataStoreUID.called,
			"must never cache the sub-part's own attachments under the containing message's uid");
	});

	it("toggles the attachmentsBlock widget's loading class while the fetch is in flight", async() =>
	{
		let resolveFetch : (v : any) => void;
		const request = sinon.stub().returns(new Promise((resolve) => { resolveFetch = resolve; }));
		const classList = {add : sinon.spy(), remove : sinon.spy()};
		const widget = {getDOMNode : () => ({classList})};
		const app = createMailApp(request, widget);

		app.renderMessageInto({set_value : sinon.spy()}, 'mail::5::42::b:1', {uid : 'mail::5::42::b:1'}, '2');

		assert.isTrue(classList.add.calledOnceWith('loading'));
		assert.isFalse(classList.remove.called);

		resolveFetch({attachmentsBlock : []});
		await new Promise((resolve) => setTimeout(resolve, 0));

		assert.isTrue(classList.remove.calledOnceWith('loading'));
	});
});
