/**
 * Test helper: a row built the way the grid really uses it.
 *
 * The grid never keeps the element Et2Datagrid._buildRowElement() returns: it serializes it with
 * outerHTML and unsafeHTML() re-parses the markup, which is when the row's widgets are created.
 * Tests that exercise hydration or attribute resolution therefore need the mounted result, not the
 * built element, and this stamps it into the document the same way (template parse, import into
 * the live document so custom elements are upgraded).
 */
let host : HTMLElement | null = null;

function mountPoint() : HTMLElement
{
	if(!host || !host.isConnected)
	{
		host = document.createElement("div");
		host.setAttribute("data-test-mounted-rows", "");
		host.innerHTML = "<table><tbody></tbody></table>";
		document.body.appendChild(host);
	}
	return host;
}

/**
 * Build a row with the grid and mount its markup in the document
 *
 * @param grid the Et2Datagrid
 * @param row {id, data}
 * @param rowIndex
 * @return the mounted row element, or null if the grid built nothing
 */
export function buildMountedRow(grid : any, row : any, rowIndex : number) : HTMLElement | null
{
	const built = grid._buildRowElement(row, rowIndex) as HTMLElement | null;
	if(!built)
	{
		return null;
	}
	const template = document.createElement("template");
	template.innerHTML = built.outerHTML;
	const mounted = document.importNode(template.content, true);
	const first = mounted.firstElementChild as HTMLElement | null;
	const target = first?.localName === "tr" ? mountPoint().querySelector("tbody")! : mountPoint();
	target.appendChild(mounted);
	return first;
}

// Rows from one test must not leak into the next
afterEach(() =>
{
	host?.remove();
	host = null;
});
