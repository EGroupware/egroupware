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
		$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
		exit;
	}
	unset($d1);

	if ($GLOBALS['phpgw_info']['user']['preferences']['calendar']['mainscreen_showevents'])
	{
		$GLOBALS['phpgw']->translation->add_app('calendar');
		if(!is_object($GLOBALS['phpgw']->datetime))
		{
			$GLOBALS['phpgw']->datetime = CreateObject('phpgwapi.datetime');
		}

		$GLOBALS['date'] = date('Ymd',$GLOBALS['phpgw']->datetime->users_localtime);
		$GLOBALS['g_year'] = substr($GLOBALS['date'],0,4);
		$GLOBALS['g_month'] = substr($GLOBALS['date'],4,2);
		$GLOBALS['g_day'] = substr($GLOBALS['date'],6,2);
		$GLOBALS['owner'] = $GLOBALS['phpgw_info']['user']['account_id'];

		$page_ = explode('.',$GLOBALS['phpgw_info']['user']['preferences']['calendar']['defaultcalendar']);
		$_page = substr($page_[0],0,7);	// makes planner from planner_{user|category}
		if ($_page=='index' || ($_page != 'day' && $_page != 'week' && $_page != 'month' && $_page != 'year' && $_page != 'planner'))
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
		
		$app_id = $GLOBALS['phpgw']->applications->name2id('calendar');
		$GLOBALS['portal_order'][] = $app_id;

		$GLOBALS['phpgw']->portalbox->set_params(array('app_id'	=> $app_id,
														'title'	=> lang('calendar')));
		$GLOBALS['phpgw']->portalbox->draw($GLOBALS['extra_data']);
	}
?>
