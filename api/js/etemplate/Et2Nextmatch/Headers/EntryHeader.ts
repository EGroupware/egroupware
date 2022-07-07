import {et2_INextmatchHeader} from "../../et2_extension_nextmatch";
import {FilterMixin} from "./FilterMixin";
import {Et2LinkEntry} from "../../Et2Link/Et2LinkEntry";

/**
 * Filter using a selected entry
 */
export class Et2EntryFilterHeader extends FilterMixin(Et2LinkEntry) implements et2_INextmatchHeader
{

	/**
	 * Override to always return a string appname:id (or just id) for simple (one real selection)
	 * cases, parent returns an object.  If multiple are selected, or anything other than app and
	 * id, the original parent value is returned.
	 */
	get value()
	{
		let value = super.value;
		if(typeof value == "object" && value != null)
		{
			if(!value.app || !value.id)
			{
				return null;
			}

			// If simple value, format it legacy string style, otherwise
			// we return full value
			if(typeof value.id == 'string')
			{
				value = value.app + ":" + value.id;
			}
		}
		return value;
	}

	set value(new_value)
	{
		super.value = new_value;
	}

}

customElements.define("et2-nextmatch-header-entry", Et2EntryFilterHeader);
