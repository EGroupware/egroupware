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
define('CHAIR',1);
define('REQ_PARTICIPANT',2);
define('OPT_PARTICIPANT',3);
define('NON_PARTICIPANT',4);

define('INDIVIDUAL',1);
define('GROUP',2);
define('RESOURCE',4);
define('ROOM',8);
define('UNKNOWN',16);

define('PRIVATE',0);
define('PUBLIC',1);
define('CONFIDENTIAL',0);

class mailto
{
	var $user;
	var $host;
}

class attendee
{
	var $cn = 'Unknown';
	var $cutype = INDIVIDUAL;
	var $role = REQ_PARTICIPANT;
	var $rsvp = False;
	var $mailto;
	var $sent_by;
	var $delegated_from;
	var $delegated_to;
	var $member;
	var $partstat;
}

class organizer
{
	var $cn;
	var $cutype = INDIVIDUAL;
	var $delegated_from;
	var $delegated_to;
	var $member;
	var $partstat;
	var $mailto;
	var $sent_by;
}

class vCalendar_time
{
	var $year;
	var $month;
	var $mday;
	var $hour;
	var $min;
	var $sec;
	var $date;
	var $allday = False;
}

class rrule
{
	var $freq;
	var $enddate;
	var $interval;
	var $count;
	var $wkst;
	var $byday;
}

class vCalendar_event
{
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
	var $class = PUBLIC;
	var $rrule;
}

class vCal
{
	var $prodid;
	var $version;
	var $method;
	var $event = Array();
}	

class vCalendar
{
	var $vcal;
	
	function splitdate($value)
	{
		$dtime = new vCalendar_time;
		if(strpos($value,':'))
		{
			$pos = explode(':',$value);
			$value = $pos[1];
		}
		$dtime->year = intval(substr($value,0,4));
		$dtime->month = intval(substr($value,4,2));
		$dtime->mday = intval(substr($value,6,2));
		$dtime->hour = intval(substr($value,9,2));
		$dtime->min = intval(substr($value,11,2));
		$dtime->sec = intval(substr($value,13,2));
		return $dtime;		
	}

	function split_address($address)
	{
		$parts = explode('@',$address);
		if(count($parts) == 2)
		{
			$temp_address = new mailto;
			$temp_address->user = $parts[0];
			$temp_address->host = $parts[1];
			return $temp_address;
		}
		else
		{
			return False;
		}
	}

	function set_var(&$event,$type,$value)
	{
//		if($value != False)
//		{
			$type = strtolower($type);
			$event->$type = $value;
//		}
	}

	function unfold(&$vcal_text,$current_line)
	{
		$next_line = $current_line;

		while(ereg("^[ \t]",substr($vcal_text[$next_line + 1],0,1)))
		{
			$vcal_text[$current_line] = str_replace("\r\n",'',$vcal_text[$current_line]);
			$vcal_text[$current_line] .= substr($vcal_text[$next_line + 1],1);
			$i = $next_line + 1;
			while($i + 1 <= count($vcal_text))
			{
				$vcal_text[$i] = $vcal_text[$i + 1];
				$i++;
			}
			$next_line++;
		}
	}

	function strip_quotes($str)
	{
		if(strpos(' '.$str.' ','"'))
		{
			$str = substr($str,1,strlen($str)-2);
		}
		return $str;
	}

	function strip_param($str)
	{
		$extra_param = explode(':',$str);
		if(count($extra_param) == 1)
		{
			return $str;
		}
		else
		{
			return $extra_param[1];
		}
	}

	function parse_attendee(&$event,$value)
	{
		$param = explode(':',$value);
		for($j=0;$j<count($param);$j++)
		{
			if(strpos($param[$j],'='))
			{
				$type = explode('=',$param[$j]);
				$this->set_var($event,$type[0],$this->strip_quotes($type[1]));
			}
			else
			{
				if(strpos($param[$j],'@'))
				{
					$this->set_var($event,'mailto',$this->split_address($param[$j]));
				}
				else
				{
					switch(strtolower($param[$j]))
					{
						case 'mailto':
							$email_addy = $param[$j + 1];
							$this->set_var($event,$param[$j++],$this->split_address($email_addy));
							break;
						default:
							$var = $this->strip_param($this->strip_quotes($param[$j + 1]));
							$this->set_var($event,$param[$j++],$var);
							break;									
					}
				}
			}
		}
	}

	function parse_recurrence(&$event,$value)
	{
		$param = explode(';',$value);
		for($j=0;$j<count($param);$j++)
		{
			if(strpos($param[$j],'='))
			{
				$type = explode('=',$param[$j]);
				$type[0] = strtolower($type[0]);
				$type[1] = $this->strip_quotes($type[1]);
				$this->set_var($event,$type[0],$type[1]);
			}
		}
	}

	function read($vcal_text)
	{
		$i = 0;
		while(chop($vcal_text[$i]) != '')
		{
//			if(strlen($vcal_text[$i]) > 75)
//			{
//				continue;
//			}

			$this->unfold($vcal_text,$i);

			// Example #1
			//vcal_text[$i] = 'BEGIN:VCALENDAR'
			
			// Example #2
			//vcal_text[$i] = 'METHOD:REQUEST'
			
			// Example #3
			//vcal_text[$i] = 'ATTENDEE;CN="John Doe";ROLE=REQ-PARTICIPANT;RSVP=TRUE:MAILTO:john.doe@somewhere.com'

			// Example #4
			//vcal_text[$i] = 'ORGANIZER:MAILTO:jim.smith@elsewhere.com'

			// Example #5
			//vcal_text[$i] = 'DTSTART:20010302T150000Z'

			// Example #6
			//vcal_text[$i] = 'UID:040000008200E00074C5B7101A82E0080000000040A12C0042A2C0010000000000000000100'
			//vcal_text[$i+1] = ' 000009BDFF7C7650ED5118DD700805FA71291'

			// When unfolded becomes,
			//vcal_text[$i] = 'UID:040000008200E00074C5B7101A82E0080000000040A12C0042A2C0010000000000000000100000009BDFF7C7650ED5118DD700805FA71291'

			$colon = strpos($vcal_text[$i],':');
			if($colon == 0)
			{
				$colon = 65535;
			}

			$semi_colon = strpos($vcal_text[$i],';');
			if($semi_colon == 0)
			{
				$semi_colon = 65535;
			}

			if($colon == 65535 && $semi_colon == 65535)
			{
				continue;
			}
			else
			{
				$min_value = min($colon,$semi_colon);
				$majortype = strtolower(substr($vcal_text[$i],0,$min_value));
				$vcal_text[$i] = chop(substr($vcal_text[$i],$min_value + 1));
				$value = $vcal_text[$i];
			}

			switch($majortype)
			{
				case 'begin':
					switch(strtolower($value))
					{
						case 'vcalendar':
							$vcal = new vCal;
							break;
						case 'vevent':
							$event = new vCalendar_event;
							$event->type = strtolower($value);
							break;
					}
					break;
				case 'prodid':
					$this->set_var($vcal,$majortype,$value);
					break;
				case 'version':
					$this->set_var($vcal,$majortype,$value);
					break;
				case 'method':
					$this->set_var($vcal,$majortype,$value);
					break;
				case 'attendee':
					$attendee = new $majortype;
					$this->parse_attendee($attendee,$value);
					$event->attendee[] = $attendee;
					unset($attendee);
					break;
				case 'organizer':
					$event->$majortype = new $majortype;
					$this->parse_attendee($event->$majortype,$value);
					break;
				case 'end':
					switch(strtolower($value))
					{
						case 'vevent':
							$this->event[] = $event;
							break;
						case 'vcalendar':
							$this->vcal = $vcal;
							$this->vcal->event = $this->event;
							break 2;
					}
					break;
				case 'dtstart':
				case 'dtend':
				case 'dtstamp':
					$this->set_var($event,$majortype,$this->splitdate($value));
					break;
//				case 'class':
//					switch(strtolower($value))
//					{
//						case 'private':
//							$var = PRIVATE;
//							break;
//						case 'public':
//							$var = PUBLIC;
//							break;
//						case 'confidential':
//							$var = CONFIDENTIAL;
//							break;
//					}
//					$this->set_var($event,$majortype,$var);
//					break;
				case 'rrule':
					$event->$majortype = new $majortype;
					$this->parse_recurrence($event->$majortype,$value);
					break;
				default:
					$this->set_var($event,$majortype,$value);
					break;
			}
			$i++;
		}
		return $this->vcal;
	}
}
?>
