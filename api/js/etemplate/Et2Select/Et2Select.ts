import {SelectOption} from "./FindSelectOptions";
import {css, html, TemplateResult} from "lit"
import shoelace from "../Styles/shoelace";
import {SlSelect} from "@shoelace-style/shoelace";
import {RowLimitedMixin} from "../Layout/RowLimitedMixin";
import {Et2widgetWithSelectMixin} from "./Et2WidgetWithSelectMixin";

// export Et2WidgetWithSelect which is used as type in other modules
export class Et2WidgetWithSelect extends RowLimitedMixin(Et2widgetWithSelectMixin(SlSelect))
{
};

export class Et2Select extends Et2WidgetWithSelect
{
	static get styles()
	{
		return [
			// Parent (SlSelect) returns a single cssResult, not an array
			shoelace,
			super.styles,
			css`
			  :host {
				:host {
				  display: block;
				  flex: 1 0 auto;
				  --icon-width: 20px;
				}


				::slotted(img), img {
				}


				::slotted(img), img {
				  vertical-align: middle;
				}

				/* Get rid of padding before/after options */

				sl-menu::part(base) {
				}

				/* Get rid of padding before/after options */

				sl-menu::part(base) {
				  padding: 0px;
				}

				/* No horizontal scrollbar, even if options are long */

				.dropdown__panel {
				  overflow-x: clip;
				}

				/* Ellipsis when too small */

				.select__tags {
				  max-width: 100%;
				}

				.select__label {
				  display: block;
				  text-overflow: ellipsis;
				  /* This is usually not used due to flex, but is the basis for ellipsis calculation */
				  width: 10ex;
				}

				/** multiple=true uses tags for each value **/
				/* styling for icon inside tag (not option) */

				.tag_image {
				  margin-right: var(--sl-spacing-x-small);
				}

				/* Maximum height + scrollbar on tags (+ other styling) */

				.select__tags {
				  margin-left: 0px;
				  max-height: initial;
				  overflow-y: auto;
				  gap: 0.1rem 0.5rem;
				}

				.select--medium .select__tags {
				  padding-top: 2px;
				  padding-bottom: 2px;
				}

				:host([rows]) .select__control > .select__label > .select__tags {
				  max-height: calc(var(--rows, 5) * 29px);
				}

				:host([rows='1']) .select__tags {
				  overflow: hidden;
				}

				/* Keep overflow tag right-aligned.  It's the only sl-tag. */

				.select__tags sl-tag {
				  margin-left: auto;
				}

				select:hover {
				  box-shadow: 1px 1px 1px rgb(0 0 0 / 60%);
				}

				/* Hide dropdown trigger when multiple & readonly */

				:host([readonly][multiple]) .select__expand-icon {
				  display: none;
				}

				/* Style for the list */

				::part(listbox) {
				  min-width: fit-content;
				  overflow-y: auto;
				}
			`
		];
	}


	/**
	 * Used by Et2WidgetWithSelect to render each option into the select
	 *
	 * @param {SelectOption} option
	 * @returns {TemplateResult}
	 */
	_optionTemplate(option : SelectOption) : TemplateResult
	{
		// Tag used must match this.optionTag, but you can't use the variable directly.
		// Pass option along so SearchMixin can grab it if needed
		return html`
            <sl-option value="${option.value}"
                       title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
                       class="${option.class}" .option=${option}
                       ?disabled=${option.disabled}
            >
                ${this._iconTemplate(option)}
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-option>`;
	}


	/**
	 * Get the icon for the select option
	 *
	 * @param option
	 * @protected
	 */
	protected _iconTemplate(option)
	{
		if(!option.icon)
		{
			return html``;
		}

		return html`
            <et2-image slot="prefix" part="icon" style="width: var(--icon-width)"
                       src="${option.icon}"></et2-image>`
	}

}

if(typeof customElements.get("et2-select") === "undefined")
{
	customElements.define("et2-select", Et2Select);
}