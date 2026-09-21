import {assert} from "@open-wc/testing";
import {Et2Historylog} from "../Et2Historylog";
import {Et2HistorylogValue, HISTORY_DIFF_MARKER} from "../Et2HistorylogValue";
import {Et2HistorylogWidgetRegistry} from "../Et2HistorylogWidgetRegistry";
import {adoptDiffStyles, diffStyleSheet} from "../Et2Historylog.diff.styles";

import "../../Et2Diff/Et2Diff";
import "../../Et2Dialog/Et2Dialog";
import {assertNoElement} from "../../test/assertDom";

/**
 * Contract under test: a diff inside the history log, which is a hostile place for one.
 *
 * - et2-diff renders its markup into its own light DOM because the rules that colour it are in
 *   the page's theme stylesheet.  The history log puts diffs inside shadow roots, which that
 *   stylesheet cannot reach, so the rules have to be carried in - see Et2Historylog.diff.styles.
 * - et2-diff also opens its own pop-out dialog, which is position: fixed and therefore unusable
 *   inside the datagrid's virtualized (transformed, contained) rows.  The cell intercepts the
 *   click and hands the diff to the history log, whose shadow root is above the virtualizer.
 *
 * Pass criteria: documented per test.
 */

const egwStub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	image: () => "",
	preference: () => null,
	set_preference: () => {},
	app_name: () => "infolog",
	link: (url : string) => url,
	debug: () => {},
	debug_level: () => 0,
	dataFetch: (_e, _r, _f, _w, callback) => callback({order: [], total: 0}),
	dataRegisterUID: (_uid, callback) => callback({}, "row::1"),
	accounts: () => Promise.resolve([]),
	accountData: () => {},
	accountInfo: () => null,
	lang_notranslate: (s : string) => s,
	decodePath: (p : string) => p,
	encodePath: (p : string) => p
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

const settle = async() =>
{
	for(let i = 0; i < 6; i++)
	{
		await Promise.resolve();
	}
};

/** A diff row: the server replaces both values with a unified diff plus a marker in old_value */
const DIFF_ROW = {
	status: 'De',
	new_value: "--- diff\n+++ diff\n@@ -1 +1 @@\n-was\n+is\n",
	old_value: HISTORY_DIFF_MARKER
};

async function owner()
{
	const log = new Et2Historylog();
	log.value = {id: 1, app: "infolog", "status-widgets": {}};
	document.body.append(log);
	await log.updateComplete;
	log.widgetRegistry = new Et2HistorylogWidgetRegistry({}, {}, (s) => s);
	return log;
}

async function cellIn(log : Et2Historylog, field : "new_value" | "old_value", row : any)
{
	const cell = new Et2HistorylogValue();
	cell.field = field;
	log.append(cell);
	cell.value = row;
	await cell.updateComplete;
	await settle();
	return cell;
}

describe("Et2Historylog diff", () =>
{
	let logs : Et2Historylog[] = [];
	let injected : HTMLStyleElement;

	before(() =>
	{
		// Stand in for the theme stylesheet, which a test page does not load.  One rule of each
		// kind the scan looks for: the library's, and EGroupware's override of it.
		injected = document.createElement("style");
		injected.textContent = ".d2h-code-line {color: rgb(1, 2, 3);}\net2-diff .d2h-file-header {display: none;}";
		document.head.append(injected);
	});

	after(() => injected.remove());

	afterEach(() =>
	{
		logs.forEach(l => l.remove());
		logs = [];
	});

	const track = (log : Et2Historylog) => { logs.push(log); return log; };

	/**
	 * Pass criteria: the rules are found in the document and come back as one adoptable sheet.
	 */
	it("lifts the diff rules out of the document", () =>
	{
		const sheet = diffStyleSheet();
		assert.instanceOf(sheet, CSSStyleSheet, "the diff rules must be found in the document");
		const text = Array.from(sheet!.cssRules).map(r => r.cssText).join("\n");
		assert.include(text, "d2h-code-line", "the library's own rules must be carried over");
		assert.include(text, "d2h-file-header", "EGroupware's overrides must be carried over too");
	});

	/**
	 * Pass criteria: adopting is idempotent - a cell re-rendering must not stack up copies.
	 */
	it("adopts the diff rules into a shadow root only once", async() =>
	{
		const host = document.createElement("div");
		const root = host.attachShadow({mode: "open"});

		adoptDiffStyles(root);
		const after = root.adoptedStyleSheets.length;
		adoptDiffStyles(root);

		assert.equal(after, 1, "the sheet must be adopted");
		assert.equal(root.adoptedStyleSheets.length, after, "adopting again must not add a second copy");
	});

	/**
	 * Pass criteria: a diff cell can style its diff - without this the markup renders as plain
	 * text, including the library's file header that EGroupware hides.
	 */
	it("gives a diff cell the diff rules", async() =>
	{
		const log = track(await owner());
		const cell = await cellIn(log, "new_value", DIFF_ROW);

		assert.equal(cell.shadowRoot!.querySelector(".value")!.firstElementChild?.localName, "et2-diff",
			"a diff row must render et2-diff");
		assert.include(cell.shadowRoot!.adoptedStyleSheets, diffStyleSheet()!,
			"the cell hosting the diff must carry the diff rules");
	});

	/**
	 * Pass criteria: the click reaches the history log instead of et2-diff, which would otherwise
	 * open a dialog inside the virtualized row, where it is unusable.
	 */
	it("hands a click on a diff to the history log", async() =>
	{
		const log = track(await owner());
		const cell = await cellIn(log, "new_value", DIFF_ROW);
		const shown : string[] = [];
		log.showDiff = (value : string) => shown.push(value);

		const diff = cell.shadowRoot!.querySelector("et2-diff")!;
		// et2-diff works out for itself whether any of the diff is cut off, from its laid-out
		// height.  Nothing here is laid out - et2-historylog renders a grid, not a <slot>, so a
		// cell appended to it is in the DOM but not in the flat tree and has no box at all.  Say
		// so directly; that et2-diff sets this from real measurements is Et2Diff's own test.
		diff.toggleAttribute("overflowing", true);
		diff.dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true}));
		await settle();

		assert.deepEqual(shown, [DIFF_ROW.new_value], "the history log must be given the diff to show");
		assert.isFalse((<any>diff).open, "et2-diff must not open its own dialog");
	});

	/**
	 * Pass criteria: a diff that fits is not clickable at all.  The pop-out would show nothing
	 * the row is not already showing, and the button that offers it is the only sign that a diff
	 * has been cut off - offering it for one that has not been would make it meaningless.
	 */
	it("leaves a diff that is not cut off alone", async() =>
	{
		const log = track(await owner());
		const cell = await cellIn(log, "new_value", DIFF_ROW);
		let shown = 0;
		log.showDiff = () => { shown++; };

		const diff = cell.shadowRoot!.querySelector("et2-diff")!;
		assert.isFalse((<any>diff).overflowing, "a short diff must not report itself as cut off");
		diff.dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true}));
		await settle();

		assert.equal(shown, 0, "a diff that is fully visible must not pop out");
		assert.isFalse((<any>diff).open, "and must not open et2-diff's own dialog either");
	});

	/**
	 * Pass criteria: only the cell rendering the diff intercepts.  The old-value cell of a diff
	 * row holds nothing but the marker, and an ordinary value cell is not a diff at all.
	 */
	it("leaves a click on anything else alone", async() =>
	{
		const log = track(await owner());
		const cell = await cellIn(log, "old_value", DIFF_ROW);
		let shown = 0;
		log.showDiff = () => { shown++; };

		cell.shadowRoot!.querySelector(".value")!.dispatchEvent(new MouseEvent("click", {bubbles: true}));
		await settle();

		assert.equal(shown, 0, "the paired old-value cell must not pop anything out");
	});

	/**
	 * Pass criteria: the dialog is a child of the history log's own shadow root, which is outside
	 * the datagrid's virtualizer, and it carries the diff.
	 */
	it("shows the diff in a dialog of its own", async() =>
	{
		const log = track(await owner());

		log.showDiff(DIFF_ROW.new_value);
		await log.updateComplete;
		await settle();

		const dialog = log.shadowRoot!.querySelector("et2-dialog");
		assert.isNotNull(dialog, "a dialog must be rendered");
		const diff = dialog!.querySelector("et2-diff");
		assert.isNotNull(diff, "the dialog must hold a diff");
		assert.include((<any>diff).value, "+is", "the diff shown must be the one asked for");
		assert.isTrue((<any>diff).noDialog,
			"the popped-out copy must not offer to pop itself out again");
		assert.include(log.shadowRoot!.adoptedStyleSheets, diffStyleSheet()!,
			"the diff markup lands in this shadow root, so the rules must be here too");
	});

	/**
	 * Pass criteria: nothing to show means no dialog, rather than an empty one.
	 */
	it("does not open an empty dialog", async() =>
	{
		const log = track(await owner());

		log.showDiff("");
		await log.updateComplete;

		assertNoElement(log.shadowRoot!.querySelector("et2-dialog"), "an empty diff must not open a dialog");
	});

	/**
	 * Pass criteria: closing clears the diff, so the next row's diff opens a fresh dialog rather
	 * than re-showing the last one.
	 */
	it("forgets the diff when the dialog closes", async() =>
	{
		const log = track(await owner());
		log.showDiff(DIFF_ROW.new_value);
		await log.updateComplete;
		await settle();

		log.shadowRoot!.querySelector("et2-dialog")!.dispatchEvent(new Event("close", {bubbles: true}));
		await log.updateComplete;

		assertNoElement(log.shadowRoot!.querySelector("et2-dialog"), "closing must take the dialog away");
	});
});
