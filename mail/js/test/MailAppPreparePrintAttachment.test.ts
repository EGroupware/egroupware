import {assert} from "@open-wc/testing";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";

const APP_SOURCE = '/mail/js/app.ts';

/**
 * MailApp.preparePrint()'s tempPrintDiv anchoring - a direct child of document.body, NOT a
 * sibling anywhere inside the mail.display template's own widget tree (mailDisplay vbox ->
 * mailDisplayContainer box -> et2-ai, three nested shadow-DOM hosts). Found live 2026-09-14
 * (ralf, relaying a real user's report): printing a long email correctly showed "page 1 of 3" in
 * the print preview, but pages 2/3 came out blank - Chrome's print/PDF pipeline has known
 * limitations paginating content projected through nested shadow-DOM <slot>s. Confirmed live that
 * moving the print copy fully outside that shadow-DOM/slot chain (this test's own subject) was
 * sufficient on its own - no ancestor CSS fix or extra paint-cycle delay needed once the print
 * copy no longer lives inside any shadow root (both were tried first and found unnecessary once
 * this fix was isolated).
 */
describe('MailApp.preparePrint()', () =>
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
		Object.assign(app, {appname : 'mail', et2 : {getWidgetById : () => undefined}});
	});

	afterEach(() =>
	{
		document.body.querySelector('#tempPrintDiv')?.remove();
	});

	it('attaches tempPrintDiv as a direct child of document.body, not nested elsewhere', () =>
	{
		(app as any).preparePrint();

		const tempPrintDiv = document.body.querySelector(':scope > #tempPrintDiv');
		assert.exists(tempPrintDiv, 'tempPrintDiv should be a direct child of document.body');
		assert.equal(tempPrintDiv.parentElement, document.body);
	});

	it('reuses the existing tempPrintDiv on a second call instead of creating a duplicate', () =>
	{
		(app as any).preparePrint();
		(app as any).preparePrint();

		assert.lengthOf(document.body.querySelectorAll('#tempPrintDiv'), 1);
	});

	it('starts hidden (display: none) until displayPrint() shows it for the actual print', () =>
	{
		(app as any).preparePrint();

		const tempPrintDiv = document.body.querySelector<HTMLElement>(':scope > #tempPrintDiv');
		assert.equal(tempPrintDiv.style.display, 'none');
	});
});
