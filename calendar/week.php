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

	$next = $phpgw->calendar->makegmttime(0,0,0,$thismonth,$thisday + 7,$thisyear);
	$prev = $phpgw->calendar->makegmttime(0,0,0,$thismonth,$thisday - 7,$thisyear);

	$nextmonth = $phpgw->calendar->makegmttime(0,0,0,$thismonth + 1,1,$thisyear);
	$prevmonth = $phpgw->calendar->makegmttime(0,0,0,$thismonth - 1,1,$thisyear);

	$first = $phpgw->calendar->gmtdate($phpgw->calendar->get_weekday_start($thisyear, $thismonth, $thisday));
	$last = $phpgw->calendar->gmtdate($first['raw'] + 518400);

// Week Label
	$week_id = lang(strftime("%B",$first['raw'])).' '.$first['day'];
	if($first['month'] <> $last['month'] && $first['year'] <> $last['year'])
	{
		$week_id .= ', '.$first['year'];
	}
	$week_id .= ' - ';
	if($first['month'] <> $last['month'])
	{
		$week_id .= lang(strftime("%B",$last['raw'])).' ';
	}
	$week_id .= $last['day'].', '.$last['year'];

	$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);
	$templates = Array(
		'week_t' => 'week.tpl'
	);
	
	$p->set_file($templates);

	if ($friendly == 0)
	{
		$printer = '';
		$prev_week_link = '<a href="'.$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/week.php','year='.$prev['year'].'&month='.$prev['month'].'&day='.$prev['day']).'">&lt;&lt;</a>';
		$next_week_link = '<a href="'.$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/week.php','year='.$next['year'].'&month='.$next['month'].'&day='.$next['day']).'">&gt;&gt;</a>';
		$param = 'year='.$thisyear.'&month='.$thismonth.'&day='.$thisday.'&friendly=1&filter='.$filter.'&owner='.$owner;
		$print = '<a href="'.$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/week.php',$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
	}
	else
	{
		$printer = '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
		$prev_week_link = '&lt;&lt;';
		$next_week_link = '&gt;&gt;';
		$print =	'';
	}

	$var = Array(
		'printer_friendly'		=>	$printer,
		'bg_text'					=> $phpgw_info['themem']['bg_text'],
		'small_calendar_prev'	=>	$phpgw->calendar->mini_calendar(1,$thismonth - 1,$thisyear,'day.php'),
		'prev_week_link'			=>	$prev_week_link,
		'small_calendar_this'	=>	$phpgw->calendar->mini_calendar($thisday,$thismonth,$thisyear,'day.php'),
		'week_identifier'			=>	$week_id,
		'next_week_link'			=>	$next_week_link,
		'username'					=>	$phpgw->common->grab_owner_name($owner),
		'small_calendar_next'	=>	$phpgw->calendar->mini_calendar(1,$thismonth + 1,$thisyear,'day.php'),
		'week_display'				=>	$phpgw->calendar->display_large_week($thisday,$thismonth,$thisyear,true,$owner),
		'print'						=>	$print
	);

	$p->set_var($var);
	$p->pparse('out','week_t');
	$phpgw->common->phpgw_footer();
?>
