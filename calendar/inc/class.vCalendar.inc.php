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

define('FOLD_LENGTH',75);

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

define('_8BIT',0);
define('_BASE64',1);

define('OTHER',99);

class mailto
{
	var $user;
	var $host;
}

class attendee
{
	var $cn = 'Unknown';
	var $dir;
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

class class_text
{
	var $cid;
	var $fmttype;
	var $encoding;
	var $altrep;
	var $value;
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
	var $attach;
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
		return chunk_split($str,FOLD_LENGTH,"\r\n");
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

	function split_param(&$return_value,$str,$check_equal)
	{
		if($check_equal)
		{
			$str_len = strlen($str);
			$i = 0;
			$found = False;
			while($i < $str_len)
			{
				$char = substr($str,$i,1);
				if(ereg("^[\=\:]",$char))
				{
					$found = True;
					$ret_str = str_replace('=3D','=',str_replace('%20',' ',substr($str,0,$i)));
					$return_value[] = $ret_str;
					$ret_array = $this->explode_param(substr($str,$i + 1),'"',False);
					while(list($key,$value) = each($ret_array))
					{
						$return_value[] = str_replace('=3D','=',str_replace('%20',' ',$value));
					}
					$i = $str_len;
				}
				$i++;
			}
			if(!$found)
			{
				$return_value[] = str_replace('=3D','=',str_replace('%20',' ',$str));
			}
		}
		else
		{
			$return_value[] = rawurldecode($str);
		}
	}

	function explode_param($str,$enclosure,$check_equal)
	{
		$enclosure_found = 0;
		$start = 0;
		$return_value = Array();
		$str_len = strlen($str);
		for($i=0;$i<$str_len;$i++)
		{
			$char = substr($str,$i,1);
			if($char == $enclosure)
			{
				$enclosure_found = (~ $enclosure_found);
			}
			elseif(ereg("^[\;\:]",$char))
			{
				if(! $enclosure_found)
				{
					$this->split_param($return_value,substr($str,$start,($i - $start)),$check_equal);
					$start = $i + 1;
				}
			}
		}
		if(! $enclosure_found)
		{
			$this->split_param($return_value,substr($str,$start,($i - $start)),$check_equal);
		}
		return $return_value;
	}

	function parse_text(&$event,$value)
	{
		$return_value = $this->explode_param($value,'"',True);
		if(count($return_value) > 0)
		{
			for($i=0;$i<count($return_value);$i=$i + 2)
			{
				$type[0] = $return_value[$i];
				$type[1] = $this->strip_quotes($return_value[$i+1]);
				switch(strtolower($type[0]))
				{
					case 'altrep':
					case 'fmttype':
					case 'cid':
						$this->set_var($event,strtolower($type[0]),$type[1]);
						break;
					case 'encoding':
						$this->set_var($event,strtolower($type[0]),$this->switch_encoding($type[1]));
						break;
					case 'value':
						break;
					default:
						$this->set_var($event,'value',$type[0]);
						break;
				}
			}				
		}
		elseif($value <> '\n')
		{
			$this->set_var($event,'value',$value);
		}
	}

	function switch_encoding($var)
	{
		if(gettype($var) == 'string')
		{
			switch($var)
			{
				case '8BIT':
					return _8BIT;
					break;
				case 'BASE64':
					return _BASE64;
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
				case _8BIT:
					return '8BIT';
					break;
				case _BASE64:
					return 'BASE64';
					break;
				case OTHER:
					return 'OTHER';
					break;
			}
		}
		else
		{
			return $var;
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
		$param = $this->explode_param($value,'"',True);

		for($j=0;$j<count($param);$j += 2)
		{
			$type[0] = strtolower($param[$j]);
			$type[1] = $this->strip_quotes($param[$j+1]);
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
				case 'mailto':
					$type[0] = str_replace('-','_',$type[0]);
					$val = $this->split_address($type[1]);
					break;
				case 'dir':
					$val = $type[1];
					break;
				default:
					$val = $type[1];
					break;
			}
			$this->set_var($event,$type[0],$val);
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

	function from_text($str)
	{
		$str = str_replace("\\,",",",$str);
		$str = str_replace("\\;",";",$str);
		$str = str_replace("\\N","\n",$str);
		$str = str_replace("\\n","\n",$str);
		$str = str_replace("\\\\","\\",$str);
		return $str;
	}

	function to_text($str)
	{
		$str = str_replace("\\","\\\\",$str);
		$str = str_replace(",","\\,",$str);
		$str = str_replace(";","\\;",$str);
		$str = str_replace("\n","\\n",$str);
		return $str;
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

			$vcal_text[$i] = str_replace("\r\n",'',$vcal_text[$i]);

			// Example #1
			//vcal_text[$i] = 'BEGIN:VCALENDAR'
			
			// Example #2
			//vcal_text[$i] = 'METHOD:REQUEST'
			
			// Example #3
			//vcal_text[$i] = 'ATTENDEE;CN="John Doe";ROLE=REQ-PARTICIPANT;RSVP=TRUE:MAILTO:john.doe@somewhere.com'

			// Example #4
			//vcal_text[$i] = 'ORGANIZER;DIR="ldap://host.com:6666/o=eDABC%20Industries,c=3DUS??(cn=3DBJim%20Dolittle)":MAILTO:John.Doe@somewhere.com'

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
					}
					break;
				case 'prodid':
				case 'version':
				case 'method':
					$this->set_var($vcal,$majortype,$value);
					break;
				case 'description':
					$event->$majortype = new class_text;
					$this->parse_text($event->$majortype,$this->from_text($value));
					break;
				case 'location':
					$this->set_var($event,$majortype,$this->from_text($value));
					break;
				case 'attach':
					$attach = new class_text;
					$this->parse_text($attach,$value);
					$event->attach[] = $attach;
					unset($attach);
					break;
				case 'attendee':
					$attendee = new attendee;
					$this->parse_attendee($attendee,$value);
					$event->attendee[] = $attendee;
					unset($attendee);
					break;
				case 'organizer':
					$event->$majortype = new attendee;
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
		if(!empty($event->cn) && $event->cn <> 'Unknown')
		{
			$str .= ';CN="'.$event->cn.'"';
		}
		if(!empty($event->dir))
		{
			$str .= ';DIR="'.str_replace('=','=3D',str_replace(' ','%20',$event->dir)).'"';
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
			$str .= ';DELEGATED-FROM="MAILTO:'.$event->delegated_from->user.'@'.$event->delegated_from->host.'"';
		}
		if(!empty($event->delegated_to->user) && !empty($event->delegated_to->host))
		{
			$str .= ';DELEGATED-TO="MAILTO:'.$event->delegated_to->user.'@'.$event->delegated_to->host.'"';
		}
		if(!empty($event->mailto->user) && !empty($event->mailto->host))
		{
			$str .= ':MAILTO:'.$event->mailto->user.'@'.$event->mailto->host;
		}
		return $str;
	}

	function build_text($event)
	{
		$str = '';
		if(!empty($event->cid))
		{
			$str .= ';CID="'.$event->cid.'"';
		}
		if(!empty($event->altrep))
		{
			$str .= ';ALTREP="'.$event->altrep.'"';
		}
		if(!empty($event->fmttype))
		{
			$str .= ';FMTTYPE='.$event->fmttype;
		}
		if(!empty($event->encoding))
		{
			$str .= ';ENCODING='.$this->switch_encoding($event->encoding);
		}		
		if(!empty($event->value))
		{
			$str .= ':'.$this->to_text($event->value);
		}
		else
		{
			$str .= ':\n';
		}

		return $str;
	}

	function build_card_internals($event)
	{
		$str = 'DTSART:'.sprintf("%4d%02d%02dT%02d%02d%02dZ",$event->dtstart->year,$event->dtstart->month,$event->dtstart->mday,$event->dtstart->hour,$event->dtstart->min,$event->dtstart->sec)."\r\n";
		$str .= 'DTEND:'.sprintf("%4d%02d%02dT%02d%02d%02dZ",$event->dtend->year,$event->dtend->month,$event->dtend->mday,$event->dtend->hour,$event->dtend->min,$event->dtend->sec)."\r\n";
// Still need to build recurrence portion......
		iF(!empty($event->location))
		{
			$str .= $this->fold('LOCATION:'.$this->to_text($event->location));
		}
		else
		{
			$str .= 'LOCATION:\n'."\r\n";
		}
		$str .= 'TRANSP:'.$this->switch_transp($event->transp)."\r\n";
		if(!empty($event->sequence))
		{
			$str .= 'SEQUENCE:'.$event->sequence."\r\n";
		}
		if(!empty($event->uid))
		{
			$str .= $this->fold('UID:'.$event->uid);
		}
		$str .= 'DTSTAMP:'.gmdate('Ymd\THms\Z')."\r\n";
		if(!empty($event->description))
		{
			$str .= $this->fold('DESCRIPTION'.$this->build_text($event->description));
		}
		else
		{
			$str .= 'DESCRIPTION:\n'."\r\n";
		}
		if(!empty($event->summary))
		{
			$str .= $this->fold('SUMMARY:'.$event->summary);
		}
		else
		{
			$str .= 'SUMMARY:\n'."\r\n";
		}
		if(!empty($event->priority))
		{
			$str .= 'PRIORITY:'.$event->priority."\r\n";
		}
		$str .= 'CLASS:'.$this->switch_class($event->class)."\r\n";

		if(!empty($event->attach))
		{
			for($i=0;$i<count($event->attach);$i++)
			{
				$str .= $this->fold('ATTACH'.$this->build_text($event->atttach[$i]));
			}
		}

		return $str;
	}

	function build_vcal($vcal)
	{
		$str = 'BEGIN:VCALENDAR'."\r\n";
		$str .= 'PRODID:'.$vcal->prodid."\r\n";
		$str .= 'VERSION:'.$vcal->version."\r\n";
		$str .= 'METHOD:'.$vcal->method."\r\n";
		if($vcal->event)
		{
			for($i=0;$i<count($vcal->event);$i++)
			{
				$str .= 'BEGIN:VEVENT'."\r\n";
				for($j=0;$j<count($vcal->event[$i]->attendee);$j++)
				{
					$temp_attendee = $this->out_organizer_attendee($vcal->event[$i]->attendee[$j]);

					if($temp_attendee)
					{
						$str .= $this->fold('ATTENDEE'.$temp_attendee);
					}
				}
				if(!empty($vcal->event[$i]->organizer))
				{
					$str .= $this->fold('ORGANIZER'.$this->out_organizer_attendee($vcal->event[$i]->organizer));
				}
				$str .= $this->build_card_internals($vcal->event[$i]);
				$str .= 'END:VEVENT'."\r\n";
			}
		}
		if($vcal->todo)
		{
			for($i=0;$i<count($vcal->todo);$i++)
			{
				$str .= 'BEGIN:VTODO'."\r\n";
				$str .= $this->build_card_internals($vcal->todo[$i]);
				$str .= 'END:VTODO'."\r\n";
			}
		}
		$str .= 'END:VCALENDAR'."\r\n";

		return $str;
	}
}
?>
