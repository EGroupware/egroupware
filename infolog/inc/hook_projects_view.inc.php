<?php
	/**************************************************************************\
	* phpGroupWare - Info Log administration                                   *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	global $phpgw_info,$phpgw;
	$save_app = $phpgw_info['flags']['currentapp']; 
	$phpgw_info['flags']['currentapp'] = 'infolog'; 

	$phpgw->translation->add_app('infolog');

	//echo "<p>hook_projects_view($GLOBALS['project_id'])</p>";

	$infolog = CreateObject('infolog.uiinfolog');
	$infolog->get_list(True,'proj',$GLOBALS['project_id']);

	$phpgw_info['flags']['currentapp'] = $save_app; 
