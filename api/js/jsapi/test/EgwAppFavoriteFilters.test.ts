/**
 * Tests for EgwApp._favoriteFilters(), which makes applying a favorite leave the nextmatch with the
 * favorite's column filters, instead of adding them to the ones set before.
 *
 * The method only reads the nextmatch's activeFilters, so it is exercised on the prototype.
 */
import {assert} from "@open-wc/testing";
import {EgwApp} from "../egw_app";

const favoriteFilters = (active : object, filters : any) : any =>
	(<any>EgwApp.prototype)._favoriteFilters.call({}, {activeFilters: {col_filter: active}}, filters);

describe("EgwApp._favoriteFilters()", () =>
{
	it("clears the column filters that are set but not in the favorite", () =>
	{
		const result = favoriteFilters({org_name: "aaa", tid: "n"}, {search: "", col_filter: {tid: "n", owner: "0"}});
		assert.deepEqual(result.col_filter, {org_name: "", tid: "n", owner: "0"});
		assert.equal(result.search, "");
	});

	it("clears the column filters the favorite has empty", () =>
	{
		const result = favoriteFilters({info_type: "task"}, {col_filter: {info_type: ""}});
		assert.deepEqual(result.col_filter, {info_type: ""});
	});

	it("clears every column filter for a favorite with an empty list of them", () =>
	{
		assert.deepEqual(favoriteFilters({info_type: "task", tid: "n"}, {col_filter: []}).col_filter, {info_type: "", tid: ""});
		assert.deepEqual(favoriteFilters({info_type: "task"}, {col_filter: {}}).col_filter, {info_type: ""});
	});

	it("leaves the column filters alone for a favorite that has none", () =>
	{
		const filters = {search: "foo"};
		assert.strictEqual(favoriteFilters({info_type: "task"}, filters), filters);
	});

	it("does not change the favorite", () =>
	{
		const filters = {col_filter: {tid: "n"}};
		favoriteFilters({org_name: "aaa"}, filters);
		assert.deepEqual(filters, {col_filter: {tid: "n"}});
	});
});

describe("EgwApp._applyFavoriteColumns()", () =>
{
	it("sets the columns once the nextmatch and its datagrid have updated", async() =>
	{
		const order : string[] = [];
		const nm = {
			updateComplete: new Promise(resolve => setTimeout(() => { order.push("nextmatch updated"); resolve(true); }, 5)),
			_datagrid: {updateComplete: new Promise(resolve => setTimeout(() => { order.push("datagrid updated"); resolve(true); }, 15))},
			setColumns: columns => order.push("columns " + columns.join())
		};
		await (<any>EgwApp.prototype)._applyFavoriteColumns.call({}, nm, ["a", "b"]);
		assert.deepEqual(order, ["nextmatch updated", "datagrid updated", "columns a,b"]);
	});

	it("sets the columns of a nextmatch without a datagrid", async() =>
	{
		const columns = [];
		await (<any>EgwApp.prototype)._applyFavoriteColumns.call({}, {setColumns: c => columns.push(...c)}, ["a"]);
		assert.deepEqual(columns, ["a"]);
	});
});
