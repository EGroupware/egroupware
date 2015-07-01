<?php
/**
 * EGroupware: Stylite Pixelegg template
 *
 * et2 Messages
 *
 * Please do NOT change css-files directly, instead change less-files and compile them!
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Stefan Reinhard <stefan.reinhard@pixelegg.de>
 * @package pixelegg
 * @version $Id$
 */

/**
* Stylite Pixelegg template
*/
class pixelegg_framework extends jdots_framework
{
	/**
	 * Appname used for everything but JS includes, which we re-use from jdots
	 */
	const APP = 'pixelegg';
	/**
	 * Appname used to include javascript code
	 */
	const JS_INCLUDE_APP = 'pixelegg';

	/**
	 * Enable to use this template sets login.tpl for login page
	 */
	const LOGIN_TEMPLATE_SET = true;

	/**
	 * Constructor
	 *
	 * Overwritten to set own app/template name (parent can NOT use static::APP!)
	 *
	 * @param string $template='pixelegg' name of the template
	 */
	function __construct($template=self::APP)
	{
		parent::__construct($template);		// call the constructor of the extended class
	}

	/**
	 * Render header
	 *
	 * Overwritten to load our slider.js
	 *
	 * @param array $extra
	 * @return type
	 */
	function header(array $extra=array())
	{
		// load our slider.js, but only if framework requested
		if (!self::$header_done && $_GET['cd'] === 'yes')
		{
			self::validate_file('/pixelegg/js/slider.js');
		}
		return parent::header($extra);
	}

	/**
	 * Make given color lighter or darker by percentage
	 *
	 * @param string $color in hex
	 * @param int $percent int
	 * @return string returns color hex format (for instance: #2b2b2b)
	 */
	function _color_shader($color, $percent) {

		$R = hexdec(substr($color,0,2));
		$G = hexdec(substr($color,2,2));
		$B = hexdec(substr($color,4,2));

		$R = round($R * (100 + $percent) / 100);
		$G = round($G * (100 + $percent) / 100);
		$B = round($B * (100 + $percent) / 100);

		$R = ($R<255)?$R:255;
		$G = ($G<255)?$G:255;
		$B = ($B<255)?$B:255;

		$RR = (strlen(dechex($R))==1?"0".dechex($R):dechex($R));
		$GG = (strlen(dechex($G))==1?"0".dechex($G):dechex($G));
		$BB = (strlen(dechex($B))==1?"0".dechex($B):dechex($B));

		return '#'.$RR.$GG.$BB;
	}

	/**
	 * Overwrite to NOT add customizable colors from jDots
	 *
	 * @see egw_framework::_get_css()
	 * @return array
	 */
	public function _get_css()
	{
		$ret = parent::_get_css();
		// color to use
		$color = str_replace('custom',$GLOBALS['egw_info']['user']['preferences']['common']['template_custom_color'],
			$GLOBALS['egw_info']['user']['preferences']['common']['template_color']);
		//The hex value of the color
		$color_hex = ltrim($color, '#');

		// Create a drak variant of the color
		$color_hex_dark = $this->_color_shader($color_hex, 15);
		// Create a draker variant of the color
		$color_hex_darker = $this->_color_shader($color_hex, -30);

		if (preg_match('/^(#[0-9A-F]+|[A-Z]+)$/i',$color))	// a little xss check
		{
			$ret['app_css'] = "
/**
 * theme changes to color pixelegg for color: $color
 */

/*
-Top window framework header
-sidebar actiuve category :hover
-popup toolbar
*/
div#egw_fw_header, div.egw_fw_ui_category:hover,#loginMainDiv,#loginMainDiv #divAppIconBar #divLogo,
#egw_fw_sidebar #egw_fw_sidemenu .egw_fw_ui_category_active:hover,
.dialogFooterToolbar, .et2_portlet .ui-widget-header{
	background-color: $color !important;
}

/*Login background*/
#loginMainDiv #divAppIconBar #divLogo img[src$='svg'] {
	background-image: -webkit-linear-gradient(top, $color, $color);
	background-image: -moz-linear-gradient(top, $color, $color);
	background-image: -o-linear-gradient(top,$color, $color);
	background-image: linear-gradient(to bottom, $color, $color);
}

/*Center box in login page*/
#loginMainDiv div#centerBox {
	background-image: -webkit-linear-gradient(top,$color_hex_dark,$color_hex_darker);
	background-image: -moz-linear-gradient(top,$color_hex_dark,$color_hex_darker);
	background-image: -o-linear-gradient(top,$color_hex_dark,$color_hex_darker);
	background-image: linear-gradient(to bottom, $color_hex_dark,$color_hex_darker);
	border-top: solid 1px $color_hex_darker;
	border-left: solid 1px $color_hex_darker;
	border-right: solid 1px $color_hex_darker;
	border-bottom: solid 1px $color_hex_dark;
}

/*Sidebar menu active category*/
#egw_fw_sidebar #egw_fw_sidemenu .egw_fw_ui_category_active{
	background-color: $color_hex_darker !important;
}
";
		}
		return $ret;
	}

	/**
	 * displays a login screen
	 *
	 * Reimplemented to remove site_title from login box and display it as loginscreenmessage, if none set.
	 *
	 * @param string $extra_vars for login url
	 * @param string $change_passwd =null string with message to render input fields for password change
	 */
	function login_screen($extra_vars, $change_passwd=null)
	{
		if (empty($GLOBALS['loginscreenmessage']))
		{
			$GLOBALS['loginscreenmessage'] = '<h1>'.$GLOBALS['egw_info']['server']['site_title'].'</h1>';
		}
		unset($GLOBALS['egw_info']['server']['site_title']);

		return parent::login_screen($extra_vars, $change_passwd);
	}
}
