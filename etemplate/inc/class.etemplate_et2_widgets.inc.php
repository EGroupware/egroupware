<?php
/**
 * EGroupware  eTemplate2 widget browser
 * View & play with et2 widgets - PHP
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2013 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage tools
 * @version $Id$
 */

/**
 * eTemplate2 widget browser
 * View & play with et2 widgets.  Most of the good stuff happens
 * on the client side via js, this is the server side.
 */

class etemplate_et2_widgets
{

	public $public_functions = array(
		'index'	=>	true
	);

	public static function index($content = array())
	{
		$GLOBALS['egw_info']['flags']['currentapp'] = 'etemplate';
		$GLOBALS['egw_info']['flags']['app_header'] = 'et2 Widgets';
                //'js_link_registry'      => True,

		// Widget browser code
		egw_framework::validate_file('/etemplate/js/widget_browser.js');

		// Include the etemplate2 javascript code
                egw_framework::validate_file('.', 'etemplate2', 'etemplate');

		egw_framework::includeCSS('/etemplate/templates/default/etemplate2.css');

                // Include the jQuery-UI CSS - many more complex widgets use it
                $theme = 'redmond';
                egw_framework::includeCSS("/phpgwapi/js/jquery/jquery-ui/$theme/jquery-ui-1.8.21.custom.css");

		egw_framework::includeCSS('etemplate','widget_browser',false);

		// load translations
		translation::add_app('etemplate');
		
		common::egw_header();
		parse_navbar();

		echo '
<div id="widget_list"></div>
<div id="widget_container"></div>
                <script>
                        egw(window).ready(function() {
				var wb = new widget_browser(
					document.getElementById("widget_list"),
					document.getElementById("widget_container")
				);
                        }, null, true);
                </script>';
		common::egw_footer();
	}
}
