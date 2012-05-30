<?php
/**
 * TimeSheet -  diverse hooks: Admin-, Preferences- and SideboxMenu-Hooks
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package timesheet
 * @copyright (c) 2005-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
		return array(
			'query' => TIMESHEET_APP.'.timesheet_bo.link_query',
			'title' => TIMESHEET_APP.'.timesheet_bo.link_title',
			'titles'=> TIMESHEET_APP.'.timesheet_bo.link_titles',
			'view'  => array(
				'menuaction' => TIMESHEET_APP.'.timesheet_ui.view',
			),
			'view_id' => 'ts_id',
			'view_popup'  => '600x400',
			'view_list' => 'timesheet.timesheet_ui.index',
			'add' => array(
				'menuaction' => TIMESHEET_APP.'.timesheet_ui.edit',
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',
			'add_popup'  => '600x400',
			'file_access'=> TIMESHEET_APP.'.timesheet_bo.file_access',
			'notify'     => TIMESHEET_APP.'.timesheet_bo.notify',
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
		$links = egw_link::get_3links(TIMESHEET_APP,'projectmanager',$param['pm_id']);

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
	 * hooks to build projectmanager's sidebox-menu plus the admin and preferences sections
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
			$file = array(
			);
			display_sidebox($appname,$GLOBALS['egw_info']['apps'][$appname]['title'].' '.lang('Menu'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Preferences'     => egw::link('/index.php','menuaction=preferences.uisettings.index&appname='.$appname),
				'Grant Access'    => egw::link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
				'Edit Categories' => egw::link('/index.php','menuaction=preferences.uicategories.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
			);
			// until we have more then one preference
			if (is_null(self::$timesheet_bo)) self::$timesheet_bo = new timesheet_bo();
			if (!self::$timesheet_bo->status_labels) unset($file['Preferences']);

			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Preferences'),$file);
			}
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Site Configuration' => egw::link('/index.php','menuaction=admin.uiconfig.index&appname=' . $appname),
				'Custom fields' => egw::link('/index.php','menuaction=admin.customfields.edit&appname='.$appname),
				'Global Categories'  => egw::link('/index.php',array(
					'menuaction' => 'admin.uicategories.index',
					'appname'    => $appname,
					'global_cats'=> True)),
				'Edit Status' => egw::link('/index.php','menuaction=timesheet.timesheet_ui.editstatus'),
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
	 * populates $GLOBALS['settings'] for the preferences
	 */
	static function settings()
	{
		if (is_null(self::$timesheet_bo)) self::$timesheet_bo = new timesheet_bo();

		return array(
			'predefined_status' => array(
				'type'   => 'select',
				'label'  => 'Status of created timesheets',
				'name'   => 'predefined_status',
				'values' => self::$timesheet_bo->status_labels,
				'help'   => 'Select the predefined status, when creating a new timesheet ',
				'xmlrpc' => True,
				'admin'  => False,
			),
		);
	}
}
