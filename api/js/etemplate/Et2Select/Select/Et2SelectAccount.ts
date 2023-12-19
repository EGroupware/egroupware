/**
 * EGroupware eTemplate2 - Account-selection WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {Et2Select} from "../Et2Select";
import {cleanSelectOptions, SelectOption} from "../FindSelectOptions";
import {SelectAccountMixin} from "../SelectAccountMixin";
import {Et2StaticSelectMixin} from "../StaticOptions";
import {html, nothing} from "lit";

export type AccountType = 'accounts' | 'groups' | 'both' | 'owngroups';

/**
 * @customElement et2-select-account
 */
export class Et2SelectAccount extends SelectAccountMixin(Et2StaticSelectMixin(Et2Select))
{
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * One of: 'accounts','groups','both','owngroups'
			 */
			accountType: String,
		}
	}

	constructor()
	{
		super();

		// all types can search the server.  If there are a lot of accounts, local list will
		// not be complete
		if(this.egw().preference('account_selection', 'common') !== 'none')
		{
			this.searchUrl = "EGroupware\\Api\\Etemplate\\Widget\\Taglist::ajax_search";
		}

		this.searchOptions = {type: 'account', account_type: 'accounts'};
		this.__accountType = 'accounts';
	}

	connectedCallback()
	{
		super.connectedCallback();

		// Start fetch of select_options
		this.fetchComplete = this._getAccounts();
	}

	/**
	 * Pre-fill the account list according to type & preferences
	 *
	 * @protected
	 * @internal
	 */
	protected _getAccounts()
	{
		const type = this.egw().preference('account_selection', 'common');
		let fetch = [];
		let process = (options) =>
		{
			// Shallow copy to avoid re-using the same object.
			// Uses more memory, but otherwise multiple selectboxes get "tied" together
			let cleaned = cleanSelectOptions(options)
				// slice to avoid problems with lots of accounts
				.slice(0, /* Et2WidgetWithSearch.RESULT_LIMIT */ 100);
			this.account_options = this.account_options.concat(cleaned);
		};
		// for primary_group we only display owngroups == own memberships, not other groups
		if(type === 'primary_group' && this.accountType !== 'accounts')
		{
			if(this.accountType === 'both')
			{
				fetch.push(this.egw().accounts('accounts').then(process));
			}

			fetch.push(this.egw().accounts('owngroups').then(process));
		}
		else if(type !== "none")
		{
			fetch.push(this.egw().accounts(this.accountType).then(process));
		}

		return Promise.all(fetch).then(() =>
		{
			this.requestUpdate("select_options");
			this.value = this.value;
		});
	}

	set accountType(type : AccountType)
	{
		this.__accountType = type;
		this.searchOptions.account_type = type;

		super.select_options = this.select_options;
	}

	get accountType() : AccountType
	{
		return this.__accountType;
	}

	/**
	 * Get account info for select options from common client-side account cache
	 */
	get select_options() : Array<SelectOption>
	{
		const type = this.egw().preference('account_selection', 'common');
		if(type === 'none' && typeof this.egw().user('apps').admin === 'undefined')
		{
			return [];
		}
		return super.select_options;
	}

	set select_options(new_options : SelectOption[])
	{
		super.select_options = new_options;
	}

	/**
	 * Override filter to not, since we don't have all accounts available
	 */
	filterOutMissingOptions(value : string[]) : string[]
	{
		return value;
	}

	/**
	 * Override icon for the select option
	 *
	 * @param option
	 * @protected
	 */
	protected _iconTemplate(option)
	{
		// lavatar uses a size property, not a CSS variable
		let style = getComputedStyle(this);

		return html`
            <et2-lavatar slot="prefix" part="icon" exportparts="image" .size=${style.getPropertyValue("--icon-width")}
                         lname=${option.lname || nothing}
                         fname=${option.fname || nothing}
                         image=${option.icon || nothing}
            >
            </et2-lavatar>`;
	}
}

customElements.define("et2-select-account", Et2SelectAccount);