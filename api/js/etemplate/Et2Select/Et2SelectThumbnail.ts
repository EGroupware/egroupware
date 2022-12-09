/**
 * EGroupware eTemplate2 - Image selection WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2Select} from "./Et2Select";
import {css} from "@lion/core";
import {SelectOption} from "./FindSelectOptions";

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

		// Once added to options, add to value / tags
		this.updateComplete.then(() =>
		{
			this.menuItems.forEach(o =>
			{
				if(o.value == text)
				{
					o.dispatchEvent(new Event("click"));
				}
			});
			this.syncItemsFromValue();
		});
		return true;
	}

	get tagTag() : string
	{
		return "et2-thumbnail-tag";
	}

	/**
	 * Customise how tags are rendered.  This overrides what SlSelect
	 * does in syncItemsFromValue().
	 * This is a copy+paste from SlSelect.syncItemsFromValue().
	 *
	 * @param item
	 * @protected
	 */
	protected _createTagNode(item)
	{
		let tag = super._createTagNode(item);

		// Different image - slot in just an image so we can have complete control over styling
		tag.querySelector("[slot=prefix]")?.remove();
		let img = document.createElement("img");
		img.slot = "prefix";
		img.src = item.value;
		tag.append(img);

		return tag;
	}
}

customElements.define("et2-select-thumbnail", Et2SelectThumbnail);