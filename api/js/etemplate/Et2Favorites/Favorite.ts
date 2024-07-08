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
				name: egw.lang("No filters"),
				state: {},
				group: false
			}
		};

		// Load saved favorites
		let sortedList = [];
		let preferences : any = await egw.preference("*", app, true);
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
		egw.set_preference(app, 'fav_sort_pref', sortedList);
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
					egw.set_preference(app, 'fav_sort_pref', sortedList);
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
			window.app[app].setState(fav);
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
}