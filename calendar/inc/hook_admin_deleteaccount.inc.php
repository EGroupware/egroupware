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
	if (floor($PHP_VERSION ) == 4)
	{
		global $account_id;
	}

	$calendar = CreateObject('calendar.calendar');
	$cal_stream = $calendar->open('INBOX',$account_id,'');
	$calendar->delete_calendar($cal_stream,$account_id);
?>
