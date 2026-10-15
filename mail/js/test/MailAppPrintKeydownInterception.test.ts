import {assert} from "@open-wc/testing";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";

const APP_SOURCE = '/mail/js/app.ts';

/**
 * MailApp.installPrintKeydownInterception() - redirects the native Ctrl/Cmd+P shortcut on
 * mail.index (the main window) to the same isolated-popup print path the toolbar's own "print"
 * button uses, instead of letting the browser print the main window in place. Added 2026-10-15
 * (ralf) as the fix for a Ctrl+P CSS-leak report: preparePrint()'s tmpPrintDiv copies the preview
 * iframe's body innerHTML - including any <style> tag a sanitized mail body may contain - into a
 * div that is a direct child of the main window's own document.body, where it applies document-
 * wide instead of staying confined to the iframe it came from.
 */
describe('MailApp.installPrintKeydownInterception()', () =>
{
	let MailAppClass : typeof MailApp;
	let app : MailApp;
	let printCalls : any[][];

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		printCalls = [];
		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {
			appname : 'mail',
			print : (...args : any[]) => { printCalls.push(args); },
		});
	});

	afterEach(() =>
	{
		// Each installed listener is a distinct closure per test's own `app` instance, so this
		// only ever removes the one this particular test actually added (document-global, since
		// that's the whole point - it must catch Ctrl+P regardless of which element has focus).
		if ((app as any).printKeydownHandler)
		{
			document.removeEventListener('keydown', (app as any).printKeydownHandler, {capture: true});
		}
	});

	const ctrlP = (overrides : Partial<KeyboardEventInit> = {}) =>
		new KeyboardEvent('keydown', {key : 'p', ctrlKey : true, cancelable : true, bubbles : true, ...overrides});

	it('redirects Ctrl+P to the same print() the toolbar button uses, and prevents the default', () =>
	{
		(app as any).installPrintKeydownInterception();

		const event = ctrlP();
		const notCancelled = document.dispatchEvent(event);

		assert.isFalse(notCancelled, 'preventDefault() should have been called');
		assert.lengthOf(printCalls, 1);
	});

	it('also redirects Cmd+P (metaKey), for macOS', () =>
	{
		(app as any).installPrintKeydownInterception();

		document.dispatchEvent(ctrlP({ctrlKey : false, metaKey : true}));

		assert.lengthOf(printCalls, 1);
	});

	it('ignores plain "p" (no modifier) and Ctrl+Shift+P', () =>
	{
		(app as any).installPrintKeydownInterception();

		document.dispatchEvent(ctrlP({ctrlKey : false}));
		document.dispatchEvent(ctrlP({shiftKey : true}));

		assert.lengthOf(printCalls, 0);
	});

	it('only ever installs one listener, even if called again (eg. a template reload)', () =>
	{
		(app as any).installPrintKeydownInterception();
		const firstHandler = (app as any).printKeydownHandler;
		(app as any).installPrintKeydownInterception();

		assert.strictEqual((app as any).printKeydownHandler, firstHandler);

		document.dispatchEvent(ctrlP());

		assert.lengthOf(printCalls, 1, 'a stale duplicate listener would have fired print() twice');
	});

	/**
	 * Found live 2026-10-15 (ralf, re-testing on boulder): Cmd+P still opened the native print
	 * dialog over the main window - focus was inside the preview iframe, a separate browsing
	 * context whose keydown events never bubble to the outer document. et2_ready()'s own
	 * 'mail.index' case re-attaches printKeydownHandler to the iframe's contentDocument on every
	 * 'load' to cover that - this documents why a document-only listener alone is not enough.
	 */
	it('does not see a keydown dispatched on an unrelated document (eg. an iframe\'s own)', () =>
	{
		(app as any).installPrintKeydownInterception();

		const otherDocument = document.implementation.createHTMLDocument('iframe body');
		otherDocument.dispatchEvent(ctrlP());

		assert.lengthOf(printCalls, 0, 'a listener on the outer document cannot see this');

		// mirrors what the iframe's own 'load' listener does with the same handler
		otherDocument.addEventListener('keydown', (app as any).printKeydownHandler, {capture : true});
		otherDocument.dispatchEvent(ctrlP());

		assert.lengthOf(printCalls, 1);
	});
});
