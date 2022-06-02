/**
 * EGroupware eTemplate2 - Search & select link entry WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {css} from "@lion/core";
import {Et2Select} from "../Et2Select/Et2Select";
import {Et2LinkAppSelect} from "./Et2LinkAppSelect";
import {Et2Link} from "./Et2Link";

export class Et2LinkSearch extends Et2Select
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				display: block;
				flex: 1 1 auto;
				min-width: 200px;
			}
			::part(icon), .select__icon {
				display: none;
			}
			`
		];
	}


	static get properties()
	{
		return {
			...super.properties,
			app: {type: String, reflect: true}
		}
	}

	constructor()
	{
		super();
		this.search = true;
		this.searchUrl = "EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_search";
		this.clearable = true;
		this.hoist = true;
	}

	get _appNode() : Et2LinkAppSelect
	{
		return this.parentNode.querySelector("et2-link-apps");
	}

	protected remoteQuery(search : string, options : object)
	{
		let request = this.egw().request(this.searchUrl, [this._appNode.value, '', search, options]);
		if(this.query && typeof this.query == "function")
		{
			if(!this.query(request, this))
			{
				return;
			}
		}
		request.then((result) =>
		{
			this.processRemoteResults(result);
		});
	}

	updated(changedProperties)
	{
		super.updated(changedProperties);

		// Set a value we don't have as an option?  That's OK, we'll just add it
		if(changedProperties.has("value") && this.value && (
			this.menuItems && this.menuItems.length == 0 ||
			this.menuItems?.filter && this.menuItems.filter(item => this.value.includes(item.value)).length == 0
		))
		{
			this._missingOption(this.value)
		}
	}

	/**
	 * The set value requires an option we don't have.
	 * Add it in, asking server for title if needed
	 *
	 * @param value
	 * @protected
	 */
	protected _missingOption(value : string)
	{
		let option = {
			value: value,
			label: Et2Link.MISSING_TITLE,
			class: "loading"
		}
		// Weird call instead of just unshift() to make sure to trigger setter
		this.select_options = Object.assign([option], this.__select_options);
		this.egw()?.link_title(this.app, option.value, true).then(title =>
		{
			option.label = title;
			option.class = "";
			// It's probably already been rendered, find the item
			let item = this.getItems().find(i => i.value === option.value);
			if(item)
			{
				item.textContent = title;
				item.classList.remove("loading");
			}
		});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-link-search", Et2LinkSearch);