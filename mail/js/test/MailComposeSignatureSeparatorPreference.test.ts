import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity} from "../jmap";

/**
 * Regression coverage for a real report (ralf, 2026-09-15): "It seems we currently are never
 * adding a separator, even if the preference says so, which is the default." - the
 * "disableRulerForSignatureSeparation" mail preference (a select, `$no_yes` values,
 * mail_hooks.inc.php's own default `0` = "No" = separator SHOWN) was never actually honoured for
 * a JMAP-mode compose.
 *
 * Root cause: MailCompose.applySignatureForCurrentIdentity() (mail/js/compose.ts) computed
 * `disableRuler = !!this.egw.preference('disableRulerForSignatureSeparation', 'mail')` - a plain
 * JS truthy coercion. egw.preference() returns the server's raw stored value, which for this
 * select is the literal STRING "0" for its own default/"show the separator" choice. Classic PHP's
 * `!$value` correctly treats "0" as falsy (ComposeMessageBuilder.php's own equivalent check), but
 * JS's `!!value` does NOT - any non-empty string, "0" included, is truthy - so `disableRuler`
 * came out `true` (separator suppressed) for the DEFAULT preference value, and for every other
 * falsy-in-PHP stored value too. The exact same bug class `isPreferenceOn()` (mail/js/jmap.ts) was
 * already introduced to fix for showAllFoldersInFolderPane/pgp_autocrypt_mutual - just never
 * applied to this call site. Fixed by routing through that same helper.
 *
 * MailJmap.composeBodyWithSignature() itself (the actual body-splicing logic, given an
 * already-correct boolean) already has thorough placement/ruler coverage in
 * ComposeBodyWithSignature.test.ts - this file instead covers the gap that let the bug through:
 * applySignatureForCurrentIdentity()'s own translation from the RAW STRING preference values
 * egw.preference() actually returns into that boolean/placement, for both HTML and plain-text
 * mode, and for the signature-placement preference alongside it.
 */

function fakeIdentity(overrides : Partial<JmapIdentity> = {}) : JmapIdentity
{
	return {
		id: '1',
		name: 'Me',
		email: 'me@example.com',
		replyTo: null,
		bcc: null,
		textSignature: 'John Doe',
		htmlSignature: '<p>John Doe</p>',
		mayDelete: false,
		...overrides,
	};
}

/** Mirrors Et2Select/Et2Checkbox enough for get_value()/set_value() round-tripping - same minimal shape MailComposeBootstrapRace.test.ts's own createFakeWidget() uses. */
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

/**
 * @param preferenceValues raw string values, exactly as egw.preference() actually returns them
 *  (EGroupware's own select-preference storage shape) - eg. {disableRulerForSignatureSeparation: '0'}.
 *  A key absent here mirrors a genuinely unset preference (egw.preference() returns null).
 */
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

	const compose = new MailCompose(app);
	const et2 = createFakeEt2();
	(compose as any).et2 = et2;
	return {compose, et2};
}

describe("MailCompose.applySignatureForCurrentIdentity() - disableRulerForSignatureSeparation preference", () =>
{
	it("HTML mode: shows the <hr> ruler for the preference's own default stored value '0' (must not be suppressed)", async() =>
	{
		const {compose, et2} = createCompose({disableRulerForSignatureSeparation : '0'});

		await (compose as any).applySignatureForCurrentIdentity('', false);

		assert.include(et2.getWidgetById('mail_htmltext').get_value(), '<hr class="ruler"',
			"disableRulerForSignatureSeparation='0' means 'No, don't disable' - the ruler must be shown");
	});

	it("HTML mode: shows the <hr> ruler when the preference was never set at all (egw.preference() returns null)", async() =>
	{
		const {compose, et2} = createCompose({});

		await (compose as any).applySignatureForCurrentIdentity('', false);

		assert.include(et2.getWidgetById('mail_htmltext').get_value(), '<hr class="ruler"',
			"an unset preference must fall back to the default 'show the separator' behaviour");
	});

	it("HTML mode: omits the <hr> ruler when the preference is explicitly '1'", async() =>
	{
		const {compose, et2} = createCompose({disableRulerForSignatureSeparation : '1'});

		await (compose as any).applySignatureForCurrentIdentity('', false);

		assert.notInclude(et2.getWidgetById('mail_htmltext').get_value(), '<hr',
			"disableRulerForSignatureSeparation='1' means the user explicitly asked to suppress the ruler");
	});

	it("plain-text mode: uses the '-- ' sig-dashes separator for the default stored value '0'", async() =>
	{
		const {compose, et2} = createCompose({disableRulerForSignatureSeparation : '0'});
		et2.getWidgetById('mimeType').set_value(false);

		await (compose as any).applySignatureForCurrentIdentity('', false);

		assert.include(et2.getWidgetById('mail_plaintext').get_value(), '-- \r\n',
			"'0' must still show the RFC sig-dashes separator in plain-text mode too");
	});

	it("plain-text mode: omits the '-- ' sig-dashes separator when the preference is explicitly '1'", async() =>
	{
		const {compose, et2} = createCompose({disableRulerForSignatureSeparation : '1'});
		et2.getWidgetById('mimeType').set_value(false);

		await (compose as any).applySignatureForCurrentIdentity('', false);

		assert.notInclude(et2.getWidgetById('mail_plaintext').get_value(), '-- ');
	});
});

describe("MailCompose.applySignatureForCurrentIdentity() - insertSignatureAtTopOfMessage preference", () =>
{
	it("places the signature BELOW the body for the default stored value '0'", async() =>
	{
		const {compose, et2} = createCompose({insertSignatureAtTopOfMessage : '0'});
		et2.getWidgetById('mimeType').set_value(false);

		await (compose as any).applySignatureForCurrentIdentity('the body', false);

		const value = et2.getWidgetById('mail_plaintext').get_value();
		assert.isTrue(value.indexOf('the body') < value.indexOf('John Doe'),
			"'0' ('after reply, visible during compose') must place the signature below the body");
	});

	it("places the signature ABOVE the body for the stored value '1'", async() =>
	{
		const {compose, et2} = createCompose({insertSignatureAtTopOfMessage : '1'});
		et2.getWidgetById('mimeType').set_value(false);

		await (compose as any).applySignatureForCurrentIdentity('the body', true);

		const value = et2.getWidgetById('mail_plaintext').get_value();
		assert.isTrue(value.indexOf('John Doe') < value.indexOf('the body'),
			"'1' ('before reply, visible during compose') must place the signature above the body");
	});

	it("inserts no signature at all for 'no_belowaftersend' (appended only right before send, not during compose)", async() =>
	{
		const {compose, et2} = createCompose({insertSignatureAtTopOfMessage : 'no_belowaftersend'});
		et2.getWidgetById('mimeType').set_value(false);

		await (compose as any).applySignatureForCurrentIdentity('the body', false);

		assert.strictEqual(et2.getWidgetById('mail_plaintext').get_value(), 'the body');
	});
});
