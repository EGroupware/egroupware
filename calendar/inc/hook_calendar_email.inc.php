<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	global $msgtype, $owner, $rights;

	$d1 = strtolower(substr($phpgw_info['server']['app_inc'],0,3));
	if($d1 == 'htt' || $d1 == 'ftp')
	{
		echo 'Failed attempt to break in via an old Security Hole!<br>'."\n";
		$phpgw->common->phpgw_exit();
	}
	unset($d1);

	$tmp_app_inc = $phpgw_info['server']['app_inc'];
	$phpgw_info['server']['app_inc'] = $phpgw->common->get_inc_dir('calendar');

	include($phpgw_info['server']['app_inc'].'/functions.inc.php');

	$str = '';

	$msg_type = explode(';',$msgtype);
	$id_array = explode('=',$msg_type[2]);
	$id = intval(substr($id_array[1],1,strlen($id_array[1])-2));

	echo 'Event ID: '.$id."<br>\n";

	$cal_stream = $phpgw->calendar->open('INBOX',$owner,'');
	$event = $phpgw->calendar->fetch_event($cal_stream,$id);

	reset($event->participants);

	$tz_offset = ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));
	$freetime = $phpgw->calendar->localdates(mktime(0,0,0,$event->start->month,$event->start->mday,$event->start->year) - $tz_offset);
	echo $phpgw->calendar->timematrix($freetime,$phpgw->calendar->splittime('000000',False),0,$event->participants);

	echo '</td></tr><tr><td>';

	function add_day(&$repeat_days,$day)
	{
		if($repeat_days)
		{
			$repeat_days .= ', ';
		}
		$repeat_days .= $day;
	}

	function display_item($p,$field,$data)
	{
//		global $p;

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

	reset($event->participants);
	$participating = False;
	for($j=0;$j<count($event->participants);$j++)
	{
		if($event->participants[$j] == $owner)
		{
			$participating = True;
		}
	}
  
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
		$var = Array(
			'field'	=>	lang('Description'),
			'data'	=>	nl2br($event->description)
		);
		$p->set_var($var);
		$p->parse('output','list',True);
	}

	$tz_offset = ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));

	$start = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $tz_offset;
	$var = Array(
		'field'	=>	lang('Start Date/Time'),
		'data'	=>	$phpgw->common->show_date($start)
	);
	$p->set_var($var);
	$p->parse('output','list',True);
	
	$end = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $tz_offset;
	$var = Array(
		'field'	=>	lang('End Date/Time'),
		'data'	=>	$phpgw->common->show_date($end)
	);
	$p->set_var($var);
	$p->parse('output','list',True);

	$var = Array(
		'field'	=>	lang('Priority'),
		'data'	=>	$pri[$event->priority]
	);
	$p->set_var($var);
	$p->parse('output','list',True);

	$participate = False;
	for($i=0;$i<count($event->participants);$i++)
	{
		if($event->participants[$i] == $phpgw_info['user']['account_id'])
		{
			$participate = True;
		}
	}
	$var = Array(
		'field'	=>	lang('Created By'),
		'data'	=>	$phpgw->common->grab_owner_name($event->owner)
	);
	$p->set_var($var);
	$p->parse('output','list',True);
	
	$var = Array(
		'field'	=>	lang('Updated'),
		'data'	=>	$phpgw->common->show_date($event->mdatetime)
	);
	$p->set_var($var);
	$p->parse('output','list',True);

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
		
		$var = Array(
			'field'	=>	lang('Groups'),
			'data'	=>	$cal_grps
		);
		$p->set_var($var);
		$p->parse('output','list',True);
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
				$status = 'No Response';
				break;
		}
		$str .= $phpgw->common->grab_owner_name($event->participants[$i]).' ('.$status.')';
	}
	$var = Array(
		'field'	=>	lang('Participants'),
		'data'	=>	$str
	);
	$p->set_var($var);
	$p->parse('output','list',True);

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

		$var = Array(
			'field'	=>	lang('Repitition'),
			'data'	=>	$str
		);
		$p->set_var($var);
		$p->parse('output','list',True);
	}

	echo $p->finish($p->parse('out','view_end'));

	echo '</td></tr><td align="center"><tr><table width="100%" cols="4"><tr align="center">';

	$templates = Array(
  		'form_button'	=> 'form_button_script.tpl'
	);
	$p->set_file($templates);
	
	$p->set_var('action_url_button',$phpgw->link('/calendar/action.php','id='.$id.'&owner='.$owner.'&action=accept'));
	$p->set_var('action_text_button','  '.lang('Accept').'  ');
	$p->set_var('action_confirm_button','');
	echo '<td>'.$p->finish($p->parse('out','form_button')).'</td>'."\n";

	$p->set_var('action_url_button',$phpgw->link('/calendar/action.php','id='.$id.'&owner='.$owner.'&action=reject'));
	$p->set_var('action_text_button','  '.lang('Reject').'  ');
	$p->set_var('action_confirm_button','');
	echo '<td>'.$p->finish($p->parse('out','form_button')).'</td>'."\n";

	$p->set_var('action_url_button',$phpgw->link('/calendar/action.php','id='.$id.'&owner='.$owner.'&action=tentative'));
	$p->set_var('action_text_button','  '.lang('Tentative').'  ');
	$p->set_var('action_confirm_button','');
	echo '<td>'.$p->finish($p->parse('out','form_button')).'</td>'."\n";

	$p->set_var('action_url_button',$phpgw->link('/calendar/action.php','id='.$id.'&owner='.$owner.'&action=noresponse'));
	$p->set_var('action_text_button','  '.lang('No Response').'  ');
	$p->set_var('action_confirm_button','');
	echo '<td>'.$p->finish($p->parse('out','form_button')).'</td>'."\n";

	echo '</tr></table>';

	$phpgw_info['server']['app_inc'] = $tmp_app_inc;
?>
