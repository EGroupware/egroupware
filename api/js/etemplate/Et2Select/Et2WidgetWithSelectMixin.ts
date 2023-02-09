/**
 * EGroupware eTemplate2 - WidgetWithSelectMixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2InputWidget, Et2InputWidgetInterface} from "../Et2InputWidget/Et2InputWidget";
import {html, LitElement, PropertyValues, render, TemplateResult} from "@lion/core";
import {et2_readAttrWithDefault} from "../et2_core_xml";
import {cleanSelectOptions, find_select_options, SelectOption} from "./FindSelectOptions";
import {SearchMixinInterface} from "./SearchMixin";

/**
 * Base class for things that do selectbox type behaviour, to avoid putting too much or copying into read-only
 * selectboxes, also for common handling of properties for more special selectboxes.
 *
 * As with most other widgets that extend Lion components, do not override render().
 * To extend this mixin, override:
 * - _optionTargetNode(): Return the HTMLElement where the "options" go.
 * - _optionTemplate(option:SelectOption): Renders the option.  To use a special widget, use its tag in render.
 * Select option:
 * ```js
 * 		return html`
 *             <option value="${option.value}" title="${option.title}" ?selected=${option.value == this.modelValue}>
 *                 ${option.label}
 *             </option>`;
 * ```
 *
 *
 * or pass it off to a different WebComponent:
 *
 * ```js
 * _optionTemplate(option:SelectOption) : TemplateResult
 * {
 *     return html`
 *           <special-option-tag .value=${option}></special-option-tag>`;
 * }
 * ```
 *
 * Optionally, you can override:
 * - _emptyLabelTemplate(): How to render the empty label
 * - slots(): Most Lion components have an input slot where the <input> tag is created.
 * You can specify something else, or return {} to do your own thing.  This is a little more complicated.  You should
 * also override _inputGroupInputTemplate() to do what you normally would in render().
 *
 *
 * Technical note:
 * LionSelect (and any other LionField) use slots to wrap a real DOM node.  ET2 doesn't expect this,
 * so we have to create the input node (via slots()) and respect that it is _external_ to the Web Component.
 * This complicates things like adding the options, since we can't just override _inputGroupInputTemplate()
 * and include them when rendering - the parent expects to find the <select> added via a slot, render() would
 * put it inside the shadowDOM.  That's fine, but then it doesn't get created until render(), and the parent
 * (LionField) can't find it when it looks for it before then.
 *
 */
// Export the Interface for TypeScript
type Constructor<T = {}> = new (...args : any[]) => T;

export const Et2widgetWithSelectMixin = <T extends Constructor<LitElement>>(superclass : T) =>
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
				emptyLabel: String,

				/**
				 * Select box options
				 *
				 * Will be found automatically based on ID and type, or can be set explicitly in the template using
				 * <option/> children, or using widget.select_options = SelectOption[]
				 */
				select_options: {type: Object, noAccessor: true},

				/**
				 * Limit size
				 */
				rows: {type: Number, noAccessor: true, reflect: true}
			}
		}

		/**
		 * Options found in the XML when reading the template
		 * @type {SelectOption[]}
		 * @private
		 */
		private _xmlOptions : SelectOption[] = [];

		constructor(...args : any[])
		{
			super(...args);

			this.__select_options = <SelectOption[]>[];
		}

		/** @param {import('@lion/core').PropertyValues } changedProperties */
		updated(changedProperties : PropertyValues)
		{
			super.updated(changedProperties);

			// If the ID changed (or was just set) and select_options wasn't, find the new select options
			if(changedProperties.has("id") && !changedProperties.has("select_options"))
			{
				const options = find_select_options(this, {}, this._xmlOptions);
				if(options.length)
				{
					this.select_options = options;
				}
			}

			// Add in actual option tags to the DOM based on the new select_options
			if(changedProperties.has('select_options') || changedProperties.has("emptyLabel"))
			{
				// Add in options as children to the target node
				this._renderOptions();

				// This is needed to display initial load value in some cases, like infolog nm header filters
				if(this.handleMenuSlotChange && !this.hasUpdated)
				{
					this.handleMenuSlotChange();
				}
			}
		}

		/**
		 * Render select_options as child DOM Nodes
		 * @protected
		 */
		protected _renderOptions()
		{
			// Add in options as children to the target node
			if(!this._optionTargetNode)
			{
				return Promise.resolve();
			}
			/**
			 * Doing all this garbage to get the options to always show up.
			 * If we just do `render(options, target)`, they only show up in the DOM the first time.  If the
			 * same option comes back in a subsequent search, map() does not put it into the DOM.
			 * If we render into a new target, the options get rendered, but we have to wait for them to be
			 * rendered before we can do anything else with them.
			 */
			let temp_target = document.createElement("div");

			let options = html`${this._emptyLabelTemplate()}${this.select_options
				// Filter out empty values if we have empty label to avoid duplicates
				.filter(o => this.emptyLabel ? o.value !== '' : o)
				.map(this._groupTemplate.bind(this))}`;

			render(options, temp_target);
			return Promise.all(([...temp_target.querySelectorAll(":scope > *")].map(item => item.render)))
				.then(() =>
				{
					this._optionTargetNode.replaceChildren(
						...Array.from(temp_target.querySelectorAll(":scope > *")),
						...Array.from(this._optionTargetNode.querySelectorAll(":scope > [slot]"))
					);
					if(typeof this.handleMenuSlotChange == "function")
					{
						this.handleMenuSlotChange();
					}
				});

		}

		/**
		 * Overwritten as sometimes called before this._inputNode is available
		 *
		 * @param {*} v - modelValue: can be an Object, Number, String depending on the
		 * input type(date, number, email etc)
		 * @returns {string} formattedValue
		 */
		formatter(v)
		{
			if (!this._inputNode)
			{
				return v;
			}
			return super.formatter(v);
		}

		/**
		 * Set the select options
		 *
		 * @param new_options
		 */
		set select_options(new_options : SelectOption[])
		{
			const old_options = this.__select_options;

			this.__select_options = cleanSelectOptions(new_options);

			this.requestUpdate("select_options", old_options);
		}

		/**
		 * Set select options
		 *
		 * @deprecated assign to select_options
		 * @param new_options
		 */
		set_select_options(new_options : SelectOption[] | { [key : string] : string }[])
		{
			this.select_options = <SelectOption[]>new_options;
		}

		get select_options() : SelectOption[]
		{
			return this.__select_options;
		}

		/**
		 * Get the node where we're putting the options
		 *
		 * If this were a normal selectbox, this would be just the <select> tag (this._inputNode) but in a more
		 * complicated widget, this could be anything.
		 *
		 * @overridable
		 * @returns {HTMLElement}
		 */
		get _optionTargetNode() : HTMLElement
		{
			return <HTMLElement><unknown>this;
		}

		/**
		 * Render the "empty label", used when the selectbox does not currently have a value
		 *
		 * @overridable
		 * @returns {TemplateResult}
		 */
		_emptyLabelTemplate() : TemplateResult
		{
			return html`${this.emptyLabel}`;
		}

		/**
		 * Render a single option
		 *
		 * Override this method to specify how to render each option.
		 * In a normal selectbox, this would be something like:
		 *```
		 * <option value="${option.value}" title="${option.title}" ?selected=${option.value == this.modelValue}>
		 *     ${option.label}
		 * </option>`;
		 * ```
		 * but you can do whatever you need.  To use a different WebComponent, just use its tag instead of "option".
		 * We should even be able to pass the whole SelectOption across
		 * ```
		 * <special-option .value=${option}></special-option>
		 * ```
		 *
		 * @overridable
		 * @param {SelectOption} option
		 * @returns {TemplateResult}
		 */
		_optionTemplate(option : SelectOption) : TemplateResult
		{
			return html`
                <span>Override _optionTemplate(). ${option.value} => ${option.label}</span>`;
		}

		_groupTemplate(option) : TemplateResult
		{
			if(!Array.isArray(option.value))
			{
				return this._optionTemplate(option);
			}
			return html`

                <sl-menu-label>${this.noLang ? option.label : this.egw().lang(option.label)}</sl-menu-label>
                ${option.value.map(this._optionTemplate.bind(this))}
                <sl-divider></sl-divider>
			`;
		}

		/**
		 * Load extra stuff from the template node.  In particular, we're looking for any <option/> tags added.
		 *
		 * @param {Element} _node
		 */
		loadFromXML(_node : Element)
		{
			let new_options = [];

			// Read the option-tags, but if not rendered there won't be any yet so check existing options
			let options = _node.querySelectorAll("option");
			for(let i = 0; i < options.length; i++)
			{
				new_options.push({
					value: et2_readAttrWithDefault(options[i], "value", options[i].textContent),
					// allow options to contain multiple translated sub-strings eg: {Firstname}.{Lastname}
					label: options[i].textContent.replace(/{([^}]+)}/g, (str, p1) =>
					{
						return this.egw().lang(p1);
					}),
					title: et2_readAttrWithDefault(options[i], "title", "")
				});
			}
			this._xmlOptions = new_options;
			if(options.length == 0 && this.__select_options.length)
			{
				// Start with any existing options, (static options from type)
				// Use a copy since we'll probably be modifying it, and we don't want to change for any other
				// widget of the same static type
				new_options = [...this.__select_options];
			}

			if(this.id)
			{
				new_options = find_select_options(this, {}, new_options);
			}
			if(new_options.length)
			{
				this.select_options = new_options;
			}
		}
	}

	return Et2WidgetWithSelect as unknown as Constructor<SearchMixinInterface> & Et2InputWidgetInterface & T;
}