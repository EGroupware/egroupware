/**
 * EGroupware eTemplate2 - et2-appicon widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */


import {Et2Image} from "./Et2Image";
import {property} from "lit/decorators/property.js";

export class Et2AppIcon extends Et2Image
{
	@property({type: Boolean})
	kdots: boolean;
	constructor()
	{
		super();

		this.defaultSrc = 'nonav';
	}

	protected parse_href(_app : string) : string
	{
		if (!_app) _app = this.egw().app_name();
		const icon = this.kdots?'kdots-navbar':'navbar'
		if(this.kdots){
			this.style.setProperty('color',`var(--${_app}-color)`);
			this.inline =true;
		}
		const src = (this.egw().app(_app, 'icon_app') || _app)+'/'+(this.egw().app(_app, 'icon') || icon);

		return super.parse_href(src);
	}
}

customElements.define("et2-appicon", Et2AppIcon);