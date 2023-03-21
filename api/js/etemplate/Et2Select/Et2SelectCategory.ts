/**
 * EGroupware eTemplate2 - Select Category WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, PropertyValues} from "@lion/core";
import {Et2Select} from "./Et2Select";
import {Et2StaticSelectMixin, StaticOptions as so} from "./StaticOptions";
import {cleanSelectOptions} from "./FindSelectOptions";

/**
 * Customised Select widget for categories
 * This widget gives us category colors and icons in the options and selected value.
 */
export class Et2SelectCategory extends Et2StaticSelectMixin(Et2Select)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			/* Category color on options */
			::slotted(*) {
				border-left: 6px solid var(--category-color, transparent);
			}
			/* Border on the (single) selected value */
			:host(.hasValue:not([multiple])) .select--standard .select__control {
				border-left: 6px solid var(--sl-input-border-color);
			}			
			`
		]
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Include global categories
			 */
			globalCategories: {type: Boolean},
			/**
			 * Show categories from this application.  If not set, will be the current application
			 */
			application: {type: String},
			/**
			 * Show categories below this parent category
			 */
			parentCat: {type: Number}
		}
	}

	constructor()
	{
		super();
	}

	async connectedCallback()
	{
		super.connectedCallback();

		if(typeof this.application == 'undefined')
		{
			this.application =
				// When the widget is first created, it doesn't have a parent and can't find it's instanceManager
				(this.getInstanceManager() && this.getInstanceManager().app) ||
				this.egw().app_name();
		}
		// If app passes options (addressbook index) we'll use those instead.
		// They will be found automatically by update() after ID is set.
		await this.updateComplete;
		if(this.select_options.length == 0)
		{

		}
	}


	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has("global_categories") || changedProperties.has("application") || changedProperties.has("parentCat"))
		{
			this.fetchComplete = so.cat(this).then(options =>
			{
				this.static_options = cleanSelectOptions(options);
				this.requestUpdate("select_options");
			});
		}

		if(changedProperties.has("value") || changedProperties.has('select_options'))
		{
			this.doLabelChange()
		}
	}

	/**
	 * Override from parent (SlSelect) to customise display of the current value.
	 * Here's where we add the icon & color border
	 */
	doLabelChange()
	{
		// Update the display label when checked menu item's label changes
		if(this.multiple)
		{
			return;
		}

		const checkedItem = this.menuItems.find(item => item.value === this.value);
		this.displayLabel = checkedItem ? checkedItem.textContent : '';
		this.querySelector("[slot=prefix].tag_image")?.remove();
		if(checkedItem)
		{
			let image = this._createImage(checkedItem)
			if(image)
			{
				this.append(image);
			}
			this.dropdown.querySelector(".select__control").style.borderColor =
				getComputedStyle(checkedItem).getPropertyValue("--category-color") || "";
		}
	}

	/**
	 * Render select_options as child DOM Nodes
	 *
	 * Overridden here so we can re-do the displayed label after first load of select options.
	 * Initial load order / lifecycle does not have all the options at the right time
	 * @protected
	 */
	protected _renderOptions()
	{
		// @ts-ignore Doesn't know about Et2WidgetWithSelectMixin._renderOptions()
		return super._renderOptions().then(() =>
		{
			// @ts-ignore Doesn't know about SlSelect.menuItems
			if(this.menuItems.length > 0)
			{
				this.doLabelChange();
			}
		});
	}

	/**
	 * Use a custom tag for when multiple=true
	 *
	 * @returns {string}
	 */
	get tagTag() : string
	{
		return "et2-category-tag";
	}

	/**
	 * Customise how tags are rendered.
	 * This overrides parent to set application
	 *
	 * @param item
	 * @protected
	 */
	protected _createTagNode(item)
	{
		let tag = super._createTagNode(item);
		tag.application = this.application;
		return tag;
	}
}

customElements.define("et2-select-cat", Et2SelectCategory);