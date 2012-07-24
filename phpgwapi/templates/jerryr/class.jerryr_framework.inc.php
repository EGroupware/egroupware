<?php
/**
 * EGroupware jerryr template set
 *
 * @link http://www.egroupware.org
 * @author Jerry Ruhe <jerry.ruhe@dilawri-group.ca>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> rewrite in 12/2006
 * @author Pim Snel <pim@lingewoud.nl> author of the idots template set
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage framework
 * @access public
 * @version $Id$
 */

require_once(EGW_SERVER_ROOT.'/phpgwapi/templates/idots/class.idots_framework.inc.php');

/**
 * eGW jerryr template
 */
class jerryr_framework extends idots_framework
{
	/**
	 * Constructor, calls the contstructor of the extended class
	 *
	 * @param string $template='jerryr'
	 * @return jerryr_framework
	 */
	function __construct($template='jerryr')
	{
		parent::__construct($template);
	}

	/**
	 * Check if current user agent is supported
	 *
	 * Currently we do NOT support:
	 * - iPhone, iPad, Android, SymbianOS due to iframe scrolling problems of Webkit
	 *
	 * @return boolean
	 */
	public static function is_supported_user_agent()
	{
		if (html::$ua_mobile)
		{
			return false;
		}
		return true;
	}

	/**
	* Display the string with html of the topmenu if its enabled
	*
	* @param array $vars
	* @param array $apps
	* @return string
	*/
	function topmenu(array $vars,array $apps)
	{
		$this->tplsav2->menuitems = array();
		$this->tplsav2->menuinfoitems = array();

		if($GLOBALS['egw_info']['user']['apps']['home'] && isset($apps['home']))
		{
			$this->_add_topmenu_item($apps['home']);
		}
		/*if($GLOBALS['egw_info']['user']['apps']['manual'])
		{
			$this->_add_topmenu_item('manual');
		}
		*/
		if($GLOBALS['egw_info']['user']['apps']['preferences'])
		{
			$this->_add_topmenu_item($apps['preferences']);
		}
		if($GLOBALS['egw_info']['user']['apps']['manual'] && isset($apps['manual']))
		{
			$this->_add_topmenu_item($apps['manual']);
		}
		//$this->_add_topmenu_item('about',lang('About %1',$GLOBALS['egw_info']['apps'][$GLOBALS['egw_info']['flags']['currentapp']]['title']));
		$this->_add_topmenu_item($apps['logout']);

		$this->tplsav2->assign('info_icons',$this->topmenu_icon_arr);

		if($GLOBALS['egw_info']['user']['apps']['notifications'])
		{
			$this->_add_topmenu_info_item($this->_get_notification_bell());
		}
		$this->_add_topmenu_info_item($vars['user_info']);
		$this->_add_topmenu_info_item($vars['current_users']);
		$this->_add_topmenu_info_item($vars['quick_add']);

		$this->tplsav2->display('topmenu.tpl.php');
	}
}
