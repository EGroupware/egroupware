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
		$phpgw->common->phpgw_exit();
	}
	unset($d1);

	global $date;

	if ($phpgw_info['user']['preferences']['calendar']['mainscreen_showevents'])
	{
		$date = $phpgw->common->show_date(time()-((60*60)*intval($phpgw_info['user']['preferences']['common']['tz_offset'])),'Ymd');
		$cal = CreateObject('calendar.uicalendar');
		echo "\n".'<tr valign="top"><td><table border="0" cols="3"><tr><td align="center" width="35%" valign="top"><!-- BEGIN Calendar info -->'."\n"
			. $cal->mini_calendar(
				Array(
					'day'	=> $cal->bo->day,
					'month'	=> $cal->bo->month,
					'year'	=> $cal->bo->year,
					'link'	=> 'day'
				)
			).'</td><td align="center">'
			. '<table border="0" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
			. lang($phpgw->common->show_date(time()-((60*60)*intval($phpgw_info['user']['preferences']['common']['tz_offset'])),'F')).' '.$cal->bo->day.', '.$cal->bo->year.'</tr></td>'
			. '<tr><td bgcolor="'.$phpgw_info['theme']['bg_text'].'" valign="top">'
			. $cal->print_day(Array('year'=>$cal->bo->year,'month'=>$cal->bo->month,'day'=>$cal->bo->day)).'</td></tr></table>'."\n"
			. "\n".'<!-- END Calendar info --></table></td></tr>'."\n";
		unset($cal);
	} 
?>
