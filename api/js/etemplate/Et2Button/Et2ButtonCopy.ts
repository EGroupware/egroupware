/**
 * EGroupware eTemplate2 - Button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2Widget} from "../Et2Widget/Et2Widget";
import {SlCopyButton} from "@shoelace-style/shoelace";
import {customElement} from "lit/decorators/custom-element.js";

@customElement('et2-button-copy')
export class Et2ButtonCopy extends Et2Widget(SlCopyButton)
{

}