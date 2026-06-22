import {Et2SelectAccount} from "../../Et2Select/Select/Et2SelectAccount";
import {et2_INextmatchHeader} from "../../et2_extension_nextmatch";
import {FilterMixin} from "./FilterMixin";
import {customElement} from "lit/decorators/custom-element.js";

/**
 * @summary Nextmatch account filter header.
 *
 * Renders a clearable account picker for filtering nextmatch rows by account.
 */
@customElement("et2-nextmatch-header-account")
export class Et2AccountFilterHeader extends FilterMixin(Et2SelectAccount) implements et2_INextmatchHeader
{
	constructor()
	{
		super();
		(this as any).hoist = true;
		(this as any).clearable = true;
	}

}
