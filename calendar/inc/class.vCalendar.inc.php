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

class class_mailto
{
	var $user;
	var $host;
}

class class_address
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

class class_datetime
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

class class_recur
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
class class_x_type
{
	var $name;
	var $value;
}

class vCalendar_item
{
	var $alarm = Array();
	var $attach;
	var $attendee = Array();
	var $calscale;
	var $categories;
	var $comment;
	var $completed;
	var $class;
	var $created;
	var $description;
	var $dtstart;
	var $dtend;
	var $dtstamp;
	var $due;
	var $duration;
	var $freebusy;
	var $geo;
	var $last_modified;
	var $location;
	var $organizer;
	var $percent_complete;
	var $priority;
	var $rrule;
	var $resources;
	var $request_status;
	var $sequence;
	var $status;
	var $summary;
	var $transp;
	var $type;
	var $tzname;
	var $tzoffsetto;
	var $tzoffsetfrom;
	var $tzid;
	var $uid;
	var $x_type = Array();
}

class vCal
{
	var $prodid;
	var $version;
	var $method;
	var $event = Array();
	var $todo = Array();
	var $journal = Array();
	var $freebusy = Array();
	var $timezone = Array();
}	

class vCalendar
{
	var $vcal;
	var $event = Array();
	var $todo = Array();
	var $journal = Array();
	var $freebusy = Array();
	var $timezone = Array();
	var $property;

	/*
	 * Base Functions
	 */

	function vCalendar()
	{
		$this->property = Array(
			'attach'		=> Array(
				'type'		=> 'uri',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'valarm'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				)
			),
			'attendee'		=> Array(
				'type'		=> 'cal-address',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'valarm'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vfreebusy'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				)
			),			
			'calscale'		=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'valarm'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vfreebusy'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'categories'		=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'class'			=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'comment'		=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'valarm'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vfreebusy'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				)
			),
			'completed'		=> Array(
				'type'		=> 'date-time',
				'to_text'	=> False,
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'created'		=> Array(
				'type'		=> 'date-time',
				'to_text'	=> False,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'description'		=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'valarm'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'dtend'			=> Array(
				'type'		=> 'date-time',
				'to_text'	=> False,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vfreebusy'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'dtstamp'		=> Array(
				'type'		=> 'date-time',
				'to_text'	=> False,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vfreebusy'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),			
			'dtstart'		=> Array(
				'type'		=> 'date-time',
				'to_text'	=> False,
				'vevent'	=> Array(
					'state'		=> 'required',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vfreebusy'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtimezone'		=> Array(
					'state'		=> 'required',
					'multiples'	=> True
				)
			),
			'due'			=> Array(
				'type'		=> 'date-time',
				'to_text'	=> False,
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'duration'		=> Array(
				'type'		=> 'duration',
				'to_text'	=> False,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'valarm'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'freebusy'		=> Array(
				'type'		=> 'freebusy',
				'to_text'	=> False,
				'vfreebusy'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				)
			),
			'geo'			=> Array(
				'type'		=> 'float',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'last_modified'		=> Array(
				'type'		=> 'date-time',
				'to_text'	=> False,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtimezone'		=> Array(
					'state'		=> 'required',
					'multiples'	=> False
				)
			),
			'location'		=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'organizer'		=> Array(
				'type'		=> 'cal-address',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vfreebusy'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),			
			'percent_complete'	=> Array(
				'type'		=> 'integer',
				'to_text'	=> False,
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'priority'		=> Array(
				'type'		=> 'integer',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'resources'		=> Array(
				'type'		=> 'text',
				'to_text'	=> False,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'request_status'	=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vfreebusy'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'rrule'			=> Array(
				'type'		=> 'recur',
				'to_text'	=> False,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> True
				),
				'vtimezone'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'sequence'		=> Array(
				'type'		=> 'integer',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'status'		=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'summary'		=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				),
				'valarm'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'transp'		=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'tzid'			=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vtimezone'		=> Array(
					'state'		=> 'required',
					'multiples'	=> False
				)
			),
			'tzname'		=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vtimezone'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'tzoffsetfrom'		=> Array(
				'type'		=> 'utc-offset',
				'to_text'	=> True,
				'vtimezone'		=> Array(
					'state'		=> 'required',
					'multiples'	=> False
				)
			),
			'tzoffsetto'		=> Array(
				'type'		=> 'utc-offset',
				'to_text'	=> True,
				'vtimezone'		=> Array(
					'state'		=> 'required',
					'multiples'	=> False
				)
			),
			'tzurl'			=> Array(
				'type'		=> 'uri',
				'to_text'	=> True,
				'vtimezone'		=> Array(
					'state'		=> 'optional',
					'multiples'	=> False
				)
			),
			'uid'			=> Array(
				'type'		=> 'text',
				'to_text'	=> True,
				'vevent'	=> Array(
					'state'		=> 'required',
					'multiples'	=> False
				),
				'vtodo'		=> Array(
					'state'		=> 'required',
					'multiples'	=> False
				),
				'vjournal'		=> Array(
					'state'		=> 'required',
					'multiples'	=> False
				),
				'vfreebusy'		=> Array(
					'state'		=> 'required',
					'multiples'	=> False
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

	function parse_datetime($value)
	{
		$dtime = new class_datetime;
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

	function parse_host_user($address)
	{
		if(strpos(' '.$address,':'))
		{
			$parts = explode(':',$address);
			$address = $parts[1];
		}
		
		$parts = explode('@',$address);
		if(count($parts) == 2)
		{
			$temp_address = new class_mailto;
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

	function parse_xtype(&$event,$majortype,$value)
	{
		$temp_x_type = new class_x_type;
		$temp_x_type->name = strtoupper(substr($majortype,2));
		$temp_x_type->value = $value;
		$event[] = $temp_x_type;
		unset($temp_x_type);
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

	function parse_address(&$event,$value)
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
					$val = $this->parse_host_user($type[1]);
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

	function build_cal_address($event)
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

	function build_text($event,$to_text)
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
			if($to_text)
			{
				$event->value = $this->to_text($event->value);
			}
			$str .= ':'.$event->value;
		}
		return $str;
	}

	function build_recur($event)
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
		$recur = ':'.implode($str,';');
		return $recur;
	}

	function build_datetime($event)
	{
		return ':'.date('Ymd\THms\Z',mktime($event->hour,$event->min,$event->sec,$event->month,$event->mday,$event->year));
	}

	function build_xtype($x_type)
	{
		return $this->fold('X-'.$x_type->name.':'.$x_type->value);
	}

	function build_card_internals($ical_item,$event)
	{
		reset($this->property);
		while(list($key,$varray) = each($this->property))
		{
			$value  = $key;
			$type   = $varray['type'];
			$to_text = $varray['to_text'];
			$state  = @$varray[$ical_item]['state'];
			$multiples  = @$varray[$ical_item]['multiples'];
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
									if($multiples)
									{
										for($i=0;$i<count($event->$value);$i++)
										{
											$str .= $this->fold(strtoupper($value).$this->build_datetime($event->{$value}[$i]));
										}
									}
									else
									{
										$str .= $this->fold(strtoupper($value).$this->build_datetime($event->$value));
									}
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
								$str .= $this->fold(strtoupper($value).$this->build_text($event->{$value}[$i],$to_text));
							}
						}
						break;
					case 'recur':
						if(!empty($event->$value))
						{
							if($multiples)
							{
								for($i=0;$i<count($event->$value);$i++)
								{
									$str .= $this->fold(strtoupper(str_replace('_','-',$value)).$this->build_recur($event->{$value}[$i]));
								}
							}
							else
							{
								$str .= $this->fold(strtoupper(str_replace('_','-',$value)).$this->build_recur($event->$value));
							}
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
							if($multiples)
							{
								for($i=0;$i<count($event->$value);$i++)
								{
									$str .= $this->fold(strtoupper(str_replace('_','-',$value)).$this->build_text($event->{$value}[$i],$to_text));
								}
							}
							else
							{
								$str .= $this->fold(strtoupper(str_replace('_','-',$value)).$this->build_text($event->$value,$to_text));
							}
						}
						break;
					case 'cal-address':
						if(!empty($event->$value))
						{
							for($j=0;$j<count($event->$value);$j++)
							{
								$temp_output = $this->build_cal_address($event->{$value}[$j]);
								if($temp_output)
								{
									$str .= $this->fold(strtoupper($value).$temp_output);
								}
							}
						}
						break;
				}
			}
		}
		if(!empty($event->x_type))
		{
			for($i=0;$i<count($event->x_type);$i++)
			{
				$str .= $this->build_xtype($event->x_type[$i]);
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
				$majortype = str_replace('-','_',strtolower(substr($vcal_text[$i],0,$min_value)));
				$vcal_text[$i] = chop(substr($vcal_text[$i],$min_value + 1));
				$value = $vcal_text[$i];
			}
			
//			if($majortype == 'begin' || $majortype == 'end')
//			{
//				$mode = 'none';
//			}

			if($mode != 'none' && ($majortype != 'begin' && $majortype != 'end'))
			{
				if(isset($this->property[$majortype]))
				{
					$state = @$this->property[$majortype]["$mode"]['state'];
					$type = @$this->property[$majortype]['type'];
					$multiples = @$this->property[$majortype]["$mode"]['multiples'];
					$do_to_text = @$this->property[$majortype]['to_text'];
				}
				elseif(substr($majortype,0,2) == 'x_')
				{
					$state = 'optional';
					$type = 'xtype';
					$multiples = True;
					$do_to_test = True;
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
				if($majortype == 'begin')
				{
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
						case 'vfreebusy':
							$mode = 'vfreebusy';
							$event = new vCalendar_item;
							$event->type = strtolower($value);
							break;
							break;
						case 'vjournal':
							$mode = 'vjournal';
							$event = new vCalendar_item;
							$event->type = strtolower($value);
							break;
						case 'vtimezone':
							$mode = 'vtimezone';
							$event = new vCalendar_item;
							$event->type = strtolower($value);
							break;
							break;
						case 'vtodo':
							$mode = 'vtodo';
							$event = new vCalendar_item;
							$event->type = strtolower($value);
							break;
						case 'valarm':
							if($mode == 'vevent' || $mode == 'vtodo')
							{
								$tmode = $mode;
								$mode = 'valarm';
								$alarm = new vCalendar_alarm;
							}
							break;
					}
				}
				elseif($majortype == 'end')
				{
					$mode = 'none';
					switch(strtolower($value))
					{
						case 'valarm':
							if($mode == 'valarm')
							{
								$event->alarm[] = $alarm;
								unset($alarm);
								$mode = $tmode;
							}
							break;
						case 'vevent':
							$this->event[] = $event;
							unset($event);
							break;
						case 'vfreebusy':
							$this->freebusy[] = $event;
							unset($event);
							break;
						case 'vjournal':
							$this->journal[] = $event;
							unset($event);
							break;
						case 'vtimezone':
							$this->timezone[] = $event;
							unset($event);
							break;
						case 'vtodo':
							$this->todo[] = $event;
							unset($event);
							break;
						case 'vcalendar':
							$this->vcal = $vcal;
							$this->vcal->event = $this->event;
							$this->vcal->freebusy = $this->freebusy;
							$this->vcal->journal = $this->journal;
							$this->vcal->timezone = $this->timezone;
							$this->vcal->todo = $this->todo;
							break 2;
					}
				}
				elseif($majortype == 'prodid' || $majortype == 'version' || $majortype == 'method')
				{
					$this->parse_text($vcal->$majortype,$this->from_text($value));
					
				}
				else
				{
					if($do_to_text)
					{
						$value = $this->from_text($value);
					}
					switch($type)
					{
						case 'text':
							$text_class = new class_text;
							$this->parse_text($text_class,$value);
							if($multiples)
							{
								$event->{$majortype}[] = $text_class;
							}
							else
							{
								$this->set_var($event,$majortype,$text_class);
							}
							unset($text_class);
							break;
						case 'integer':
							if($multiples)
							{
								$event->{$majortype}[] = intval($value);
							}
							else
							{
								$this->set_var($event,$majortype,intval($value));
							}
							break;
						case 'date-time':
							$date_class = $this->parse_datetime($value);
							if($multiples)
							{
								$event->{$majortype}[] = $date_class;
							}
							else
							{
								$this->set_var($event,$majortype,$date_class);
							}
							unset($date_class);
							break;
						case 'float':
							$event->$majortype = new class_geo;
							$this->parse_geo($event->$majortype,$value);
							break;
						case 'cal-address':
							$address = new class_address;
							$this->parse_address($address,$value);
							if($multiples)
							{
								$event->{$majortype}[] = $address;
							}
							else
							{
								$this->set_var($event,$majortype,$address);
							}
							unset($address);
							break;
						case 'recur':
							$recur = new class_recur;
							$this->parse_recurrence($recur,$value);
							if($multiples)
							{
								$event->{$majortype}[] = $recur;
							}
							else
							{
								$this->set_var($event,$majortype,$recur);
							}
							unset($recur);
							break;
						case 'uri':
							$new_var = new class_text;
							$this->parse_text($new_var,$value);
							if($multiples)
							{
								switch($mode)
								{
									case 'valarm':
										$alarm->attach[] = $new_var;
										break;
									default:
										$event->{$majortype}[] = $new_var;
										break;
								}
							}
							else
							{
								$event->{$majortype} = $new_var;
							}
							unset($new_var);
							break;
						case 'xtype':
							$this->parse_xtype($event->x_type,$majortype,$value);
							break;

					}
				}
			}
			$i++;
		}
		return $this->vcal;
	}

	function build_vcal($vcal)
	{
		$var = Array(
			'timezone',
			'event',
			'todo',
			'journal',
			'freebusy'
		);

		$str = 'BEGIN:VCALENDAR'."\r\n";
		$str .= $this->fold('PRODID'.$this->build_text($vcal->prodid));
		$str .= $this->fold('VERSION'.$this->build_text($vcal->version));
		$str .= $this->fold('METHOD'.$this->build_text($vcal->method));
		while(list($key,$vtype) = each($var))
		{
			if($vcal->$vtype)
			{
				for($i=0;$i<count($vcal->$vtype);$i++)
				{
					$str .= 'BEGIN:V'.strtoupper($vtype)."\r\n";
					$str .= $this->build_card_internals('v'.$vtype,$vcal->{$vtype}[$i]);
					$str .= 'END:V'.strtoupper($vtype)."\r\n";
				}
			}
		}
		$str .= 'END:VCALENDAR'."\r\n";

		return $str;
	}
}
?>
