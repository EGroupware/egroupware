<?php
/**
 * EGroupware  eTemplate2 widget browser
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2013 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage tools
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate;

use EGroupware\Api;

/**
 * eTemplate2 widget browser
 *
 * View & play with et2 widgets.  Most of the good stuff happens
 * on the client side via js, this is the server side.
 */
class WidgetBrowser
{

	public $public_functions = array(
		'index'	=>	true
	);

	public static function index()
	{
		$GLOBALS['egw_info']['flags']['currentapp'] = 'etemplate';
		$GLOBALS['egw_info']['flags']['app_header'] = 'et2 Widgets';
                //'js_link_registry'      => True,

		// Widget browser code
		Api\Framework::includeJS('/api/js/etemplate/widget_browser.js');

		// Include the etemplate2 javascript code
		Api\Framework::includeJS('.', 'etemplate2', 'etemplate');

		// Include the jQuery-UI CSS - many more complex widgets use it
		$theme = 'redmond';
		Api\Framework::includeCSS("/vendor/bower-asset/jquery-ui/themes/$theme/jquery-ui-1.10.3.custom.css");

		Api\Framework::includeCSS('/api/templates/default/etemplate2.css');

		Api\Framework::includeCSS('api','widget_browser',false);

		// load translations
		Api\Translation::add_app('etemplate');

		$GLOBALS['egw']->framework->render('
<div id="widget_list"></div>
<div id="widget_container"></div>');
	}
}
