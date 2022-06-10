/**
 * EGroupware eTemplate2 - Email-selection WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2Select} from "./Et2Select";
import {css} from "@lion/core";
import {IsEmail} from "../Validators/IsEmail";

export class Et2SelectEmail extends Et2Select
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
			::slotted(sl-icon[slot="suffix"]) {
				display: none;
			}
			`
		];
	}

	constructor(...args : any[])
	{
		super(...args);
		this.search = true;
		this.searchUrl = "EGroupware\\Api\\Etemplate\\Widget\\Taglist::ajax_email";
		this.allowFreeEntries = true;
		this.editModeEnabled = true;
		this.multiple = true;
		this.defaultValidators.push(new IsEmail());
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	/**
	 * Actually query the server.
	 *
	 * Overridden to change request to match server
	 *
	 * @param {string} search
	 * @param {object} options
	 * @returns {any}
	 * @protected
	 */
	protected remoteQuery(search : string, options : object)
	{
		return this.egw().json(this.searchUrl, [search]).sendRequest().then((result) =>
		{
			this.processRemoteResults(result);
		});
	}

	/**
	 * Add in remote results
	 *
	 * Overridden to get results in a format parent expects.
	 * Current server-side gives {
	 * 	icon: "/egroupware/api/avatar.php?contact_id=5&etag=1"
	 * 	id: "ng@egroupware.org"
	 * 	label: "ng@egroupware.org"
	 * 	name: ""
	 * 	title: "ng@egroupware.org"
	 * }
	 * Parent expects value instead of id
	 *
	 * @param results
	 * @protected
	 */
	protected processRemoteResults(results)
	{
		results.forEach(r => r.value = r.id);
		super.processRemoteResults(results);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-email", Et2SelectEmail);