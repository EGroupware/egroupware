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
		'currentapp'		=>	'calendar',
		'enable_nextmatchs_class'	=>	True,
		'noheader'	=> True,
		'nonavbar'	=> True
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');
	if (! $keywords)
	{
		// If we reach this, it is because they didn't search for anything,
		// attempt to send them back to where they where.
		Header('Location: ' . $phpgw->link($from,'owner='.$owner.'&month='.$month.'&day='.$day.'&year='.$year));
	}
	else
	{
		$phpgw->common->phpgw_header();
		echo parse_navbar();
	}
	
	$error = '';

	if (strlen($keywords) == 0)
	{
		echo '<b>'.lang('Error').':</b>';
		echo lang('You must enter one or more search keywords.');
		$phpgw->common->phpgw_footer();
		$phpgw->common->phpgw_exit();
	}
	
	$matches = 0;

	$phpgw->calendar->set_filter();

	// There is currently a problem searching in with repeated events.
	// It spits back out the date it was entered.  I would like to to say that
	// it is a repeated event.
	$ids = array();
	$words = split(' ',$keywords);
	for ($i=0;$i<count($words);$i++)
	{
		$sql = "AND (UPPER(phpgw_cal.title) LIKE UPPER('%".$words[$i]."%') OR "
				. " UPPER(phpgw_cal.description) LIKE UPPER('%".$words[$i]."%')) ";

// Private
		if(strpos($phpgw->calendar->filter,'private'))
		{
			$sql .= "AND phpgw_cal.is_public=0 ";
		}
		
		$sql .= 'ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';

		$events = $phpgw->calendar->get_event_ids(True,$sql);

		if($events == False)
		{
			$matches = 0;
		}
		else
		{
			$cal_stream = $phpgw->calendar->open('INBOX',intval($owner),'');
			for($i=0;$i<count($events);$i++)
			{
				$event = $phpgw->calendar->fetch_event($cal_stream,$events[$i]);
				
				$datetime = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));
				
				$ids[strval($event->id)]++;
				$info[strval($event->id)] = $event->name.' ('
					. $phpgw->common->show_date($datetime).')';
			}
			$matches = count($events);
		}
	}

	if ($matches > 0)
	{
		$matches = count($ids);
	}

	if ($matches == 1)
	{
		$quantity = lang('1 match found').'.';
	}
	elseif ($matches > 0)
	{
		$quantity = lang('x matches found',$matches).'.';
	}
	else
	{
		echo '<b>'.lang('Error').':</b>';
		echo lang('no matches found.');
		$phpgw->common->phpgw_footer();
		$phpgw->common->phpgw_exit();
	}

	$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);
	$templates = Array(
		'search'		=>	'search.tpl',
		'search_list'	=>	'search_list.tpl',
	);
	$p->set_file($templates);

	$var = Array(
		'color'		=>	$phpgw_info['theme']['bg_text'],
		'search_text'	=>	lang('Search Results'),
		'quantity'	=>	$quantity
	);

	$p->set_var($var);

	// now sort by number of hits
	arsort($ids);
	for(reset($ids);$key=key($ids);next($ids))
	{
		$p->set_var('url_result',$phpgw->link('/calendar/view.php','id='.$key.'&owner='.$owner));
		$p->set_var('result_desc',$info[$key]);
		$p->parse('output','search_list',True);
	}
	
	$p->pparse('out','search');

	$phpgw->common->phpgw_footer();
?>
