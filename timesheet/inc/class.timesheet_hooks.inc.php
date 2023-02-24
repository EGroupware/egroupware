<?php
/**
 * TimeSheet -  diverse hooks: Admin-, Preferences- and SideboxMenu-Hooks
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package timesheet
 * @copyright (c) 2005-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;
use EGroupware\Timesheet\Events;

if (!defined('TIMESHEET_APP'))
{
	define('TIMESHEET_APP','timesheet');
}

/**
 * diverse hooks as static methods
 *
 */
class timesheet_hooks
{
	/**
	 * Instance of timesheet_bo class
	 *
	 * @var timesheet_bo
	 */
	static $timesheet_bo;

	/**
	 * Hook called by link-class to include timesheet in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		unset($location);	// not used, but required by function signature

		return array(
			'query' => TIMESHEET_APP.'.timesheet_bo.link_query',
			'title' => TIMESHEET_APP.'.timesheet_bo.link_title',
			'titles'=> TIMESHEET_APP.'.timesheet_bo.link_titles',
			'view'  => array(
				'menuaction' => TIMESHEET_APP.'.timesheet_ui.edit',
			),
			'view_id' => 'ts_id',
			'view_popup'  => '630x480',
			'edit_popup'  => '630x480',
			'list' => array(
				'menuaction' => 'timesheet.timesheet_ui.index',
				'ajax' => 'true'
			),
			'add' => array(
				'menuaction' => TIMESHEET_APP.'.timesheet_ui.edit',
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',
			'add_popup'  => '630x480',
			'file_access'=> TIMESHEET_APP.'.timesheet_bo.file_access',
			'file_access_user' => true,	// file_access supports 4th parameter $user
			'notify'     => TIMESHEET_APP.'.timesheet_bo.notify',
			'merge'      => true,
			'push_data'  => 'ts_owner',
		);
	}

	/**
	 * Return the timesheets linked with given project(s) AND with entries of other apps, which are also linked to the same project
	 *
	 * Projectmanager will cumulate them in the other apps entries.
	 *
	 * @param array $param int/array $param['pm_id'] project-id(s)
	 * @return array with pm_id, pe_id, pe_app('timesheet'), pe_app_id(ts_id), other_id, other_app, other_app_id
	 */
	static function cumulate($param)
	{
		$links = Link::get_3links(TIMESHEET_APP,'projectmanager',$param['pm_id']);

		$rows = array();
		foreach($links as $link)
		{
			$rows[$link['id']] = array(
				'pm_id'       => $link['id2'],
				'pe_id'       => $link['id'],
				'pe_app'      => $link['app1'],
				'pe_app_id'   => $link['id1'],
				'other_id'    => $link['link3'],
				'other_app'   => $link['app3'],
				'other_app_id'=> $link['id3'],
			);
		}
		return $rows;
	}

	/**
	 * hooks to build projectmanager's sidebox-menu plus the admin and Api\Preferences sections
	 *
	 * @param string/array $args hook args
	 */
	static function all_hooks($args)
	{
		$appname = TIMESHEET_APP;
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>ts_admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			// Magic etemplate2 favorites menu (from nextmatch widget)
			display_sidebox($appname, lang('Favorites'), Framework\Favorites::list_favorites($appname));

			$file = array(
				'Timesheet list' => Egw::link('/index.php',array(
					'menuaction' => 'timesheet.timesheet_ui.index',
					'ajax' => 'true')),
				array(
					'text' => lang('Add %1',lang(Link::get_registry($appname, 'entry'))),
					'no_lang' => true,
					'link' => "javascript:egw.open('','$appname','add')"
				),
			);
			$file[] = ['text'=>'--'];
			$file['Placeholders'] = Egw::link('/index.php','menuaction=timesheet.timesheet_merge.show_replacements');
			display_sidebox($appname,$GLOBALS['egw_info']['apps'][$appname]['title'].' '.lang('Menu'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = Array(
				'Site Configuration' => Egw::link('/index.php','menuaction=admin.admin_config.index&appname=' . $appname,'&ajax=true'),
				'Custom fields' => Egw::link('/index.php','menuaction=admin.admin_customfields.index&appname='.$appname.'&use_private=1&ajax=true'),
				'Global Categories'  => Egw::link('/index.php',array(
					'menuaction' => 'admin.admin_categories.index',
					'appname'    => $appname,
					'global_cats'=> True,
					'ajax' => 'true',
				)),
				'Edit Status' => Egw::link('/index.php','menuaction=timesheet.timesheet_ui.editstatus&ajax=true'),
			);
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}
	}

	/**
	 * populates $GLOBALS['settings'] for the Api\Preferences
	 */
	static function settings()
	{
		$settings = [
			'workingtime_session' => [
				'type'   => 'select',
				'label'  => 'Ask to start and stop working time with session',
				'name'   => 'workingtime_session',
				'values' => [
					'yes' => 'yes',
					'no' => 'no',
				],
				'help'   => 'Would you like to be asked, to start and stop working time, when login in or off',
				'xmlrpc' => True,
				'admin'  => False,
			],
		];
		if (is_null(self::$timesheet_bo)) self::$timesheet_bo = new timesheet_bo();
		if (self::$timesheet_bo->status_labels)
		{
			$settings['predefined_status'] = array(
				'type'   => 'select',
				'label'  => 'Status of created timesheets',
				'name'   => 'predefined_status',
				'values' => self::$timesheet_bo->status_labels,
				'help'   => 'Select the predefined status, when creating a new timesheet ',
				'xmlrpc' => True,
				'admin'  => False,
			);
		}

		// Merge print
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$merge = new timesheet_merge();
			$settings += $merge->merge_preferences();
		}

		return $settings;
	}

	/**
	 * ACL rights and labels used by Calendar
	 *
	 * @param string|array string with location or array with parameters incl. "location", specially "owner" for selected acl owner
	 */
	public static function acl_rights($params)
	{
		unset($params);	// not used, but required by function signature

		return array(
			Acl::READ    => 'read',
			Acl::EDIT    => 'edit',
			Acl::DELETE  => 'delete',
		);
	}

	/**
	 * Hook to tell framework we use standard categories method
	 *
	 * @param string|array $data hook-data or location
	 * @return boolean
	 */
	public static function categories($data)
	{
		unset($data);	// not used, but required by function signature

		return true;
	}

	public static function add_timer($data)
	{
		// hook is called without check if user has permissions for timesheet app
		if (empty($GLOBALS['egw_info']['user']['apps']['timesheet']))
		{
			return;
		}
		$state = Events::timerState();
		// only send/display if at least one timer is not disabled
		if (array_diff(['specific', 'overall'], $state['disable'] ?? []))
		{
			$GLOBALS['egw']->framework->_add_topmenu_info_item('<div id="topmenu_timer" title="'.
				lang('Start & stop timer').'"'.
				($state ? ' data-state="'.htmlspecialchars(json_encode($state)).'"' : '').'>0:00</div>', 'timer');
		}
	}
}