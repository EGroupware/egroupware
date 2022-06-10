/**
 * EGroupware eTemplate2 - Account-selection WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {Et2Select} from "./Et2Select";
import {SelectOption} from "./FindSelectOptions";

export type AccountType = 'accounts'|'groups'|'both'|'owngroups';

/**
 * @customElement et2-select-account
 */
export class Et2SelectAccount extends Et2Select
{
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * One of: 'accounts','groups','both','owngroups'
			 */
			account_type: String,
		}
	}

	constructor()
	{
		super();
		
		this.searchUrl = "EGroupware\\Api\\Etemplate\\Widget\\Taglist::ajax_search";

		this.__account_type = 'accounts';
	}

	set account_type(type : AccountType)
	{
		this.__account_type = type;

		super.select_options = this.select_options;
	}

	get account_type() : AccountType
	{
		return this.__account_type;
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
		let select_options : Array<SelectOption>;
		// for primary_group we only display owngroups == own memberships, not other groups
		if (type === 'primary_group' && this.account_type !== 'accounts')
		{
			if (this.account_type === 'both')
			{
				select_options = this.egw().accounts('accounts');
			}
			select_options = select_options.concat(this.egw().accounts('owngroups'));
		}
		else
		{
			select_options = this.egw().accounts(this.account_type);
		}
		return select_options;
	}

	set select_options(new_options : SelectOption[])
	{
		super.select_options = new_options;
	}
}

customElements.define("et2-select-account", Et2SelectAccount);