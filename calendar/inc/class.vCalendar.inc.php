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
define('REQ-PARTICIPANT',2);
define('OPT-PARTICIPANT',3);
define('NON-PARTICIPANT',4);

define('INDIVIDUAL',1);
define('GROUP',2);
define('RESOURCE',4);
define('ROOM',8);
define('UNKNOWN',16);

class mailto
{
	var $user;
	var $host;
}

class attendee
{
	var $cn = 'Unknown';
	var $cutype = 1;
	var $role = 2;
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
	var $cutype = 1;
	var $delegated_from;
	var $delegated_to;
	var $member;
	var $partstat;
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

	function set_var_subtype(&$event,$majortype,$subtype,$value)
	{
		if($value != False)
		{
			$event->${strtolower($majortype)}->${strtolower($subtype)} = $value;
		}
	}

	function unfold(&$vcal_text,$current_line)
	{
		$next_line = $current_line;

		while(ereg("^[ \t]",substr($vcal_text[$next_line + 1],0,1)))
		{
			$vcal_text[$current_line] = str_replace("\r\n",'',$vcal_text[$current_line]);
			$vcal_text[$current_line] .= substr($vcal_text[$next_line + 1],1);
			$next_line++;
		}
		if($next_line != $current_line)
		{
			for($i=$next_line;$i>$current_line;$i--)
			{
				unset($vcal_text[$i]);
			}
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

	function read($vcal_text)
	{
		for($i=0;$i<count($vcal_text);$i++)
		{
			if(strlen($vcal_text[$i]) > 75)
			{
				continue;
			}

			unfold($vcal_text,$i);

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

			$colon = pos($vcal_text[$i],':');
			if($colon == 0)
			{
				$colon = 65535;
			}

			$semi_colon = pos($vcal_text[$i],';');
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
				$majortype = substr($vcal_text[$i],0,$min_value - 1);
				$vcal_text[$i] = substr($vcal_text[$i],$min_value + 1);
				$value = $vcal_text[$i];
			}

			switch(strtolower($majortype))
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
*/
				case 'organizer':
					$event->${strtolower($majortype)} = new ${strtolower($majortype)};
					$param = explode(':',$value);
					for($j=0;$j<count($param);$j++)
					{
						if(strpos($param[$j],'='))
						{
							$subtype = explode('=',$param[$j]);
							$this->set_var_subtype($event,$majortype,$subtype[0],$this->strip_quotes($subtype[1]));
						}
						else
						{
							if(strpos($param[$j],'@'))
							{
								$this->set_var_subtype($event,$majortype,'mailto',$this->split_address($param[$j]));
							}
							else
							{
								switch(strtolower($param[$j]))
								{
									case 'mailto':
										$email_addy = $param[$j + 1];
										$this->set_var_subtype($event,$majortype,$param[$j++],$this->split_address($email_addy));
										break;
									default:
										$var = $this->strip_param($this->strip_quotes($param[$j + 1]));
										$this->set_var_subtype($event,$majortype,$param[$j++],$var);
										break;									
								}
							}
						}
					}
					break;
				case 'end':
					switch(strtolower($value))
					{
						case 'vevent':
							$this->event[] = $event;
							unset($event);
							break;
						case 'vcalendar':
							break 2;
					}
					break;
				case 'dtstart':
				case 'dtend':
				case 'dtstamp':
					$event->${strtolower($majortype)} = new vCalendar_time;
					$event->${strtolower($majortype)} = $this->splitdate($value);
					break;
				default:
					$event->${strtolower($majortype)} = $value;
					break;
			}
		}
	}
}
?>
