import {Et2Dialog} from "../Et2Dialog/Et2Dialog";

export class Favorite
{
	name : string
	state : object
	group : number | false

	// Favorites are prefixed in preferences
	public static readonly PREFIX = "favorite_";
	public static readonly ADD_VALUE = "~add~";

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
		const dialog = await Favorite._addPopup(egw, state);
		const [button, content] = await dialog.getComplete();
		if(button !== Et2Dialog.OK_BUTTON)
		{
			return;
		}
		Favorite._addFavorite(egw, appname, {...content, state: {...state}});
	}

	private static async _addPopup(egw, state)
	{
		// Add some controls if user is an admin
		const apps = egw.user('apps');
		const is_admin = (typeof apps['admin'] != "undefined");

		// Setup data
		let data = {
			content: {
				state: state || [],
				current_filters: []
			},
			readonlys: {
				group: !is_admin
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
		add_to_popup(data.content.state);
		data.content.current_filters = filter_list;

		// Create popup
		const dialog = new Et2Dialog(egw);
		dialog.transformAttributes({
			title: egw.lang("New favorite"),
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			width: 400,
			value: data,
			template: egw.webserverUrl + '/api/templates/default/add_favorite.xet'
		});
		document.body.appendChild(dialog);

		return dialog;
	}

	private static async _addFavorite(egw, appname, value)
	{
		if(!value.name)
		{
			return;
		}

		// Add to the list
		value.name = (<string>value.name).replace(/(<([^>]+)>)/ig, "");
		let safe_name = (<string>value.name).replace(/[^A-Za-z0-9-_]/g, "_");
		if(safe_name != value.name)
		{
			// Check if the label matches an existing preference, consider it an update
			let existing = egw.preference(Favorite.PREFIX + safe_name, appname);
			if(existing && existing.name !== value.name)
			{
				// Name mis-match, this is a new favorite with the same safe name
				safe_name += "_" + await egw.hashString(value.name);
			}
		}
		let favorite = {
			name: value.name,
			group: value.group || false,
			state: value.state
		};

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
					favorite.state
				]
			);
		}
		else
		{
			// Normal user - just save to preferences client side
			await egw.set_preference(appname, favorite_pref, favorite);
		}

		// Trigger event so widgets can update
		document.dispatchEvent(new CustomEvent("preferenceChange", {
			bubbles: true,
			detail: {
				application: appname,
				preference: favorite_pref
			}
		}));
	}
}