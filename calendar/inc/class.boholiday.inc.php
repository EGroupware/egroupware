<?php
  /**************************************************************************\
  * phpGroupWare - Holiday                                                   *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	class boholiday
	{
		var $public_functions = Array(
			'read_entries'	=> True,
			'read_entry'	=> True,
			'add_entry'	=> True,
			'update_entry'	=> True
		);

		var $debug = False;

		var $so;

		var $owner;

		var $year;
		
		var $locales = Array();
		var $holidays;
		var $cached_holidays;
		
		function boholiday($year,$owner=0)
		{
			global $phpgw_info;

			$this->so = CreateObject('calendar.soholiday');

			$this->year = $year;

			if($owner == 0)
			{
				$this->owner = $phpgw_info['user']['account_id'];
			}
			else
			{
				$this->owner = $owner;
			}			
		
			if(@$phpgw_info['user']['preferences']['common']['country'])
			{
				$this->locales[] = $phpgw_info['user']['preferences']['common']['country'];
			}
			elseif(@$phpgw_info['user']['preferences']['calendar']['locale'])
			{
				$this->locales[] = $phpgw_info['user']['preferences']['calendar']['locale'];
			}
			
			if($this->owner != $phpgw_info['user']['account_id'])
			{
				$owner_pref = CreateObject('phpgwapi.preferences',$owner);
				$owner_prefs = $owner_pref->read_repository();
				if(@$owner_prefs['common']['country'])
				{
					$this->locales[] = $owner_prefs['common']['country'];
				}
				elseif(@$owner_prefs['calendar']['locale'])
				{
					$this->locales[] = $owner_prefs['calendar']['locale'];
				}
				unset($owner_pref);
			}

			@reset($this->locales);
			if($phpgw_info['server']['auto_load_holidays'] == True)
			{
				while(list($key,$value) = each($this->locales))
				{
					$this->auto_load_holidays($value);
				}
			}
		}

		/* Begin Calendar functions */
		function auto_load_holidays($locale)
		{
			if($this->so->count_of_holidays($locale) == 0)
			{
				global $phpgw_info, $HTTP_HOST, $SERVER_PORT;
		
				@set_time_limit(0);

				/* get the file that contains the calendar events for your locale */
				/* "http://www.phpgroupware.org/cal/holidays.US";                 */
				$network = CreateObject('phpgwapi.network');
				if(isset($phpgw_info['server']['holidays_url_path']) && $phpgw_info['server']['holidays_url_path'] != 'localhost')
				{
					$load_from = $phpgw_info['server']['holidays_url_path'];
				}
				else
				{
					$pos = strpos(' '.$phpgw_info['server']['webserver_url'],$HTTP_HOST);
					if($pos == 0)
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
//				echo 'Loading from: '.$load_from.'/holidays.'.strtoupper($locale)."<br>\n";
				$lines = $network->gethttpsocketfile($load_from.'/holidays.'.strtoupper($locale));
				if (!$lines)
				{
					return false;
				}
				$c_lines = count($lines);
				for($i=0;$i<$c_lines;$i++)
				{
//					echo 'Line #'.$i.' : '.$lines[$i]."<br>\n";
					$holiday = explode("\t",$lines[$i]);
					if(count($holiday) == 7)
					{
						$holiday['locale'] = $holiday[0];
						$holiday['name'] = addslashes($holiday[1]);
						$holiday['mday'] = intval($holiday[2]);
						$holiday['month_num'] = intval($holiday[3]);
						$holiday['occurence'] = intval($holiday[4]);
						$holiday['dow'] = intval($holiday[5]);
						$holiday['observance_rule'] = intval($holiday[6]);
						$holiday['hol_id'] = 0;
						$this->so->save_holiday($holiday);
					}
				}
			}
		}

		function sort_holidays_by_date($holidays)
		{
			$c_holidays = count($holidays);
			for($outer_loop=0;$outer_loop<($c_holidays - 1);$outer_loop++)
			{
				for($inner_loop=$outer_loop;$inner_loop<$c_holidays;$inner_loop++)
				{
					if($holidays[$outer_loop]['date'] > $holidays[$inner_loop]['date'])
					{
						$temp = $holidays[$inner_loop];
						$holidays[$inner_loop] = $holidays[$outer_loop];
						$holidays[$outer_loop] = $temp;
					}
				}
			}
			return $holidays;
		}

		function set_holidays_to_date($holidays)
		{
			$new_holidays = Array();
			for($i=0;$i<count($holidays);$i++)
			{
//	echo "Setting Holidays Date : ".date('Ymd',$holidays[$i]['date'])."<br>\n";
				$new_holidays[date('Ymd',$holidays[$i]['date'])][] = $holidays[$i];
			}
			return $new_holidays;
		}

		function read_holiday()
		{
			if(isset($this->cached_holidays))
			{
				return $this->cached_holidays;
			}

			$holidays = $this->so->read_holidays($this->locales);

			if(count($holidays) == 0)
			{
				return $holidays;
			}			

			global $phpgw_info;

			$temp_locale = $phpgw_info['user']['preferences']['common']['country'];
			$datetime = CreateObject('phpgwapi.datetime');
			for($i=0;$i<count($holidays);$i++)
			{
				$c = $i;
				$phpgw_info['user']['preferences']['common']['country'] = $holidays[$i]['locale'];
				$holidaycalc = CreateObject('calendar.holidaycalc');
				$holidays[$i]['date'] = $holidaycalc->calculate_date($holidays[$i], $holidays, $this->year, $datetime, $c);
				unset($holidaycalc);
				if($c != $i)
				{
					$i = $c;
				}
			}
			unset($datetime);
			$this->holidays = $this->sort_holidays_by_date($holidays);
			$this->cached_holidays = $this->set_holidays_to_date($this->holidays);
			$phpgw_info['user']['preferences']['common']['country'] = $temp_locale;
			return $this->cached_holidays;
		}
		/* End Calendar functions */
	}
?>
