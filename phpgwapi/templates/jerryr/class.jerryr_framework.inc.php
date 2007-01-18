<?php
/**
 * eGW jerryr template
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
	function jerryr_framework($template='jerryr')
	{
		$this->idots_framework($template);
	}

	function topmenu()
	
	{
                 $this->tplsav2->menuitems = array();
                 $this->tplsav2->menuinfoitems = array();

                 $this->apps = $this->_get_navbar_apps();

                 $this->_add_topmenu_item('home');

                 /*if($GLOBALS['egw_info']['user']['apps']['manual'])
                 {
                        $this->_add_topmenu_item('manual');
                 }
                 */
                 if($GLOBALS['egw_info']['user']['apps']['preferences'])
                 {
                        $this->_add_topmenu_item('preferences');
                 }
		if($GLOBALS['egw_info']['user']['apps']['manual'] && $this->apps['manual'])
		{
			$this->_add_topmenu_item('manual');
		}
                 //$this->_add_topmenu_item('about',lang('About %1',$GLOBALS['egw_info']['apps'][$GLOBALS['egw_info']['flags']['currentapp']]['title']));
                 $this->_add_topmenu_item('logout');

                 $this->tplsav2->assign('info_icons',$this->topmenu_icon_arr);

                 $this->_add_topmenu_info_item($this->_user_time_info());
                 $this->_add_topmenu_info_item($this->_current_users());
                 $this->_add_topmenu_info_item($this->_get_quick_add());

                 $this->tplsav2->display('topmenu.tpl.php');
          }

	
}
