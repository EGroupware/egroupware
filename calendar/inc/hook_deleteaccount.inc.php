<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
	/* $Id$ */

	// Delete all records for a user
	$cal = CreateObject('calendar.bocalendar');

	if(intval($GLOBALS['HTTP_POST_VARS']['new_owner'])==0)
	{
		$cal->delete_calendar(intval($GLOBALS['HTTP_POST_VARS']['account_id']));
	}
	else
	{
		$cal->change_owner(intval($GLOBALS['HTTP_POST_VARS']['account_id']),intval($GLOBALS['HTTP_POST_VARS']['new_owner']));
	}
?>
