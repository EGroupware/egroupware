import {assert} from "@open-wc/testing";
import {emulateMedia} from "@web/test-runner-commands";
import * as sinon from "sinon";
import "./InfologAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way addressbook/js/test/AddressbookNoFiltersReload.test.ts does, before app.ts pulls it in
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {etemplate2} from "../../../api/js/etemplate/etemplate2";
import {Et2Template} from "../../../api/js/etemplate/Et2Template/Et2Template";
import * as egwGlobal from "../../../api/js/jsapi/egw_global";
// the test server loads .css as its text, as the build does for unsafeCSS()
import kdotsCss from "../../../kdots/css/kdots.css";
import printCss from "../../../api/templates/default/print.css";

/**
 * app.ts has to be loaded through its explicit source path - see
 * addressbook/js/test/MailVcardMessage.test.ts's docblock for why. InfologApp itself is not
 * exported - the module registers it as `app.classes.infolog` at module scope.
 */
const APP_SOURCE = '/infolog/js/app.ts';

/**
 * InfoLog's print view: edit -> Actions -> Print, or Print from the list's context menu
 *
 * Both open the entry in a popup with print=1, which loads infolog.edit.print instead of
 * infolog.edit, prints it once the template is loaded, and closes the popup again when the print
 * dialog is done.  Three things broke that at once, each covered here:
 * - the template itself, which only ever shows up as a wrong or empty printout,
 * - the popup's own scroll container clipping the printout to one page, with the rest of the
 *   content's height printed as blank pages,
 * - every sub-template's bubbling load event opening another print dialog, so Cancel had to be
 *   clicked once per sub-template.
 */
describe("InfoLog print view", () =>
{
	let InfologApp : any;

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		InfologApp = (<any>window).app.classes.infolog;
	});

	/**
	 * The real template file, loaded the way the popup loads it
	 *
	 * Through etemplate2.load() into the form the server renders for it, so InfoLog's own
	 * et2_ready() sees infolog.edit.print and starts the print, as in the popup.
	 *
	 * Pass criteria: every widget in it is one that still exists, the entry's own values end up
	 * in the page, all three sub-templates are found and filled, and it prints exactly once.
	 */
	describe("infolog.edit.print template", () =>
	{
		const CONTENT = {
			info_id: 42,
			info_type: "task",
			info_number: 42,
			info_subject: "Subject for printing",
			info_des: "Description for printing",
			info_status: "ongoing",
			info_percent: 50,
			info_access: "public",
			info_startdate: "2026-09-01T10:00:00Z",
			info_enddate: "2026-09-30T00:00:00Z",
			info_owner: 5,
			info_modifier: 5,
			info_datemodified: "2026-09-29T12:00:00Z",
			pm_id: "",
			link_to: {to_app: "infolog", to_id: 42}
		};
		const SEL_OPTIONS = {
			info_type: {task: "ToDo"},
			info_status: {"not-started": "not started", ongoing: "ongoing", done: "done"}
		};
		// what the widgets and InfologApp use of egw beyond what the import stub's egw provides
		const EGW_ADDITIONS = {
			debug_level: () => 0,
			is_popup: () => true,
			loading_prompt: () => {},
			message: () => {},
			app: () => undefined,
			dataStoreUID: (uid : string) => uid,
			dataHasUID: () => false,
			dataGetUIDdata: () => null,
			link_get_registry: () => undefined,
			link_title: () => Promise.resolve(""),
			accountData: () => Promise.resolve({}),
			accounts: () => [],
			grants: () => ({}),
			getLocalStorageItem: () => null,
			setLocalStorageItem: () => {},
			preferences: () => ({}),
			// no AI prompts: et2-ai around the description then only shows its content
			prompts: () => [],
			stylesheet: () => {}
		};
		// replaced for the test, as they would ask the server: no server-side select options, and
		// preference(..., true) promises its value
		const EGW_OVERRIDES = {
			json: () => ({sendRequest: () => Promise.resolve({response: [{data: []}]})}),
			jsonq: () => Promise.resolve([]),
			preference: (name, app, async) => async ? Promise.resolve(undefined) : undefined
		};
		let overridden : [any, string, any][] = [];
		// egw_global.js exports the window.egw of its import time, which is not window.egw here.
		// Its .d.ts only declares globals, so the export has to be reached without types.
		const egws = () => [...new Set([(<any>window).egw, (<any>egwGlobal).egw])];
		let added : [any, string][] = [];
		let form : HTMLFormElement;
		let et2 : etemplate2;
		let print : sinon.SinonStub;

		before(async function()
		{
			this.timeout(20000);
			const additions = {...EGW_ADDITIONS, ...(<any>window).egw};
			for(const egw of egws())
			{
				for(const name of Object.keys(additions).filter(name => !egw[name]))
				{
					egw[name] = additions[name];
					added.push([egw, name]);
				}
			}
			for(const egw of egws())
			{
				for(const name of Object.keys(EGW_OVERRIDES))
				{
					overridden.push([egw, name, egw[name]]);
					egw[name] = EGW_OVERRIDES[name];
				}
			}
			// a legacy global from egw.js, for etemplate2 (egw_getFramework is the import stub's)
			const globals = {egw_topWindow: () => window};
			for(const name of Object.keys(globals).filter(name => !(<any>window)[name]))
			{
				(<any>window)[name] = globals[name];
				added.push([window, name]);
			}
			print = sinon.stub(window, "print");

			// the server's etemplate.php url is not served here: put the file's templates where
			// Et2Template looks before asking the server
			const xml = await (await fetch("/infolog/templates/default/edit.print.xet")).text();
			const overlay = new window.DOMParser().parseFromString(xml, "text/xml").documentElement;
			assert.notEqual(overlay.nodeName, "parsererror", "edit.print.xet is not well-formed XML");
			for(const template of Array.from(overlay.children))
			{
				Et2Template.templateCache[template.getAttribute("id")] = template;
			}
			assert.exists(Et2Template.templateCache["infolog.edit.print"],
				"edit.print.xet has no infolog.edit.print template");

			// the id the server gives the form, which infolog_print_preview_onload() looks for
			form = document.createElement("form");
			form.id = "infolog-edit-print";
			form.className = "et2_container";
			document.body.append(form);

			// the form's own load, not the first sub-template's bubbling up to it.  An error while
			// loading means it never comes: say so, rather than timing out the hook without a word
			const loaded = Promise.race([
				new Promise(resolve => form.addEventListener("load", event => event.target === form && resolve(event))),
				new Promise((resolve, reject) => setTimeout(() => reject(new Error(
					"infolog.edit.print never finished loading - the error is in the browser log above")), 10000))
			]);
			et2 = new etemplate2(form, "infolog.infolog_ui.edit");
			await et2.load(
				"infolog.edit.print", "/infolog/templates/default/edit.print.xet",
				{content: CONTENT, sel_options: SEL_OPTIONS, readonlys: {}, modifications: {}, currentapp: "infolog"}
			);
			await loaded;
		});

		after(() =>
		{
			form?.remove();
			print?.restore();
			added.forEach(([egw, name]) => delete egw[name]);
			overridden.forEach(([egw, name, original]) => egw[name] = original);
		});

		it("uses no widget that no longer exists", () =>
		{
			// an unknown legacy type renders as a chip naming the type, with no error anywhere
			assert.isNull(form.querySelector(".et2_placeholder"),
				"a widget type in edit.print.xet is unknown and rendered as a placeholder");
			const undefinedTags = new Set(Array.from(form.querySelectorAll("*"))
				.map(node => node.localName)
				.filter(tag => tag.includes("-") && !customElements.get(tag)));
			assert.isEmpty([...undefinedTags], "edit.print.xet uses unregistered widgets");
		});

		it("shows the entry's subject and description", () =>
		{
			// by widget id: the DOM ids get the form's id as prefix
			const value = (id : string) => (<any>et2.widgetContainer.getWidgetById(id))?.value;
			assert.equal(value("info_subject"), CONTENT.info_subject);
			assert.equal(value("info_des"), CONTENT.info_des);
		});

		it("loads all three sub-templates", () =>
		{
			for(const name of ["project", "links", "delegation"])
			{
				const sub = <HTMLElement><unknown>et2.widgetContainer.getWidgetById(`infolog.edit.print.${name}`);
				assert.exists(sub, `infolog.edit.print.${name} is missing`);
				assert.isAbove(sub.querySelectorAll("*").length, 0,
					`infolog.edit.print.${name} was not found or rendered empty`);
			}
		});

		it("prints exactly once", async function()
		{
			this.timeout(5000);
			// infolog_print_preview_onload() waits a second for the rendering to settle
			await new Promise(resolve => setTimeout(resolve, 2000));
			sinon.assert.calledOnce(print);
		});
	});

	/**
	 * A popup's #popupMainDiv is a viewport-high scroll container on screen, so a footer is
	 * always reachable.  Printed, that clipped the entry to what fits on the first page, and the
	 * document still being as long as the content added blank pages after it.
	 *
	 * Against the stylesheets the app ships: the screen rules come from kdots.css, the print
	 * override from print.css.
	 *
	 * Pass criteria: on screen the popup is still capped (so the test is looking at the real
	 * rules), printed it grows to its whole content.
	 */
	describe("printed popup", () =>
	{
		const styles : HTMLStyleElement[] = [];
		let popup : HTMLDivElement;
		// what kdots.css caps the popup to: 100dvh, which in CI's headless Firefox is not
		// window.innerHeight, so measure the unit itself
		let capHeight : number;

		before(async() =>
		{
			for(const css of [kdotsCss, printCss])
			{
				const style = document.createElement("style");
				style.textContent = css;
				// Firefox applies none of an inline sheet's rules while one of its @imports (kdots.css
				// has some) is still loading, which on a busy CI runner it still is when the tests
				// measure.  The rules apply once the imports are done, whether they loaded or failed.
				const applied = new Promise(resolve =>
				{
					style.addEventListener("load", resolve, {once: true});
					style.addEventListener("error", resolve, {once: true});
				});
				document.head.append(style);
				styles.push(style);
				await applied;
			}
		});

		beforeEach(() =>
		{
			const probe = document.createElement("div");
			probe.style.height = "100dvh";
			document.body.append(probe);
			capHeight = probe.offsetHeight;
			probe.remove();

			// the kdots rules match a direct child of body only, like the real popup
			popup = document.createElement("div");
			popup.id = "popupMainDiv";
			popup.className = "popupMainDiv";
			const content = document.createElement("div");
			content.style.height = (3 * capHeight) + "px";
			popup.append(content);
			document.body.append(popup);
		});

		afterEach(async() =>
		{
			popup.remove();
			await emulateMedia({media: "screen"});
		});

		after(() => styles.forEach(style => style.remove()));

		it("is capped to the window on screen", async() =>
		{
			await emulateMedia({media: "screen"});
			assert.isBelow(popup.clientHeight, popup.scrollHeight,
				"kdots.css no longer caps the popup - this test is not looking at the real rules");
			assert.isAtMost(popup.clientHeight, capHeight);
		});

		it("is not cut off to one page when printed", async() =>
		{
			await emulateMedia({media: "print"});
			assert.isAtLeast(popup.clientHeight, popup.scrollHeight,
				"printed popup clips its content: one cut-off page followed by blank ones");
			assert.equal(getComputedStyle(popup).overflowY, "visible");
		});
	});

	/**
	 * infolog_print_preview_onload() prints once the template has loaded
	 *
	 * The form's own load event is the signal, but every sub-template's load bubbles up to it as
	 * well - infolog.edit.print has three.
	 *
	 * Pass criteria: print() is called exactly once, and the popup is closed once the print
	 * dialog is done.
	 */
	describe("print trigger", () =>
	{
		let clock : sinon.SinonFakeTimers;
		let form : HTMLFormElement;
		let et2 : etemplate2;
		let print : sinon.SinonStub;
		let close : sinon.SinonSpy;
		let original_egw : any;

		beforeEach(() =>
		{
			clock = sinon.useFakeTimers();
			form = document.createElement("form");
			form.id = "infolog-edit-print";
			for(const name of ["project", "links", "delegation"])
			{
				const sub = document.createElement("div");
				sub.className = name;
				form.append(sub);
			}
			document.body.append(form);

			print = sinon.stub(window, "print");
			close = sinon.spy();
			original_egw = (<any>window).egw;
			// egw(window).close() goes through the bare global
			(<any>window).egw = Object.assign(() => ({close}), original_egw);
		});

		afterEach(() =>
		{
			// a test that printed but never finished the dialog leaves its afterprint listener behind
			window.dispatchEvent(new Event("afterprint"));
			(<any>window).egw = original_egw;
			print.restore();
			clock.restore();
			form.remove();
		});

		function onload()
		{
			const self = {
				egw: {lang: (text : string) => text, message: () => {}, window: window},
				infolog_print_preview: InfologApp.prototype.infolog_print_preview
			};
			InfologApp.prototype.infolog_print_preview_onload.call(self);
		}

		const load = (target : Element) => target.dispatchEvent(new CustomEvent("load", {bubbles: true}));

		it("prints once, though the sub-templates' load events bubble up to the form too", () =>
		{
			onload();
			// sub-templates announce themselves in no fixed order, before and after the form
			load(form.querySelector(".project"));
			load(form);
			load(form.querySelector(".links"));
			load(form.querySelector(".delegation"));
			clock.tick(2000);

			sinon.assert.calledOnce(print);
		});

		it("waits for the form itself, not the first sub-template", () =>
		{
			onload();
			load(form.querySelector(".links"));
			clock.tick(2000);

			sinon.assert.notCalled(print);
		});

		it("closes the popup once the print dialog is done", () =>
		{
			onload();
			load(form);
			clock.tick(2000);
			sinon.assert.notCalled(close);

			window.dispatchEvent(new Event("afterprint"));
			sinon.assert.calledOnce(close);
		});
	});
});
