import {Et2CustomfieldsBase} from "./Et2CustomfieldsBase";
import {customElement} from "lit/decorators/custom-element.js";
import {css, html} from "lit";
import {html as staticHtml, unsafeStatic} from "lit/static-html.js";
import {repeat} from "lit/directives/repeat.js";
import {ref} from "lit/directives/ref.js";
import {CUSTOMFIELD_PREFIX} from "./Et2CustomfieldsBase";
import {
	applyCustomfieldWidgetMapping,
	mapCustomfieldToWidget
} from "./Et2CustomfieldWidgetMapper";
import type {Et2CustomfieldWidgetMapping} from "./Et2CustomfieldWidgetMapper";

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
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					display: block;
				}

				.customfields-filters {
					display: flex;
					flex-direction: column;
					gap: var(--sl-spacing-2x-small, 0.25rem);
				}

				.customfields-filters__field {
					min-width: 0;
				}

				.customfields-filters__field > * {
					min-width: 0;
				}
			`
		];
	}

	protected createRenderRoot()
	{
		return this;
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

	private _fieldWidgetTemplate(mapping : Et2CustomfieldWidgetMapping)
	{
		if(!mapping)
		{
			return html``;
		}
		const tag = unsafeStatic(mapping.tagName);
		return staticHtml`
			<${tag}
				${ref((element) => applyCustomfieldWidgetMapping(element, mapping))}
			></${tag}>
		`;
	}

	private _lightDomStylesTemplate()
	{
		return html`
			<style>
				et2-customfields-filters {
					display: block;
				}

				et2-customfields-filters .customfields-filters {
					display: flex;
					flex-direction: column;
					gap: var(--sl-spacing-2x-small, 0.25rem);
				}

				et2-customfields-filters .customfields-filters__field {
					min-width: 0;
				}

				et2-customfields-filters .customfields-filters__field > * {
					min-width: 0;
				}
			</style>
		`;
	}

	render()
	{
		const fields = this.getVisibleFieldNames();
		return html`
			${this._lightDomStylesTemplate()}
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
							${this._fieldWidgetTemplate(mapping)}
						</div>
					`;
				})}
			</div>
		`;
	}
}
