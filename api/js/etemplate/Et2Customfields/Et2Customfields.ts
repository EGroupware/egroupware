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
					/* cap the label column: max-content lets a long label claim the whole
					   width and squeeze the value to nothing in narrow panes - beyond the
					   cap the label wraps instead */
					grid-template-columns: fit-content(45%) minmax(0, 1fr);
					gap: var(--sl-spacing-2x-small, 0.25rem) var(--sl-spacing-small, 0.75rem);
					align-items: start;
				}

				.customfields__label {
					padding-top: var(--sl-spacing-2x-small, 0.25rem);
					min-width: 0;
					overflow-wrap: break-word;
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

		private _dirtySnapshot : string | null = null;

	/**
	 * Collect '#name' => value pairs from the rendered field widgets.
	 *
	 * Only the legacy DEFAULT_ID instance ('custom_fields') answers with the collected
	 * object - matching et2_customfields_list.getValue() - so an id-less instance in an
	 * edit dialog returns null and etemplate2.getValues() leaves submission to the
	 * individual '#name' child widgets.
	 */
	getValue() : Record<string, any> | null
	{
		return this.id === "custom_fields" ? this._collectValues() : null;
	}

	set_value(value : Record<string, any> | null)
	{
		this.value = value || {};
	}

	isDirty() : boolean
	{
		// no snapshot taken yet means nothing to compare against, not "everything changed"
		return this._dirtySnapshot !== null && this._dirtySnapshot !== JSON.stringify(this._collectValues());
	}

	resetDirty()
	{
		// set_visible()/set_value() only queue a Lit re-render: snapshot after it settles,
		// or the snapshot describes the previous entry's fields and every row click looks
		// like unsaved changes. null in the meantime means "not dirty" to isDirty().
		this._dirtySnapshot = null;
		const token = ++this._dirtySnapshotToken;
		this._renderSettled().then(() =>
		{
			if(token === this._dirtySnapshotToken)
			{
				this._dirtySnapshot = JSON.stringify(this._collectValues());
			}
		});
	}

	private _dirtySnapshotToken = 0;

	private async _renderSettled()
	{
		await this.updateComplete;
		await Promise.all(Array.from(this.querySelectorAll("[data-field] > *"))
			.map((widget : any) => widget.updateComplete)
			.filter(Boolean));
	}

	isValid() : boolean
	{
		return true;
	}

	private _collectValues() : Record<string, any>
	{
		const result : Record<string, any> = {};
		const widgetValue = (widget : any) => (typeof widget?.getValue === "function" ? widget.getValue() : widget?.value) ?? "";
		for(const wrapper of Array.from(this.querySelectorAll("[data-field]")))
		{
			const fieldName = wrapper.getAttribute("data-field");
			const widget = wrapper.querySelector(":scope > :not(label)") as any;
			if(!fieldName || !widget)
			{
				continue;
			}
			result[CUSTOMFIELD_PREFIX + fieldName] = widgetValue(widget);
		}
		return result;
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
                    /* cap the label column: max-content lets a long label claim the whole
                       width and squeeze the value to nothing in narrow panes - beyond the
                       cap the label wraps instead */
                    grid-template-columns: fit-content(45%) minmax(0, 1fr);
                    gap: var(--sl-spacing-2x-small, 0.25rem) var(--sl-spacing-small, 0.75rem);
                    align-items: start;
                }

                et2-customfields .customfields__label {
                    padding-top: var(--sl-spacing-2x-small, 0.25rem);
                    min-width: 0;
                    overflow-wrap: break-word;
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
