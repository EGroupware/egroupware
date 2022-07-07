import {et2_INextmatchHeader} from "../../et2_extension_nextmatch";
import {Et2Select} from "../../Et2Select/Et2Select";
import {FilterMixin} from "./FilterMixin";

/**
 * Filter from a provided list of options
 */
export class Et2FilterHeader extends FilterMixin(Et2Select) implements et2_INextmatchHeader
{
	constructor(...args : any[])
	{
		super(...args);
		this.hoist = true;
		this.clearable = true;
	}
}

customElements.define("et2-nextmatch-header-filter", Et2FilterHeader);
