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

	$tmp_app_inc = PHPGW_APP_INC;
	define('PHPGW_APP_INC',$phpgw->common->get_inc_dir('calendar'));

	if ($phpgw_info['user']['preferences']['calendar']['mainscreen_showevents'])
	{
		include(PHPGW_APP_INC . '/functions.inc.php');
		echo "\n".'<tr valign="top"><td><table border="0" cols="3"><tr><td align="center" width="35%" valign="top"><!-- Calendar info -->'."\n";
		echo $phpgw->calendar->mini_calendar($phpgw->calendar->today["day"],$phpgw->calendar->today["month"],$phpgw->calendar->today["year"],"day.php").'</td><td align="center">';
		echo '<table border="0" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
			. lang(date("F",$phpgw->calendar->today["raw"])).' '.$phpgw->calendar->today["day"].', '.$phpgw->calendar->today["year"].'</tr></td>'
			. '<tr><td bgcolor="'.$phpgw_info["theme"]["bg_text"].'" valign="top">';
//		$phpgw->calendar->printer_friendly = True;
		$now = $phpgw->calendar->datetime->makegmttime(0,0,0,$phpgw->calendar->today['month'],$phpgw->calendar->today['day'],$phpgw->calendar->today['year']);
		$now['raw'] += $phpgw->calendar->datetime->tz_offset;
		echo $phpgw->calendar->print_day_at_a_glance($now).'</td></tr></table>'."\n";
//		$phpgw->calendar->printer_friendly = False;
		echo "\n".'<!-- Calendar info --></table></td></tr>'."\n";
		unset($phpgw->calendar);
	} 

	define('PHPGW_APP_INC',$tmp_app_inc);
?>
