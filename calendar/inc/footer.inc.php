<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	if (isset($friendly) && $friendly)
	{
		$phpgw->common->phpgw_footer();
		$phpgw->common->phpgw_exit();
	}

	if(floor(phpversion()) == 4)
	{
		global $phpgw_info, $thisday, $thismonth, $thisyear;
	}

	$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);
	
	$templates = Array(
		'footer'	=>	'footer.tpl'
	);

	$p->set_file($templates);
	$p->set_block('footer','footer_table','footer_table');
	$p->set_block('footer','footer_row','footer_row');

	if ($phpgw->calendar->tempyear && $phpgw->calendar->tempmonth)
	{
		$m = $phpgw->calendar->tempmonth;
		$y = $phpgw->calendar->tempyear;
	}
	else
	{
		$m = date('m');
		$y = date('Y');
	}

	$d_time = mktime(0,0,0,$m,1,$y);
	$thisdate = date('Ymd', $d_time);
	$y--;

	$str = '';

	for ($i = 0; $i < 25; $i++)
	{
		$m++;
		if ($m > 12)
		{
			$m = 1;
			$y++;
		}
		$d = mktime(0,0,0,$m,1,$y);
		$str .= '<option value="' . date('Ymd', $d) . '"';
		if (date('Ymd', $d) == $thisdate)
		{
			$str .= ' selected';
		}
		$str .= '>'.lang(date('F', $d)).strftime(' %Y', $d).'</option>'."\n";
	}

	$var = Array(
		'action_url'		=>	$phpgw->link('/calendar/month.php','owner='.$owner),
		'form_name'			=>	'SelectMonth',
		'label'				=>	lang('Month'),
		'form_label'		=>	'date',
		'form_onchange'	=>	'document.SelectMonth.submit()',
		'row'					=>	$str,
		'go'					=>	lang('Go!')
	);

	$p->set_var($var);
	
	$p->parse('table_row','footer_row',True);

	$str = '';

	if ($phpgw->calendar->tempyear)
	{
		$y = $phpgw->calendar->tempyear;
	}
	else
	{
		$y = date('Y');
	}
	if ($phpgw->calendar->tempmonth)
	{
		$m = $phpgw->calendar->tempmonth;
	}
	else
	{
		$m = date('m');
	}
	if ($phpgw->calendar->tempday)
	{
		$d = $phpgw->calendar->tempday;
	}
	else
	{
		$d = date('d');
	}

//	if ($phpgw->calendar->tempyear && $phpgw->calendar->tempmonth)
//	{
//		$m = $phpgw->calendar->tempmonth;
//		$y = $phpgw->calendar->tempyear;
//	}
//	else
//	{
//		$m = date('m');
//		$y = date('Y');
//	}
//	
//	if ($thisday)
//	{
//		$d = $thisday;
//	}
//	else
//	{
//		$d = date ('d');
//	}
	unset($thisdate);
	
	$thisdate = $phpgw->calendar->datetime->makegmttime(0,0,0,$m,$d,$y);
	$sun = $phpgw->calendar->datetime->get_weekday_start($y,$m,$d) - $phpgw->calendar->datetime->tz_offset - 7200;

	$str = '';
	
	for ($i = -7; $i <= 7; $i++)
	{
		$begin = $sun + (3600 * 24 * 7 * $i);
		$end = $begin + (3600 * 24 * 6);
		$str .= '<option value="' . $phpgw->common->show_date($begin,'Ymd') . '"';
		if ($begin <= $thisdate['raw'] && $end >= $thisdate['raw'])
		{
			$str .= ' selected';
		}
		$str .= '>' . lang($phpgw->common->show_date($begin,'F')) . ' ' . $phpgw->common->show_date($begin,'d') . '-'
			. lang($phpgw->common->show_date($end,'F')) . ' ' . $phpgw->common->show_date($end,'d') . '</option>'."\n";
	}
 
	$var = Array(
		'action_url'		=>	$phpgw->link('/calendar/week.php','owner='.$owner),
		'form_name'			=>	'SelectWeek',
		'label'				=>	lang('Week'),
		'form_label'		=>	'date',
		'form_onchange'	=>	'document.SelectWeek.submit()',
		'row'					=>	$str,
		'go'					=>	lang('Go!')
	);

	$p->set_var($var);
	
	$p->parse('table_row','footer_row',True);

	if ($phpgw->calendar->tempyear)
	{
		$y = $phpgw->calendar->tempyear;
	}
	else
	{
		$y = date('Y');
	}
	$str = '';
	for ($i = ($y - 3); $i < ($y + 3); $i++)
	{
		$str .= '<option value="'.$i.'"';
		if ($i == $y)
		{
			$str .= ' selected';
		}
		$str .= '>'.$i.'</option>'."\n";
	}
  
	$var = Array(
		'action_url'		=>	$phpgw->link('/calendar/year.php','owner='.$owner),
		'form_name'			=>	'SelectYear',
		'label'				=>	lang('Year'),
		'form_label'		=>	'year',
		'form_onchange'	=>	'document.SelectYear.submit()',
		'row'					=>	$str,
		'go'					=>	lang('Go!')
	);

	$p->set_var($var);
	
	$p->parse('table_row','footer_row',True);

	$p->pparse('out','footer_table');
?>

