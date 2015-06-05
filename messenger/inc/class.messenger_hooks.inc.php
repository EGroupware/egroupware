<?php
/**
 * EGroupware - messenger - Hooks, preferences and sidebox-menus and other hooks
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn[at]stylite.de>
 * @package messenger
 * @subpackage setup
 * @copyright (c) 2014 by Stylite AG
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: $
 */

/**
 * Class containing admin, preferences and sidebox-menus and other hooks
 */
class messenger_hooks
{
	/*
	 * Messsenger App Name
	 */
	static $APPNAME = 'messenger';
	
	/**
     * Hook called by link-class to include Messenger in the appregistry of the linkage
     *
     * @return array with method-names
     */
    static function search_link()
    {
        return array(
			'dialog'=> array(
				'menuaction' => 'messenger.messenger_ui.dialog',
			),
			'dialog_id'=> 'id',
			'dialog_popup' => '480x420',
        );
    }
	
	/**
	 * Sidebox menu hook
	 *
	 * @param array|string $hook_data
	 */
	static function sidebox_menu($hook_data)
	{
		$menu_title = $GLOBALS['egw_info']['apps'][self::$APPNAME]['title'];
		$file = Array(
			'Add' => "javascript:egw_openWindowCentered2('".
					egw::link('/index.php',array('menuaction' => 'addressbook.addressbook_ui.edit'),false).	
			"','_blank',870,480,'yes')",
			array(
				'text'    => '<div id="messenger_contacts_sidebox"/>',
				'no_lang' => true,
				'link'    => false,
				'icon'    => false,
			),
			'menuOpened'  => true
		);
		
		// Display Contacts
		display_sidebox(self::$APPNAME,lang('Contacts'),$file);
		
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = Array(
				'Site configuration' => egw::link('/index.php',array(
					'menuaction' => 'admin.uiconfig.index',
					'appname'    => self::$APPNAME,
			)));
		}
		// Display Admin menu
		display_sidebox(self::$APPNAME,lang('Admin'),$file);
	}
	
}