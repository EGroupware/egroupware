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

	$cal_info = CreateObject('calendar.calendar_item');

	function validate($cal_info)
	{
		$error = 0;
		// do a little form verifying
		if ($cal_info->name == '')
		{
			$error = 40;
		}
		elseif (($cal_info->hour < 0 || $cal_info->hour > 23) || ($cal_info->end_hour < 0 || $cal_info->end_hour > 23))
		{
			$error = 41;
		}
		elseif (($cal_info->minute < 0 || $cal_info->minute > 59) || ($cal_info->end_minute < 0 || $cal_info->minute > 59))
		{
			$error = 41;
		}
		elseif(!checkdate($cal_info->month,$cal_info->day,$cal_info->year) || !checkdate($cal_info->end_month,$cal_info->end_day,$cal_info->end_year))
		{
			$error = 42;
		}
		elseif (($cal_info->year == $cal_info->end_year) && ($cal_info->month == $cal_info->end_month) && ($cal_info->day == $cal_info->end_day))
		{
			if ($cal_info->hour > $cal_info->end_hour)
			{
				$error = 42;
			}
			elseif (($cal_info->hour == $cal_info->end_hour) && ($cal_info->minute > $cal_info->end_minute))
			{
				$error = 42;
			}
		}
		elseif (($cal_info->year == $cal_info->end_year) && ($cal_info->month == $cal_info->end_month) && ($cal_info->day > $cal_info->end_day))
		{
			$error = 42;
		}
		elseif (($cal_info->year == $cal_info->end_year) && ($cal_info->month > $cal_info->end_month))
		{
			$error = 42;
		}
		elseif ($cal_info->year > $cal_info->end_year)
		{
			$error = 42;
		}
		
		return $error;
	}

	if(!isset($readsess))
	{
		for(reset($cal);$key=key($cal);next($cal))
		{
			$data = $cal[$key];
			$cal_info->set($key,$data);
		}

		$cal_info->owner=$owner;

		$parts = $cal_info->participants;
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

		$cal_info->participants = Array();
		while($parts = each($part))
		{
			$cal_info->participants[] = $parts[0];
		}
		
		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
		{
			if ($cal_info->ampm == 'pm')
			{
				if ($cal_info->hour <> 12)
				{
					$cal_info->hour += 12;
				}
			}
			elseif ($cal_info->ampm == 'am')
			{
				if ($cal_info->hour == 12)
				{
					$cal_info->hour -= 12;
				}
			}
			
			if ($cal_info->end_ampm == 'pm')
			{
				if ($cal_info->end_hour <> 12)
				{
					$cal_info->end_hour += 12;
				}
			}
			elseif ($cal_info->end_ampm == 'am')
			{
				if ($cal_info->end_hour == 12)
				{
					$cal_info->end_hour -= 12;
				}
			}
		}
		$datetime	=	$phpgw->calendar->makegmttime($cal_info->hour,$cal_info->minute,0,$cal_info->month,$cal_info->day,$cal_info->year);
		$cal_info->datetime	= $datetime['raw'];
		$datetime	=	$phpgw->calendar->makegmttime($cal_info->end_hour,$cal_info->end_minute,0,$cal_info->end_month,$cal_info->end_day,$cal_info->end_year);
		$cal_info->edatetime	= $datetime['raw'];
		$datetime	=	$phpgw->calendar->makegmttime(0,0,0,$cal_info->rpt_month,$cal_info->rpt_day,$cal_info->rpt_year);
		$cal_info->rpt_end	= $datetime['raw'];

		$phpgw->session->appsession('entry','calendar',$cal_info);
		$datetime_check = validate($cal_info);
		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
		{
			if ($cal_info->hour >= 12)
			{
				$cal_info->ampm = '';
			}
			
			if ($cal_info->end_hour >= 12)
			{
				$cal_info->end_ampm = '';
			}
		}
		
		$tz_offset = intval(((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset'])));
		
		$cal_info->datetime += $tz_offset;
		$cal_info->edatetime += $tz_offset;
		$overlapping_events = $phpgw->calendar->overlap($cal_info->datetime,$cal_info->edatetime,$cal_info->participants,$cal_info->owner,$cal_info->id);
	}
	else
	{
		$data = $phpgw->session->appsession('entry','calendar');
		$cal_info = unserialize($data);
	}

	if($datetime_check)
	{
		Header('Location: '.$phpgw->link('edit_entry.php','readsess='.$cal_info->id.'&cd='.$datetime_check));
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

		$calendar_overlaps = $phpgw->calendar->getevent($overlapping_events);

		$format = $phpgw_info['user']['preferences']['common']['dateformat'] . ' - ';
		
		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
		{
			$format .= 'h:i:s a';
		}
		else
		{
			$format .= 'H:i:s';
		}

		$overlap = '';
		for($i=0;$i<count($calendar_overlaps);$i++)
		{
			$cal_over = $calendar_overlaps[$i];
			if($cal_over)
			{
				$overlap .= '<li>';
				$private = $phpgw->calendar->is_private($cal_over,$cal_over->owner);
				
				if(strtoupper($private) == 'PRIVATE')
				{
					$overlap .= '(PRIVATE)';
				}
				else
				{
					$overlap .= $phpgw->calendar->link_to_entry($cal_over->id,'circle.gif',$cal_over->description).$cal_over->name;
				}
				
				$overlap .= ' ('.$phpgw->common->show_date($cal_over->datetime).' - '.$phpgw->common->show_date($cal_over->edatetime).')<br>';
			}
		}
		if(strlen($overlap))
		{
			$var = Array(
								'overlap_text'	=>	lang('Your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:',date($format,$cal_info->datetime),date($format,$cal_info->edatetime)),
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

		$var = Array(
							'action_url_button'		=>	$phpgw->link('','readsess='.$cal_info->id),
							'action_text_button'		=>	lang('Ignore Conflict'),
							'action_confirm_button'	=>	''
		);
		$p->set_var($var);

		$p->parse('resubmit_button','form_button');

		$var = Array(
							'action_url_button'		=>	$phpgw->link('edit_entry.php','readsess='.$cal_info->id),
							'action_text_button'		=>	lang('Re-Edit Event'),
							'action_confirm_button'	=>	''
		);
		$p->set_var($var);

		$p->parse('reedit_button','form_button');

		$p->pparse('out','overlap');
		
	}
	else
	{
		$phpgw->calendar->add($cal_info,$cal_info->id);
		Header('Location: '.$phpgw->link('index.php','year='.$year.'&month='.$month.'&cd=14&owner='.$owner));
	}
	$phpgw->common->phpgw_footer();
?>
