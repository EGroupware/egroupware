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

	function calculate_date($holiday, &$holidays, $year, $datetime, &$i)
	{

		if($holiday['day'] == 0 && $holiday['dow'] != 0 && $holiday['occurence'] != 0)
		{
			// for Coming of Age Day and Health and Sports Day
			// Happy monday law.
			if ($year >= 2000)
			{
				$dow = $datetime->day_of_week($year, $holiday['month'], 1);
				$dayshift = (($holiday['dow'] + 7) - $dow) % 7;
				$day = ($holiday['occurence'] - 1) * 7 + $dayshift + 1;
			}
			else
			{
				// non Happy monday law.
				if ($holiday['month'] == 1)
				{
					$day = 15;
				}
				elseif ($holiday['month'] == 10)
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

		if($holiday['observance_rule'] == True)
		{
			$dow = $datetime->day_of_week($year,$holiday['month'],$day);
			// This now calulates Observed holidays and creates a new entry for them.
			if($dow == 0)
			{
				$i++;
				$holidays[$i]['locale'] = $holiday['locale'].' (Observed)';
				$holidays[$i]['name'] = lang('overlap holiday');
				$holidays[$i]['day'] = $holiday['day'] + 1;
				$holidays[$i]['month'] = $holiday['month'];
				$holidays[$i]['occurence'] = $holiday['occurence'];
				$holidays[$i]['dow'] = $holiday['dow'];
				$holidays[$i]['date'] = mktime(0,0,0,$holiday['month'],$day+1,$year);
				$holidays[$i]['obervance_rule'] = 0;
			}
		}

		$date = mktime(0,0,0,$holiday['month'],$day,$year);

		return $date;
	}
}
?>
