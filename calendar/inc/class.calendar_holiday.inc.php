<?php
  /**************************************************************************\
  * phpGroupWare - Calendar Holidays                                         *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

class calendar_holiday
{
	var $db;
	var $year;
	var $tz_offset;
	var $holidays;
	var $users;
//	var $cal;

	function calendar_holiday($owner='')
	{
		global $phpgw, $phpgw_info;
		
		$this->db = $phpgw->db;
		$this->users['user'] = $phpgw_info['user']['preferences']['calendar']['locale'];
		$owner_id = get_account_id($owner);
		if($owner_id != $phpgw_info['user']['account_id'])
		{
			$owner_pref = CreateObject('phpgwapi.preferences',$owner_id);
			$owner_prefs = $owner_pref->read_repository();
			$this->users['owner'] = $owner_prefs['calendar']['locale'];			
		}
		if($phpgw_info['server']['auto_load_holidays'] == True)
		{
			while(list($key,$value) = each($this->users))
			{
				$this->is_network_load_needed($value);
			}
		}
	}

	function is_network_load_needed($locale)
	{
		$sql = "SELECT count(*) FROM phpgw_cal_holidays WHERE locale='".$locale."'";
		$this->db->query($sql,__LINE__,__FILE__);
		$this->db->next_record();
		$rows = $this->db->f(0);
		if($rows==0)
		{
			$this->load_from_network($locale);
		}
	}

	function load_from_network($locale)
	{
		global $phpgw_info, $HTTP_HOST, $SERVER_PORT;
		
		@set_time_limit(0);

		// get the file that contains the calendar events for your locale
		// "http://www.phpgroupware.org/headlines.rdf";
		$network = CreateObject('phpgwapi.network');
		if(isset($phpgw_info['server']['holidays_url_path']) && $phpgw_info['server']['holidays_url_path'] != 'localhost')
		{
			$load_from = $phpgw_info['server']['holidays_url_path'];
		}
		else
		{
			$pos = strpos(' '.$phpgw_info['server']['webserver_url'],$HTTP_HOST);
			if($pos === False)
			{
				switch($SERVER_PORT)
				{
					case 80:
						$http_protocol = 'http://';
						break;
					case 443:
						$http_protocol = 'https://';
						break;
				}
				$server_host = $http_protocol.$HTTP_HOST.$phpgw_info['server']['webserver_url'];
			}
			else
			{
				$server_host = $phpgw_info['server']['webserver_url'];
			}
			$load_from = $server_host.'/calendar/setup';
		}
//		echo 'Loading from: '.$load_from.'/holidays.'.strtoupper($locale)."<br>\n";
		$lines = $network->gethttpsocketfile($load_from.'/holidays.'.strtoupper($locale));
		if (!$lines) return false;
		$c_lines = count($lines) - 4;
		for($i=10;$i=$c_lines;$i++)
		{
//			echo 'Line #'.$i.' : '.$lines[$i]."<br>\n";
			$holiday = explode("\t",$lines[$i]);
			$loc = $holiday[0];
			$name = addslashes($holiday[1]);
			$day = intval($holiday[2]);
			$month = intval($holiday[3]);
			$occurence = intval($holiday[4]);
			$dow = intval($holiday[5]);
//			echo "Inserting LOCALE='".$loc."' NAME='".$name."' DATE='".$date."'<br>\n";
			$sql = "INSERT INTO phpgw_cal_holidays(locale,name,mday,month_num,occurence,dow) VALUES('$loc','$name',$day,$month,$occurence,$dow)";
			$this->db->query($sql,__LINE__,__FILE__);
		}
	}

	function calculate_date($holiday,&$i)
	{
		global $phpgw;
		
		if($holiday['mday'] == 0 && $holiday['dow'] != 0 && $holiday['occurence'] != 0)
		{
			if($holiday['occurence'] != 99)
			{
				$dow = $phpgw->calendar->day_of_week($this->year,$holiday['month'],1);
				$day = (7 * $holiday['occurence'] - 6 + ($holiday['dow'] - $dow) % 7);
			}
			else
			{
				$ld = $phpgw->calendar->days_in_month($holiday['month'],$this->year);
				$dow = $phpgw->calendar->day_of_week($this->year,$holiday['month'],$ld);
				$day = $ld - ($dow - $holiday['dow']) % 7 ;
			}
		}
		else
		{
			$day = $holiday['day'];
			$dow = $phpgw->calendar->day_of_week($this->year,$holiday['month'],$day);
			// This now calulates Observed holidays and creates a new entry for them.
			if($dow == 0)
			{
				$i++;
				$this->holidays[$i]['locale'] = $holiday['locale'].' (Observed)';
				$this->holidays[$i]['name'] = $holiday['name'];
				$this->holidays[$i]['day'] = $holiday['day'] + 1;
				$this->holidays[$i]['month'] = $holiday['month'];
				$this->holidays[$i]['occurence'] = $holiday['occurence'];
				$this->holidays[$i]['dow'] = $holiday['dow'];
				$this->holidays[$i]['date'] = mktime(0,0,0,$holiday['month'],$day+1,$this->year) - $this->tz_offset;
//		echo 'Calculating for year('.$this->year.') month('.$this->holidays[$i]['month'].') dow('.$this->holidays[$i]['dow'].') occurence('.$this->holidays[$i]['occurence'].') datetime('.$this->holidays[$i]['date'].') DATE('.date('Y.m.d H:i:s',$this->holidays[$i]['date']).')<br>'."\n";
			}
			elseif($dow == 6)
			{
				$i++;
				$this->holidays[$i]['locale'] = $holiday['locale'].' (Observed)';
				$this->holidays[$i]['name'] = $holiday['name'];
				$this->holidays[$i]['day'] = $holiday['day'] - 1;
				$this->holidays[$i]['month'] = $holiday['month'];
				$this->holidays[$i]['occurence'] = $holiday['occurence'];
				$this->holidays[$i]['dow'] = $holiday['dow'];
				$this->holidays[$i]['date'] = mktime(0,0,0,$holiday['month'],$day-1,$this->year) - $this->tz_offset;
//		echo 'Calculating for year('.$this->year.') month('.$this->holidays[$i]['month'].') dow('.$this->holidays[$i]['dow'].') occurence('.$this->holidays[$i]['occurence'].') datetime('.$this->holidays[$i]['date'].') DATE('.date('Y.m.d H:i:s',$this->holidays[$i]['date']).')<br>'."\n";
			}
		}
		$datetime = mktime(0,0,0,$holiday['month'],$day,$this->year) - $this->tz_offset;
//		echo 'Calculating for year('.$this->year.') month('.$holiday['month'].') dow('.$holiday['dow'].') occurence('.$holiday['occurence'].') datetime('.$datetime.') DATE('.date('Y.m.d H:i:s',$datetime).')<br>'."\n";
		return $datetime;
	}

	function read_holiday()
	{
		global $phpgw;

		$this->year = intval($phpgw->calendar->tempyear);
		$this->tz_offset = intval($phpgw->calendar->tz_offset);
		
		$sql = $this->build_holiday_query();
		$this->holidays = Null;
		$this->db->query($sql,__LINE__,__FILE__);

		$i = 0;
		while($this->db->next_record())
		{
			$this->holidays[$i]['locale'] = $this->db->f('locale');
			$this->holidays[$i]['name'] = $phpgw->strip_html($this->db->f('name'));
			$this->holidays[$i]['day'] = intval($this->db->f('mday'));
			$this->holidays[$i]['month'] = intval($this->db->f('month_num'));
			$this->holidays[$i]['occurence'] = intval($this->db->f('occurence'));
			$this->holidays[$i]['dow'] = intval($this->db->f('dow'));
			if(count($find_locale) == 2 && $find_locale[0] != $find_locale[1])
			{
				if($this->holidays[$i]['locale'] == $find_locale[1])
				{
					$this->holidays[$i]['owner'] = 'user';
				}
				else
				{
					$this->holidays[$i]['owner'] = 'owner';
				}
			}
			else
			{
				$this->holidays[$i]['owner'] = 'user';		
			}
			$c = $i;
			$this->holidays[$i]['date'] = $this->calculate_date($this->holidays[$i],$c);
			if($c != $i)
			{
				$i = $c;
			}
			$i++;
		}
		$this->holidays = $this->sort_by_date($this->holidays);
		return $this->holidays;
	}

	function build_holiday_query()
	{
		$sql = 'SELECT * FROM phpgw_cal_holidays WHERE locale in (';
		$find_it = '';
		reset($this->users);
		while(list($key,$value) = each($this->users))
		{
			if($find_it)
			{
				$find_it .= ',';
			}
			$find_it .= "'".$value."'";
		}
		$sql .= $find_it.')';

		return $sql;
	}

	function sort_by_date($holidays)
	{
		$c_holidays = count($holidays);
		for($outer_loop=0;$outer_loop<($c_holidays - 1);$outer_loop++)
		{
			$outer_date = $holidays[$outer_loop]['date'];
			for($inner_loop=$outer_loop;$inner_loop<$c_holidays;$inner_loop++)
			{
				$inner_date = $holidays[$inner_loop]['date'];
				if($outer_date > $inner_date)
				{
					$temp = $holidays[$inner_loop];
					$holidays[$inner_loop] = $holidays[$outer_loop];
					$holidays[$outer_loop] = $temp;
				}
			}
		}
		return $holidays;
	}
	

	function find_date($date)
	{
		global $phpgw;
		
		$c_holidays = count($this->holidays);
		for($i=0;$i<$c_holidays;$i++)
		{
			if($this->holidays[$i]['date'] > $date)
			{
				$i = $c_holidays + 1;
			}
			elseif($this->holidays[$i]['date'] == $date)
			{
				$return_value[] = $i;
			}
		}
//		echo 'Searching for '.$phpgw->common->show_date($date).'  Found = '.count($return_value)."<br>\n";
		if(isset($return_value))
		{
			return $return_value;
		}
		else
		{
			return False;
		}
	}

	function get_name($id)
	{
		return $this->holidays[$id]['name'];
	}
}
?>
