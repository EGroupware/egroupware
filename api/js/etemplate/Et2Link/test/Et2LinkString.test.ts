import {assert, fixture, html} from "@open-wc/testing";
import "../Et2Link";
import "../Et2LinkString";

// Et2LinkString's constructor reads the "maxmatchs" preference before any test code gets a
// chance to override egw() on the instance, so the fallback needs to be a thenable up front.
window.egw = Object.assign(() => window.egw, {
	preference: () => Promise.resolve(20),
	lang: (l : string) => l,
	debug: () => {},
	// Rendered <et2-link> children (one per parsed id) call these during their own lifecycle
	image: () => "",
	link_title: () => Promise.resolve(""),
	tooltipBind: () => {},
	tooltipUnbind: () => {}
}) as any;

/**
 * Regression coverage: an empty-string value must render no links at all, not a phantom
 * entry that ends up showing the "??" MISSING_TITLE placeholder forever (see Et2Link.test.ts
 * for the sentinel's own contract).
 */
describe("Et2LinkString", () =>
{
	it("renders no links for an empty string value", async() =>
	{
		const element = await fixture<any>(html`<et2-link-string></et2-link-string>`);

		element.set_value("");
		await element.updateComplete;

		assert.isEmpty(element._link_list);
		assert.isNull(element.shadowRoot.querySelector("et2-link"));
	});

	it("still parses a normal CSV list of ids", async() =>
	{
		const element = await fixture<any>(html`<et2-link-string application="addressbook"></et2-link-string>`);

		element.set_value("1,2,3");
		await element.updateComplete;

		assert.lengthOf(element._link_list, 3);
		assert.deepEqual(element._link_list.map(l => l.id), ["1", "2", "3"]);
	});

	it("ignores a trailing comma instead of adding an empty entry", async() =>
	{
		const element = await fixture<any>(html`<et2-link-string application="addressbook"></et2-link-string>`);

		element.set_value("1,2,");
		await element.updateComplete;

		assert.lengthOf(element._link_list, 2);
	});

	/**
	 * Inside a nextmatch row the widget is rendered with the row placeholder as entryId and only
	 * gets the real entry ID afterwards, when the row is bound to its data.  Asking the server
	 * about the placeholder is not just useless, it used to block the request for the real ID.
	 */
	describe("in a nextmatch row", () =>
	{
		// Answers are resolved by the test, so a request can be left in flight on purpose
		let requests : { to_id : string, resolve : (links : any) => void }[];

		// updateComplete waits for the answer, which is exactly what we don't want here
		const rendered = () => new Promise(resolve => setTimeout(resolve, 0));

		const stubRequests = (element) =>
		{
			requests = [];
			element.egw = () => Object.assign(Object.create(window.egw()), {
				jsonq: (_method, [_value]) => new Promise(resolve => requests.push({to_id: _value.to_id, resolve}))
			});
		};

		["$row_cont[ts_id]", "${row}[ts_id]", "$ts_id"].forEach(placeholder =>
		{
			it(`does not request links for the unresolved placeholder ${placeholder}`, async() =>
			{
				const element = await fixture<any>(html`
                    <et2-link-string application="timesheet"></et2-link-string>`);
				stubRequests(element);

				element.entryId = placeholder;
				await rendered();

				assert.isEmpty(requests, "asked the server about a row placeholder");

				// ... and the real ID, arriving later, is still requested
				element.entryId = "12";
				await rendered();

				assert.lengthOf(requests, 1);
				assert.equal(requests[0].to_id, "12");
			});
		});

		it("replaces a running request when it gets another entry", async() =>
		{
			const element = await fixture<any>(html`
                <et2-link-string application="timesheet"></et2-link-string>`);
			stubRequests(element);

			element.entryId = "12";
			await rendered();
			// Row re-used for another entry before the first answer arrives
			element.entryId = "13";
			await rendered();

			assert.deepEqual(requests.map(r => r.to_id), ["12", "13"]);

			// The late answer for the entry we no longer show must not end up in the list
			requests[0].resolve({1: {app: "infolog", id: "1", link_id: 1}});
			requests[1].resolve({2: {app: "infolog", id: "2", link_id: 2}});
			await rendered();

			assert.deepEqual(element._link_list.map(l => l.id), ["2"]);
		});

		/**
		 * Asking the server costs a link lookup plus a title for every entry, so a list nobody
		 * can see must not ask.  Being hidden is not visible in the widget's own attributes -
		 * the timesheet list switches its rows' link lists off with a CSS custom property set
		 * on the nextmatch - so the widget has to go by whether it is being rendered.
		 */
		describe("that is not being displayed", () =>
		{
			// Long enough for the IntersectionObserver to report a change
			const observed = () => new Promise(resolve => setTimeout(resolve, 50));

			const hiddenRow = async() =>
			{
				// Mirrors how the timesheet list hides its links: display comes from a custom
				// property set further up, which the widget itself knows nothing about
				const row = await fixture<HTMLElement>(html`
                    <div style="--links-display:none">
                        <et2-link-string application="timesheet"
                                         style="display:var(--links-display, inline)"></et2-link-string>
                    </div>`);
				const element = <any>row.querySelector("et2-link-string");
				await element.updateComplete;
				stubRequests(element);

				return {
					element: element,
					show: () => row.style.setProperty("--links-display", "inline")
				};
			};

			it("does not request links", async() =>
			{
				const {element} = await hiddenRow();

				element.entryId = "12";
				await observed();

				assert.isEmpty(requests, "asked for links nobody can see");
			});

			it("requests them once it is displayed", async() =>
			{
				const {element, show} = await hiddenRow();

				element.entryId = "12";
				await observed();
				show();
				await observed();

				assert.deepEqual(requests.map(r => r.to_id), ["12"]);

				requests[0].resolve({1: {app: "infolog", id: "1", link_id: 1}});
				await observed();

				assert.deepEqual(element._link_list.map(l => l.id), ["1"]);
			});

			it("requests the entry it has by then, not the one it was hidden with", async() =>
			{
				const {element, show} = await hiddenRow();

				element.entryId = "12";
				await observed();
				// Row re-used for another entry while still hidden
				element.entryId = "13";
				await observed();

				assert.isEmpty(requests);

				show();
				await observed();

				assert.deepEqual(requests.map(r => r.to_id), ["13"]);
			});

			it("keeps a late answer out when it gets another entry while hidden", async() =>
			{
				const row = await fixture<HTMLElement>(html`
                    <div>
                        <et2-link-string application="timesheet"></et2-link-string>
                    </div>`);
				const element = <any>row.querySelector("et2-link-string");
				await element.updateComplete;
				stubRequests(element);

				element.entryId = "12";
				await rendered();
				assert.lengthOf(requests, 1);

				// Hidden while that request is still running, then re-used for another entry
				row.style.display = "none";
				element.entryId = "13";
				await observed();
				assert.lengthOf(requests, 1, "asked while hidden");

				row.style.display = "";
				await observed();
				assert.deepEqual(requests.map(r => r.to_id), ["12", "13"]);

				requests[0].resolve({1: {app: "infolog", id: "1", link_id: 1}});
				requests[1].resolve({2: {app: "infolog", id: "2", link_id: 2}});
				await observed();

				assert.deepEqual(element._link_list.map(l => l.id), ["2"]);
			});
		});
	});
});
