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

/* $Id: index.php 20159 2005-12-19 04:23:14Z ralfbecker $ */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'admin', 
));
include('../header.inc.php');

require_once(EGW_INCLUDE_ROOT.'/timesheet/inc/class.botimesheet.inc.php');

$bots =& new botimesheet();
$so_sql =& new so_sql('timesheet',$bots->table_name);

// search timesheet which have a project-field identical to an exiting PM project, but no link to it
$rows = $so_sql->search(false,'ts_id,ts_project,ts_title','','pm_id,link_id','',false,'AND',false,array('link_id IS NULL'),
	' JOIN egw_pm_projects ON ts_project='.$so_sql->db->concat('pm_number',"': '",'pm_title').
	" LEFT JOIN egw_links ON (link_app1='timesheet' AND link_id1=ts_id AND link_app2='projectmanager' AND link_id2=pm_id".
	" OR link_app1='projectmanager' AND link_id1=pm_id AND link_app2='timesheet' and link_id2=ts_id)");

echo "<h1>Fixing links to ProjectManager lost by the bug in the links-class</h1>\n";

if ($rows)
{
	foreach($rows as $row)
	{
		if ($bots->link->link('timesheet',$row['ts_id'],'projectmanager',$row['pm_id']))
		{
			echo "<p>relinked timesheet '$row[ts_title]' with project '$row[ts_project]'</p>\n";
		}
	}
}
echo "<h3>".(is_array($rows) ? count($rows) : 0)." missing links found.</h3>\n";