<?php
/**
 * EGroupware - TimeSheet - setup definitions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package timesheet
 * @subpackage setup
 * @copyright (c) 2005-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

if (!defined('TIMESHEET_APP'))
{
	define('TIMESHEET_APP','timesheet');
}

$setup_info[TIMESHEET_APP]['name']      = TIMESHEET_APP;
$setup_info[TIMESHEET_APP]['version']   = '23.1';
$setup_info[TIMESHEET_APP]['app_order'] = 5;
$setup_info[TIMESHEET_APP]['tables']    = array('egw_timesheet','egw_timesheet_extra','egw_timesheet_events');
$setup_info[TIMESHEET_APP]['enable']    = 1;
$setup_info[TIMESHEET_APP]['index']     = 'timesheet.timesheet_ui.index&ajax=true';

$setup_info[TIMESHEET_APP]['author'] =
$setup_info[TIMESHEET_APP]['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'RalfBecker@outdoor-training.de'
);
$setup_info[TIMESHEET_APP]['license']  = 'GPL';
$setup_info[TIMESHEET_APP]['description'] =
'Tracking times and other activities for the Projectmanager.';
$setup_info[TIMESHEET_APP]['note'] =
'The TimeSheet application was sponsored by:<ul>
<li> <a href="http://www.stylite.de" target="_blank">Stylite AG</a></li>
<li> <a href="http://www.outdoor-training.de" target="_blank">Outdoor Unlimited Training GmbH</a></li>
</ul>';

/* The hooks this app includes, needed for hooks registration */
$setup_info[TIMESHEET_APP]['hooks']['categories'] = 'timesheet_hooks::categories';
$setup_info[TIMESHEET_APP]['hooks']['settings'] = 'timesheet_hooks::settings';
$setup_info[TIMESHEET_APP]['hooks']['admin'] = 'timesheet_hooks::all_hooks';
$setup_info[TIMESHEET_APP]['hooks']['sidebox_menu'] = 'timesheet_hooks::all_hooks';
$setup_info[TIMESHEET_APP]['hooks']['search_link'] = 'timesheet_hooks::search_link';
$setup_info[TIMESHEET_APP]['hooks']['pm_cumulate'] = 'timesheet_hooks::cumulate';
$setup_info[TIMESHEET_APP]['hooks']['deleteaccount'] = 'timesheet.timesheet_bo.deleteaccount';
$setup_info[TIMESHEET_APP]['hooks']['acl_rights'] = 'timesheet_hooks::acl_rights';
$setup_info[TIMESHEET_APP]['hooks']['topmenu_info'] = 'timesheet_hooks::add_timer';
$setup_info[TIMESHEET_APP]['hooks']['config_validate'] = 'EGroupware\\Timesheet\\Events::config_validate';

/* Dependencies for this app to work */
$setup_info[TIMESHEET_APP]['depends'][] = array(
	 'appname' => 'api',
	 'versions' => Array('23.1')
);