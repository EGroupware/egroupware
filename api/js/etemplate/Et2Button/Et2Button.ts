/**
 * EGroupware eTemplate2 - Button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import '../Et2Image/Et2Image';
import {SlButton} from "@shoelace-style/shoelace";
import {ButtonMixin} from "./ButtonMixin";


export class Et2Button extends ButtonMixin(Et2InputWidget(SlButton))
{

}

customElements.define("et2-button", Et2Button);