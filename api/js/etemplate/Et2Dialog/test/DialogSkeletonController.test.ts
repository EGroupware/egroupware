import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {DialogSkeletonController, forgetDialogSize, menuactionOf, rememberedSize} from "../DialogSkeletonController";
import "../Et2Dialog";

const ACTION = "infolog.infolog_ui.edit";

/**
 * Stand-in shown while an edit form opens in an in-page dialog
 */
describe("DialogSkeletonController", () =>
{
	let css : HTMLStyleElement;
	let parent : HTMLElement;
	let controller : DialogSkeletonController | undefined;
	let real : any;

	beforeEach(async() =>
	{
		css = document.createElement("style");
		css.id = "egw-skeleton-css";
		document.head.append(css);
		parent = await fixture(html`<div></div>`);
		window.localStorage.setItem("egw_skeleton_size:" + ACTION, JSON.stringify({width: 600, height: 500}));
	});
	afterEach(() =>
	{
		controller?.discard();
		real?.remove();
		css.remove();
		window.localStorage.removeItem("egw_skeleton_size:" + ACTION);
		window.localStorage.removeItem("egw_skeleton");
	});

	/**
	 * What the user would see: a dialog element is display:contents, so only its base part can be transparent
	 */
	const seen = (dialog : any) => getComputedStyle(dialog.shadowRoot.querySelector(".dialog")).opacity !== "0";

	it("finds the menuaction in what a dialog is opened with", () =>
	{
		assert.equal(menuactionOf("index.php?menuaction=infolog.infolog_ui.edit&type=task"), "infolog.infolog_ui.edit");
		assert.equal(menuactionOf("infolog.infolog_ui.edit&type=task"), "");
		assert.equal(menuactionOf("menuaction=a.b.c"), "a.b.c");
	});

	describe("learn()", () =>
	{
		const load = (dialog : any, skeleton : boolean) => dialog._contentNode.dispatchEvent(
			new CustomEvent("load", {bubbles: true, composed: true, detail: {skeleton}}));

		it("remembers the size of a form the server says has a skeleton, forgets one it says has not", async() =>
		{
			window.localStorage.removeItem("egw_skeleton_size:" + ACTION);
			real = await fixture(html`<et2-dialog isModal style="--width: 600px"><form class="et2_container dialog_content"></form></et2-dialog>`);
			DialogSkeletonController.learn("index.php?menuaction=" + ACTION, real);

			load(real, true);
			await new Promise(r => setTimeout(r, 900));
			assert.isNotNull(window.localStorage.getItem("egw_skeleton_size:" + ACTION));

			load(real, false);
			assert.isNull(window.localStorage.getItem("egw_skeleton_size:" + ACTION));
		});

		it("ignores a load of something nested in the form", async() =>
		{
			window.localStorage.setItem("egw_skeleton_size:" + ACTION, JSON.stringify({width: 600, height: 500}));
			real = await fixture(html`<et2-dialog isModal><form class="et2_container dialog_content"><div id="nested"></div></form></et2-dialog>`);
			DialogSkeletonController.learn("index.php?menuaction=" + ACTION, real);
			real.querySelector("#nested").dispatchEvent(new CustomEvent("load", {bubbles: true, composed: true, detail: {skeleton: false}}));
			assert.isNotNull(window.localStorage.getItem("egw_skeleton_size:" + ACTION));
		});

		it("forgetDialogSize() removes it", () =>
		{
			forgetDialogSize(ACTION);
			assert.isNull(window.localStorage.getItem("egw_skeleton_size:" + ACTION));
		});
	});

	it("is not shown for a form it does not know yet", () =>
	{
		window.localStorage.removeItem("egw_skeleton_size:" + ACTION);
		assert.isUndefined(DialogSkeletonController.create("index.php?menuaction=" + ACTION, parent));
	});

	it("still works when the browser blocks storage", () =>
	{
		const blocked = sinon.stub(Storage.prototype, "getItem").throws(new DOMException("blocked", "SecurityError"));
		try
		{
			assert.doesNotThrow(() => DialogSkeletonController.create("index.php?menuaction=" + ACTION, parent));
			assert.isUndefined(DialogSkeletonController.create("index.php?menuaction=" + ACTION, parent));
			assert.equal(menuactionOf("index.php?menuaction=%E0%A4%A"), "");
		}
		finally
		{
			blocked.restore();
		}
	});

	it("is not shown for a small dialog, nor when turned off", () =>
	{
		window.localStorage.setItem("egw_skeleton_size:" + ACTION, JSON.stringify({width: 300, height: 200}));
		assert.isUndefined(DialogSkeletonController.create("index.php?menuaction=" + ACTION, parent));
		window.localStorage.setItem("egw_skeleton_size:" + ACTION, JSON.stringify({width: 600, height: 500}));
		window.localStorage.setItem("egw_skeleton", "0");
		assert.isUndefined(DialogSkeletonController.create("index.php?menuaction=" + ACTION, parent));
	});

	it("clamps the remembered size to the viewport", () =>
	{
		window.localStorage.setItem("egw_skeleton_size:" + ACTION, JSON.stringify({width: 99999, height: 99999}));
		const size = rememberedSize(ACTION);
		assert.isAtMost(size.width, window.innerWidth);
		assert.isAtMost(size.height, window.innerHeight);
	});

	describe("when created", () =>
	{
		beforeEach(async() =>
		{
			controller = DialogSkeletonController.create("index.php?menuaction=" + ACTION, parent, "/icon.svg");
			await controller.host.updateComplete;
			await new Promise(r => setTimeout(r, 0));
		});

		it("is an inert dialog of the remembered size with the stand-in in it", () =>
		{
			const dialog = controller.host;
			assert.isTrue(dialog.inert);
			assert.isTrue(dialog.isConnected);
			const panel = <HTMLElement>dialog.shadowRoot.querySelector(".dialog__panel");
			assert.equal(panel.style.width, "600px");
			assert.equal(panel.style.height, "500px");
			assert.isNotNull(dialog.querySelector(".egw-skeleton"));
			assert.equal(dialog.querySelector(".egw-skeleton img").getAttribute("src"), "/icon.svg");
		});

		it("hides a dialog that is added next to it before it can be painted, and gives it back when it goes", async() =>
		{
			real = document.createElement("et2-dialog");
			parent.append(real);
			// before the next paint: a mutation observer has run by the time this microtask does
			await Promise.resolve();
			assert.isFalse(seen(real), "the real dialog was visible on top of the stand-in");

			controller!.discard();
			assert.isTrue(seen(real));
		});

		it("hides it even though the dialog element itself has no box (display:contents) for opacity to act on", async() =>
		{
			real = document.createElement("et2-dialog");
			parent.append(real);
			await Promise.resolve();
			assert.equal(getComputedStyle(real).display, "contents");
			assert.equal(getComputedStyle(real).opacity, "1", "the host is not what gets hidden");
			assert.isFalse(seen(real));
		});

		it("hides a dialog that was handed over before it was added, and shows it again when it is done", async() =>
		{
			// the order the framework uses: the dialog is handed over first and only then put in the page
			real = document.createElement("et2-dialog");
			controller!.handOver(real);
			parent.append(real);
			await Promise.resolve();
			assert.isFalse(seen(real));
			real.dispatchEvent(new CustomEvent("close"));

			assert.isTrue(seen(real), "left hidden for good");
		});

		it("is removed by discard()", () =>
		{
			controller.discard();
			assert.isFalse(controller.host.isConnected);
		});

		describe("handOver()", () =>
		{
			beforeEach(async() =>
			{
				real = await fixture(html`<et2-dialog isModal><form class="et2_container dialog_content"></form></et2-dialog>`);
				real.sizeIsSet = true;
			});

			it("keeps the real dialog hidden until its own form has loaded and settled, then swaps in one go", async() =>
			{
				controller.handOver(real);
				assert.isFalse(seen(real));
				// a template nested in the form fires its own load first: that is not the form
				const nested = document.createElement("div");
				real.querySelector("form").append(nested);
				nested.dispatchEvent(new CustomEvent("load", {bubbles: true, composed: true, detail: {}}));
				await new Promise(r => setTimeout(r, 400));
				assert.isFalse(seen(real));
				assert.isTrue(controller.host.isConnected);

				real.querySelector("form").dispatchEvent(new CustomEvent("load", {bubbles: true, composed: true, detail: {skeleton: true}}));
				await new Promise(r => setTimeout(r, 500));
				assert.isTrue(seen(real));
				assert.isFalse(controller.host.isConnected);
			});

			it("does not wait for the open animation, which scales the panel while nobody sees it", async() =>
			{
				const sheet = new CSSStyleSheet();
				sheet.replaceSync("@keyframes grow{from{transform:scale(.5)}to{transform:scale(1)}} .dialog__panel{animation:grow 5s linear infinite alternate}");
				real.shadowRoot.adoptedStyleSheets = [...real.shadowRoot.adoptedStyleSheets, sheet];
				controller.handOver(real);

				real.querySelector("form").dispatchEvent(new CustomEvent("load", {bubbles: true, composed: true, detail: {skeleton: true}}));
				await new Promise(r => setTimeout(r, 300));

				assert.isFalse(controller.host.isConnected, "still waiting for a transform to stop changing");
				assert.isTrue(seen(real));
			});

			it("drops the stand-in when the real dialog is closed, e.g. after an error", async() =>
			{
				controller.handOver(real);
				real.dispatchEvent(new Event("close"));
				assert.isFalse(controller.host.isConnected);
				assert.isTrue(seen(real));
			});
		});
	});
});
