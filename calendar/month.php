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
			'currentapp'			=> 'calendar',
			'enable_nextmatchs_class'	=> True,
			'noheader'			=> True,
			'nonavbar'			=> True,
			'noappheader'			=> True,
			'noappfooter'			=> True,
			'nofooter'			=> True
		);
	}
	else
	{
		$phpgw_flags = Array(
			'currentapp'			=> 'calendar',
			'enable_nextmatchs_class'	=> True
		);
		
		$friendly = 0;
	}

	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	$view = 'month';

	$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);

	$templates = Array(
		'index_t'	=>	'index.tpl'
	);
	
	$p->set_file($templates);

	$m = mktime(0,0,0,$thismonth,1,$thisyear);

	if ($friendly == 0)
	{
		$printer = '';
		$param = 'year='.$thisyear.'&month='.$thismonth.'&friendly=1&filter='.$filter.'&owner='.$owner;
		$print = '<a href="'.$phpgw->link('/calendar/month.php',$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
	}
	else
	{
		$printer = '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
		$print =	'';
	}

	$var = Array(
		'printer_friendly'		=>	$printer,
		'bg_text'					=> $phpgw_info['themem']['bg_text'],
		'small_calendar_prev'	=>	$phpgw->calendar->mini_calendar(1,$thismonth - 1,$thisyear,'day.php'),
		'month_identifier'		=>	lang(strftime("%B",$m)) . ' ' . $thisyear,
		'username'					=>	$phpgw->common->grab_owner_name($owner),
		'small_calendar_next'	=>	$phpgw->calendar->mini_calendar(1,$thismonth + 1,$thisyear,'day.php'),
		'large_month'				=>	$phpgw->calendar->display_large_month($thismonth,$thisyear,True,$owner),
		'print'						=>	$print
	);

	$p->set_var($var);
	$p->pparse('out','index_t');
	$phpgw->common->phpgw_footer();
?>
