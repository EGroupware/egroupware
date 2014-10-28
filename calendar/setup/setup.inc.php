<?php
/**
 * EGroupware - Calendar
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$setup_info['calendar']['name']    = 'calendar';
$setup_info['calendar']['version'] = '14.1.001';
$setup_info['calendar']['app_order'] = 3;
$setup_info['calendar']['enable']  = 1;
$setup_info['calendar']['index']   = 'calendar.calendar_uiviews.index';

$setup_info['calendar']['license']  = 'GPL';
$setup_info['calendar']['description'] =
	'Powerful group calendar with meeting request system and ACL security.';
$setup_info['calendar']['note'] =
	'The calendar has been completly rewritten for eGroupWare 1.2.';
$setup_info['calendar']['author'] = $setup_info['calendar']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'RalfBecker@outdoor-training.de'
);

$setup_info['calendar']['tables'][] = 'egw_cal';
$setup_info['calendar']['tables'][] = 'egw_cal_holidays';
$setup_info['calendar']['tables'][] = 'egw_cal_repeats';
$setup_info['calendar']['tables'][] = 'egw_cal_user';
$setup_info['calendar']['tables'][] = 'egw_cal_extra';
$setup_info['calendar']['tables'][] = 'egw_cal_dates';
$setup_info['calendar']['tables'][] = 'egw_cal_timezones';

/* The hooks this app includes, needed for hooks registration */
$setup_info['calendar']['hooks']['admin'] = 'calendar_hooks::admin';
$setup_info['calendar']['hooks']['deleteaccount'] = 'calendar.calendar_so.deleteaccount';
$setup_info['calendar']['hooks']['home'] = 'calendar_hooks::home';
$setup_info['calendar']['hooks']['preferences'] = 'calendar_hooks::preferences';
$setup_info['calendar']['hooks']['settings'] = 'calendar_hooks::settings';
$setup_info['calendar']['hooks']['sidebox_menu'] = 'calendar.calendar_ui.sidebox_menu';
$setup_info['calendar']['hooks']['search_link'] = 'calendar_hooks::search_link';
$setup_info['calendar']['hooks']['config_validate'] = 'calendar_hooks::config_validate';
$setup_info['calendar']['hooks']['timesheet_set'] = 'calendar.calendar_bo.timesheet_set';
$setup_info['calendar']['hooks']['infolog_set'] = 'calendar.calendar_bo.infolog_set';
$setup_info['calendar']['hooks']['export_limit'] = 'calendar_hooks::getAppExportLimit';

/* Dependencies for this app to work */
$setup_info['calendar']['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('1.7','1.8','1.9')
);
$setup_info['calendar']['depends'][] = array(
	 'appname' => 'etemplate',
	 'versions' => Array('1.7','1.8','1.9')
);

// installation checks for calendar
$setup_info['calendar']['check_install'] = array(
	// check if PEAR is availible
	'' => array(
		'func' => 'pear_check',
		'from' => 'Calendar (iCal import+export)',
	),
	// check if PDO SQLite support is available
	'pdo_sqlite' => array(
		'func' => 'extension_check',
		'from' => 'Calendar',
	),
);

