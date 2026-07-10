import {assert} from "@open-wc/testing";
import "../Headers/CustomfieldsHeader";
import {ET2_NEXTMATCH_SORT_EVENT} from "../Headers/events";

const egwStub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url,
	debug: () => {}
};

window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

const waitForBubblingHandlers = async() =>
{
	await Promise.resolve();
	await Promise.resolve();
};

const customfieldsHeader = () =>
{
	const header = document.createElement("et2-nextmatch-header-customfields") as any;
	header.customfields = {
		cf_text: {label: "Text", type: "text"},
		cf_select: {label: "Select", type: "select"},
		cf_date: {label: "Date", type: "date"},
		cf_owner: {label: "Owner", type: "select"}
	};
	header.fields = {
		cf_text: true,
		cf_select: true,
		cf_date: true,
		cf_owner: true
	};
	return header;
};

const sortHeaderById = (header : any, id : string) =>
{
	return Array.from(header.querySelectorAll("et2-nextmatch-sortheader"))
		.find((sortHeader : any) => sortHeader.getAttribute("id") === id) as HTMLElement | undefined;
};

const modificationsWithCustomfields = (customfieldSettings : Record<string, any>) =>
{
	return {
		getEntry: (id : string) => id === "~custom_fields~" ? customfieldSettings : {},
		getRoot()
		{
			return this;
		}
	};
};

describe("Et2CustomfieldsHeader", () =>
{
	it("treats fields as the selected customfield allow-list", () =>
	{
		const header = customfieldsHeader();
		header.fields = {
			cf_text: true,
			cf_owner: true
		};

		assert.deepEqual(
			header.getCustomfieldVisibility(),
			{
				cf_text: true,
				cf_select: false,
				cf_date: false,
				cf_owner: true
			},
			"customfields missing from fields should be hidden"
		);
	});

	it("uses a fields attribute from column preferences before modification defaults", () =>
	{
		const header = document.createElement("et2-nextmatch-header-customfields") as any;
		header.setAttribute("fields", "cf_text,cf_select");
		header.setArrayMgr("modifications", modificationsWithCustomfields({
			customfields: {
				cf_text: {label: "Text", type: "text"},
				cf_select: {label: "Select", type: "select"},
				cf_date: {label: "Date", type: "date"}
			},
			fields: {
				cf_text: true,
				cf_select: true,
				cf_date: true
			}
		}) as any);

		header._applyFieldsAttribute();
		assert.isTrue(
			header._syncCustomfieldsFromModifications(),
			"customfield metadata should hydrate from modifications"
		);
		assert.deepEqual(
			header.fields,
			{cf_text: true, cf_select: true},
			"preference fields attribute should stay the selected sparse field list"
		);
		assert.deepEqual(
			header.getCustomfieldVisibility(),
			{
				cf_text: true,
				cf_select: true,
				cf_date: false
			},
			"customfields missing from the preference fields attribute should remain hidden"
		);
	});

	/**
	 * Contract under test:
	 * - CustomfieldsHeader renders each visible custom field as a nested sortable
	 *   header whose click still emits a composed Nextmatch sort event.
	 *
	 * Setup strategy:
	 * - Render CustomfieldsHeader inside a host listening for Nextmatch sort events.
	 * - Click the nested et2-nextmatch-sortheader rendered by CustomfieldsHeader.
	 *
	 * Pass criteria:
	 * - The host receives the sort event with the customfield id and `#` prefix.
	 */
	it("emits sort event when clicking a customfields sort header", async() =>
	{
		const host = document.createElement("div");
		document.body.append(host);
		try
		{
			const header = customfieldsHeader();
			host.append(header);
			await header.updateComplete;
			let sortDetail : any = null;
			host.addEventListener(ET2_NEXTMATCH_SORT_EVENT, (event : CustomEvent) =>
			{
				sortDetail = event.detail;
			});

			const sortHeader = sortHeaderById(header, "#cf_text");
			assert.isNotNull(sortHeader, "customfield sort header should render");
			assert.equal(sortHeader!.getAttribute("id"), "#cf_text", "customfield sort header DOM id should be set");
			sortHeader!.click();
			await waitForBubblingHandlers();

			assert.deepEqual(
				{id: sortDetail?.id, asc: sortDetail?.asc},
				{id: "#cf_text", asc: true},
				"customfield sort event should be emitted"
			);
		}
		finally
		{
			host.remove();
		}
	});
});
