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

	$GLOBALS['phpgw_info']['etemplate']['hooked'] = True;

	$infolog = CreateObject('infolog.uiinfolog');
	$infolog->index(0,'calendar',$GLOBALS['project_id'],array(
		'menuaction' => 'projects.uiprojects.view',
		'project_id' => $GLOBALS['project_id']
	));
	$GLOBALS['phpgw_info']['flags']['currentapp'] = $save_app;
	unset($GLOBALS['phpgw_info']['etemplate']['hooked']); 
