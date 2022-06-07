/**
 * Static holiday cache
 * access through holidays(year)
 */
import {egw} from "../../jsapi/egw_global";

let _holiday_cache = {};

/**
 * Get a list of holidays for the given year
 *
 * Returns either a list of holidays indexed by date, in Ymd format:
 * {20001225: [{day: 14, month: 2, occurence: 2021, name: "Valentinstag"}]}
 * or a promise that resolves with the list.
 *
 * No need to cache the results, we do it here.
 *
 * @param year
 * @returns Promise<{[key: string]: Array<object>}>|{[key: string]: Array<object>}
 */
export function holidays(year) : Promise<{ [key : string] : Array<object> }> | { [key : string] : Array<object> }
{
	// No country selected causes error, so skip if it's missing
	if(!egw || !egw.preference('country', 'common'))
	{
		return {};
	}

	if(typeof _holiday_cache[year] === 'undefined')
	{
		// Fetch with json instead of jsonq because there may be more than
		// one widget listening for the response by the time it gets back,
		// and we can't do that when it's queued.
		_holiday_cache[year] = window.fetch(
			egw.link('/calendar/holidays.php', {year: year})
		).then((response) =>
		{
			return _holiday_cache[year] = response.json();
		});
	}
	return _holiday_cache[year];
}
