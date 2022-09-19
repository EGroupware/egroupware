import {Et2Description} from "../Et2Description/Et2Description";

/**
 * A readonly textbox is just a description.  You should use that instead, but here it is.
 */
export class Et2TextareaReadonly extends Et2Description
{
}

// We can't bind the same class to a different tag
// @ts-ignore TypeScript is not recognizing that Et2Textbox is a LitElement
customElements.define("et2-textarea_ro", Et2TextareaReadonly);