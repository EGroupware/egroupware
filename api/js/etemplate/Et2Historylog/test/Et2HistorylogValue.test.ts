import {assert} from "@open-wc/testing";
import {Et2Historylog} from "../Et2Historylog";
import {Et2HistorylogValue, HISTORY_DIFF_MARKER, HISTORY_ONE2N_SEPARATOR} from "../Et2HistorylogValue";
import {Et2HistorylogWidgetRegistry} from "../Et2HistorylogWidgetRegistry";

// Widgets the rows under test resolve to.  etemplate2 registers everything in the real app; a
// test file has to ask for what it uses, or the registry resolves a tag customElements has never
// heard of.
import "../../Et2Select/Et2Select";
import "../../Et2Select/Select/Et2SelectAccount";
import "../../Et2Description/Et2Description";
import "../../Et2Date/Et2DateTime";
import "../../Et2Diff/Et2Diff";
import "../../Layout/Et2Box/Et2Box";

/**
 * Contract under test:
 * - A value cell picks its widget from the row's `status`, via the owner's registry.
 * - A diff row renders et2-diff in the new-value cell only, and marks both cells so the paired
 *   old-value cell can take itself out of the grid flow.
 * - A status with no usable widget falls back to plain text rather than throwing.
 * - The cell never contributes to a submit.
 *
 * Setup strategy:
 * - Put real cells inside a real (but not loaded) et2-historylog, whose registry is set directly.
 *   The cells find their owner by walking up shadow boundaries, which works the same here.
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
	dataFetch: (_e, _r, _f, _w, callback) => callback({order: [], total: 0}),
	dataRegisterUID: (_uid, callback) => callback({}, "row::1"),
	accountData: () => {},
	// Et2SelectAccount's connectedCallback() fetches its option list through this, and chains
	// .then() on the result - so it has to be a promise, not an array.
	accounts: () => Promise.resolve([]),
	accountInfo: () => null,
	lang_notranslate: (s : string) => s,
	// Et2Template checks this while loading; without it the (expected) template-load failure in
	// these tests throws a second, confusing error on top of the real one.
	debug_level: () => 0,
	// Et2VfsPath runs every value through these; a '~file~' history row renders through it
	decodePath: (p : string) => p,
	encodePath: (p : string) => p
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

const settle = async() =>
{
	for(let i = 0; i < 4; i++)
	{
		await Promise.resolve();
	}
};

/**
 * An owner whose registry is set directly - firstUpdated() would need a server and a template.
 */
async function ownerWith(statusWidgets : Record<string, any>, customfields : Record<string, any> = {})
{
	const owner = new Et2Historylog();
	owner.value = {id: 1, app: "infolog", "status-widgets": statusWidgets};
	document.body.append(owner);
	await owner.updateComplete;
	owner.widgetRegistry = new Et2HistorylogWidgetRegistry(statusWidgets, customfields, (s) => s);
	return owner;
}

async function cellIn(owner : Et2Historylog, field : "new_value" | "old_value", row : any)
{
	const cell = new Et2HistorylogValue();
	cell.field = field;
	owner.append(cell);
	cell.value = row;
	await cell.updateComplete;
	await settle();
	return cell;
}

describe("Et2HistorylogValue", () =>
{
	let owners : Et2Historylog[] = [];

	afterEach(() =>
	{
		owners.forEach(o => o.remove());
		owners = [];
	});

	const track = (owner : Et2Historylog) => { owners.push(owner); return owner; };

	/**
	 * Pass criteria: the widget the app mapped the field to is the one that renders, and it gets
	 * that field's value.
	 */
	it("renders the widget the row's status maps to", async() =>
	{
		const owner = track(await ownerWith({'St': {'open': 'Open', 'done': 'Done'}}));
		const cell = await cellIn(owner, "new_value", {status: 'St', new_value: 'done', old_value: 'open'});

		const rendered = cell.shadowRoot!.querySelector(".value")!.firstElementChild;
		assert.equal(rendered?.localName, "et2-select",
			"a status mapped to an options map must render as a select");
		assert.equal((<any>rendered).value, 'done', "the cell's own field value must be applied");
	});

	/**
	 * Pass criteria: the old-value cell of the same row shows the *old* value, not the new one.
	 */
	it("shows its own field, not the other one", async() =>
	{
		const owner = track(await ownerWith({'St': {'open': 'Open', 'done': 'Done'}}));
		const cell = await cellIn(owner, "old_value", {status: 'St', new_value: 'done', old_value: 'open'});

		const rendered = cell.shadowRoot!.querySelector(".value")!.firstElementChild;
		assert.equal((<any>rendered).value, 'open');
	});

	/**
	 * Pass criteria: a status with no widget renders the raw value as text, and does not throw.
	 */
	it("falls back to text for a status with no widget", async() =>
	{
		const owner = track(await ownerWith({}));
		const cell = await cellIn(owner, "new_value", {status: 'whatever', new_value: 'plain text', old_value: ''});

		const container = cell.shadowRoot!.querySelector(".value")!;
		assert.equal(container.firstElementChild, null, "no widget should have been built");
		assert.equal(container.textContent, 'plain text', "the value must still be shown");
	});

	/**
	 * A null or object value has no sensible text form; showing "[object Object]" or "null" would
	 * be worse than showing nothing.
	 *
	 * Pass criteria: neither leaks into the cell as text.
	 */
	it("shows nothing rather than null/[object Object] in the text fallback", async() =>
	{
		const owner = track(await ownerWith({}));

		const nullCell = await cellIn(owner, "new_value", {status: 'x', new_value: null, old_value: ''});
		assert.equal(nullCell.shadowRoot!.querySelector(".value")!.textContent, '');

		const objCell = await cellIn(owner, "new_value", {status: 'x', new_value: {a: 1}, old_value: ''});
		assert.equal(objCell.shadowRoot!.querySelector(".value")!.textContent, '');
	});

	/**
	 * The server replaces both values with a unified diff plus a marker for long/multi-line
	 * values.  The diff is rendered once and spans both columns, so only the new-value cell
	 * renders it - but both cells must be marked, or the old-value cell would sit in the track the
	 * diff is trying to span into.
	 *
	 * Pass criteria: new-value renders et2-diff and is marked `diff` + `diff-row`; old-value
	 * renders nothing and is marked `diff-row` only.
	 */
	it("renders a diff in the new-value cell and marks both cells", async() =>
	{
		const owner = track(await ownerWith({'De': 'description'}));
		const row = {status: 'De', new_value: '--- a\\n+++ b\\n@@ -1 +1 @@\\n-old\\n+new', old_value: HISTORY_DIFF_MARKER};

		const newCell = await cellIn(owner, "new_value", row);
		assert.isTrue(newCell.hasAttribute("diff"), "the rendering cell must be marked diff");
		assert.isTrue(newCell.hasAttribute("diff-row"), "and as belonging to a diff row");
		assert.equal(newCell.shadowRoot!.querySelector(".value")!.firstElementChild?.localName, "et2-diff");

		const oldCell = await cellIn(owner, "old_value", row);
		assert.isFalse(oldCell.hasAttribute("diff"),
			"the old-value cell must not render its own diff");
		assert.isTrue(oldCell.hasAttribute("diff-row"),
			"but must be marked so it can remove itself from the grid flow");
		assert.equal(oldCell.shadowRoot!.querySelector(".value")!.firstElementChild, null);
		assert.notInclude(oldCell.shadowRoot!.querySelector(".value")!.textContent, HISTORY_DIFF_MARKER,
			"the diff marker itself must never be shown as a value");
	});

	/**
	 * The diff decision is a property of the row, not of the field: it is keyed on old_value even
	 * when rendering new_value.  A row whose *new* value happens to equal the marker is not a diff.
	 *
	 * Pass criteria: only old_value === marker triggers the diff.
	 */
	it("keys the diff off old_value only", async() =>
	{
		const owner = track(await ownerWith({}));
		const cell = await cellIn(owner, "new_value", {status: 'x', new_value: HISTORY_DIFF_MARKER, old_value: 'real'});

		assert.isFalse(cell.hasAttribute("diff-row"),
			"the marker in new_value must not be mistaken for a diff row");
	});

	/**
	 * Calendar's participants arrive as one value object per part.
	 *
	 * Pass criteria: each sub-widget gets its own part of the value.
	 */
	it("applies each part of a multi-part value", async() =>
	{
		const owner = track(await ownerWith({
			'participants': ['description', {'U': 'Unknown', 'A': 'Accepted'}]
		}));
		const cell = await cellIn(owner, "new_value", {
			status: 'participants',
			new_value: {'0': 'Someone', '1': 'A'},
			old_value: ''
		});

		const box = cell.shadowRoot!.querySelector(".value")!.firstElementChild!;
		const parts = Array.from(box.children);
		assert.equal(parts.length, 2, "one widget per declared part");
		assert.equal((<any>parts[0]).value, 'Someone');
		assert.equal((<any>parts[1]).value, 'A');
	});

	/**
	 * A 1:N value that reached us still joined (from the row cache, or an app hook that skipped
	 * the server's explode) must not be shown as one string with the separator in it.
	 *
	 * Pass criteria: it is split back apart.
	 */
	it("splits a still-joined 1:N value", async() =>
	{
		const owner = track(await ownerWith({}));
		const cell = await cellIn(owner, "new_value", {
			status: 'x',
			new_value: 'a' + HISTORY_ONE2N_SEPARATOR + 'b',
			old_value: ''
		});

		assert.notInclude(cell.shadowRoot!.querySelector(".value")!.textContent, HISTORY_ONE2N_SEPARATOR,
			"the raw separator must never be shown");
	});

	/**
	 * The datagrid recycles row elements while scrolling, so a cell is re-bound to a different
	 * row far more often than it changes widget type.
	 *
	 * Pass criteria: re-binding to the same status keeps the same widget instance (and updates
	 * its value); re-binding to a different status rebuilds.
	 */
	it("reuses its widget across row re-binds, and rebuilds on a type change", async() =>
	{
		const owner = track(await ownerWith({
			'St': {'open': 'Open', 'done': 'Done'},
			'De': 'description'
		}));
		const cell = await cellIn(owner, "new_value", {status: 'St', new_value: 'open', old_value: ''});
		const first = cell.shadowRoot!.querySelector(".value")!.firstElementChild;

		cell.value = {status: 'St', new_value: 'done', old_value: ''};
		await cell.updateComplete;
		await settle();
		assert.strictEqual(cell.shadowRoot!.querySelector(".value")!.firstElementChild, first,
			"the same status must reuse the widget instance");
		assert.equal((<any>first).value, 'done', "but must be given the new row's value");

		cell.value = {status: 'De', new_value: 'some text', old_value: ''};
		await cell.updateComplete;
		await settle();
		assert.notStrictEqual(cell.shadowRoot!.querySelector(".value")!.firstElementChild, first,
			"a status resolving to a different widget must rebuild");
	});

	/**
	 * The widget a cell builds must carry the row's status as its id.
	 *
	 * An Et2Select resolves its options through the array manager *by id*. Given the status it
	 * finds that field's own options (HistoryLog::beforeSendToClient() namespaces one entry per
	 * field); left without an id it resolves to the namespace root instead and takes the whole
	 * `{status: ..., col_filter: ..., Ty: ..., St: ...}` object as its option list - so nothing
	 * matches the row's value and the cell renders blank. Found live: Status, Completed and a
	 * custom-field select all showed empty while Responsible happened to work.
	 *
	 * The legacy widget passed the field code as the id for the same reason.
	 *
	 * Pass criteria: the built widget's id is the status; multi-part children get their part key.
	 */
	it("gives the widget the row's status as its id, so options resolve", async() =>
	{
		const owner = track(await ownerWith({'St': {'open': 'Open', 'done': 'Done'}}));
		const cell = await cellIn(owner, "new_value", {status: 'St', new_value: 'done', old_value: ''});

		const built = cell.shadowRoot!.querySelector(".value")!.firstElementChild;
		assert.equal((<any>built).id, 'St',
			"without the status as its id a select resolves sel_options to the namespace root");
	});

	/**
	 * Pass criteria: each sub-widget of a multi-part value gets its own part key as id, so its
	 * options resolve too (eg. calendar's participants status/role lists).
	 */
	it("gives each multi-part sub-widget its part key as id", async() =>
	{
		const owner = track(await ownerWith({
			'participants': ['description', {'U': 'Unknown', 'A': 'Accepted'}]
		}));
		const cell = await cellIn(owner, "new_value", {
			status: 'participants',
			new_value: {'0': 'Someone', '1': 'A'},
			old_value: ''
		});

		const box = cell.shadowRoot!.querySelector(".value")!.firstElementChild!;
		assert.deepEqual(Array.from(box.children).map(c => (<any>c).id), ['0', '1'],
			"each part must carry its own key as id");
	});

	/**
	 * Pass criteria: the cell is a display widget - never dirty, never a value.
	 */
	it("never contributes to a submit", async() =>
	{
		const owner = track(await ownerWith({}));
		const cell = await cellIn(owner, "new_value", {status: 'x', new_value: 'v', old_value: ''});

		assert.isFalse(cell.isDirty(), "a display cell is never dirty");
		assert.isNull(cell.getValue(), "a display cell returns no value");
	});
});
