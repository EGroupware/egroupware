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
		'enable_nextmatchs_class'	=> True,
		'noheader'						=> True,
		'nonavbar'						=> True,
		'noappheader'					=>	True,
		'noappfooter'					=>	True
	);
	
	$phpgw_info['flags'] = $phpgw_flags;

	include('../header.inc.php');

	$sb = CreateObject('phpgwapi.sbox');

	$cal_info = CreateObject('calendar.calendar_item');

	function display_item($field,$data)
	{
		global $p;
		
		$p->set_var('field',$field);
		$p->set_var('data',$data);
		$p->parse('output','list',True);
	}

	if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
	{
		$hourformat = 'h';
	}
	else
	{
		$hourformat = 'H';
	}

	if ($id > 0)
	{
		$cal_stream = $phpgw->calendar->open('INBOX',intval($owner),'');
		$event = $phpgw->calendar->fetch_event($cal_stream,intval($id));

		$can_edit = False;
		
		if(($event->owner == $owner) && ($phpgw->calendar->check_perms(PHPGW_ACL_EDIT) == True))
		{
			if($event->public != True)
			{
				if($phpgw->calendar->check_perms(16) == True)
				{
					$can_edit = True;
				}
			}
			else
			{
				$can_edit = True;
			}
		}

		if($can_edit == False)
		{
			header('Location: '.$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/view.php','id='.$id.'&owner='.$owner));
		}
	}
	elseif(isset($readsess))
	{
		$event = $phpgw->session->appsession('entry','calendar');
		
		if($event->owner == 0)
		{
			$event->owner = $owner;
		}
		
		$can_edit = True;
	}
	else
	{
		if($phpgw->calendar->check_perms(PHPGW_ACL_ADD) == False)
		{
			header('Location: '.$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/view.php','id='.$id.'&owner='.$owner));
		}

		$cal_stream = $phpgw->calendar->open('INBOX',intval($cal_info->owner),'');
		$phpgw->calendar->event_init($cal_stream);
		$phpgw->calendar->event->id = 0;

		$can_edit = True;

		if (!isset($hour))
		{
			$thishour = 0;
		}
		else
		{
			$thishour = (int)$hour;
		}
		
		if (!isset($minute))
		{
			$thisminute = 00;
		}
		else
		{
			$thisminute = (int)$minute;
		}

		$phpgw->calendar->event_set_start($cal_stream,$thisyear,$thismonth,$thisday,$thishour,$this->minute,0);
		$phpgw->calendar->event_set_end($cal_stream,$thisyear,$thismonth,$thisday,$thishour,$this->minute,0);
		$phpgw->calendar->event_set_title($cal_stream,'');
		$phpgw->calendar->event_set_description($cal_stream,'');
		$phpgw->calendar->event->priority = 2;

		$phpgw->calendar->event_set_recur_none($cal_stream);
		$event = $phpgw->calendar->event;
	}

	$tz_offset = ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));
	$start = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $tz_offset;
	$end = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $tz_offset;

	$phpgw->common->phpgw_header();
	echo parse_navbar();

	$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);
	$templates = Array(
		'edit_entry_begin'=>	'edit.tpl',
		'list'				=>	'list.tpl',
		'hr'					=> 'hr.tpl',
		'edit_entry_end'	=> 'edit.tpl',
		'form_button'		=>	'form_button_script.tpl'
	);
	$p->set_file($templates);

	if($id > 0)
	{
		$action = lang('Calendar - Edit');
	}
	else
	{
		$action = lang('Calendar - Add');
	}

	$common_hidden = '<input type="hidden" name="id" value="'.$event->id.'">'."\n"
						. '<input type="hidden" name="owner" value="'.$owner.'">'."\n";
						
	$vars = Array(
						'bg_color'			=>	$phpgw_info['theme']['bg_text'],
						'calendar_action'	=>	$action,
						'action_url'		=>	$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/edit_entry_handler.php'),
						'common_hidden'	=>	$common_hidden
	);
	
	$p->set_var($vars);
	$p->parse('out','edit_entry_begin');

// Brief Description
	display_item(lang('Title'),'<input name="title" size="25" value="'.$event->title.'">');

// Full Description
	display_item(lang('Full Description'),'<textarea name="description" rows="5" cols="40" wrap="virtual">'.$event->description.'</textarea>');

// Date
	$day_html = $sb->getDays('start[mday]',intval($phpgw->common->show_date($start,'d')));
	$month_html = $sb->getMonthText('start[month]',intval($phpgw->common->show_date($start,'n')));
	$year_html = $sb->getYears('start[year]',intval($phpgw->common->show_date($start,'Y')),intval($phpgw->common->show_date($start,'Y')));
	display_item(lang('Start Date'),$phpgw->common->dateformatorder($year_html,$month_html,$day_html));

// Time
	$amsel = ' checked'; $pmsel = '';
	if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
	{
		if ($event->start->hour >= 12)
		{
			$amsel = ''; $pmsel = ' checked';
		}
	}
	$str = '<input name="start[hour]" size="2" VALUE="'.$phpgw->common->show_date($start,$hourformat).'" maxlength="2">:<input name="start[min]" size="2" value="'.$phpgw->common->show_date($start,'i').'" maxlength="2">';
	if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
	{
		$str .= '<input type="radio" name="start[ampm]" value="am"'.$amsel.'>am';
		$str .= '<input type="radio" name="start[ampm]" value="pm"'.$pmsel.'>pm';
	}

	display_item(lang('Start Time'),$str);

// End Date
	$day_html = $sb->getDays('end[mday]',intval($phpgw->common->show_date($end,'d')));
	$month_html = $sb->getMonthText('end[month]',intval($phpgw->common->show_date($end,'n')));
	$year_html = $sb->getYears('end[year]',intval($phpgw->common->show_date($end,'Y')),intval($phpgw->common->show_date($end,'Y')));
	display_item(lang('End Date'),$phpgw->common->dateformatorder($year_html,$month_html,$day_html));

// End Time
	$amsel = ' checked'; $pmsel = '';
	if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
	{
		if ($event->end->hour >= 12)
		{
			$amsel = ''; $pmsel = ' checked';
		}
	}

	$str = '<input name="end[hour]" size="2" VALUE="'.$phpgw->common->show_date($end,$hourformat).'" maxlength="2">:<input name="end[min]" size="2" value="'.$phpgw->common->show_date($end,'i').'" maxlength="2">';
	if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
	{
		$str .= '<input type="radio" name="end[ampm]" value="am"'.$amsel.'>am';
		$str .= '<input type="radio" name="end[ampm]" value="pm"'.$pmsel.'>pm';
	}

    display_item(lang("End Time"),$str);

// Priority
	display_item(lang('Priority'),$sb->getPriority('priority',$event->priority));

// Access
	$str = '<input type="checkbox" name="private" value="private"';
	if($event->public != True)
	{
		$str .= ' checked';
	}
	$str .= '>';
	display_item(lang('Private'),$str);

// Participants
	$accounts = $phpgw->acl->get_ids_for_location('run',1,'calendar');
	$users = Array();
	for($i=0;$i<count($accounts);$i++)
	{
		echo '<!-- account_id '.$accounts[$i].' should be valid -->'."\n";
		if($accounts[$i] != $owner && !isset($users[$accounts[$i]]))
		{
			$users[$accounts[$i]] = $phpgw->common->grab_owner_name($accounts[$i]);
			if($phpgw->accounts->get_type($accounts[$i]) == 'g')
			{
				$group_members = $phpgw->acl->get_ids_for_location($accounts[$i],1,'phpgw_group');
				if($group_members != False)
				{
					for($j=0;$j<count($group_members);$j++)
					{
						if($group_members[$j] != $owner && !isset($users[$group_members[$j]]))
						{
							$users[$group_members[$j]] = $phpgw->common->grab_owner_name($group_members[$j]);
						}
					}
				}
			}
		}
	}

	$str = "\n".'   <select name="participants[]" multiple size="5">'."\n";
	for ($l=0;$l<count($event->participants);$l++)
	{
		$parts[$event->participants[$l]] = ' selected';
	}
    
	@asort($users);
	@reset($users);
	$user = Array();
	while ($user = each($users))
	{
		$userid = intval($user[0]);
		if($userid != $owner && $phpgw->accounts->exists($userid) == True)
		{
			$str .= '    <option value="' . $userid . '"'.$parts[$userid].'>('.$phpgw->accounts->get_type($userid).') '.$user[1].'</option>'."\n";
		}
	}
	$str .= '   </select>';
	display_item(lang('Participants'),$str);

// I Participate
	$participate = False;
	if($id)
	{
		for($i=0;$i<count($event->participants);$i++)
		{
			if($event->participants[$i] == $owner)
			{
				$participate = True;
			}
		}
	}
	$str = '<input type="checkbox" name="participants[]" value="'.$owner.'"';
	if((($id > 0) && ($participate == True)) || !isset($id))
	{
		$str .= ' checked';
	}
	$str .= '>';
	display_item($phpgw->common->grab_owner_name($owner).' '.lang('Participates'),$str);

// Repeat Type
	$p->set_var('hr_text','<hr>');
	$p->parse('output','hr',True);
	$p->set_var('hr_text','<center><b>'.lang('Repeating Event Information').'</b></center><br>');
	$p->parse('output','hr',True);
	$str = '<select name="recur_type">';
	$rpt_type = Array(
		RECUR_NONE,
		RECUR_DAILY,
		RECUR_WEEKLY,
		RECUR_MONTHLY_WDAY,
		RECUR_MONTHLY_MDAY,
		RECUR_YEARLY
	);
	$rpt_type_out = Array(
		RECUR_NONE => 'None',
		RECUR_DAILY => 'Daily',
		RECUR_WEEKLY => 'Weekly',
		RECUR_MONTHLY_WDAY => 'Monthly (by day)',
		RECUR_MONTHLY_MDAY => 'Monthly (by date)',
		RECUR_YEARLY => 'Yearly'
	);
	for($l=0;$l<count($rpt_type);$l++)
	{
		$str .= '<option value="'.$rpt_type[$l].'"';
		if($event->recur_type == $rpt_type[$l])
		{
			$str .= ' selected';
		}
		$str .= '>'.lang($rpt_type_out[$rpt_type[$l]]).'</option>';
	}
	$str .= '</select>';
	display_item(lang('Repeat Type'),$str);

	$p->set_var('field',lang('Repeat End Date'));
	$str = '<input type="checkbox" name="rpt_use_end" value="y"';

	if($event->recur_enddate->year != 0 && $event->recur_enddate->month != 0 && $event->recur_enddate->mday != 0)
	{
		$str .= ' checked';
		$recur_end = mktime($event->recur_enddate->hour,$event->recur_enddate->min,$event->recur_enddate->sec,$event->recur_enddate->month,$event->recur_enddate->mday,$event->recur_enddate->year) - $tz_offset;
	}
	else
	{
		$recur_end = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->sec) + 86400 - $tz_offset;
	}
	
	$str .= '>'.lang('Use End Date').'  ';

	

	$day_html = $sb->getDays('recur_enddate[mday]',intval($phpgw->common->show_date($recur_end,'d')));
	$month_html = $sb->getMonthText('recur_enddate[month]',intval($phpgw->common->show_date($recur_end,'n')));
	$year_html = $sb->getYears('recur_enddate[year]',intval($phpgw->common->show_date($recur_end,'Y')),intval($phpgw->common->show_date($recur_end,'Y')));
	$str .= $phpgw->common->dateformatorder($year_html,$month_html,$day_html);

	display_item(lang('Repeat End Date'),$str);

	$str  = '<input type="checkbox" name="cal[rpt_sun]" value="'.M_SUNDAY.'"'.(($event->recur_data & M_SUNDAY) ?' checked':'').'> '.lang('Sunday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_mon]" value="'.M_MONDAY.'"'.(($event->recur_data & M_MONDAY) ?' checked':'').'> '.lang('Monday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_tue]" value="'.M_TUESDAY.'"'.(($event->recur_data & M_TUESDAY) ?' checked':'').'> '.lang('Tuesday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_wed]" value="'.M_WEDNESDAY.'"'.(($event->recur_data & M_WEDNESDAY) ?' checked':'').'> '.lang('Wednesday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_thu]" value="'.M_THURSDAY.'"'.(($event->recur_data & M_THURSDAY) ?' checked':'').'> '.lang('Thursday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_fri]" value="'.M_FRIDAY.'"'.(($event->recur_data & M_FRIDAY) ?' checked':'').'> '.lang('Friday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_sat]" value="'.M_SATURDAY.'"'.(($event->recur_data & M_SATURDAY) ?' checked':'').'> '.lang('Saturday').' ';

	display_item(lang('Repeat Day').'<br>'.lang('(for weekly)'),$str);

	display_item(lang('Frequency'),'<input name="recur_interval" size="4" maxlength="4" value="'.$event->recur_interval.'">');

	$p->set_var('submit_button',lang('Submit'));

	if ($id > 0)
	{
		$p->set_var('action_url_button',$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/delete.php','id='.$id));
		$p->set_var('action_text_button',lang('Delete'));
		$p->set_var('action_confirm_button',"onClick=\"return confirm('".lang("Are you sure\\nyou want to\\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.")."')\"");
		$p->parse('delete_button','form_button');
		$p->pparse('out','edit_entry_end');
	}
	else
	{
		$p->set_var('delete_button','');
		$p->pparse('out','edit_entry_end');
	}
	$phpgw->common->phpgw_footer();
?>
