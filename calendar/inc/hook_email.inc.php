<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	global $owner, $rights, $calendar_id;

	$d1 = strtolower(substr($phpgw_info['server']['app_inc'],0,3));
	if($d1 == 'htt' || $d1 == 'ftp')
	{
		echo 'Failed attempt to break in via an old Security Hole!<br>'."\n";
		$phpgw->common->phpgw_exit();
	}
	unset($d1);

	$tmp_app_inc = $phpgw_info['server']['app_inc'];
	$phpgw_info['server']['app_inc'] = $phpgw->common->get_inc_dir('calendar');

	include($phpgw_info['server']['app_inc'].'/functions.inc.php');

	$str = '';

	$id = $calendar_id;

	echo 'Event ID: '.$id."<br>\n";

	$cal_stream = $phpgw->calendar->open('INBOX',$owner,'');
	$event = $phpgw->calendar->fetch_event($id);

	reset($event->participants);
	while(list($particpants,$status) = each($event->participants))
	{
		$parts[] = $participants;
	}
	@reset($parts);

	$freetime = $phpgw->calendar->localdates(mktime(0,0,0,$event->start->month,$event->start->mday,$event->start->year) - $phpgw->calendar->tz_offset);
	echo $phpgw->calendar->timematrix($freetime,$phpgw->calendar->splittime('000000',False),0,$parts);

	echo '</td></tr><tr><td>';

	echo $phpgw->calendar->view_event($event);

	echo '</td></tr><td align="center"><tr>';

	$phpgw->calendar->fetch_event($id);
	echo $phpgw->calendar->get_response();
	
	$phpgw_info['server']['app_inc'] = $tmp_app_inc;
?>
