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
								'currentapp'					=>	'calendar',
								'noheader'						=> True,
								'nonavbar'						=> True,
								'enable_nextmatchs_class'	=> True,
								'noappheader'					=>	True,
								'noappfooter'					=>	True
	);
	
	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	$event = CreateObject('calendar.calendar_item');

	function validate($event)
	{
		global $phpgw;
		
		$error = 0;
		// do a little form verifying
		if ($event->title == '')
		{
			$error = 40;
		}
		elseif (($phpgw->calendar->time_valid($event->start->hour,$event->start->min,0) == False) || ($phpgw->calendar->time_valid($event->end->hour,$event->end->min,0) == False))
		{
			$error = 41;
		}
		elseif (($phpgw->calendar->date_valid($event->start->year,$event->start->month,$event->start->mday) == False) || ($phpgw->calendar->date_valid($event->end->year,$event->end->month,$event->end->mday) == False) || ($phpgw->calendar->date_compare($event->start->year,$event->start->month,$event->start->mday,$event->end->year,$event->end->month,$event->end->mday) == 1))
		{
			$error = 42;
		}
		elseif ($phpgw->calendar->date_compare($event->start->year,$event->start->month,$event->start->mday,$event->end->year,$event->end->month,$event->end->mday) == 0)
		{
			if ($phpgw->calendar->time_compare($event->start->hour,$event->start->min,0,$event->end->hour,$event->end->min,0) == 1)
			{
				$error = 42;
			}
		}
		
		return $error;
	}

	if(!isset($readsess))
	{
		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
		{
			if ($start[ampm] == 'pm')
			{
				if ($start[hour] <> 12)
				{
					$start[hour] += 12;
				}
			}
			elseif ($start[ampm] == 'am')
			{
				if ($start[hour] == 12)
				{
					$start[hour] -= 12;
				}
			}
			
			if($start[hour] > 24)
			{
				$start[hour] -= 12;
			}
			
			if ($end[ampm] == 'pm')
			{
				if ($end[hour] <> 12)
				{
					$end[hour] += 12;
				}
			}
			elseif ($end[ampm] == 'am')
			{
				if ($end[hour] == 12)
				{
					$end[hour] -= 12;
				}
			}
			
			if($end[hour] > 24)
			{
				$end[hour] -= 12;
			}
		}

		if(!isset($private))
		{
			$private = 'public';
		}

		$cal_stream = $phpgw->calendar->open('INBOX',intval($owner),'');
		$phpgw->calendar->event_init($cal_stream);
		$phpgw->calendar->event_set_category($cal_stream,$category);
		$phpgw->calendar->event_set_title($cal_stream,$title);
		$phpgw->calendar->event_set_description($cal_stream,$description);
		$phpgw->calendar->event_set_start($cal_stream,$start[year],$start[month],$start[mday],$start[hour],$start[min],0);
		$phpgw->calendar->event_set_end($cal_stream,$end[year],$end[month],$end[mday],$end[hour],$end[min],0);
		$phpgw->calendar->event_set_class($cal_stream,($private == 'public'));

		if($id != 0)
		{
			$phpgw->calendar->event->id = $id;
		}

		switch($recur_type)
		{
			case RECUR_NONE:
				$phpgw->calendar->event_set_recur_none($cal_stream);
				break;
			case RECUR_DAILY:
				$phpgw->calendar->event_set_recur_daily($cal_stream,$recur_enddate[year],$recur_enddate[month],$recur_enddate[mday],$recur_interval);
				break;
			case RECUR_WEEKLY:
				$phpgw->calendar->event_set_recur_weekly($cal_stream,$recur_enddate[year],$recur_enddate[month],$recur_enddate[mday],$recur_interval,$recur_data);
				break;
			case RECUR_MONTHLY_MDAY:
				$phpgw->calendar->event_set_recur_monthly_mday($cal_stream,$recur_enddate[year],$recur_enddate[month],$recur_enddate[mday],$recur_interval);
				break;
			case RECUR_MONTHLY_WDAY:
				$phpgw->calendar->event_set_recur_monthly_wday($cal_stream,$recur_enddate[year],$recur_enddate[month],$recur_enddate[mday],$recur_interval);
				break;
			case RECUR_YEARLY:
				$phpgw->calendar->event_set_recur_yearly($cal_stream,$recur_enddate[year],$recur_enddate[month],$recur_enddate[mday],$recur_interval);
				break;
		}

		$parts = $participants;
		$part = Array();
		for($i=0;$i<count($parts);$i++)
		{
			$acct_type = $phpgw->accounts->get_type(intval($parts[$i]));
			if($acct_type == 'u')
			{
				$part[$parts[$i]] = 1;
			}
			elseif($acct_type == 'g')
			{
				$acct = CreateObject('phpgwapi.accounts',intval($parts[$i]));
				$members = $acct->members(intval($parts[$i]));
				unset($acct);
				if($members == False)
				{
					continue;
				}
				while($member = each($members))
				{
					$part[$member[1]['account_id']] = 1;
				}
			}
		}

		$participants = Array();
		while($parts = each($part))
		{
			$participants[] = $parts[0];
		}

		$phpgw->calendar->event_set_participants($cal_stream,$participants);

		$phpgw->calendar->event->priority = intval($priority);
		$event = $phpgw->calendar->event;

		$phpgw->session->appsession('entry','calendar',$event);
		
		$datetime_check = validate($event);
		
		$tz_offset = ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));
		$start = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $tz_offset;
		$end = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $tz_offset;

		$overlapping_events = $phpgw->calendar->overlap($start,$end,$event->participants,$event->owner,$event->id);
	}
	else
	{
		$cal_stream = $phpgw->calendar->open('INBOX',intval($owner),'');
		$phpgw->calendar->event_init($cal_stream);
		
		$event = unserialize(str_replace('O:8:"stdClass"','O:13:"calendar_time"',serialize($phpgw->session->appsession('entry','calendar'))));
		$phpgw->calendar->event = $event;
		$datetime_check = validate($event);
	}

	if($datetime_check)
	{
		Header('Location: '.$phpgw->link('/calendar/edit_entry.php','readsess='.$event->id.'&cd='.$datetime_check));
		$phpgw->common->phpgw_exit();
	}
	elseif($overlapping_events)
	{
		$phpgw->common->phpgw_header();
		echo parse_navbar();

		$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
		$templates = Array(
									'overlap'		=>	'overlap.tpl',
									'form_button'	=>	'form_button_script.tpl'
		);
		$p->set_file($templates);

		$p->set_var('color',$phpgw_info['theme']['bg_text']);

		$cal_stream = $phpgw->calendar->open('INBOX',intval($owner),'');
		$overlap = '';
		for($i=0;$i<count($overlapping_events);$i++)
		{
			$over = $phpgw->calendar->fetch_event($cal_stream,$overlapping_events[$i]);
			$overlap .= '<li>';
			$private = $phpgw->calendar->is_private($over,$over->owner,'title');

			if(strtoupper($private) == 'PRIVATE')
			{
				$overlap .= '(PRIVATE)';
			}
			else
			{
				$overlap .= $phpgw->calendar->link_to_entry($over->id,'circle.gif',$over->description).$over->title;
			}

			$over_start = mktime($over->start->hour,$over->start->min,$over->start->sec,$over->start->month,$over->start->mday,$over->start->year) - $tz_offset;
			$over_end = mktime($over->end->hour,$over->end->min,$over->end->sec,$over->end->month,$over->end->mday,$over->end->year) - $tz_offset;
			$overlap .= ' ('.$phpgw->common->show_date($over_start).' - '.$phpgw->common->show_date($over_end).')<br>';
		}
		if(strlen($overlap) > 0)
		{
			$var = Array(
								'overlap_text'	=>	lang('Your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:',$phpgw->common->show_date($start),$phpgw->common->show_date($end)),
								'overlap_list'	=>	$overlap
			);
		}
		else
		{
			$var = Array(
								'overlap_text'	=>	'',
								'overlap_list'	=>	''
			);
		}

		$p->set_var($var);

		$phpgw->calendar->event = $event;

		$var = Array(
							'action_url_button'		=>	$phpgw->link('/calendar/edit_entry_handler.php','readsess='.$event->id.'&year='.$event->start->year.'&month='.$event->start->month.'&day='.$event->start->mday),
							'action_text_button'		=>	lang('Ignore Conflict'),
							'action_confirm_button'	=>	''
		);
		$p->set_var($var);

		$p->parse('resubmit_button','form_button');

		$var = Array(
							'action_url_button'		=>	$phpgw->link('/calendar/edit_entry.php','readsess='.$event->id.'&year='.$event->start->year.'&month='.$event->start->month.'&day='.$event->start->mday),
							'action_text_button'		=>	lang('Re-Edit Event'),
							'action_confirm_button'	=>	''
		);
		$p->set_var($var);

		$p->parse('reedit_button','form_button');

		$p->pparse('out','overlap');
		
	}
	else
	{
		$cal_stream = $phpgw->calendar->open('INBOX',intval($owner),'');
		$phpgw->calendar->store_event($cal_stream);
		Header('Location: '.$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/index.php','year='.$event->start->year.'&month='.$event->start->month.'&day='.$event->start->mday.'&cd=14&owner='.$owner));
	}
	$phpgw->common->phpgw_footer();
?>
