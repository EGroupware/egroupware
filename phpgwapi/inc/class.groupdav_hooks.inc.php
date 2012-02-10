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
			unset($addressbooks[$user]);	// allways synced
			unset($addressbooks[$user.'p']);// ignore (optional) private addressbook for now
		}
		$addressbooks = array(
			'A'	=> lang('All'),
			'G'	=> lang('Primary Group'),
			'U' => lang('Accounts'),
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
			'label'  => 'Addressbooks to sync in addition to personal addressbook',
			'name'   => 'addressbook-home-set',
			'help'   => lang('Only supported by a few fully conformant clients (eg. from Apple). If you have to enter a URL, it will most likly not be suppored!').'<br/>'.lang('They will be sub-folders in users home (%1 attribute).','CardDAV "addressbook-home-set"'),
			'values' => $addressbooks,
			'xmlrpc' => True,
			'admin'  => False,
		);

		if ($hook_data['setup'])
		{
			$calendars = array();
		}
		else
		{
			$user = $GLOBALS['egw_info']['user']['account_id'];
			$cal_bo = new calendar_bo();
			foreach ($cal_bo->list_cals() as $entry)
			{
				$calendars[$entry['grantor']] = $entry['name'];
			}
			unset($calendars[$user]);
		}
		$calendars = array(
			'A'	=> lang('All'),
			'G'	=> lang('Primary Group'),
		) + $calendars;

		$settings['calendar-home-set'] = array(
			'type'   => 'multiselect',
			'label'  => 'Calendars to sync in addition to personal calendar',
			'name'   => 'calendar-home-set',
			'help'   => lang('Only supported by a few fully conformant clients (eg. from Apple). If you have to enter a URL, it will most likly not be suppored!').'<br/>'.lang('They will be sub-folders in users home (%1 attribute).','CalDAV "calendar-home-set"'),
			'values' => $calendars,
			'xmlrpc' => True,
			'admin'  => False,
		);

		translation::add_app('infolog');
		$infolog = new infolog_bo();

		if (!($types = $infolog->enums['type']))
		{
			$types = array(
				'task' => 'Tasks',
			);
		}

		$settings['infolog-types'] = array(
			'type'   => 'multiselect',
			'label'  => 'InfoLog types to sync',
			'name'   => 'infolog-types',
			'help'   => 'Which InfoLog types should be synced with the device, default only tasks.',
			'values' => $types,
			'default' => 'task',
			'xmlrpc' => True,
			'admin'  => False,
		);

		$settings['debug_level'] = array(
			'type'   => 'select',
			'label'  => 'Debug level for Apache/PHP error-log',
			'name'   => 'debug_level',
			'help'   => 'Enables debug-messages to Apache/PHP error-log, allowing to diagnose problems on a per user basis.',
			'values' => array(
				'0' => 'Off',
				'r' => 'Requests and truncated responses',
				'f' => 'Requests and full responses to files directory',
				'1' => 'Debug 1 - function calls',
				'2' => 'Debug 2 - more info',
				'3' => 'Debug 3 - complete $_SERVER array',
			),
			'xmlrpc' => true,
			'admin'  => false,
			'default' => '0',
		);
		return $settings;
	}
}