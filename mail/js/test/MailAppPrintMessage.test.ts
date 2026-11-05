import {assert} from "@open-wc/testing";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";
import {addPrintShortcut, fitToPrintWidth, imagesLoaded, printHeaderRows} from "../printBody";

const APP_SOURCE = '/mail/js/app.ts';

/**
 * MailApp.printMessage() prints the body iframe on its own, instead of a copy of the body in the
 * page (#tempPrintDiv), which restyled the whole mail.display popup with the message's own CSS and
 * loaded content the body's CSP blocked (found live 2026-11-04)
 */
describe('MailApp.printMessage()', () =>
{
	let MailAppClass: typeof MailApp;
	let iframes: HTMLIFrameElement[] = [];

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	afterEach(() =>
	{
		iframes.forEach((iframe) => iframe.remove());
		iframes = [];
	});

	/** body iframe with a displayed message, whose print() only records the printed body */
	function bodyIframe(rowId: string | null, body: string = '<div class="mailDisplayBody">the body</div>')
	{
		const iframe = document.createElement('iframe');
		document.body.append(iframe);
		iframes.push(iframe);
		const doc = iframe.contentDocument;
		doc.body.innerHTML = body;
		if (rowId) doc.documentElement.dataset.rowId = rowId;
		const printed: {body: string, darkmode: boolean, colorScheme: string}[] = [];
		(iframe.contentWindow as any).print = () => printed.push({
			body: doc.body.innerHTML,
			darkmode: doc.documentElement.classList.contains('mailDarkmode'),
			colorScheme: iframe.style.colorScheme,
		});
		return {iframe, doc, printed};
	}

	function mailApp(widgets: {[id: string]: HTMLIFrameElement}, printHeaderData?: any): MailApp
	{
		const app = Object.create(MailAppClass.prototype);
		Object.assign(app, {
			appname: 'mail',
			egw: {lang: (phrase: string) => phrase},
			et2: {getWidgetById: (id: string) => widgets[id] ? {iframe: widgets[id]} : undefined},
			printHeaderData,
			currentlyFocussed: printHeaderData?.rowId,
		});
		return app;
	}

	const rowId = 'mail::1::INBOX::42';
	const data = {
		subject: 'A newsletter',
		date: '2026-09-29T18:35:37Z',
		additionalfromaddress: ['Sender <sender@example.org>'],
		additionaltoaddress: ['rb@example.org', 'bb@example.org'],
		ccaddress: [],
		bccaddress: [],
		attachmentsBlock: [{filename: 'guide.pdf'}],
	};

	it('prints the body iframe itself, with a header block added only for printing', () =>
	{
		const {iframe, doc, printed} = bodyIframe(rowId);
		const app = mailApp({mailDisplayBodySrc: iframe}, {rowId, data});

		assert.isTrue(app.printMessage());

		assert.lengthOf(printed, 1);
		assert.include(printed[0].body, 'mailPrintHeader mailDefaultFont', 'in the user\'s default font');
		assert.include(printed[0].body, 'A newsletter');
		assert.include(printed[0].body, 'Sender &lt;sender@example.org&gt;');
		assert.include(printed[0].body, 'rb@example.org, bb@example.org');
		assert.include(printed[0].body, 'guide.pdf');
		assert.include(printed[0].body, 'the body');

		iframe.contentWindow.dispatchEvent(new Event('afterprint'));
		assert.isNull(doc.querySelector('.mailPrintHeader'), 'removed again after printing');
	});

	it('copies nothing of the message into the page', () =>
	{
		const {iframe} = bodyIframe(rowId, '<style>body { background-color: #24378d }</style><p>newsletter</p>');
		const styles = document.querySelectorAll('style').length;

		mailApp({mailDisplayBodySrc: iframe}, {rowId, data}).printMessage();

		assert.isNull(document.getElementById('tempPrintDiv'));
		assert.equal(document.querySelectorAll('style').length, styles);
	});

	it('does not add a second header, if printed again before afterprint', () =>
	{
		const {iframe, doc} = bodyIframe(rowId);
		const app = mailApp({mailDisplayBodySrc: iframe}, {rowId, data});

		app.printMessage();
		app.printMessage();

		assert.lengthOf(doc.querySelectorAll('.mailPrintHeader'), 1);
	});

	it('prints a body shown in dark mode with the message\'s own colors, and restores dark mode afterwards', () =>
	{
		const {iframe, doc, printed} = bodyIframe(rowId);
		doc.documentElement.classList.add('mailDarkmode');
		const app = mailApp({mailDisplayBodySrc: iframe}, {rowId, data});
		let restored = 0;
		(app as any).applyBodyDarkmode = (restoreDoc: Document) =>
		{
			assert.equal(restoreDoc, doc);
			restored++;
		};

		app.printMessage();

		assert.isFalse(printed[0].darkmode);
		assert.equal(printed[0].colorScheme, 'light');
		assert.equal(restored, 0);
		iframe.contentWindow.dispatchEvent(new Event('afterprint'));
		assert.equal(restored, 1);
	});

	it('prefers mail.display\'s mailDisplayBodySrc over messageIFRAME', () =>
	{
		const display = bodyIframe(rowId);
		const preview = bodyIframe('mail::1::INBOX::43');
		const app = mailApp({mailDisplayBodySrc: display.iframe, messageIFRAME: preview.iframe}, {rowId, data});

		app.printMessage();

		assert.lengthOf(display.printed, 1);
		assert.lengthOf(preview.printed, 0);
	});

	it('does nothing without a displayed message', () =>
	{
		const {iframe, printed} = bodyIframe(null);

		assert.isFalse(mailApp({mailDisplayBodySrc: iframe}).printMessage());
		assert.isFalse(mailApp({}).printMessage());
		assert.lengthOf(printed, 0);
	});

	it('does nothing for the mail mail.index\'s preview still shows after it got deselected', () =>
	{
		const {iframe, printed} = bodyIframe(rowId);
		const app = mailApp({messageIFRAME: iframe}, {rowId, data});
		app.currentlyFocussed = '';

		assert.isFalse(app.printMessage());
		assert.lengthOf(printed, 0);
	});

	it('does nothing while Mailvelope shows the message instead of the hidden body iframe', () =>
	{
		const {iframe, printed} = bodyIframe(rowId);
		iframe.style.display = 'none';

		assert.isFalse(mailApp({mailDisplayBodySrc: iframe}, {rowId, data}).printMessage());
		assert.lengthOf(printed, 0);
	});
});

/**
 * mail.index prints the mail of its preview right there: from the preview's toolbar, or ctrl/cmd+P
 * with a mail shown - otherwise ctrl/cmd+P is left to the framework, printing the list, as the app
 * header's print button does
 */
describe('MailApp printing in mail.index', () =>
{
	let MailAppClass: typeof MailApp;
	let elements: HTMLElement[] = [];

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	afterEach(() =>
	{
		elements.forEach((element) => element.remove());
		elements = [];
	});

	/** preview pane (an et2-template) with the body iframe, and a list beside it */
	function mailIndex(rowId = 'mail::1::INBOX::42')
	{
		const preview = document.createElement('et2-template');
		const container = document.createElement('div');
		const header = document.createElement('div');
		preview.append(header, container);
		const list = document.createElement('div');
		document.body.append(preview, list);
		elements.push(preview, list);
		const iframe = document.createElement('iframe');
		container.append(iframe);
		iframe.contentDocument.documentElement.dataset.rowId = rowId;

		const calls: string[] = [];
		const app = Object.create(MailAppClass.prototype);
		Object.assign(app, {
			appname: 'mail',
			et2_obj: {name: 'mail.index'},
			et2: {getWidgetById: (id: string) => ({mailPreviewContainer: container, messageIFRAME: {iframe}})[id]},
			printMessage: () => { calls.push('printMessage'); return true; },
			prevPrint: () => { calls.push('prevPrint'); },
		});
		return {app, header, list, calls};
	}

	it('prints the mail of the preview from its toolbar, without a popup', () =>
	{
		const {app, calls} = mailIndex();

		app.print({data: {}}, []);
		app.print({data: {}}, [{id: 'mail::1::INBOX::42'}]);

		assert.deepEqual(calls, ['printMessage', 'printMessage']);
	});

	it('prints another mail, eg. from a row\'s context menu, in a popup', () =>
	{
		const {app, calls} = mailIndex();

		app.print({data: {}}, [{id: 'mail::1::INBOX::7'}]);
		app.print({data: {}}, [{id: 'mail::1::INBOX::42'}, {id: 'mail::1::INBOX::7'}]);

		assert.deepEqual(calls, ['prevPrint', 'prevPrint']);
	});

	it('prints the shown mail on ctrl+P, without one leaves it to the framework (the list)', () =>
	{
		let shown = true;
		const {app, list, calls} = mailIndex();
		app.printMessage = () => { calls.push('printMessage'); return shown; };
		(window as any).app = {mail: app};
		(app as any).addPrintShortcut(document);
		const press = () =>
		{
			const event = new KeyboardEvent('keydown', {key: 'p', ctrlKey: true, cancelable: true, bubbles: true, composed: true});
			list.dispatchEvent(event);
			return event.defaultPrevented;
		};

		assert.isTrue(press(), 'a mail shown: prints it, wherever the focus is');
		shown = false;
		assert.isFalse(press(), 'no mail shown: left to the framework');
		assert.deepEqual(calls, ['printMessage', 'printMessage']);
	});
});

describe('printHeaderRows()', () =>
{
	const lang = (phrase: string) => phrase.toUpperCase();
	const formatDate = (date: Date) => date.toISOString().slice(0, 10);

	it('lists only the headers a message has, with translated labels', () =>
	{
		assert.deepEqual(printHeaderRows({
			fromaddress: 'Sender <sender@example.org>',
			additionaltoaddress: ['a@example.org', 'b@example.org'],
			ccaddress: [],
			date: '2026-09-29T18:35:37Z',
			attachmentsBlock: [{filename: 'a.pdf'}, {filename: 'b.png'}],
		}, lang, formatDate), [
			['FROM', 'Sender <sender@example.org>'],
			['TO', 'a@example.org, b@example.org'],
			['DATE', '2026-09-29'],
			['ATTACHMENTS', 'a.pdf, b.png'],
		]);
	});

	it('copes with missing data', () =>
	{
		assert.deepEqual(printHeaderRows(undefined, lang, formatDate), []);
		assert.deepEqual(printHeaderRows({date: 'no date'}, lang, formatDate), []);
	});
});

/**
 * Content wider than the page got cut off on the right, eg. a 1600px wide table (print test 5)
 */
describe('fitToPrintWidth()', () =>
{
	let iframe: HTMLIFrameElement;

	afterEach(() => iframe?.remove());

	function bodyDocument(content: string): Document
	{
		iframe = document.createElement('iframe');
		iframe.style.width = '1200px';
		document.body.append(iframe);
		const doc = iframe.contentDocument;
		doc.body.style.margin = '0';
		doc.body.innerHTML = '<div class="mailDisplayBody">' + content + '</div>';
		return doc;
	}

	it('scales down content wider than the printable width, and resets it again', () =>
	{
		const doc = bodyDocument('<table style="width:1600px"><tr><td>wide</td></tr></table>');
		const content = doc.querySelector<HTMLElement>('.mailDisplayBody');

		const reset = fitToPrintWidth(doc, 690);

		const zoom = parseFloat(content.style.getPropertyValue('zoom'));
		assert.isBelow(zoom, 0.45);
		assert.isAbove(zoom, 0.4);
		assert.equal(doc.documentElement.style.width, '', 'measured with the printable width only temporarily');
		assert.isFalse(doc.documentElement.classList.contains('mailPrintMeasure'));
		reset();
		assert.equal(content.style.getPropertyValue('zoom'), '');
	});

	it('leaves content fitting the printable width alone, also when it is wider on screen', () =>
	{
		const doc = bodyDocument('<p>' + 'some text '.repeat(200) + '</p>');

		fitToPrintWidth(doc, 690);

		assert.equal(doc.querySelector<HTMLElement>('.mailDisplayBody').style.getPropertyValue('zoom'), '');
	});
});

describe('imagesLoaded()', () =>
{
	it('waits for images still loading, but not longer than the timeout', async() =>
	{
		const doc = document.implementation.createHTMLDocument('');
		const img = doc.createElement('img');
		Object.defineProperty(img, 'complete', {value: false});
		doc.body.append(img);

		let done = false;
		const loaded = imagesLoaded(doc, 100).then(() => done = true);
		await new Promise((resolve) => setTimeout(resolve, 20));
		assert.isFalse(done);
		img.dispatchEvent(new Event('load'));
		await loaded;

		Object.defineProperty(doc.body.appendChild(doc.createElement('img')), 'complete', {value: false});
		const start = Date.now();
		await imagesLoaded(doc, 100);
		assert.isAtLeast(Date.now() - start, 90, 'a stuck image does not block printing forever');
	});
});

describe('addPrintShortcut()', () =>
{
	let iframe: HTMLIFrameElement;
	let doc: Document;

	beforeEach(() =>
	{
		// a document of its own for each test, as listeners can not be removed again
		iframe = document.createElement('iframe');
		document.body.append(iframe);
		doc = iframe.contentDocument;
	});

	afterEach(() => iframe.remove());

	function press(init: KeyboardEventInit, target: Document = doc): KeyboardEvent
	{
		const event = new KeyboardEvent('keydown', {cancelable: true, bubbles: true, ...init});
		target.body.dispatchEvent(event);
		return event;
	}

	it('prints on ctrl+P or cmd+P instead of the browser', () =>
	{
		let printed = 0;
		addPrintShortcut(doc, () => ++printed > 0);

		assert.isTrue(press({key: 'p', ctrlKey: true}).defaultPrevented);
		assert.isTrue(press({key: 'p', metaKey: true}).defaultPrevented);
		assert.isFalse(press({key: 'p'}).defaultPrevented);
		assert.isFalse(press({key: 'p', ctrlKey: true, shiftKey: true}).defaultPrevented);
		assert.equal(printed, 2);
	});

	it('leaves ctrl+P to the browser, if there is nothing to print', () =>
	{
		let other = 0;
		doc.addEventListener('keydown', () => other++);
		addPrintShortcut(doc, () => false);

		assert.isFalse(press({key: 'p', ctrlKey: true}).defaultPrevented);
		assert.equal(other, 1);
	});

	it('adds its listener only once per document', () =>
	{
		let printed = 0;
		addPrintShortcut(doc, () => ++printed > 0);
		addPrintShortcut(doc, () => ++printed > 0);

		press({key: 'p', ctrlKey: true});
		assert.equal(printed, 1);
	});

	/** the iframe's contentWindow stays the same object, but the next message is a new document */
	it('works again for the next document loaded into the same iframe', async() =>
	{
		let printed = 0;
		addPrintShortcut(iframe.contentDocument, () => ++printed > 0);
		await new Promise((resolve) =>
		{
			iframe.addEventListener('load', resolve, {once: true});
			iframe.srcdoc = '<p>next message</p>';
		});
		addPrintShortcut(iframe.contentDocument, () => ++printed > 0);

		press({key: 'p', ctrlKey: true}, iframe.contentDocument);
		assert.equal(printed, 1);
	});
});
