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
			'add'		=> True,
			'delete_holiday'	=> True,
			'delete_locale'	=> True,
			'accept_holiday'	=> True,
			
			'read_entries'	=> True,
			'read_entry'	=> True,
			'add_entry'	=> True,
			'update_entry'	=> True
		);

		var $debug = False;
		var $base_url = '/index.php';

		var $ui;
		var $so;
		var $owner;
		var $year;

		var $id;
		var $total;
		var $start;
		var $query;
		var $sort;
		
		var $locales = Array();
		var $holidays;
		var $cached_holidays;
		
		function boholiday()
		{
			global $phpgw_info, $locale, $start, $query, $sort, $order, $id;

			$this->so = CreateObject('calendar.soholiday');

			if(isset($locale)) { $this->locales[] = $locale; }

			if(isset($start))  { $this->start = $start;      } else { $this->start = 0; }

			if(isset($query))  { $this->query = $query;      }

			if(isset($sort))   { $this->sort = $sort;        }

			if(isset($order))  { $this->order = $order;      }

			if(isset($id))     { $this->id = $id;            }
			
			if($this->debug)
			{
				echo "Locale = ".$this->locales[0]."<br>\n";
			}

			$this->total = $this->so->holiday_total($this->locales[0],$this->query);
		}

		/* Begin Calendar functions */
		function read_entry($id=0)
		{

			if($this->debug)
			{
				echo "BO : Reading Holiday ID : ".$id."<br>\n";
			}
			
			if(!$id)
			{
				if(!$this->id)
				{
					return Array();
				}
				else
				{
					$id = $this->id;
				}
			}

			return $this->so->read_holiday($id);
		}

		function delete_holiday($id=0)
		{
			if(!$id)
			{
				if($this->id)
				{
					$id = $this->id;
				}
			}

			$this->ui = CreateObject('calendar.uiholiday');
			if($id)
			{
				$this->so->delete_holiday($id);
				$this->ui->edit_locale();
			}
			else
			{
				$this->ui->admin();
			}
		}
		
		function delete_locale($locale='')
		{
			if(!$locale)
			{
				if($this->locales[0])
				{
					$locale = $this->locales[0];
				}
			}

			if($locale)
			{
				$this->so->delete_locale($locale);
			}
			$this->ui = CreateObject('calendar.uiholiday');
			$this->ui->admin();
		}

		function accept_holiday()
		{
			global $HTTP_REFERER;
			global $name, $day, $month, $occurence, $dow, $observance;
			
			$send_back_to = str_replace('submitlocale','holiday_admin',$HTTP_REFERER);
			if(!@$this->locales[0])
			{
				Header('Location: '.$send_back_to);
			}

			$send_back_to = str_replace('&locale='.$this->locales[0],'',$send_back_to);
			$file = './holidays.'.$this->locales[0];
			if(!file_exists($file) && count($name))
			{
				$c_holidays = count($name);
				$fp = fopen($file,'w');
				for($i=0;$i<$c_holidays;$i++)
				{
					fwrite($fp,$this->locales[0]."\t".$name[$i]."\t".$day[$i]."\t".$month[$i]."\t".$occurence[$i]."\t".$dow[$i]."\t".$observance[$i]."\n");
				}
				fclose($fp);
			}
			Header('Location: '.$send_back_to);
		}
		
		function get_holiday_list($locale='', $sort='', $order='', $query='', $total='')
		{
			if(!$locale)
			{
				$locale = $this->locales[0];
			}

			if(!$sort)
			{
				$sort = $this->sort;
			}

			if(!$order)
			{
				$order = $this->order;
			}

			if(!$query)
			{
				$query = $this->query;
			}

			return $this->so->read_holidays($locale,$query,$order);
		}

		function get_locale_list($sort='', $order='', $query='')
		{
			if(!$sort)
			{
				$sort = $this->sort;
			}

			if(!$order)
			{
				$order = $this->order;
			}

			if(!$query)
			{
				$query = $this->query;
			}

			return $this->so->get_locale_list($sort,$order,$query);
		}

		function prepare_read_holidays($year=0,$owner=0)
		{
			global $phpgw_info;
			
			if($year==0)
			{
				$this->year = date('Y');
			}
			else
			{
				$this->year = $year;
			}

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
			else
			{
				$this->locales[] = 'US';
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
		
		function auto_load_holidays($locale)
		{
			if($this->so->holiday_total($locale) == 0)
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

		function save_holiday($holiday)
		{
			$this->so->save_holiday($holiday);
		}

		function add()
		{
			global $phpgw, $submit, $holiday, $locale;
			
			if(@$submit)
			{
				if(empty($holiday['mday']))
				{
					$holiday['mday'] = 0;
				}
				if(!isset($this->bo->locales[0]) || $this->bo->locales[0]=='')
				{
					$this->bo->locales[0] = $holiday['locale'];
				}
				elseif(!isset($holiday['locale']) || $holiday['locale']=='')
				{
					$holiday['locale'] = $this->bo->locales[0];
				}
				if(!isset($holiday['hol_id']))
				{
					$holiday['hol_id'] = $this->bo->id;
				}
		
	// Still need to put some validation in here.....

				$this->ui = CreateObject('calendar.uiholiday');
				if (is_array($errors))
				{
					$this->ui->add($errors,$holiday);
				}
				else
				{
					$this->so->save_holiday($holiday);
					$this->ui->edit_locale();
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

		function check_admin()
		{
			global $phpgw, $phpgw_info;

			$admin = False;
			if(@$phpgw_info['user']['apps']['admin'])
			{
				$admin = True;
			}
				
			if(!$admin)
			{
				Header('Location: ' . $phpgw->link('/index.php'));
			}
		}
	}
?>
