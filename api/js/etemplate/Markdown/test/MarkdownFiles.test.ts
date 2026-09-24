/**
 * Test file for the markdown format popup's attach-file button
 *
 * Contract under test: when the server says the entry is saved, the popup offers a paperclip;
 * choosing a file POSTs it to Vfs::ajax_htmlarea_upload - the same endpoint TinyMCE sends a
 * dragged-in image to - and writes the URL that comes back into the source at the caret the user
 * had when they clicked.  When the server does not say so, there is no button: an upload for an
 * unsaved entry parks in a temp directory, and the link would dangle after the first save.
 *
 * Setup: real widgets in a fixture with the usual minimal egw() stub, and fetch() stubbed to
 * answer the way the endpoint does ({location}).  Nothing is uploaded and no server is involved.
 *
 * Pass criteria: what ends up in the widget's value, and what was sent to the endpoint.  A
 * failure here is a logic bug in the mixin.
 */
import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import type {Et2Textarea} from "../../Et2Textarea/Et2Textarea";
import "../../Et2Textarea/Et2Textarea";

window.egw = {
	lang: (label: string, ...args: any[]) => args.length ? `${label} ${args.join(" ")}` : label,
	preference: () => undefined,
	set_preference: () => {},
	webserverUrl: "/egroupware",
	image: () => "",
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	ajaxUrl: (menuaction: string) => "/egroupware/json.php?menuaction=" + menuaction,
	message: () => {},
	uid: () => "uid"
} as any;

/**
 * `value` is protected on Et2InputWidget, for widgets - a test is allowed to look at what the
 * editor ended up holding, which is the whole point here
 */
type Editor = Et2Textarea & { value: string };

/** every request the stubbed fetch() saw, newest last */
let requests: { url: string, body: FormData }[] = [];
/** what the next upload should answer with; a function gets the request number (0-based) */
let answer: any = null;
let realFetch: typeof fetch;

beforeEach(() =>
{
	requests = [];
	answer = (n: number) => ({location: `/egroupware/webdav.php/apps/tracker/42/file-${n}.png`});
	realFetch = window.fetch;
	window.fetch = <any>((url: string, init: any) =>
	{
		const n = requests.length;
		requests.push({url, body: init.body});
		const body = typeof answer === "function" ? answer(n) : answer;
		return Promise.resolve(<any>{json: () => Promise.resolve(body)});
	});
});

afterEach(() => {window.fetch = realFetch;});

/** A markdown textarea in edit view, optionally allowed to attach files */
async function editor(canAttachFile = false, value = "see "): Promise<Editor>
{
	const el: Editor = await fixture(html`
        <et2-textarea markdown markdown-mode="edit" ?can-attach-file=${canAttachFile}
                      .value=${value}></et2-textarea>`);
	await elementUpdated(el);

	// The format popup is built around the source textarea, which only exists once the first
	// render is done - so the bar, and the button in it, appear from the second render on.  In
	// the browser that is the update the selection itself triggers.
	el.requestUpdate();
	await elementUpdated(el);
	return el;
}

function attachButton(el: Editor) { return el.shadowRoot.querySelector(".markdown-popup__attach"); }

function fileInput(el: Editor) { return el.shadowRoot.querySelector<HTMLInputElement>("input.markdown-popup__file"); }

/**
 * Click the paperclip and choose files, the way the browser's file chooser would.
 *
 * The mousedown is what snapshots the caret, before the chooser takes the focus away - a test
 * that skips it is not testing what ships.
 */
async function chooseFiles(el: Editor, files: File[])
{
	attachButton(el).dispatchEvent(new MouseEvent("mousedown", {bubbles: true, composed: true}));

	const input = fileInput(el);
	Object.defineProperty(input, "files", {value: files, configurable: true});
	input.dispatchEvent(new Event("change", {bubbles: true}));

	// let the upload promise chain settle
	await new Promise(resolve => setTimeout(resolve, 0));
	await elementUpdated(el);
}

const png = (name = "shot.png") => new File(["x"], name, {type: "image/png"});
const pdf = (name = "spec.pdf") => new File(["x"], name, {type: "application/pdf"});

describe("markdown attach button - when it exists", () =>
{
	it("is not offered until the server says the entry is saved", async() =>
	{
		const el = await editor(false);

		assert.isFalse(!!attachButton(el), "no attach button");
		assert.isFalse(!!fileInput(el), "no file input either");
	});

	it("is offered once the server says so", async() =>
	{
		const el = await editor(true);

		assert.isTrue(!!attachButton(el), "attach button");
	});

	it("defaults the upload target to the link_to content, like the Links tab", async() =>
	{
		const el = await editor(true);

		assert.equal((<any>el).imageUpload, "link_to");
	});

	it("leaves an upload target the template set alone", async() =>
	{
		const el: Editor = await fixture(html`
            <et2-textarea markdown markdown-mode="edit" can-attach-file image-upload="attachments"
                          .value=${"see "}></et2-textarea>`);
		await elementUpdated(el);

		assert.equal((<any>el).imageUpload, "attachments");
	});
});

describe("markdown attach button - what it sends", () =>
{
	it("posts to the htmlarea upload endpoint, for the named widget", async() =>
	{
		const el = await editor(true);
		await chooseFiles(el, [png()]);

		assert.equal(requests.length, 1, "one request per file");
		const url = requests[0].url;
		assert.include(url, "Vfs::ajax_htmlarea_upload", "the endpoint TinyMCE uses");
		assert.include(url, "widget_id=link_to", "which content names the entry");
		assert.include(url, "type=htmlarea", "the response shape we parse");
		assert.equal(requests[0].body.get("file")["name"], "shot.png", "the file itself");
	});

	it("sends one request per chosen file", async() =>
	{
		const el = await editor(true);
		await chooseFiles(el, [png("a.png"), pdf("b.pdf")]);

		assert.equal(requests.length, 2);
	});
});

describe("markdown attach button - what it inserts", () =>
{
	it("renders an image inline, at the caret", async() =>
	{
		const el = await editor(true);
		el.shadowRoot.querySelector("textarea").setSelectionRange(4, 4);
		answer = {location: "/egroupware/webdav.php/apps/tracker/42/shot.png"};

		await chooseFiles(el, [png()]);

		assert.equal(el.value, "see ![shot.png](/egroupware/webdav.php/apps/tracker/42/shot.png)");
	});

	it("links anything that is not an image", async() =>
	{
		const el = await editor(true);
		el.shadowRoot.querySelector("textarea").setSelectionRange(4, 4);
		answer = {location: "/egroupware/webdav.php/apps/tracker/42/spec.pdf"};

		await chooseFiles(el, [pdf()]);

		assert.equal(el.value, "see [spec.pdf](/egroupware/webdav.php/apps/tracker/42/spec.pdf)");
	});

	it("makes the selection the link text", async() =>
	{
		const el = await editor(true, "see the spec here");
		el.shadowRoot.querySelector("textarea").setSelectionRange(8, 12);
		answer = {location: "/egroupware/webdav.php/apps/tracker/42/spec.pdf"};

		await chooseFiles(el, [pdf()]);

		assert.equal(el.value, "see the [spec](/egroupware/webdav.php/apps/tracker/42/spec.pdf) here");
	});

	it("inserts where the caret was when the button was clicked", async() =>
	{
		const el = await editor(true, "start end");
		const node = el.shadowRoot.querySelector("textarea");
		answer = {location: "/egroupware/webdav.php/apps/tracker/42/a.pdf"};

		node.setSelectionRange(0, 5);
		attachButton(el).dispatchEvent(new MouseEvent("mousedown", {bubbles: true, composed: true}));
		// the user clicks away while the file chooser is open
		node.setSelectionRange(9, 9);

		const input = fileInput(el);
		Object.defineProperty(input, "files", {value: [pdf("a.pdf")], configurable: true});
		input.dispatchEvent(new Event("change", {bubbles: true}));
		await new Promise(resolve => setTimeout(resolve, 0));
		await elementUpdated(el);

		assert.equal(el.value, "[start](/egroupware/webdav.php/apps/tracker/42/a.pdf) end");
	});

	it("keeps several files in the order they were chosen", async() =>
	{
		const el = await editor(true, "");

		await chooseFiles(el, [png("a.png"), png("b.png")]);

		assert.equal(el.value,
			"![a.png](/egroupware/webdav.php/apps/tracker/42/file-0.png)" +
			"![b.png](/egroupware/webdav.php/apps/tracker/42/file-1.png)");
	});

	it("inserts nothing when the server reports an error", async() =>
	{
		const el = await editor(true);
		// the endpoint puts its error in the field it would otherwise put the URL in
		answer = {location: "Could not read session"};

		await chooseFiles(el, [png()]);

		assert.equal(el.value, "see ");
	});

	it("inserts nothing when the server falls back to a data: URL", async() =>
	{
		const el = await editor(true);
		// its "nowhere to store this" answer - a whole image inlined in the text is not wanted
		answer = {location: "data:image/png;base64,eA=="};

		await chooseFiles(el, [png()]);

		assert.equal(el.value, "see ");
	});

	it("survives the upload failing outright", async() =>
	{
		const el = await editor(true);
		window.fetch = <any>(() => Promise.reject(new Error("network")));

		await chooseFiles(el, [png()]);

		assert.equal(el.value, "see ");
	});

	it("tells the entry its attachments changed", async() =>
	{
		const el = await editor(true);
		let changed = 0;
		el.addEventListener("et2-link-changed", () => changed++);

		await chooseFiles(el, [png()]);

		assert.equal(changed, 1, "one et2-link-changed for one attached file");
	});
});
