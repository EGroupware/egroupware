<?php
/**
 * EGroupware: GroupDAV hooks: eg. preferences
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * GroupDAV hooks: eg. preferences
 */
class groupdav_hooks
{
	/**
	 * Show GroupDAV preferences link in preferences
	 *
	 * @param string|array $args
	 */
	public static function menus($args)
	{
		$appname = 'groupdav';
		$location = is_array($args) ? $args['location'] : $args;

		if ($location == 'preferences')
		{
			$file = array(
				'Preferences'     => egw::link('/index.php','menuaction=preferences.uisettings.index&appname='.$appname),
			);
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Preferences'),$file);
			}
		}
	}

	/**
	 * populates $settings for the preferences
	 *
	 * @param array|string $hook_data
	 * @return array
	 */
	static function settings($hook_data)
	{
		$settings = array();

		if ($hook_data['setup'])
		{
			$addressbooks = array();
		}
		else
		{
			$user = $GLOBALS['egw_info']['user']['account_id'];
			$addressbook_bo = new addressbook_bo();
			$addressbooks = $addressbook_bo->get_addressbooks(EGW_ACL_READ);
			unset($addressbooks[$user]);	// Use P for personal addressbook
			unset($addressbooks[$user.'p']);// ignore (optional) private addressbook for now
		}
		$addressbooks = array(
			'P'	=> lang('Personal'),
			'G'	=> lang('Primary Group'),
			//'U' => lang('Accounts'),	// not yet working
			'O' => lang('All in one'),
			'A'	=> lang('All'),
		) + $addressbooks;

		// rewriting owner=0 to 'U', as 0 get's always selected by prefs
		if (!isset($addressbooks[0]))
		{
			unset($addressbooks['U']);
		}
		else
		{
			unset($addressbooks[0]);
		}

		$settings['addressbook-home-set'] = array(
			'type'   => 'multiselect',
			'label'  => 'Addressbooks to sync with Apple clients',
			'name'   => 'addressbook-home-set',
			'help'   => 'Addressbooks for CardDAV attribute "addressbook-home-set".',
			'values' => $addressbooks,
			'xmlrpc' => True,
			'admin'  => False,
			'default' => 'P',
		);

		$settings['debug_level'] = array(
			'type'   => 'select',
			'label'  => 'Debug level for Apache/PHP error-log',
			'name'   => 'debug_level',
			'help'   => 'Enables debug-messages to Apache/PHP error-log, allowing to diagnose problems on a per user basis.',
			'values' => array(
				'0' => '0 - off',
				'1' => '1 - function calls',
				'2' => '2 - more info',
				'3' => '3 - complete $_SERVER array',
			),
			'xmlrpc' => true,
			'admin'  => false,
			'default' => '0',
		);
		return $settings;
	}
}