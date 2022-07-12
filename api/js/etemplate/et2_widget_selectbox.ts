/**
 * EGroupware eTemplate2 - JS Selectbox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @author Andreas Stöckel
 * @copyright Nathan Gray 2011
 */

import {Et2Select} from "./Et2Select/Et2Select";
import {Et2SelectReadonly} from "./Et2Select/Et2SelectReadonly";

/**
 * @deprecated use Et2Select
 */
export class et2_selectbox extends Et2Select{}

/**
 * @deprecated use Et2SelectReadonly
 */
export type et2_selectbox_ro = Et2SelectReadonly;