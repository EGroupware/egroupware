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
	if (floor(phpversion()) == 4)
	{
		global $account_id, $new_owner;
	}

	$calendar = CreateObject('calendar.calendar');
	$calendar->open('INBOX',$account_id,'');

	if(intval($new_owner)==0)
	{
		$calendar->delete_calendar(intval($account_id));
	}
	else
	{
		$calendar->change_owner(intval($account_id),intval($new_owner));
	}
?>
