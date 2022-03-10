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

		this.account_type = 'accounts';
	}

	set_account_type(type : AccountType)
	{
		this.account_type = type;

		this.set_select_options(this.get_select_options());
	}

	/**
	 * Get account info for select options from common client-side account cache
	 */
	get_select_options() : Array<SelectOption>
	{
		const type = this.egw().preference('account_selection', 'common');
		if (type === 'none' && typeof egw.user('apps').admin === 'undefined')
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
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-account", Et2SelectAccount);