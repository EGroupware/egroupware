/**
 * EGroupware eTemplate2 - Readonly Textbox widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2Description} from "../Et2Description/Et2Description";

/**
 * A readonly textbox is just a description.  You should use that instead, but here it is.
 */
export class Et2TextboxReadonly extends Et2Description
{
}

// We can't bind the same class to a different tag
// @ts-ignore TypeScript is not recognizing that Et2Textbox is a LitElement
customElements.define("et2-textbox_ro", Et2TextboxReadonly);