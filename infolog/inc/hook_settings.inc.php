<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog Preferences                                               *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	create_check_box('Show open entries: Tasks/Calls/Notes on main screen','homeShowEvents',
		'Should InfoLog display your open entries - not finised tasks, phonecalls or notes - on the main screen. Works only if you dont selected an application for the main screen (in your preferences).');
	
	$ui = CreateObject('infolog.uiinfolog');	// need some labels from
	foreach($ui->filters as $key => $label)
	{
		$filters[$key] = lang($label);
	}
	unset($ui);
	create_select_box('Default Filter for InfoLog','defaultFilter',$filters,
		'This is the filter InfoLog uses when you enter the application. Filters limit the entries to show in the actual view. There are filters to show only finished, still open or futures entries of yourself or all users.');
	unset($filters);

	create_check_box('List no Subs/Childs','listNoSubs',
		'Should InfoLog show Subtasks, -calls or -notes in the normal view or not. You can always view the Subs via there parent.');
/*
	create_check_box('Show full usernames','longNames',
		'Should InfoLog use full names (surname and familyname) or just the loginnames.');
*/
	$show_links = array(
		'all'    => lang('all links and attachments'),
		'links'  => lang('only the links'),
		'attach' => lang('only the attachments'),
		'none'   => lang('no links or attachments')
	);
	create_select_box('Show in the InfoLog list','show_links',$show_links,
		'Should InfoLog show the links to other applications and/or the file-attachments in the InfoLog list (normal view when u enter InfoLog).');
