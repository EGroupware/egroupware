<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	if (floor(phpversion()) == 4)
	{
		global $phpgw_info, $phpgw, $grants, $owner, $rights, $filter;
		global $date, $year, $month, $day, $thisyear, $thismonth, $thisday;
	}

	if(!isset($filter) || !$filter)
	{
		$filter = $phpgw_info['user']['preferences']['calendar']['defaultfilter'];
	}

	// This is the initialization of the ACL usage

	$grants = $phpgw->acl->get_grants('calendar');

	if(!isset($owner))
	{
		$owner = 0;
	}

	settype($owner,'integer');

	if(!isset($owner) || !$owner || ($owner == $phpgw_info['user']['account_id']))
	{
		$owner = $phpgw_info['user']['account_id'];
		$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + PHPGW_ACL_PRIVATE;
		$grants[$owner] = $rights;
	}
	else
	{
		if($grants[$owner])
		{
			$grants[$phpgw_info['user']['account_id']] = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + PHPGW_ACL_PRIVATE;

			$rights = $grants[$owner];
			if ($rights == 0)
			{
				$owner = $phpgw_info['user']['account_id'];
				$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + PHPGW_ACL_PRIVATE;
				$grants[$owner] = $rights;
			}
		}
		else
		{
			$owner = $phpgw_info['user']['account_id'];
			$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + PHPGW_ACL_PRIVATE;
			$grants[$owner] = $rights;
		}
	}

	/* Load calendar class */
	$parameters = Array(
		'printer_friendly'=> ((isset($friendly) && ($friendly==1))?True:False),
		'owner'				=> intval($owner),
		'rights'				=> intval($rights)
	);
  
	if(!isset($phpgw_info['user']['preferences']['calendar']['weekdaystarts']))
	{
		$phpgw_info['user']['preferences']['calendar']['weekdaystarts'] = 'Sunday';
		$phpgw->preferences->save_repository();
	}
	
	$phpgw->calendar  = CreateObject('calendar.calendar',$parameters);

	if (isset($date) && strlen($date) > 0)
	{
		$thisyear  = intval(substr($date, 0, 4));
		$thismonth = intval(substr($date, 4, 2));
		$thisday   = intval(substr($date, 6, 2));
	}
	else
	{
		if (!isset($day) || !$day)
		{
			$thisday = $phpgw->calendar->today['day'];
		}
		else
		{
			$thisday = $day;
		}
    
		if (!isset($month) || !$month)
		{
			$thismonth = $phpgw->calendar->today['month'];
		}
		else
		{
			$thismonth = $month;
		}
    
		if (!isset($year) || !$year)
		{
			$thisyear = $phpgw->calendar->today['year'];
		}
		else
		{
			$thisyear = $year;
		}

	}
  
	$phpgw->calendar->tempyear = $thisyear;
	$phpgw->calendar->tempmonth = $thismonth;
	$phpgw->calendar->tempday = $thisday;

	$phpgw->calendar->holidays->read_holiday();
?>
