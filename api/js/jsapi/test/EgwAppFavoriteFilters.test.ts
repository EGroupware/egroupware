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

	it("clears the column filters for a favorite that has no column filters key", () =>
	{
		const result = favoriteFilters({"#cf": "x", info_type: "task"}, {search: "foo"});
		assert.deepEqual(result, {search: "foo", col_filter: {"#cf": "", info_type: ""}});
	});

	it("clears the search, filters and category that are set but not in the favorite", () =>
	{
		const nm = {activeFilters: {col_filter: {}, search: "foo", filter: "3", cat_id: "5", path: "/home"}};
		const result = (<any>EgwApp.prototype)._favoriteFilters.call({}, nm, {filter2: "x"});
		assert.deepEqual(result, {search: "", filter: "", cat_id: "", filter2: "x", col_filter: {}});
	});

	it("passes a favorite without any filters on, for applyFilters() to reset everything", () =>
	{
		const filters = {};
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

describe("EgwApp._showFilteredCustomfields()", () =>
{
	const show = (colFilter : any, columns : Promise<any> = Promise.resolve()) =>
	{
		const shown : string[][] = [];
		return (<any>EgwApp.prototype)._showFilteredCustomfields.call({}, {showCustomfields: n => shown.push(n)}, colFilter, columns)
			.then(() => shown);
	};

	it("shows the custom fields the favorite filters by", async() =>
	{
		assert.deepEqual(await show({"#Label": "x", "#Jahr": ["2020"], tid: "n"}), [["Label", "Jahr"]]);
	});

	it("ignores empty custom field filters and other columns", async() =>
	{
		assert.deepEqual(await show({"#Label": "", "#Jahr": [], tid: "n"}), []);
		assert.deepEqual(await show([]), []);
		assert.deepEqual(await show(undefined), []);
	});

	it("waits for the favorite's columns", async() =>
	{
		const order : string[] = [];
		const columns = new Promise(resolve => setTimeout(() => { order.push("columns"); resolve(true); }, 10));
		await (<any>EgwApp.prototype)._showFilteredCustomfields.call({}, {showCustomfields: () => order.push("shown")}, {"#a": "1"}, columns);
		assert.deepEqual(order, ["columns", "shown"]);
	});
});
