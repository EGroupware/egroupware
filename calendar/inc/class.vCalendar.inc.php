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

class class_geo
{
	var $lat;
	var $lon;
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
	var $language;
	var $value;
}

class vCalendar_item
{
	var $type;
	var $attendee = Array();
	var $organizer;
	var $dtstart;
	var $dtend;
	var $dtstamp;
	var $due;
	var $created;
	var $last_modified;
	var $completed;
	var $duration;
	var $freebusy;
	var $location;
	var $categories;
	var $transp;
	var $sequence;
	var $percent_complete;
	var $attach;
	var $calscale;
	var $tzid;
	var $uid;
	var $description;
	var $comment;
	var $summary;
	var $status;
	var $priority;
	var $class;
	var $rrule;
	var $resources;
	var $request_status;
}

class vCal
{
	var $prodid;
	var $version;
	var $method;
	var $event = Array();
	var $todo = Array();
}	

class vCalendar
{
	var $vcal;
	var $event = Array();
	var $todo = Array();
	var $property;

	/*
	 * Base Functions
	 */

	function vCalendar()
	{
		$this->property = Array(
			'dtstart'		=> Array(
				'type'  => 'date-time',
				'mangle'=> False,
				'state' => Array(
					'vevent'   => 'required',
					'vtodo'    => 'optional',
					'vfreebusy'=> 'optional',
					'vtimezone'=> 'required'
				)
			),
			'dtend'		=> Array(
				'type'  => 'date-time',
				'mangle'=> False,
				'state' => Array(
					'vevent'   => 'optional',
					'vfreebusy'=> 'optional'
				)
			),
			'dtstamp'		=> Array(
				'type'  => 'date-time',
				'mangle'=> False,
				'state' => Array(
					'vevent'   => 'required',
					'vtodo'    => 'required',
					'vjournal' => 'required',
					'vfreebusy'=> 'required'
				)
			),			
			'due'		=> Array(
				'type'  => 'date-time',
				'mangle'=> False,
				'state' => Array(
					'vtodo'    => 'optional'
				)
			),
			'completed'		=> Array(
				'type'  => 'date-time',
				'mangle'=> False,
				'state' => Array(
					'vtodo'    => 'optional'
				)
			),
			'created'		=> Array(
				'type'  => 'date-time',
				'mangle'=> False,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional'
				)
			),
			'last_modified'	=> Array(
				'type'  => 'date-time',
				'mangle'=> False,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional',
					'vtimezone'=> 'optional'
				)
			),
			'duration'		=> Array(
				'type'  => 'duration',
				'mangle'=> False,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vfreebusy'=> 'optional',
					'valarm'   => 'optional'
				)
			),
			'freebusy'		=> Array(
				'type'  => 'freebusy',
				'mangle'=> False,
				'state' => Array(
					'vfreebusy'=> 'optional'
				)
			),
			'attendee'		=> Array(
				'type'  => 'cal-address',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional'
				)
			),			
			'organizer'		=> Array(
				'type'  => 'cal-address',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional',
					'vfreebusy'=> 'optional'
				)
			),			
			'rrule'		=> Array(
				'type'  => 'recur',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional',
					'vtimezone'=> 'optional'
				)
			),
			'comment'		=> Array(
				'type'  => 'uri',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional',
					'vtimezone'=> 'optional',
					'vfreebusy'=> 'optional'
				)
			),
			'summary'		=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional',
					'valarm'   => 'optional'
				)
			),
			'resources'		=> Array(
				'type'  => 'text',
				'mangle'=> False,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional'
				)
			),
			'description'		=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional',
					'valarm'   => 'optional'
				)
			),
			'location'		=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional'
				)
			),
			'priority'		=> Array(
				'type'  => 'integer',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional'
				)
			),
			'calscale'		=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional',
					'valarm'   => 'optional'
				)
			),
			'transp'		=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional'
				)
			),
			'tzid'		=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vtimezone' => 'required'
				)
			),
			'geo'		=> Array(
				'type'  => 'float',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional'
				)
			),
			'uid'		=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional',
					'vfreebusy'=> 'optional'
				)
			),
			'percent_complete'	=> Array(
				'type'  => 'integer',
				'mangle'=> True,
				'state' => Array(
					'vtodo'    => 'optional'
				)
			),
			'sequence'		=> Array(
				'type'  => 'integer',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional'
				)
			),
			'status'		=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional'
				)
			),
			'class'		=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional'
				)
			),
			'categories'		=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional'
				)
			),
			'request_status'	=> Array(
				'type'  => 'text',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional',
					'vfreebusy'=> 'optional'
				)
			),
			'attach'		=> Array(
				'type'  => 'uri',
				'mangle'=> True,
				'state' => Array(
					'vevent'   => 'optional',
					'vtodo'    => 'optional',
					'vjournal' => 'optional',
					'valarm'   => 'optional'
				)
			)
		);
	}

	function set_var(&$event,$type,$value)
	{
		$type = strtolower(str_replace('-','_',$type));
		$event->$type = $value;
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
//			$next_line++;
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

	/*
	 * Parse Functions
	 */

	function parse_date($value)
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

	function parse_address($address)
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

	function parse_geo(&$event,$value)
	{
		$return_value = $this->explode_param($value,'"',True);
		if(count($return_value) == 2)
		{
			$event->lat = $return_value[0];
			$event->lon = $return_value[1];
		}
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
					case 'language':
						$this->set_var($event,strtolower($type[0]),$type[1]);
						break;
					case 'encoding':
						$this->set_var($event,strtolower($type[0]),$this->switch_encoding($type[1]));
						break;
					case 'value':
						break;
					default:
						if($type[0] <> "\\n")
						{
							$this->set_var($event,'value',$type[0]);
						}
						break;
				}
			}				
		}
		elseif($value <> "\\n")
		{
			$this->set_var($event,'value',$value);
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
					$val = $this->parse_address($type[1]);
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
		$return_value = $this->explode_param($value,'"',True);
		if(count($return_value) > 0)
		{
			for($i=0;$i<count($return_value);$i=$i + 2)
			{
				$type[0] = $return_value[$i];
				$type[1] = $this->strip_quotes($return_value[$i+1]);
				$this->set_var($event,$type[0],$type[1]);
			}
		}
	}

	/*
	 * Build-Card Functions
	 */

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

	function build_text($event,$mangle)
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
			if($mangle)
			{
				$event->value = $this->to_text($event->value);
			}
			$str .= ':'.$event->value;
		}
//		else
//		{
//			$str .= ':\n';
//		}

		return $str;
	}

	function build_rrule($event)
	{
		$var = Array(
			'freq',
			'count',
			'wkst',
			'byday'
		);
		for($i=0;$i<count($var);$i++)
		{
			iF(!empty($event->{$var[$i]}))
			{
				$str[] = strtoupper($var[$i]).'='.$event->{$var[$i]};
			}
		}
		return implode($str,';');
	}

	function build_time($event)
	{
		return ':'.date('Ymd\THms\Z',mktime($event->year,$event->month,$event->mday,$event->hour,$event->min,$event->sec));
	}

	function build_card_internals($ical_item,$event)
	{
		reset($this->property);
		while(list($key,$varray) = each($this->property))
		{
			$value  = $key;
			$type   = $varray['type'];
			$mangle = $varray['mangle'];
			$state  = $varray['state'][$ical_item];
			if(@$state == 'optional' || @$state == 'required')
			{
				switch($type)
				{
					case 'date-time':
						switch($value)
						{
							case 'last_modified':
								$str .= $this->fold(strtoupper(str_replace('_','-',$value)).':'.gmdate('Ymd\THms\Z'));
								break;
							default:
								if(!empty($event->$value))
								{
									$str .= $this->fold(strtoupper($value).$this->build_time($event->$value));
								}
								elseif($value == 'dtstamp' || $value == 'created')
								{
									$str .= $this->fold(strtoupper($value).':'.gmdate('Ymd\THms\Z'));
								}								
								break;
						}
						break;
					case 'uri':
						if(!empty($event->$value))
						{
							for($i=0;$i<count($event->$value);$i++)
							{
								$str .= $this->fold(strtoupper($value).$this->build_text($event->{$value}[$i],$mangle));
							}
						}
						break;
					case 'recur':
						if(!empty($event->$value))
						{
							$str .= $this->fold(strtoupper($value).':'.$this->build_rrule($event->$value));
						}
						break;
					case 'integer':
						if(!empty($event->$value))
						{
							$str .= $this->fold(strtoupper(str_replace('_','-',$value)).':'.$event->$value);
						}
						elseif($value == 'sequence' || $value == 'percent_complete')
						{
							$str .= $this->fold(strtoupper(str_replace('_','-',$value)).':0');
						}
						break;
					case 'float':
						if(!empty($event->$value))
						{
							$str .= $this->fold(strtoupper(str_replace('_','-',$value)).':'.$event->$value->lat.';'.$event->$value->lon);
						}
						break;
					case 'text':
						if(empty($event->$value) && $state == 'required')
						{
							return '';
						}
						if(!empty($event->$value))
						{
							$str .= $this->fold(strtoupper(str_replace('_','-',$value)).$this->build_text($event->$value,$mangle));
						}
						break;
				}
			}
		}
		return $str;
	}

	/*
	 * Switching Functions
	 */

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

	/*
	 * The brunt of the class
	 */	

	function read($vcal_text)
	{
		$i = 0;
		$mode = 'none';
		while(chop($vcal_text[$i]) != '')
		{
//			if(strlen($vcal_text[$i]) > 75)
//			{
//				continue;
//			}

			$this->unfold($vcal_text,$i);

			$vcal_text[$i] = str_replace("\r\n",'',$vcal_text[$i]);

//	echo "TEXT : ".$vcal_text[$i]."<br>\n";

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

			$mtype = str_replace('-','_',$majortype);
			
			if($mtype == 'begin' || $mtype == 'end')
			{
				$mode = 'none';
			}

			if($mode != 'none')
			{
				if(isset($this->property[$mtype]))
				{
					$state = @$this->property[$mtype]['state']["$mode"];
				}
				else
				{
					$state = '';
				}
			}
			else
			{
				$state = 'required';
			}

			if($state == 'optional' || $state == 'required')
			{
				switch($mtype)
				{
					case 'begin':
						switch(strtolower($value))
						{
							case 'vcalendar':
								$vcal = $this->new_vcal();
								break;
							case 'vevent':
								$mode = 'vevent';
								$event = new vCalendar_item;
								$event->type = strtolower($value);
								break;
							case 'vtodo':
								$mode = 'vtodo';
								$event = new vCalendar_item;
								$event->type = strtolower($value);
								break;
						}
						break;
					case 'prodid':
					case 'version':
					case 'method':
						$this->parse_text($vcal->$majortype,$this->from_text($value));
						break;
					case 'geo':
						$event->$majortype = new class_geo;
						$this->parse_geo($event->$majortype,$value);
						break;
					case 'description':
					case 'location':
					case 'summary':
					case 'calscale':
					case 'tzid':
					case 'transp':
					case 'uid':
					case 'class':
					case 'status':
					case 'categories':
					case 'resources':
					case 'request_status':
						$event->$majortype = new class_text;
						$this->parse_text($event->$majortype,$this->from_text($value));
						break;
					case 'attach':
						$attach = new class_text;
						$this->parse_text($attach,$this->from_text($value));
						$event->attach[] = $attach;
						unset($attach);
						break;
					case 'comment':
						$comment = new class_text;
						$this->parse_text($comment,$this->from_text($value));
						$event->comment[] = $comment;
						unset($comment);
						break;
					case 'percent_complete':
						$this->set_var($event,str_replace('-','_',$majortype),$value);
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
					case 'due':
					case 'completed':
					case 'duration':
					case 'freebusy':
					case 'dtstart':
					case 'dtend':
					case 'dtstamp':
					case 'created':
					case 'last_modified':
						$this->set_var($event,$majortype,$this->parse_date($value));
						break;
					case 'rrule':
						$event->$majortype = new $majortype;
						$this->parse_recurrence($event->$majortype,$value);
						break;
					case 'end':
						$mode = 'none';
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
					default:
						$this->set_var($event,$majortype,$value);
						break;
				}
			}
			$i++;
		}
		return $this->vcal;
	}

	function build_vcal($vcal)
	{
		$str = 'BEGIN:VCALENDAR'."\r\n";
		$str .= $this->fold('PRODID'.$this->build_text($vcal->prodid));
		$str .= $this->fold('VERSION'.$this->build_text($vcal->version));
		$str .= $this->fold('METHOD'.$this->build_text($vcal->method));
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
				$str .= $this->build_card_internals('vevent',$vcal->event[$i]);
				$str .= 'END:VEVENT'."\r\n";
			}
		}
		if($vcal->todo)
		{
			for($i=0;$i<count($vcal->todo);$i++)
			{
				$str .= 'BEGIN:VTODO'."\r\n";
				$str .= $this->build_card_internals('vtodo',$vcal->todo[$i]);
				$str .= 'END:VTODO'."\r\n";
			}
		}
		$str .= 'END:VCALENDAR'."\r\n";

		return $str;
	}
}
?>
