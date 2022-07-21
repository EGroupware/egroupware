import {egw} from "../../../jsapi/egw_global";
import {et2_INextmatchHeader, et2_nextmatch} from "../../et2_extension_nextmatch";
import {LitElement} from "@lion/core";

// Export the Interface for TypeScript
type Constructor<T = LitElement> = new (...args : any[]) => T;

/**
 * Base class for things that do filter type behaviour in nextmatch header
 * Separated to keep things a little simpler.
 *
 * Currently I assume we're extending an Et2Select, so changes may need to be made for better abstraction
 */
export const FilterMixin = <T extends Constructor>(superclass : T) => class extends superclass implements et2_INextmatchHeader
{
	private nextmatch : et2_nextmatch;

	/**
	 * Override to add change handler
	 *
	 */
	connectedCallback()
	{
		super.connectedCallback();

		// Make sure there's an option for all
		if(!this.emptyLabel && Array.isArray(this.select_options) && !this.select_options.find(o => o.value == ""))
		{
			this.emptyLabel = this.label ? this.label : egw.lang("All");
		}

		this.handleChange = this.handleChange.bind(this);

		// Bind late, maybe that helps early change triggers?
		this.updateComplete.then(() =>
		{
			this.addEventListener("change", this.handleChange);
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("change", this.handleChange);
	}

	handleChange(event)
	{
		if(typeof this.nextmatch == 'undefined')
		{
			// Not fully set up yet
			return;
		}
		let col_filter = {};
		col_filter[this.id] = this.value;
		
		this.nextmatch.applyFilters({col_filter: col_filter});
	}

	/**
	 * Set nextmatch is the function which has to be implemented for the
	 * et2_INextmatchHeader interface.
	 *
	 * @param {et2_nextmatch} _nextmatch
	 */
	setNextmatch(_nextmatch : et2_nextmatch)
	{
		this.nextmatch = _nextmatch;

		// Set current filter value from nextmatch settings
		if(this.nextmatch.activeFilters.col_filter && this.nextmatch.activeFilters.col_filter[this.id])
		{
			this.set_value(this.nextmatch.activeFilters.col_filter[this.id]);
		}
	}
}