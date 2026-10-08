import {assert} from "@open-wc/testing";
import {extractPdfText, pdfItemsToText, renderPdfPages} from "../pdf-text";

// Stub global egw - pdf-player.ts's ensureWorkerSrc() reads egw.webserverUrl to fetch() the real
// pdf.worker.mjs, see pdf-player.test.ts for the details. Every load goes through a REAL Worker.
// @ts-ignore
const egw = {
	message: () => {},
	webserverUrl: ""
};
window.egw = function() {return egw};
Object.assign(window.egw, egw);

/**
 * Builds a minimal PDF with the given content stream, no xref table: pdf.js recovers it by scanning for "N G obj"
 */
function pdfWithContent(content : string) : Uint8Array
{
	const pdf = `%PDF-1.1\n` +
		`1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n` +
		`2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n` +
		`3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 200]/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>endobj\n` +
		`4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n` +
		`5 0 obj<</Length ${content.length}>>stream\n${content}\nendstream endobj\n` +
		`trailer<</Root 1 0 R>>\n`;
	return new TextEncoder().encode(pdf);
}

/** a text item as pdf.js returns it */
const item = (str : string, x : number, y : number, width = 50, height = 12) => ({str, transform: [height, 0, 0, height, x, y], width, height});

describe("pdfItemsToText", () =>
{
	it("sorts lines top to bottom and items left to right, independent of the order in the PDF", () =>
	{
		const text = pdfItemsToText([
			item("Summe", 20, 100),
			item("Rechnung", 20, 150),
			item("4711", 80, 150, 30),
		]);
		assert.equal(text, "Rechnung 4711\nSumme");
	});

	it("separates a wide gap (eg. an amount column) by a tab and joins adjacent items without space", () =>
	{
		const text = pdfItemsToText([
			item("Lizenz", 20, 100, 40),
			item("EUR 10,00", 250, 100, 60),
			item("12", 20, 80, 12),
			item("34", 32, 80, 12),		// directly behind "12"
		]);
		assert.equal(text, "Lizenz\tEUR 10,00\n1234");
	});

	it("tolerates a slightly different baseline (sub- or superscript) on the same line", () =>
	{
		assert.equal(pdfItemsToText([item("a", 20, 100, 8), item("b", 30, 102, 8)]), "a b");
	});

	it("ignores the items with just a space pdf.js adds between separately drawn texts, they must not hide a gap", () =>
	{
		assert.equal(pdfItemsToText([
			item("Rechnung 4711", 20, 150, 85),
			item(" ", 105, 150, 95, 0),
			item("EUR 10,00", 200, 150, 59),
		]), "Rechnung 4711\tEUR 10,00");
	});

	it("ignores empty items and markers without text", () =>
	{
		assert.equal(pdfItemsToText(<any>[{type: "beginMarkedContent"}, item("", 20, 100), item("x", 20, 90)]), "x");
	});

	it("returns an empty string for no items", () =>
	{
		assert.equal(pdfItemsToText([]), "");
	});
});

describe("extractPdfText", () =>
{
	it("reads the text of a PDF with a text layer", async() =>
	{
		const data = pdfWithContent(
			"BT /F1 12 Tf 20 120 Td (Summe) Tj ET\n" +	// lower line first
			"BT /F1 12 Tf 20 150 Td (Rechnung 4711) Tj ET\n" +
			"BT /F1 12 Tf 200 150 Td (EUR 10,00) Tj ET");
		const result = await extractPdfText(data);
		assert.equal(result.text, "Rechnung 4711\tEUR 10,00\nSumme");
		assert.equal(result.numPages, 1);
		assert.deepEqual(result.pages, [result.text]);
		assert.equal(result.chars, "Rechnung4711EUR10,00Summe".length);
	});

	it("does NOT consume the data, so it can be used again (eg. to render the pages)", async() =>
	{
		const data = pdfWithContent("BT /F1 12 Tf 20 150 Td (Text) Tj ET");
		const length = data.byteLength;
		await extractPdfText(data);
		assert.equal(data.byteLength, length, "pdf.js transferred (detached) our array");
		assert.equal((await extractPdfText(data)).text, "Text");
	});

	it("finds no text in a PDF without a text layer (scan or paths)", async() =>
	{
		const result = await extractPdfText(pdfWithContent("0 0 m 100 100 l S"));
		assert.equal(result.text, "");
		assert.equal(result.chars, 0);
		assert.equal(result.numPages, 1);
	});

	it("rejects for something which is no PDF", async() =>
	{
		let error = null;
		try
		{
			await extractPdfText(new TextEncoder().encode("this is not a PDF"));
		}
		catch(e)
		{
			error = e;
		}
		assert.isOk(error, "should have thrown");
	});
});

describe("renderPdfPages", () =>
{
	it("renders each page to a canvas of the size of the page times the scale", async() =>
	{
		const data = pdfWithContent("BT /F1 12 Tf 20 150 Td (Text) Tj ET");
		const canvases : HTMLCanvasElement[] = [];
		const numPages = await renderPdfPages(data, (canvas, n) =>
		{
			assert.equal(n, canvases.length + 1);
			canvases.push(canvas);
		}, 2);
		assert.equal(numPages, 1);
		assert.equal(canvases.length, 1);
		assert.equal(canvases[0].width, 600);	// MediaBox is 300 x 200
		assert.equal(canvases[0].height, 400);
	});
});
