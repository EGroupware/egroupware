<?php
	/**************************************************************************\
	* eGroupWare - Holiday                                                     *
	* http://www.egroupware.org                                                *
	* Written by Mark Peters <skeeter@phpgroupware.org>                        *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/**
	 * Business object for calendar holidays
	 *
	 * @package calendar
	 * @author Mark Peters <skeeter@phpgroupware.org>
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 */
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

		var $debug = false;
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
			$this->so =& CreateObject('calendar.soholiday');

			$this->start  = (int)get_var('start',array('POST','GET'));
			$this->query  = get_var('query',array('POST','GET'));
			$this->sort   = get_var('sort',array('POST','GET'));
			$this->order  = get_var('order',array('POST','GET'));
			$this->id     = get_var('id',array('POST','GET'));
			$this->year   = get_var('year',array('POST','GET'),date('Y'));
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

			$this->ui =& CreateObject('calendar.uiholiday');
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
			$this->ui =& CreateObject('calendar.uiholiday');
			$this->ui->admin();
		}

		function accept_holiday()
		{
			$send_back_to = str_replace('submitlocale','holiday_admin',$_SERVER['HTTP_REFERER']);
			if(!@$this->locales[0])
			{
				Header('Location: '.$send_back_to);
			}

			$send_back_to = str_replace('&locale='.$this->locales[0],'',$send_back_to);
			$file = './holidays.'.$this->locales[0];
			if(!file_exists($file) && count($_POST['name']))
			{
				$fp = fopen($file,'w');
				fwrite($fp,"charset\t".$GLOBALS['egw']->translation->charset()."\n");

				$holidays = array();
				foreach($_POST['name'] as $i => $name)
				{
					$holiday = array(
						'locale' => $_POST['locale'],
						'name'   => str_replace('\\','',$name),
						'day'    => $_POST['day'][$i],
						'month'  => $_POST['month'][$i],
						'occurence' => $_POST['occurence'][$i],
						'dow'    => $_POST['dow'][$i],
						'observance' => $_POST['observance'][$i],
					);
				}
				// sort holidays by year / occurence:
				usort($holidays,'_holiday_cmp');

				$last_year = -1;
				foreach($holidays as $holiday)
				{
					$year = $holiday['occurence'] <= 0 ? 0 : $holiday['occurence'];
					if ($year != $last_year)
					{
						echo "\n".($year ? $year : 'regular (year=0)').":\n";
						$last_year = $year;
					}
					fwrite($fp,"$holiday[locale]\t$holiday[name]\t$holiday[day]\t$holiday[month]\t$holiday[occurence]\t$holiday[dow]\t$holiday[observance_rule]\n");
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
			$this->year = (isset($year) && $year > 0?$year:$GLOBALS['egw']->common->show_date(time() - $GLOBALS['egw']->datetime->tz_offset,'Y'));
			$this->owner = ($owner?$owner:$GLOBALS['egw_info']['user']['account_id']);

			if($this->debug)
			{
				echo 'Setting Year to : '.$this->year.'<br>'."\n";
			}

			if(@$GLOBALS['egw_info']['user']['preferences']['common']['country'])
			{
				$this->locales[] = $GLOBALS['egw_info']['user']['preferences']['common']['country'];
			}
			elseif(@$GLOBALS['egw_info']['user']['preferences']['calendar']['locale'])
			{
				$this->locales[] = $GLOBALS['egw_info']['user']['preferences']['calendar']['locale'];
			}
			else
			{
				$this->locales[] = 'US';
			}

			if($this->owner != $GLOBALS['egw_info']['user']['account_id'])
			{
				$owner_pref =& CreateObject('phpgwapi.preferences',$owner);
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

			if($GLOBALS['egw_info']['server']['auto_load_holidays'] == True && $this->locales)
			{
				foreach($this->locales as $local)
				{
					$this->auto_load_holidays($local, $year);
				}
			}
		}

		function auto_load_holidays($locale, $year=0)
		{
			//error_log(__METHOD__."('$locale', $year)");
			if (!egw_cache::getInstance(__CLASS__, $locale.'-'.$year) &&	// check if autoload has been tried for this locale and year
				(!($total_year = $this->so->holiday_total($locale, '', $year)) ||
				// automatic try load new holidays, if there are no irregular ones for queried year
				$total_year == $this->so->holiday_total($locale, '', 1901)))
			{
				//error_log(__METHOD__."('$locale', $year) attemption autoload ...");
				egw_cache::setInstance(__CLASS__, $locale.'-'.$year, true, 864000);	// do NOT try again for 10 days
				@set_time_limit(0);

				/* get the file that contains the calendar events for your locale */
				/* "http://www.egroupware.org/cal/holidays.US.csv";                 */
				$network =& CreateObject('phpgwapi.network');
				if(isset($GLOBALS['egw_info']['server']['holidays_url_path']) && $GLOBALS['egw_info']['server']['holidays_url_path'] != 'localhost')
				{
					$load_from = $GLOBALS['egw_info']['server']['holidays_url_path'];
				}
				else
				{
					if ($GLOBALS['egw_info']['server']['webserver_url'][0] == '/')
					{
						$server_host = ($_SERVER['HTTPS']?'https://':'http://').$_SERVER['HTTP_HOST'].$GLOBALS['egw_info']['server']['webserver_url'];
					}
					else
					{
						$server_host = $GLOBALS['egw_info']['server']['webserver_url'];
					}
					$load_from = $server_host.'/calendar/egroupware.org';
				}
//				echo 'Loading from: '.$load_from.'/holidays.'.strtoupper($locale).'.csv'."<br>\n";
				if($GLOBALS['egw_info']['server']['holidays_url_path'] == 'localhost')
				{
					$lines = @file(EGW_SERVER_ROOT.'/calendar/egroupware.org/holidays.'.strtoupper($locale).'.csv');
				}
				else
				{
					$lines = $network->gethttpsocketfile($load_from.'/holidays.'.strtoupper($locale).'.csv');
				}
				if (!$lines)
				{
					return false;
				}
				// reading the holidayfile from egroupware.org via network::gethttpsocketfile contains all the headers!
				foreach($lines as $line)
				{
					$fields = preg_split("/[\t\n ]+/",$line);

					if ($fields[0] == 'charset' && $fields[1])
					{
						$lines = translation::convert($lines,$fields[1]);
						break;
					}
				}
				foreach ($lines as $line)
				{
//					echo 'Line #'.$i.' : '.$lines[$i]."<br>\n";
					$holiday = explode("\t",$line);
					if(count($holiday) == 7)
					{
						$holiday['locale'] = $holiday[0];
						$holiday['name'] = $GLOBALS['egw']->db->db_addslashes($holiday[1]);
						$holiday['mday'] = (int)$holiday[2];
						$holiday['month_num'] = (int)$holiday[3];
						$holiday['occurence'] = (int)$holiday[4];
						$holiday['dow'] = (int)$holiday[5];
						$holiday['observance_rule'] = (int)$holiday[6];
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
			if(@$_POST['submit'])
			{
				$holiday = $_POST['holiday'];

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
					$holiday['occurence'] = (int)($holiday['occurence'] ? $holiday['occurence'] : $holiday['year']);
					unset($holiday['year']);
				}

	// Still need to put some validation in here.....

				$this->ui =& CreateObject('calendar.uiholiday');

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

			$temp_locale = $GLOBALS['egw_info']['user']['preferences']['common']['country'];
			foreach($holidays as $i => $holiday)
			{
				if($i == 0 || $holidays[$i]['locale'] != $holidays[$i - 1]['locale'])
				{
					if(is_object($holidaycalc))
					{
						unset($holidaycalc);
					}
					$GLOBALS['egw_info']['user']['preferences']['common']['country'] = $holidays[$i]['locale'];
					$holidaycalc =& CreateObject('calendar.holidaycalc');
				}
				$holidays[$i]['date'] = $holidaycalc->calculate_date($holiday, $holidays, $this->year);
			}
			unset($holidaycalc);
			$this->holidays = $this->sort_holidays_by_date($holidays);
			$this->cached_holidays = $this->set_holidays_to_date($this->holidays);
			$GLOBALS['egw_info']['user']['preferences']['common']['country'] = $temp_locale;
			return $this->cached_holidays;
		}
		/* End Calendar functions */

		function check_admin()
		{
			if(!@$GLOBALS['egw_info']['user']['apps']['admin'])
			{
				Header('Location: ' . $GLOBALS['egw']->link('/index.php'));
			}
		}

		function rule_string($holiday)
		{
			if (!is_array($holiday))
			{
				return false;
			}
			$monthnames = array(
				'','January','February','March','April','May','June',
				'July','August','September','October','November','December'
			);
			$month = $holiday['month'] ? lang($monthnames[$holiday['month']]) : '';

			if (!$holiday['day'])
			{
				$occ = $holiday['occurence'] == 99 ? lang('last') : $holiday['occurence'].'.';

				$dow_str = Array(lang('Sun'),lang('Mon'),lang('Tue'),lang('Wed'),lang('Thu'),lang('Fri'),lang('Sat'));
				$dow = $dow_str[$holiday['dow']];

				$str = lang('%1 %2 in %3',$occ,$dow,$month);
			}
			else
			{
				$str = $GLOBALS['egw']->common->dateformatorder($holiday['occurence']>1900?$holiday['occurence']:'',$month,$holiday[day]);
			}
			if ($holiday['observance_rule'])
			{
				$str .= ' ('.lang('Observance Rule').')';
			}
			return $str;
		}
	}

	function _holiday_cmp($a,$b)
	{
		if (($year_diff = ($a['occurence'] <= 0 ? 0 : $a['occurence']) - ($b['occurence'] <= 0 ? 0 : $b['occurence'])))
		{
			return $year_diff;
		}
		return $a['month'] - $b['month'] ? $a['month'] - $b['month'] : $a['day'] - $b['day'];
	}
