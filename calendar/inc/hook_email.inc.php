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

	global $calendar_id;

	$d1 = strtolower(substr($phpgw_info['server']['app_inc'],0,3));
	if($d1 == 'htt' || $d1 == 'ftp')
	{
		echo 'Failed attempt to break in via an old Security Hole!<br>'."\n";
		$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
		exit;
	}
	unset($d1);

	$cal = CreateObject('calendar.uicalendar');
	echo 'Event ID: '.$calendar_id."<br>\n";

	$event = $cal->bo->read_entry($calendar_id);

	reset($event['participants']);
	while(list($particpants,$status) = each($event['participants']))
	{
		$parts[] = $participants;
	}
	@reset($parts);

	$freetime = $cal->bo->datetime->localdates(mktime(0,0,0,$event['start']['month'],$event['start']['mday'],$event['start']['year']) - $phpgw->calendar->tz_offset);
	echo $cal->timematrix($freetime,$cal->bo->splittime('000000',False),0,$parts);

	echo '</td></tr><tr><td>';

	echo $cal->view_event($event);

	echo '</td></tr><td align="center"><tr>';

	echo $cal->get_response($calendar_id);
?>
