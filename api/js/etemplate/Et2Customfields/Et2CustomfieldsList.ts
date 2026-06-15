import {CUSTOMFIELD_PREFIX, Et2CustomfieldsBase} from "./Et2CustomfieldsBase";
import {customElement} from "lit/decorators/custom-element.js";
import {css, html} from "lit";
import {html as staticHtml, unsafeStatic} from "lit/static-html.js";
import {repeat} from "lit/directives/repeat.js";
import "../Et2Description/Et2Description";

/**
 * Read-only customfields list.
 *
 * Renders selected customfields using the matching readonly Et2 widget where
 * possible, falling back to et2-description for simple/unknown types.
 *
 * @csspart base - Container around all customfield rows.
 * @csspart field - Container for one visible customfield.
 */
@customElement("et2-customfields-list")
export class Et2CustomfieldsList extends Et2CustomfieldsBase
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					display: block;
				}

				.customfields-list {
					display: flex;
					flex-direction: column;
					gap: var(--sl-spacing-2x-small, 0.25rem);
				}

				.customfields-list__field {
					display: flex;
					align-items: center;
					min-width: 0;
				}

				.customfields-list__field[hidden] {
					display: none;
				}

				.customfields-list__field > * {
					min-width: 0;
				}
			`
		];
	}

	constructor()
	{
		super();
		this.mode = "customfields-list";
	}

	/**
	 * Field widgets are intentionally rendered into light DOM so legacy widget
	 * lookup, validation, and event paths can see the generated child widgets.
	 */
	protected createRenderRoot()
	{
		return this;
	}

	/**
	 * Read values by the supported row/content key first, with unprefixed lookup
	 * retained for non-row list contexts that assign value directly.
	 */
	private _fieldValue(fieldName : string)
	{
		return this.value?.[CUSTOMFIELD_PREFIX + fieldName] ?? this.value?.[fieldName] ?? "";
	}

	private _fieldWidgetType(field : Record<string, any>) : string
	{
		const type = String(field.type || "text").replace(/_/g, "-");
		if(type === "text" || type === "int" || type === "float" || type === "serial")
		{
			return "et2-description";
		}
		const readonlyType = "et2-" + type + "_ro";
		if(customElements.get(readonlyType))
		{
			return readonlyType;
		}
		const editableType = "et2-" + type;
		if(customElements.get(editableType))
		{
			return editableType;
		}
		return "et2-description";
	}

	private _fieldWidgetTemplate(fieldName : string, field : Record<string, any>, value : any)
	{
		const tag = unsafeStatic(this._fieldWidgetType(field));
		const common = {
			id: CUSTOMFIELD_PREFIX + fieldName,
			label: field.label || fieldName
		};
		if(field.help)
		{
			return staticHtml`
				<${tag}
					.id=${common.id}
					.noLang=${true}
					.value=${value}
					.statustext=${field.help}
					.label=${common.label}
					readonly
				></${tag}>
			`;
		}
		return staticHtml`
			<${tag}
				.id=${common.id}
				.noLang=${true}
				.value=${value}
				.label=${common.label}
				readonly
			></${tag}>
		`;
	}

	private _lightDomStylesTemplate()
	{
		return html`
			<style>
				et2-customfields-list {
					display: block;
				}

				et2-customfields-list .customfields-list {
					display: flex;
					flex-direction: column;
					gap: var(--sl-spacing-2x-small, 0.25rem);
				}

				et2-customfields-list .customfields-list__field {
					display: flex;
					align-items: center;
					min-width: 0;
				}

				et2-customfields-list .customfields-list__field[hidden] {
					display: none;
				}

				et2-customfields-list .customfields-list__field > * {
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
			<div class="customfields-list" part="base">
				${repeat(fields, (fieldName) => fieldName, (fieldName) =>
				{
					const field = this.customfields?.[fieldName] || {};
					const value = this._fieldValue(fieldName);
					return html`
						<div
							class="customfields-list__field"
							part="field"
							data-field=${fieldName}
							title=${field.label || fieldName}
							?hidden=${value === null || typeof value === "undefined" || value === ""}
						>
							${this._fieldWidgetTemplate(fieldName, field, value)}
						</div>
					`;
				})}
			</div>
		`;
	}
}
