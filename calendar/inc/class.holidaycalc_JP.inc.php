<?php 
  /**************************************************************************\
  * phpGroupWare - holidaycalc_JP                                            *
  * http://www.phpgroupware.org                                              *
  * Based on Yoshihiro Kamimura <your@itheart.com>                           *
  *          http://www.itheart.com                                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

class holidaycalc {

	function calculate_date($holiday, &$holidays, $year, &$i)
	{
		static	$cached_month;
		static	$cached_day;
		static	$cached_observance_rule;

		if ($holiday['day'] == 0 && $holiday['dow'] != 0 && $holiday['occurence'] != 0)
		{
			$dow = $GLOBALS['phpgw']->datetime->day_of_week($year, $holiday['month'], 1);
			$dayshift = (($holiday['dow'] + 7) - $dow) % 7;
			$day = ($holiday['occurence'] - 1) * 7 + $dayshift + 1;

			// Happy monday law.
			if ($holiday['month'] == 1)
			{
				if ($year < 2000) 
				{
					$day = 15;
				}
			}
			elseif ($holiday['month'] == 7)
			{
				if ($year < 2003)
				{
					$day = 20;
				}
			}
			elseif ($holiday['month'] == 9)
			{
				if ($year < 2003)
				{
					$day = 15;
				}
			}
			elseif ($holiday['month'] == 10)
			{
				if ($year < 2000)
				{
					$day = 10;
				}
			}
		}
		elseif ($holiday['day'] == 0 && $holiday['dow'] == 0 && $holiday['occurence'] == 0)
		{
			// For the next generation.
			// over 2151, please set $factor...
			if ($holiday['month'] == 3)
			{
				// for Vernal Equinox
				if ($year >= 1980 && $year <= 2099)
				{
					$factor = 20.8431;
				}
				elseif ($year >= 2100 && $year <= 2150)
				{
					$factor = 21.851;
				}
			}
			elseif ($holiday['month'] == 9)
			{
				// for Autumnal Equinox
				if ($year >= 1980 && $year <= 2099)
				{
					$factor = 23.2488;
				}
				elseif ($year >= 2100 && $year <= 2150)
				{
					$factor = 24.2488;
				}
			}

			$day = (int)($factor + 0.242194 * ($year - 1980)
			     - (int)(($year - 1980) / 4));
		}
		else
		{
			// normal holiday
			$day = $holiday['day'];
		}

		if ($year >= 1985 && $holiday['month'] == $cached_month && $day == $cached_day + 2 && $cached_observance_rule == True && $holiday['observance_rule'] == True)
		{
			$pdow = $GLOBALS['phpgw']->datetime->day_of_week($year,$holiday['month'],$day-1);
			if ($pdow != 0)
			{
				$addcnt = count($holidays) + 1;
				$holidays[$addcnt]['locale'] = $holiday['locale'];
				if ($pdow == 1)
				{
					$holidays[$addcnt]['name'] = lang('overlap holiday');
				}
				else
				{
					$holidays[$addcnt]['name'] = lang('people holiday');
				}
				$holidays[$addcnt]['day'] = $day - 1;
				$holidays[$addcnt]['month'] = $holiday['month'];
				$holidays[$addcnt]['occurence'] = 0;
				$holidays[$addcnt]['dow'] = 0;
				$holidays[$addcnt]['date'] = mktime(0,0,0,$holiday['month'],$day-1,$year);
				$holidays[$addcnt]['observance_rule'] = 0;
			}
		}

		$cached_month = $holiday['month'];
		$cached_day = $day;
		$cached_observance_rule = $holiday['observance_rule'];

		if ($year >= 1985 && $holiday['month'] == 5 && $day == 3)
		{
			;
		}
		elseif ($holiday['observance_rule'] == True)
		{
			$dow = $GLOBALS['phpgw']->datetime->day_of_week($year,$holiday['month'],$day);
			// This now calulates Observed holidays and creates a new entry for them.
			if($dow == 0)
			{
				$addcnt = count($holidays) + 1;
				$holidays[$addcnt]['locale'] = $holiday['locale'];
				$holidays[$addcnt]['name'] = lang('overlap holiday');
				$holidays[$addcnt]['day'] = $day + 1;
				$holidays[$addcnt]['month'] = $holiday['month'];
				$holidays[$addcnt]['occurence'] = $holiday['occurence'];
				$holidays[$addcnt]['dow'] = $holiday['dow'];
				$holidays[$addcnt]['date'] = mktime(0,0,0,$holiday['month'],$day+1,$year);
				$holidays[$addcnt]['observance_rule'] = 0;
			}
		}

		$date = mktime(0,0,0,$holiday['month'],$day,$year);

		return $date;
	}
}
?>
