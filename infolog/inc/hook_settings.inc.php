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

	create_check_box('Show open Events: Tasks/Calls/Notes on main screen','homeShowEvents');
	
	$ui = CreateObject('infolog.uiinfolog');	// need some labels from
	create_select_box('Default Filter for InfoLog','defaultFilter',$ui->filters);
	unset($ui);
	
	create_check_box('List no Subs/Childs','listNoSubs');

	create_check_box('Show full usernames','longNames');
