/*
 * Calendar owner widget
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2Select} from "../../api/js/etemplate/Et2Select/Et2Select";
import {css} from "@lion/core";
import {IsEmail} from "../../api/js/etemplate/Validators/IsEmail";

/**
 * Select widget customised for calendar owner, which can be a user
 * account or group, or an entry from almost any app, or an email address
 *
 */
export class CalendarOwner extends Et2Select
{

	static get styles()
	{
		return [
			...super.styles,
			css`
			/* Larger maximum height before scroll*/
			.select__tags {
				max-height: 10em;
			}
			`
		];
	}

	constructor(...args : any[])
	{
		super(...args);
		this.searchUrl = "calendar_owner_etemplate_widget::ajax_search";
		this.multiple = true;

		this._handleMouseWheel = this._handleMouseWheel.bind(this);
	}

	_bindListeners()
	{
		super._bindListeners();

		this.addEventListener("mousewheel", this._handleMouseWheel);
	}

	_unbindListeners()
	{
		this.removeEventListener("mousewheel", this._handleMouseWheel);
	}

	/**
	 * Override parent to handle our special additional data types (c#,r#,etc.) when they
	 * are not available client side.
	 *
	 * @param {string|string[]} _value array of selected owners, which can be a number,
	 *	or a number prefixed with one character indicating the resource type.
	 */
	set_value(_value)
	{
		super.set_value(_value);

		// If parent didn't find a label, label will be the same as ID so we
		// can find them that way
		let missing_labels = [];
		this.updateComplete.then(() =>
		{
			for(var i = 0; i < this.value.length; i++)
			{
				if(!this.menuItems.find(o => o.value == this.value[i]))
				{
					missing_labels.push(this.value[i]);
				}
			}
			if(Object.keys(missing_labels).length > 0)
			{
				// Proper label was not found by parent - ask directly
				this.egw().json('calendar_owner_etemplate_widget::ajax_owner', [missing_labels], function(data)
				{
					for(let owner in data)
					{
						if(!owner || typeof owner == "undefined")
						{
							continue;
						}
						// Put it in the list of options
						let index = this.select_options.findIndex(o => o.value == owner);
						if(index !== -1)
						{
							this.select_options[index] = data[owner];
						}
						else
						{
							this.select_options.push(data[owner]);
						}
					}
					this.requestUpdate("select_options");
					this.updateComplete.then(() => {this.syncItemsFromValue();});
				}, this, true, this).sendRequest();
			}
		});
	}

	/**
	 * Stop scroll from bubbling so the sidemenu doesn't scroll too
	 *
	 * @param {MouseEvent} e
	 */
	_handleMouseWheel(e : MouseEvent)
	{
		e.stopPropagation();
	}

	/**
	 * Check if a free entry value is acceptable.
	 * We only check the free entry, since value can be mixed.
	 *
	 * @param text
	 * @returns {boolean}
	 */
	public validateFreeEntry(text) : boolean
	{
		let validators = [...this.validators, new IsEmail()];
		let result = validators.filter(v =>
			v.execute(text, v.param, {node: this}),
		);
		return result.length == 0;
	}
}

customElements.define("et2-calendar-owner", CalendarOwner);