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
import {Et2Image} from "../Et2Image/Et2Image";
import {SelectAccountMixin} from "./SelectAccountMixin";
import {Et2StaticSelectMixin} from "./StaticOptions";

export type AccountType = 'accounts'|'groups'|'both'|'owngroups';

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
				fetch.push(this.egw().accounts('accounts').then(options => {this.static_options = this.static_options.concat(options)}));
			}

			fetch.push(this.egw().accounts('owngroups').then(options => {this.static_options = this.static_options.concat(options)}));
		}
		else
		{
			fetch.push(this.egw().accounts(this.accountType).then(options => {this.static_options = this.static_options.concat(options)}));
		}
		this.fetchComplete = Promise.all(fetch)
			.then(() => this._renderOptions());
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
		let select_options : Array<SelectOption> = [...super.select_options] || [];

		return select_options.filter((value, index, self) =>
		{
			return self.findIndex(v => v.value === value.value) === index;
		});
	}

	set select_options(new_options : SelectOption[])
	{
		super.select_options = new_options;
	}

	/**
	 * Override the prefix image for tags (multiple=true)
	 * The default is probably fine, but we're being explicit here.
	 * @param item
	 * @returns {TemplateResult<1>}
	 * @protected
	 *
	 */
	protected _createImage(item) : Et2Image
	{
		const image = super._createImage(item);
		if(image)
		{
			image.src = "/egroupware/api/avatar.php?account_id=" + item.value + "&etag=1";
		}
		return image;
	}
}

customElements.define("et2-select-account", Et2SelectAccount);