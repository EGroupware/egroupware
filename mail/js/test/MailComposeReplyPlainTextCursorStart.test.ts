import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity, JmapReplyContext} from "../jmap";

/**
 * Live report 2026-10-12 (ralf, reproduced against boulder.egroupware.org acc_id=42, subject
 * "Text Mail"): replying to a plain-text original left the textarea's caret at
 * `selectionStart === selectionEnd === value.length` - the very end, after the quote AND the
 * signature composeBodyWithSignature() appends below it - instead of at offset 0, where the
 * leading blank line a reply is built with (see quoteOriginalMessage()/composeBodyWithSignature()
 * in jmap.ts) is meant to be typed into.
 *
 * Root cause: ticket #124821's own fix (bootstrapCompose(), compose.ts) added a `focusBody` call
 * to `currentBodyWidget()?.focus?.()` so a reply starts in the body instead of the "to" field -
 * but a bare focus() on a native <textarea> (Et2Textarea/Shoelace's SlTextarea) whose .value was
 * just set programmatically does NOT reset the caret to 0, unlike a contentEditable region
 * (Et2HtmlArea's own focus() is the same bare call, landing correctly only incidentally). The
 * legacy app.ts `setSelectionRange(0,0)` code that used to handle this predates the async JMAP
 * body-population flow and is gated on container-visibility state that isn't settled yet by the
 * time it runs for this path - effectively dead for it.
 *
 * Fix: bootstrapCompose() now also calls setSelectionRange(0, 0) directly on the plain-text
 * widget specifically (mail_plaintext only - the HTML path already lands correctly and needs no
 * change).
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
	return {egw, _set_Window_title : () => {}} as unknown as MailApp;
}

/** Same shape as MailComposeBootstrapRace.test.ts's own fake, plus focus()/setSelectionRange() spying and a fake shadow DOM for scrollTop. */
function createFakeWidget(id : string, initial : any = '')
{
	const shadowTextarea = {scrollTop : 100};
	return {
		id,
		_value : initial,
		_onchange : null as ((widget : any) => void) | null,
		focusCalled : false,
		selectionRangeCalls : [] as [number, number][],
		shadowTextarea,
		get_value() { return this._value; },
		set_value(v : any) { this._value = v; this._onchange?.(this); },
		getValue() { return this._value; },
		set_disabled() {},
		getParent() { return null; },
		getDOMNode() { return {shadowRoot : {querySelector : () => shadowTextarea}}; },
		async focus() { this.focusCalled = true; },
		setSelectionRange(start : number, end : number) { this.selectionRangeCalls.push([start, end]); },
	};
}

const WIDGET_IDS = ['mailaccount', 'mimeType', 'to', 'cc', 'subject', 'mail_htmltext', 'mail_plaintext'];

function createFakeEt2(compose : MailCompose, initialMailaccount : string)
{
	const widgets : Record<string, any> = {};
	for (const id of WIDGET_IDS) widgets[id] = createFakeWidget(id);
	widgets.mailaccount.set_value(initialMailaccount);
	widgets.mailaccount._onchange = (widget : any) => compose.submitOnChange(egw, widget);

	return {
		getWidgetById : (id : string) => widgets[id],
		getArrayMgr : (_name : string) => ({getEntry : (_key : string) => undefined, data : {}}),
		setArrayMgr : (_name : string, _mgr : any) => {},
		getInstanceManager : () => ({resetDirty : () => {}, etemplate_exec_id : 'test'}),
		widgets,
	};
}

function fakeContext(overrides : Partial<JmapReplyContext> = {}) : JmapReplyContext
{
	return {
		from : [{name : 'Sender', email : 'sender@example.com'}],
		to : [{name : 'Me', email : 'me@example.com'}],
		cc : [], bcc : [], replyTo : null,
		subject : 'Original subject',
		date : '2026-01-01T00:00:00Z',
		mimeType : 'plain',
		body : 'the original body',
		profileID : '1',
		inReplyTo : ['msg1@example.com'],
		references : ['msg1@example.com'],
		attachments : [], threadTopic : null, threadIndex : null, listId : null, autocrypt : null,
		...overrides,
	};
}

function fakeIdentity(overrides : Partial<JmapIdentity> = {}) : JmapIdentity
{
	return {
		id : '1', name : 'Me', email : 'me@example.com', replyTo : null, bcc : null,
		textSignature : '', htmlSignature : '', mayDelete : false, isStandard : false, isPersonal : false,
		...overrides,
	};
}

/** Same as MailComposeBootstrapRace.test.ts's withUrl() - bootstrapCompose() reads window.location.search directly. */
function withUrl<T>(search : string, fn : () => Promise<T>) : Promise<T>
{
	const {pathname, hash} = window.location;
	history.pushState(null, '', pathname + search + hash);
	return fn().finally(() => history.pushState(null, '', pathname + hash));
}

function createComposeForReply(context : JmapReplyContext, identities : JmapIdentity[], initialMailaccount = '1:0')
{
	const app = createFakeApp();
	const jmap = new MailJmap(app);
	(jmap as any).fetchForReply = async() => context;
	(jmap as any).getIdentities = async() => identities;
	(app as any).jmap = jmap;

	const compose = new MailCompose(app);
	(compose as any).isJmapMode = true;
	const et2 = createFakeEt2(compose, initialMailaccount);
	(compose as any).et2 = et2;
	return {compose, et2};
}

describe("MailCompose.bootstrapCompose() - reply caret position (ticket live report 2026-10-12)", () =>
{
	it("moves the caret to offset 0 and scrolls to top for a plain-text reply", async() =>
	{
		const context = fakeContext({mimeType : 'plain'});
		const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);

		await withUrl('?from=reply&id=msg1', () => (compose as any).bootstrapCompose());

		const plaintext = et2.widgets.mail_plaintext;
		assert.isTrue(plaintext.focusCalled);
		assert.deepEqual(plaintext.selectionRangeCalls, [[0, 0]]);
		assert.strictEqual(plaintext.shadowTextarea.scrollTop, 0);
	});

	it("does NOT touch mail_htmltext's selection for an HTML reply - that path already lands correctly on its own", async() =>
	{
		const context = fakeContext({mimeType : 'html', body : '<p>the original body</p>'});
		const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);

		await withUrl('?from=reply&id=msg1', () => (compose as any).bootstrapCompose());

		const htmltext = et2.widgets.mail_htmltext;
		assert.isTrue(htmltext.focusCalled);
		assert.deepEqual(htmltext.selectionRangeCalls, [], "only the plain-text widget needs the explicit reset");
	});

	it("does NOT reposition anything for a forward - focusBody is deliberately false there (recipient field keeps its focus)", async() =>
	{
		const context = fakeContext({mimeType : 'plain'});
		const {compose, et2} = createComposeForReply(context, [fakeIdentity()]);

		await withUrl('?from=forward&id=msg1', () => (compose as any).bootstrapCompose());

		const plaintext = et2.widgets.mail_plaintext;
		assert.isFalse(plaintext.focusCalled);
		assert.deepEqual(plaintext.selectionRangeCalls, []);
	});
});
