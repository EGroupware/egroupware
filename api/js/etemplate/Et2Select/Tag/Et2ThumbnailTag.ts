/**
 * EGroupware eTemplate2 - Thumbnail Tag WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import {css} from "@lion/core";
import shoelace from "../../Styles/shoelace";
import {Et2Tag} from "./Et2Tag";

/**
 * Used in a Et2ThumbnailSelect with multiple=true
 *
 * It's just easier to deal with the styling here due to scoping
 */
export class Et2ThumbnailTag extends Et2Tag
{

	static get styles()
	{
		return [
			super.styles,
			shoelace, css`
			.tag {
				--icon-width: 100%;
				max-width: 15em;
				height: unset;
			}
			
			::slotted(img) {
				width: 100%;
				height: 50px;
			} 

		`];
	}

	constructor(...args : [])
	{
		super(...args);
		this.pill = false
	}

}

customElements.define("et2-thumbnail-tag", Et2ThumbnailTag);