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
	// _set_Window_title() - bootstrapCompose()'s own end-of-bootstrap title refresh (2026-09-07,
	// fixing the title not updating for a client-side reply/forward bootstrap since Et2Textbox's
	// set_value() doesn't fire a 'change' event) - a no-op stub is enough, this test only cares
	// about the bootstrapping-flag race, not window title behaviour.
	return {egw, _set_Window_title: () => {}} as unknown as MailApp;
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
		threadTopic: null,
		threadIndex: null,
		listId: null,
		autocrypt: null,
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

	describe("bootstrapCompose() syncs body-container visibility for a blank new compose", () =>
	{
		// bootstrapReply()/bootstrapComposeAsNew() already call syncMimeTypeContainers()
		// themselves once they know their own source message's mimeType - bootstrapSignature()'s
		// blank-new-compose path (no from=reply/composeasnew/... URL param) never did, relying on
		// the body container's own one-shot server-side "disabled" default to already match
		// whatever the mimeType widget itself defaults to. Found live 2026-09-14 investigating
		// "sending to a distribution list via a Group loses the mail body": HTML checkbox showed
		// checked, but the HTML container stayed disabled from that stale default while the
		// PLAINTEXT container was the one actually visible - typing into the visible (plaintext)
		// widget while every send/signature code path reads mail_htmltext instead (mimeType said
		// true) loses the body deterministically, no timing race involved at all.
		it("calls syncMimeTypeContainers() once bootstrapSignature()'s blank-compose path finishes", async() =>
		{
			const app = createFakeApp();
			const jmap = new MailJmap(app);
			(jmap as any).getIdentities = async() => [fakeIdentity()];
			(app as any).jmap = jmap;
			const compose = new MailCompose(app);
			(compose as any).isJmapMode = true;
			const et2 = createFakeEt2(compose, '1:0');
			(compose as any).et2 = et2;
			// Simulates the widget's own HTML-mode default already being in effect before
			// bootstrap runs, same as the live report - nothing here ever calls
			// mimeType.set_value() itself for a genuinely blank compose.
			et2.getWidgetById('mimeType').set_value(true);

			const syncCalls : boolean[] = [];
			(compose as any).syncMimeTypeContainers = (toHtml : boolean) => syncCalls.push(toHtml);

			await withUrl('?jmap=1', () => (compose as any).bootstrapCompose());

			assert.deepEqual(syncCalls, [true],
				"blank-compose bootstrap must sync the body containers to the mimeType widget's own value");
		});

		it("stays a no-op re-sync (same value) for a reply, which already calls it itself", async() =>
		{
			const context = fakeContext({mimeType: 'plain'});
			const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);

			const syncCalls : boolean[] = [];
			const original = (compose as any).syncMimeTypeContainers.bind(compose);
			(compose as any).syncMimeTypeContainers = (toHtml : boolean) =>
			{
				syncCalls.push(toHtml);
				return original(toHtml);
			};

			await withUrl('?jmap=1&from=reply&id=msg1', () => (compose as any).bootstrapCompose());

			// bootstrapReply() itself calls it once (false, for the plain-text original), then
			// bootstrapCompose()'s own trailing call re-confirms the same value - never toggles
			// the container back.
			assert.deepEqual(syncCalls, [false, false]);
			assert.strictEqual(et2.getWidgetById('mimeType').get_value(), false);
		});
	});

	describe("applySignatureForCurrentIdentity() vs. a user typing during its own async gap", () =>
	{
		// Distinct from the "last write wins" hazard below (two CONCURRENT calls racing each
		// other): here there's only ONE call, but it captures `pristineBody` before its own
		// getIdentities() network round-trip (and, for a rich-text body, before awaiting the
		// widget's own TinyMCE init - see Et2HtmlArea.ts's matching fix) - found live 2026-09-14
		// investigating "sending to a distribution list via a Group loses the mail body": a user
		// composing a quick message has plenty of time to start typing during either wait, and the
		// pre-fix code would silently discard it, overwriting the body with just the signature.
		it("inserts the signature after content typed while getIdentities() is still in flight, instead of discarding it", async() =>
		{
			const app = createFakeApp();
			const jmap = new MailJmap(app);
			let resolveIdentities : (identities : JmapIdentity[]) => void;
			(jmap as any).getIdentities = async() => new Promise<JmapIdentity[]>(resolve =>
			{
				resolveIdentities = resolve;
			});
			(app as any).jmap = jmap;
			const compose = new MailCompose(app);
			const et2 = createFakeEt2(compose, '1:0');
			(compose as any).et2 = et2;
			et2.getWidgetById('mimeType').set_value(false);

			const pending = (compose as any).applySignatureForCurrentIdentity('', false);
			// The user starts typing while getIdentities() is still unresolved.
			et2.getWidgetById('mail_plaintext').set_value('user typed message');
			resolveIdentities([fakeIdentity({textSignature: 'Sig', htmlSignature: '<p>Sig</p>'})]);
			await pending;

			const expected = MailJmap.composeBodyWithSignature('user typed message', 'plain',
				{textSignature: 'Sig', htmlSignature: '<p>Sig</p>'}, {placement: 'below', disableRuler: false, isReply: false});
			assert.strictEqual(et2.getWidgetById('mail_plaintext').get_value(), expected,
				"the signature should be appended to what the user typed, not overwrite it with a signature-only body");
			assert.include(et2.getWidgetById('mail_plaintext').get_value(), 'user typed message');
		});
	});

	describe("the underlying hazard (documents why the guard is needed)", () =>
	{
		it("applySignatureForCurrentIdentity() calls racing on the same widget: still corrupts the body", async() =>
		{
			// Directly exercises the mechanism the guard now prevents from ever running concurrently
			// during a bootstrap: two independent calls writing into the SAME body widget - this is
			// what selectIdentityForRecipients()'s un-awaited updateSignatureForIdentity() used to
			// race against bootstrapReply()'s own call. The 2026-09-14 live-typing fix (see
			// applySignatureForCurrentIdentity()'s own docblock/comments) changed the exact SHAPE of
			// the corruption - the first call's own baseline ('') matches its own `pristineBody`
			// parameter, so it now treats the second call's already-written result as "the user
			// typed this" and inserts a SECOND signature on top of it, rather than blindly
			// overwriting it with an empty-pristine one - but two genuinely concurrent calls are
			// still unsafe (now doubled content instead of silently lost content), which is exactly
			// why the bootstrapping flag must keep preventing this from ever happening in practice.
			const app = createFakeApp();
			const jmap = new MailJmap(app);
			let call = 0;
			(jmap as any).getIdentities = async() =>
			{
				call++;
				// first caller resolves LAST, after the second call has already written its own result
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
			const identity = {textSignature: 'Sig', htmlSignature: '<p>Sig</p>'};
			const expectedSecondCallResult = MailJmap.composeBodyWithSignature('quoted body', 'plain',
				identity, {placement: 'below', disableRuler: false, isReply: true});
			const expectedFinalResult = MailJmap.composeBodyWithSignature(expectedSecondCallResult, 'plain',
				identity, {placement: 'below', disableRuler: false, isReply: false});
			assert.strictEqual(et2.getWidgetById('mail_plaintext').get_value(), expectedFinalResult,
				"two genuinely concurrent calls still corrupt the body (now a doubled signature instead of silently losing the quote) - proves concurrent calls remain unsafe, exactly the hazard the bootstrapping flag exists to prevent");
		});
	});
});

/**
 * Regression coverage for a bug found live 2026-09-22 (ralf, while live-testing the
 * 'no_belowaftersend' reply fix above): switching the "From"/identity dropdown repeatedly kept
 * adding ANOTHER leading blank line in front of the reply each time, instead of leaving the one
 * already there alone. Root cause: composeBodyWithSignature() unconditionally (re-)adds its own
 * leading blank line for a reply/forward, and updateSignatureForIdentity()'s pristine-body
 * derivation only ever stripped the signature MARKER div (which that leading line deliberately
 * lives outside of, see its own 2026-09-04 history) - so a previous switch's own insertion kept
 * getting treated as part of "the pristine body" and another one got added on top. Fixed by having
 * updateSignatureForIdentity() detect a correct-type leading block already being there and, in
 * that case only, ask applySignatureForCurrentIdentity() not to add a second one - explicitly
 * NOT by stripping/replacing it, so a user's own typing into that line survives too (ralf: "we
 * probably only need to add that empty-line-block if nothing is there, or at least not the correct
 * type ... independent if it's empty or someone already started typing something into it").
 */
describe("MailCompose updateSignatureForIdentity() - repeated switches stay idempotent", () =>
{
	/** Counts leading-blank-block insertions (<p><br></p> / <div><br></div>, any self-closing style) - the one thing composeBodyWithSignature() ever prefixes a reply body with. */
	function countLeadingBlankBlocks(html : string) : number
	{
		return (html.match(/<(?:p|div)><br\s*\/?>/gi) || []).length;
	}

	it("does not accumulate an extra leading blank line across 5 identity switches (HTML mode reply)", async() =>
	{
		const identityA = fakeIdentity({id: '0', htmlSignature: '<p>Sig A</p>'});
		const identityB = fakeIdentity({id: '1', htmlSignature: '<p>Sig B</p>'});
		const context = fakeContext({mimeType: 'html', body: '<p>the original body</p>'});
		const {compose, et2} = createComposeForReply(context, [identityA, identityB], '1:0');

		await (compose as any).bootstrapReply('msg1', 'reply');

		const afterBootstrap = et2.widgets.mail_htmltext.get_value();
		assert.equal(countLeadingBlankBlocks(afterBootstrap), 1,
			"bootstrap itself should insert exactly one leading blank line");

		// simulate repeatedly changing the "From" dropdown - directly mutating the widget's value
		// (not set_value()) so this test controls exactly when updateSignatureForIdentity() runs,
		// rather than also racing its wired onchange (a separate, already-covered concern above)
		for (let i = 0; i < 5; i++)
		{
			et2.widgets.mailaccount._value = i % 2 === 0 ? '1:1' : '1:0';
			await (compose as any).updateSignatureForIdentity();
		}

		const final = et2.widgets.mail_htmltext.get_value();
		assert.equal(countLeadingBlankBlocks(final), 1,
			`expected exactly one leading blank block after 5 identity switches, got: ${final}`);
		assert.include(final, 'the original body', "the quoted body must survive every switch");
	});

	it("preserves text the user typed into the leading blank line across a later identity switch", async() =>
	{
		const identityA = fakeIdentity({id: '0', htmlSignature: '<p>Sig A</p>'});
		const identityB = fakeIdentity({id: '1', htmlSignature: '<p>Sig B</p>'});
		const context = fakeContext({mimeType: 'html', body: '<p>the original body</p>'});
		const {compose, et2} = createComposeForReply(context, [identityA, identityB], '1:0');

		await (compose as any).bootstrapReply('msg1', 'reply');

		const withTyping = et2.widgets.mail_htmltext.get_value()
			.replace(/<p><br\s*\/?><\/p>/i, '<p>Hello there</p>');
		et2.widgets.mail_htmltext.set_value(withTyping);

		et2.widgets.mailaccount._value = '1:1';
		await (compose as any).updateSignatureForIdentity();

		const final = et2.widgets.mail_htmltext.get_value();
		assert.include(final, 'Hello there', "the user's own typed text in the leading line must survive an identity switch");
		assert.include(final, 'Sig B', "the newly-selected identity's signature must be inserted");
		assert.notInclude(final, 'Sig A', "the old identity's signature should be gone");
	});

	/**
	 * Found live 2026-09-22 (Ingo, via a real customer "Henze"): with insertSignatureAtTopOfMessage
	 * ('1' - "Signatur über dem zitierten Text anzeigen") AND the separator NOT hidden, switching
	 * identity in a reply made the leading line - including anything the user had already typed
	 * into it - appear to vanish. Root cause: composeBodyWithSignature()'s 'top' branch builds
	 * `start + signatureBlock + body` - the earlier idempotency fix above suppressed `start` (via
	 * isReply:false) but left the pre-existing leading block folded INSIDE `body`, which 'top'
	 * places AFTER the signature block, not before it - so the typed text/blank line ends up
	 * sandwiched between the OLD... no, the NEW signature and the quoted text, easy to miss/looks
	 * gone. Fixed by extracting the leading block's own HTML and re-prepending it to the final
	 * result, restoring its original "topmost, before the signature" position.
	 */
	it("keeps the leading block ABOVE the (new) signature after an identity switch when placement is 'top'", async() =>
	{
		const identityA = fakeIdentity({id: '0', htmlSignature: '<p>Sig A</p>'});
		const identityB = fakeIdentity({id: '1', htmlSignature: '<p>Sig B</p>'});
		const context = fakeContext({mimeType: 'html', body: '<p>the original body</p>'});
		const {compose, et2} = createComposeForReply(context, [identityA, identityB], '1:0');

		const originalPreference = egw.preference;
		egw.preference = (key : string, app? : string) =>
			key === 'insertSignatureAtTopOfMessage' ? '1' : originalPreference(key, app);
		try
		{
			await (compose as any).bootstrapReply('msg1', 'reply');

			const afterBootstrap = et2.widgets.mail_htmltext.get_value();
			assert.isBelow(afterBootstrap.indexOf('<p><br'), afterBootstrap.indexOf('Sig A'),
				"bootstrap itself must place the leading blank line above the signature for 'top'");

			const withTyping = afterBootstrap.replace(/<p><br\s*\/?><\/p>/i, '<p>Hello there</p>');
			et2.widgets.mail_htmltext.set_value(withTyping);

			et2.widgets.mailaccount._value = '1:1';
			await (compose as any).updateSignatureForIdentity();

			const final = et2.widgets.mail_htmltext.get_value();
			assert.include(final, 'Hello there', "the user's own typed text must survive the switch");
			assert.include(final, 'Sig B', "the newly-selected identity's signature must be inserted");
			assert.isBelow(final.indexOf('Hello there'), final.indexOf('Sig B'),
				"the typed leading line must stay ABOVE the signature, not get pushed below/after it");
		}
		finally
		{
			egw.preference = originalPreference;
		}
	});
});

/**
 * Ticket #124821 (2026-09-22, a real customer): "die Einstellung dass immer die persönliche
 * Signatur genommen werden soll wird nicht berücksichtigt" (the "always use my personal
 * signature" setting isn't respected). selectIdentityForRecipients() already applies
 * insertSignatureAtTopOfMessage's sibling preference, mail/defaultIdentity, for reply/forward
 * (tracker #124251's own fix) - but a genuinely NEW, blank compose never consulted it at all until
 * this fix, unlike the classic, now-deleted mail_compose.inc.php's get_preferred_identity(), which
 * ran for every compose() call unconditionally.
 */
describe("MailCompose bootstrapSignature() - 'Default identity for compose' preference (mail/defaultIdentity)", () =>
{
	it("switches to the personal identity for a brand-new compose when the preference is 'personal'", async() =>
	{
		const identityDefault = fakeIdentity({id: '0', htmlSignature: '<p>Default Sig</p>'});
		const identityPersonal = fakeIdentity({id: '1', htmlSignature: '<p>Personal Sig</p>'});
		const app = createFakeApp();
		const jmap = new MailJmap(app);
		(jmap as any).getIdentities = async() => [identityDefault, identityPersonal];
		(app as any).jmap = jmap;
		const compose = new MailCompose(app);
		(compose as any).isJmapMode = true;
		// server pre-selected the account's own lowest-id ("default") identity, same as a normal
		// blank-compose page render without this preference set
		const et2 = createFakeEt2(compose, '1:0');
		(compose as any).et2 = et2;

		const originalPreference = egw.preference;
		egw.preference = (key : string, app? : string) =>
			key === 'defaultIdentity' ? 'personal' : originalPreference(key, app);
		try
		{
			await (compose as any).bootstrapSignature();

			assert.equal(et2.widgets.mailaccount.get_value(), '1:1',
				"mailaccount should be switched to the personal (second) identity");
			assert.include(et2.widgets.mail_htmltext.get_value(), 'Personal Sig');
			assert.notInclude(et2.widgets.mail_htmltext.get_value(), 'Default Sig');
		}
		finally
		{
			egw.preference = originalPreference;
		}
	});

	it("leaves the server-preselected identity alone when the preference is 'last-used'/unset (the everyday case)", async() =>
	{
		const identityDefault = fakeIdentity({id: '0', htmlSignature: '<p>Default Sig</p>'});
		const identityPersonal = fakeIdentity({id: '1', htmlSignature: '<p>Personal Sig</p>'});
		const app = createFakeApp();
		const jmap = new MailJmap(app);
		(jmap as any).getIdentities = async() => [identityDefault, identityPersonal];
		(app as any).jmap = jmap;
		const compose = new MailCompose(app);
		(compose as any).isJmapMode = true;
		const et2 = createFakeEt2(compose, '1:0');
		(compose as any).et2 = et2;

		await (compose as any).bootstrapSignature();

		assert.equal(et2.widgets.mailaccount.get_value(), '1:0', "mailaccount must stay untouched");
		assert.include(et2.widgets.mail_htmltext.get_value(), 'Default Sig');
	});
});

/**
 * Ticket #124821 (2026-09-22, a real customer): replying from mail_ui::displayMessage()'s "view an
 * attached message" popup silently replied to the OUTER/carrying message instead of the attached
 * one - MailApp.composeMessage()'s own backfill only ever read content.mail_id, never content.part.
 * Fixed by threading that popup's `part` through to compose.php's own `part` URL param, which
 * bootstrapCompose() now uses to import the attached message into Drafts first (see
 * MailJmap.importAttachedMessageToDrafts()) and reply to THAT instead - see its own docblock.
 */
describe("MailCompose bootstrapCompose() - 'part' param (reply to an attached message)", () =>
{
	it("imports the attached message into Drafts and replies to the NEW row id, not the containing message", async() =>
	{
		const context = fakeContext({mimeType: 'plain'});
		const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);
		const jmap = (compose as any).app.jmap;
		let importCalledWith : any = null;
		jmap.importAttachedMessageToDrafts = async(rowId : string, partID : string) =>
		{
			importCalledWith = {rowId, partID};
			return 'mail::5::1::drafts-id::new-draft-email-id';
		};
		const bootstrapReplyCalledWith : string[] = [];
		const originalBootstrapReply = (compose as any).bootstrapReply.bind(compose);
		(compose as any).bootstrapReply = async(sourceId : string, from : string) =>
		{
			bootstrapReplyCalledWith.push(sourceId);
			return originalBootstrapReply(sourceId, from);
		};

		await withUrl('?jmap=1&from=reply&id=msg1&part=2', () => (compose as any).bootstrapCompose());

		assert.deepEqual(importCalledWith, {rowId: 'msg1', partID: '2'});
		assert.deepEqual(bootstrapReplyCalledWith, ['mail::5::1::drafts-id::new-draft-email-id'],
			"must reply to the newly-imported Drafts message, not the containing message ('msg1')");
		assert.strictEqual(et2.widgets.mimeType.get_value(), false);
	});

	it("falls back to replying to the containing message when the import fails", async() =>
	{
		const context = fakeContext({mimeType: 'plain'});
		const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);
		const jmap = (compose as any).app.jmap;
		jmap.importAttachedMessageToDrafts = async() => { throw new Error('boom'); };
		const bootstrapReplyCalledWith : string[] = [];
		const originalBootstrapReply = (compose as any).bootstrapReply.bind(compose);
		(compose as any).bootstrapReply = async(sourceId : string, from : string) =>
		{
			bootstrapReplyCalledWith.push(sourceId);
			return originalBootstrapReply(sourceId, from);
		};

		await withUrl('?jmap=1&from=reply&id=msg1&part=2', () => (compose as any).bootstrapCompose());

		assert.deepEqual(bootstrapReplyCalledWith, ['msg1'],
			"a failed import must never block the reply - falls back to the containing message, same as before this feature existed");
	});

	it("never calls importAttachedMessageToDrafts() when no 'part' param is present (the everyday reply)", async() =>
	{
		const context = fakeContext({mimeType: 'plain'});
		const {compose} = createComposeForReply(context, [fakeIdentity()]);
		const jmap = (compose as any).app.jmap;
		let called = false;
		jmap.importAttachedMessageToDrafts = async() => { called = true; return ''; };

		await withUrl('?jmap=1&from=reply&id=msg1', () => (compose as any).bootstrapCompose());

		assert.isFalse(called);
	});
});
