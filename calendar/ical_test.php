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

	echo "Product ID = ".$vcalendar->prodid->value."<br>\n";
	echo "Method = ".$vcalendar->method->value."<br>\n";
	echo "Version = ".$vcalendar->version->value."<br>\n";

	for($i=0;$i<count($vcalendar->event);$i++)
	{
		echo "<br>\nEVENT<br>\n";
		if($vcalendar->event[$i]->calscale->value)
		{
			echo "Calscale = ".$vcalendar->event[$i]->calscale->value."<br>\n";
		}
		if($vcalendar->event[$i]->description->value)
		{
			echo "Description (Value) = ".$vcalendar->event[$i]->description->value."<br>\n";
		}
		if($vcalendar->event[$i]->description->altrep)
		{
			echo "Description (Alt Rep) = ".$vcalendar->event[$i]->description->altrep."<br>\n";
		}
		if($vcalendar->event[$i]->summary->value)
		{
			echo "Summary = ".$vcalendar->event[$i]->summary->value."<br>\n";
		}
		if(!empty($vcalendar->event[$i]->comment))
		{
			for($j=0;$j<count($vcalendar->event[$i]->comment);$j++)
			{
				echo "Comment = ".$vcalendar->event[$i]->comment[$j]->value."<br>\n";
			}
		}		
		if($vcalendar->event[$i]->location->value)
		{
			echo "Location = ".$vcalendar->event[$i]->location->value."<br>\n";
		}
		echo "Sequence = ".$vcalendar->event[$i]->sequence."<br>\n";
		echo "Date Start : ".$phpgw->common->show_date(mktime($vcalendar->event[$i]->dtstart->hour,$vcalendar->event[$i]->dtstart->min,$vcalendar->event[$i]->dtstart->sec,$vcalendar->event[$i]->dtstart->month,$vcalendar->event[$i]->dtstart->mday,$vcalendar->event[$i]->dtstart->year) - $phpgw->calendar->datatime->tz_offset)."<br>\n";
		if($vcalendar->event[$i]->rrule)
		{
			echo "Recurrence : Frequency = ".$vcalendar->event[$i]->rrule->freq." Count = ".$vcalendar->event[$i]->rrule->count."<br>\n";
		}
		echo "Class = ".$vcalendar->event[$i]->class->value."<br>\n";
		echo "Organizer = ".$vcalendar->event[$i]->organizer->mailto->user.'@'.$vcalendar->event[$i]->organizer->mailto->host."<br>\n";
		if($vcalendar->event[$i]->organizer->dir)
		{
			echo "Organizer Dir     = ".$vcalendar->event[$i]->organizer->dir."<br>\n";
		}
		for($j=0;$j<count($vcalendar->event[$i]->attendee);$j++)
		{
			echo "<br>\nAttendee[$j] CN      = ".$vcalendar->event[$i]->attendee[$j]->cn."<br>\n";
			if($vcalendar->event[$i]->attendee[$j]->dir)
			{
				echo "Attendee[$j] Dir     = ".$vcalendar->event[$i]->attendee[$j]->dir."<br>\n";
			}
			echo "Attendee[$j] Address = ".$vcalendar->event[$i]->attendee[$j]->mailto->user.'@'.$vcalendar->event[0]->attendee[$j]->mailto->host."<br>\n";
			echo "Attendee[$j] Role    = ".$vcal->switch_role($vcalendar->event[$i]->attendee[$j]->role)."<br>\n";
			echo "Attendee[$j] RSVP    = ".$vcal->switch_rsvp($vcalendar->event[$i]->attendee[$j]->rsvp)."<br>\n";
//			echo "Attendee[$j] RSVP    = ".$vcalendar->event[$i]->attendee[$j]->rsvp."<br>\n";
			if($vcalendar->event[$i]->attendee[$j]->delegated_from->user && $vcalendar->event[$i]->attendee[$j]->delegated_from->host)
			{
				echo "Attendee[$j] DELEGATED_FROM = ".$vcalendar->event[$i]->attendee[$j]->delegated_from->user.'@'.$vcalendar->event[$i]->attendee[$j]->delegated_from->host."<br>\n";
			}
		}
	}
	for($i=0;$i<count($vcalendar->todo);$i++)
	{
		echo "<br>\nTODO<br>\n";
		if($vcalendar->todo[$i]->summary->value)
		{
			echo "Summary = ".$vcalendar->todo[$i]->summary->value."<br>\n";
		}
		if($vcalendar->todo[$i]->description->value)
		{
			echo "Description (Value) = ".$vcalendar->todo[$i]->description->value."<br>\n";
		}
		if($vcalendar->todo[$i]->description->altrep)
		{
			echo "Description (Alt Rep) = ".$vcalendar->todo[$i]->description->altrep."<br>\n";
		}
		if($vcalendar->event[$i]->location->value)
		{
			echo "Location = ".$vcalendar->todo[$i]->location->value."<br>\n";
		}
		echo "Sequence = ".$vcalendar->todo[$i]->sequence."<br>\n";	
		echo "Date Start : ".$phpgw->common->show_date(mktime($vcalendar->todo[$i]->dtstart->hour,$vcalendar->todo[$i]->dtstart->min,$vcalendar->todo[$i]->dtstart->sec,$vcalendar->todo[$i]->dtstart->month,$vcalendar->todo[$i]->dtstart->mday,$vcalendar->todo[$i]->dtstart->year) - $phpgw->calendar->datatime->tz_offset)."<br>\n";
		echo "Class = ".$vcalendar->todo[$i]->class->value."<br>\n";
	}

	include(PHPGW_APP_INC.'/../setup/setup.inc.php');

	$vcal->set_var($vcalendar->prodid,'value','-//phpGroupWare//phpGroupWare '.$setup_info['calendar']['version'].' MIMEDIR//'.strtoupper($phpgw_info['user']['preferences']['common']['lang']));
	echo "<br><br><br>\n";
	echo nl2br($vcal->build_vcal($vcalendar));
	$phpgw->common->phpgw_footer();
?>
