<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */
	$phpgw_flags = Array(
		'currentapp'	=> 'calendar',
		'noheader'	=> True,
		'nonavbar'	=> True
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	$cal_stream = $phpgw->calendar->open('INBOX',$owner,'');
	$event = $phpgw->calendar->fetch_event($cal_stream,intval($id));
	if(($id > 0) && ($event->owner == $owner) && ($phpgw->calendar->check_perms(PHPGW_ACL_DELETE) == True))
	{
		$thisyear = $event->start->year;
		$thismonth = $event->start->month;

		$phpgw->calendar->delete_event($cal_stream,intval($id));
		$phpgw->calendar->expunge($cal_stream);
	}

	Header('Location: ' . $phpgw->link('index.php','year='.$thisyear.'&month='.$thismonth.'&owner='.$owner));
?>
