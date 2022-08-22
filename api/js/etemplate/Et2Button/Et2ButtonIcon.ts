/**
 * EGroupware eTemplate2 - Button that's just an image
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import '../Et2Image/Et2Image';
import {SlIconButton} from "@shoelace-style/shoelace";
import {ButtonMixin} from "./ButtonMixin";


export class Et2ButtonIcon extends ButtonMixin(Et2InputWidget(SlIconButton))
{

}

customElements.define("et2-button-icon", Et2ButtonIcon);