<?php 
  /**************************************************************************\
  * phpGroupWare - holidaycalc_US                                            *
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
//		if($holiday['day'] == 0 && $holiday['dow'] != 0 && $holiday['occurence'] != 0)
		if($holiday['day'] == 0 && $holiday['occurence'] != 0)
		{
			if($holiday['occurence'] != 99)
			{
				$dow = $datetime->day_of_week($year,$holiday['month'],1);
				$day = (((7 * $holiday['occurence']) - 6) + ((($holiday['dow'] + 7) - $dow) % 7));
				$day += ($day < 1 ? 7 : 0);
				// What is the point of this?  
				// Add 7 when the holiday falls on a Monday???
				//$day += ($holiday['dow']==1 ? 7 : 0);

				// Sometimes the 5th occurance of a weekday (ie the 5th monday)
				// can spill over to the next month.  This prevents that.  
				$ld = $datetime->days_in_month($holiday['month'],$year);
				if ($day > $ld)
				{
					return;
				}
			}
			else
			{
				$ld = $datetime->days_in_month($holiday['month'],$year);
				$dow = $datetime->day_of_week($year,$holiday['month'],$ld);
				$day = $ld - (($dow + 7) - $holiday['dow']) % 7 ;
			}
		}
		else
		{
			$day = $holiday['day'];
			if($holiday['observance_rule'] == True)
			{
				$dow = $datetime->day_of_week($year,$holiday['month'],$day);
				// This now calulates Observed holidays and creates a new entry for them.
				if($dow == 0)
				{
					$i++;
					$holidays[$i]['locale'] = $holiday['locale'];
					$holidays[$i]['name'] = $holiday['name'].' (Observed)';
					$holidays[$i]['day'] = $holiday['day'] + 1;
					$holidays[$i]['month'] = $holiday['month'];
					$holidays[$i]['occurence'] = $holiday['occurence'];
					$holidays[$i]['dow'] = $holiday['dow'];
					$holidays[$i]['date'] = mktime(0,0,0,$holiday['month'],$day+1,$year) - $datetime->tz_offset;
					$holidays[$i]['obervance_rule'] = 0;
				}
				elseif($dow == 6)
				{
					$i++;
					$holidays[$i]['locale'] = $holiday['locale'];
					$holidays[$i]['name'] = $holiday['name'].' (Observed)';
					$holidays[$i]['day'] = $holiday['day'] - 1;
					$holidays[$i]['month'] = $holiday['month'];
					$holidays[$i]['occurence'] = $holiday['occurence'];
					$holidays[$i]['dow'] = $holiday['dow'];
					$holidays[$i]['date'] = mktime(0,0,0,$holiday['month'],$day-1,$year) - $datetime->tz_offset;
					$holidays[$i]['obervance_rule'] = 0;
				}
			}
		}
		$date = mktime(0,0,0,$holiday['month'],$day,$year) - $datetime->tz_offset;

		return $date;
	}
}
?>
