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
	var $holidays;
	var $users;

	function calendar_holiday($owner='')
	{
		global $phpgw, $phpgw_info;
		
		$this->db = $phpgw->db;
//		$phpgw_info['user']['preferences']['calendar']['locale'] = 'US';
		$this->users['user'] = $phpgw_info['user']['preferences']['calendar']['locale'];
		$owner_id = get_account_id($owner);
		if($owner_id != $phpgw_info['user']['account_id'])
		{
			$owner_pref = CreateObject('phpgwapi.preferences',$owner_id);
			$owner_prefs = $owner_pref->read_repository();
//			$owner_prefs['calendar']['locale'] = 'UK';
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
		global $phpgw_info;
		
		@set_time_limit(0);

		// get the file that contains the calendar events for your locale
		// "http://www.phpgroupware.org/headlines.rdf";
		$network = CreateObject('phpgwapi.network');
		if(isset($phpgw_info['server']['holidays_url_path']))
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
		$lines = $network->gethttpsocketfile($load_from.'/holidays.'.$locale);
//		if (!$lines) return false;
		
	}

	function read_holiday()
	{
		global $phpgw;
		
		$sql = $this->build_holiday_query();
		$this->holidays = Null;
		$this->db->query($sql,__LINE__,__FILE__);

		$i = -1;
		while($this->db->next_record())
		{
			$i++;
			$this->holidays[$i]['locale'] = $this->db->f('locale');
			$this->holidays[$i]['name'] = $this->db->f('name');
			$this->holidays[$i]['date'] = $this->db->f('date_time');
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
		}
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
}
?>
