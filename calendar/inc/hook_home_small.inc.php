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

	$d1 = strtolower(substr(PHPGW_APP_INC,0,3));
	if($d1 == 'htt' || $d1 == 'ftp' )
	{
		echo 'Failed attempt to break in via an old Security Hole!<br>'."\n";
		$GLOBALS['phpgw']->common->phpgw_exit();
	}
	unset($d1);

	$today = $GLOBALS['phpgw']->datetime->users_localtime;
	$dates = array($today);
	$wday = date('w',$today);
	if($wday=='5') // if it's Friday, show the weekend, plus Monday
	{
		$dates[] = $today + 86400;		// Saturday
		$dates[] = $today + (2*86400);	// Sunday
	}
	if($wday=='6') // if it's Saturday, show Sunday, plus Monday
	{
		$dates[] = $today + 86400;		// Sunday
	}
	$dates[] = $dates[count($dates)-1] + 86400; // the next business day

	$extra_data = $GLOBALS['css']."\n"
			. '<table border="0" width="100%" cellspacing="0" cellpadding="1">'
			. '<tr><td valign="top" width="100%">';
	foreach($dates as $id=>$day)
	{
		$dayprint = ExecMethod('calendar.uicalendar.print_day',
							Array(
								'year'  => date('Y',$day),
								'month' => date('m',$day),
								'day'   => date('d',$day)
							));
		$extra_data .= '<font class="event-off" style="font-weight: bold">'.date('l',$day) .'</font><br />' . $dayprint;
	}
	$extra_data .= '</td></tr></table>'."\n";

	$GLOBALS['extra_data'] = $extra_data;

	unset($dates);
	unset($today);
	unset($extra_data);
?>
