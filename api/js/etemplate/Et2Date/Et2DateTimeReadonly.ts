/**
 * EGroupware eTemplate2 - Readonly date+time WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {formatDateTime, parseDateTime} from "./Et2Date";
import {Et2DateReadonly} from "./Et2DateReadonly";

/**
 * This is a stripped-down read-only widget used in nextmatch
 */
export class Et2DateTimeReadonly extends Et2DateReadonly
{
	constructor()
	{
		super();
		this.parser = parseDateTime;
		this.formatter = formatDateTime;
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Date is a LitElement
customElements.define("et2-date-time_ro", Et2DateTimeReadonly);