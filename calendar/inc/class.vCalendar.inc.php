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

class vCalendar
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

	function read($vcal_text)
	{
		$role = Array(
			'NONE'					=>	0,
			'OPT-PARTICIPANT'		=> 1,
			'REQ-PARTICIPANT'		=>	2
		);
		
		while(strtoupper($text) != 'END:VCALENDAR')
		{
			$element = strtolower($this->find_element($text,Array(':',';')));
			switch($element)
			{
				case 'begin':
					$value = strtolower($this->find_element(substr($text,7,strlen($text)),Array('')));
					if($value != 'VCALENDAR')
					{
						$this->type = $value;
					}
					break;
				case 'prodid':
					$this->prodid = strtolower($this->find_element(substr($text,7,strlen($text)),Array('')));
					break;
				case 'version':
					$this->version = strtolower($this->find_element(substr($text,8,strlen($text)),Array('')));
					break;
				case 'attendee':
					$attendee = new attendee;
					$i = 9;
					while($i < strlen($text))
					{
						$value = strtolower($this->find_element(substr($text,$i,strlen($text)),Array('=')));
						switch($value)
						{
							case 'cn':
								$i += 4;
								$data = strtolower($this->find_element(substr($text,$i,strlen($text)),Array(';')));
								if(substr($data,1,1) == '"')
								{
									$attendee->$value=substr($data,1,strlen($data) - 2);
								}
								else
								{
									$attendee->$value=$data;
								}
								$i += strlen($data) + 1;
								break;
							case 'role':
								$data = strtolower($this->find_element(substr($text,$i,strlen($text)),Array(';')));
								$attendee->$value=$role[$data];
								$i += strlen($data) + 1;
								break;
							case 'rsvp':
								$data = strtolower($this->find_element(substr($text,$i,strlen($text)),Array(':')));
								$attendee->$value=$data;
								$i += strlen($data) + 1;
								$value = strtolower($this->find_element(substr($text,$i,strlen($text)),Array(':')));
								$i += strlen($value) + 1;
								$data = strtolower($this->find_element(substr($text,$i,strlen($text)),Array('')));
								$attendee->$value=$data;
								$i += strlen($data) + 1;
								break;
							case 'sent-by':
								$data = strtolower($this->find_element(substr($text,$i,strlen($text)),Array(':',';')));
								$organizer = each('@',$data);
								$attendee->sent_by->user = $organizer[0];
								$attendee->sent_by->host = $organizer[1];
								$i += strlen($data) + 1;
								break;
						}
					}
					$this->attendee[] = $attendee;
					unset($attendee);
					break;
				case 'organizer':
					$i = 10;
					$value = strtolower($this->find_element(substr($text,$i,strlen($text)),Array(':')));
					$i += strlen($value) + 1;
					switch($value)
					{
						case 'mailto':
							$data = strtolower($this->find_element(substr($text,$i,strlen($text)),Array(':')));
							$organizer = each('@',$data);
							$this->organizer->mailto->user = $organizer[0];
							$this->organizer->mailto->host = $organizer[1];
							break;
						case 'sent-by':
							$data = strtolower($this->find_element(substr($text,$i,strlen($text)),Array(':',';')));
							$organizer = each('@',$data);
							$this->organizer->sent_by->user = $organizer[0];
							$this->organizer->sent_by->host = $organizer[1];
							$i += strlen($data) + 1;
							break;
						otherwise:
							$data = $value;
							if(strpos(' '.$data.' ','@') > 0)
							{
								$organizer = each('@',$data);
								$this->organizer->mailto->user = $organizer[0];
								$this->organizer->mailto->host = $organizer[1];
							}
							break;
					}
					break;
				case 'dtstart':
					break;
			}			
		}
	}

	function find_element($text,$stop_chars)
	{
		$element = '';
		$i=0;
		$char = '';
		while(!ereg('['.explode($stop_chars,'').']',$char) && ($i<strlen($text)))
		{
			$char = substr($text,$i++,1);
			$element .= $char;
		}
		return $element;
	}
}
?>


