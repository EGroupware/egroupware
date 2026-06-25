import {CUSTOMFIELD_PREFIX, Et2CustomfieldsBase} from "./Et2CustomfieldsBase";
import {customElement} from "lit/decorators/custom-element.js";
import {css, html} from "lit";
import {html as staticHtml, unsafeStatic} from "lit/static-html.js";
import {repeat} from "lit/directives/repeat.js";
import {ref} from "lit/directives/ref.js";
import type {Et2CustomfieldWidgetMapping} from "./Et2CustomfieldWidgetMapper";
import {applyCustomfieldWidgetMapping, mapCustomfieldToWidget} from "./Et2CustomfieldWidgetMapper";
import "../Et2Link/Et2LinkEntry";

/**
 * @summary Renders editable customfield widgets.
 *
 * Field widgets render in light DOM so eTemplate widget lookup,
 * validation, and event paths can discover generated child widgets.
 *
 * @csspart base - Container around all customfield rows.
 * @csspart field - Container for one rendered customfield widget.
 */
@customElement("et2-customfields")
export class Et2Customfields extends Et2CustomfieldsBase
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					display: block;
				}

				.customfields {
					display: grid;
					grid-template-columns: max-content minmax(0, 1fr);
					gap: var(--sl-spacing-2x-small, 0.25rem) var(--sl-spacing-small, 0.75rem);
					align-items: start;
				}

				.customfields__label {
					padding-top: var(--sl-spacing-2x-small, 0.25rem);
				}

				.customfields__field {
					min-width: 0;
				}

				.customfields__field > * {
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

	private _fieldWidgetMapping(fieldName : string, field : Record<string, any>, value : any) : Et2CustomfieldWidgetMapping | null
	{
		const readonly = (this as any).readonly === true;
		return mapCustomfieldToWidget(fieldName, field, value, {
			context: "field",
			readonly,
			prefix: CUSTOMFIELD_PREFIX
		});
	}

	private _fieldWidgetTemplate(mapping : Et2CustomfieldWidgetMapping)
	{
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
                et2-customfields {
                    display: block;
                }

                et2-customfields .customfields {
                    display: grid;
                    grid-template-columns: max-content minmax(0, 1fr);
                    gap: var(--sl-spacing-2x-small, 0.25rem) var(--sl-spacing-small, 0.75rem);
                    align-items: start;
                }

                et2-customfields .customfields__label {
                    padding-top: var(--sl-spacing-2x-small, 0.25rem);
                }

                et2-customfields .customfields__field {
                    min-width: 0;
                }

                et2-customfields .customfields__field > * {
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
            <div class="customfields" part="base">
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
                        <label class="customfields__label"
                               for=${CUSTOMFIELD_PREFIX + fieldName}>${field.label || fieldName}</label>
                        <div class="customfields__field" data-field=${fieldName} part="field">
                            ${this._fieldWidgetTemplate(mapping)}
                        </div>
                    `;
                })}
            </div>
		`;
	}
}
