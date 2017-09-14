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
$setup_info['calendar']['version'] = '16.1.003';
$setup_info['calendar']['app_order'] = 3;
$setup_info['calendar']['enable']  = 1;
$setup_info['calendar']['index']   = 'calendar.calendar_uiviews.index&ajax=true';

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
$setup_info['calendar']['tables'][] = 'egw_cal_repeats';
$setup_info['calendar']['tables'][] = 'egw_cal_user';
$setup_info['calendar']['tables'][] = 'egw_cal_extra';
$setup_info['calendar']['tables'][] = 'egw_cal_dates';
$setup_info['calendar']['tables'][] = 'egw_cal_timezones';

/* The hooks this app includes, needed for hooks registration */
$setup_info['calendar']['hooks']['admin'] = 'calendar_hooks::admin';
$setup_info['calendar']['hooks']['deleteaccount'] = 'calendar.calendar_so.deleteaccount';
$setup_info['calendar']['hooks']['settings'] = 'calendar_hooks::settings';
$setup_info['calendar']['hooks']['verify_settings'] = 'calendar_hooks::verify_settings';
$setup_info['calendar']['hooks']['sidebox_menu'] = 'calendar.calendar_ui.sidebox_menu';
$setup_info['calendar']['hooks']['search_link'] = 'calendar_hooks::search_link';
$setup_info['calendar']['hooks']['config_validate'] = 'calendar_hooks::config_validate';
$setup_info['calendar']['hooks']['timesheet_set'] = 'calendar.calendar_bo.timesheet_set';
$setup_info['calendar']['hooks']['infolog_set'] = 'calendar.calendar_bo.infolog_set';
$setup_info['calendar']['hooks']['export_limit'] = 'calendar_hooks::getAppExportLimit';
$setup_info['calendar']['hooks']['acl_rights'] = 'calendar_hooks::acl_rights';
$setup_info['calendar']['hooks']['categories'] = 'calendar_hooks::categories';
$setup_info['calendar']['hooks']['mail_import'] = 'calendar_hooks::mail_import';

/* Dependencies for this app to work */
$setup_info['calendar']['depends'][] = array(
	 'appname' => 'api',
	 'versions' => Array('16.1')
);
