import {Et2SelectAccount} from "../../Et2Select/Et2SelectAccount";
import {et2_INextmatchHeader} from "../../et2_extension_nextmatch";
import {FilterMixin} from "./FilterMixin";

/**
 * Filter by account
 */
export class Et2AccountFilterHeader extends FilterMixin(Et2SelectAccount) implements et2_INextmatchHeader
{
	constructor(...args : any[])
	{
		super();
		this.hoist = true;
		this.clearable = true;
	}

}

customElements.define("et2-nextmatch-header-account", Et2AccountFilterHeader);