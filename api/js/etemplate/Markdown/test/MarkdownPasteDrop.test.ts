/**
 * Test file for pasting and dropping a file into a markdown field
 *
 * Contract under test: a screenshot on the clipboard, pasted into the source, is uploaded through
 * the same endpoint the attach button uses and embedded at the caret - the gesture the html editor
 * has always had, since Et2HtmlArea hands TinyMCE paste_data_images and the same upload URL.  A
 * drop does the same for any file, at the point it was dropped on.  Neither may fire when there is
 * nothing on the clipboard but text, or when the field cannot attach at all.
 *
 * Setup: real widgets in a fixture with the usual minimal egw() stub, and fetch() stubbed to answer
 * the way the endpoint does ({location}).  Nothing is uploaded and no server is involved.
 *
 * Pass criteria: what ends up in the widget's value, what was sent, and whether the event's default
 * was suppressed - a paste that preventDefault()s without uploading would silently eat text.
 */
import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import type {Et2Textarea} from "../../Et2Textarea/Et2Textarea";
import "../../Et2Textarea/Et2Textarea";

window.egw = {
	lang: (label : string, ...args : any[]) => args.length ? `${label} ${args.join(" ")}` : label,
	preference: () => undefined,
	set_preference: () => {},
	webserverUrl: "/egroupware",
	image: () => "",
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	ajaxUrl: (menuaction : string) => "/egroupware/json.php?menuaction=" + menuaction,
	message: () => {},
	uid: () => "uid"
} as any;

type Editor = Et2Textarea & { value : string };

/** every request the stubbed fetch() saw, newest last */
let requests : { url : string, body : FormData }[] = [];
let realFetch : typeof fetch;

beforeEach(() =>
{
	requests = [];
	realFetch = window.fetch;
	window.fetch = <any>((url : string, init : any) =>
	{
		const n = requests.length;
		requests.push({url, body: init.body});
		return Promise.resolve(<any>{
			json: () => Promise.resolve({location: `/egroupware/webdav.php/apps/tracker/42/up-${n}.png`})
		});
	});
});

afterEach(() => {window.fetch = realFetch;});

async function editor(canAttachFile = true, value = "see ") : Promise<Editor>
{
	const el : Editor = await fixture(html`
        <et2-textarea markdown markdown-mode="edit" ?can-attach-file=${canAttachFile}
                      .value=${value}></et2-textarea>`);
	await elementUpdated(el);
	el.requestUpdate();
	await elementUpdated(el);
	return el;
}

function sourcePane(el : Editor) { return el.shadowRoot.querySelector(".markdown-shell__source"); }

function caretAt(el : Editor, at : number)
{
	el.shadowRoot.querySelector("textarea").setSelectionRange(at, at);
}

/** a DataTransfer carrying these files, the way a real paste or drag does */
function transfer(files : File[]) : DataTransfer
{
	const data = new DataTransfer();
	files.forEach(file => data.items.add(file));
	return data;
}

/** the name the nth upload actually sent, which is not always the name of the File handed in */
function sentName(n : number) : string
{
	return (<File>requests[n].body.get("file")).name;
}

/**
 * Firefox accepts `clipboardData` in the ClipboardEvent constructor and then ignores it, so the
 * event arrives carrying nothing.  Defining the property afterwards works in both browsers.
 */
function withData<T extends Event>(event : T, property : string, data : DataTransfer) : T
{
	Object.defineProperty(event, property, {value: data, configurable: true});
	return event;
}

async function settle(el : Editor)
{
	await new Promise(resolve => setTimeout(resolve, 0));
	await elementUpdated(el);
}

async function paste(el : Editor, files : File[]) : Promise<ClipboardEvent>
{
	const event = withData(new ClipboardEvent("paste", {bubbles: true, cancelable: true, composed: true}),
		"clipboardData", transfer(files));
	sourcePane(el).dispatchEvent(event);
	await settle(el);
	return event;
}

async function drop(el : Editor, files : File[]) : Promise<DragEvent>
{
	const event = withData(new DragEvent("drop", {bubbles: true, cancelable: true, composed: true}),
		"dataTransfer", transfer(files));
	sourcePane(el).dispatchEvent(event);
	await settle(el);
	return event;
}

/**
 * Where the browser says a point lands in the text.
 *
 * Stubbed rather than dispatched at real coordinates: a synthetic drop has no coordinates worth
 * the name (Chromium hit-tests (0,0) into the fixture's own textarea and lands on offset 1), and
 * what is under test is that we ASK, not that the browser can hit-test.  `null` is the case where
 * it cannot answer, which is the fallback-to-the-caret path.
 */
function caretFromPoint(el : Editor, offset : number | null)
{
	const real = (<any>document).caretPositionFromPoint;
	(<any>document).caretPositionFromPoint = () => offset === null ? null :
		{offsetNode: el.shadowRoot.querySelector("textarea"), offset};
	return () => {(<any>document).caretPositionFromPoint = real;};
}

const png = (name = "shot.png") => new File(["x"], name, {type: "image/png"});
const pdf = (name = "spec.pdf") => new File(["x"], name, {type: "application/pdf"});

describe("markdown paste", () =>
{
	it("uploads a pasted image and embeds it at the caret", async() =>
	{
		const el = await editor();
		caretAt(el, 4);

		const event = await paste(el, [png()]);

		assert.equal(requests.length, 1, "one upload");
		assert.isTrue(event.defaultPrevented, "the browser must not also paste it");
		assert.equal(el.value, "see ![shot.png](/egroupware/webdav.php/apps/tracker/42/up-0.png)");
	});

	it("leaves a text paste completely alone", async() =>
	{
		const el = await editor();

		const event = await paste(el, []);

		assert.equal(requests.length, 0, "nothing uploaded");
		assert.isFalse(event.defaultPrevented, "or the pasted text would be lost");
		assert.equal(el.value, "see ");
	});

	it("ignores a non-image on the clipboard", async() =>
	{
		const el = await editor();

		const event = await paste(el, [pdf()]);

		assert.equal(requests.length, 0, "a copied document is not a screenshot");
		assert.isFalse(event.defaultPrevented);
	});

	it("does nothing when the field cannot attach", async() =>
	{
		const el = await editor(false);

		const event = await paste(el, [png()]);

		assert.equal(requests.length, 0);
		assert.isFalse(event.defaultPrevented);
		assert.equal(el.value, "see ");
	});

	/**
	 * Every browser calls a pasted screenshot "image.png".  The store overwrites, so without a
	 * name of its own the second paste would replace the first file and both links would point at
	 * whichever won.
	 */
	it("gives a pasted screenshot a name of its own", async() =>
	{
		const el = await editor();

		await paste(el, [png("image.png")]);
		await paste(el, [png("image.png")]);

		assert.notEqual(sentName(0), "image.png", "not the browser's placeholder name");
		assert.notEqual(sentName(0), sentName(1), "two screenshots are two files");
		assert.match(sentName(0), /^image-\d{8}\d{6}-[a-z0-9]+\.png$/);
	});

	it("keeps the name of a file copied out of a file manager", async() =>
	{
		const el = await editor();

		await paste(el, [png("holiday.png")]);

		assert.equal(sentName(0), "holiday.png");
	});
});

describe("markdown drop", () =>
{
	it("uploads a dropped image and embeds it", async() =>
	{
		const el = await editor();
		caretAt(el, 4);
		const restore = caretFromPoint(el, null);

		const event = await drop(el, [png()]);
		restore();

		assert.equal(requests.length, 1);
		assert.isTrue(event.defaultPrevented, "or the browser navigates to the file");
		assert.equal(el.value, "see ![shot.png](/egroupware/webdav.php/apps/tracker/42/up-0.png)");
	});

	/**
	 * A drop carries its own position, and it is not where the caret happened to be - dropping a
	 * file at the end of a paragraph must not insert it wherever you were last typing
	 */
	it("inserts at the point it was dropped on, not at the caret", async() =>
	{
		const el = await editor(true, "see  here");
		caretAt(el, 0);
		const restore = caretFromPoint(el, 4);

		await drop(el, [png()]);
		restore();

		assert.equal(el.value, "see ![shot.png](/egroupware/webdav.php/apps/tracker/42/up-0.png) here");
	});

	/**
	 * Unlike a paste, a drop is deliberate enough to accept any file - it just becomes a link
	 * rather than an embed
	 */
	it("links a dropped document instead of embedding it", async() =>
	{
		const el = await editor();
		caretAt(el, 4);
		const restore = caretFromPoint(el, null);

		await drop(el, [pdf()]);
		restore();

		assert.equal(el.value, "see [spec.pdf](/egroupware/webdav.php/apps/tracker/42/up-0.png)");
	});

	it("does nothing when the field cannot attach", async() =>
	{
		const el = await editor(false);

		const event = await drop(el, [png()]);

		assert.equal(requests.length, 0);
		assert.isFalse(event.defaultPrevented);
	});

	it("claims a file drag so the browser delivers the drop", async() =>
	{
		const el = await editor();

		const over = new DragEvent("dragover", {
			bubbles: true, cancelable: true, composed: true, dataTransfer: transfer([png()])
		});
		sourcePane(el).dispatchEvent(over);

		assert.isTrue(over.defaultPrevented, "without this the browser never fires drop");
	});

	it("leaves a drag that carries no file to whoever else wants it", async() =>
	{
		const el = await editor();

		const over = new DragEvent("dragover", {
			bubbles: true, cancelable: true, composed: true, dataTransfer: new DataTransfer()
		});
		sourcePane(el).dispatchEvent(over);

		assert.isFalse(over.defaultPrevented);
	});
});
