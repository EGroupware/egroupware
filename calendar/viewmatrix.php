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

	$date = $thisyear;
	$date .= ($thismonth<=9?"0":"").$thismonth;
	$date .= ($thisday<=9?"0":"").$thisday;

	$parts = Array();
	for($i=0;$i<count($participants);$i++)
	{
		switch ($phpgw->accounts->get_type($participants[$i]))
		{
			case 'g':
				$acct = CreateObject('phpgwapi.accounts',$participants[$i]);
				$members = $acct->members(intval($participants[$i]));
				while($members != False && $member = each($members))
				{
					if(($grants[$member[1]['account_id']] & PHPGW_ACL_READ) && !isset($parts[$member[1]['account_id']]))
					{
						$parts[$member[1]['account_id']] = 1;
					}
				}
				unset($acct);
				break;
			case 'u':
				if(($grants[$participants[$i]] & PHPGW_ACL_READ) && !isset($parts[$participants[$i]]))
				{
					$parts[$participants[$i]] = 1;
				}
				break;
		}
	}

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
			echo $phpgw->calendar->timematrix($phpgw->calendar->date_to_epoch($date),$phpgw->calendar->splittime('000000'),0,$participants);
			break;
		case 'weekly':
			echo $phpgw->calendar->display_large_week($thisday,$thismonth,$thisyear,true,$participants);
			break;
	}
	echo '<center>';
	echo '<form action="'.$phpgw->link('viewmatrix.php').'" method="post" name="matrixform" target="viewmatrix">';
	echo '<input type="hidden" name="date" value="'.$date.'">';
	echo '<input type="hidden" name="matrixtype" value="'.$matrixtype.'">';
	for ($i=0;$i<count($participants);$i++)
	{
		echo '<input type="hidden" name="participants[]" value="'.$participants[$i].'">';
	}
	if(isset($filter) && $filter)
	{
		echo '<input type="hidden" name="filter" value="'.$filter.'">';
	}
	echo '<input type="submit" value="Refresh">';
	echo '</form>';
	echo '</center>';

	$phpgw->common->phpgw_footer();
?>
