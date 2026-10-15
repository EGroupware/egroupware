import {assert} from "@open-wc/testing";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";

const APP_SOURCE = '/mail/js/app.ts';

/**
 * MailApp.intoOwnerRealm() - same purpose and technique as Et2Select/StaticOptions.ts's own
 * intoCacheRealm(), for mail's row cache. renderMessageInto() runs in a "view" popup's own
 * MailApp just as often as in the main window's, and anything it builds there (address-repair
 * results, attachment view urls, ...) is constructed via the POPUP's own realm before being
 * stored into egw's shared row-data cache with egw.dataStoreUID() - read back by the MAIN
 * window's own row widgets. One such object left in that cache kept every closed "view" popup in
 * memory (~20MB each) until the main window reloaded, the same class of bug #305/#306 fixed for
 * the compose-toolbar cache and patchRow().
 */
describe('MailApp.intoOwnerRealm()', () =>
{
	let MailAppClass : typeof MailApp;
	let app : MailApp;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		app = Object.create(MailAppClass.prototype);
	});

	it('returns the value unchanged when egw has no owner window set (main window, or test default)', () =>
	{
		const value = {attachmentsBlock : [{filename : 'a.pdf'}]};

		const result = (app as any).intoOwnerRealm(value);

		assert.strictEqual(result, value);
	});

	it('returns the value unchanged when the owner window IS this window (not actually a popup)', () =>
	{
		const egw = (window as any).egw;
		const original = egw.window;
		egw.window = window;
		try
		{
			const value = {attachmentsBlock : [{filename : 'a.pdf'}]};
			const result = (app as any).intoOwnerRealm(value);
			assert.strictEqual(result, value);
		}
		finally
		{
			egw.window = original;
		}
	});

	/**
	 * A popup shares its opener's egw and so its cache. One object of the popup's own realm in
	 * that cache keeps the whole closed popup in memory, so the stored value must be a copy made
	 * by the owning window. The owner is played by an iframe's window, a realm of its own -
	 * mirrors Et2Select/StaticOptions.ts's own ServerBackedSelects.test.ts test for the identical
	 * concern.
	 */
	it('reconstructs the value via the owner window when it differs from this one', () =>
	{
		const owner = document.createElement('iframe');
		document.body.appendChild(owner);
		const egw = (window as any).egw;
		const original = egw.window;
		egw.window = owner.contentWindow;
		try
		{
			const value = {attachmentsBlock : [{filename : 'a.pdf'}]};

			const result = (app as any).intoOwnerRealm(value);

			assert.notStrictEqual(result, value, 'must be a different object, not the popup-realm one');
			assert.isTrue(result instanceof (owner.contentWindow as any).Array === false);
			assert.isTrue(result instanceof (owner.contentWindow as any).Object, "result is not from the owning window");
			assert.isTrue(result.attachmentsBlock instanceof (owner.contentWindow as any).Array, "nested array is not from the owning window");
			assert.isTrue(result.attachmentsBlock[0] instanceof (owner.contentWindow as any).Object, "nested object is not from the owning window");
			assert.deepEqual(result, value, "value survives the round trip");
		}
		finally
		{
			egw.window = original;
			owner.remove();
		}
	});

	it('falls back to the original value if reconstructing via the owner window throws (eg. closed/inaccessible)', () =>
	{
		// A real closed/cross-origin-inaccessible window throws accessing its own members (eg.
		// "TypeError: can't access property ... closed window") - a plain object missing
		// structuredClone entirely reproduces the same "calling it throws" effect reliably,
		// without depending on browser-specific detached-iframe timing.
		const egw = (window as any).egw;
		const original = egw.window;
		egw.window = {};
		try
		{
			const value = {a : 1};
			const result = (app as any).intoOwnerRealm(value);
			assert.strictEqual(result, value, "keep what we have rather than throw");
		}
		finally
		{
			egw.window = original;
		}
	});
});
