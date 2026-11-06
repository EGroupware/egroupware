import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {Favorite} from "../Favorite";

/**
 * Where favorites are listed (folders) and how a change of that is stored
 */

const APP = "testapp";

/** What the server would have in the user's preferences for the app */
let prefs : { [name : string] : any };
let setPreference : sinon.SinonStub;
const savedEgw = (<any>window).egw;

const own = (name : string, folder? : string) : any => ({name: name, group: false, state: {search: name}, ...(folder ? {folder} : {})});
const shared = (name : string, folder? : string) : any => ({name: name, group: -2, state: {search: name}, ...(folder ? {folder} : {})});

describe("Favorite folders", () =>
{
	beforeEach(() =>
	{
		prefs = {};
		setPreference = sinon.stub().callsFake((app, name, value) =>
		{
			if(value === "" || value === null)
			{
				delete prefs[name];
			}
			else
			{
				prefs[name] = value;
			}
		});
		const egw = {
			lang: i => i,
			preference: () => Promise.resolve(prefs),
			set_preference: setPreference,
			user: () => ({admin: true})
		};
		(<any>window).egw = Object.assign(() => egw, egw);
	});
	afterEach(() =>
	{
		(<any>window).egw = savedEgw;
	});

	describe("which folder a favorite is in", () =>
	{
		it("takes the folder of the user's own favorite from the favorite", () =>
		{
			assert.equal(Favorite.folderOf(own("A", "Work"), "A", {}), "Work");
		});

		it("ignores a placement for the user's own favorite", () =>
		{
			assert.equal(Favorite.folderOf(own("A", "Work"), "A", {A: "Other"}), "Work",
				"own favorites keep their folder themselves");
		});

		it("takes the folder a favorite is shared in, if the user did not place it", () =>
		{
			assert.equal(Favorite.folderOf(shared("S", "Team"), "S", {}), "Team");
		});

		it("takes where the user placed a shared favorite", () =>
		{
			assert.equal(Favorite.folderOf(shared("S", "Team"), "S", {S: "Mine"}), "Mine");
		});

		it("lets the user take a shared favorite out of its folder", () =>
		{
			assert.equal(Favorite.folderOf(shared("S", "Team"), "S", {S: ""}), "",
				"an empty placement is 'no folder', not 'not placed'");
		});

		it("never puts 'No filters' in a folder", () =>
		{
			assert.equal(Favorite.folderOf(<Favorite>{name: "No filters", state: {}, group: false, folder: "Work"}, "blank", {blank: "Work"}), "");
		});

		it("strips markup from folder names", () =>
		{
			assert.equal(Favorite.cleanFolder("  <b>Work</b> "), "Work");
		});
	});

	describe("loading", () =>
	{
		it("lists the folder of each favorite, with the user's placement of shared ones", async() =>
		{
			prefs = {
				favorite_A: own("A", "Work"),
				favorite_B: own("B"),
				favorite_S: shared("S", "Team"),
				favorite_T: shared("T", "Team"),
				fav_folder_pref: {T: ""}
			};
			const favorites = await Favorite.load((<any>window).egw(), APP);
			assert.equal(favorites.A.folder, "Work");
			assert.equal(favorites.B.folder ?? "", "");
			assert.equal(favorites.S.folder, "Team");
			assert.equal(favorites.T.folder, "", "T was taken out of its folder by the user");
			assert.deepEqual(Favorite.folderNames(favorites), ["Team", "Work"]);
		});

		it("does not change the preferences it read", async() =>
		{
			prefs = {favorite_T: shared("T", "Team"), fav_folder_pref: {T: "Mine"}};
			const before = JSON.stringify(prefs);
			await Favorite.load((<any>window).egw(), APP);
			assert.equal(JSON.stringify(prefs.favorite_T), JSON.stringify(JSON.parse(before).favorite_T),
				"the favorite as stored still has the folder it was shared in");
		});

		it("does not write the placements, even those of favorites it can't see", async() =>
		{
			// Reading must never write: the preferences can be incomplete for a moment
			prefs = {favorite_A: own("A"), fav_folder_pref: {gone: "Work"}};
			await Favorite.load((<any>window).egw(), APP);
			assert.isFalse(setPreference.calledWith(APP, Favorite.FOLDER_PREF), "fav_folder_pref was written while loading");
		});

		it("copes with the empty array PHP sends for no placements", async() =>
		{
			prefs = {favorite_S: shared("S", "Team"), fav_folder_pref: []};
			const favorites = await Favorite.load((<any>window).egw(), APP);
			assert.equal(favorites.S.folder, "Team");
		});
	});

	describe("moving into a folder", () =>
	{
		it("stores the folder with the user's own favorite", async() =>
		{
			prefs = {favorite_A: own("A")};
			await Favorite.setFolders((<any>window).egw(), APP, {A: "Work"});
			assert.equal(prefs.favorite_A.folder, "Work");
			assert.equal(prefs.favorite_A.name, "A", "the rest of the favorite is kept");
			assert.deepEqual(prefs.favorite_A.state, {search: "A"});
		});

		it("takes the folder off the user's own favorite", async() =>
		{
			prefs = {favorite_A: own("A", "Work")};
			await Favorite.setFolders((<any>window).egw(), APP, {A: ""});
			assert.notProperty(prefs.favorite_A, "folder");
		});

		it("keeps the placement of a shared favorite in the user's own preferences", async() =>
		{
			prefs = {favorite_S: shared("S", "Team")};
			await Favorite.setFolders((<any>window).egw(), APP, {S: "Mine"});
			assert.deepEqual(prefs.fav_folder_pref, {S: "Mine"});
			assert.equal(prefs.favorite_S.folder, "Team", "the shared favorite itself is not changed");
		});

		it("remembers that a shared favorite was taken out of its folder", async() =>
		{
			prefs = {favorite_S: shared("S", "Team")};
			await Favorite.setFolders((<any>window).egw(), APP, {S: ""});
			assert.deepEqual(prefs.fav_folder_pref, {S: ""});
		});

		it("forgets the placement when the shared favorite goes back to where it was shared", async() =>
		{
			prefs = {favorite_S: shared("S", "Team"), fav_folder_pref: {S: "Mine", other: "x"}, favorite_other: own("other", "x")};
			await Favorite.setFolders((<any>window).egw(), APP, {S: "Team"});
			assert.notProperty(prefs.fav_folder_pref, "S");
		});

		it("removes the placements preference when nothing is left in it", async() =>
		{
			prefs = {favorite_S: shared("S", "Team"), fav_folder_pref: {S: "Mine"}};
			await Favorite.setFolders((<any>window).egw(), APP, {S: "Team"});
			assert.notProperty(prefs, Favorite.FOLDER_PREF);
		});

		it("drops placements of favorites that are gone when it changes something", async() =>
		{
			prefs = {favorite_A: own("A"), fav_folder_pref: {gone: "Work"}};
			await Favorite.setFolders((<any>window).egw(), APP, {A: "Work"});
			assert.notProperty(prefs, Favorite.FOLDER_PREF);
		});

		it("does not move 'No filters'", async() =>
		{
			prefs = {};
			await Favorite.setFolders((<any>window).egw(), APP, {blank: "Work"});
			// (Loading the favorites also stores their order)
			const written = setPreference.getCalls().map(call => call.args[1]).filter(name => name !== "fav_sort_pref");
			assert.deepEqual(written, [], "something was stored for 'No filters'");
		});

		it("tells the widgets showing favorites", async() =>
		{
			prefs = {favorite_A: own("A")};
			const heard = sinon.spy();
			document.addEventListener("preferenceChange", heard);
			await Favorite.setFolders((<any>window).egw(), APP, {A: "Work"});
			document.removeEventListener("preferenceChange", heard);
			assert.equal(heard.callCount, 1);
			assert.equal(heard.firstCall.args[0].detail.application, APP);
		});
	});

	describe("who can change a favorite", () =>
	{
		it("lets users change their own", () =>
		{
			assert.isTrue(Favorite.canEdit((<any>window).egw(), <Favorite>own("A"), "A"));
		});

		it("lets an admin change a shared one found by its name", () =>
		{
			assert.isTrue(Favorite.canEdit((<any>window).egw(), <Favorite>shared("Some name"), "Some_name"));
		});

		it("does not offer changing a shared one that its name can't find", () =>
		{
			assert.isFalse(Favorite.canEdit((<any>window).egw(), <Favorite>shared("Some name"), "Some_name_ab12"),
				"the server finds a shared favorite by its name");
		});

		it("does not let other users change a shared one", () =>
		{
			const egw = {user: () => ({})};
			assert.isFalse(Favorite.canEdit(egw, <Favorite>shared("S"), "S"));
		});

		it("does not change 'No filters'", () =>
		{
			assert.isFalse(Favorite.canEdit((<any>window).egw(), <Favorite>own("blank"), "blank"));
		});
	});
});
