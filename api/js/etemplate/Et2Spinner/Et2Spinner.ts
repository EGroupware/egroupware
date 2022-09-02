/**
 * EGroupware eTemplate2 - Spinner widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {Et2Widget} from "../Et2Widget/Et2Widget";
import {SlSpinner} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";
import {css} from "@lion/core";

export class Et2Spinner extends Et2Widget(SlSpinner)
{
	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			css`
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * font-size		size is based on font size
			 * --track-width	The width of the track.
			 * --track-color	The color of the track.
			 * --indicator-color	The color of the indicator.
			 * --speed	The time it takes for the spinner to complete one animation cycle.
			 */
			style: {type: String},
		}
	}

	constructor()
	{
		super();
		this.style = '';
	}

	/**
	 * Handle changes that have to happen based on changes to properties
	 *
	 */
	updated(changedProperties)
	{
		super.updated(changedProperties);
		if (changedProperties.has("style")) {
			if(this.style)
			{
				this.getDOMNode().setAttribute('style', this.style);
			}
		}
	}
}
customElements.define("et2-spinner", Et2Spinner);