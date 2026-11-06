/**
 * Print a message by printing its body iframe on its own (MailApp.printMessage())
 *
 * The body document keeps its own CSP while printing, so nothing it blocks gets loaded, and the
 * message's styles stay in it. Copying the body into the page for printing (the former
 * #tempPrintDiv of the mail.display popup) did neither: the message's `<style>` blocks restyled
 * the whole popup (eg. a newsletter's `body { background-color }`), and the copy loaded content
 * the body's CSP had blocked, eg. a style block's `@import` of Google Fonts.
 *
 * The headers of the message are not part of the body document, so a plain header block is
 * added to it for printing only (styled by preview.less).
 */

/** label and value of a header row, eg. ['From', 'Ralf <rb@example.org>'] */
export type PrintHeaderRow = [string, string];

/**
 * Header rows of a message, from the row data MailApp.renderMessageInto() displays
 *
 * @param data row data, eg. egw.dataGetUIDdata(rowId).data
 * @param lang translation function
 * @param formatDate formats the message's date as the user prefers
 */
export function printHeaderRows(data: any, lang: (phrase: string) => string, formatDate: (date: Date) => string): PrintHeaderRow[]
{
	const addresses = (list: any) => (Array.isArray(list) ? list : (list ? [list] : []))
		.filter((address) => typeof address === 'string' && address).join(', ');
	const date = data?.date ? new Date(data.date) : null;
	const attachments = (Array.isArray(data?.attachmentsBlock) ? data.attachmentsBlock : [])
		.map((attachment) => attachment?.filename).filter(Boolean).join(', ');

	return ([
		[lang('from'), addresses(data?.additionalfromaddress ?? data?.fromaddress)],
		[lang('to'), addresses(data?.additionaltoaddress ?? data?.toaddress)],
		[lang('cc'), addresses(data?.ccaddress)],
		[lang('bcc'), addresses(data?.bccaddress)],
		[lang('date'), date && !isNaN(date.getTime()) ? formatDate(date) : ''],
		[lang('attachments'), attachments],
	] as PrintHeaderRow[]).filter(([, value]) => value);
}

/**
 * Wait for the images of a document still loading or decoding, eg. inline cid: images resolved
 * to blob: urls only after the body rendered - so they are not missing from the printout
 *
 * A broken image counts as done, and one stuck image must not block printing forever.
 *
 * @param doc
 * @param timeout ms to wait at most
 */
export function imagesLoaded(doc: Document, timeout: number = 5000): Promise<void>
{
	const pending = Array.from(doc.images).filter((img) => !img.complete);
	if (!pending.length)
	{
		return Promise.resolve();
	}
	return Promise.race([
		Promise.all(pending.map((img) => new Promise<void>((resolve) =>
		{
			img.addEventListener('load', () => resolve(), {once: true});
			img.addEventListener('error', () => resolve(), {once: true});
		}))).then(() => undefined),
		new Promise<void>((resolve) => setTimeout(resolve, timeout)),
	]);
}

/**
 * Print the body document of an iframe, with a header block on top
 *
 * @param iframe body iframe
 * @param subject
 * @param rows printHeaderRows()
 * @param afterPrint called once the print dialog closed, eg. to restore what was changed for printing
 */
export function printBodyWithHeader(iframe: HTMLIFrameElement, subject: string, rows: PrintHeaderRow[],
	afterPrint?: () => void): void
{
	const doc = iframe.contentDocument;
	const win = iframe.contentWindow;

	doc.querySelector('.mailPrintHeader')?.remove();
	const header = doc.createElement('div');
	// the user's default font, as the body's own text without a font of its own (MailJmap.wrapDocument())
	header.className = 'mailPrintHeader mailDefaultFont';
	if (subject)
	{
		const subjectDiv = doc.createElement('div');
		subjectDiv.className = 'mailPrintHeader_subject';
		subjectDiv.textContent = subject;
		header.append(subjectDiv);
	}
	const table = doc.createElement('table');
	for (const [label, value] of rows)
	{
		const row = table.insertRow();
		row.insertCell().textContent = label + ':';
		row.insertCell().textContent = value;
	}
	header.append(table);
	doc.body.prepend(header);
	const resetZoom = fitToPrintWidth(doc);

	// print() returns before the dialog closes in some browsers
	win.addEventListener('afterprint', () =>
	{
		header.remove();
		resetZoom();
		afterPrint?.();
	}, {once: true});
	win.print();
}

/**
 * Printable width in CSS px the mail gets fitted into: A4 is 794px wide, minus Firefox's default
 * margins of 12.7mm (Chrome's are 10mm, Letter is wider) that leaves 698px
 */
export const PRINT_WIDTH_PX = 690;

/**
 * Scale the mail down to the printable width, if its content is wider, eg. a 1600px wide table
 *
 * Measured with the print rules for wide content (preview.less's .mailPrintFit()), which make
 * images, long words and lines fit already. Only the mail's own content is scaled, not the
 * header block. Measuring happens synchronously, so the screen never shows the measuring width.
 *
 * @param doc body document
 * @param width printable width in CSS px
 * @return callback to reset the zoom again
 */
export function fitToPrintWidth(doc: Document, width: number = PRINT_WIDTH_PX): () => void
{
	const content = doc.querySelector<HTMLElement>('.mailDisplayBody') ?? doc.body;
	const html = doc.documentElement;
	const htmlWidth = html.style.width;
	html.classList.add('mailPrintMeasure');
	html.style.width = width+'px';
	const needed = content.scrollWidth;
	const available = content.clientWidth;
	html.classList.remove('mailPrintMeasure');
	html.style.width = htmlWidth;

	if (!(available > 0) || needed <= available + 1)
	{
		return () => {};
	}
	const zoom = content.style.getPropertyValue('zoom');
	content.style.setProperty('zoom', String(available / needed));
	return () => { content.style.setProperty('zoom', zoom); };
}

/** documents addPrintShortcut() already added its listener to */
const printShortcutDocuments = new WeakSet<Document>();

/**
 * Print the displayed message on ctrl+P or cmd+P, instead of the whole page
 *
 * Needed for the page AND each document loaded into the body iframe, as a key pressed in the body
 * does not reach its parent - per document, not per window: an iframe's contentWindow stays the
 * same object, while the listeners of its previous document are gone.
 * Listens in the capture phase and stops the event, when it printed, as the framework prints the
 * whole application on ctrl+P too (EgwFramework.handleKeydown(), a bubble listener of the page).
 * The browser's own print menu still prints the page, which hides the body iframe for printing
 * (app.less) - there is no way to redirect that.
 *
 * @param doc document of the page, or of the body iframe
 * @param print returning false, if it did not print, to leave the key to the framework or browser
 */
export function addPrintShortcut(doc: Document, print: (event: KeyboardEvent) => boolean): void
{
	if (!doc || printShortcutDocuments.has(doc))
	{
		return;
	}
	printShortcutDocuments.add(doc);
	doc.addEventListener('keydown', (event: KeyboardEvent) =>
	{
		if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey &&
			event.key?.toLowerCase() === 'p' && print(event))
		{
			event.preventDefault();
			event.stopPropagation();
		}
	}, {capture: true});
}
