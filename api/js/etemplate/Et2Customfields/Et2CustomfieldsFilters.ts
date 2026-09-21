import {CUSTOMFIELD_PREFIX, Et2CustomfieldsBase, lightDomStylesTemplate} from "./Et2CustomfieldsBase";
import {customElement} from "lit/decorators/custom-element.js";
import {html} from "lit";
import {html as staticHtml, unsafeStatic} from "lit/static-html.js";
import {repeat} from "lit/directives/repeat.js";
import {ref} from "lit/directives/ref.js";
import {
	applyCustomfieldWidgetMapping,
	mapCustomfieldToWidget
} from "./Et2CustomfieldWidgetMapper";
import type {Et2CustomfieldWidgetMapping} from "./Et2CustomfieldWidgetMapper";

import styles from "./Et2CustomfieldsFilters.styles";
/**
 * @summary Renders customfield filter selectboxes.
 *
 * Only legacy filter-eligible customfields render: select-style fields and
 * app-backed link-entry fields. Filemanager and non-select fields are skipped.
 *
 * @csspart base - Container around all customfield filter controls.
 * @csspart field - Container for one rendered customfield filter.
 */
@customElement("et2-customfields-filters")
export class Et2CustomfieldsFilters extends Et2CustomfieldsBase
{
	/**
	 * Field widgets are intentionally rendered into light DOM so legacy widget lookup,
	 * validation, and event paths can see the generated child widgets.  That also means Lit
	 * never adopts `static styles`, so our CSS is rendered as a <style> of our own.
	 */
	protected createRenderRoot()
	{
		return this;
	}

	/**
	 * A filter box has no tabs, so a customfield's tab must not decide whether it can be
	 * filtered on.  Which entries a filter selects has nothing to do with which tab of the edit
	 * dialog shows the field.
	 */
	protected get honoursTabs() : boolean
	{
		return false;
	}

	private _fieldValue(fieldName : string)
	{
		return this.value?.[CUSTOMFIELD_PREFIX + fieldName] ?? this.value?.[fieldName] ?? "";
	}

	private _apps()
	{
		try
		{
			return this.egw?.()?.link_app_list?.() || {};
		}
		catch(e)
		{
			return {};
		}
	}

	private _fieldWidgetMapping(fieldName : string, field : Record<string, any>, value : any) : Et2CustomfieldWidgetMapping | null
	{
		return mapCustomfieldToWidget(fieldName, field, value, {
			context: "filters",
			readonly: false,
			apps: this._apps(),
			prefix: CUSTOMFIELD_PREFIX
		});
	}

	/** The generated filter controls, by unprefixed customfield name. */
	protected widgets : Record<string, any> = {};

	/** Filters already given their starting value, so a re-render does not undo the user's choice. */
	private _valued : WeakSet<Element> = new WeakSet();

	private _fieldWidgetTemplate(fieldName : string, mapping : Et2CustomfieldWidgetMapping)
	{
		if(!mapping)
		{
			return html``;
		}
		const tag = unsafeStatic(mapping.tagName);
		return staticHtml`
			<${tag}
				${ref((element) => this._adoptFilterWidget(fieldName, element, mapping))}
			></${tag}>
		`;
	}

	/**
	 * Keep hold of a generated filter so we can report what it is set to.
	 *
	 * @param {string} fieldName Unprefixed customfield name.
	 * @param {Element} element The generated control, or undefined when it was removed.
	 * @param {Et2CustomfieldWidgetMapping} mapping Tag and attributes for it.
	 */
	private _adoptFilterWidget(fieldName : string, element : Element | undefined, mapping : Et2CustomfieldWidgetMapping)
	{
		if(!element)
		{
			delete this.widgets[fieldName];
			return;
		}
		// Only the parent link, not addChild(): that would put the control in the widget tree, where
		// it would be reported a second time under an id of its own.
		(<any>element)._parent = this;
		const attrs = {...mapping.attrs};
		if(this._valued.has(element))
		{
			// Already placed, so by now it holds whatever the user filtered by - handing it the
			// value we started with would throw that away.  Lit gives ref() a fresh callback each
			// render, so this is tracked per element rather than per name.
			delete attrs.value;
		}
		this._valued.add(element);
		applyCustomfieldWidgetMapping(element, {tagName: mapping.tagName, attrs});
		this.widgets[fieldName] = element;
	}

	/**
	 * et2_IInput: what the customfield filters are set to, keyed as the nextmatch expects.
	 *
	 * A nextmatch takes its customfield filters as one `col_filter` map of `{"#name": value}`, and
	 * this widget is given that id - so the whole set is reported here.  The generated controls are
	 * deliberately outside the widget tree, so without this nothing would ask them and filtering
	 * would quietly stop working.
	 */
	getValue() : Record<string, any>
	{
		const value = {};
		for(const [fieldName, widget] of Object.entries(this.widgets))
		{
			if(typeof widget?.getValue === "function")
			{
				value[CUSTOMFIELD_PREFIX + fieldName] = widget.getValue();
			}
		}
		return value;
	}

	/**
	 * et2_IInput: true once any filter has been changed.
	 */
	isDirty() : boolean
	{
		return Object.values(this.widgets).some((widget) => typeof widget?.isDirty === "function" && widget.isDirty());
	}

	/**
	 * et2_IInput: take the current filters as the unchanged ones.
	 */
	resetDirty() : void
	{
		Object.values(this.widgets).forEach((widget) => widget?.resetDirty?.());
	}

	/**
	 * et2_IInput: a filter has nothing to validate, but the interface is only recognised when all
	 * four methods are there.
	 */
	isValid(messages : string[]) : boolean
	{
		return Object.values(this.widgets).every(
			(widget) => typeof widget?.isValid !== "function" || widget.isValid(messages)
		);
	}

	render()
	{
		const fields = this.getVisibleFieldNames();
		return html`
			${lightDomStylesTemplate(styles)}
			<div class="customfields-filters" part="base">
				${repeat(fields, (fieldName) => fieldName, (fieldName) =>
				{
					const field = this.customfields?.[fieldName] || {};
					const value = this._fieldValue(fieldName);
					const mapping = this._fieldWidgetMapping(fieldName, field, value);
					if(!mapping)
					{
						return html``;
					}
					return html`
						<div class="customfields-filters__field" data-field=${fieldName} part="field">
							${this._fieldWidgetTemplate(fieldName, mapping)}
						</div>
					`;
				})}
			</div>
		`;
	}
}
