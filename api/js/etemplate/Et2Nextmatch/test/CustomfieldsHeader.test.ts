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

/**
 * Upper bound of update cycles a settling header may run.
 * Connecting, hydrating metadata and a handful of hydration retries stay well below it,
 * a re-render loop exceeds it within one task.
 */
const MAX_SETTLE_UPDATES = 25;

/**
 * Count the header's update cycles, and stop a runaway re-render loop once it passes
 * MAX_SETTLE_UPDATES.
 *
 * Lit chains the updates of such a loop as microtasks, which would starve timers and hang the
 * runner, so past the limit `updated()` is no longer forwarded - that breaks the loop - and the
 * returned counter records the overflow for the test to fail on.
 */
const countUpdates = (header: any) =>
{
	const counter = {updates: 0};
	const originalUpdated = header.updated.bind(header);
	header.updated = (changedProperties: Map<string, any>) =>
	{
		counter.updates++;
		if(counter.updates <= MAX_SETTLE_UPDATES)
		{
			originalUpdated(changedProperties);
		}
	};
	return counter;
};

/**
 * Modifications whose local entry (for the widget id) and root `~custom_fields~` entry both carry a key
 * that is none of customfields/fields/exclude/typeFilter, so every merge reports `changed`.
 */
const modificationsWithExtraKeys = (id: string, customfieldSettings: Record<string, any>) =>
{
	return {
		getEntry: (entryId: string) =>
		{
			if(entryId === "~custom_fields~")
			{
				return {...customfieldSettings, extra_global: "global"};
			}
			return entryId === id ? {extra_local: "local"} : {};
		},
		getRoot()
		{
			return this;
		}
	};
};

const waitMs = (ms: number) => new Promise((resolve) => window.setTimeout(resolve, ms));

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

	/**
	 * Contract under test:
	 * - With explicit `fields` (column preference / column selection), hydrating from modifications keeps
	 *   the `fields` object itself instead of assigning a copy.  A copy is a new reference, so Lit saw a
	 *   property change on every hydration, and as long as customfields or fields were empty `updated()`
	 *   hydrated again - an endless re-render loop that pegged the browser tab's CPU.
	 *
	 * Setup strategy:
	 * - Modifications carry a key beyond customfields/fields/exclude/typeFilter in both the widget's own
	 *   entry and the root `~custom_fields~` entry.  Hydration never stores such keys back, so the merge
	 *   reports `changed` on every call - the condition that used to keep the loop going.
	 * - (a) explicit but empty fields, with customfield definitions in `~custom_fields~`.
	 * - (b) explicit non-empty fields, with no customfield definitions anywhere, so the hydration
	 *   retry timer runs.
	 *   A `changed` merge used to reset the retry attempts every time, so the timer never stopped.
	 * - A short hydrationRetryMs, waiting well past HYDRATION_RETRY_MAX (8) retry intervals.
	 * - countUpdates() stops forwarding `updated()` past MAX_SETTLE_UPDATES, so a regression fails
	 *   instead of hanging the runner in an endless microtask chain.
	 *
	 * Pass criteria:
	 * - `updateComplete` resolves, and the header runs at most MAX_SETTLE_UPDATES update cycles in total.
	 * - The explicit fields are kept as they were.
	 * - No hydration retry is still pending, ie. the retries stopped.
	 *
	 * Environment-sensitive constraints:
	 * - Timers can fire late on a loaded runner, so the wait is 20 retry intervals
	 *   for at most 8 retries.
	 */
	const explicitFieldsSettleCases = [
		{
			name: "empty explicit fields",
			fields: {},
			customfields: {cf_text: {label: "Text", type: "text"}}
		},
		{
			name: "non-empty explicit fields without customfields",
			fields: {cf_text: true},
			customfields: undefined
		}
	];
	explicitFieldsSettleCases.forEach(({name, fields, customfields}) =>
	{
		it(`settles with ${name} and always-changing modifications`, async() =>
		{
			const retryMs = 10;
			const header = document.createElement("et2-nextmatch-header-customfields") as any;
			header.id = "cf_header";
			header.hydrationRetryMs = retryMs;
			header.fields = fields;
			header._hasExplicitFields = true;
			header.setArrayMgr("modifications", modificationsWithExtraKeys(
				"cf_header",
				customfields ? {customfields} : {}
			) as any);
			const counter = countUpdates(header);

			document.body.append(header);
			try
			{
				await header.updateComplete;
				await waitMs(retryMs * 20);
				await header.updateComplete;

				assert.isAtMost(
					counter.updates,
					MAX_SETTLE_UPDATES,
					"header should settle instead of re-rendering endlessly"
				);
				assert.deepEqual(header.fields, fields, "explicit fields should be kept");
				assert.isNull(header._pendingHydrationTimer, "hydration retries should stop");
			}
			finally
			{
				header.remove();
			}
		});
	});
});
