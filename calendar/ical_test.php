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
	$phpgw_info['flags']['currentapp'] = 'calendar';
	include('../header.inc.php');

	$icsfile='/home/httpd/html/phpgroupware/calendar/inc/vcal1.ics';
	$fp=fopen($icsfile,'r');
	$contents = explode("\n",fread ($fp, filesize($icsfile)));
	fclose($fp);

	$vcal = CreateObject('calendar.vCalendar');

	$vcalendar = $vcal->read($contents);

	echo "Product ID = ".$vcalendar->prodid."<br>\n";
	echo "Method = ".$vcalendar->method."<br>\n";
	echo "Version = ".$vcalendar->version."<br>\n";
	echo "Sequence = ".$vcalendar->event[0]->sequence."<br>\n";
	$datet = CreateObject('phpgwapi.datetime');
	$datetime = mktime($vcalendar->event[0]->dtstart->hour,$vcalendar->event[0]->dtstart->min,$vcalendar->event[0]->dtstart->sec,$vcalendar->event[0]->dtstart->month,$vcalendar->event[0]->dtstart->mday,$vcalendar->event[0]->dtstart->year);
	echo "Date Start : ".$phpgw->common->show_date($datetime)."<br>\n";
	echo "Organizer = ".$vcalendar->event[0]->organizer->mailto->user.'@'.$vcalendar->event[0]->organizer->mailto->host."<br>\n";
	echo "Attendee[0] = ".$vcalendar->event[0]->attendee[0]->mailto->user.'@'.$vcalendar->event[0]->attendee[0]->mailto->host."<br>\n";
	echo "Attendee[1] = ".$vcalendar->event[0]->attendee[1]->mailto->user.'@'.$vcalendar->event[0]->attendee[1]->mailto->host."<br>\n";
	echo "Class = ".$vcalendar->event[0]->class."<br>\n";
	$phpgw->common->phpgw_footer();
?>
