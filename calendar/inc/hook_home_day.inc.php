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

	$GLOBALS['extra_data'] = $GLOBALS['css']."\n".'<td>'."\n".'<table border="0" cols="3"><tr><td align="center" width="35%" valign="top">'
		. ExecMethod('calendar.uicalendar.mini_calendar',
			Array(
				'day'		=> $GLOBALS['g_day'],
				'month'	=> $GLOBALS['g_month'],
				'year'	=> $GLOBALS['g_year'],
				'link'	=> 'day'
			)
		).'</td><td align="center"><table border="0" width="100%" cellspacing="0" cellpadding="0">'
		. '<tr><td align="center">'.lang($GLOBALS['phpgw']->common->show_date($time,'F')).' '.$GLOBALS['g_day'].', '
		.$GLOBALS['g_year'].'</td></tr><tr><td bgcolor="'.$GLOBALS['phpgw_info']['theme']['bg_text']
		.'" valign="top">'.ExecMethod('calendar.uicalendar.print_day',
			Array(
				'year'	=> $GLOBALS['g_year'],
				'month'	=> $GLOBALS['g_month'],
				'day'		=> $GLOBALS['g_day']
			)
		).'</td></tr></table>'."\n".'</td>'."\n".'</tr>'."\n".'</table>'."\n".'</td>'."\n";
?>
