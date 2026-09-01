import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity, JmapReplyContext} from "../jmap";

/**
 * Regression coverage for the bootstrap race found live 2026-09-01 (ralf: "I got HTML for a
 * plain-text original mail, while I should have gotten plain-text in the reply"):
 * MailCompose.bootstrapReply()/bootstrapComposeAsNew() call selectIdentityForRecipients(), which
 * does mailaccount.set_value(...) - a real et2-select set_value() dispatches a genuine 'change'
 * event regardless of whether the change was user- or code-driven, routing through the SAME
 * submitOnChange() the template wires as mailaccount's onchange. That handler's own 'mailaccount'
 * branch used to unconditionally fire updateSignatureForIdentity() un-awaited, racing with
 * bootstrapReply()'s own later mimeType/quote/signature setup - both paths write into
 * currentBodyWidget() (resolved fresh from mimeType's CURRENT value each call), so whichever
 * finishes last wins. The fix: a `bootstrapping` flag, true for bootstrapCompose()'s whole
 * duration, guards that branch so the racy side-effect never fires while a bootstrap is in
 * flight.
 */

const egw : any = {
	lang: (label : string, ...args : string[]) =>
	{
		let i = 0;
		return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
	},
	preference: (_key : string, _app? : string) => null,
	message: (_msg : string, _type? : string) => {},
};

function createFakeApp() : MailApp
{
	return {egw} as unknown as MailApp;
}

/** Mirrors Et2Select/Et2Checkbox: set_value() updates the value AND (if wired) fires onchange synchronously, same as a real dispatchEvent("change") - the exact mechanism the race depends on. */
function createFakeWidget(id : string, initial : any = '')
{
	return {
		id,
		_value: initial,
		_onchange: null as ((widget : any) => void) | null,
		get_value() { return this._value; },
		set_value(v : any)
		{
			this._value = v;
			this._onchange?.(this);
		},
		getValue() { return this._value; },
		set_disabled() {},
		getParent() { return null; },
		getDOMNode() { return null; },
	};
}

const WIDGET_IDS = ['mailaccount', 'mimeType', 'to', 'cc', 'subject', 'mail_htmltext', 'mail_plaintext'];

function createFakeEt2(compose : MailCompose, initialMailaccount : string)
{
	const widgets : Record<string, any> = {};
	for (const id of WIDGET_IDS) widgets[id] = createFakeWidget(id);
	widgets.mailaccount.set_value(initialMailaccount);
	// only mailaccount is wired - that's the confirmed race trigger (see file docblock); mimeType's
	// own onchange (switchMimeTypeClientSide()) is a separate, unrelated concern not under test here
	widgets.mailaccount._onchange = (widget : any) => compose.submitOnChange(egw, widget);

	return {
		getWidgetById: (id : string) => widgets[id],
		getArrayMgr: (_name : string) => ({getEntry: (_key : string) => undefined, data: {}}),
		setArrayMgr: (_name : string, _mgr : any) => {},
		getInstanceManager: () => ({resetDirty: () => {}, etemplate_exec_id: 'test'}),
		widgets,
	};
}

function fakeContext(overrides : Partial<JmapReplyContext> = {}) : JmapReplyContext
{
	return {
		from: [{name: 'Sender', email: 'sender@example.com'}],
		to: [{name: 'Me', email: 'me@example.com'}],
		cc: [],
		bcc: [],
		replyTo: null,
		subject: 'Original subject',
		date: '2026-01-01T00:00:00Z',
		mimeType: 'plain',
		body: 'the original body',
		profileID: '1',
		inReplyTo: ['msg1@example.com'],
		references: ['msg1@example.com'],
		attachments: [],
		...overrides,
	};
}

function fakeIdentity(overrides : Partial<JmapIdentity> = {}) : JmapIdentity
{
	return {
		id: '1',
		name: 'Me',
		email: 'me@example.com',
		replyTo: null,
		bcc: null,
		textSignature: '',
		htmlSignature: '',
		mayDelete: false,
		...overrides,
	};
}

/** Sets window.location.search for the duration of one test - MailCompose.isJmapMode/bootstrapCompose() read it directly, isJmapMode only at construction time. */
function withUrl<T>(search : string, fn : () => T) : T
{
	const {pathname, hash} = window.location;
	history.pushState(null, '', pathname + search + hash);
	try
	{
		return fn();
	}
	finally
	{
		history.pushState(null, '', pathname + hash);
	}
}

/**
 * Builds a MailCompose whose jmap layer is a real MailJmap (so quoteOriginalMessage()/
 * composeBodyWithSignature() run for real, not reimplemented here) with fetchForReply()/
 * getIdentities() replaced by controllable fakes - both real instance methods, monkey-patched
 * per-instance same as createFakeClient()'s own pattern in MailJmap.test.ts.
 */
function createComposeForReply(context : JmapReplyContext, identities : JmapIdentity[], initialMailaccount = '1:0')
{
	const app = createFakeApp();
	const jmap = new MailJmap(app);
	(jmap as any).fetchForReply = async() => context;
	(jmap as any).getIdentities = async() => identities;
	(app as any).jmap = jmap;

	const compose = new MailCompose(app);
	// isJmapMode is normally fixed at construction time from window.location.search (readonly by
	// design - see its own docblock) - overridden directly here rather than juggling URL timing
	// for every test, same as any other readonly-in-TS-only field
	(compose as any).isJmapMode = true;
	const et2 = createFakeEt2(compose, initialMailaccount);
	(compose as any).et2 = et2;
	return {compose, et2};
}

describe("MailCompose bootstrap race (bootstrapping flag)", () =>
{
	describe("submitOnChange() 'mailaccount' branch - direct guard check", () =>
	{
		it("skips updateSignatureForIdentity() while bootstrapping is true", () =>
		{
			const app = createFakeApp();
			const compose = new MailCompose(app);
			(compose as any).et2 = createFakeEt2(compose, '1:0');
			(compose as any).isJmapMode = true;
			(compose as any).bootstrapping = true;
			let called = false;
			(compose as any).updateSignatureForIdentity = async() => { called = true; };

			compose.submitOnChange(egw, {id: 'mailaccount'});

			assert.isFalse(called, "updateSignatureForIdentity() must not fire while bootstrapping");
		});

		it("still fires updateSignatureForIdentity() for a genuine post-bootstrap identity switch", () =>
		{
			const app = createFakeApp();
			const compose = new MailCompose(app);
			(compose as any).et2 = createFakeEt2(compose, '1:0');
			(compose as any).isJmapMode = true;
			(compose as any).bootstrapping = false;
			let called = false;
			(compose as any).updateSignatureForIdentity = async() => { called = true; };

			compose.submitOnChange(egw, {id: 'mailaccount'});

			assert.isTrue(called, "the guard must not permanently disable the identity-switch feature");
		});
	});

	describe("bootstrapReply() end-to-end - reply to a plain-text original", () =>
	{
		it("quotes into mail_plaintext, leaves mail_htmltext empty, mimeType false", async() =>
		{
			const context = fakeContext({mimeType: 'plain'});
			const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);
			let raceCalls = 0;
			(compose as any).updateSignatureForIdentity = async() => { raceCalls++; };

			(compose as any).bootstrapping = true;
			await (compose as any).bootstrapReply('msg1', 'reply');

			assert.strictEqual(et2.widgets.mimeType.get_value(), false);
			assert.include(et2.widgets.mail_plaintext.get_value(), 'the original body');
			assert.strictEqual(et2.widgets.mail_htmltext.get_value(), '');
			assert.strictEqual(raceCalls, 0, "the racy mailaccount side-effect must never fire during bootstrap");
		});
	});

	describe("bootstrapReply() end-to-end - reply to an HTML original", () =>
	{
		it("quotes into mail_htmltext, leaves mail_plaintext empty, mimeType true", async() =>
		{
			const context = fakeContext({mimeType: 'html', body: '<p>the original body</p>'});
			const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);
			let raceCalls = 0;
			(compose as any).updateSignatureForIdentity = async() => { raceCalls++; };

			(compose as any).bootstrapping = true;
			await (compose as any).bootstrapReply('msg1', 'reply');

			assert.strictEqual(et2.widgets.mimeType.get_value(), true);
			assert.include(et2.widgets.mail_htmltext.get_value(), 'the original body');
			assert.strictEqual(et2.widgets.mail_plaintext.get_value(), '');
			assert.strictEqual(raceCalls, 0);
		});
	});

	describe("bootstrapReply() end-to-end - forward a plain-text original", () =>
	{
		it("still resolves to plain mode with no race firing", async() =>
		{
			const context = fakeContext({mimeType: 'plain'});
			const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);
			let raceCalls = 0;
			(compose as any).updateSignatureForIdentity = async() => { raceCalls++; };

			(compose as any).bootstrapping = true;
			await (compose as any).bootstrapReply('msg1', 'forward');

			assert.strictEqual(et2.widgets.mimeType.get_value(), false);
			assert.include(et2.widgets.mail_plaintext.get_value(), 'the original body');
			assert.strictEqual(et2.widgets.mail_htmltext.get_value(), '');
			assert.strictEqual(raceCalls, 0);
		});
	});

	describe("bootstrapReply() end-to-end - reply_all to an HTML original", () =>
	{
		it("still resolves to HTML mode with no race firing", async() =>
		{
			const context = fakeContext({
				mimeType: 'html',
				body: '<p>the original body</p>',
				cc: [{name: 'Other', email: 'other@example.com'}],
			});
			const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);
			let raceCalls = 0;
			(compose as any).updateSignatureForIdentity = async() => { raceCalls++; };

			(compose as any).bootstrapping = true;
			await (compose as any).bootstrapReply('msg1', 'reply_all');

			assert.strictEqual(et2.widgets.mimeType.get_value(), true);
			assert.include(et2.widgets.mail_htmltext.get_value(), 'the original body');
			assert.strictEqual(et2.widgets.mail_plaintext.get_value(), '');
			assert.strictEqual(raceCalls, 0);
		});
	});

	describe("bootstrapReply() - no matching identity (mailaccount.set_value() never called)", () =>
	{
		it("still resolves correctly with nothing to race against", async() =>
		{
			// context.to/cc match no identity's own email - selectIdentityForRecipients() finds
			// no match, so mailaccount.set_value() (the race trigger) is never even called
			const context = fakeContext({mimeType: 'plain', to: [{email: 'someone-else@example.com'}]});
			const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);
			let raceCalls = 0;
			(compose as any).updateSignatureForIdentity = async() => { raceCalls++; };

			await (compose as any).bootstrapReply('msg1', 'reply');

			assert.strictEqual(et2.widgets.mimeType.get_value(), false);
			assert.include(et2.widgets.mail_plaintext.get_value(), 'the original body');
			assert.strictEqual(raceCalls, 0);
		});
	});

	describe("bootstrapCompose() - full URL-driven dispatch, flag lifecycle", () =>
	{
		it("sets bootstrapping true during dispatch and false again after, for a reply URL", async() =>
		{
			const context = fakeContext({mimeType: 'plain'});
			const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);
			const seenDuring : boolean[] = [];
			const originalBootstrapReply = (compose as any).bootstrapReply.bind(compose);
			(compose as any).bootstrapReply = async(...args : any[]) =>
			{
				seenDuring.push((compose as any).bootstrapping);
				return originalBootstrapReply(...args);
			};

			assert.strictEqual((compose as any).bootstrapping, false);
			await withUrl('?jmap=1&from=reply&id=msg1', () => (compose as any).bootstrapCompose());

			assert.deepEqual(seenDuring, [true], "bootstrapping must be true while bootstrapReply() runs");
			assert.strictEqual((compose as any).bootstrapping, false, "must be reset once bootstrapCompose() finishes");
			assert.strictEqual(et2.widgets.mimeType.get_value(), false);
			assert.include(et2.widgets.mail_plaintext.get_value(), 'the original body');
		});

		it("resets bootstrapping to false even when bootstrapReply() throws", async() =>
		{
			const app = createFakeApp();
			const jmap = new MailJmap(app);
			(jmap as any).fetchForReply = async() => { throw new Error('boom'); };
			(app as any).jmap = jmap;
			const compose = new MailCompose(app);
			(compose as any).et2 = createFakeEt2(compose, '1:0');

			let threw = false;
			try
			{
				await withUrl('?jmap=1&from=reply&id=msg1', () => (compose as any).bootstrapCompose());
			}
			catch (e)
			{
				threw = true;
			}

			assert.isFalse(threw, "fetchForReply() failure is caught/surfaced inside bootstrapReply(), not rethrown");
			assert.strictEqual((compose as any).bootstrapping, false);
		});
	});

	describe("the underlying hazard (documents why the guard is needed)", () =>
	{
		it("applySignatureForCurrentIdentity() calls racing on the same widget: last write wins", async() =>
		{
			// Directly exercises the mechanism the guard now prevents from ever running concurrently
			// during a bootstrap: two independent calls writing into the SAME body widget, the
			// later-resolving one clobbering the earlier one - this is what selectIdentityForRecipients()'s
			// un-awaited updateSignatureForIdentity() used to race against bootstrapReply()'s own call.
			const app = createFakeApp();
			const jmap = new MailJmap(app);
			let call = 0;
			(jmap as any).getIdentities = async() =>
			{
				call++;
				// first caller resolves LAST - simulates the pre-fix ordering that clobbered a
				// correctly-quoted body with an empty-pristine, signature-only one
				await new Promise((resolve) => setTimeout(resolve, call === 1 ? 20 : 0));
				return [fakeIdentity({textSignature: 'Sig', htmlSignature: '<p>Sig</p>'})];
			};
			(app as any).jmap = jmap;
			const compose = new MailCompose(app);
			(compose as any).et2 = createFakeEt2(compose, '1:0');
			(compose as any).et2.getWidgetById('mimeType').set_value(false);

			const first = (compose as any).applySignatureForCurrentIdentity('', false);
			const second = (compose as any).applySignatureForCurrentIdentity('quoted body', true);
			await Promise.all([first, second]);

			const et2 = (compose as any).et2;
			const expectedFirstCallResult = MailJmap.composeBodyWithSignature('', 'plain',
				{textSignature: 'Sig', htmlSignature: '<p>Sig</p>'}, {placement: 'below', disableRuler: false, isReply: false});
			assert.strictEqual(et2.getWidgetById('mail_plaintext').get_value(), expectedFirstCallResult,
				"the FIRST call's empty-pristine result won, even though it was issued first - proves 'last write wins' is real and order-sensitive, exactly the hazard the bootstrapping flag now prevents from ever occurring");
		});
	});
});
