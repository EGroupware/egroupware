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
  						'currentapp'				=> 'calendar',
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

  $now	= $phpgw->calendar->splitdate(mktime (0, 0, 0, $thismonth, $thisday, $thisyear) - ((60 * 60) * $phpgw_info['user']['preferences']['common']['tz_offset']));

  $template = Array(
					'day_t' => 'day.tpl'
  );

  $phpgw->template->set_file($template);

  //$phpgw->template->set_block('day_t');

  if ($friendly)
  {
    $phpgw->template->set_var('printer_friendly','<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">');
  }
  else
  {
    $phpgw->template->set_var('printer_friendly','');
  }

  $phpgw->template->set_var('bg_text',$phpgw_info['theme']['bg_text']);

  $m = mktime(2,0,0,$thismonth,1,$thisyear);
  $phpgw->template->set_var('date',lang(date('F',$m)).' '.$thisday.', '.$thisyear);
  $phpgw->template->set_var('username',$phpgw->common->grab_owner_name($owner));
  $phpgw->template->set_var('daily_events',$phpgw->calendar->print_day_at_a_glance($now,$owner));
  $phpgw->template->set_var('small_calendar',$phpgw->calendar->mini_calendar($now['day'],$now['month'],$now['year'],'day.php'));

  if (!$friendly)
  {
    $param = 'year='.$thisyear.'&month='.$thismonth.'&day='.$thisday.'&friendly=1&filter='.$filter.'&owner='.$owner;
    $phpgw->template->set_var('print','<a href="'.$phpgw->link('',$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</A>');
    $phpgw->template->pparse('out','day_t');
  }
  else
  {
    $phpgw->template->set_var('print','');
    $phpgw->template->pparse('out','day_t');
  }

  $phpgw->common->phpgw_footer();
?>
