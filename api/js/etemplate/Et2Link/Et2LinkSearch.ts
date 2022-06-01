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
	}

	get _appNode() : Et2LinkAppSelect
	{
		return this.parentNode.querySelector("et2-link-apps");
	}

	protected remoteQuery(search : string, options : object)
	{
		let request = this.egw().json(this.searchUrl, [this._appNode.value, '', search, options]);
		if(this.query && typeof this.query == "function")
		{
			if(!this.query(request, this))
			{
				return;
			}
		}
		// ToDo use egw.request(), not old egw.json()
		request.sendRequest().then((result) =>
		{
			result.response.forEach((response) =>
			{
				if (typeof response.data !== 'undefined')
				{
					this.processRemoteResults(response.data);
				}
			});
		});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-link-search", Et2LinkSearch);