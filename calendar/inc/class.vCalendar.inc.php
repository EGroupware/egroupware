<?php
  /**************************************************************************\
  * phpGroupWare - vCalendar                                                 *
  * http://www.phpgroupware.org                                              *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

define('NONE',0);
define('OPT-PARTICIPANT',1);
define('REQ-PARTICIPANT',2);

class mailto
{
	var $user;
	var $host;
}

class attendee
{
	var $cn = 'Unknown';
	var $role = 0;
	var $rsvp = False;
	var $mailto;
	var $sent_by;
}

class organizer
{
	var $mailto;
	var $sent_by;
}

class vCalendar_time {
	var $year;
	var $month;
	var $mday;
	var $hour;
	var $min;
	var $sec;
	var $date;
	var $allday = False;
}

class vCalendar_event
{
	var $prodid;
	var $version;
	var $method;
	var $type;
	var $attendee = Array();
	var $organizer;
	var $dtstart;
	var $dtend;
	var $location;
	var $transp;
	var $sequence;
	var $uid;
	var $dtstamp;
	var $description;
	var $summary;
	var $priority;
	var $class;
}

class vCalendar
{
	var $event;
	
	function splitdate($value)
	{
		$dtime = new vCalendar_time;
		$dtime->year = intval(substr($value,0,4));
		$dtime->month = intval(substr($value,5,2));
		$dtime->mday = intval(substr($value,7,2));
		$dtime->hour = intval(substr($value,10,2));
		$dtime->min = intval(substr($value,12,2));
		$dtime->sec = intval(substr($value,14,2));
		return $dtime;		
	}

	function read($vcal_text)
	{

		$c_vcal_text = count($vcal_text);
		for($i=0;$i<$c_val_text;$i++)
		{
			if($vcal_text[$i] == 'END:VCALENDAR')
			{
				continue;
			}
			$element = explode(';',$vcal_text[$i]);
			$c_element = count($element);
			for($j=0;$j<$c_element;$j++)
			{
				$temp_array = explode(':',$element[$j]);
				$c_temp_array = count($temp_array);
				if($c_temp_array > 1)
				{
					if(strpos($temp_array[0],'=') == 0)
					{
						$type = $temp_array[0];
						if(isset($temp_array[1]))
						{
							$value = $temp_array[1];
						}
					}
					else
					{
						$parameter = $temp_array[0];
						$type = $temp_array[1];
						$value = $temp_array[2];
					}
				}
				else
				{
					$type = $element[$j];
				}
				switch(strtolower($type))
				{
					case 'begin':
						$event = new vCalendar_event;
						if($value != 'VCALENDAR')
						{
							$event->type = $value;
						}
						break;
/*
					case 'attendee':
						$attendee = new attendee;
						$j++;
						$att_data = explode(';',substr($vcal_text[$i],9,strlen($vcal_text[$i])));
						$c_att_data = count($att_data);
						for($k=0;$k<$c_att_data;$k++)
						{
							if(strpos($att_data[$k],':'))
							{
							}
							elseif(strpos($att_data[$k],'='))
							{
								$att_att = explode('=',$att_data[$k])
							}
						}
						$event->attendee[] = $attendee;
						unset($attendee);
						break;
					case 'organizer':
						break;
*/
					case 'end':
						switch(strtolower($value))
						{
							case 'vevent':
								$this->event[] = $event;
								break;
							case 'vcalendar':
								break;
						}
						break;
					default:
						if(strtolower(substr($type,0,2)) == 'DT')
						{
							$this->$type = new vCalendar_time;
							$this->$type = $this->splitdate($value);
						}
						else
						{
							$this->$type = $value;
						}
						break;
				}
			}			
		}
	}
}
?>
