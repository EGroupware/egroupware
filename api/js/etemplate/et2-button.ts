/**
 * EGroupware eTemplate2 - Button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {LitElement,html} from "https://cdn.skypack.dev/lit-element";
import {SlButton} from "https://cdn.jsdelivr.net/npm/@shoelace-style/shoelace@2.0.0-beta.44/dist/shoelace.js";

export class Et2Button extends SlButton
{
	size='small';
}
customElements.define("et2-button",Et2Button);