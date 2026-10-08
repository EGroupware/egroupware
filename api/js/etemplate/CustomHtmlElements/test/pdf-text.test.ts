import {assert} from "@open-wc/testing";
import {EINVOICE_FORMATS, embeddedXmlFiles, extractPdfText, findEInvoiceXml, joinPageTexts, pagesWithoutText, pdfItemsToText, renderPdfPages} from "../pdf-text";

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

/**
 * Builds a minimal PDF with the given number of (empty) pages, see pdfWithContent()
 */
function pdfWithPages(pageCount : number) : Uint8Array
{
	const kids = [];
	let pages = "";
	for(let i = 0; i < pageCount; i++)
	{
		kids.push(`${3 + i} 0 R`);
		pages += `${3 + i} 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 100 100]>>endobj\n`;
	}
	return new TextEncoder().encode(`%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n` +
		`2 0 obj<</Type/Pages/Kids[${kids.join(" ")}]/Count ${pageCount}>>endobj\n${pages}trailer<</Root 1 0 R>>\n`);
}

/**
 * Builds a minimal PDF with the given files embedded, like the hybrid PDFs of ZUGFeRD / Factur-X do
 */
function pdfWithAttachments(files : {[name : string] : string}) : Uint8Array
{
	let names = "";
	let objects = "";
	let n = 6;
	for(const [name, content] of Object.entries(files))
	{
		names += `(${name}) ${n} 0 R `;
		objects += `${n} 0 obj<</Type/Filespec/F(${name})/UF(${name})/EF<</F ${n + 1} 0 R>>>>endobj\n` +
			`${n + 1} 0 obj<</Type/EmbeddedFile/Length ${content.length}>>stream\n${content}\nendstream endobj\n`;
		n += 2;
	}
	return new TextEncoder().encode(`%PDF-1.7\n` +
		`1 0 obj<</Type/Catalog/Pages 2 0 R/Names<</EmbeddedFiles<</Names[${names}]>>>>>>endobj\n` +
		`2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n` +
		`3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 100 100]>>endobj\n` + objects + `trailer<</Root 1 0 R>>\n`);
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

	it("renders to a fixed width, if asked for it", async() =>
	{
		const data = pdfWithContent("BT /F1 12 Tf 20 150 Td (Text) Tj ET");
		let canvas : HTMLCanvasElement = null;
		await renderPdfPages(data, c => {canvas = c;}, {width: 1200});
		assert.equal(canvas.width, 1200);
		assert.equal(canvas.height, 800);	// MediaBox is 300 x 200
	});

	it("renders only the requested pages and stops at maxPages", async() =>
	{
		const data = pdfWithPages(3);
		const rendered : number[] = [];
		const numPages = await renderPdfPages(data, (canvas, n) => {rendered.push(n);}, 1, 20, [1, 3]);
		assert.equal(numPages, 3);
		assert.deepEqual(rendered, [1, 3]);

		const first : number[] = [];
		await renderPdfPages(data, (canvas, n) => {first.push(n);}, 1, 2);
		assert.deepEqual(first, [1, 2]);
	});

	it("waits for the promise returned by onPage before rendering the next page", async() =>
	{
		const data = pdfWithPages(2);
		const events : string[] = [];
		await renderPdfPages(data, async(canvas, n) =>
		{
			events.push("start " + n);
			await new Promise(resolve => setTimeout(resolve, 30));
			events.push("end " + n);
		}, 1);
		assert.deepEqual(events, ["start 1", "end 1", "start 2", "end 2"]);
	});
});

describe("pagesWithoutText / joinPageTexts", () =>
{
	const text = "Rechnung 4711 über EUR 10,00 inklusive Steuer";

	it("finds the pages without a (real) text layer", () =>
	{
		assert.deepEqual(pagesWithoutText([text, "", text, "  Seite 3/4 \n"]), [2, 4]);
		assert.deepEqual(pagesWithoutText([text, text]), []);
		assert.deepEqual(pagesWithoutText(["", ""]), [1, 2]);
		assert.deepEqual(pagesWithoutText([]), []);
	});

	it("takes the minimum number of characters into account, not counting whitespace", () =>
	{
		assert.deepEqual(pagesWithoutText(["a b c d e f g h i j"], 10), []);	// 10 characters
		assert.deepEqual(pagesWithoutText(["a b c d e f g h i"], 10), [1]);		// 9 characters
	});

	it("uses OCR-ed text only for the pages without text, in the order of the pages", () =>
	{
		assert.equal(joinPageTexts([text, "", "Page 3 text"], {2: "OCR of page 2"}), `${text}\n\nOCR of page 2\n\nPage 3 text`);
	});

	it("keeps the own text of a page whose OCR failed and skips pages without any text", () =>
	{
		assert.equal(joinPageTexts([text, "", "  "], {}), text);
		assert.equal(joinPageTexts(["", ""], {}), "");
	});

	it("prefers the OCR text of a page, even if the page has some text (eg. just a page number)", () =>
	{
		assert.equal(joinPageTexts(["Seite 1/2", "Seite 2/2"], {1: "Invoice", 2: "Total"}), "Invoice\n\nTotal");
	});
});

describe("embeddedXmlFiles", () =>
{
	it("finds the XML of an e-invoice (ZUGFeRD, Factur-X, XRechnung)", async() =>
	{
		assert.deepEqual(await embeddedXmlFiles(pdfWithAttachments({"factur-x.xml": "<rsm:CrossIndustryInvoice/>"})), ["factur-x.xml"]);
		assert.deepEqual(await embeddedXmlFiles(pdfWithAttachments({"ZUGFeRD-invoice.XML": "<x/>"})), ["ZUGFeRD-invoice.XML"]);
	});

	it("finds only the XML files, if there are other attachments too", async() =>
	{
		assert.deepEqual(await embeddedXmlFiles(pdfWithAttachments({"terms.txt": "hello", "xrechnung.xml": "<x/>"})), ["xrechnung.xml"]);
	});

	it("finds nothing in a PDF with other attachments or without any", async() =>
	{
		assert.deepEqual(await embeddedXmlFiles(pdfWithAttachments({"terms.txt": "hello"})), []);
		assert.deepEqual(await embeddedXmlFiles(pdfWithPages(1)), []);
	});

	it("does NOT consume the data and returns an empty list for something which is no PDF", async() =>
	{
		const data = pdfWithAttachments({"factur-x.xml": "<x/>"});
		const length = data.byteLength;
		await embeddedXmlFiles(data);
		assert.equal(data.byteLength, length);
		assert.deepEqual(await embeddedXmlFiles(new TextEncoder().encode("no pdf")).catch(() => []), []);
	});
});

describe("findEInvoiceXml", () =>
{
	it("finds the XML of ZUGFeRD 1.0 and 2.x, Factur-X and XRechnung and knows their format", async() =>
	{
		const expected = {
			"ZUGFeRD-invoice.xml": "ZUGFeRD 1.0",
			"zugferd-invoice.xml": "ZUGFeRD 2.0",
			"factur-x.xml": "Factur-X / ZUGFeRD 2.1",
			"xrechnung.xml": "XRechnung"
		};
		assert.deepEqual(EINVOICE_FORMATS, expected);
		for(const name of Object.keys(expected))
		{
			assert.equal(await findEInvoiceXml(pdfWithAttachments({[name]: "<x/>"})), name);
		}
	});

	it("finds the e-invoice among other attachments", async() =>
	{
		assert.equal(await findEInvoiceXml(pdfWithAttachments({"terms.txt": "hello", "other.xml": "<x/>", "factur-x.xml": "<x/>"})), "factur-x.xml");
	});

	it("is NOT fooled by any other XML or by a name in a different case, like the PHP reader", async() =>
	{
		assert.isNull(await findEInvoiceXml(pdfWithAttachments({"metadata.xml": "<x/>"})));
		assert.isNull(await findEInvoiceXml(pdfWithAttachments({"FACTUR-X.XML": "<x/>"})));
		assert.isNull(await findEInvoiceXml(pdfWithAttachments({"toString": "<x/>"})), "no prototype properties");
		assert.isNull(await findEInvoiceXml(pdfWithAttachments({"terms.txt": "hello"})));
		assert.isNull(await findEInvoiceXml(pdfWithPages(1)));
	});
});
