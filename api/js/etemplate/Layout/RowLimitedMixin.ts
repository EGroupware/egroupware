// Export the Interface for TypeScript
import {css, LitElement} from "lit";

type Constructor<T = {}> = new (...args : any[]) => T;

/**
 * Mixin to support widgets that have a set number of rows.
 * Whether that's a maximum or a fixed size, implementation is up to the widget.
 * Set rows=0 to clear.
 *
 * To implement in a webcomponent set height or max-height based on the --rows CSS variable,
 * and the --row-height of one option row this mixin provides:
 *  max-height: calc(var(--rows, 5) * var(--row-height));
 * @param {T} superclass
 * @constructor
 */
export const RowLimitedMixin = <T extends Constructor<LitElement>>(superclass : T) =>
{
	class RowLimit extends superclass
	{
		static get styles()
		{
			return [
				// Parent may return a single cssResult, not an array
				...(super.styles ? (Array.isArray(super.styles) ? super.styles : [super.styles]) : []),
				css`
				:host {
                    /* one option row: text line + the sl-menu-item's vertical padding */
                    --row-height: calc(var(--sl-font-size-medium) * var(--sl-line-height-normal) + 2 * var(--sl-spacing-2x-small));
				}
				`
			]
		}
		set rows(row_count : string | number)
		{
			if(isNaN(Number(row_count)) || !row_count)
			{
				this.style.removeProperty("--rows");
				this.removeAttribute("rows");
			}
			else
			{
				this.style.setProperty("--rows", row_count);
				this.setAttribute("rows", row_count)
			}
		}

		get rows() : string | number
		{
			return this.style.getPropertyValue("--rows");
		}

	}

	return RowLimit;// as unknown as superclass & T;
}