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

	$save_app = $GLOBALS['phpgw_info']['flags']['currentapp']; 
	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'infolog'; 

	$GLOBALS['phpgw']->translation->add_app('infolog');

	/* echo "<p>hook_addressbook_view(ab_id=$ab_id)</p>"; */

	$infolog = CreateObject('infolog.uiinfolog');
	$infolog->get_list(True,'addr',$GLOBALS['ab_id']);

	$GLOBALS['phpgw_info']['flags']['currentapp'] = $save_app; 
