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
	$cal_info = $phpgw->calendar->fetch_event($cal_stream,$id);

	reset($cal_info->participants);
	$participating = False;
	for($j=0;$j<count($cal_info->participants);$j++)
	{
		if($cal_info->participants[$j] == $owner)
		{
			$participating = True;
		}
	}
  
	if($participating == False)
	{
		echo lang('You do not have permission to read this record.');
		$phpgw->common->phpgw_exit();
	}
  
	$description = nl2br($description);

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
		'name'	=>	$cal_info->name
	);
	$p->set_var($var);
	$p->parse('out','view_begin');

	// Some browser add a \n when its entered in the database. Not a big deal
	// this will be printed even though its not needed.
	if (nl2br($cal_info->description))
	{
		display_item(lang("Description"),nl2br($cal_info->description));
	}

	display_item(lang('Start Date/Time'),$phpgw->common->show_date($cal_info->datetime));

	// save date so the trailer links are for the same time period
	$thisyear	= $cal_info->start->year;
	$thismonth	= $cal_info->start->month;
	$thisday 	= $cal_info->start->mday;

	display_item(lang('End Date/Time'),$phpgw->common->show_date($cal_info->edatetime));

	display_item(lang('Priority'),$pri[$cal_info->priority]);

	$participate = False;
	for($i=0;$i<count($cal_info->participants);$i++)
	{
		if($cal_info->participants[$i] == $phpgw_info['user']['account_id'])
		{
			$participate = True;
		}
	}
	if($cal_info->owner == $phpgw_info['user']['account_id'] && $participate)
	{
		display_item(lang('Created by'),'<a href="'
			.$phpgw->link('viewmatrix.php','participants='.$cal_info->owner.'&date='.$cal_info->year.$cal_info->month.$cal_info->day.'&matrixtype=free/busy&owner='.$owner)
			.'">'.$phpgw->common->grab_owner_name($cal_info->owner).'</a>');
	}
	else
	{
		display_item(lang('Created by'),$phpgw->common->grab_owner_name($cal_info->owner));
	}

	display_item(lang('Updated'),$phpgw->common->show_date($cal_info->mdatetime));

	if($cal_info->groups[0])
	{
		$cal_grps = '';
		for($i=0;$i<count($cal_info->groups);$i++)
		{
			if($i>0)
			{
				$cal_grps .= '<br>';
			}
			$cal_grps .= $phpgw->accounts->id2name($cal_info->groups[$i]);
		}
		display_item(lang('Groups'),$cal_grps);
	}

	$str = '';
	for($i=0;$i<count($cal_info->participants);$i++)
	{
		if($i)
		{
			$str .= '<br>';
		}
		switch ($cal_info->status[$i])
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
		$str .= $phpgw->common->grab_owner_name($cal_info->participants[$i]).' ('.$status.')';
	}
	display_item(lang('Participants'),$str);

// Repeated Events
	$str = $cal_info->rpt_type;
	if($str <> 'none' || $cal_info->rpt_use_end)
	{
		$str .= ' (';
		$recur_end = mktime(0,0,0,$cal_info->recur_enddate->month,$cal_info->recur_enddate->mday,$cal_info->recur_enddate->year);
		if($recur_end != 0)
		{
			$str .= lang('ends').': '.$phpgw->common->show_date($recur_end,'l, F d, Y').' ';
		}
		if($cal_info->recur_type == RECUR_WEEKLY || $cal_info->recur_type == RECUR_DAILY)
		{
			$repeat_days = '';
			if ($cal_info->recur_data & M_SUNDAY)
			{
				add_day($repeat_days,lang('Sunday '));
			}
			if ($cal_info->recur_data & M_MONDAY)
			{
				add_day($repeat_days,lang('Monday '));
			}
			if ($cal_info->recur_data & M_TUESDAY)
			{
				add_day($repeat_days,lang('Tuesay '));
			}
			if ($cal_info->recur_data & M_WEDNESDAY)
			{
				add_day($repeat_days,lang('Wednesday '));
			}
			if ($cal_info->recur_data & M_THURSDAY)
			{
				add_day($repeat_days,lang('Thursday '));
			}
			if ($cal_info->recur_data & M_FRIDAY)
			{
				add_day($repeat_days,lang('Friday '));
			}
			if ($cal_info->recur_data & M_SATURDAY)
			{
				add_day($repeat_days,lang('Saturday '));
			}
			$str .= lang('days repeated').': '.$repeat_days;
		}
		if($cal_info->recur_interval)
		{
			$str .= lang('frequency').' '.$cal_info->recur_interval;
		}
		$str .= ')';

		display_item(lang('Repetition'),$str);
	}

	if (($cal_info->owner == $owner) && ($rights & PHPGW_ACL_EDIT))
	{
		$p->set_var('action_url_button',$phpgw->link('edit_entry.php','id='.$id.'&owner='.$owner));
		$p->set_var('action_text_button','  '.lang('Edit').'  ');
		$p->set_var('action_confirm_button','');
		$p->parse('edit_button','form_button');
	}
	else
	{
		$p->set_var('edit_button','');
	}

	if (($cal_info->owner == $owner) && ($rights & PHPGW_ACL_DELETE))
	{
		$p->set_var('action_url_button',$phpgw->link('delete.php','id='.$id.'&owner='.$owner));
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
