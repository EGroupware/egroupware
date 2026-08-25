import {assert, fixture, html} from "@open-wc/testing";
import "../Et2Link";

/**
 * Regression coverage for the "??" MISSING_TITLE sentinel.
 *
 * Spec: a complete entry (app & entryId both present) but no title yet/ever should show the
 * "??" placeholder - that's a legitimate "still loading" / "couldn't resolve a title" signal.
 * An incomplete entry (app or entryId missing) is not a real value at all, and must render
 * blank, never "??".
 */
describe("Et2Link", () =>
{
	it("renders nothing when app is missing (incomplete value)", async() =>
	{
		const element = await fixture<any>(html`<et2-link></et2-link>`);
		element.egw = () => ({
			lang: (l : string) => l,
			debug: () => {},
			image: () => "",
			tooltipBind: () => {},
			tooltipUnbind: () => {},
			link_title: () => Promise.resolve("Should not be called")
		});

		element.value = {id: "123"};
		await element.updateComplete;

		assert.equal(element.title, "");
	});

	it("renders nothing when entryId is missing (incomplete value)", async() =>
	{
		const element = await fixture<any>(html`<et2-link></et2-link>`);
		element.egw = () => ({
			lang: (l : string) => l,
			debug: () => {},
			image: () => "",
			tooltipBind: () => {},
			tooltipUnbind: () => {},
			link_title: () => Promise.resolve("Should not be called")
		});

		element.value = {app: "addressbook"};
		await element.updateComplete;

		assert.equal(element.title, "");
	});

	it("shows the MISSING_TITLE placeholder while the real title is loading for a complete value", async() =>
	{
		const element = await fixture<any>(html`<et2-link></et2-link>`);
		let resolveTitle : (title : string) => void;
		const titlePromise = new Promise<string>(resolve => resolveTitle = resolve);
		element.egw = () => ({
			lang: (l : string) => l,
			debug: () => {},
			image: () => "",
			tooltipBind: () => {},
			tooltipUnbind: () => {},
			link_title: () => titlePromise
		});

		element.value = {app: "addressbook", id: "123"};
		// Et2Link.getUpdateComplete() deliberately waits on the title fetch too (so external
		// callers get the real title, not the placeholder) - so it can't be used here to observe
		// the intermediate "still loading" state. A single rendered frame is enough to let Lit's
		// synchronous render commit without waiting for the (still-pending) title promise.
		await new Promise<void>(resolve => requestAnimationFrame(() => resolve()));

		assert.equal(element.title, "??");

		resolveTitle("Real Title");
		await titlePromise;
		await element.updateComplete;

		assert.equal(element.title, "Real Title");
	});

	it("clears app/entryId back to incomplete and blanks the title", async() =>
	{
		const element = await fixture<any>(html`<et2-link></et2-link>`);
		element.egw = () => ({
			lang: (l : string) => l,
			debug: () => {},
			image: () => "",
			tooltipBind: () => {},
			tooltipUnbind: () => {},
			link_title: () => Promise.resolve("Real Title")
		});

		element.value = {app: "addressbook", id: "123"};
		await element.updateComplete;
		assert.equal(element.title, "Real Title");

		element.value = "";
		await element.updateComplete;

		assert.equal(element.title, "");
	});
});
