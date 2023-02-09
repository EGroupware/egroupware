/**
 * EGroupware eTemplate2 - JS Favorite widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2022
 */

import {Et2DropdownButton} from "../Et2DropdownButton/Et2DropdownButton";
import {css, html, PropertyValues, TemplateResult} from "@lion/core";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import {et2_INextmatchHeader, et2_nextmatch} from "../et2_extension_nextmatch";
import {Et2Image} from "../Et2Image/Et2Image";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {SlMenuItem} from "@shoelace-style/shoelace";
import {cssImage} from "../Et2Widget/Et2Widget";

/**
 * Favorites widget, designed for use in the nextmatch header
 *
 * The primary control is a split/dropdown button.  Clicking on the left side of the button filters the
 * nextmatch list by the user's default filter.  The right side of the button gives a list of
 * saved filters, pulled from preferences.  Clicking a filter from the dropdown list sets the
 * filters as saved.
 *
 * Favorites can also automatically be shown in the sidebox, using the special ID favorite_sidebox.
 * Use the following code to generate the sidebox section:
 *  display_sidebox($appname,lang('Favorites'),array(
 *	array(
 *		'no_lang' => true,
 *		'text'=>'<span id="favorite_sidebox"/>',
 *		'link'=>false,
 *		'icon' => false
 *	)
 * ));
 * This sidebox list will be automatically generated and kept up to date.
 *
 *
 * Favorites are implemented by saving the values for [column] filters.  Filters are stored
 * in preferences, with the name favorite_<name>.  The favorite favorite used for clicking on
 * the filter button is stored in nextmatch-<columnselection_pref>-favorite.
 *
 */
export class Et2Favorites extends Et2DropdownButton implements et2_INextmatchHeader
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			  :host {
				min-width: 8ex;
			  }

			  et2-image {
				${egwIsMobile() ?
				  css`
					width: 6ex;
					margin-top: 1.2ex;
				  ` :
				  css`
					width: 20px;
					margin-top: 4px;
				  `
				}
			  }

			  et2-image[src="trash"] {
				display: none;
			  }

			  sl-menu {
				min-width: 15em;
			  }

			  sl-menu-item:hover et2-image[src="trash"] {
				display: initial;
			  }

			  /* Add star icons - radio button is already in prefix */

			  sl-menu-item::part(base) {
				background-image: ${cssImage("fav_filter")};
				background-repeat: no-repeat;
				background-size: 16px 16px;
				background-position: 5px center;
			  }

			  sl-menu-item[checked]::part(base) {
				background-image: ${cssImage("favorites")};
			  }

			  sl-menu-item:last-child::part(base) {
				background-image: none;
			  }
			`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			// Where we keep the "default" preference
			defaultPref: {type: String},
			// Application to show favorites for
			app: {type: String},
			// Extra filters to include in the saved favorite
			filters: {type: Object}
		};
	}

	// Favorites are prefixed in preferences
	public static readonly PREFIX = "favorite_";
	protected static readonly ADD_VALUE = "~add~";

	private favSortedList : any = [];
	private _preferred : string;
	private _nextmatch : et2_nextmatch;

	constructor()
	{

		super();
		this.__statustext = "Favorite queries";
		this._handleRadio = this._handleRadio.bind(this);
		this._handleDelete = this._handleDelete.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		if(!this.id)
		{
			this.id = "favorite";
		}

		this._preferred = <string>this.egw().preference(this.defaultPref, this.app);

		// Need to wait until update is done and these exist
		this.updateComplete.then(() =>
		{
			if(this.buttonNode)
			{
				let img = new Et2Image();
				img.src = "fav_filter";
				this.buttonNode.append(img);
			}
		});
	}

	set select_options(_new_options : SelectOption[])
	{
		// We don't actually want your options, thanks.
	}

	get select_options() : SelectOption[]
	{
		if(this.__select_options.length)
		{
			return this.__select_options;
		}
	}

	get preferred() : string
	{
		return this._preferred;
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		let radio = html`<input type="radio" slot="prefix" name="favorite" value="${option.value}"
                                ?checked="${option.value == this._preferred}"
                                @change=${this._handleRadio}
                                title="${this.egw().lang('Set as default')}"/>`;

		//@ts-ignore TS doesn't know about window.app
		let is_admin = (typeof this.egw().app('admin') != "undefined");
		//@ts-ignore option.group does not exist
		let icon = (option.group !== false && !is_admin || option.value == 'blank') ? "" : html`
            <et2-image slot="suffix" src=${"trash"} icon @click=${this._handleDelete}
                       statustext="${this.egw().lang("Delete")}"></et2-image>`;

		return html`
            <sl-menu-item value="${option.value}" ?checked="${option.value == this._preferred}">
                ${option.value !== Et2Favorites.ADD_VALUE ? radio : ""}
                ${icon}
                ${option.label}
            </sl-menu-item>`;
	}


	/** @param {import('@lion/core').PropertyValues } changedProperties */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(changedProperties.has("app"))
		{
			this._preferred = <string>this.egw().preference(this.defaultPref, this.app);
			this.__select_options = this._load_favorites(this.app);
			this.requestUpdate("select_options");
		}
	}

	/**
	 * Load favorites from preferences
	 *
	 * @param app String Load favorites from this application
	 */
	_load_favorites(app)
	{

		// Default blank filter
		let favorites : any = {
			'blank': {
				name: this.egw().lang("No filters"),
				state: {}
			}
		};

		// Load saved favorites
		this.favSortedList = [];
		let preferences : any = this.egw().preference("*", app);
		for(let pref_name in preferences)
		{
			if(pref_name.indexOf(Et2Favorites.PREFIX) == 0 && typeof preferences[pref_name] == 'object')
			{
				let name = pref_name.substr(Et2Favorites.PREFIX.length);
				favorites[name] = preferences[pref_name];
				// Keep older favorites working - they used to store nm filters in 'filters',not state
				if(preferences[pref_name]["filters"])
				{
					favorites[pref_name]["state"] = preferences[pref_name]["filters"];
				}
			}
			if(pref_name == 'fav_sort_pref')
			{
				this.favSortedList = preferences[pref_name];
				//Make sure sorted list is always an array, seems some old fav are not array
				if(!Array.isArray(this.favSortedList))
				{
					this.favSortedList = this.favSortedList.split(',');
				}
			}
		}

		for(let name in favorites)
		{
			if(this.favSortedList.indexOf(name) < 0)
			{
				this.favSortedList.push(name);
			}
		}
		this.egw().set_preference(this.app, 'fav_sort_pref', this.favSortedList);
		if(this.favSortedList.length > 0)
		{
			let sortedListObj = {};

			for(let i = 0; i < this.favSortedList.length; i++)
			{
				if(typeof favorites[this.favSortedList[i]] != 'undefined')
				{
					sortedListObj[this.favSortedList[i]] = favorites[this.favSortedList[i]];
				}
				else
				{
					this.favSortedList.splice(i, 1);
					this.egw().set_preference(this.app, 'fav_sort_pref', this.favSortedList);
				}
			}
			favorites = Object.assign(sortedListObj, favorites);
		}

		let options = [];
		Object.keys(favorites).forEach((name) =>
		{
			options.push(Object.assign({value: name, label: favorites[name].name || name}, favorites[name]));
		})
		// Only add 'Add current' if we have a nextmatch
		if(this._nextmatch)
		{
			options.push({value: Et2Favorites.ADD_VALUE, label: this.egw().lang('Add current')});
		}

		this.requestUpdate("select_options");
		return options;
	}

	public load_favorites(app)
	{
		this.__select_options = this._load_favorites(app);
		this.requestUpdate("select_options");
	}

	/**
	 * Add the current settings as a new favorite
	 */
	_add_current()
	{
		// Get current filters
		let current_filters = Object.assign({}, this._nextmatch.activeFilters);

		// Add in extras
		for(let extra in this.filters)
		{
			// Don't overwrite what nm has, chances are nm has more up-to-date value
			if(typeof current_filters == 'undefined')
			{
				// @ts-ignore
				current_filters[extra] = this._nextmatch.options.settings[extra];
			}
		}
		// Skip columns
		delete current_filters.selectcols;

		// Add in application's settings
		if(this.filters != true)
		{
			for(let i = 0; i < this.filters.length; i++)
			{
				current_filters[this.filters[i]] = this._nextmatch.options.settings[this.filters[i]];
			}
		}

		// Call framework
		//@ts-ignore TS doesn't know about window.app
		window.app[this.app].add_favorite(current_filters);
	}

	/**
	 * Get a favorite from the list by id
	 */
	favoriteByID(id : string) : any
	{
		if(!id)
		{
			return null;
		}
		return this.__select_options.find(f => f.value == id)
	}

	/**
	 * Clicked on an option
	 *
	 * @param ev
	 * @protected
	 */
	protected _handleSelect(ev)
	{
		if(ev.detail.item.value == Et2Favorites.ADD_VALUE)
		{
			return this._add_current();
		}
		this._value = ev.detail.item.value;

		this._apply_favorite(ev.detail.item.value);
	}

	/**
	 * Clicked a radio button
	 *
	 * @param _ev
	 * @protected
	 */
	protected _handleRadio(_ev)
	{
		// Don't do the menu
		_ev.stopImmediatePropagation();

		// Save as default favorite - used when you click the button
		let pref = _ev.target.value;
		this.egw().set_preference(this.app, this.defaultPref, pref);
		this._preferred = pref;
		this.dropdownNode.hide();
		this.requestUpdate("select_options");

		this.dispatchEvent(new Event("change", {bubbles: true}));
	}

	_handleDelete(_ev : MouseEvent)
	{
		// Don't do the menu
		_ev.stopImmediatePropagation();

		let trash = <HTMLElement>(<HTMLElement>_ev.target).parentNode;
		let line = <SlMenuItem>trash.parentNode;
		let fav = this.favoriteByID(line.value);
		line.classList.add("loading");

		// Make sure first
		let do_delete = function(button_id)
		{
			if(button_id != Et2Dialog.YES_BUTTON)
			{
				line.classList.remove('loading');
				return;
			}

			// Hide the trash
			trash.remove();

			// Delete preference server side
			let request = this.egw().json("EGroupware\\Api\\Framework::ajax_set_favorite",
				[this.app, fav.name, "delete", "" + fav.group, '']).sendRequest();
			request.then(response =>
			{
				line.classList.remove("loading");

				let result = response.response.find(r => r.type == "data");

				// Could not find the result we want
				if(!result || result.type !== "data")
				{
					return;
				}

				if(typeof result.data == 'boolean' && result.data)
				{
					// Remove line from list
					line.remove();

					// Remove favorite from options
					this.__select_options = this.__select_options.filter(f => f.value != fav.value);
				}
				else
				{
					// Something went wrong server side
					line.classList.add('error');
				}
			});
		}.bind(this);
		Et2Dialog.show_dialog(do_delete, (this.egw().lang("Delete") + " " + fav.name + "?"),
			"Delete", null, Et2Dialog.BUTTONS_YES_NO, Et2Dialog.QUESTION_MESSAGE);

		return false;
	}

	/**
	 * Clicked the main button
	 *
	 * @param {MouseEvent} _ev
	 * @returns {boolean}
	 * @protected
	 */
	_handleClick(_ev : MouseEvent) : boolean
	{
		// Apply preferred filter - make sure it's an object, and not a reference
		if(this._preferred && this.favoriteByID(this._preferred))
		{
			this._apply_favorite(this._preferred);
		}
		_ev.stopImmediatePropagation();
		return false;
	}

	/**
	 * Apply a favorite to the app or nextmatch
	 *
	 * @param {string} favorite_name
	 * @protected
	 */
	protected _apply_favorite(favorite_name : string)
	{
		let fav = favorite_name == "blank" ? {} : this.favoriteByID(favorite_name);
		// use app[appname].setState if available to allow app to overwrite it (eg. change to non-listview in calendar)
		//@ts-ignore TS doesn't know about window.app
		if(typeof window.app[this.app] != 'undefined')
		{
			//@ts-ignore TS doesn't know about window.app
			window.app[this.app].setState(fav);
		}
	}

	/**
	 * Set the nextmatch to filter
	 * From et2_INextmatchHeader interface
	 *
	 * @param {et2_nextmatch} nextmatch
	 */
	setNextmatch(nextmatch)
	{
		this._nextmatch = nextmatch;

		if(this.nm_filter)
		{
			this.set_value(this.nm_filter);
			this.nm_filter = false;
		}

		// Re-generate filter list so we can add 'Add current'
		this.__select_options = this._load_favorites(this.app);
		this.requestUpdate("select_options");
	}
}

customElements.define("et2-favorites", Et2Favorites);