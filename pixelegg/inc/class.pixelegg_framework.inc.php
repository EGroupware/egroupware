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

use EGroupware\Api;

/**
* Stylite Pixelegg template
*/
class pixelegg_framework extends Api\Framework\Ajax
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
	 * @param string $template ='pixelegg' name of the template
	 */
	function __construct($template=self::APP)
	{
		parent::__construct($template);		// call the constructor of the extended class

		// search 'mobile' dirs first
		if (Api\Header\UserAgent::mobile()) array_unshift ($this->template_dirs, 'mobile');
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
		if (!self::$header_done && $_GET['cd'] === 'yes' &&
			!(Api\Header\UserAgent::mobile() || $GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'mobile'))
		{
			self::includeJS('/pixelegg/js/slider.js');
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
	function _color_shader($color, $percent)
	{
		if ($color[0] == '#') $color = ltrim($color, '#');

		$R = hexdec(substr($color,0,2));
		$G = hexdec(substr($color,2,2));
		$B = hexdec(substr($color,4,2));

		$Rs = round($R * (100 + $percent) / 100);
		$Gs = round($G * (100 + $percent) / 100);
		$Bs = round($B * (100 + $percent) / 100);

		if ($Rs > 255) $Rs = 255;
		if ($Gs > 255) $Gs = 255;
		if ($Bs > 255) $Bs = 255;

		return '#'.sprintf('%02X%02X%02X', $Rs, $Gs, $Bs);
	}

	/**
	 * Overwrite to NOT add customizable colors from jDots
	 *
	 * @see Api\Framework::_get_css()
	 * @return array
	 */
	public function _get_css()
	{
		$ret = parent::_get_css();
		// color to use
		$color = str_replace('custom',$GLOBALS['egw_info']['user']['preferences']['common']['template_custom_color'],
			$GLOBALS['egw_info']['user']['preferences']['common']['template_color']);

		// Create a dark variant of the color
		$color_darker = $this->_color_shader($color, -30);

		if (preg_match('/^(#[0-9A-F]+|[A-Z]+)$/i', $GLOBALS['egw_info']['user']['preferences']['common']['sidebox_custom_color']))
		{
			$sidebox_color_hover = $GLOBALS['egw_info']['user']['preferences']['common']['sidebox_custom_color'];
			$sidebox_color = $this->_color_shader($sidebox_color_hover, -30);
		}
		else
		{
			$sidebox_color_hover = $color;
			$sidebox_color = $color_darker;
		}
		if (preg_match('/^(#[0-9A-F]+|[A-Z]+)$/i', $GLOBALS['egw_info']['user']['preferences']['common']['loginbox_custom_color']))
		{
			$loginbox_color = $GLOBALS['egw_info']['user']['preferences']['common']['loginbox_custom_color'];
		}
		else
		{
			$loginbox_color = $color_darker;
		}

		if (preg_match('/^(#[0-9A-F]+|[A-Z]+)$/i',$color))	// a little xss check
		{
			if (!Api\Header\UserAgent::mobile())
			{
				$ret['app_css'] .= "
/**
 * theme changes to color pixelegg for color: $color
 */

/*
-Top window framework header
-sidebar actiuve category :hover
-popup toolbar
*/
div#egw_fw_header, div.egw_fw_ui_category:hover,#loginMainDiv,
.et2_portlet .ui-widget-header{
	background-color: $color !important;
}

/*Login background*/
#loginMainDiv #divAppIconBar #divLogo img[src$='svg'] {
	background-color: $color;
}

/*Center box in login page*/
#loginMainDiv div#centerBox form{
	background-color: $loginbox_color;
}

/*Sidebar menu active category*/
#egw_fw_sidebar #egw_fw_sidemenu .egw_fw_ui_category_active:hover{
	background-color: $sidebox_color_hover !important;
}
#egw_fw_sidebar #egw_fw_sidemenu .egw_fw_ui_category_active{
	background-color: $sidebox_color !important;
}
/* Quick add */
#egw_fw_topmenu_info_items #topmenu_info_quick_add span#quick_add {
	background-color: $color !important;
}
#egw_fw_topmenu_info_items #topmenu_info_quick_add span#quick_add:hover {
	background-color: $sidebox_color !important;
}
.ui-datepicker div.ui-timepicker-div div.ui_tpicker_minute_slider span.ui-slider-handle,
.ui-datepicker table.ui-datepicker-calendar .ui-state-active,
.ui-datepicker div.ui-timepicker-div div.ui_tpicker_hour_slider span.ui-slider-handle,
.ui-widget-header {background-color: $sidebox_color;}
";
				if ($GLOBALS['egw_info']['user']['preferences']['common']['theme'] == 'traditional')
				{
					$ret['app_css'] .= ".dialogFooterToolbar {background-color: $color !important;}";
				}
			}
			else
			/* Mobile theme custom colors*/
			{
				$ret['app_css'] .= "
/* Mobile theme specific color changes */

/*nextmatch header and plus_button in mobile theme*/
body div.et2_nextmatch .search,
body div.et2_nextmatch .search button,
body button.plus_button,
body div.et2_nextmatch .search .nm_action_header,
body div.et2_nextmatch .search .nm_toggle_header,
body div.et2_nextmatch .search .nm_favorites_button,
body #loginMainDiv,
body #egw_fw_firstload,
body .dialogHeadbar{
	background-color: $color;
}
body #egw_fw_sidebar #egw_fw_sidemenu .egw_fw_ui_category_active{background-color: $sidebox_color !important};
";
			}
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
