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

	$phpgw_flags = Array (
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
	
	$view = 'day';

	$now	= $phpgw->calendar->makegmttime(0, 0, 0, $thismonth, $thisday, $thisyear);

	$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
	$template = Array(
		'day_t' => 'day.tpl'
	);

	$p->set_file($template);

//	$phpgw->template->set_block('day_t');

	if ($friendly)
	{
		$p->set_var('printer_friendly','<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">');
	}
	else
	{
		$p->set_var('printer_friendly','');
	}

	$p->set_var('bg_text',$phpgw_info['theme']['bg_text']);

	$m = mktime(0,0,0,$thismonth,1,$thisyear);
	$p->set_var('date',lang(date('F',$m)).' '.$thisday.', '.$thisyear);
	$p->set_var('username',$phpgw->common->grab_owner_name($owner));
	$p->set_var('daily_events',$phpgw->calendar->print_day_at_a_glance($now,$owner));
	$p->set_var('small_calendar',$phpgw->calendar->mini_calendar($thisday,$thismonth,$thisyear,'day.php'));

	if ($friendly == 0)
	{
		$param = 'year='.$thisyear.'&month='.$thismonth.'&day='.$thisday.'&friendly=1&filter='.$filter.'&owner='.$owner;
		$print = '<a href="'.$phpgw->link('',$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
	}
	else
	{
		$print =	'';
	}

	$p->set_var('print',$print);

	$p->pparse('out','day_t');
	$phpgw->common->phpgw_footer();
?>
