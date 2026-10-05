import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {MailCompose} from "../compose";
import {Et2Dialog} from "../../../api/js/etemplate/Et2Dialog/Et2Dialog";

/**
 * Ticket #124811: on Send, ask before sending to more recipients in To and Cc than the admin allows
 * (site configuration mail "max_recipients_to_cc", 0 = off) and offer to move them all to Bcc.
 * Distribution lists and groups are counted with their members, the same recipient counts once.
 */
describe("MailCompose recipient helpers", () =>
{
	it("recipientKey() is the bare lower-case address, a list placeholder is identified by its entry", () =>
	{
		assert.equal(MailCompose.recipientKey('"Doe, John" <John.Doe@Example.org>'), 'john.doe@example.org');
		assert.equal(MailCompose.recipientKey('  Jane@Example.org '), 'jane@example.org');
		assert.equal(MailCompose.recipientKey('"Team" <12@lists.egroupware.org>'), '12@lists.egroupware.org');
	});

	it("countDistinctRecipients() counts the same address in To and Cc once, ignoring case and display name", () =>
	{
		assert.equal(MailCompose.countDistinctRecipients(['a@x.org', 'B <b@x.org>'], ['A <A@X.org>', 'c@x.org']), 3);
		assert.equal(MailCompose.countDistinctRecipients([], []), 0);
		assert.equal(MailCompose.countDistinctRecipients(['', ' ']), 0, 'blank entries are no recipients');
	});

	it("recipientEntries() accepts an array or a comma-separated string", () =>
	{
		assert.deepEqual(MailCompose.recipientEntries(['a@x.org', 'b@x.org']), ['a@x.org', 'b@x.org']);
		assert.deepEqual(MailCompose.recipientEntries('a@x.org, b@x.org ,'), ['a@x.org', 'b@x.org']);
		assert.deepEqual(MailCompose.recipientEntries(undefined), []);
	});
});

describe("MailCompose.confirmManyRecipients()", () =>
{
	let compose : MailCompose;
	let widgets : Record<string, {value : any, get_value : () => any, set_value : sinon.SinonSpy}>;
	let limit : any;
	let resolved : any;
	let request : sinon.SinonStub;
	let dialog : sinon.SinonStub;
	let expander : sinon.SinonSpy;

	const widget = (value : any) =>
	{
		const w : any = {value};
		w.get_value = () => w.value;
		w.set_value = sinon.spy((v : any) => { w.value = v; });
		return w;
	};
	const addresses = (n : number, domain = 'x.org') => Array.from({length : n}, (_, i) => `user${i}@${domain}`);
	/** let the dialog answer with the given button id */
	const answer = (button : string) => dialog.returns({getComplete : () => Promise.resolve([button, {}])});

	beforeEach(() =>
	{
		limit = 5;
		resolved = undefined;
		request = sinon.stub().callsFake(async () => resolved);
		const egw = {
			config : (key : string, app : string) => key === 'max_recipients_to_cc' && app === 'mail' ? limit : undefined,
			lang : (label : string, ...args : any[]) => { let i = 0; return String(label).replace(/%(\d+)/g, () => String(args[i++])); },
			request,
		};
		widgets = {to : widget([]), cc : widget([]), bcc : widget([])};
		compose = new MailCompose({egw} as any);
		(compose as any).et2 = {getWidgetById : (id : string) => widgets[id]};
		expander = sinon.spy();
		(compose as any).fieldExpanderInit = expander;
		dialog = sinon.stub(Et2Dialog, 'show_dialog');
	});

	afterEach(() => sinon.restore());

	const confirm = () : Promise<boolean> => (compose as any).confirmManyRecipients();

	it("does nothing and never asks when the limit is off (0 or not configured)", async () =>
	{
		for (limit of [0, undefined, '', '0'])
		{
			widgets.to.value = addresses(50);
			assert.isTrue(await confirm());
		}
		assert.isFalse(dialog.called);
	});

	it("does not ask up to and including the limit, Bcc is not counted", async () =>
	{
		widgets.to.value = addresses(3);
		widgets.cc.value = addresses(2, 'y.org');
		widgets.bcc.value = addresses(100, 'z.org');
		assert.isTrue(await confirm());
		assert.isFalse(dialog.called);
	});

	it("counts an address in To and Cc once", async () =>
	{
		widgets.to.value = addresses(5);
		widgets.cc.value = addresses(5);	// the same five
		assert.isTrue(await confirm());
		assert.isFalse(dialog.called);
	});

	it("asks above the limit and moves everybody to Bcc on 'bcc': existing Bcc kept, no duplicates, To/Cc empty, Bcc row shown", async () =>
	{
		widgets.to.value = addresses(4);
		widgets.cc.value = [...addresses(2), 'extra@x.org', 'extra2@x.org'];	// user0, user1 again --> 6 different
		widgets.bcc.value = ['hidden@x.org', 'user0@x.org'];
		answer('bcc');

		assert.isTrue(await confirm());

		assert.isTrue(dialog.calledOnce);
		assert.deepEqual(widgets.bcc.value, ['hidden@x.org', 'user0@x.org', 'user1@x.org', 'user2@x.org', 'user3@x.org', 'extra@x.org', 'extra2@x.org']);
		assert.deepEqual(widgets.to.value, []);
		assert.deepEqual(widgets.cc.value, []);
		assert.isTrue(expander.called, 'the Bcc row is hidden while empty and has to be shown now');
		assert.include(dialog.firstCall.args[1], '6 recipients', 'message names the number of different recipients');
	});

	it("'send' sends unchanged and does not ask again for the same recipients, but for changed ones", async () =>
	{
		widgets.to.value = addresses(6);
		answer('send');

		assert.isTrue(await confirm());
		assert.deepEqual(widgets.to.value, addresses(6), 'nothing moved');
		assert.isTrue(await confirm(), 'repeated submit, eg. after entering the S/MIME passphrase');
		assert.equal(dialog.callCount, 1);

		widgets.to.value = addresses(7);
		assert.isTrue(await confirm());
		assert.equal(dialog.callCount, 2, 'recipients changed since');
	});

	it("'cancel' (or closing the dialog) stops sending and changes nothing", async () =>
	{
		widgets.to.value = addresses(6);
		for (const button of ['cancel', 'undefined'])
		{
			answer(button);
			assert.isFalse(await confirm());
		}
		assert.deepEqual(widgets.to.value, addresses(6));
		assert.deepEqual(widgets.bcc.value, []);
	});

	it("counts the members of a distribution list or group, resolved server-side", async () =>
	{
		widgets.to.value = ['"Team" <12@lists.egroupware.org>', 'one@x.org'];
		resolved = {to : addresses(8), cc : []};
		answer('cancel');

		assert.isFalse(await confirm(), 'two entries, but eight recipients');
		assert.isTrue(request.calledOnce);
		assert.equal(request.firstCall.args[0], 'mail.EGroupware\\Mail\\Compose.ajax_resolveDistributionLists');
	});

	it("a list under the limit after resolving needs no question", async () =>
	{
		widgets.to.value = ['-3'];	// a group
		resolved = {to : addresses(4), cc : []};
		assert.isTrue(await confirm());
		assert.isFalse(dialog.called);
	});

	it("does not resolve at all, if plain addresses already exceed the limit, or there is no list", async () =>
	{
		widgets.to.value = ['"Team" <12@lists.egroupware.org>', ...addresses(5)];
		answer('send');
		await confirm();
		widgets.to.value = addresses(6, 'y.org');
		await confirm();
		assert.isFalse(request.called);
	});

	it("a failed list expansion does not block sending - the real send reports it", async () =>
	{
		widgets.to.value = ['-3'];
		resolved = undefined;	// egw.request() resolves undefined on a server error
		assert.isTrue(await confirm());
		assert.isFalse(dialog.called);
	});
});
