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
			$this->so = CreateObject('calendar.soholiday');

			if(isset($GLOBALS['start']))  { $this->start = intval($GLOBALS['start']); } else { $this->start = 0; }

			if(isset($GLOBALS['query']))  { $this->query = $GLOBALS['query'];      }

			if(isset($GLOBALS['sort']))   { $this->sort = $GLOBALS['sort'];        }

			if(isset($GLOBALS['order']))  { $this->order = $GLOBALS['order'];      }

			$this->id   = get_var('id',array('POST','GET'));
			$this->year = get_var('year',array('POST','GET'),date('Y'));
			$this->locale = get_var('locale',array('POST','GET'));
			if ($this->locale)
			{
				$this->locales[] = $this->locale;
			}
			
			if($this->debug)
			{
				echo '<-- Locale = '.$this->locales[0].' -->'."\n";
			}

			$this->total = $this->so->holiday_total($this->locales[0],$this->query,$this->year);
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
			$send_back_to = str_replace('submitlocale','holiday_admin',$GLOBALS['HTTP_REFERER']);
			if(!@$this->locales[0])
			{
				Header('Location: '.$send_back_to);
			}

			$send_back_to = str_replace('&locale='.$this->locales[0],'',$send_back_to);
			$file = './holidays.'.$this->locales[0];
			if(!file_exists($file) && count($GLOBALS['HTTP_POST_VARS']['name']))
			{
				$c_holidays = count($GLOBALS['HTTP_POST_VARS']['name']);
				$fp = fopen($file,'w');
				for($i=0;$i<$c_holidays;$i++)
				{
					fwrite($fp,$this->locales[0]."\t".$GLOBALS['HTTP_POST_VARS']['name'][$i]."\t".$GLOBALS['HTTP_POST_VARS']['day'][$i]."\t".$GLOBALS['HTTP_POST_VARS']['month'][$i]."\t".$GLOBALS['HTTP_POST_VARS']['occurence'][$i]."\t".$GLOBALS['HTTP_POST_VARS']['dow'][$i]."\t".$GLOBALS['HTTP_POST_VARS']['observance'][$i]."\n");
				}
				fclose($fp);
			}
			Header('Location: '.$send_back_to);
		}
		
		function get_holiday_list($locale='', $sort='', $order='', $query='', $total='', $year=0)
		{
			$locale = ($locale?$locale:$this->locales[0]);
			$sort = ($sort?$sort:$this->sort);
			$order = ($order?$order:$this->order);
			$query = ($query?$query:$this->query);
			$year = ($$year?$$year:$this->year);
			return $this->so->read_holidays($locale,$query,$order,$year);
		}

		function get_locale_list($sort='', $order='', $query='')
		{
			$sort = ($sort?$sort:$this->sort);
			$order = ($order?$order:$this->order);
			$query = ($query?$query:$this->query);
			return $this->so->get_locale_list($sort,$order,$query);
		}

		function prepare_read_holidays($year=0,$owner=0)
		{
			$this->year = (isset($year) && $year > 0?$year:$GLOBALS['phpgw']->common->show_date(time() - $GLOBALS['phpgw']->datetime->tz_offset,'Y'));
			$this->owner = ($owner?$owner:$GLOBALS['phpgw_info']['user']['account_id']);

			if($this->debug)
			{
				echo 'Setting Year to : '.$this->year.'<br>'."\n";
			}

			if(@$GLOBALS['phpgw_info']['user']['preferences']['common']['country'])
			{
				$this->locales[] = $GLOBALS['phpgw_info']['user']['preferences']['common']['country'];
			}
			elseif(@$GLOBALS['phpgw_info']['user']['preferences']['calendar']['locale'])
			{
				$this->locales[] = $GLOBALS['phpgw_info']['user']['preferences']['calendar']['locale'];
			}
			else
			{
				$this->locales[] = 'US';
			}
			
			if($this->owner != $GLOBALS['phpgw_info']['user']['account_id'])
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
			if($GLOBALS['phpgw_info']['server']['auto_load_holidays'] == True)
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
				global $HTTP_HOST, $SERVER_PORT;
		
				@set_time_limit(0);

				/* get the file that contains the calendar events for your locale */
				/* "http://www.phpgroupware.org/cal/holidays.US";                 */
				$network = CreateObject('phpgwapi.network');
				if(isset($GLOBALS['phpgw_info']['server']['holidays_url_path']) && $GLOBALS['phpgw_info']['server']['holidays_url_path'] != 'localhost')
				{
					$load_from = $GLOBALS['phpgw_info']['server']['holidays_url_path'];
				}
				else
				{
					$pos = strpos(' '.$GLOBALS['phpgw_info']['server']['webserver_url'],$HTTP_HOST);
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
						$server_host = $http_protocol.$HTTP_HOST.$GLOBALS['phpgw_info']['server']['webserver_url'];
					}
					else
					{
						$server_host = $GLOBALS['phpgw_info']['server']['webserver_url'];
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
						$holiday['name'] = $GLOBALS['phpgw']->db->db_addslashes($holiday[1]);
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
			if(@$GLOBALS['HTTP_POST_VARS']['submit'])
			{
				$holiday = $GLOBALS['HTTP_POST_VARS']['holiday'];

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
				
				// some input validation

				if (!$holiday['mday'] == !$holiday['occurence'])
				{
					$errors[] = lang('You need to set either a day or a occurence !!!');
				}
				if($holiday['year'] && $holiday['occurence'])
				{
					$errors[] = lang('You can only set a year or a occurence !!!');
				}
				else
				{
					$holiday['occurence'] = intval($holiday['occurence'] ? $holiday['occurence'] : $holiday['year']);
					unset($holiday['year']);
				}

		
	// Still need to put some validation in here.....

				$this->ui = CreateObject('calendar.uiholiday');

				if (is_array($errors))
				{
					$holiday['month'] = $holiday['month_num'];
					$holiday['day']   = $holiday['mday'];
					$this->ui->edit_holiday($errors,$holiday);
				}
				else
				{
					$this->so->save_holiday($holiday);
					$this->ui->edit_locale($holiday['locale']);
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
			$holidays = $this->so->read_holidays($this->locales,'','',$this->year);

			if(count($holidays) == 0)
			{
				return $holidays;
			}			

			$temp_locale = $GLOBALS['phpgw_info']['user']['preferences']['common']['country'];
			for($i=0;$i<count($holidays);$i++)
			{
				$c = $i;
				if($i == 0 || $holidays[$i]['locale'] != $holidays[$i - 1]['locale'])
				{
					if(is_object($holidaycalc))
					{
						unset($holidaycalc);
					}
					$GLOBALS['phpgw_info']['user']['preferences']['common']['country'] = $holidays[$i]['locale'];
					$holidaycalc = CreateObject('calendar.holidaycalc');
				}
				$holidays[$i]['date'] = $holidaycalc->calculate_date($holidays[$i], $holidays, $this->year, $c);
				if($c != $i)
				{
					$i = $c;
				}
			}
			unset($holidaycalc);
			$this->holidays = $this->sort_holidays_by_date($holidays);
			$this->cached_holidays = $this->set_holidays_to_date($this->holidays);
			$GLOBALS['phpgw_info']['user']['preferences']['common']['country'] = $temp_locale;
			return $this->cached_holidays;
		}
		/* End Calendar functions */

		function check_admin()
		{
			if(!@$GLOBALS['phpgw_info']['user']['apps']['admin'])
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php'));
			}
		}
		
		function rule_string($holiday)
		{
			if (!is_array($holiday))
			{
				return false;
			}
			$sbox = CreateObject('phpgwapi.sbox');
			$month = $holiday['month'] ? lang($sbox->monthnames[$holiday['month']]) : '';
			unset($sbox);

			if (!$holiday['day'])
			{
				$occ = $holiday['occurence'] == 99 ? lang('last') : $holiday['occurence'].'.';

				$dow_str = Array(lang('Sun'),lang('Mon'),lang('Tue'),lang('Wed'),lang('Thu'),lang('Fri'),lang('Sat'));
				$dow = $dow_str[$holiday['dow']];
				
				$str = lang('%1 %2 in %3',$occ,$dow,$month);
			}
			else
			{
				$str = $GLOBALS['phpgw']->common->dateformatorder($holiday['occurence']>1900?$holiday['occurence']:'',$month,$holiday[day]);
			}
			if ($holiday['observance_rule'])
			{
				$str .= ' ('.lang('Observance Rule').')';
			}
			return $str;
		}
	}
?>
