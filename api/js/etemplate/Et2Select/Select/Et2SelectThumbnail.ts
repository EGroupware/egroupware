/**
 * EGroupware eTemplate2 - Image selection WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2Select} from "../Et2Select";
import {css, html, TemplateResult} from "lit";
import {SelectOption} from "../FindSelectOptions";
import {SlOption} from "@shoelace-style/shoelace";

export class Et2SelectThumbnail extends Et2Select
{
	static get styles()
	{
		return [
			...super.styles,
			css`
	
			/* Hide selected options from the dropdown */
			::slotted([checked])
			{
				display: none;
			}
			/* Hide dropdown icon */
			::part(icon), .select__icon {
				display: none;
			}
			`
		];
	}

	constructor(...args : any[])
	{
		super(...args);
		this.search = false;
		this.allowFreeEntries = true;
		this.editModeEnabled = true;
		this.multiple = true;
		this.pill = false;
	}

	/**
	 * Create an entry that is not in the options and add it to the value
	 * Overridden here to set the icon as the text, since this is a thumbnail
	 *
	 * @param {string} text Used as both value and label
	 */
	public createFreeEntry(text : string) : boolean
	{
		if(!this.validateFreeEntry(text))
		{
			return false;
		}
		// Make sure not to double-add
		if(!this.select_options.find(o => o.value == text))
		{
			this.__select_options.push(<SelectOption>{
				value: text,
				label: "",
				icon: text
			});
			this.requestUpdate('select_options');
		}
		this.requestUpdate();

		// Make sure not to double-add
		if(this.multiple && this.value.indexOf(text) == -1)
		{
			this.value.push(text);
		}
		else if(!this.multiple)
		{
			this.value = text;
			return;
		}

		return true;
	}

	get tagTag() : string
	{
		return "et2-thumbnail-tag";
	}

	/**
	 * Custom tag
	 *
	 * Override this to customise display when multiple=true.
	 * There is no restriction on the tag used, unlike _optionTemplate()
	 *
	 * @param {Et2Option} option
	 * @param {number} index
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _tagTemplate(option : SlOption, index : number) : TemplateResult
	{
		// Different image - slot in just an image so we can have complete control over styling
		return html`
            <et2-thumbnail-tag>
                <img
                        part="image"
                        slot="prefix"
                        src="${option.value}"
                />
            </et2-thumbnail-tag>
		`;
	}
}

customElements.define("et2-select-thumbnail", Et2SelectThumbnail);