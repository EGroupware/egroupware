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
	 * Overwrite to NOT add customizable colors from jDots
	 *
	 * @see egw_framework::_get_css()
	 * @return array
	 */
	public function _get_css()
	{
		return egw_framework::_get_css();
	}

	/**
	 * displays a login screen
	 *
	 * Reimplemented to remove site_title from login box and display it as loginscreenmessage, if none set.
	 *
	 * @param string $extra_vars for login url
	 */
	function login_screen($extra_vars)
	{
		if (empty($GLOBALS['loginscreenmessage']))
		{
			$GLOBALS['loginscreenmessage'] = '<h1>'.$GLOBALS['egw_info']['server']['site_title'].'</h1>';
		}
		unset($GLOBALS['egw_info']['server']['site_title']);

		return parent::login_screen($extra_vars);
	}
}
