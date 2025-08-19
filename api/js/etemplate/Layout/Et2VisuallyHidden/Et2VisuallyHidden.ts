/**
 * EGroupware eTemplate2 - Widget visually hiding it's children
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */
import {SlVisuallyHidden} from "@shoelace-style/shoelace";
import {customElement} from "lit/decorators/custom-element.js";
import {Et2Widget} from "../../Et2Widget/Et2Widget";

@customElement("et2-visually-hidden")
class Et2VisuallyHidden extends Et2Widget(SlVisuallyHidden)
{

}