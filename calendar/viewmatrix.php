<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	$phpgw_flags = Array(
		'currentapp'					=>	'calendar',
		'enable_nextmatchs_class'	=>	True,
	);

	$phpgw_info['flags'] = $phpgw_flags;

	include('../header.inc.php');

//	$date = $thisyear;
//	$date .= ($thismonth<=9?"0":"").$thismonth;
//	$date .= ($thisday<=9?"0":"").$thisday;

	$parts = Array();
	$acct = CreateObject('phpgwapi.accounts');
	for($i=0;$i<count($participants);$i++)
	{
		switch ($phpgw->accounts->get_type($participants[$i]))
		{
			case 'g':
				$members = $acct->members(intval($participants[$i]));
				while($members != False && $member = each($members))
				{
					if(!!($grants[$member[1]['account_id']] & PHPGW_ACL_READ == True) && !isset($parts[$member[1]['account_id']]))
					{
						$parts[$member[1]['account_id']] = 1;
					}
				}
				break;
			case 'u':
				if(!!($grants[$participants[$i]] & PHPGW_ACL_READ == True) && !isset($parts[$participants[$i]]))
				{
					$parts[$participants[$i]] = 1;
				}
				break;
		}
	}
	unset($acct);

	$participants = Array();
	reset($parts);
	while($part = each($parts))
	{
		$participants[] = $part[0];
	}

	reset($participants);

	switch($matrixtype)
	{
		case 'free/busy':
			$freetime = $phpgw->calendar->datetime->makegmttime(0,0,0,$thismonth,$thisday,$thisyear);
			echo $phpgw->calendar->timematrix($freetime,$phpgw->calendar->splittime('000000',False),0,$participants);
			break;
		case 'weekly':
			echo $phpgw->calendar->display_large_week($thisday,$thismonth,$thisyear,true,$participants);
			break;
	}
	echo "\n".'<center>'."\n";
	echo ' <form action="'.$phpgw->link('/calendar/viewmatrix.php').'" method="post" name="matrixform" target="viewmatrix">'."\n";
	echo '  <input type="hidden" name="year" value="'.$thisyear.'">'."\n";
	echo '  <input type="hidden" name="month" value="'.$thismonth.'">'."\n";
	echo '  <input type="hidden" name="day" value="'.$thisday.'">'."\n";
	echo '  <input type="hidden" name="matrixtype" value="'.$matrixtype.'">'."\n";
	for ($i=0;$i<count($participants);$i++)
	{
		echo '  <input type="hidden" name="participants[]" value="'.$participants[$i].'">'."\n";
	}
	if(isset($filter) && $filter)
	{
		echo '  <input type="hidden" name="filter" value="'.$filter.'">'."\n";
	}
	echo '  <input type="submit" value="'.lang('refresh').'">'."\n";
	echo ' </form>'."\n";
	echo '</center>'."\n";

	$phpgw->common->phpgw_footer();
?>
