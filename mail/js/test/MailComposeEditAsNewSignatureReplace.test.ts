import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity} from "../jmap";

/**
 * Ticket #126191 follow-up (Ingo, 2026-10-16): "Wenn ich eine E-Mail 'als neu bearbeite' und dann
 * in dieser neuen E-Mail die Identität ändere wird die neue Signatur unter die bestehende
 * gesetzt." ("edit as new" an existing message, then switch identity - the new signature lands
 * below the EXISTING one instead of replacing it.)
 *
 * bootstrapComposeAsNew() deliberately leaves a non-empty inherited body's own signature untouched
 * and unmarked (ralf's own call: "open as new" should keep the body exactly as-is) - so the first
 * identity switch afterward has no SIGNATURE_MARKER_ID to find. These tests cover
 * updateSignatureForIdentity()'s best-effort fallback (stripLastKnownSignature(),
 * lastKnownSignatureIdentity) instead: recognize and replace the old identity's own signature text
 * when it's still there verbatim, and fall back to the pre-fix "just add the new one" behaviour
 * (ralf, explicitly accepted) when it isn't.
 */

function fakeIdentity(overrides : Partial<JmapIdentity> = {}) : JmapIdentity
{
	return {
		id: '1',
		name: 'Ralf Becker',
		email: 'rb@egroupware.org',
		replyTo: null,
		bcc: null,
		textSignature: 'Mit freundlichen Grüßen\nRalf Becker',
		htmlSignature: '<p>Mit freundlichen Grüßen</p><p>Ralf Becker</p>',
		mayDelete: false,
		isStandard: false,
		isPersonal: false,
		...overrides,
	};
}

const OLD_IDENTITY = fakeIdentity({
	id: '1',
	name: 'Ralf Becker',
	textSignature: 'Mit freundlichen Grüßen\nRalf Becker',
	htmlSignature: '<p>Mit freundlichen Grüßen</p><p>Ralf Becker</p>',
});
const NEW_IDENTITY = fakeIdentity({
	id: '2',
	name: 'Ingo Kreißelmeyer',
	email: 'ik@egroupware.org',
	textSignature: 'Freundliche Grüße\nIngo Kreißelmeyer',
	htmlSignature: '<p>Freundliche Grüße</p><p>Ingo Kreißelmeyer</p>',
});

/** Mirrors Et2Select/Et2Checkbox enough for get_value()/set_value() round-tripping. */
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
	// identity switch landed on the NEW identity (ident_id '2') - matches updateSignatureForIdentity()'s
	// own "re-derive pristine, insert the NEWLY-selected identity's signature" docblock.
	widgets.mailaccount.set_value('1:2');
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
	(jmap as any).getIdentities = async() => [OLD_IDENTITY, NEW_IDENTITY];
	(app as any).jmap = jmap;

	const compose = new MailCompose(app, {from: '', sourceId: '', mode: ''});
	const et2 = createFakeEt2();
	(compose as any).et2 = et2;
	(compose as any).isReplyCompose = false;
	// bootstrapComposeAsNew()'s own assignment for a non-empty inherited body - the identity
	// presumed to match whatever's already sitting in the body unmarked (see its own docblock).
	(compose as any).lastKnownSignatureIdentity = '1:1';
	return {compose, et2};
}

describe("MailCompose.updateSignatureForIdentity() best-effort old-signature replace (ticket #126191 follow-up)", () =>
{
	it("replaces a byte-for-byte matching old signature instead of stacking the new one below it (HTML)", async() =>
	{
		const {compose, et2} = createCompose({insertSignatureAtTopOfMessage: '0'});
		et2.getWidgetById('mail_htmltext').set_value(
			'<p>Hier ist der ursprüngliche Text der E-Mail.</p>' +
			OLD_IDENTITY.htmlSignature
		);

		await (compose as any).updateSignatureForIdentity();

		const value = et2.getWidgetById('mail_htmltext').get_value();
		assert.notInclude(value, 'Ralf Becker', "the old identity's signature must be gone, not just appended-to");
		assert.include(value, 'Ingo Kreißelmeyer', "the new identity's signature must be inserted");
		assert.include(value, 'Hier ist der ursprüngliche Text der E-Mail.', "the original message text must survive");
		// exactly one signature total - not stacked
		assert.strictEqual((value.match(/Grüßen|Grüße/g) || []).length, 1);
	});

	it("replaces a byte-for-byte matching old signature instead of stacking the new one below it (plain text)", async() =>
	{
		const {compose, et2} = createCompose({insertSignatureAtTopOfMessage: '0'});
		et2.getWidgetById('mimeType').set_value(false);
		et2.getWidgetById('mail_plaintext').set_value(
			'Hier ist der ursprüngliche Text der E-Mail.\r\n\r\n-- \r\n' + OLD_IDENTITY.textSignature
		);

		await (compose as any).updateSignatureForIdentity();

		const value = et2.getWidgetById('mail_plaintext').get_value();
		assert.notInclude(value, 'Ralf Becker');
		assert.include(value, 'Ingo Kreißelmeyer');
		assert.include(value, 'Hier ist der ursprüngliche Text der E-Mail.');
	});

	it("falls back to just adding the new signature when the old one can't be confidently identified", async() =>
	{
		const {compose, et2} = createCompose({insertSignatureAtTopOfMessage: '0'});
		// Nothing matching OLD_IDENTITY's own signature text at all - eg. the source message was
		// authored by someone else entirely, or the signature was edited/removed before this reply.
		et2.getWidgetById('mail_htmltext').set_value(
			'<p>Hier ist der ursprüngliche Text der E-Mail.</p><p>Ein ganz anderer Schluss.</p>'
		);

		await (compose as any).updateSignatureForIdentity();

		const value = et2.getWidgetById('mail_htmltext').get_value();
		assert.include(value, 'Ein ganz anderer Schluss.', "unmatched content must be left completely untouched");
		assert.include(value, 'Ingo Kreißelmeyer', "the new identity's signature must still be added");
	});

	it("does nothing special once a real signature marker already exists (normal, already-fixed path)", async() =>
	{
		const {compose, et2} = createCompose({insertSignatureAtTopOfMessage: '0'});
		et2.getWidgetById('mail_htmltext').set_value(
			'<p>Text</p>' +
			'<div id="' + MailJmap.SIGNATURE_MARKER_ID + '"><p>Mit freundlichen Grüßen</p><p>Ralf Becker</p></div>'
		);

		await (compose as any).updateSignatureForIdentity();

		const value = et2.getWidgetById('mail_htmltext').get_value();
		assert.notInclude(value, 'Ralf Becker');
		assert.include(value, 'Ingo Kreißelmeyer');
	});
});
