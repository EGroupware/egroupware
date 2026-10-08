/**
 * EGroupware Api - read the text and render the pages of a PDF in the browser with pdf.js
 *
 * No server side dependency (like Tika or Collabora): works for every PDF with a text layer, and gives the pages of
 * all others (scans, PDFs with just paths) as canvases, eg. to be OCR-ed by the server.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb[at]egroupware.org>
 */

import * as pdfjs from "pdfjs-dist";
import {ensureWorkerSrc} from "./pdf-player";

/**
 * Result of extractPdfText()
 */
export interface PdfText
{
	/** text of all (read) pages separated by an empty line */
	text : string;
	/** text of each (read) page */
	pages : string[];
	/** number of pages of the PDF, which might be more than the (maximum) number of pages read */
	numPages : number;
	/** number of characters without whitespace, a scan or PDF with just paths has none or just a page number */
	chars : number;
}

/**
 * The subset of pdf.js' TextItem we use
 */
export interface PdfTextItem
{
	str? : string;
	/** [scaleX, skewY, skewX, scaleY, x, y], y goes UP in a PDF */
	transform? : number[];
	width? : number;
	height? : number;
}

/**
 * Assemble the text items of one page to lines
 *
 * Items on the same baseline form a line, sorted from left to right, with a space for a small and a tab for a wide
 * gap (eg. before the amount column of an invoice), the lines from top to bottom.
 * Using the order of the items in the PDF would mix up lines, as it is up to the program creating the PDF.
 *
 * @param items TextContent.items of a pdf.js page
 * @return text of the page, lines separated by "\n"
 */
export function pdfItemsToText(items : PdfTextItem[]) : string
{
	type Line = { y : number, items : { x : number, w : number, h : number, str : string }[] };
	const lines : Line[] = [];
	for(const item of items)
	{
		// TextMarkedContent items have no "str", and pdf.js adds items with just a space (as wide as the gap) between
		// separately drawn texts - we calculate the gap from the position of the real items
		if(typeof item.str !== "string" || item.str.trim() === "" || !item.transform)
		{
			continue;
		}
		const x = item.transform[4];
		const y = item.transform[5];
		const h = item.height || Math.abs(item.transform[3]) || 10;
		let line = lines.find(l => Math.abs(l.y - y) < h * 0.5);
		if(!line)
		{
			lines.push(line = {y, items: []});
		}
		line.items.push({x, w: item.width || 0, h, str: item.str});
	}
	// PDF y goes up, so the highest is the first line
	lines.sort((a, b) => b.y - a.y);

	return lines.map(line =>
	{
		line.items.sort((a, b) => a.x - b.x);
		let out = "";
		let end : number = null;
		for(const item of line.items)
		{
			if(end !== null)
			{
				const gap = item.x - end;
				out += gap > item.h * 3 ? "\t" : gap > item.h * 0.15 ? " " : "";
			}
			out += item.str;
			end = item.x + item.w;
		}
		return out.trimEnd();
	}).filter(line => line !== "").join("\n");
}

/**
 * Open a PDF, pdf.js takes over (transfers) the array it gets, so we always hand it a copy
 */
async function openPdf(data : Uint8Array|ArrayBuffer)
{
	await ensureWorkerSrc();
	return pdfjs.getDocument({data: new Uint8Array(data instanceof ArrayBuffer ? data.slice(0) : data.slice())}).promise;
}

/**
 * Extract the text of a PDF
 *
 * @param data content of the PDF, the array is NOT changed or consumed
 * @param maxPages maximum number of pages to read
 * @throws if pdf.js can not read the PDF (eg. a damaged or password protected one)
 */
export async function extractPdfText(data : Uint8Array|ArrayBuffer, maxPages = 20) : Promise<PdfText>
{
	const pdf = await openPdf(data);
	try
	{
		const pages : string[] = [];
		for(let n = 1; n <= Math.min(pdf.numPages, maxPages); n++)
		{
			const page = await pdf.getPage(n);
			pages.push(pdfItemsToText(<PdfTextItem[]>(await page.getTextContent()).items));
			page.cleanup();
		}
		const text = pages.join("\n\n");
		return {text, pages, numPages: pdf.numPages, chars: text.replace(/\s/g, "").length};
	}
	finally
	{
		await pdf.destroy();
	}
}

/**
 * Render the pages of a PDF to canvases
 *
 * @param data content of the PDF, the array is NOT changed or consumed
 * @param onPage called with each rendered page, so it can be shown or converted to an image before the next one is rendered
 * @param scale 1 = 72 dpi, 1.5 is a good size to read on screen, 2 to OCR
 * @param maxPages maximum number of pages to render
 * @return total number of pages of the PDF
 */
export async function renderPdfPages(data : Uint8Array|ArrayBuffer, onPage : (canvas : HTMLCanvasElement, pageNumber : number) => void|Promise<void>,
									 scale = 1.5, maxPages = 20) : Promise<number>
{
	const pdf = await openPdf(data);
	try
	{
		for(let n = 1; n <= Math.min(pdf.numPages, maxPages); n++)
		{
			const page = await pdf.getPage(n);
			const viewport = page.getViewport({scale});
			const canvas = document.createElement("canvas");
			canvas.width = viewport.width;
			canvas.height = viewport.height;
			await page.render({canvas, viewport}).promise;
			await onPage(canvas, n);
			page.cleanup();
		}
		return pdf.numPages;
	}
	finally
	{
		await pdf.destroy();
	}
}
