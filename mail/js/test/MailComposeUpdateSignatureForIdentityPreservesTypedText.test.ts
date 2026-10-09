import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity} from "../jmap";

/**
 * Ticket #126191 (Ingo, 2026-10-08): "If the identity is changed after some text is written it
 * places the new signature within the text" - reproduced from Ingo's own screen recording. With
 * insertSignatureAtTopOfMessage='1' ("top"), a fresh compose bootstraps a blank leading <p> above
 * the signature (composeBodyWithSignature()'s own "somewhere to click and type" line). The user
 * types several lines directly into that first line/paragraph, then switches the "Von"/identity
 * dropdown. Before this fix, updateSignatureForIdentity() (mail/js/compose.ts) recognized "the
 * blank placeholder line" purely by tag name - once real text replaced the blank content, it was
 * wrongly extracted anyway and re-glued onto the very FRONT of the result, stranding every
 * following line of typed text behind the newly-spliced-in signature instead of all together.
 *
 * Root cause and fix: see updateSignatureForIdentity()'s own updated docblock/comment in
 * mail/js/compose.ts.
 */

function fakeIdentity(overrides : Partial<JmapIdentity> = {}) : JmapIdentity
{
	return {
		id: '2',
		name: 'Ingo Kreißelmeyer',
		email: 'ik@egroupware.org',
		replyTo: null,
		bcc: null,
		textSignature: 'Freundliche Grüße\nIngo Kreißelmeyer',
		htmlSignature: '<p>Freundliche Grüße</p><p>Ingo Kreißelmeyer</p>',
		mayDelete: false,
		isStandard: false,
		isPersonal: false,
		...overrides,
	};
}

/** Mirrors Et2Select/Et2Checkbox enough for get_value()/set_value() round-tripping - same shape MailComposeSignatureSeparatorPreference.test.ts's own createFakeWidget() uses. */
function createFakeWidget(id : string, initial : any = '')
{
	return {
		id, _value: initial,
		get_value() { return this._value; },
		set_value(v : any) { this._value = v; },
		set_disabled() {},
		getParent() { return null; },
	};
}

const WIDGET_IDS = ['mailaccount', 'mimeType', 'mail_htmltext', 'mail_plaintext'];

function createFakeEt2()
{
	const widgets : Record<string, any> = {};
	for (const id of WIDGET_IDS) widgets[id] = createFakeWidget(id);
	widgets.mailaccount.set_value('1:0');
	return {
		getWidgetById : (id : string) => widgets[id],
		getArrayMgr : (_name : string) => ({getEntry : (_key : string) => undefined, data : {}}),
		setArrayMgr : (_name : string, _mgr : any) => {},
		widgets,
	};
}

function createCompose(preferenceValues : Record<string, string | null> = {})
{
	const egw : any = {
		lang : (label : string, ...args : string[]) =>
		{
			let i = 0;
			return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
		},
		preference : (key : string, _app? : string) => preferenceValues[key] ?? null,
		message : (_msg : string, _type? : string) => {},
	};
	const app = {egw} as unknown as MailApp;
	const jmap = new MailJmap(app);
	(jmap as any).getIdentities = async() => [fakeIdentity()];
	(app as any).jmap = jmap;

	// explicitBootstrap truthy -> isJmapMode = true, required for updateSignatureForIdentity()
	// to do anything at all (it's a no-op classic-mode no-op otherwise).
	const compose = new MailCompose(app, {from: '', sourceId: '', mode: ''});
	const et2 = createFakeEt2();
	(compose as any).et2 = et2;
	return {compose, et2};
}

describe("MailCompose.updateSignatureForIdentity() preserves typed text (ticket #126191)", () =>
{
	it("keeps every typed line together in front of the new signature ('top' placement)", async() =>
	{
		const {compose, et2} = createCompose({insertSignatureAtTopOfMessage: '1'});
		// Exactly the reporter's repro: the blank bootstrap line got typed into (no longer blank),
		// two more lines added below it, then the OLD identity's signature marker still present.
		et2.getWidgetById('mail_htmltext').set_value(
			'<p>Ich schreibe hier einen Text</p>' +
			'<p>in mehreren Zeilen</p>' +
			'<p>und ändere dann die Identität</p>' +
			'<div id="' + MailJmap.SIGNATURE_MARKER_ID + '"><p>Old Signature</p></div>'
		);

		await (compose as any).updateSignatureForIdentity();

		const value = et2.getWidgetById('mail_htmltext').get_value();
		assert.notInclude(value, 'Old Signature');
		assert.include(value, 'Freundliche Grüße', "the new identity's signature must be inserted");
		const firstLine = value.indexOf('Ich schreibe hier einen Text');
		const secondLine = value.indexOf('in mehreren Zeilen');
		const thirdLine = value.indexOf('und ändere dann die Identität');
		assert.isAbove(firstLine, -1);
		assert.isAbove(secondLine, -1);
		assert.isAbove(thirdLine, -1);
		assert.isBelow(firstLine, secondLine,
			"all three typed lines must stay in their original relative order");
		assert.isBelow(secondLine, thirdLine,
			"all three typed lines must stay in their original relative order");
		// Ticket #126191 FOLLOW-UP (Ingo, 2026-10-09): 'top'/"before" only has meaning relative to a
		// reply's own quoted content - a fresh, non-reply compose that already has real typed text
		// has nothing for the signature to be "before" at all, so it must land AFTER every typed
		// line instead, regardless of the raw preference value (see updateSignatureForIdentity()'s
		// own forcePlacementBelow computation).
		const signaturePos = value.indexOf('Freundliche Grüße');
		assert.isAbove(signaturePos, thirdLine,
			"the signature must land after every typed line, not before them");
	});

	it("still places the signature ahead of a reply's own quoted content, even with 'top' preference", async() =>
	{
		const {compose, et2} = createCompose({insertSignatureAtTopOfMessage: '1'});
		(compose as any).isReplyCompose = true;
		et2.getWidgetById('mail_htmltext').set_value(
			'<p>Danke für die Info</p>' +
			'<div id="' + MailJmap.SIGNATURE_MARKER_ID + '"><p>Old Signature</p></div>' +
			'<blockquote>Original message text</blockquote>'
		);

		await (compose as any).updateSignatureForIdentity();

		const value = et2.getWidgetById('mail_htmltext').get_value();
		const introPos = value.indexOf('Danke für die Info');
		const signaturePos = value.indexOf('Freundliche Grüße');
		const quotePos = value.indexOf('Original message text');
		assert.isBelow(introPos, signaturePos,
			"the user's own intro line stays pinned above the signature, unaffected by the non-reply forcePlacementBelow override");
		assert.isBelow(signaturePos, quotePos,
			"the signature stays above the quoted original message");
	});

	it("still recognizes a genuinely untouched blank placeholder line and preserves it verbatim", async() =>
	{
		const {compose, et2} = createCompose({insertSignatureAtTopOfMessage: '1'});
		// Bootstrap state: nothing typed yet, just the blank leading line + old signature.
		et2.getWidgetById('mail_htmltext').set_value(
			'<p><br/></p>' +
			'<div id="' + MailJmap.SIGNATURE_MARKER_ID + '"><p>Old Signature</p></div>'
		);

		await (compose as any).updateSignatureForIdentity();

		const value = et2.getWidgetById('mail_htmltext').get_value();
		assert.notInclude(value, 'Old Signature');
		assert.include(value, 'Freundliche Grüße');
		assert.isTrue(value.trimStart().startsWith('<p><br/></p>') || value.trimStart().startsWith('<p><br></p>'),
			"the still-blank placeholder line must still be preserved at the very front, unchanged");
	});
});
