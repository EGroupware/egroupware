<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	if (isset($friendly) && $friendly)
	{
		$phpgw_flags = Array(
			'currentapp'					=>	'calendar',
			'enable_nextmatchs_class'	=>	True,
			'noheader'						=>	True,
			'nonavbar'						=>	True,
			'noappheader'					=>	True,
			'noappfooter'					=>	True,
			'nofooter'						=>	True
		);
	}
	else
	{
		$phpgw_flags = Array(
			'currentapp'					=>	'calendar',
			'enable_nextmatchs_class'	=>	True
		);
		
		$friendly = 0;
	}

	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');
	
	$view = 'day';

	$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
	
	$template = Array(
		'day_t' => 'day.tpl'
	);

	$p->set_file($template);

	if ($friendly == 0)
	{
		$printer = '';
		$param = 'year='.$thisyear.'&month='.$thismonth.'&day='.$thisday.'&friendly=1&filter='.$filter.'&owner='.$owner;
		$print = '<a href="'.$phpgw->link('/calendar/day.php',$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
		$minical = $phpgw->calendar->mini_calendar($thisday,$thismonth,$thisyear,'day.php');
	}
	else
	{
		$printer = '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
		$print =	'';
		if($phpgw_info['user']['preferences']['calendar']['display_minicals'] == 'Y' || $phpgw_info['user']['preferences']['calendar']['display_minicals'])
		{
			$minical = $phpgw->calendar->mini_calendar($thisday,$thismonth,$thisyear,'day.php');
		}
		else
		{
			$minical = '';
		}
	}

	$now	= $phpgw->calendar->makegmttime(0, 0, 0, $thismonth, $thisday, $thisyear);
	$now['raw'] += $phpgw->calendar->tz_offset;
	$m = mktime(0,0,0,$thismonth,1,$thisyear);
	
	$var = Array(
		'printer_friendly'		=>	$printer,
		'bg_text'					=> $phpgw_info['themem']['bg_text'],
		'daily_events'				=>	$phpgw->calendar->print_day_at_a_glance($now),
		'small_calendar'			=>	$minical,
		'date'						=>	lang(date('F',$m)).' '.$thisday.', '.$thisyear,
		'username'					=>	$phpgw->common->grab_owner_name($owner),
		'print'						=>	$print
	);

	$p->set_var($var);

	$p->pparse('out','day_t');
	if(!isset($friendly) || $friendly == False)
	{
		$phpgw->common->phpgw_footer();
	}
	else
	{
		$phpgw->common->phpgw_exit();
	}
?>
