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
	/**
	 * Value to set checkbox in (third) indeterminate state
	 */
	static readonly INDETERMINATE = '***undefined***';

	static get styles()
	{
		return [
			...shoelace,
			...super.styles,
			css`
              :host {
                /* Make it line up with the middle of surroundings */
                margin: auto 0px;
                vertical-align: baseline;
              }

              :host([disabled]) {
                display: initial;
              }

              /* Fix positioning */
              .checkbox {
                position: relative;
              }

              /* Extend hover highlight to label */
              .checkbox:not(.checkbox--disabled):hover {
                color: var(--sl-input-border-color-hover);
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

		this.isSlComponent = true;

		this.selectedValue = 'true';
		this.unselectedValue = '';
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

	get value() : string | boolean
	{
		return this.indeterminate ? undefined :
			(this.checked ? this.selectedValue : this.unselectedValue);
	}

	set value(new_value : string | boolean)
	{
		this.requestUpdate("checked");
		this.indeterminate = false;

		if(typeof new_value === "boolean")
		{
			this.checked = new_value;
		}
		else if(new_value == this.selectedValue)
		{
			this.checked = true;
		}
		else if(new_value == this.unselectedValue)
		{
			this.checked = false;
		}
		// concept of an indeterminate value did not exist in eT2 and set value gets called with all kind of truthy of falsy values
		// therefore we can NOT set everything not matching our (un)selectedValue to indeterminate!
		// For now, we only do that for an explicit Et2Checkbox.INDETERMINATE value
		else if (new_value === Et2Checkbox.INDETERMINATE)
		{
			this.indeterminate = true;
		}
		else
		{
			this.checked = !!new_value;
		}
	}

	private get _labelNode()
	{
		return this.shadowRoot?.querySelector("slot:not([name])");
	}
}

customElements.define("et2-checkbox", Et2Checkbox);