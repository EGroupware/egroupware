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

	$phpgw_flags = array(
  		'currentapp'		=> 'calendar',
  		'enable_nextmatchs_class'	=> True
	);
	$phpgw_info['flags'] = $phpgw_flags;
  
	include('../header.inc.php');

	if(! ($rights & PHPGW_ACL_READ))
	{
		echo lang('You do not have permission to read this record!');
		$phpgw->common->phpgw_footer();
		$phpgw->common->phpgw_exit();    
	}

	if ($id < 1)
	{
		echo lang('Invalid entry id.');
		$phpgw->common->phpgw_footer();
		$phpgw->common->phpgw_exit();
	}

	function add_day(&$repeat_days,$day)
	{
		if($repeat_days)
		{
			$repeat_days .= ', ';
		}
		$repeat_days .= $day;
	}

	function display_item($field,$data)
	{
		global $phpgw, $p;

		$p->set_var('field',$field);
		$p->set_var('data',$data);
		$p->parse('output','list',True);
	}

	$pri = Array(
  		1	=> lang('Low'),
  		2	=> lang('Normal'),
  		3	=> lang('High')
	);

	$db = $phpgw->db;

	$unapproved = FALSE;

	$cal_stream = $phpgw->calendar->open('INBOX',$owner,'');
	$event = $phpgw->calendar->fetch_event($cal_stream,$id);

	reset($event->participants);
	$participating = False;
	for($j=0;$j<count($event->participants);$j++)
	{
		if($event->participants[$j] == $owner)
		{
			$participating = True;
		}
	}
  
	if($participating == False)
	{
		echo lang('You do not have permission to read this record.');
		$phpgw->common->phpgw_exit();
	}
  
//	$description = nl2br($event->description);

	$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);

	$templates = Array(
  		'view_begin'	=> 'view.tpl',
  		'list'			=> 'list.tpl',
  		'view_end'		=> 'view.tpl',
  		'form_button'	=> 'form_button_script.tpl'
	);
	$p->set_file($templates);

	$var = Array(
		'bg_text'	=>	$phpgw_info['theme']['bg_text'],
		'name'	=>	$event->name
	);
	$p->set_var($var);
	$p->parse('out','view_begin');

	// Some browser add a \n when its entered in the database. Not a big deal
	// this will be printed even though its not needed.
	if (nl2br($event->description))
	{
		display_item(lang('Description'),nl2br($event->description));
	}

	$tz_offset = ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));

	$start = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $tz_offset;
	display_item(lang('Start Date/Time'),$phpgw->common->show_date($start));

	// save date so the trailer links are for the same time period
	$thisyear	= $cal_info->start->year;
	$thismonth	= $cal_info->start->month;
	$thisday 	= $cal_info->start->mday;

	$end = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $tz_offset;
	display_item(lang('End Date/Time'),$phpgw->common->show_date($end));

	display_item(lang('Priority'),$pri[$event->priority]);

	$participate = False;
	for($i=0;$i<count($event->participants);$i++)
	{
		if($event->participants[$i] == $phpgw_info['user']['account_id'])
		{
			$participate = True;
		}
	}
	if($event->owner == $phpgw_info['user']['account_id'] && $participate)
	{
		display_item(lang('Created by'),'<a href="'
			.$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/viewmatrix.php','participants='.$event->owner.'&month='.$event->start->month.'&day='.$event->start->mday.'&year='.$event->start->year.'&matrixtype=free/busy&owner='.$owner)
			.'">'.$phpgw->common->grab_owner_name($event->owner).'</a>');
	}
	else
	{
		display_item(lang('Created by'),$phpgw->common->grab_owner_name($event->owner));
	}

	display_item(lang('Updated'),$phpgw->common->show_date($event->mdatetime));

	if($event->groups[0])
	{
		$cal_grps = '';
		for($i=0;$i<count($event->groups);$i++)
		{
			if($i>0)
			{
				$cal_grps .= '<br>';
			}
			$cal_grps .= $phpgw->accounts->id2name($event->groups[$i]);
		}
		display_item(lang('Groups'),$cal_grps);
	}

	$str = '';
	for($i=0;$i<count($event->participants);$i++)
	{
		if($i)
		{
			$str .= '<br>';
		}
		switch ($event->status[$i])
		{
			case 'A':
				$status = 'Accepted';
				break;
			case 'R':
				$status = 'Rejected';
				break;
			case 'P':
				$status = 'Pending';
				break;
			case 'U':
				$status = 'No Repsonse';
				break;
		}
		$str .= $phpgw->common->grab_owner_name($event->participants[$i]).' ('.$status.')';
	}
	display_item(lang('Participants'),$str);

// Repeated Events
	$str = $event->rpt_type;
	if($event->recur_type <> RECUR_NONE || ($event->recur_enddate->mday != 0 && $event->recur_enddate->month != 0 && $event->recur_enddate->year != 0))
	{
		$str .= ' (';
		$recur_end = mktime(0,0,0,$event->recur_enddate->month,$event->recur_enddate->mday,$event->recur_enddate->year);
		if($recur_end != 0)
		{
			$str .= lang('ends').': '.$phpgw->common->show_date($recur_end,'l, F d, Y').' ';
		}
		if($event->recur_type == RECUR_WEEKLY || $event->recur_type == RECUR_DAILY)
		{
			$repeat_days = '';
			if ($event->recur_data & M_SUNDAY)
			{
				add_day($repeat_days,lang('Sunday '));
			}
			if ($event->recur_data & M_MONDAY)
			{
				add_day($repeat_days,lang('Monday '));
			}
			if ($event->recur_data & M_TUESDAY)
			{
				add_day($repeat_days,lang('Tuesay '));
			}
			if ($event->recur_data & M_WEDNESDAY)
			{
				add_day($repeat_days,lang('Wednesday '));
			}
			if ($event->recur_data & M_THURSDAY)
			{
				add_day($repeat_days,lang('Thursday '));
			}
			if ($event->recur_data & M_FRIDAY)
			{
				add_day($repeat_days,lang('Friday '));
			}
			if ($event->recur_data & M_SATURDAY)
			{
				add_day($repeat_days,lang('Saturday '));
			}
			$str .= lang('days repeated').': '.$repeat_days;
		}
		if($event->recur_interval)
		{
			$str .= lang('frequency').' '.$event->recur_interval;
		}
		$str .= ')';

		display_item(lang('Repetition'),$str);
	}

	if (($event->owner == $owner) && ($rights & PHPGW_ACL_EDIT))
	{
		$p->set_var('action_url_button',$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/edit_entry.php','id='.$id.'&owner='.$owner));
		$p->set_var('action_text_button','  '.lang('Edit').'  ');
		$p->set_var('action_confirm_button','');
		$p->parse('edit_button','form_button');
	}
	else
	{
		$p->set_var('edit_button','');
	}

	if (($event->owner == $owner) && ($rights & PHPGW_ACL_DELETE))
	{
		$p->set_var('action_url_button',$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/delete.php','id='.$id.'&owner='.$owner));
		$p->set_var('action_text_button',lang('Delete'));
		$p->set_var('action_confirm_button',"onClick=\"return confirm('".lang("Are you sure\\nyou want to\\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.")."')\"");
		$p->parse('delete_button','form_button');
	}
	else
	{
		$p->set_var('delete_button','');
	}
	$p->pparse('out','view_end');
	$phpgw->common->phpgw_footer();
?>
