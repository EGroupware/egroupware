import {assert, elementUpdated, fixture, html, waitUntil} from "@open-wc/testing";
import * as sinon from "sinon";
// The menu only imports shoelace as types, so esbuild drops their registration
import "@shoelace-style/shoelace/dist/components/tree/tree.js";
import "@shoelace-style/shoelace/dist/components/tree-item/tree-item.js";
import "@shoelace-style/shoelace/dist/components/dropdown/dropdown.js";
import "@shoelace-style/shoelace/dist/components/menu/menu.js";
import "@shoelace-style/shoelace/dist/components/menu-item/menu-item.js";
import "@shoelace-style/shoelace/dist/components/icon/icon.js";
import "@shoelace-style/shoelace/dist/components/popup/popup.js";
import "../../Et2Button/Et2ButtonIcon";
// Imported for the registration of the element as well as its type
import Sortable from "sortablejs/modular/sortable.complete.esm.js";
import "../Et2FavoritesMenu";
import type {Et2FavoritesMenu} from "../Et2FavoritesMenu";
import {Et2Dialog} from "../../Et2Dialog/Et2Dialog";

/**
 * The tree of favorites, with the favorites that are in folders under their folder
 */
const APP = "testapp";

let prefs : { [name : string] : any };
let user : () => object;
let setState : sinon.SinonSpy;
const savedEgw = (<any>window).egw;
const savedApp = (<any>window).app;

const own = (name : string, folder? : string) : any => ({name: name, group: false, state: {search: name}, ...(folder ? {folder} : {})});
const shared = (name : string, folder? : string) : any => ({name: name, group: -2, state: {search: name}, ...(folder ? {folder} : {})});

async function create() : Promise<Et2FavoritesMenu>
{
	const element = <Et2FavoritesMenu>await fixture(html`
        <et2-favorites-menu application="${APP}" sortable></et2-favorites-menu>`);
	await waitUntil(() => element.shadowRoot.querySelector("sl-tree"), "tree was not rendered");
	await elementUpdated(element);
	return element;
}

const item = (element : Et2FavoritesMenu, name : string) =>
	<any>element.shadowRoot.querySelector(`sl-tree-item[data-name="${name}"]`);
const folder = (element : Et2FavoritesMenu, name : string) =>
	<any>element.shadowRoot.querySelector(`sl-tree-item[data-folder="${name}"]`);
const rootOrder = (element : Et2FavoritesMenu) =>
	Array.from(element.shadowRoot.querySelector("sl-tree").children)
		.filter((c : HTMLElement) => c.localName === "sl-tree-item")
		.map((c : HTMLElement) => c.dataset.folder ? "folder:" + c.dataset.folder : c.dataset.name);

describe("Favorites menu", () =>
{
	beforeEach(() =>
	{
		prefs = {};
		user = () => ({admin: true});
		setState = sinon.spy();
		(<any>window).app = {[APP]: {setState: setState, getState: () => ({})}};
		const egw = {
			lang: i => i,
			preference: () => Promise.resolve(prefs),
			set_preference: (app, name, value) =>
			{
				if(value === "" || value === null)
				{
					delete prefs[name];
				}
				else
				{
					prefs[name] = value;
				}
			},
			user: (...args) => user.apply(null, args),
			deepExtend: (target, ...sources) => Object.assign(target, ...sources)
		};
		(<any>window).egw = Object.assign(() => egw, egw);
		try
		{
			window.localStorage.clear();
		}
		catch(e)
		{
		}
	});
	afterEach(() =>
	{
		(<any>window).egw = savedEgw;
		(<any>window).app = savedApp;
		sinon.restore();
	});

	describe("listing", () =>
	{
		it("lists favorites that are not in a folder as they are, without room for folders", async() =>
		{
			prefs = {favorite_A: own("A"), favorite_B: own("B")};
			const element = await create();
			assert.deepEqual(rootOrder(element), ["blank", "A", "B", "~add~"]);
			assert.isTrue(element.shadowRoot.querySelector("sl-tree").classList.contains("no-folders"));
		});

		it("lists favorites in a folder under it, where the first of them was", async() =>
		{
			prefs = {
				favorite_A: own("A"), favorite_B: own("B", "Work"), favorite_C: own("C"), favorite_D: own("D", "Work"),
				fav_sort_pref: ["blank", "A", "B", "C", "D"]
			};
			const element = await create();
			assert.deepEqual(rootOrder(element), ["blank", "A", "folder:Work", "C", "~add~"]);
			assert.deepEqual(Array.from(folder(element, "Work").children).filter((c : any) => c.dataset?.name).map((c : any) => c.dataset.name),
				["B", "D"]);
			assert.isFalse(element.shadowRoot.querySelector("sl-tree").classList.contains("no-folders"));
		});

		it("lists a shared favorite where the user put it", async() =>
		{
			prefs = {favorite_S: shared("S", "Team"), fav_folder_pref: {S: "Mine"}};
			const element = await create();
			assert.isNotNull(folder(element, "Mine"), "S should be in the user's folder");
			assert.isNull(folder(element, "Team"), "a folder nobody is in is not listed");
		});

		it("does not offer folders for 'No filters'", async() =>
		{
			const element = await create();
			assert.isNull(item(element, "blank").querySelector("sl-dropdown"));
		});
	});

	describe("choosing a favorite", () =>
	{
		it("applies a favorite when it is clicked", async() =>
		{
			prefs = {favorite_A: own("A")};
			const element = await create();
			item(element, "A").click();
			await waitUntil(() => setState.called, "favorite was not applied");
			assert.equal(setState.callCount, 1);
			assert.equal(setState.firstCall.args[0].name, "A");
		});

		it("applies a favorite in a folder", async() =>
		{
			prefs = {favorite_A: own("A", "Work")};
			const element = await create();
			item(element, "A").click();
			await waitUntil(() => setState.called, "favorite was not applied");
			assert.equal(setState.firstCall.args[0].name, "A");
		});

		it("does not apply anything when a folder is clicked", async() =>
		{
			prefs = {favorite_A: own("A", "Work")};
			const element = await create();
			folder(element, "Work").click();
			await new Promise(resolve => setTimeout(resolve, 50));
			assert.isFalse(setState.called);
		});

		it("applies the favorite that has the focus on Enter, once", async() =>
		{
			prefs = {favorite_A: own("A")};
			const element = await create();
			// A key press always goes to the item that has the focus
			item(element, "A").focus();
			item(element, "A").dispatchEvent(new KeyboardEvent("keydown", {key: "Enter", bubbles: true, composed: true}));
			await waitUntil(() => setState.called, "favorite was not applied");
			assert.equal(setState.callCount, 1);
		});

		it("does not apply the favorite when Enter opens its actions", async() =>
		{
			prefs = {favorite_A: own("A")};
			const element = await create();
			item(element, "A").focus();
			item(element, "A").querySelector("et2-button-icon")
				.dispatchEvent(new KeyboardEvent("keydown", {key: "Enter", bubbles: true, composed: true}));
			await new Promise(resolve => setTimeout(resolve, 50));
			assert.isFalse(setState.called);
		});

		it("does not apply the favorite when its actions are opened", async() =>
		{
			prefs = {favorite_A: own("A")};
			const element = await create();
			item(element, "A").querySelector("sl-dropdown").dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true}));
			await new Promise(resolve => setTimeout(resolve, 50));
			assert.isFalse(setState.called);
		});
	});

	describe("showing the favorite in use", () =>
	{
		it("marks it, and only it", async() =>
		{
			prefs = {favorite_A: own("A"), favorite_B: own("B")};
			const element = await create();
			element.activeFavorite = "B";
			await elementUpdated(element);
			await new Promise(resolve => setTimeout(resolve, 20));
			assert.isTrue(item(element, "B").selected);
			assert.isFalse(item(element, "A").selected);
			element.activeFavorite = "";
			await elementUpdated(element);
			await new Promise(resolve => setTimeout(resolve, 20));
			assert.isFalse(item(element, "B").selected, "the mark stays after nothing matches");
		});

		it("opens the folder it is in", async() =>
		{
			prefs = {favorite_A: own("A", "Work")};
			const element = await create();
			assert.isFalse(folder(element, "Work").expanded, "folders start closed");
			element.activeFavorite = "A";
			await elementUpdated(element);
			await new Promise(resolve => setTimeout(resolve, 20));
			assert.isTrue(folder(element, "Work").expanded);
		});
	});

	describe("when the favorite in use changes", () =>
	{
		it("keeps the same elements, so a click that is under way still lands on the button it started on", async() =>
		{
			prefs = {favorite_A: own("A"), favorite_B: own("B")};
			const element = await create();
			const tree = element.shadowRoot.querySelector("sl-tree");
			const button = item(element, "A").querySelector(".more et2-button-icon");
			element.activeFavorite = "B";
			await elementUpdated(element);
			await new Promise(resolve => setTimeout(resolve, 50));
			assert.isTrue(element.shadowRoot.querySelector("sl-tree") === tree, "the tree was rebuilt");
			assert.isTrue(item(element, "A").querySelector(".more et2-button-icon") === button, "the button was replaced");
		});

		it("keeps an open actions menu open", async() =>
		{
			prefs = {favorite_A: own("A"), favorite_B: own("B")};
			const element = await create();
			const dropdown = item(element, "A").querySelector(".more");
			dropdown.setAttribute("open", "");
			element.activeFavorite = "B";
			await elementUpdated(element);
			await new Promise(resolve => setTimeout(resolve, 50));
			assert.isTrue(item(element, "A").querySelector(".more") === dropdown, "the menu was rebuilt");
			assert.isTrue(dropdown.hasAttribute("open"));
		});

		it("keeps the same elements when asked to show again", async() =>
		{
			prefs = {favorite_A: own("A")};
			const element = await create();
			const row = item(element, "A");
			element.requestUpdate();
			await elementUpdated(element);
			await new Promise(resolve => setTimeout(resolve, 50));
			assert.isTrue(item(element, "A") === row, "the row was rebuilt");
		});
	});

	describe("folders open and closed", () =>
	{
		it("remembers which are open", async() =>
		{
			prefs = {favorite_A: own("A", "Work"), favorite_B: own("B", "Home")};
			let element = await create();
			folder(element, "Work").expanded = true;
			await new Promise(resolve => setTimeout(resolve, 50));
			element.remove();

			element = await create();
			assert.isTrue(folder(element, "Work").expanded, "Work was left open");
			assert.isFalse(folder(element, "Home").expanded, "Home was never opened");
		});
	});

	describe("what can be done to a favorite", () =>
	{
		const actions = (element, name) =>
			Array.from(item(element, name).querySelectorAll(":scope > .row sl-menu-item")).map((i : any) => i.value);

		it("offers a user all of it for their own favorite", async() =>
		{
			user = () => ({});
			prefs = {favorite_A: own("A")};
			assert.deepEqual(actions(await create(), "A"), ["edit", "move", "delete"]);
		});

		it("offers an admin all of it for a shared favorite", async() =>
		{
			prefs = {favorite_Some_name: shared("Some name")};
			assert.deepEqual(actions(await create(), "Some_name"), ["edit", "move", "delete"]);
		});

		it("only lets a user move a shared favorite", async() =>
		{
			user = () => ({});
			prefs = {favorite_S: shared("S")};
			assert.deepEqual(actions(await create(), "S"), ["move"]);
		});

		it("offers renaming and removing a folder", async() =>
		{
			prefs = {favorite_A: own("A", "Work")};
			const element = await create();
			const values = Array.from(folder(element, "Work").querySelectorAll(":scope > .row sl-menu-item")).map((i : any) => i.value);
			assert.deepEqual(values, ["rename", "ungroup"]);
		});
	});

	describe("dragging over other favorites", () =>
	{
		// Fakes what Sortable passes when the dragged favorite is over `over`, at `part` of it
		const dragOver = (element, dragged : string, over : string, part : "label" | "icon" = "label") =>
		{
			const target = item(element, over);
			const path : any[] = [target, element.shadowRoot.querySelector("sl-tree")];
			// ("No filters" has no icon, so the pointer can only be over its label)
			path.unshift(part === "icon" && target.querySelector(".drop-icon") || target.querySelector(".name"));
			return (<any>element).handleMove({dragged: item(element, dragged), related: target}, {clientY: 0, composedPath: () => path});
		};

		beforeEach(() =>
		{
			prefs = {favorite_A: own("A"), favorite_B: own("B"), favorite_C: own("C"), favorite_D: own("D", "Work")};
		});

		it("reorders when over the label of a favorite, wherever on the row", async() =>
		{
			const element = await create();
			assert.isTrue(dragOver(element, "A", "B"));
			assert.isTrue(dragOver(element, "C", "B"));
			assert.isNull(element["_dropTarget"], "reordering is not 'onto'");
		});

		it("drops onto a favorite at once when over its folder icon, and leaves the list as it is", async() =>
		{
			const element = await create();
			assert.isFalse(dragOver(element, "A", "B", "icon"));
			assert.equal(element["_dropTarget"], item(element, "B"));
			assert.isTrue(item(element, "B").classList.contains("drop-onto"));
		});

		it("clears the mark when moving off the icon", async() =>
		{
			const element = await create();
			dragOver(element, "A", "B", "icon");
			assert.isTrue(dragOver(element, "A", "B"));
			assert.isFalse(item(element, "B").classList.contains("drop-onto"));
			assert.isNull(element["_dropTarget"]);
		});

		it("marks the one it is over, and only that one", async() =>
		{
			const element = await create();
			dragOver(element, "A", "B", "icon");
			dragOver(element, "A", "C", "icon");
			assert.isFalse(item(element, "B").classList.contains("drop-onto"));
			assert.equal(element["_dropTarget"], item(element, "C"));
		});

		it("reorders 'No filters', but never drops it onto anything or anything onto it", async() =>
		{
			const element = await create();
			assert.isTrue(dragOver(element, "blank", "A", "icon"), "'No filters' is reordered");
			assert.isNull(element["_dropTarget"], "'No filters' does not make a folder");
			assert.isTrue(dragOver(element, "B", "blank", "icon"));
			assert.isNull(element["_dropTarget"], "nor does anything go onto it");
		});

		it("has no icon to drop onto for 'No filters' and the add button", async() =>
		{
			const element = await create();
			assert.isNull(item(element, "blank").querySelector(".drop-icon"));
			assert.isNull(item(element, "~add~").querySelector(".drop-icon"));
			assert.isNotNull(item(element, "A").querySelector(".drop-icon"));
			assert.isNotNull(folder(element, "Work").querySelector(":scope > .row > .drop-icon"));
		});

		it("has no icon to drop onto for a favorite in a folder, folders are one level deep", async() =>
		{
			const element = await create();
			assert.isNotNull(item(element, "D"), "D is in the folder");
			assert.isNull(item(element, "D").querySelector(".drop-icon"));
		});
	});

	describe("starting a drag", () =>
	{
		// What Sortable is asked whether a press may start a drag: the path a press on `target` has
		const mayDrag = (element, name : string, target : "label" | "button" | "folder") =>
		{
			const filter = (<any>Array.from((<any>element)._sortables.values())[0]).options.filter;
			let path : any[];
			if(target === "folder")
			{
				path = [folder(element, name)];
			}
			else
			{
				const row = item(element, name);
				path = target === "button" ?
					   [row.querySelector(".more et2-button-icon"), row.querySelector(".more"), row.querySelector(":scope > .row"), row] :
					   [row.querySelector(".name"), row.querySelector(":scope > .row"), row];
			}
			return !filter({composedPath: () => path});
		};

		it("starts from the label of a favorite", async() =>
		{
			prefs = {favorite_A: own("A")};
			const element = await create();
			await waitUntil(() => (<any>element)._sortables.size, "Sortable was not set up");
			assert.isTrue(mayDrag(element, "A", "label"));
		});

		it("does not start from the button for its actions, so pressing it can not turn into a drag", async() =>
		{
			prefs = {favorite_A: own("A")};
			const element = await create();
			await waitUntil(() => (<any>element)._sortables.size, "Sortable was not set up");
			assert.isFalse(mayDrag(element, "A", "button"));
		});

		it("does not start from a folder", async() =>
		{
			prefs = {favorite_A: own("A", "Work")};
			const element = await create();
			await waitUntil(() => (<any>element)._sortables.size, "Sortable was not set up");
			assert.isFalse(mayDrag(element, "Work", "folder"));
		});
	});

	describe("while dragging", () =>
	{
		it("keeps the buttons out of the picture dragged along with the favorite", async() =>
		{
			prefs = {favorite_A: own("A")};
			const element = await create();
			const more = item(element, "A").querySelector(".more");
			// The button shows when the pointer is over the row, or its menu is open: use the open state, which a test can set
			more.setAttribute("open", "");
			assert.equal(getComputedStyle(more).visibility, "visible", "control: the button shows while its menu is open");
			item(element, "A").classList.add("sortable-chosen");
			assert.equal(getComputedStyle(more).visibility, "hidden", "the row Sortable has picked up shows no button");
		});

		it("makes way for the icons to drop onto when a drag starts, and goes back when it ends", async() =>
		{
			prefs = {favorite_A: own("A"), favorite_B: own("B")};
			const element = await create();
			assert.isFalse(element.hasAttribute("dragging"));
			(<any>element).handleStart();
			assert.isTrue(element.hasAttribute("dragging"), "the stylesheet hides the buttons and shows the icons for this");
			await (<any>element).handleSortEnd({item: item(element, "B")});
			assert.isFalse(element.hasAttribute("dragging"));
		});
	});

	describe("dragging over a folder", () =>
	{
		// Fakes the dragover event on a folder's header: over its icon, or `fraction` of the way down the rest of it
		const dragOver = (element, dragged : string, fraction : number, icon = false) =>
		{
			const target = folder(element, "Work");
			const row = target.shadowRoot.querySelector("[part~='item']").getBoundingClientRect();
			const event = {
				currentTarget: target,
				composedPath: () => [icon ? target.querySelector(":scope > .row > .drop-icon") : target, target],
				clientY: row.top + row.height * fraction,
				preventDefault: sinon.spy(), stopImmediatePropagation: sinon.spy()
			};
			(<any>Sortable).dragged = item(element, dragged);
			(<any>element).handleFolderDragOver(event);
			return event;
		};

		beforeEach(() =>
		{
			prefs = {favorite_A: own("A"), favorite_B: own("B"), favorite_C: own("C", "Work")};
		});
		afterEach(() =>
		{
			(<any>Sortable).dragged = undefined;
		});

		it("puts a favorite that is going down after the folder when over its header", async() =>
		{
			const element = await create();
			const event = dragOver(element, "B", 0.75);
			assert.deepEqual(rootOrder(element), ["blank", "A", "folder:Work", "B", "~add~"]);
			assert.isTrue(event.stopImmediatePropagation.called, "Sortable would put it in the folder, where it may not be shown");
			assert.isNull(item(element, "B").getAttribute("slot"));
		});

		it("puts a favorite that is going up before the folder when over its header", async() =>
		{
			const element = await create();
			// Sortable has taken it to below the folder, now it is on its way back up
			folder(element, "Work").after(item(element, "B"));
			assert.deepEqual(rootOrder(element), ["blank", "A", "folder:Work", "B", "~add~"]);
			const event = dragOver(element, "B", 0.25);
			assert.deepEqual(rootOrder(element), ["blank", "A", "B", "folder:Work", "~add~"]);
			assert.isTrue(event.preventDefault.called);
		});

		it("takes a favorite out of the folder it is in, just above it, when over its header", async() =>
		{
			const element = await create();
			assert.equal(item(element, "C").getAttribute("slot"), "children", "in a folder it is in the children slot");
			dragOver(element, "C", 0.25);
			assert.deepEqual(rootOrder(element), ["blank", "A", "B", "C", "folder:Work", "~add~"]);
			assert.isNull(item(element, "C").getAttribute("slot"), "at the top level it is not, or it is not shown");
		});

		it("reorders wherever on the header the pointer is, except over the icon", async() =>
		{
			const element = await create();
			dragOver(element, "B", 0.1);
			assert.deepEqual(rootOrder(element), ["blank", "A", "folder:Work", "B", "~add~"], "the top of the header");
			assert.isNull(element["_dropTarget"]);
		});

		it("drops onto the folder at once when over its icon, and leaves the list as it is", async() =>
		{
			const element = await create();
			dragOver(element, "B", 0.9, true);
			assert.equal(element["_dropTarget"], folder(element, "Work"));
			assert.isTrue(folder(element, "Work").classList.contains("drop-onto"));
			assert.deepEqual(rootOrder(element), ["blank", "A", "B", "folder:Work", "~add~"], "the list is as it was");
		});

		it("does not drop 'No filters' onto a folder, but moves it past it", async() =>
		{
			const element = await create();
			dragOver(element, "blank", 0.5, true);
			assert.isNull(element["_dropTarget"]);
			assert.deepEqual(rootOrder(element), ["A", "B", "folder:Work", "blank", "~add~"], "it is moved past it instead");
		});

		it("leaves the event to Sortable when it is not over the folder's header", async() =>
		{
			const element = await create();
			const target = folder(element, "Work");
			const event = {currentTarget: target, composedPath: () => [item(element, "C"), target], clientY: 0,
				preventDefault: sinon.spy(), stopImmediatePropagation: sinon.spy()};
			(<any>Sortable).dragged = item(element, "B");
			(<any>element).handleFolderDragOver(event);
			assert.isFalse(event.stopImmediatePropagation.called);
		});

		it("keeps the slot right when Sortable moves a favorite between a folder and the top level", async() =>
		{
			const element = await create();
			folder(element, "Work").appendChild(item(element, "A"));
			(<any>element).handleChange({item: item(element, "A")});
			assert.equal(item(element, "A").getAttribute("slot"), "children");
			element.shadowRoot.querySelector("sl-tree").insertBefore(item(element, "A"), folder(element, "Work"));
			(<any>element).handleChange({item: item(element, "A")});
			assert.isNull(item(element, "A").getAttribute("slot"));
		});
	});

	describe("moving favorites around", () =>
	{
		// What Sortable leaves behind is the moved element, the menu works out the rest
		const drop = async(element, name, intoFolder? : string) =>
		{
			const moved = item(element, name);
			const to = intoFolder ? folder(element, intoFolder) : element.shadowRoot.querySelector("sl-tree");
			to.insertBefore(moved, to.querySelector(":scope > sl-tree-item.favorite") ?? to.querySelector("slot"));
			await (<any>element).handleSortEnd({item: moved});
		};

		it("puts a favorite in the folder it was dropped in", async() =>
		{
			prefs = {favorite_A: own("A", "Work"), favorite_B: own("B")};
			const element = await create();
			await drop(element, "B", "Work");
			assert.equal(prefs.favorite_B.folder, "Work");
			assert.equal(prefs.favorite_A.folder, "Work", "the others stay where they were");
		});

		it("takes a favorite out of a folder when it is dropped outside", async() =>
		{
			prefs = {favorite_A: own("A", "Work"), favorite_B: own("B", "Work")};
			const element = await create();
			await drop(element, "A");
			assert.notProperty(prefs.favorite_A, "folder");
			assert.equal(prefs.favorite_B.folder, "Work");
		});

		it("keeps where the user put a shared favorite to the user", async() =>
		{
			prefs = {favorite_S: shared("S", "Team"), favorite_A: own("A", "Work")};
			const element = await create();
			await drop(element, "S", "Work");
			assert.deepEqual(prefs.fav_folder_pref, {S: "Work"});
			assert.equal(prefs.favorite_S.folder, "Team", "the shared favorite itself is not changed");
		});

		it("stores the new order", async() =>
		{
			prefs = {favorite_A: own("A"), favorite_B: own("B"), fav_sort_pref: ["blank", "A", "B"]};
			const element = await create();
			const tree = element.shadowRoot.querySelector("sl-tree");
			tree.insertBefore(item(element, "B"), item(element, "A"));
			await (<any>element).handleSortEnd({item: item(element, "B")});
			assert.deepEqual(prefs.fav_sort_pref, ["blank", "B", "A"]);
		});

		it("makes a folder of two favorites when one is dropped onto the other", async() =>
		{
			prefs = {favorite_A: own("A"), favorite_B: own("B")};
			sinon.stub(Et2Dialog, "show_prompt").callsFake((callback : Function) => callback(Et2Dialog.OK_BUTTON, "Group"));
			const element = await create();
			element["_dropTarget"] = item(element, "A");
			await (<any>element).handleSortEnd({item: item(element, "B")});
			assert.equal(prefs.favorite_A.folder, "Group");
			assert.equal(prefs.favorite_B.folder, "Group");
		});

		it("makes no folder when its name is not given", async() =>
		{
			prefs = {favorite_A: own("A"), favorite_B: own("B")};
			sinon.stub(Et2Dialog, "show_prompt").callsFake((callback : Function) => callback(Et2Dialog.CANCEL_BUTTON, ""));
			const element = await create();
			element["_dropTarget"] = item(element, "A");
			await (<any>element).handleSortEnd({item: item(element, "B")});
			assert.notProperty(prefs.favorite_A, "folder");
			assert.notProperty(prefs.favorite_B, "folder");
		});

		it("puts a favorite dropped onto a folder in it", async() =>
		{
			prefs = {favorite_A: own("A", "Work"), favorite_B: own("B")};
			const element = await create();
			element["_dropTarget"] = folder(element, "Work");
			await (<any>element).handleSortEnd({item: item(element, "B")});
			assert.equal(prefs.favorite_B.folder, "Work");
		});

		it("renames a folder for the favorites in it", async() =>
		{
			prefs = {favorite_A: own("A", "Work"), favorite_S: shared("S", "Work")};
			sinon.stub(Et2Dialog, "show_prompt").callsFake((callback : Function) => callback(Et2Dialog.OK_BUTTON, "Office"));
			const element = await create();
			await (<any>element).handleFolderAction({detail: {item: {value: "rename"}}}, "Work");
			assert.equal(prefs.favorite_A.folder, "Office");
			assert.deepEqual(prefs.fav_folder_pref, {S: "Office"});
		});

		it("takes the favorites out of a folder that is removed", async() =>
		{
			prefs = {favorite_A: own("A", "Work"), favorite_S: shared("S", "Work")};
			const element = await create();
			await (<any>element).handleFolderAction({detail: {item: {value: "ungroup"}}}, "Work");
			assert.notProperty(prefs.favorite_A, "folder");
			assert.deepEqual(prefs.fav_folder_pref, {S: ""});
		});
	});

	describe("highlighting", () =>
	{
		const favorite = (name : string, state : object) : any => ({name: name, group: false, state: state});
		const highlighted = async(state : object) : Promise<string> =>
		{
			(<any>window).app[APP].fixState = fav => fav;
			const element = await create();
			await element.highlightFavorite(state);
			return element.activeFavorite;
		};

		it("highlights a favorite that matches the state exactly", async() =>
		{
			prefs = {favorite_A: favorite("A", {search: "a", filter: "open"}), favorite_B: favorite("B", {search: "b"})};
			assert.equal(await highlighted({search: "a", filter: "open"}), "A");
		});

		it("does not highlight a favorite with a different value", async() =>
		{
			prefs = {favorite_A: favorite("A", {search: "a", filter: "open"})};
			assert.equal(await highlighted({search: "a", filter: "done"}), "");
		});

		it("ignores empty column filters that only the favorite holds", async() =>
		{
			prefs = {favorite_A: favorite("A", {cat_id: "5", col_filter: {info_type: "", info_status: "", linked: null}})};
			assert.equal(await highlighted({cat_id: "5", col_filter: {}}), "A");
		});

		it("still tells apart a favorite with a column filter set", async() =>
		{
			prefs = {favorite_A: favorite("A", {cat_id: "5", col_filter: {info_status: "done", info_type: ""}})};
			assert.equal(await highlighted({cat_id: "5", col_filter: {}}), "");
			assert.equal(await highlighted({cat_id: "5", col_filter: {info_status: "done"}}), "A");
		});

		it("ignores empty values, like an empty selected list, that only the favorite holds", async() =>
		{
			prefs = {favorite_A: favorite("A", {cat_id: "5", selected: [], search: "", startdate: null})};
			assert.equal(await highlighted({cat_id: "5"}), "A");
		});

		it("does not highlight a favorite that sets something the state does not", async() =>
		{
			prefs = {favorite_A: favorite("A", {cat_id: "5", filter: "open", selected: []})};
			assert.equal(await highlighted({cat_id: "5", filter: ""}), "");
		});

		it("does not rule out a favorite because its columns differ", async() =>
		{
			prefs = {favorite_A: favorite("A", {cat_id: "5", selectcols: ["old_a", "old_b"]})};
			assert.equal(await highlighted({cat_id: "5", selectcols: ["new_a"]}), "A");
		});

		it("prefers the favorite whose columns also match", async() =>
		{
			prefs = {
				favorite_A: favorite("A", {cat_id: "5", selectcols: ["a"]}),
				favorite_B: favorite("B", {cat_id: "5", selectcols: ["b"]})
			};
			assert.equal(await highlighted({cat_id: "5", selectcols: ["b"]}), "B");
		});

		it("still rules out a favorite with other filters, whatever its columns", async() =>
		{
			prefs = {favorite_A: favorite("A", {cat_id: "6", selectcols: ["a"]})};
			assert.equal(await highlighted({cat_id: "5", selectcols: ["a"]}), "");
		});
	});
});
