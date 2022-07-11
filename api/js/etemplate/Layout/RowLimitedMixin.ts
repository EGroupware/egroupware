// Export the Interface for TypeScript
import {LitElement} from "@lion/core";

type Constructor<T = {}> = new (...args : any[]) => T;

/**
 * Mixin to support widgets that have a set number of rows.
 * Whether that's a maximum or a fixed size, implementation is up to the widget.
 * Set rows=0 to clear.
 *
 * To implement in a webcomponent set height or max-height based on the --rows CSS variable:
 *  max-height: calc(var(--rows, 5) * 1.3rem);
 * @param {T} superclass
 * @constructor
 */
export const RowLimitedMixin = <T extends Constructor<LitElement>>(superclass : T) =>
{
	class RowLimit extends superclass
	{
		static get properties()
		{
			return {
				...super.properties,
			}
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