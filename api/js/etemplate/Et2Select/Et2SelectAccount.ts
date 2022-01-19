/**
 * EGroupware eTemplate2 - Description WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2Select} from "./Et2Select";


export class Et2SelectAccount extends Et2Select
{

}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-account", Et2SelectAccount);