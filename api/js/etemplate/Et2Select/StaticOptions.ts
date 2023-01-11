/**
 * Some static options, no need to transfer them over and over.
 * We still need the same thing on the server side to validate, so they
 * have to match.  See Etemplate\Widget\Select::typeOptions()
 * The type specific legacy options wind up in attrs.other, but should be explicitly
 * defined and set.
 *
 * @param {type} widget
 */
import {sprintf} from "../../egw_action/egw_action_common";
import {Et2SelectReadonly} from "./Et2SelectReadonly";
import {find_select_options, SelectOption} from "./FindSelectOptions";
import {Et2Select, Et2SelectNumber, Et2WidgetWithSelect} from "./Et2Select";

export type Et2SelectWidgets = Et2Select | Et2WidgetWithSelect | Et2SelectReadonly;

// Export the Interface for TypeScript
type Constructor<T = {}> = new (...args : any[]) => T;

/**
 * Base class for things that have static options
 *
 * We keep static options separate and concatenate them in to allow for extra options without
 * overwriting them when we get static options from the server
 */

export const Et2StaticSelectMixin = <T extends Constructor<Et2WidgetWithSelect>>(superclass : T) =>
{
	class Et2StaticSelectOptions extends (superclass)
	{

		// Hold the static widget options separately so other options (like sent from server in sel_options) won't
		// conflict or be wiped out
		protected static_options : SelectOption[];

		// If widget needs to fetch options from server, we might want to wait for them
		protected fetchComplete : Promise<SelectOption[] | void>;

		constructor(...args)
		{
			super(...args);

			this.static_options = [];
			this.fetchComplete = Promise.resolve();

			// Trigger the options to get rendered into the DOM
			this.requestUpdate("select_options");
		}

		get select_options() : SelectOption[]
		{
			// @ts-ignore
			const options = super.select_options || [];
			// make sure result is unique

			return [...new Map([...options, ...(this.static_options || [])].map(item =>
				[item.value, item])).values()];

		}

		set select_options(new_options)
		{
			// @ts-ignore IDE doesn't recognise property
			super.select_options = new_options;
		}

		set_static_options(new_static_options)
		{
			this.static_options = new_static_options;
			this.requestUpdate("select_options");
		}

		/**
		 * Override the parent fix_bad_value() to wait for server-side options
		 * to come back before we check to see if the value is not there.
		 */
		fix_bad_value()
		{
			this.fetchComplete.then(() =>
			{
				// @ts-ignore Doesn't know it's an Et2Select
				if(typeof super.fix_bad_value == "function")
				{
					// @ts-ignore Doesn't know it's an Et2Select
					super.fix_bad_value();
				}
			})
		}
	}

	return Et2StaticSelectOptions;
}

/**
 * Some options change, or are too complicated to have twice, so we get the
 * options from the server once, then keep them to use if they're needed again.
 * We use the options string to keep the different possibilities (eg. categories
 * for different apps) separate.
 *
 * @param {et2_selectbox} widget Selectbox we're looking at
 * @param {string} options_string
 * @param {Object} attrs Widget attributes (not yet fully set)
 * @param {boolean} return_promise true: always return a promise
 * @returns {Object[]|Promise<Object[]>} Array of options, or empty and they'll get filled in later, or Promise
 */
export class StaticOptions
{
	cached_server_side(widget : Et2SelectWidgets, type : string, options_string, return_promise? : boolean) : SelectOption[]|Promise<SelectOption[]>
	{
		// normalize options by removing trailing commas
		options_string = options_string.replace(/,+$/, '');

		const cache_id = widget.nodeName + '_' + options_string;
		const cache_owner = widget.egw().getCache('Et2Select');
		let cache = cache_owner[cache_id];

		if(typeof cache === 'undefined')
		{
			// Fetch with json instead of jsonq because there may be more than
			// one widget listening for the response by the time it gets back,
			// and we can't do that when it's queued.
			const req = widget.egw().json(
				'EGroupware\\Api\\Etemplate\\Widget\\Select::ajax_get_options',
				[type, options_string, widget.value]
			).sendRequest();
			if(typeof cache === 'undefined')
			{
				cache_owner[cache_id] = req;
			}
			cache = req;
		}
		if(typeof cache.then === 'function')
		{
			// pending, wait for it
			const promise = cache.then((response) =>
			{
				cache = cache_owner[cache_id] = response.response[0].data || undefined;

				if (return_promise) return cache;

				// Set select_options in attributes in case we get a response before
				// the widget is finished loading (otherwise it will re-set to {})
				//widget.select_options = cache;

				// Avoid errors if widget is destroyed before the timeout
				if(widget && typeof widget.id !== 'undefined')
				{
					if(typeof widget.set_static_options == "function")
					{
						widget.set_static_options(cache);
					}
					else if(typeof widget.set_select_options == "function")
					{
						widget.set_select_options(find_select_options(widget, {}, cache));
					}
				}
			});
			return return_promise ? promise : [];
		}
		else
		{
			// Check that the value is in there
			// Make sure we are not requesting server for an empty value option or
			// other widgets but select-timezone as server won't find anything and
			// it will fall into an infinitive loop, e.g. select-cat widget.
			if(widget.value && widget.value != "" && widget.value != "0" && type == "select-timezone")
			{
				var missing_option = true;
				for(var i = 0; i < cache.length && missing_option; i++)
				{
					if(cache[i].value == widget.value)
					{
						missing_option = false;
					}
				}
				// Try again - ask the server with the current value this time
				if(missing_option)
				{
					delete cache_owner[cache_id];
					return this.cached_server_side(widget, type, options_string);
				}
				else
				{
					if(widget.value && widget && widget.get_value() !== widget.value)
					{
						egw.window.setTimeout(function()
						{
							// Avoid errors if widget is destroyed before the timeout
							if(this.widget && typeof this.widget.id !== 'undefined')
							{
								this.widget.set_value(this.widget.options.value);
							}
						}.bind({widget: widget}), 1);
					}
				}
			}
			return return_promise ? Promise.resolve(cache) : cache;
		}
	}

	priority(widget : Et2SelectWidgets) : SelectOption[]
	{
		return [
			{value: "1", label: 'low'},
			{value: "2", label: 'normal'},
			{value: "3", label: 'high'},
			{value: "0", label: 'undefined'}
		];
	}

	bool(widget : Et2SelectWidgets) : SelectOption[]
	{
		return [
			{value: "0", label: 'no'},
			{value: "1", label: 'yes'}
		];
	}

	month(widget : Et2SelectWidgets) : SelectOption[]
	{
		return [
			{value: "1", label: 'January'},
			{value: "2", label: 'February'},
			{value: "3", label: 'March'},
			{value: "4", label: 'April'},
			{value: "5", label: 'May'},
			{value: "6", label: 'June'},
			{value: "7", label: 'July'},
			{value: "8", label: 'August'},
			{value: "9", label: 'September'},
			{value: "10", label: 'October'},
			{value: "11", label: 'November'},
			{value: "12", label: 'December'}
		];
	}

	number(widget : Et2SelectWidgets, attrs = {
		min: undefined,
		max: undefined,
		interval: undefined,
		format: undefined
	}) : SelectOption[]
	{

		var options = [];
		var min = attrs.min ?? parseFloat(widget.min);
		var max = attrs.max ?? parseFloat(widget.max);
		var interval = attrs.interval ?? parseFloat(widget.interval);
		var format = attrs.format ?? '%d';

		// leading zero specified in interval
		if(widget.leading_zero && widget.leading_zero[0] == '0')
		{
			format = '%0' + ('' + interval).length + 'd';
		}
		// Suffix
		if(widget.suffix)
		{
			format += widget.egw().lang(widget.suffix);
		}

		// Avoid infinite loop if interval is the wrong direction
		if((min <= max) != (interval > 0))
		{
			interval = -interval;
		}

		for(var i = 0, n = min; n <= max && i <= 100; n += interval, ++i)
		{
			options.push({value: "" + n, label: sprintf(format, n)});
		}
		return options;
	}

	percent(widget : Et2SelectNumber) : SelectOption[]
	{
		return this.number(widget);
	}

	year(widget : Et2SelectWidgets, attrs?) : SelectOption[]
	{
		if(typeof attrs != 'object')
		{
			attrs = {}
		}
		var t = new Date();
		attrs.min = t.getFullYear() + parseInt(widget.min);
		attrs.max = t.getFullYear() + parseInt(widget.max);
		return this.number(widget, attrs);
	}

	day(widget : Et2SelectWidgets, attrs) : SelectOption[]
	{
		attrs.other = [1, 31, 1];
		return this.number(widget, attrs);
	}

	hour(widget : Et2SelectWidgets, attrs) : SelectOption[]
	{
		var options = [];
		var timeformat = widget.egw().preference('common', 'timeformat');
		for(var h = 0; h <= 23; ++h)
		{
			options.push({
				value: h,
				label: timeformat == 12 ?
					   ((12 ? h % 12 : 12) + ' ' + (h < 12 ? egw.lang('am') : egw.lang('pm'))) :
					   sprintf('%02d', h)
			});
		}
		return options;
	}

	app(widget : Et2SelectWidgets | Et2Select, attrs) : SelectOption[] | Promise<SelectOption[]>
	{
		var options = ',' + (attrs.other || []).join(',');
		return this.cached_server_side(widget, 'select-app', options);
	}

	cat(widget : Et2SelectWidgets) : Promise<SelectOption[]>
	{
		var options = [widget.globalCategories, /*?*/, widget.application, widget.parentCat];

		if(typeof options[3] == 'undefined')
		{
			options[3] = widget.application ||
				// When the widget is first created, it doesn't have a parent and can't find it's instanceManager
				(widget.getInstanceManager() && widget.getInstanceManager().app) ||
				widget.egw().app_name();
		}
		return <Promise<SelectOption[]>>this.cached_server_side(widget, 'select-cat', options.join(','), true);
	}

	country(widget : Et2SelectWidgets, attrs, return_promise) : SelectOption[]|Promise<SelectOption[]>
	{
		var options = ',';
		return this.cached_server_side(widget, 'select-country', options, return_promise);
	}

	state(widget : Et2SelectWidgets, attrs) : SelectOption[] | Promise<SelectOption[]>
	{
		var options = attrs.country_code ? attrs.country_code : 'de';
		return this.cached_server_side(widget, 'select-state', options);
	}

	dow(widget : Et2SelectWidgets, attrs) : SelectOption[] | Promise<SelectOption[]>
	{
		var options = ',' + (attrs.other || []).join(',');
		return this.cached_server_side(widget, 'select-dow', options);
	}

	lang(widget : Et2SelectWidgets, attrs) : SelectOption[] | Promise<SelectOption[]>
	{
		var options = ',' + (attrs.other || []).join(',');
		return this.cached_server_side(widget, 'select-lang', options);
	}

	timezone(widget : Et2SelectWidgets, attrs) : SelectOption[] | Promise<SelectOption[]>
	{
		var options = ',' + (attrs.other || []).join(',');
		return this.cached_server_side(widget, 'select-timezone', options);
	}
}