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

	$icsfile=PHPGW_APP_INC.'/vcal1.ics';
	$fp=fopen($icsfile,'r');
	$contents = explode("\n",fread ($fp, filesize($icsfile)));
	fclose($fp);

	$vcal = CreateObject('calendar.vCalendar');

	$vcalendar = $vcal->read($contents);

	echo "Product ID = ".$vcalendar->prodid."<br>\n";
	echo "Method = ".$vcalendar->method."<br>\n";
	echo "Version = ".$vcalendar->version."<br>\n";
	echo "Summary = ".$vcalendar->event[0]->summary."<br>\n";
	echo "Location = ".$vcalendar->event[0]->location."<br>\n";
	echo "Sequence = ".$vcalendar->event[0]->sequence."<br>\n";	
	echo "Date Start : ".$phpgw->common->show_date(mktime($vcalendar->event[0]->dtstart->hour,$vcalendar->event[0]->dtstart->min,$vcalendar->event[0]->dtstart->sec,$vcalendar->event[0]->dtstart->month,$vcalendar->event[0]->dtstart->mday,$vcalendar->event[0]->dtstart->year))."<br>\n";
	echo "Organizer = ".$vcalendar->event[0]->organizer->mailto->user.'@'.$vcalendar->event[0]->organizer->mailto->host."<br>\n";
	for($i=0;$i<3;$i++)
	{
		echo "Attendee[$i] CN = ".$vcalendar->event[0]->attendee[$i]->cn."<br>\n";
		echo "Attendee[$i] Address= ".$vcalendar->event[0]->attendee[$i]->mailto->user.'@'.$vcalendar->event[0]->attendee[$i]->mailto->host."<br>\n";
		echo "Attendee[$i] Role = ".$vcal->switch_role($vcalendar->event[0]->attendee[$i]->role)."<br>\n";
//		echo "Attendee[$i] RSVP = ".$vcal->switch_rsvp($vcalendar->event[0]->attendee[$i]->rsvp)."<br>\n";
		echo "Attendee[$i] RSVP = ".$vcalendar->event[0]->attendee[$i]->rsvp."<br>\n";
	}
	echo "Class = ".$vcalendar->event[0]->class."<br>\n";
	$phpgw->common->phpgw_footer();
?>
