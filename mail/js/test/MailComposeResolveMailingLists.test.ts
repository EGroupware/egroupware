import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {MailCompose} from "../compose";

/**
 * Ticket #125731: toolbar action "Resolve mailing-lists" replaces all distribution lists and groups in
 * To, Cc and Bcc by their members, so the user can check who the message really goes to.
 */
describe("MailCompose.uniqueRecipients()", () =>
{
	it("keeps the first of the same address, whatever its name or case", () =>
	{
		assert.deepEqual(MailCompose.uniqueRecipients(['a@x.org', 'B <b@x.org>', 'A <A@X.org>', 'b@x.org']), ['a@x.org', 'B <b@x.org>']);
	});

	it("drops blank entries", () =>
	{
		assert.deepEqual(MailCompose.uniqueRecipients(['', ' ', 'a@x.org']), ['a@x.org']);
	});
});

describe("MailCompose.resolveMailingLists()", () =>
{
	const LIST = '"Team" <12@lists.egroupware.org>';
	let compose : MailCompose;
	let widgets : Record<string, any>;
	let messages : [string, string][];
	let resolved : any;
	let request : sinon.SinonStub;
	let expander : sinon.SinonSpy;

	const widget = (value : any) =>
	{
		const w : any = {value};
		w.get_value = () => w.value;
		w.set_value = sinon.spy((v : any) => { w.value = v; });
		return w;
	};

	beforeEach(() =>
	{
		messages = [];
		resolved = undefined;
		request = sinon.stub().callsFake(async () => resolved);
		const egw = {
			lang : (label : string, ...args : any[]) => { let i = 0; return String(label).replace(/%(\d+)/g, () => String(args[i++])); },
			message : (msg : string, type : string) => { messages.push([msg, type]); },
			request,
		};
		widgets = {to : widget([]), cc : widget([]), bcc : widget([])};
		compose = new MailCompose({egw} as any);
		(compose as any).et2 = {getWidgetById : (id : string) => widgets[id]};
		expander = sinon.spy();
		(compose as any).fieldExpanderInit = expander;
	});

	afterEach(() => sinon.restore());

	it("tells there is nothing to resolve, without asking the server or changing anything", async () =>
	{
		widgets.to.value = ['a@x.org'];
		widgets.cc.value = 'b@x.org, c@x.org';

		await compose.resolveMailingLists();

		assert.isFalse(request.called);
		assert.isFalse(widgets.to.set_value.called);
		assert.equal(messages.length, 1);
		assert.equal(messages[0][0], 'No mailing-list or group found in the recipients');
		assert.equal(messages[0][1], 'info');
	});

	it("resolves lists and groups in all three fields in one request and sets the members", async () =>
	{
		widgets.to.value = [LIST, 'a@x.org'];
		widgets.cc.value = ['-3'];	// a group
		widgets.bcc.value = [];
		resolved = {to : ['a@x.org', 'm1@x.org', 'm2@x.org'], cc : ['g1@x.org', 'g2@x.org'], bcc : []};

		await compose.resolveMailingLists();

		assert.isTrue(request.calledOnce);
		assert.equal(request.firstCall.args[0], 'mail.EGroupware\\Mail\\Compose.ajax_resolveDistributionLists');
		assert.deepEqual(request.firstCall.args[1], [{to : [LIST, 'a@x.org'], cc : ['-3'], bcc : []}]);
		assert.deepEqual(widgets.to.value, ['a@x.org', 'm1@x.org', 'm2@x.org']);
		assert.deepEqual(widgets.cc.value, ['g1@x.org', 'g2@x.org']);
		assert.deepEqual(widgets.bcc.value, []);
		assert.isTrue(expander.called, 'cc/bcc rows with recipients have to be shown');
		assert.equal(messages.at(-1)![1], 'success');
		assert.include(messages.at(-1)![0], '5 recipients', 'counts the different recipients over all fields');
	});

	it("keeps a recipient once, which is a member of the list and was listed explicitly too", async () =>
	{
		widgets.to.value = ['m1@x.org', LIST];
		resolved = {to : ['m1@x.org', 'M1@X.org', 'm2@x.org'], cc : [], bcc : []};

		await compose.resolveMailingLists();

		assert.deepEqual(widgets.to.value, ['m1@x.org', 'm2@x.org']);
	});

	it("a failed request changes nothing and shows an error", async () =>
	{
		widgets.to.value = [LIST];
		resolved = undefined;	// egw.request() resolves undefined on a server error

		await compose.resolveMailingLists();

		assert.deepEqual(widgets.to.value, [LIST]);
		assert.isFalse(widgets.to.set_value.called);
		assert.equal(messages.at(-1)![1], 'error');
	});
});
