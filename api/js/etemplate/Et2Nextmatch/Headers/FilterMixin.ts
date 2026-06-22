import {egw} from "../../../jsapi/egw_global";
import {et2_INextmatchHeader, et2_nextmatch} from "../../et2_extension_nextmatch";
import {LitElement, PropertyValues} from "lit";
import {ET2_NEXTMATCH_FILTER_EVENT, Et2NextmatchFilterEventDetail} from "./events";

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

	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate?.(changedProperties);
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated?.(changedProperties);
	}

	/**
	 * Override to add change handler
	 *
	 */
	connectedCallback()
	{
		super.connectedCallback();

		const filter = this as any;
		// Make sure there's an option for all
		if(!filter.emptyLabel && Array.isArray(filter.select_options) && !filter.select_options.find(o => o.value == ""))
		{
			filter.emptyLabel = filter.label ? filter.label : egw.lang("All");
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
		let col_filter = {};
		col_filter[this.id] = (this as any).value;

		const filterEvent = new CustomEvent<Et2NextmatchFilterEventDetail>(ET2_NEXTMATCH_FILTER_EVENT, {
			bubbles: true,
			composed: true,
			cancelable: true,
			detail: {
				filters: {col_filter: col_filter}
			}
		});
		this.dispatchEvent(filterEvent);
		queueMicrotask(() =>
		{
			// Legacy fallback for pages still relying on direct nextmatch coupling.
			if(filterEvent.defaultPrevented || !this.nextmatch)
			{
				return;
			}
			this.nextmatch?.applyFilters(filterEvent.detail.filters);
		});
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
			(this as any).set_value(this.nextmatch.activeFilters.col_filter[this.id]);
		}
	}
}
