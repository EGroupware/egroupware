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

export class Et2AppIcon extends Et2Image
{
	constructor()
	{
		super();

		this.defaultSrc = 'nonav';
	}

	protected parse_href(_app : string) : string
	{
		if (!_app) _app = this.egw().app_name();

		const src = (this.egw().app(_app, 'icon_app') || _app)+'/'+(this.egw().app(_app, 'icon') || 'navbar');

		return super.parse_href(src);
	}
}
customElements.define("et2-appicon", Et2AppIcon as any, {extends: 'img'});