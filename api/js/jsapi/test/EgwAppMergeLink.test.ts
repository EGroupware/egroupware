/**
 * Tests for EgwApp._canLinkMergedDocument(), which decides if the merge dialog offers
 * "Link to each entry".
 *
 * CONTRACT
 * A merged document can only be linked to entries the user may edit. Lists flag rows the user
 * can not edit with the "rowNoEdit" class in the row data. The option is withdrawn only when
 * every selected entry is known to be not editable: one editable entry, a row without data, or
 * "select all" (where only the loaded rows are known) keeps it, since the server decides per
 * entry anyway.
 *
 * SETUP
 * The method is called on the prototype with a minimal `this` (no EgwApp is constructed, its
 * constructor needs a framework and window.app) whose egw.dataGetUIDdata() answers from a map
 * of row classes.
 *
 * PASS CRITERIA
 * The method returns exactly true or false as described.
 */
import {assert} from "@open-wc/testing";
import {EgwApp} from "../egw_app";

const NO_EDIT = "rowNoEdit rowNoClose rowNoDelete ";
const EDITABLE = "rowNoCloseAll ";

/**
 * Ask if the document could be linked, for rows with the given classes
 *
 * @param rows row id => class of the row data, undefined for a row without data
 * @param all "select all" is active in the list the action came from
 */
function canLink(rows : { [id : string] : string | undefined }, all : boolean = false) : boolean
{
	const app = {
		egw: {
			dataGetUIDdata: (uid : string) => typeof rows[uid] === "undefined" ? null : {data: {class: rows[uid]}}
		}
	};
	const nm = {getSelection: () => ({ids: Object.keys(rows), all: all})};
	// the list hangs off a parent of the executed action
	const action = {data: {}, parent: {data: {nextmatch: nm}, parent: null}};
	const selected = Object.keys(rows).map(id => ({id: id}));

	return (<any>EgwApp.prototype)._canLinkMergedDocument.call(app, action, selected);
}

describe("EgwApp: offering to link a merged document", () =>
{
	it("offers linking for an editable entry", () =>
	{
		assert.isTrue(canLink({"example::1": EDITABLE}));
	});

	it("withdraws linking when the only selected entry is not editable", () =>
	{
		assert.isFalse(canLink({"example::1": NO_EDIT}));
	});

	it("withdraws linking when none of several selected entries is editable", () =>
	{
		assert.isFalse(canLink({"example::1": NO_EDIT, "example::2": NO_EDIT, "example::3": NO_EDIT}));
	});

	it("offers linking as soon as one selected entry is editable", () =>
	{
		assert.isTrue(canLink({"example::1": NO_EDIT, "example::2": NO_EDIT, "example::3": EDITABLE}));
	});

	it("follows the selection: swapping the editable entry for a non-editable one withdraws linking", () =>
	{
		assert.isTrue(canLink({"example::1": NO_EDIT, "example::2": NO_EDIT, "example::3": EDITABLE}));
		assert.isFalse(canLink({"example::1": NO_EDIT, "example::2": NO_EDIT, "example::4": NO_EDIT}));
	});

	it("offers linking for a row it has no data for", () =>
	{
		assert.isTrue(canLink({"example::1": NO_EDIT, "example::2": undefined}));
	});

	it("offers linking with select all, even if every loaded row is not editable", () =>
	{
		assert.isTrue(canLink({"example::1": NO_EDIT, "example::2": NO_EDIT}, true));
	});

	it("offers linking when nothing is selected", () =>
	{
		assert.isTrue(canLink({}));
	});

	it("works without an action to find the list from", () =>
	{
		const app = {egw: {dataGetUIDdata: () => ({data: {class: NO_EDIT}})}};
		assert.isFalse((<any>EgwApp.prototype)._canLinkMergedDocument.call(app, undefined, [{id: "example::1"}]));
	});
});
