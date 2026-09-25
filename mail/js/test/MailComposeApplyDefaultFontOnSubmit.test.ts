import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail tests do, before compose.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailCompose} from "../compose";

/**
 * Ticket #125241 (a real customer, relayed by Ingo): a message's font/size shown in the Sent
 * folder didn't match what was shown while composing it. Root cause: Et2HtmlArea.applyDefaultFont
 * only inlines the user's preferred font/size into the markup when ITS OWN getValue() sees
 * submit_value===true (its own docblock - "easier to do here before submit than to do it
 * server-side"). The classic postback always passed that on every real form submit
 * (etemplate2.ts's getValues(): "true: let widget know getValue()/submit is calling it"); this
 * JMAP-native compose has no equivalent postback at all, and currentEmailFields() read the body
 * via the legacy get_value() compat shim (Et2InputWidget.ts: `get_value() { return
 * this.getValue(); }`, no arguments) - so the font/size never got inlined for a real send.
 *
 * Setup: a bare MailCompose over a fake app/et2, same established minimal-stub pattern
 * MailComposeApplyPresetFilemode.test.ts already uses - only the html/plaintext widget matters
 * here, everything else currentEmailFields() touches degrades gracefully via its own `?.`
 * optional chaining.
 */
describe('MailCompose.currentEmailFields() applies the default font on read', () =>
{
	let compose : MailCompose;

	function createHtmlWidget(getValueResult : string) : {get_value : sinon.SinonSpy, getValue : sinon.SinonSpy}
	{
		return {
			// the legacy compat shim - must NEVER be the one currentEmailFields() actually uses
			get_value: sinon.spy(() => { throw new Error('get_value() must not be called - it never passes submit_value=true'); }),
			getValue: sinon.spy((submitValue? : boolean) => submitValue === true ? getValueResult : 'WRONG: submit_value was not true'),
		};
	}

	beforeEach(() =>
	{
		const egw = {lang: (s : string) => s, preference: () => null, message: () => {}};
		compose = new MailCompose({egw} as any);
	});

	it('reads the HTML body via getValue(true), triggering applyDefaultFont, not the legacy get_value()', async() =>
	{
		const mailHtmltext = createHtmlWidget('<p style="font-family: arial">Hello</p>');
		const widgets : any = {mimeType: {get_value: () => true}, mail_htmltext: mailHtmltext};
		(compose as any).et2 = {
			getWidgetById: (id : string) => widgets[id],
			getArrayMgr: () => ({getEntry: () => undefined}),
		};

		const fields = await (compose as any).currentEmailFields(true);

		assert.isTrue(mailHtmltext.getValue.calledOnceWith(true));
		assert.isFalse(mailHtmltext.get_value.called);
		assert.equal(fields.body, '<p style="font-family: arial">Hello</p>');
	});

	it('reads the plain-text body via getValue(true) too, when the message is plain text', async() =>
	{
		const mailPlaintext = createHtmlWidget('Hello, plain');
		const widgets : any = {mimeType: {get_value: () => false}, mail_plaintext: mailPlaintext};
		(compose as any).et2 = {
			getWidgetById: (id : string) => widgets[id],
			getArrayMgr: () => ({getEntry: () => undefined}),
		};

		const fields = await (compose as any).currentEmailFields(true);

		assert.isTrue(mailPlaintext.getValue.calledOnceWith(true));
		assert.isFalse(mailPlaintext.get_value.called);
		assert.equal(fields.body, 'Hello, plain');
	});
});
