/**
 * EGroupware eTemplate2 - Checkbox widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css} from "@lion/core";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import '../Et2Image/Et2Image';
import {SlCheckbox} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";

export class Et2Checkbox extends Et2InputWidget(SlCheckbox)
{
	static get styles()
	{
		return [
			...shoelace,
			...super.styles,
			css`
			:host {
				/* Make it line up with the middle of surroundings */
				margin: auto 0px;
				vertical-align: -webkit-baseline-middle;
			}
            :host([disabled]) {
            	display:initial;
            }
            `,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/* Different value when checked */
			selectedValue: {type: String},
			/* Different value when unchecked */
			unselectedValue: {type: String}
		}
	}

	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();

	}

	get label()
	{
		return this._labelNode?.textContent || "";
	}

	set label(label_text)
	{
		if(this._labelNode)
		{
			this._labelNode.textContent = label_text;
		}
		else
		{
			this.updateComplete.then(() =>
			{
				this.label = label_text;
			})
		}
	}

	get value()
	{
		if(this.checked && this.selectedValue)
		{
			return this.selectedValue;
		}
		if(!this.checked && this.unselectedValue)
		{
			return this.unselectedValue;
		}
		return this.checked + "";
	}

	set value(new_value : string | boolean)
	{
		this.requestUpdate("checked");
		this.indeterminate = false;
		if(typeof new_value === "boolean" || !this.selectedValue)
		{
			this.checked = <boolean>new_value;
			return;
		}
		if(this.selectedValue && new_value == this.selectedValue)
		{
			this.checked = true;
		}
		else if(this.unselectedValue && new_value == this.unselectedValue)
		{
			this.checked = false;
		}
		else
		{
			this.indeterminate = true;
		}
	}

	private get _labelNode()
	{
		return this.shadowRoot?.querySelector("slot:not([name])");
	}
}

customElements.define("et2-checkbox", Et2Checkbox);