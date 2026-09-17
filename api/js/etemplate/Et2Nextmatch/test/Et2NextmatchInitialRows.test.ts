import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";

/**
 * Contract under test:
 * - `transformAttributes()` splits the server's initial `rows` payload into real records and
 *   per-request metadata by KEY (integer-keyed = record), the same way the server itself does
 *   when answering a refresh (`is_int($n)` in Etemplate\Widget\Nextmatch::ajax_get_rows()).
 *
 * Why it matters: a get_rows callback may put string-keyed metadata into the same `$rows`
 * array as the records, and that metadata is not always a scalar - addressbook always sends
 * `$rows['customfields'] = array_values(...)`, an array. The old split was by value type
 * ("anything object-ish is a row"), so that array became a bogus extra row. Having no row_id
 * field it landed in the datagrid under its own array index (`addressbook::50`), where it
 * showed up as a blank, unselectable row and - because that blank row carries none of the
 * app's row classes - hid every context-menu action gated on one (addressbook's whole
 * "Email" submenu is `enableClass=contact_contact, hideOnDisabled`). Worse, if a real record
 * happened to have that same id, the real one was then dropped by the datagrid as a duplicate
 * and left an unfillable placeholder row. Reported live 2026-09-17.
 *
 * Setup strategy: call `transformAttributes()` directly on a fresh, unattached widget with a
 * rows payload shaped like addressbook's (integer-keyed records + a string-keyed array +
 * string-keyed scalars). No rendering, no server, no datagrid needed - the split happens
 * before any of that.
 *
 * Pass criteria: only the integer-keyed entries survive as rows, in order; every string-keyed
 * entry (scalar or not) is stashed for processAdditionalData() instead.
 */
const egwStub = {
	lang: (label : string) => label,
	app_name: () => "addressbook",
	preference: () => null,
	debug: () => {}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

describe("Et2Nextmatch initial rows / metadata split", () =>
{
	let nm : Et2Nextmatch;

	/**
	 * transformAttributes() reads its `settings` out of the content array manager, which a
	 * never-attached widget does not have - stub an empty one so the rows/metadata split
	 * below can be exercised on its own.
	 */
	const createNextmatch = () =>
	{
		const widget = new Et2Nextmatch();
		(<any>widget).getArrayMgr = () => ({
			getEntry: () => null,
			getPerspectiveData: () => ({owner: null}),
			getRoot: () => null,
			data: {}
		});
		return widget;
	};

	beforeEach(() =>
	{
		nm = createNextmatch();
	});

	it("keeps only integer-keyed entries as rows", () =>
	{
		nm.transformAttributes({
			rows: {
				"0": {id: "11", n_fn: "First"},
				"1": {id: "12", n_fn: "Second"},
				// addressbook's own always-present metadata: an ARRAY, not a scalar
				customfields: [{id: "3", name: "text"}, {id: "10", name: "Infolog"}],
				call_popup: "",
				no_customfields: true
			}
		});

		assert.deepEqual((nm as any).rows.map((row) => row.id), ["11", "12"],
			"only the two real records, still in order");
	});

	it("stashes every string-keyed entry for processAdditionalData(), arrays included", () =>
	{
		nm.transformAttributes({
			rows: {
				"0": {id: "11"},
				customfields: [{id: "3", name: "text"}],
				no_customfields: false
			}
		});

		const additional = (nm as any)._initialAdditionalData;
		assert.isOk(additional, "metadata was stashed");
		assert.deepEqual(additional.customfields, [{id: "3", name: "text"}],
			"the array metadata is passed on, not silently dropped");
		assert.strictEqual(additional.no_customfields, false, "scalar metadata still passed on");
		assert.notProperty(additional, "0", "records are not passed on as metadata");
	});

	it("still handles a plain array payload and non-sequential record keys", () =>
	{
		nm.transformAttributes({rows: [{id: "11"}, {id: "12"}]});
		assert.deepEqual((nm as any).rows.map((row) => row.id), ["11", "12"], "plain array untouched");

		// PHP emits an object as soon as the row numbering has a gap
		const other = createNextmatch();
		other.transformAttributes({rows: {"1": {id: "11"}, "3": {id: "12"}}});
		assert.deepEqual((other as any).rows.map((row) => row.id), ["11", "12"],
			"gapped integer keys are still records");
	});
});
