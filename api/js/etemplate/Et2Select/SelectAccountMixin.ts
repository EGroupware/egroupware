import {SelectOption} from "./FindSelectOptions";
import {LitElement} from "@lion/core";

/**
 * EGroupware eTemplate2 - SelectAccountMixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

/**
 * Common code for editable & read-only select account
 * Moved out so we don't have copy+paste code in both
 */
export declare class SelectAccountInterface
{
	set value(new_value : string | string[])

	get value() : string | string[]

	set select_options(new_options : SelectOption[])
	get select_options() : SelectOption[]
}

type Constructor<T = {}> = new (...args : any[]) => T;
export const SelectAccountMixin = <T extends Constructor<LitElement>>(superclass : T) =>
{
	class SelectAccount extends superclass
	{

		/**
		 * Hold on to accounts we had to fetch from the server
		 * @type {any[]}
		 * @protected
		 */
		protected account_options = [];

		constructor()
		{
			super();
			this.account_options = [];
		}

		/**
		 * If the value has an account that's not already in the list, check with the server.
		 * We probably don't have all the accounts client side.  This is similar to freeEntries,
		 * but a little safer because we don't allow just anything.
		 *
		 * @param {any} new_value
		 */
		set value(new_value : string | string[])
		{
			super.value = new_value;
			if(!new_value)
			{
				return;
			}
			let val = Array.isArray(this.value) ? this.value : [this.value];

			if(this.isConnected)
			{
				this._find_options(val)
			}
			else
			{
				// If not already connected, wait until any provided select_options have been found
				this.updateComplete.then(() =>
				{
					this._find_options(val);
					this.requestUpdate('select_options');
				});
			}
		}

		_find_options(val)
		{
			for(let id of val)
			{
				// Don't add if it's already there
				if(this.select_options.findIndex(o => o.value == id) != -1)
				{
					continue;
				}

				let account_name = null;
				let option = <SelectOption>{value: "" + id, label: id + " ..."};
				this.account_options.push(option);
				if(this.value && (account_name = this.egw().link_title('api-accounts', id, false)))
				{
					option.label = account_name;
				}
				else if(!account_name)
				{
					// Not already cached, need to fetch it
					this.egw().link_title('api-accounts', id, true).then(title =>
					{
						option.label = title;
						this.requestUpdate('select_options');
					});
				}
			}
		}

		get value()
		{
			return super.value;
		}

		get select_options()
		{
			return [...(this.account_options || []), ...super.select_options];
		}

		set select_options(value : SelectOption[])
		{
			super.select_options = value;
		}
	}

	return SelectAccount as unknown as Constructor<SelectAccountInterface> & T;
}