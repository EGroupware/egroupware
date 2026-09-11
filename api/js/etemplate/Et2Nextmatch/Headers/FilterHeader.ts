import {et2_INextmatchHeader} from "../../et2_extension_nextmatch";
import {Et2Select} from "../../Et2Select/Et2Select";
import {FilterMixin} from "./FilterMixin";
import {customElement} from "lit/decorators/custom-element.js";

/**
 * @summary Nextmatch select filter header.
 *
 * Renders a clearable select control populated from the provided option list.
 */
@customElement("et2-nextmatch-header-filter")
export class Et2FilterHeader extends FilterMixin(Et2Select) implements et2_INextmatchHeader
{
	constructor()
	{
		super();
		(this as any).hoist = true;
		(this as any).clearable = true;
	}
}
