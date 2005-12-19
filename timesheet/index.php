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

include_once('setup/setup.inc.php');
$ts_version = $setup_info[TIMESHEET_APP]['version'];
unset($setup_info);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> TIMESHEET_APP, 
		'noheader'		=> True,
		'nonavbar'		=> True
));
include('../header.inc.php');

if ($ts_version != $GLOBALS['egw_info']['apps'][TIMESHEET_APP]['version'])
{
	$GLOBALS['egw']->common->egw_header();
	parse_navbar();
	echo '<p style="text-align: center; color:red; font-weight: bold;">'.lang('Your database is NOT up to date (%1 vs. %2), please run %3setup%4 to update your database.',
		$ts_version,$GLOBALS['egw_info']['apps'][TIMESHEET_APP]['version'],
		'<a href="../setup/">','</a>')."</p>\n";
	$GLOBALS['egw']->common->egw_exit();
}

//ExecMethod(TIMESHEET_APP.'.pm_admin_prefs_sidebox_hooks.check_set_default_prefs');

$GLOBALS['egw']->redirect_link('/index.php',array('menuaction'=>TIMESHEET_APP.'.uitimesheet.index'));
