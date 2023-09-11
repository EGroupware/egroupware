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
		const type = this.egw().preference('account_selection', 'common');
		let fetch = [];
		// for primary_group we only display owngroups == own memberships, not other groups
		if(type === 'primary_group' && this.accountType !== 'accounts')
		{
			if(this.accountType === 'both')
			{
				fetch.push(this.egw().accounts('accounts').then(options => {this._static_options = this._static_options.concat(cleanSelectOptions(options))}));
			}

			fetch.push(this.egw().accounts('owngroups').then(options => {this._static_options = this._static_options.concat(cleanSelectOptions(options))}));
		}
		else
		{
			fetch.push(this.egw().accounts(this.accountType).then(options => {this._static_options = this._static_options.concat(cleanSelectOptions(options))}));
		}
		this.fetchComplete = Promise.all(fetch);
	}


	firstUpdated(changedProperties?)
	{
		super.firstUpdated(changedProperties);
		// Due to the different way Et2SelectAccount handles options, we call this explicitly
		this._renderOptions();
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
            <et2-lavatar slot="prefix" part="icon" .size=${style.getPropertyValue("--icon-width")}
                         lname=${option.lname || nothing}
                         fname=${option.fname || nothing}
                         image=${option.icon || nothing}
            >
            </et2-lavatar>`;
	}
}

customElements.define("et2-select-account", Et2SelectAccount);