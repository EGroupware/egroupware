<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	// Delete all records for a user
	$info = CreateObject('infolog.soinfolog');

	$info->change_delete_owner(intval($GLOBALS['HTTP_POST_VARS']['account_id']),
		intval($GLOBALS['HTTP_POST_VARS']['new_owner']));

	unset($info);
?>
