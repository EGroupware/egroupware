import {CUSTOMFIELD_PREFIX, Et2CustomfieldsBase} from "./Et2CustomfieldsBase";
import {customElement} from "lit/decorators/custom-element.js";
import {css, html} from "lit";
import {html as staticHtml, unsafeStatic} from "lit/static-html.js";
import {repeat} from "lit/directives/repeat.js";
import {ref} from "lit/directives/ref.js";
import "../Et2Description/Et2Description";
import {applyCustomfieldWidgetMapping, mapCustomfieldToWidget} from "./Et2CustomfieldWidgetMapper";

/**
 * @summary Renders read-only customfield widgets.
 *
 * Field widgets render in light DOM so eTemplate widget lookup,
 * validation, and event paths can discover generated child widgets. Selected
 * customfields use the matching readonly Et2 widget when possible and fall back
 * to `et2-description` for unsupported types.
 *
 * @csspart base - Container around all customfield rows.
 * @csspart field - Container for one visible customfield.
 */
@customElement("et2-customfields-list")
export class Et2CustomfieldsList extends Et2CustomfieldsBase
{
	static styles = [
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

	private _fieldWidgetTemplate(fieldName : string, field : Record<string, any>, value : any)
	{
		const mapping = mapCustomfieldToWidget(fieldName, field, value, {
			context: "list",
			readonly: true,
			prefix: CUSTOMFIELD_PREFIX
		});
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
