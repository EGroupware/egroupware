<?php
  /**************************************************************************\
  * phpGroupWare API - Commononly used functions                             *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * and Mark Peters <skeeter@phpgroupware.org>                               *
  * Commononly used functions by phpGroupWare developers                     *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

	/* $Id$ */

	$d1 = strtolower(@substr(PHPGW_API_INC,0,3));
	$d2 = strtolower(@substr(PHPGW_SERVER_ROOT,0,3));
	$d3 = strtolower(@substr(PHPGW_APP_INC,0,3));
	if($d1 == 'htt' || $d1 == 'ftp' || $d2 == 'htt' || $d2 == 'ftp' || $d3 == 'htt' || $d3 == 'ftp')
	{
		echo 'Failed attempt to break in via an old Security Hole!<br>'."\n";
		exit;
	}
	unset($d1);
	unset($d2);
	unset($d3);
		
	/*!
	@class datetime
	@abstract datetime class that contains common date/time functions
	*/
	class datetime
	{
		var $tz_offset;
		var $days = Array();
		var $gmtnow;
		var $gmtdate;

		function datetime()
		{
			$this->tz_offset = ((60 * 60) * intval($GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset']));
/*
 * This is not advanced enough to automatically recalc Daylight Savings time
			if(date('I') == 1)
			{
				$this->tz_offset += 3600;
			}
 */
			$this->gmtdate = gmdate('D, d M Y H:i:s',time()).' GMT';
			$this->gmtnow = $this->convert_rfc_to_epoch($this->gmtdate);
		}

		function convert_rfc_to_epoch($date_str)
		{
			$comma_pos = strpos($date_str,',');
			if($comma_pos)
			{
				$date_str = substr($date_str,$comma_pos+1);
			}

			// This may need to be a reference to the different months in native tongue....
			$month= array(
				'Jan' => 1,
				'Feb' => 2,
				'Mar' => 3,
				'Apr' => 4,
				'May' => 5,
				'Jun' => 6,
				'Jul' => 7,
				'Aug' => 8,
				'Sep' => 9,
				'Oct' => 10,
				'Nov' => 11,
				'Dec' => 12
			);
			$dta = array();
			$ta = array();

			// Convert "15 Jul 2000 20:50:22 +0200" to unixtime
			$dta = explode(' ',$date_str);
			$ta = explode(':',$dta[4]);

			if(substr($dta[5],0,3) <> 'GMT')
			{
				$tzoffset = substr($dta[5],0,1);
				$tzhours = intval(substr($dta[5],1,2));
				$tzmins = intval(substr($dta[5],3,2));
				switch ($tzoffset)
				{
					case '-':
						(int)$ta[0] += $tzhours;
						(int)$ta[1] += $tzmins;
						break;
					case '+':
						(int)$ta[0] -= $tzhours;
						(int)$ta[1] -= $tzmins;
						break;
				}
			}
			return mktime($ta[0],$ta[1],$ta[2],$month[$dta[2]],$dta[1],$dta[3]);
		}

		function get_weekday_start($year,$month,$day)
		{
			$weekday = $this->day_of_week($year,$month,$day);
			switch($GLOBALS['phpgw_info']['user']['preferences']['calendar']['weekdaystarts'])
			{
				// Saturday is for arabic support
				case 'Saturday':
					$this->days = Array(
						0 => Array(
							'name'	=> 'Sat',
							'weekday'	=> 0
						),
						1 => Array(
							'name'	=> 'Sun',
							'weekday'	=> 0
						),
						2 => Array(
							'name'	=> 'Mon',
							'weekday'	=> 1
						),
						3 => Array(
							'name'	=> 'Tue',
							'weekday'	=> 1
						),
						4 => Array(
							'name'	=> 'Wed',
							'weekday'	=> 1
						),
						5 => Array(
							'name'	=> 'Thu',
							'weekday'	=> 1
						),
						6 => Array(
							'name'	=> 'Fri',
							'weekday'	=> 1
						)
					);
					switch($weekday)
					{
						case 0:
							$sday = mktime(2,0,0,$month,$day - 1,$year);
							break;
						case 6:
							$sday = mktime(2,0,0,$month,$day,$year);
							break;
						default:
							$sday = mktime(2,0,0,$month,$day - ($weekday + 1),$year);
							break;
					}
					break;
				case 'Monday':
					$this->days = Array(
						0 => Array(
							'name'	=> 'Mon',
							'weekday'	=> 1
						),
						1 => Array(
							'name'	=> 'Tue',
							'weekday'	=> 1
						),
						2 => Array(
							'name'	=> 'Wed',
							'weekday'	=> 1
						),
						3 => Array(
							'name'	=> 'Thu',
							'weekday'	=> 1
						),
						4 => Array(
							'name'	=> 'Fri',
							'weekday'	=> 1
						),
						5 => Array(
							'name'	=> 'Sat',
							'weekday'	=> 0
						),
						6 => Array(
							'name'	=> 'Sun',
							'weekday'	=> 0
						)
					);
					switch($weekday)
					{
						case 0:
							$sday = mktime(2,0,0,$month,$day - 6,$year);
							break;
						case 1:
							$sday = mktime(2,0,0,$month,$day,$year);
							break;
						default:
							$sday = mktime(2,0,0,$month,$day - ($weekday - 1),$year);
							break;
					}
					break;
				case 'Sunday':
				default:
					$this->days = Array(
						0 => Array(
							'name'	=> 'Sun',
							'weekday'	=> 0
						),
						1 => Array(
							'name'	=> 'Mon',
							'weekday'	=> 1
						),
						2 => Array(
							'name'	=> 'Tue',
							'weekday'	=> 1
						),
						3 => Array(
							'name'	=> 'Wed',
							'weekday'	=> 1
						),
						4 => Array(
							'name'	=> 'Thu',
							'weekday'	=> 1
						),
						5 => Array(
							'name'	=> 'Fri',
							'weekday'	=> 1
						),
						6 => Array(
							'name'	=> 'Sat',
							'weekday'	=> 0
						)
					);
					$sday = mktime(2,0,0,$month,$day - $weekday,$year);
					break;
			}
			return $sday - 7200;
		}

		function is_leap_year($year)
		{
			if ((intval($year) % 4 == 0) && (intval($year) % 100 != 0) || (intval($year) % 400 == 0))
			{
				return 1;
			}
			else
			{
				return 0;
			}
		}

		function days_in_month($month,$year)
		{
			$days = Array(
				1	=>	31,
				2	=>	28 + $this->is_leap_year(intval($year)),
				3	=>	31,
				4	=>	30,
				5	=>	31,
				6	=>	30,
				7	=>	31,
				8	=>	31,
				9	=>	30,
				10	=>	31,
				11	=>	30,
				12	=>	31
			);
			return $days[intval($month)];
		}

		function date_valid($year,$month,$day)
		{
			return checkdate(intval($month),intval($day),intval($year));
		}

		function time_valid($hour,$minutes,$seconds)
		{
			if(intval($hour) < 0 || intval($hour) > 24)
			{
				return False;
			}
			if(intval($minutes) < 0 || intval($minutes) > 59)
			{
				return False;
			}
			if(intval($seconds) < 0 || intval($seconds) > 59)
			{
				return False;
			}

			return True;
		}

		function day_of_week($year,$month,$day)
		{
			if($month > 2)
			{
				$month -= 2;
			}
			else
			{
				$month += 10;
				$year--;
			}
			$day = (floor((13 * $month - 1) / 5) + $day + ($year % 100) + floor(($year % 100) / 4) + floor(($year / 100) / 4) - 2 * floor($year / 100) + 77);
			return (($day - 7 * floor($day / 7)));
		}
	
		function day_of_year($year,$month,$day)
		{
			$days = array(0,31,59,90,120,151,181,212,243,273,304,334);

			$julian = ($days[$month - 1] + $day);

			if($month > 2 && $this->is_leap_year($year))
			{
				$julian++;
			}
			return($julian);
		}

		function date_compare($a_year,$a_month,$a_day,$b_year,$b_month,$b_day)
		{
			$a_date = mktime(0,0,0,intval($a_month),intval($a_day),intval($a_year));
			$b_date = mktime(0,0,0,intval($b_month),intval($b_day),intval($b_year));
			if($a_date == $b_date)
			{
				return 0;
			}
			elseif($a_date > $b_date)
			{
				return 1;
			}
			elseif($a_date < $b_date)
			{
				return -1;
			}
		}

		function time_compare($a_hour,$a_minute,$a_second,$b_hour,$b_minute,$b_second)
		{
			$a_time = mktime(intval($a_hour),intval($a_minute),intval($a_second),0,0,70);
			$b_time = mktime(intval($b_hour),intval($b_minute),intval($b_second),0,0,70);
			if($a_time == $b_time)
			{
				return 0;
			}
			elseif($a_time > $b_time)
			{
				return 1;
			}
			elseif($a_time < $b_time)
			{
				return -1;
			}
		}

		function makegmttime($hour,$minute,$second,$month,$day,$year)
		{
			return $this->gmtdate(mktime($hour, $minute, $second, $month, $day, $year));
		}

		function localdates($localtime)
		{
			$date = Array('raw','day','month','year','full','dow','dm','bd');
			$date['raw'] = $localtime;
			$date['year'] = intval($GLOBALS['phpgw']->common->show_date($date['raw'],'Y'));
			$date['month'] = intval($GLOBALS['phpgw']->common->show_date($date['raw'],'m'));
			$date['day'] = intval($GLOBALS['phpgw']->common->show_date($date['raw'],'d'));
			$date['full'] = intval($GLOBALS['phpgw']->common->show_date($date['raw'],'Ymd'));
			$date['bd'] = mktime(0,0,0,$date['month'],$date['day'],$date['year']);
			$date['dm'] = intval($GLOBALS['phpgw']->common->show_date($date['raw'],'dm'));
			$date['dow'] = $this->day_of_week($date['year'],$date['month'],$date['day']);
			$date['hour'] = intval($GLOBALS['phpgw']->common->show_date($date['raw'],'H'));
			$date['minute'] = intval($GLOBALS['phpgw']->common->show_date($date['raw'],'i'));
			$date['second'] = intval($GLOBALS['phpgw']->common->show_date($date['raw'],'s'));
		
			return $date;
		}

		function gmtdate($localtime)
		{
			return $this->localdates($localtime - $this->tz_offset);
		}
	}
?>
