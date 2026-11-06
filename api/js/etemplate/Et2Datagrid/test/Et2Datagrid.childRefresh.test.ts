import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract for Et2Datagrid.refresh() reaching rows that live in an expanded child grid.
 *
 * A targeted refresh stores the new row data centrally, but each grid only re-renders the rows
 * it holds itself.  Refreshing a row of an expanded sub-grid through the root grid therefore left
 * it showing the old values (reported from projectmanager: an InfoLog element inside an expanded
 * sub-project did not follow a push).
 *
 * Setup: a real parent grid with a stubbed provider, and real child grids placed in expanded rows
 * of its shadow root, their own refresh() recorded.
 *
 * Pass criteria: a child that renders a refreshed row is asked to refresh it; one that does not is
 * left alone, as `add` or `update` would otherwise insert the row there.
 */

const PREFIX = "projectmanager_elements::";
const hosts : HTMLElement[] = [];

async function connectedGrid() : Promise<Et2Datagrid>
{
	const host = document.createElement("div");
	document.body.appendChild(host);
	hosts.push(host);
	const grid = new Et2Datagrid();
	host.appendChild(grid);
	await grid.updateComplete;
	return grid;
}

/** A child grid in an expanded row of `parent`, rendering the given data-row-ids */
async function addChild(parent : Et2Datagrid, renderedRowIds : string[]) : Promise<any>
{
	const child : any = await connectedGrid();
	renderedRowIds.forEach((rowId) =>
	{
		const row = document.createElement("tr");
		row.setAttribute("data-row-id", rowId);
		child.shadowRoot.appendChild(row);
	});
	child.refreshed = [];
	child.refresh = async function(ids : string[], type : string)
	{
		this.refreshed.push({ids, type});
	};
	const expandedRow = document.createElement("tr");
	expandedRow.setAttribute("data-dg-expanded-row", "1");
	expandedRow.appendChild(child);
	parent.shadowRoot!.appendChild(expandedRow);
	return child;
}

describe("Et2Datagrid refresh of rows in an expanded child grid", () =>
{
	let parent : Et2Datagrid;

	beforeEach(async() =>
	{
		parent = await connectedGrid();
		parent.dataProvider = {
			normalizeRowId: (rowId : string) => rowId.startsWith(PREFIX) ? rowId : PREFIX + rowId,
			toProviderRowId: (rowId : string) => rowId.replace(PREFIX, ""),
			refresh: async() => ({rows: [], removedRowIds: []})
		} as any;
	});

	afterEach(() =>
	{
		while(hosts.length)
		{
			hosts.pop()!.remove();
		}
	});

	it("refreshes the row in the child grid that renders it", async() =>
	{
		const child = await addChild(parent, [`${PREFIX}infolog:5:42`]);

		await parent.refresh(["infolog:5:42"], "update-in-place" as any);

		assert.deepEqual(child.refreshed, [{ids: [`${PREFIX}infolog:5:42`], type: "update-in-place"}]);
	});

	it("leaves a child grid alone that does not render the row", async() =>
	{
		const child = await addChild(parent, [`${PREFIX}infolog:6:43`]);

		await parent.refresh(["infolog:5:42"], "update-in-place" as any);

		assert.isEmpty(child.refreshed, "an add or update would insert the row into a grid that does not have it");
	});
});
