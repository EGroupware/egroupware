import {Et2Dialog} from "../Et2Dialog/Et2Dialog";

export class Favorite
{
	name : string
	state : object
	group : number | false
	/** Name of the folder the favorite is listed in, resolved from the favorite and the user's own placement */
	folder? : string

	// Favorites are prefixed in preferences
	public static readonly PREFIX = "favorite_";
	public static readonly ADD_VALUE = "~add~";
	/**
	 * Preference holding where the user wants favorites listed, {<favorite key>: <folder>}.
	 * Needed for favorites shared with a group, as the user cannot store their own copy of those.
	 * An empty folder means "not in a folder", even if the shared favorite is in one by default.
	 */
	public static readonly FOLDER_PREF = "fav_folder_pref";

	/**
	 * Load favorites from preferences
	 *
	 * @param app String Load favorites from this application
	 */
	static async load(egw, app : string) : Promise<{ [name : string] : Favorite }>
	{
		// Default blank filter
		let favorites : { [name : string] : Favorite } = {
			'blank': {
				name: window.egw.lang("No filters"),
				state: {},
				group: false
			}
		};

		// Load saved favorites
		let sortedList = [];
		let preferences : any = await window.egw.preference("*", app, true);
		const folderPref = Favorite.folderPreference(preferences[Favorite.FOLDER_PREF]);
		for(let pref_name in preferences)
		{
			if(pref_name.indexOf(Favorite.PREFIX) == 0 && typeof preferences[pref_name] == 'object')
			{
				let name = pref_name.substr(Favorite.PREFIX.length);
				favorites[name] = preferences[pref_name];
				// Keep older favorites working - they used to store nm filters in 'filters',not state
				if(preferences[pref_name]["filters"])
				{
					favorites[pref_name]["state"] = preferences[pref_name]["filters"];
				}
			}
			if(pref_name == 'fav_sort_pref')
			{
				sortedList = preferences[pref_name];
				//Make sure sorted list is always an array, seems some old fav are not array
				if(!Array.isArray(sortedList) && typeof sortedList == "string")
				{
					// @ts-ignore What's the point of a typecheck if IDE still errors
					sortedList = sortedList.split(',');
				}
			}
		}

		// Work out which folder each favorite is shown in.  Copies, so the cached preference is not changed.
		for(let name in favorites)
		{
			const folder = Favorite.folderOf(favorites[name], name, folderPref);
			if(folder !== (favorites[name].folder ?? ""))
			{
				favorites[name] = {...favorites[name], folder: folder};
			}
		}
		for(let name in favorites)
		{
			if(sortedList.indexOf(name) < 0)
			{
				sortedList.push(name);
			}
		}
		window.egw.set_preference(app, 'fav_sort_pref', sortedList);
		if(sortedList.length > 0)
		{
			let sortedListObj = {};

			for(let i = 0; i < sortedList.length; i++)
			{
				if(typeof favorites[sortedList[i]] != 'undefined')
				{
					sortedListObj[sortedList[i]] = favorites[sortedList[i]];
				}
				else
				{
					sortedList.splice(i, 1);
					window.egw.set_preference(app, 'fav_sort_pref', sortedList);
				}
			}
			favorites = Object.assign(sortedListObj, favorites);
		}

		return favorites;
	}

	/**
	 * The user's folder placements as a plain object
	 *
	 * An empty PHP array arrives as [], and anything else unexpected is treated as no placements.
	 */
	protected static folderPreference(value : any) : { [key : string] : string }
	{
		return value && typeof value == "object" && !Array.isArray(value) ? {...value} : {};
	}

	/**
	 * Store the user's placements, or forget them all if there are none left
	 */
	protected static saveFolderPreference(egw, app : string, folderPref : { [key : string] : string })
	{
		egw.set_preference(app, Favorite.FOLDER_PREF, Object.keys(folderPref).length ? folderPref : "");
	}

	/**
	 * Strip markup and whitespace from a folder name
	 */
	public static cleanFolder(name : any) : string
	{
		return ("" + (name ?? "")).replace(/(<([^>]+)>)/ig, "").trim();
	}

	/**
	 * Is the favorite shared with a group (or everybody), so it is not the user's to change?
	 */
	public static isShared(favorite : Favorite) : boolean
	{
		return !!favorite?.group;
	}

	public static isAdmin(egw) : boolean
	{
		return typeof egw.user('apps')?.['admin'] != "undefined";
	}

	/**
	 * Which folder a favorite is listed in
	 *
	 * The user's own favorites carry their folder themselves.  A shared favorite is listed where the user
	 * placed it, otherwise in the folder it was shared in.  "No filters" is never in a folder.
	 *
	 * @param favorite
	 * @param key preference name of the favorite, without the prefix
	 * @param folderPref the user's placements
	 */
	public static folderOf(favorite : Favorite, key : string, folderPref : { [key : string] : string }) : string
	{
		if(key === "blank")
		{
			return "";
		}
		if(Favorite.isShared(favorite) && typeof folderPref[key] == "string")
		{
			return Favorite.cleanFolder(folderPref[key]);
		}
		return Favorite.cleanFolder(favorite.folder);
	}

	/**
	 * Names of all the folders in use, sorted
	 */
	public static folderNames(favorites : { [name : string] : Favorite }) : string[]
	{
		return [...new Set(Object.values(favorites).map(f => f.folder).filter(f => !!f))].sort((a, b) => a.localeCompare(b));
	}

	/**
	 * Can the user change the favorite itself (name, filters, audience, default folder)?
	 *
	 * Shared favorites are only the admin's, and only if its preference name can be worked out from its name,
	 * as that is how the server finds it again.
	 */
	public static canEdit(egw, favorite : Favorite, key : string) : boolean
	{
		if(key === "blank" || key === Favorite.ADD_VALUE)
		{
			return false;
		}
		return !Favorite.isShared(favorite) || Favorite.isAdmin(egw) && key === Favorite.safeName(favorite.name);
	}

	/**
	 * Part of a favorite's name that can be used as preference name
	 */
	public static safeName(name : string) : string
	{
		return name.replace(/[^A-Za-z0-9-_]/g, "_");
	}

	/**
	 * Let widgets showing favorites know something changed
	 */
	public static notify(app : string, preference : string)
	{
		document.dispatchEvent(new CustomEvent("preferenceChange", {
			bubbles: true,
			detail: {
				application: app,
				preference: preference
			}
		}));
	}

	/**
	 * Move favorites into folders, as a change only the user sees
	 *
	 * A favorite of the user's own stores its folder.  A shared favorite is not the user's to change, so the
	 * placement is kept in the user's own preferences instead.
	 *
	 * @param egw
	 * @param app
	 * @param changes {<favorite key>: <folder name, empty for none>}
	 */
	static async setFolders(egw, app : string, changes : { [key : string] : string })
	{
		const favorites = await Favorite.load(egw, app);
		const preferences : any = await egw.preference("*", app, true);
		const folderPref = Favorite.folderPreference(preferences[Favorite.FOLDER_PREF]);
		let placementsChanged = false;
		let changed = false;

		// Placements of favorites that are gone (deleted, or no longer shared) are of no use.  Only dropped here,
		// when the user changes something, so reading favorites never writes.
		Object.keys(folderPref).filter(key => typeof favorites[key] == "undefined").forEach(key =>
		{
			delete folderPref[key];
			placementsChanged = true;
		});

		for(const key of Object.keys(changes))
		{
			const favorite = favorites[key];
			if(!favorite || key === "blank")
			{
				continue;
			}
			const folder = Favorite.cleanFolder(changes[key]);
			if(Favorite.isShared(favorite))
			{
				// Only keep a placement that differs from where the favorite is shared
				if(folder === Favorite.cleanFolder(preferences[Favorite.PREFIX + key]?.folder))
				{
					delete folderPref[key];
				}
				else
				{
					folderPref[key] = folder;
				}
				placementsChanged = true;
			}
			else
			{
				const {folder: _old, ...own} = favorite;
				egw.set_preference(app, Favorite.PREFIX + key, folder ? {...own, folder: folder} : own);
				changed = true;
			}
		}
		if(placementsChanged)
		{
			Favorite.saveFolderPreference(egw, app, folderPref);
		}
		if(placementsChanged || changed)
		{
			Favorite.notify(app, placementsChanged ? Favorite.FOLDER_PREF : "favorite");
		}
	}

	static async applyFavorite(egw, app : string, favoriteName : string)
	{
		const favorites = await Favorite.load(egw, app);
		let fav = favoriteName == "blank" ? {} : favorites[favoriteName] ?? {};
		// use app[appname].setState if available to allow app to overwrite it (eg. change to non-listview in calendar)
		//@ts-ignore TS doesn't know about window.app
		if(typeof window.app[app] != 'undefined')
		{
			//@ts-ignore TS doesn't know about window.app
			window.app[app].setState(egw.deepExtend({},fav));
		}
	}

	static async remove(egw, app, favoriteName)
	{
		const favorites = await Favorite.load(egw, app);
		let fav = favorites[favoriteName];
		if(!fav)
		{
			return Promise.reject("No such favorite");
		}

		return egw.request("EGroupware\\Api\\Framework::ajax_set_favorite",
			[app, favoriteName, "delete", "" + fav.group, '']);
	}

	static async add(egw, appname, state)
	{
		const favorites = await Favorite.load(egw, appname);
		const dialog = await Favorite._popup(egw, {
			title: egw.lang("New favorite"),
			content: {state: state || [], folder: ""},
			readonlys: {group: !Favorite.isAdmin(egw)},
			folders: Favorite.folderNames(favorites)
		});
		const [button, content] = await dialog.getComplete();
		if(button !== Et2Dialog.OK_BUTTON)
		{
			return;
		}
		Favorite._addFavorite(egw, appname, {...content, state: {...state}});
	}

	/**
	 * Change a favorite, or just the folder it is in
	 *
	 * @param egw
	 * @param app
	 * @param key preference name of the favorite, without the prefix
	 * @param moveOnly only ask for the folder, and keep the change to the user.  Otherwise the favorite itself
	 *  is changed, which for a shared favorite also changes the folder it is shown in for everybody it is shared with.
	 */
	static async edit(egw, app : string, key : string, moveOnly : boolean = false)
	{
		const favorites = await Favorite.load(egw, app);
		const favorite = favorites[key];
		if(!favorite || key === "blank")
		{
			return;
		}
		const shared = Favorite.isShared(favorite);
		const dialog = await Favorite._popup(egw, {
			title: egw.lang(moveOnly ? "Move to folder" : "Edit favorite"),
			content: {
				name: favorite.name,
				group: typeof favorite.group == "boolean" ? "" : favorite.group,
				folder: favorite.folder ?? "",
				state: favorite.state ?? {}
			},
			// The audience can't be changed, and a shared favorite is found by its name
			readonlys: {name: moveOnly || shared, group: true},
			folders: Favorite.folderNames(favorites)
		});
		const [button, content] = <[any, any]>await dialog.getComplete();
		if(button !== Et2Dialog.OK_BUTTON)
		{
			return;
		}
		const folder = Favorite.cleanFolder(content.folder);
		if(moveOnly)
		{
			return Favorite.setFolders(egw, app, {[key]: folder});
		}
		if(shared)
		{
			// Written server side, in the preferences of the group it is shared with
			await egw.request("EGroupware\\Api\\Framework::ajax_set_favorite",
				[app, favorite.name, "add", favorite.group, favorite.state ?? (<any>favorite).filters ?? {}, folder]);
			// The admin's own placement of it would hide the change
			const preferences : any = await egw.preference("*", app, true);
			const folderPref = Favorite.folderPreference(preferences[Favorite.FOLDER_PREF]);
			if(typeof folderPref[key] != "undefined")
			{
				delete folderPref[key];
				Favorite.saveFolderPreference(egw, app, folderPref);
			}
			Favorite.notify(app, Favorite.PREFIX + key);
			return;
		}
		const name = Favorite.cleanFolder(content.name) || favorite.name;
		const {folder: _old, ...own} = favorite;
		const changed = {...own, name: name, ...(folder ? {folder: folder} : {})};
		if(name === favorite.name)
		{
			egw.set_preference(app, Favorite.PREFIX + key, changed);
			Favorite.notify(app, Favorite.PREFIX + key);
			return;
		}
		// A new name is a new preference.  Keep its place in the list.
		const newKey = await Favorite._uniqueKey(egw, app, name);
		const preferences : any = await egw.preference("*", app, true);
		let sortedList = preferences['fav_sort_pref'];
		sortedList = Array.isArray(sortedList) ? sortedList : (typeof sortedList == "string" ? sortedList.split(',') : []);
		if(newKey !== key)
		{
			egw.set_preference(app, Favorite.PREFIX + key, "");
			egw.set_preference(app, 'fav_sort_pref', sortedList.map(k => k === key ? newKey : k));
		}
		egw.set_preference(app, Favorite.PREFIX + newKey, changed);
		Favorite.notify(app, Favorite.PREFIX + newKey);
	}

	/**
	 * Show the dialog for adding or changing a favorite
	 */
	private static async _popup(egw, options : {
		title : string, content : object, readonlys : object, folders : string[]
	})
	{
		// Setup data
		let data = {
			content: {
				current_filters: [],
				...options.content
			},
			readonlys: options.readonlys,
			sel_options: {
				folder: options.folders.map(folder => ({value: folder, label: folder}))
			}
		};


		// Show current set filters (more for debug than user)
		let filter_list = [];
		let add_to_popup = function(arr, inset = "")
		{
			Object.keys(arr).forEach((index) =>
			{
				let filter = arr[index];
				filter_list.push({
					label: inset + index.toString(),
					value: (typeof filter != "object" ? "" + filter : "")
				});
				if(typeof filter == "object" && filter != null)
				{
					add_to_popup(filter, inset + "    ");
				}
			});
		};
		add_to_popup(data.content["state"]);
		data.content.current_filters = filter_list;

		// Create popup
		const dialog = new Et2Dialog(egw);
		dialog.transformAttributes({
			title: options.title,
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			width: 400,
			value: data,
			template: egw.webserverUrl + '/api/templates/default/add_favorite.xet'
		});
		document.body.appendChild(dialog);

		return dialog;
	}

	/**
	 * Preference name for a favorite name
	 *
	 * The name without characters that can't be in an ID.  If another favorite already uses it,
	 * a hash of the full name is added.
	 */
	private static async _uniqueKey(egw, appname : string, name : string) : Promise<string>
	{
		let safe_name = Favorite.safeName(name);
		if(safe_name != name)
		{
			// Check if the label matches an existing preference, consider it an update
			let existing = egw.preference(Favorite.PREFIX + safe_name, appname);
			if(existing && existing.name !== name)
			{
				// Name mis-match, this is a new favorite with the same safe name
				safe_name += "_" + await egw.hashString(name);
			}
		}
		return safe_name;
	}

	private static async _addFavorite(egw, appname, value)
	{
		if(!value.name)
		{
			return;
		}

		// Add to the list
		value.name = (<string>value.name).replace(/(<([^>]+)>)/ig, "");
		const safe_name = await Favorite._uniqueKey(egw, appname, value.name);
		const folder = Favorite.cleanFolder(value.folder);
		let favorite : any = {
			name: value.name,
			group: value.group || false,
			state: value.state
		};
		if(folder)
		{
			favorite.folder = folder;
		}

		let favorite_pref = Favorite.PREFIX + safe_name;

		// Save to preferences
		if(typeof value.group != "undefined" && value.group != '')
		{
			// Admin stuff - save preference server side
			await egw.jsonq('EGroupware\\Api\\Framework::ajax_set_favorite',
				[
					appname,
					favorite.name,
					"add",
					favorite.group,
					favorite.state,
					folder
				]
			);
		}
		else
		{
			// Normal user - just save to preferences client side
			await egw.set_preference(appname, favorite_pref, favorite);
		}

		// Trigger event so widgets can update
		Favorite.notify(appname, favorite_pref);
	}
}