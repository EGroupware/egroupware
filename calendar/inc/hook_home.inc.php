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

	if ($GLOBALS['phpgw_info']['user']['preferences']['calendar']['mainscreen_showevents'])
	{
		global $date;
		$time = time() - ((60*60) * intval($GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset']));
		$date = $GLOBALS['phpgw']->common->show_date($time,'Ymd');
		$cal = CreateObject('calendar.uicalendar');
		$extra_data = "\n".'<td>'."\n".'<table border="0" cols="3"><tr><td align="center" width="35%" valign="top">'
			. $cal->mini_calendar(
				Array(
					'day'		=> $cal->bo->day,
					'month'	=> $cal->bo->month,
					'year'	=> $cal->bo->year,
					'link'	=> 'day'
				)
			).'</td><td align="center"><table border="0" width="100%" cellspacing="0" cellpadding="0">'
			. '<tr><td align="center">'.lang($GLOBALS['phpgw']->common->show_date($time,'F')).' '.$cal->bo->day.', '
			.$cal->bo->year.'</td></tr><tr><td bgcolor="'.$GLOBALS['phpgw_info']['theme']['bg_text']
			.'" valign="top">'.$cal->print_day(
				Array(
					'year'	=> $cal->bo->year,
					'month'	=> $cal->bo->month,
					'day'		=> $cal->bo->day
				)
			).'</td></tr></table>'."\n".'</td>'."\n".'</tr>'."\n".'</table>'."\n".'</td>'."\n";
			
		$title = '<font color="#FFFFFF">'.lang('Calendar').'</font>';
		
		$portalbox = CreateObject('phpgwapi.listbox',
			Array(
				'title'	=> $title,
				'primary'	=> $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'secondary'	=> $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'tertiary'	=> $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'width'	=> '90%',
				'outerborderwidth'	=> '0',
				'header_background_image'	=> $GLOBALS['phpgw']->common->image('phpgwapi/templates/phpgw_website','bg_filler.gif')
			)
		);

		$app_id = $GLOBALS['phpgw']->applications->name2id('calendar');
		$var = Array(
			'up'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id, 'order' => $GLOBALS['order_seq']),
			'down'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id, 'order' => $GLOBALS['order_seq']),
			'close'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id, 'order' => $GLOBALS['order_seq']),
			'question'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id, 'order' => $GLOBALS['order_seq']),
			'edit'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id, 'order' => $GLOBALS['order_seq'])
		);

		while(list($key,$value) = each($var))
		{
			$portalbox->set_controls($key,$value);
		}

		$portalbox->data = Array();

		echo "\n".'<!-- BEGIN Calendar info -->'."\n".$portalbox->draw($extra_data)."\n".'<!-- END Calendar info -->'."\n";
		unset($cal);
	} 
	flush();
?>
