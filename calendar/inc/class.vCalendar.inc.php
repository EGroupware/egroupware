<?php
  /**************************************************************************\
  * phpGroupWare - vCalendar Parser                                          *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

define('FOLD_LENGTH',79);

define('VEVENT',1);
define('VTODO',2);

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

define('NEEDS_ACTION',0);
define('ACCEPTED',1);
define('DECLINED',2);
define('TENTATIVE',3);
define('DELEGATED',4);
define('COMPLETED',5);
define('IN_PROCESS',6);

define('PRIVATE',0);
define('PUBLIC',1);
define('CONFIDENTIAL',3);

define('TRANSPARENT',0);
define('OPAQUE',1);

define('OTHER',99);

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
	var $rsvp = 0;
	var $mailto;
	var $sent_by;
	var $delegated_from;
	var $delegated_to;
	var $member;
	var $partstat = NEEDS_ACTION;
}

class organizer
{
	var $cn;
	var $cutype = INDIVIDUAL;
	var $delegated_from;
	var $delegated_to;
	var $member;
	var $partstat = NEEDS_ACTION;
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

class vCalendar_item
{
	var $type;
	var $attendee = Array();
	var $organizer;
	var $dtstart;
	var $dtend;
	var $location;
	var $transp = OPAQUE;
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
	var $event = Array();
	var $todo = Array();
	
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
		if(substr($value,8,1) == 'T')
		{
			$dtime->hour = intval(substr($value,9,2));
			$dtime->min = intval(substr($value,11,2));
			$dtime->sec = intval(substr($value,13,2));
		}
		else
		{
			$dtime->hour = 0;
			$dtime->min = 0;
			$dtime->sec = 0;
		}
		return $dtime;		
	}

	function split_address($address)
	{
		if(strpos(' '.$address,':'))
		{
			$parts = explode(':',$address);
			$address = $parts[1];
		}
		
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
			$type = strtolower(str_replace('-','_',$type));
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

	function fold($str)
	{
//		$temp_str = $str;
//		if(strlen($str) > FOLD_LENGTH)
//		{
			return chunk_split($str,FOLD_LENGTH,"\n");
//		}
//		return $temp_str;
	}

	function strip_quotes($str)
	{
		return str_replace('"','',$str);
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

	function switch_role($var)
	{
		if(gettype($var) == 'string')
		{
			switch($var)
			{
				case 'NONE':
					return NONE;
					break;
				case 'CHAIR':
					return CHAIR;
					break;
				case 'REQ-PARTICIPANT':
					return REQ_PARTICIPANT;
					break;
				case 'OPT-PARTICIPANT':
					return OPT_PARTICIPANT;
					break;
				case 'NON-PARTICIPANT':
					return NON_PARTICIPANT;
					break;
			}
		}
		elseif(gettype($var) == 'integer')
		{
			switch($var)
			{
				case NONE:
					return 'NONE';
					break;
				case CHAIR:
					return 'CHAIR';
					break;
				case REQ_PARTICIPANT:
					return 'REQ-PARTICIPANT';
					break;
				case OPT_PARTICIPANT:
					return 'OPT-PARTICIPANT';
					break;
				case NON_PARTICIPANT:
					return 'NON-PARTICIPANT';
					break;
			}
		}
		else
		{
			return $var;
		}
	}

	function switch_partstat($var)
	{
		if(gettype($var) == 'string')
		{
			switch($var)
			{
				case 'NEEDS-ACTION':
					return NEEDS_ACTION;
					break;
				case 'ACCEPTED':
					return ACCEPTED;
					break;
				case 'DECLINED':
					return DECLINED;
					break;
				case 'TENTATIVE':
					return TENTATIVE;
					break;
				case 'DELEGATED':
					return DELEGATED;
					break;
				case 'COMPLETED':
					return COMPLETED;
					break;
				case 'IN-PROCESS':
					return IN_PROCESS;
					break;
				default:
					return OTHER;
					break;
			}
		}
		elseif(gettype($var) == 'integer')
		{
			switch($var)
			{
				case NEEDS_ACTION:
					return 'NEEDS-ACTION';
					break;
				case ACCEPTED:
					return 'ACCEPTED';
					break;
				case DECLINED:
					return 'DECLINED';
					break;
				case TENTATIVE:
					return 'TENTATIVE';
					break;
				case DELEGATED:
					return 'DELEGATED';
					break;
				case COMPLETED:
					return 'COMPLETED';
					break;
				case IN_PROCESS:
					return 'IN-PROCESS';
					break;
				default:
					return 'X-OTHER';
					break;
			}
		}
		else
		{
			return $var;
		}
	}

	function switch_rsvp($var)
	{
		if(gettype($var) == 'string')
		{
			if($var == 'TRUE')
			{
				return 1;
			}
			else
			{
				return 0;
			}
		}
		elseif(gettype($var) == 'integer')
		{
			if($var == 1)
			{
				return 'TRUE';
			}
			else
			{
				return 'FALSE';
			}
		}
		else
		{
			return $var;
		}
	}

	function switch_class($var)
	{
		if(gettype($var) == 'string')
		{
			switch($var)
			{
				case 'PRIVATE':
					return PRIVATE;
					break;
				case 'PUBLIC':
					return PUBLIC;
					break;
				case 'CONFIDENTIAL':
					return CONFIDENTIAL;
					break;
			}
		}
		elseif(gettype($var) == 'integer')
		{
			switch($var)
			{
				case PRIVATE:
					return 'PRIVATE';
					break;
				case PUBLIC:
					return 'PUBLIC';
					break;
				case CONFIDENTIAL:
					return 'CONFIDENTIAL';
					break;
			}
		}
		else
		{
			return $var;
		}
	}

	function switch_transp($var)
	{
		if(gettype($var) == 'string')
		{
			switch($var)
			{
				case 'TRANSPARENT':
					return TRANSPARENT;
					break;
				case 'OPAQUE':
					return OPAQUE;
					break;
			}
		}
		elseif(gettype($var) == 'integer')
		{
			switch($var)
			{
				case TRANSPARENT:
					return 'TRANSPARENT';
					break;
				case OPAQUE:
					return 'OPAQUE';
					break;
			}
		}
		else
		{
			return $var;
		}
	}

	function parse_attendee(&$event,$value)
	{
		$param = explode(':',$value);
// Here down needs to be faster....		
		$j = 0;
		while($j<count($param))
		{
			if(strpos(' '.$param[$j],'DELEGATED-'))
			{
				$param[$j] .= ':'.$param[$j + 1];
				$i = j + 1;
				while($i + 1 <= count($param))
				{
					$param[$i] = $param[$i + 1];
					$i++;
				}
			}
			$j++;
		}
// Here up needs to be faster....		

//		$j = 0;
//		while(($j - 1)<count($param))
//		{
//			if(strpos(' '.$param[$j],'DELEGATED-'))
//			{
//				$param[$j] .= ':'.$param[$j + 1];
//				unset($param[$j + 1]);
//			}
//			$j++;
//		}

		for($j=0;$j<count($param);$j++)
		{
			$param_sub = explode(';',$param[$j]);
			for($k=0;$k<count($param_sub);$k++)
			{
				if(strpos($param_sub[$k],'='))
				{
					$type = explode('=',$param_sub[$k]);
					$type[0] = strtolower($type[0]);
					$type[1] = $this->strip_quotes($type[1]);
					switch($type[0])
					{
						case 'role':
							$val = $this->switch_role($type[1]);
							break;
						case 'partstat':
							$val = $this->switch_partstat($type[1]);
							break;
						case 'rsvp':
							$val = $this->switch_rsvp($type[1]);
							break;
						case 'delegated-from':
						case 'delegated-to':
							$type[0] = str_replace('-','_',$type[0]);
							$val = $this->split_address($type[1]);
							break;
						default:
							$val = $type[1];
							break;
					}
					$this->set_var($event,$type[0],$val);
				}
				else
				{
					if(strpos($param_sub[$k],'@'))
					{
						$this->set_var($event,'mailto',$this->split_address($param_sub[$k]));
					}
					else
					{
						switch(strtolower($param_sub[$k]))
						{
							case 'mailto':
								$email_addy = $param_sub[$k + 1];
								$this->set_var($event,$param_sub[$k++],$this->split_address($email_addy));
								break;
							default:
								$var = $this->strip_param($this->strip_quotes($param_sub[$k + 1]));
								$this->set_var($event,$param_sub[$k++],$var);
								break;									
						}
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

	function new_vcal()
	{
		return new vCal;
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
							$vcal = $this->new_vcal();
							break;
						case 'vevent':
						case 'vtodo':
							$event = new vCalendar_item;
							$event->type = strtolower($value);
							break;
//						case 'vtodo':
//							$event = new vCalendar_item;
//							$event->type = strtolower($value);
//							break;							
					}
					break;
				case 'prodid':
				case 'version':
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
							unset($event);
							break;
						case 'vtodo':
							$this->todo[] = $event;
							unset($event);
							break;
						case 'vcalendar':
							$this->vcal = $vcal;
							$this->vcal->event = $this->event;
							$this->vcal->todo = $this->todo;
							break 2;
					}
					break;
				case 'dtstart':
				case 'dtend':
				case 'dtstamp':
					$this->set_var($event,$majortype,$this->splitdate($value));
					break;
				case 'class':
					$this->set_var($event,$majortype,$this->switch_class($value));
					break;
				case 'transp':
					$this->set_var($event,$majortype,$this->switch_transp($value));
					break;
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

	function out_organizer_attendee($event)
	{
		$str = '';
		if(!empty($event->cn))
		{
			$str .= ';CN="'.$event->cn.'"';
		}
		if(!empty($event->role))
		{
			$str .= ';ROLE='.$this->switch_role($event->role);
		}
		if(!empty($event->rsvp))
		{
			$str .= ';RSVP='.$this->switch_rsvp($event->rsvp);
		}
		if(!empty($event->delegated_from->user) && !empty($event->delegated_from->host))
		{
			$str .= ';DELEGATED-FROM:MAILTO:"'.$event->delegated_from->user.'@'.$event->delegated_from->host.'"';
		}
		if(!empty($event->delegated_to->user) && !empty($event->delegated_to->host))
		{
			$str .= ';DELEGATED-TO:MAILTO:"'.$event->delegated_to->user.'@'.$event->delegated_to->host.'"';
		}
		if(!empty($event->mailto->user) && !empty($event->mailto->host))
		{
			$str .= ':MAILTO:'.$event->mailto->user.'@'.$event->mailto->host;
		}
		return $str;
	}

	function build_card_internals($event)
	{
		$str .= 'DTSART:'.sprintf("%4d%02d%02dT%02d%02d%02dZ",$event->dtstart->year,$event->dtstart->month,$event->dtstart->mday,$event->dtstart->hour,$event->dtstart->min,$event->dtstart->sec)."\n";
		$str .= 'DTEND:'.sprintf("%4d%02d%02dT%02d%02d%02dZ",$event->dtend->year,$event->dtend->month,$event->dtend->mday,$event->dtend->hour,$event->dtend->min,$event->dtend->sec)."\n";
// Still need to build recurrence portion......
		iF(!empty($event->location))
		{
			$str .= $this->fold('LOCATION:'.$event->location);
		}
		else
		{
			$str .= 'LOCATION:\n'."\n";
		}
		$str .= 'TRANSP:'.$this->switch_transp($event->transp)."\n";
		if(!empty($event->sequence))
		{
			$str .= 'SEQUENCE:'.$event->sequence."\n";
		}
		if(!empty($event->uid))
		{
			$str .= $this->fold('UID:'.$event->uid);
		}
		$str .= 'DTSTAMP:'.gmdate('Ymd\THms\Z')."\n";
		if(!empty($event->description))
		{
			$str .= $this->fold('DESCRIPTION:'.$event->description);
		}
		else
		{
			$str .= 'DESCRIPTION:\n'."\n";
		}
		if(!empty($event->summary))
		{
			$str .= $this->fold('SUMMARY:'.$event->summary);
		}
		else
		{
			$str .= 'SUMMARY:\n'."\n";
		}
		if(!empty($event->priority))
		{
			$str .= 'PRIORITY:'.$event->priority."\n";
		}
		$str .= 'CLASS:'.$this->switch_class($event->class)."\n";

		return $str;
	}

	function build_vcal($vcal)
	{
		$str = 'BEGIN:VCALENDAR'."\n";
		$str .= 'PRODID:'.$vcal->prodid."\n";
		$str .= 'VERSION:'.$vcal->version."\n";
		$str .= 'METHOD:'.$vcal->method."\n";
		if($vcal->event)
		{
			for($i=0;$i<count($vcal->event);$i++)
			{
				$str .= 'BEGIN:VEVENT'."\n";
				for($j=0;$j<count($vcal->event[$i]->attendee);$j++)
				{
					$temp_attendee = $this->out_organizer_attendee($vcal->event[$i]->attendee[$j]);

					if($temp_attendee)
					{
						$str .= 'ATTENDEE'.$temp_attendee."\n";
					}
				}
				if(!empty($vcal->event[$i]->organizer))
				{
					$str .= 'ORGANIZER'.$this->out_organizer_attendee($vcal->event[$i]->organizer)."\n";
				}
				$str .= $this->build_card_internals($vcal->event[$i]);
				$str .= 'END:VEVENT'."\n";
			}
		}
		if($vcal->todo)
		{
			for($i=0;$i<count($vcal->todo);$i++)
			{
				$str .= 'BEGIN:VTODO'."\n";
				$str .= $this->build_card_internals($vcal->todo[$i]);
				$str .= 'END:VTODO'."\n";
			}
		}
		$str .= 'END:VCALENDAR'."\n";

		return $str;
	}
}
?>
