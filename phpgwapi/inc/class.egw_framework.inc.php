<?php
/**
 * EGroupware API - framework baseclass
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> rewrite in 12/2006
 * @author Pim Snel <pim@lingewoud.nl> author of the idots template set
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage framework
 * @access public
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Framework: virtual base class for all template sets
 *
 * @deprecated use Api\Framework
 */
abstract class egw_framework extends Api\Framework
{
	/**
	 * Set/get Content-Security-Policy attributes for script-src: 'unsafe-eval' and/or 'unsafe-inline'
	 *
	 * Using CK-Editor currently requires both to be set :(
	 *
	 * Old pre-et2 apps might need to call egw_framework::csp_script_src_attrs(array('unsafe-eval','unsafe-inline'))
	 *
	 * EGroupware itself currently still requires 'unsafe-eval'!
	 *
	 * @param string|array $set =null 'unsafe-eval' and/or 'unsafe-inline' (without quotes!) or URL (incl. protocol!)
	 * @deprecated use Api\Header\ContentSecurityPolicy::add('script-src', $set);
	 */
	public static function csp_script_src_attrs($set=null)
	{
		Api\Header\ContentSecurityPolicy::add('script-src', $set);
	}

	/**
	 * Set/get Content-Security-Policy attributes for style-src: 'unsafe-inline'
	 *
	 * EGroupware itself currently still requires 'unsafe-inline'!
	 *
	 * @param string|array $set =null 'unsafe-inline' (without quotes!) and/or URL (incl. protocol!)
	 * @deprecated use Api\Header\ContentSecurityPolicy::add('style-src', $set);
	 */
	public static function csp_style_src_attrs($set=null)
	{
		Api\Header\ContentSecurityPolicy::add('style-src', $set);
	}

	/**
	 * Set/get Content-Security-Policy attributes for connect-src:
	 *
	 * @param string|array $set =array() URL (incl. protocol!)
	 * @deprecated use Api\Header\ContentSecurityPolicy::add('connect-src', $set);
	 */
	public static function csp_connect_src_attrs($set=null)
	{
		Api\Header\ContentSecurityPolicy::add('connect-src', $set);
	}

	/**
	 * Set/get Content-Security-Policy attributes for frame-src:
	 *
	 * Calling this method with an empty array sets no frame-src, but "'self'"!
	 *
	 * @param string|array $set =null URL (incl. protocol!)
	 * @deprecated use Api\Header\ContentSecurityPolicy::add('frame-src', $set);
	 */
	public static function csp_frame_src_attrs($set=null)
	{
		Api\Header\ContentSecurityPolicy::add('frame-src', $set);
	}

	/**
	 * Get the (depricated) application footer
	 *
	 * @deprecated
	 * @return string html
	 */
	protected static function _get_app_footer()
	{
		ob_start();
		// Include the apps footer files if it exists
		if (EGW_APP_INC != EGW_API_INC &&	// this prevents an endless inclusion on the homepage
			                                // (some apps set currentapp in hook_home => it's not releyable)
			(file_exists (EGW_APP_INC . '/footer.inc.php') || isset($_GET['menuaction'])) &&
			$GLOBALS['egw_info']['flags']['currentapp'] != 'home' &&
			$GLOBALS['egw_info']['flags']['currentapp'] != 'login' &&
			$GLOBALS['egw_info']['flags']['currentapp'] != 'logout' &&
			!@$GLOBALS['egw_info']['flags']['noappfooter'])
		{
			list(, $class) = explode('.',(string)$_GET['menuaction']);
			if ($class && is_object($GLOBALS[$class]) && is_array($GLOBALS[$class]->public_functions) &&
				isset($GLOBALS[$class]->public_functions['footer']))
			{
				$GLOBALS[$class]->footer();
			}
			elseif(file_exists(EGW_APP_INC.'/footer.inc.php'))
			{
				include(EGW_APP_INC . '/footer.inc.php');
			}
		}
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	/**
	 * Sets an onLoad action for a page
	 *
	 * @param string $code ='' javascript to be used
	 * @param boolean $replace =false false: append to existing, true: replace existing tag
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @return string content of onXXX tag after adding code
	 */
	static function set_onload($code='',$replace=false)
	{
		if ($replace || empty(self::$body_tags['onLoad']))
		{
			self::$body_tags['onLoad'] = $code;
		}
		else
		{
			self::$body_tags['onLoad'] .= $code;
		}
		return self::$body_tags['onLoad'];
	}

	/**
	 * Sets an onUnload action for a page
	 *
	 * @param string $code ='' javascript to be used
	 * @param boolean $replace =false false: append to existing, true: replace existing tag
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @return string content of onXXX tag after adding code
	 */
	static function set_onunload($code='',$replace=false)
	{
		if ($replace || empty(self::$body_tags['onUnload']))
		{
			self::$body_tags['onUnload'] = $code;
		}
		else
		{
			self::$body_tags['onUnload'] .= $code;
		}
		return self::$body_tags['onUnload'];
	}

	/**
	 * Sets an onBeforeUnload action for a page
	 *
	 * @param string $code ='' javascript to be used
	 * @param boolean $replace =false false: append to existing, true: replace existing tag
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @return string content of onXXX tag after adding code
	 */
	static function set_onbeforeunload($code='',$replace=false)
	{
		if ($replace || empty(self::$body_tags['onBeforeUnload']))
		{
			self::$body_tags['onBeforeUnload'] = $code;
		}
		else
		{
			self::$body_tags['onBeforeUnload'] .= $code;
		}
		return self::$body_tags['onBeforeUnload'];
	}

	/**
	 * Sets an onResize action for a page
	 *
	 * @param string $code ='' javascript to be used
	 * @param boolean $replace =false false: append to existing, true: replace existing tag
	 * @deprecated since 14.1 use app.js et2_ready method instead to execute code or bind a handler (CSP will stop onXXX attributes!)
	 * @return string content of onXXX tag after adding code
	 */
	static function set_onresize($code='',$replace=false)
	{
		if ($replace || empty(self::$body_tags['onResize']))
		{
			self::$body_tags['onResize'] = $code;
		}
		else
		{
			self::$body_tags['onResize'] .= $code;
		}
		return self::$body_tags['onResize'];
	}

	/**
	* Checks to make sure a valid package and file name is provided
	*
	* @param string $package package or complete path (relative to EGW_SERVER_ROOT) to be included
	* @param string|array $file =null file to be included - no ".js" on the end or array with get params
	* @param string $app ='phpgwapi' application directory to search - default = phpgwapi
	* @param boolean $append =true should the file be added
	* @deprecated use Api\Framework::includeJS($package, $file=null, $app='api')
	*/
	static function validate_file($package, $file=null, $app='api')
	{
		self::includeJS($package, $file, $app);
	}

	/**
	 * Include favorites when generating the page server-side
	 *
	 * @param string $app application, needed to find preferences
	 * @param string $default =null preference name for default favorite, default "nextmatch-$app.index.rows-favorite"
	 * @deprecated use Api\Framework\Favorites::favorite_list
	 * @return array with a single sidebox menu item (array) containing html for favorites
	 */
	public static function favorite_list($app, $default=null)
	{
		return Api\Framework\Favorites::list_favorites($app, $default);
	}
}

/**
 * Public functions to be compatible with the exiting eGW framework
 */
if (!function_exists('parse_navbar'))
{
	/**
	 * echo's out the navbar
	 *
	 * @deprecated use $GLOBALS['egw']->framework->navbar() or $GLOBALS['egw']->framework::render()
	 */
	function parse_navbar()
	{
		echo $GLOBALS['egw']->framework->navbar();
	}
}

if (!function_exists('display_sidebox'))
{
	/**
	 * echo's out a sidebox menu
	 *
	 * @deprecated use $GLOBALS['egw']->framework->sidebox()
	 */
	function display_sidebox($appname,$menu_title,$_file)
	{
		$file = str_replace('preferences.uisettings.index', 'preferences.preferences_settings.index', $_file);
		$GLOBALS['egw']->framework->sidebox($appname,$menu_title,$file);
	}
 }
