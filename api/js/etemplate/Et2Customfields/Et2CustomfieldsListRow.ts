import {CUSTOMFIELD_PREFIX, Et2CustomfieldsBase} from "./Et2CustomfieldsBase";
import {customElement} from "lit/decorators/custom-element.js";
import {css, html} from "lit";
import {repeat} from "lit/directives/repeat.js";

/**
 * @summary Renders customfield values as lightweight row text.
 *
 * Datagrid rows can create hundreds of these, so this widget renders plain text
 * values and avoids nested Et2 widgets. Et2Datagrid assigns customfields,
 * fields and row #customfield values directly when a row is bound.
 *
 * @csspart base - Container around all customfield rows.
 * @csspart field - Container for one visible customfield value.
 */
@customElement("et2-customfields-list-row")
export class Et2CustomfieldsListRow extends Et2CustomfieldsBase
{
	static styles = [
		...super.styles,
		css`
			:host {
				display: block;
			}

			.customfields-list-row {
				display: flex;
				flex-direction: column;
				gap: var(--sl-spacing-2x-small, 0.25rem);
			}

			.customfields-list-row__field[hidden] {
				display: none;
			}
		`
	];

	/**
	 * Row renderers only support the datagrid row contract: #customfield keys.
	 */
	private _fieldValue(fieldName : string)
	{
		return this.value?.[CUSTOMFIELD_PREFIX + fieldName] ?? "";
	}

	render()
	{
		const fields = this.getVisibleFieldNames();
		return html`
			<div class="customfields-list-row" part="base">
				${repeat(fields, (fieldName) => fieldName, (fieldName) =>
				{
					const field = this.customfields?.[fieldName] || {};
					const value = this._fieldValue(fieldName);
					return html`
						<div
							class="customfields-list-row__field"
							part="field"
							data-field=${fieldName}
							title=${field.label || fieldName}
							?hidden=${value === null || typeof value === "undefined" || value === ""}
						>${value}</div>
					`;
				})}
			</div>
		`;
	}
}
