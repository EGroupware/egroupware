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

	$phpgw_flags = Array(
									'currentapp'					=> 'calendar',
									'enable_nextmatchs_class'	=> True
	);
	
	$phpgw_info['flags'] = $phpgw_flags;

	if (isset($friendly) && $friendly)
	{
		$phpgw_info['flags']['noheader'] = True;
		$phpgw_info['flags']['nonavbar'] = True;
		$phpgw_info['flags']['noappheader'] = True;
		$phpgw_info['flags']['noappfooter'] = True;
		$phpgw_info['flags']['nofooter'] = True;
	}
	else
	{
		$friendly = 0;
	}
	
	include('../header.inc.php');

	$next = $phpgw->calendar->makegmttime(0,0,0,$thismonth,$thisday + 7,$thisyear);
	$prev = $phpgw->calendar->makegmttime(0,0,0,$thismonth,$thisday - 7,$thisyear);

	$nextmonth = $phpgw->calendar->makegmttime(0,0,0,$thismonth + 1,1,$thisyear);
	$prevmonth = $phpgw->calendar->makegmttime(0,0,0,$thismonth - 1,1,$thisyear);

	$first = $phpgw->calendar->gmtdate($phpgw->calendar->get_weekday_start($thisyear, $thismonth, $thisday));
	$last = $phpgw->calendar->gmtdate($first['raw'] + 518400);

	$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
	$templates = Array(
								'week_t' => 'week.tpl'
	);
	$p->set_file($templates);

	$p->set_block('week_t','week');

	if ($friendly)
	{
		$p->set_var('printer_friendly','<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">');
	}
	else
	{
		$p->set_var('printer_friendly','');
	}

	$p->set_var('bg_text',$phpgw_info['theme']['bg_text']);

	$p->set_var('small_calendar_prev',$phpgw->calendar->mini_calendar($thisday,$thismonth - 1,$thisyear,'day.php'));
	
	if (!$friendly)
	{
		$p->set_var('prev_week_link','<a href="'.$phpgw->link('week.php','year='.$prev['year'].'&month='.$prev['month'].'&day='.$prev['day']).'">&lt;&lt;</a>');
	}
	else
	{
		$p->set_var('prev_week_link','&lt;&lt;');
	}
	
	$p->set_var('small_calendar_this',$phpgw->calendar->mini_calendar($thisday,$thismonth,$thisyear,'day.php'));

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

	$p->set_var('week_identifier',$week_id);
	
	$p->set_var('username',$phpgw->common->grab_owner_name($owner));

	if (!$friendly)
	{
		$p->set_var('next_week_link','<a href="'.$phpgw->link('week.php','year='.$next['year'].'&month='.$next['month'].'&day='.$next['day']).'">&gt;&gt;</a>');
	}
	else
	{
		$p->set_var('next_week_link','&gt;&gt;');
	}
	
	$p->set_var('small_calendar_next',$phpgw->calendar->mini_calendar($thisday,$$thismonth + 1,$thisyear,'day.php'));
	
	$p->set_var('week_display',$phpgw->calendar->display_large_week($thisday,$thismonth,$thisyear,true,$owner));

	if (!$friendly)
	{
		$param = 'year='.$thisyear.'&month='.$thismonth.'&friendly=1&filter='.$filter;
		$p->set_var('print','<a href="'.$phpgw->link('',$param)
					.'" TARGET="cal_printer_friendly" onMouseOver="window.'
					. "status = '" . lang('Generate printer-friendly version')
					. "'\">[". lang('Printer Friendly') . ']</A>');
		$p->parse('out','week_t');
		$p->pparse('out','week_t');
	}
	else
	{
		$p->set_var('print','');
		$p->parse('out','week_t');
		$p->pparse('out','week_t');
	}
	$phpgw->common->phpgw_footer();
?>
