/**
 * EGroupware eTemplate2 - WidgetWithSelectMixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {StaticOptions} from "./StaticOptions";
import {dedupeMixin, html, PropertyValues, TemplateResult} from "@lion/core";
import {et2_readAttrWithDefault} from "../et2_core_xml";
import {find_select_options, SelectOption} from "./FindSelectOptions";

/**
 * Base class for things that do selectbox type behaviour, to avoid putting too much or copying into read-only
 * selectboxes, also for common handling of properties for more special selectboxes.
 *
 * LionSelect (and any other LionField) use slots to wrap a real DOM node.  ET2 doesn't expect this,
 * so we have to create the input node (via slots()) and respect that it is _external_ to the Web Component.
 * This complicates things like adding the options, since we can't just override _inputGroupInputTemplate()
 * and include them when rendering - the parent expects to find the <select> added via a slot, render() would
 * put it inside the shadowDOM.  That's fine, but then it doesn't get created until render(), and the parent
 * (LionField) can't find it when it looks for it before then.
 *
 */
export const Et2widgetWithSelectMixin = dedupeMixin((superclass) =>
{
	class Et2WidgetWithSelect extends Et2InputWidget(superclass)
	{
		static get properties()
		{
			return {
				...super.properties,
				/**
				 * Textual label for first row, eg: 'All' or 'None'.  It's value will be ''
				 */
				empty_label: String,

				/**
				 * Select box options
				 *
				 * Will be found automatically based on ID and type, or can be set explicitly in the template using
				 * <option/> children, or using widget.set_select_options(SelectOption[])
				 */
				select_options: Object,
			}
		}

		constructor()
		{
			super();

			this.select_options = <StaticOptions[]>[];
		}

		/** @param {import('@lion/core').PropertyValues } changedProperties */
		updated(changedProperties : PropertyValues)
		{
			super.updated(changedProperties);

			// If the ID changed (or was just set) find the select options
			if(changedProperties.has("id"))
			{
				this.set_select_options(find_select_options(this));
			}
		}

		set_value(val)
		{
			let oldValue = this.modalValue;

			// Make sure it's a string
			val = "" + val;

			this.modalValue = val
			this.requestUpdate("value", oldValue);
		}

		/**
		 * Set the select options
		 *
		 * @param {SelectOption[]} new_options
		 */
		set_select_options(new_options : SelectOption[] | { [key : string] : string })
		{
			if(!Array.isArray(new_options))
			{
				let fixed_options = [];
				for(let key in new_options)
				{
					fixed_options.push({value: key, label: new_options[key]});
				}
				this.select_options = fixed_options;
			}
			else
			{
				this.select_options = new_options;
			}
		}

		get_select_options()
		{
			return this.select_options;
		}

		/**
		 * Render the "empty label", used when the selectbox does not currently have a value
		 *
		 * @returns {}
		 */
		_emptyLabelTemplate() : TemplateResult
		{
			return html`${this.empty_label}`;
		}

		_optionTemplate(option : SelectOption) : TemplateResult
		{
			return html``;
		}

		/**
		 * Load extra stuff from the template node.  In particular, we're looking for any <option/> tags added.
		 *
		 * @param {Element} _node
		 */
		loadFromXML(_node : Element)
		{
			// Read the option-tags
			let options = _node.querySelectorAll("option");
			let new_options = [];
			for(let i = 0; i < options.length; i++)
			{
				new_options.push({
					value: et2_readAttrWithDefault(options[i], "value", options[i].textContent),
					// allow options to contain multiple translated sub-strings eg: {Firstname}.{Lastname}
					label: options[i].textContent.replace(/{([^}]+)}/g, function(str, p1)
					{
						return this.egw().lang(p1);
					}),
					title: et2_readAttrWithDefault(options[i], "title", "")
				});
			}

			if(this.id)
			{
				new_options = find_select_options(this, {}, new_options);
			}
			this.set_select_options(new_options);
		}
	}
	return Et2WidgetWithSelect;
});
