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

	if ($GLOBALS['phpgw_info']['user']['preferences']['calendar']['mainscreen_showevents'])
	{
		$time = time() - ((60*60) * intval($GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset']));
		$GLOBALS['date'] = $GLOBALS['phpgw']->common->show_date($time,'Ymd');
		$GLOBALS['g_year'] = substr($GLOBALS['date'],0,4);
		$GLOBALS['g_month'] = substr($GLOBALS['date'],4,2);
		$GLOBALS['g_day'] = substr($GLOBALS['date'],6,2);
		$GLOBALS['owner'] = $GLOBALS['phpgw_info']['user']['account_id'];
		$GLOBALS['css'] = "\n".'<STYLE type="text/css">'."\n".'<!--'."\n"
			. ExecMethod('calendar.uicalendar.css').'-->'."\n".'</style>';

		$page_ = explode('.',$GLOBALS['phpgw_info']['user']['preferences']['calendar']['defaultcalendar']);
		$_page = $page_[0];
		if ($_page=='index' || ($_page != 'day' && $_page != 'week' && $_page != 'month' && $_page != 'year'))
		{
			$_page = 'month';
//			$GLOBALS['phpgw']->preferences->add('calendar','defaultcalendar','month');
//			$GLOBALS['phpgw']->preferences->save_repository();
		}

		if(!@file_exists(PHPGW_INCLUDE_ROOT.'/calendar/inc/hook_home_'.$_page.'.inc.php'))
		{
			$_page = 'day';
		}
		include(PHPGW_INCLUDE_ROOT.'/calendar/inc/hook_home_'.$_page.'.inc.php');
		

		$title = '<font color="#FFFFFF">'.lang('Calendar').'</font>';
		
		$portalbox = CreateObject('phpgwapi.listbox',
			Array(
				'title'	=> $title,
				'primary'	=> $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'secondary'	=> $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'tertiary'	=> $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'width'	=> '100%',
				'outerborderwidth'	=> '0',
				'header_background_image'	=> $GLOBALS['phpgw']->common->image('phpgwapi/templates/phpgw_website','bg_filler.gif')
			)
		);

		$app_id = $GLOBALS['phpgw']->applications->name2id('calendar');
		$GLOBALS['portal_order'][] = $app_id;
		$var = Array(
			'up'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'down'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'close'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'question'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id),
			'edit'	=> Array('url'	=> '/set_box.php', 'app'	=> $app_id)
		);

		while(list($key,$value) = each($var))
		{
			$portalbox->set_controls($key,$value);
		}

		$portalbox->data = Array();

		echo "\n".'<!-- BEGIN Calendar info -->'."\n".$portalbox->draw($GLOBALS['extra_data'])."\n".'<!-- END Calendar info -->'."\n";
		unset($cal);
	} 
	flush();
?>
