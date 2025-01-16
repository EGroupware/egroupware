/**
 * EGroupware eTemplate2 - JS Diff object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 */

import {et2_register_widget} from "./et2_core_widget";
import {Et2Diff} from "./Et2Diff/Et2Diff";

/**
 * Class that displays the diff between two [text] values
 *
 * @augments et2_valueWidget
 */
export class et2_diff extends Et2Diff
{
}
et2_register_widget(et2_diff, ["diff"]);