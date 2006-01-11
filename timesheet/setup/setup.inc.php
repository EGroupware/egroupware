<?php
/**************************************************************************\
* eGroupWare - TimeSheet - time tracking for ProjectManager                *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

if (!defined('TIMESHEET_APP'))
{
	define('TIMESHEET_APP','timesheet');
}

$setup_info[TIMESHEET_APP]['name']      = TIMESHEET_APP;
$setup_info[TIMESHEET_APP]['version']   = '0.2.001';
$setup_info[TIMESHEET_APP]['app_order'] = 5;
$setup_info[TIMESHEET_APP]['tables']    = array('egw_timesheet');
$setup_info[TIMESHEET_APP]['enable']    = 1;

$setup_info[TIMESHEET_APP]['author'] = 
$setup_info[TIMESHEET_APP]['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'RalfBecker@outdoor-training.de'
);
$setup_info[TIMESHEET_APP]['license']  = 'GPL';
$setup_info[TIMESHEET_APP]['description'] = 
'Tracking times and other activities for the Projectmanager.';
$setup_info[TIMESHEET_APP]['note'] = 
'The TimeSheet application is sponsored by:<ul>
<li> <a href="http://www.stylite.de" target="_blank">Stylite GmbH</a></li>
<li> <a href="http://www.outdoor-training.de" target="_blank">Outdoor Unlimited Training GmbH</a></li>
</ul>';

/* The hooks this app includes, needed for hooks registration */
$setup_info[TIMESHEET_APP]['hooks']['preferences'] = TIMESHEET_APP.'.ts_admin_prefs_sidebox_hooks.all_hooks';
$setup_info[TIMESHEET_APP]['hooks']['settings'] = TIMESHEET_APP.'.ts_admin_prefs_sidebox_hooks.settings';
$setup_info[TIMESHEET_APP]['hooks']['admin'] = TIMESHEET_APP.'.ts_admin_prefs_sidebox_hooks.all_hooks';
$setup_info[TIMESHEET_APP]['hooks']['sidebox_menu'] = TIMESHEET_APP.'.ts_admin_prefs_sidebox_hooks.all_hooks';
$setup_info[TIMESHEET_APP]['hooks']['search_link'] = TIMESHEET_APP.'.botimesheet.search_link';

/* Dependencies for this app to work */
$setup_info[TIMESHEET_APP]['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('1.2','1.3')
);
$setup_info[TIMESHEET_APP]['depends'][] = array(
	 'appname' => 'etemplate',
	 'versions' => Array('1.2','1.3')
);

