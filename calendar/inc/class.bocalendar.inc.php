<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	class bocalendar
	{
		var $public_functions = Array(
			'read_entries'	=> True,
			'read_entry'	=> True,
			'add_entry'	=> True,
			'update_entry'	=> True
		);

		var $debug = False;

		var $so;
		var $cached_events;
		var $repeating_events;
		var $datetime;
		var $day;
		var $month;
		var $year;

		var $owner;
		var $holiday_color;
		var $printer_friendly = False;

		var $holiday_locales;
		var $holidays;
		var $cached_holidays;
		
		var $filter;
		var $cat_id;
		var $users_timeformat;
		
		var $use_session = False;

		function bocalendar($session=False)
		{
			global $phpgw, $phpgw_info, $date, $year, $month, $day;

			$phpgw->nextmatchs = CreateObject('phpgwapi.nextmatchs');

			$this->so = CreateObject('calendar.socalendar');
			$this->datetime = $this->so->datetime;

			$this->filter = ' '.$phpgw_info['user']['preferences']['calendar']['defaultfilter'].' ';

			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}

			if(isset($this->so->owner))
			{
				$this->owner = $this->so->owner;
			}
			else
			{
				$this->so->owner = $this->owner;
			}
				
			if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
			{
				$this->users_timeformat = 'h:i a';
			}
			else
			{
				$this->users_timeformat = 'H:i';
			}

			$this->holiday_color = (substr($phpgw_info['theme']['bg07'],0,1)=='#'?'':'#').$phpgw_info['theme']['bg07'];

			global $filter, $fcat_id, $owner, $month, $day, $year, $friendly;

			if($friendly == 1)
			{
				$this->printer_friendly = True;
			}

			if(isset($filter))   { $this->filter = ' '.chop($filter).' '; }
			if(isset($fcat_id))  { $this->cat_id = $fcat_id; }

			if(isset($date))
			{
				$this->year = intval(substr($date,0,4));
				$this->month = intval(substr($date,4,2));
				$this->day = intval(substr($date,6,2));
			}
			else
			{
				if(isset($year))
				{
					$this->year = $year;
				}
				if(isset($month))
				{
					$this->month = $month;
				}
				if(isset($day))
				{
					$this->day = $day;
				}
			}
		}

		function save_sessiondata($data)
		{
			if ($this->use_session)
			{
				global $phpgw;
				if($this->debug) { echo '<br>Save:'; _debug_array($data); }
				$phpgw->session->appsession('session_data','calendar',$data);
			}
		}

		function read_sessiondata()
		{
			global $phpgw;

			$data = $phpgw->session->appsession('session_data','calendar');
			if($this->debug) { echo '<br>Read:'; _debug_array($data); }

			$this->filter = $data['filter'];
			$this->cat_id = $data['cat_id'];
			$this->owner  = $data['owner'];
			$this->year   = $data['year'];
			$this->month  = $data['month'];
			$this->day    = $data['day'];

		}

		function strip_html($dirty = '')
		{

			if ($dirty == '')
			{
				$dirty = array();
				return $dirty;
			}
			else
			{
				global $phpgw;
				for($i=0;$i<count($dirty);$i++)
				{
					while (list($name,$value) = each($dirty[$i]))
					{
						$cleaned[$i][$name] = $phpgw->strip_html($dirty[$i][$name]);
					}
				}
				return $cleaned;
			}
		}

		function read_entry($id)
		{
			return $this->so->read_entry($id);
		}

		/* Private functions */

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

		function build_holiday_query()
		{
			@reset($this->holiday_locales);
			if(count(@$this->holiday_locales) == 0)
			{
				return False;
			}
			$sql = 'SELECT * FROM phpgw_cal_holidays WHERE locale in (';
			$find_it = '';
			while(list($key,$value) = each($this->holiday_locales))
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
			global $phpgw, $phpgw_info;

			if(isset($this->cached_holidays))
			{
				return $this->cached_holidays;
			}

			if(@$phpgw_info['user']['preferences']['common']['country'])
			{
				$this->holiday_locales[] = $phpgw_info['user']['preferences']['common']['country'];
			}
			elseif(@$phpgw_info['user']['preferences']['calendar']['locale'])
			{
				$this->holiday_locales[] = $phpgw_info['user']['preferences']['calendar']['locale'];
			}
			
			if($this->owner != $phpgw_info['user']['account_id'])
			{
				$owner_pref = CreateObject('phpgwapi.preferences',$owner);
				$owner_prefs = $owner_pref->read_repository();
				if(@$owner_prefs['common']['country'])
				{
					$this->holiday_locales[] = $owner_prefs['common']['country'];
				}
				elseif(@$owner_prefs['calendar']['locale'])
				{
					$this->holiday_locales[] = $owner_prefs['calendar']['locale'];
				}
				unset($owner_pref);
			}

			@reset($this->holiday_locales);
			if($phpgw_info['server']['auto_load_holidays'] == True)
			{
				while(list($key,$value) = each($this->holiday_locales))
				{
					$this->auto_load_holidays($value);
				}
			}

			$sql = $this->build_holiday_query();
			if($sql == False)
			{
				return array();
			}
			$holidays = $this->so->read_holidays($sql);
			$temp_locale = $phpgw_info['user']['preferences']['common']['country'];
			for($i=0;$i<count($holidays);$i++)
			{
				$c = $i;
				$phpgw_info['user']['preferences']['common']['country'] = $holidays[$i]['locale'];
				$holidaycalc = CreateObject('calendar.holidaycalc');
				$holidays[$i]['date'] = $holidaycalc->calculate_date($holidays[$i], $holidays, $this->year, $this->datetime, $c);
				unset($holidaycalc);
				if($c != $i)
				{
					$i = $c;
				}
			}
			$this->holidays = $this->sort_holidays_by_date($holidays);
			$this->cached_holidays = $this->set_holidays_to_date($this->holidays);
			$phpgw_info['user']['preferences']['common']['country'] = $temp_locale;
			return $this->holidays;
		}
		/* End Calendar functions */


		function check_perms($needed,$user=0)
		{
			global $grants;
			if($user == 0)
			{
				return ($this->so->rights & $needed);
			}
			else
			{
				return ($grants[$user] & $needed);
			}
		}

		function display_status($user_status)
		{
			global $phpgw_info;
		
			if(isset($phpgw_info['user']['preferences']['calendar']['display_status']) && $phpgw_info['user']['preferences']['calendar']['display_status'] == True)
			{
				return ' ('.$user_status.')';
			}
			else
			{
				return '';
			}
		}

		function get_long_status($status_short)
		{
			switch ($status_short)
			{
				case 'A':
					$status = lang('Accepted');
					break;
				case 'R':
					$status = lang('Rejected');
					break;
				case 'T':
					$status = lang('Tentative');
					break;
				case 'U':
					$status = lang('No Response');
					break;
			}
			return $status;
		}

		function is_private($event,$owner,$field='')
		{
			global $phpgw, $phpgw_info, $grants;

			if($owner == 0) { $owner = $phpgw_info['user']['account_id']; }
			if ($owner == $phpgw_info['user']['account_id'] || ($grants[$owner] & PHPGW_ACL_PRIVATE) || ($event->public == 1))
			{
				$is_private  = False;
			}
			elseif($event->public == 0)
			{
				$is_private = True;
			}
			elseif($event->public == 2)
			{
				$is_private = True;
				$groups = $phpgw->accounts->memberships($owner);
				while ($group = each($groups))
				{
					if (strpos(' '.implode($event->groups,',').' ',$group[1]['account_id']))
					{
						$is_private = False;
					}
				}
			}
			else
			{
				$is_private  = False;
			}

			return $is_private;
		}

		function get_short_field($event,$is_private=True,$field='')
		{
			if ($is_private)
			{
				$str = 'private';
			}
			elseif (strlen($event->$field) > 19)
			{
				$str = substr($event->$field, 0 , 19) . '...';
			}
			else
			{
				$str = $event->$field;
			}

			return $str;
		}

		function sort_event($event,$date)
		{
			$inserted = False;
			if($this->cached_events[$date])
			{
				for($i=0;$i<count($this->cached_events[$date]);$i++)
				{
					$events = $this->cached_events[$date][$i];
					$events_id = $events->id;
					$event_id = $event->id;
					if($events->id == $event->id)
					{
						$inserted = True;
						break;
					}
					$year = substr($date,0,4);
					$month = substr($date,4,2);
					$day = substr($date,6,2);
					if(date('Hi',mktime($event->start->hour,$event->start->min,$event->start->sec,$month,$day,$year)) < date('Hi',mktime($events->start->hour,$events->start->min,$events->start->sec,$month,$day,$year)))
					{
						for($j=count($this->cached_events[$date]);$j>=$i;$j--)
						{
							$this->cached_events[$date][$j + 1] = $this->cached_events[$date][$j];
						}
						$inserted = True;
						$this->cached_events[$date][$j] = $event;
						break;
					}
				}
			}
			if(!$inserted)
			{
				$this->cached_events[$date][] = $event;
			}					
		}

		function check_repeating_events($datetime)
		{
			global $phpgw, $phpgw_info;

			@reset($this->repeating_events);
			$search_date_full = date('Ymd',$datetime);
			$search_date_year = date('Y',$datetime);
			$search_date_month = date('m',$datetime);
			$search_date_day = date('d',$datetime);
			$search_date_dow = date('w',$datetime);
			$search_beg_day = mktime(0,0,0,$search_date_month,$search_date_day,$search_date_year);
			$repeated = $this->repeating_events;
			$r_events = count($repeated);
			for ($i=0;$i<$r_events;$i++)
			{
				$rep_events = $this->repeating_events[$i];
				$id = $rep_events->id;
				$event_beg_day = mktime(0,0,0,$rep_events->start->month,$rep_events->start->mday,$rep_events->start->year);
				if($rep_events->recur_enddate->month != 0 && $rep_events->recur_enddate->mday != 0 && $rep_events->recur_enddate->year != 0)
				{
					$event_recur_time = mktime($rep_events->recur_enddate->hour,$rep_events->recur_enddate->min,$rep_events->recur_enddate->sec,$rep_events->recur_enddate->month,$rep_events->recur_enddate->mday,$rep_events->recur_enddate->year);
				}
				else
				{
					$event_recur_time = mktime(0,0,0,1,1,2030);
				}
				$end_recur_date = date('Ymd',$event_recur_time);
				$full_event_date = date('Ymd',$event_beg_day);
			
				// only repeat after the beginning, and if there is an rpt_end before the end date
				if (($search_date_full > $end_recur_date) || ($search_date_full < $full_event_date))
				{
					continue;
				}

				if ($search_date_full == $full_event_date)
				{
					$this->sort_event($rep_events,$search_date_full);
					continue;
				}
				else
				{				
					$freq = $rep_events->recur_interval;
					$type = $rep_events->recur_type;
					switch($type)
					{
						case MCAL_RECUR_DAILY:
							if (floor(($search_beg_day - $event_beg_day)/86400) % $freq)
							{
								continue;
							}
							else
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							break;
						case MCAL_RECUR_WEEKLY:
							if (floor(($search_beg_day - $event_beg_day)/604800) % $freq)
							{
								continue;
							}
							$check = 0;
							switch($search_date_dow)
							{
								case 0:
									$check = MCAL_M_SUNDAY;
									break;
								case 1:
									$check = MCAL_M_MONDAY;
									break;
								case 2:
									$check = MCAL_M_TUESDAY;
									break;
								case 3:
									$check = MCAL_M_WEDNESDAY;
									break;
								case 4:
									$check = MCAL_M_THURSDAY;
									break;
								case 5:
									$check = MCAL_M_FRIDAY;
									break;
								case 6:
									$check = MCAL_M_SATURDAY;
									break;
							}
							if ($rep_events->recur_data & $check)
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							break;
						case MCAL_RECUR_MONTHLY_WDAY:
							if ((($search_date_year - $rep_events->start->year) * 12 + $search_date_month - $rep_events->start->month) % $freq)
							{
								continue;
							}
	  
							if (($this->datetime->day_of_week($rep_events->start->year,$rep_events->start->month,$rep_events->start->mday) == $this->datetime->day_of_week($search_date_year,$search_date_month,$search_date_day)) &&
								(ceil($rep_events->start->mday/7) == ceil($search_date_day/7)))
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							break;
						case MCAL_RECUR_MONTHLY_MDAY:
							if ((($search_date_year - $rep_events->start->year) * 12 + $search_date_month - $rep_events->start->month) % $freq)
							{
								continue;
							}
							if ($search_date_day == $rep_events->start->mday)
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							break;
						case MCAL_RECUR_YEARLY:
							if (($search_date_year - $rep_events->start->year) % $freq)
							{
								continue;
							}
							if (date('dm',$datetime) == date('dm',$event_beg_day))
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							break;
					}
				}
			}	// end for loop
		}	// end function

		function store_to_cache($syear,$smonth,$sday)
		{
			global $phpgw, $phpgw_info;

			$edate = mktime(23,59,59,$smonth + 1,$sday + 1,$syear);
			$eyear = date('Y',$edate);
			$emonth = date('m',$edate);
			$eday = date('d',$edate);
			$cached_event_ids = $this->so->list_events($syear,$smonth,$sday,$eyear,$emonth,$eday);
			$cached_event_ids_repeating = $this->so->list_repeated_events($syear,$smonth,$sday,$eyear,$emonth,$eday);

			$c_cached_ids = count($cached_event_ids);
			$c_cached_ids_repeating = count($cached_event_ids_repeating);

			$this->cached_events = Array();
			
			if($c_cached_ids == 0 && $c_cached_ids_repeating == 0)
			{
				return;
			}

			$this->cached_events = Array();
			if($c_cached_ids)
			{
				for($i=0;$i<$c_cached_ids;$i++)
				{
					$event = $this->so->read_entry($cached_event_ids[$i]);
					$starttime = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $this->datetime->tz_offset;
					$endtime = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $this->datetime->tz_offset;
					$this->cached_events[date('Ymd',$starttime)][] = $event;
					if($this->cached_events[date('Ymd',$endtime)][count($this->cached_events[date('Ymd',$endtime)]) - 1] != $event)
					{
						$this->cached_events[date('Ymd',$endtime)][] = $event;
					}
				}
			}

			$this->repeating_events = Array();
			if($c_cached_ids_repeating)
			{
				for($i=0;$i<$c_cached_ids_repeating;$i++)
				{
					$this->repeating_events[$i] = $this->so->read_entry($cached_event_ids_repeating[$i]);
				}
				$edate -= $this->datetime->tz_offset;
				for($date=mktime(0,0,0,$smonth,$sday,$syear) - $this->datetime->tz_offset;$date<$edate;$date += (60 * 60 * 24))
				{
					$this->check_repeating_events($date);
				}
			}
		}

		function set_week_array($startdate,$cellcolor,$weekly)
		{
			global $phpgw, $phpgw_info;

			$today = date('Ymd',time());
			for ($j=0;$j<7;$j++)
			{
				$date = $this->datetime->gmtdate($startdate + ($j * 86400));

				$holidays = $this->cached_holidays[$date['full']];
				if($weekly)
				{
					$cellcolor = $phpgw->nextmatchs->alternate_row_color($cellcolor);
				}
				
				$day_image = '';
				if($holidays)
				{
					$extra = ' bgcolor="'.$this->holiday_color.'"';
					$class = 'minicalhol';
					if ($date['full'] == $today)
					{
						$day_image = ' background="'.$phpgw->common->image('calendar','mini_day_block.gif').'"';
					}
				}
				elseif ($date['full'] != $today)
				{
					$extra = ' bgcolor="'.$cellcolor.'"';
					$class = 'minicalendar';
				}
				else
				{
					$extra = ' bgcolor="'.$phpgw_info['theme']['cal_today'].'"';
					$class = 'minicalendar';
					$day_image = ' background="'.$phpgw->common->image('calendar','mini_day_block.gif').'"';
				}

				if($this->printer_friendly && @$phpgw_info['user']['preferences']['calendar']['print_black_white'])
				{
					$extra = '';
				}

				$new_event = False;
				if(!$this->printer_friendly && $this->check_perms(PHPGW_ACL_ADD))
				{
					$new_event = True;
				}
				$holiday_name = Array();
				if($holidays)
				{
					for($k=0;$k<count($holidays);$k++)
					{
						$holiday_name[] = $holidays[$k]['name'];
					}
				}
				$rep_events = $this->cached_events[$date['full']];
				$appts = False;
				if($rep_events)
				{
					$appts = True;
				}
				$week = '';
				if (!$j || ($j && substr($date['full'],6,2) == '01'))
				{
					$week = 'week ' .(int)((date('z',($startdate+(24*3600*4)))+7)/7);
				}
				$daily[$date['full']] = Array(
					'extra'		=> $extra,
					'new_event'	=> $new_event,
					'holidays'	=> $holiday_name,
					'appts'		=> $appts,
					'week'		=> $week,
					'day_image'=> $day_image,
					'class'		=> $class
				);
			}
//			$this->_debug_array($daily);
			return $daily;
		}

		function _debug_array($data)
		{
			echo '<br>UI:';
			_debug_array($data);
		}
	}
?>
