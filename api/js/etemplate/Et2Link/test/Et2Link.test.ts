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

	/**
	 * Attachments which are symlinks to a remote mount are listed without a mime type, the widget gets it when the user starts to use the link
	 */
	describe("attachment without a mime type", () =>
	{
		const attachment = (extra = {}) => Object.assign({
			app: "file", id: "archive.zip", title: "archive.zip", app2: "infolog", id2: "1450"
		}, extra);

		const setup = async(type : string | null) =>
		{
			const element = await fixture<any>(html`<et2-link></et2-link>`);
			const calls : any[] = [];
			const opened : any[] = [];
			let answer : (mime : string | false) => void;
			const pending = new Promise<string | false>(resolve => answer = resolve);
			element.egw = () => ({
				lang: (l : string) => l,
				debug: () => {},
				image: () => "",
				tooltipBind: () => {},
				tooltipUnbind: () => {},
				link_title: () => Promise.resolve("archive.zip"),
				jsonq: (menuaction : string, params : any[]) =>
				{
					calls.push({menuaction, params});
					return pending;
				},
				open: (...args : any[]) => opened.push(args)
			});
			element.value = attachment({type: type});
			await new Promise<void>(resolve => requestAnimationFrame(() => resolve()));
			return {element, calls, opened, answer};
		};

		const pointerdown = (element : any) =>
			element.shadowRoot.querySelector(".link").dispatchEvent(new PointerEvent("pointerdown", {bubbles: true}));
		const settle = () => new Promise<void>(resolve => setTimeout(resolve, 0));

		it("does not ask when it is displayed", async() =>
		{
			const {calls} = await setup(null);

			assert.lengthOf(calls, 0);
		});

		it("asks the server on mousedown and stores the answer", async() =>
		{
			const {element, calls, answer} = await setup(null);

			pointerdown(element);

			assert.lengthOf(calls, 1);
			assert.equal(calls[0].menuaction, "EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_attachment_mime");
			assert.deepEqual(calls[0].params, ["infolog", "1450", "archive.zip"]);

			answer("application/zip");
			await settle();
			assert.equal(element.dataset.type, "application/zip");
		});

		it("does not ask if the mime type is known", async() =>
		{
			const {element, calls} = await setup("application/zip");

			pointerdown(element);
			element._handleClick(new MouseEvent("click"));

			assert.lengthOf(calls, 0);
		});

		it("does not ask twice for the same attachment", async() =>
		{
			const {element, calls} = await setup(null);

			pointerdown(element);
			pointerdown(element);
			element._handleClick(new MouseEvent("click"));

			assert.lengthOf(calls, 1);
		});

		it("a click asks if there was no mousedown, and waits for the mime type before opening", async() =>
		{
			const {element, calls, opened, answer} = await setup(null);

			element._handleClick(new MouseEvent("click"));
			assert.lengthOf(calls, 1);
			assert.lengthOf(opened, 0, "opened before the mime type was known");
			assert.isTrue(element.classList.contains("waiting-for-mime"), "no progress cursor while waiting");

			answer("application/zip");
			await settle();
			assert.isFalse(element.classList.contains("waiting-for-mime"), "progress cursor stays");

			assert.lengthOf(opened, 1);
			assert.equal(opened[0][0].type, "application/zip");
		});

		it("a click after the mousedown waits for the request already sent", async() =>
		{
			const {element, calls, opened, answer} = await setup(null);

			pointerdown(element);
			element._handleClick(new MouseEvent("click"));
			assert.lengthOf(calls, 1);
			assert.lengthOf(opened, 0);

			answer("application/zip");
			await settle();

			assert.lengthOf(opened, 1);
			assert.equal(opened[0][0].type, "application/zip");
		});

		it("a click still opens if the mime type could not be found", async() =>
		{
			const {element, calls, opened, answer} = await setup(null);

			element._handleClick(new MouseEvent("click"));
			answer(false);
			await settle();

			assert.lengthOf(opened, 1);
			assert.notOk(opened[0][0].type);
			assert.lengthOf(calls, 1);
		});

		it("a click still opens, once, if the request fails", async() =>
		{
			const {element, calls, opened} = await setup(null);
			element.egw = () => ({
				lang: (l : string) => l, debug: () => {}, image: () => "", tooltipBind: () => {}, tooltipUnbind: () => {},
				jsonq: () => {calls.push(1); return Promise.reject(new Error("offline"));},
				open: (...args : any[]) => opened.push(args)
			});

			element._handleClick(new MouseEvent("click"));
			await settle();

			assert.lengthOf(opened, 1);
			assert.lengthOf(calls, 1);
		});
	});
});
