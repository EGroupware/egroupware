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

	$phpgw->calendar->open('INBOX',$owner,'');
	$event = $phpgw->calendar->fetch_event(intval($id));
	if(($id > 0) && ($event->owner == $owner) && ($phpgw->calendar->check_perms(PHPGW_ACL_DELETE) == True))
	{
		$thisyear = $event->start->year;
		$thismonth = $event->start->month;
		$thisday = $event->start->mday;

		$phpgw->calendar->delete_event(intval($id));
		$phpgw->calendar->expunge();
	}

	Header('Location: '.$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/index.php','year='.$thisyear.'&month='.$thismonth.'&day='.$thisday.'&owner='.$owner));
?>
