<?php
  /**************************************************************************\
  * eGroupWare - Calendar                                                    *
  * http://www.egroupware.org                                                *
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

	class toolbar
	{

		
		function toolbar()
		{
			$toolbar = Array();
			$toolbar['viewday'] = Array(
				'title' => "Today",
				'image'   => 'today.png',
				'url'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.day')
				);

			$toolbar['viewweek'] = Array(
				'title' => "This week",
				'image'   => 'week.png',
				'url'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.week')
				);
				
			$toolbar['viewmonth'] = Array(
				'title' => "This month",
				'image'   => 'month.png',
				'url'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.month')
				);	
			
			$toolbar['viewyear'] = Array(
				'title' => "This year",
				'image'   => 'year.png',
				'url'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.year')
				);
				
			
			
				
			$toolbar['planner'] = Array(
				'title' => "Group Planner",
				'image'   => 'planner.png',
				'url'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.planner')
				);	
				
			$toolbar['view'] = 'Separator';
			
			$toolbar['matrixselect'] = Array(
				'title' => "Daily Matrix",
				'image'   => 'view.png',
				'url'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.matrixselect')
				);								
			
	
		
			return $toolbar;
		}

	}	
?>
