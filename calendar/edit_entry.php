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
		'enable_categories_class'	=> True,
		'noheader'						=> True,
		'nonavbar'						=> True,
		'noappheader'					=>	True,
		'noappfooter'					=>	True
	);
	
	$phpgw_info['flags'] = $phpgw_flags;

	include('../header.inc.php');

	$sb = CreateObject('phpgwapi.sbox');

	$cal_info = CreateObject('calendar.calendar_item');

	function display_item(&$p,$field,$data)
	{
		$p->set_var('field',$field);
		$p->set_var('data',$data);
		$p->parse('row','list',True);
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
		$phpgw->calendar->open('INBOX',intval($owner),'');
		$event = $phpgw->calendar->fetch_event(intval($id));

		$can_edit = False;
		
		if(($event->owner == $owner) && ($phpgw->calendar->check_perms(PHPGW_ACL_EDIT) == True))
		{
			if($event->public != True)
			{
				if($phpgw->calendar->check_perms(PHPGW_ACL_PRIVATE) == True)
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
			header('Location: '.$phpgw->link('/calendar/view.php','id='.$id.'&owner='.$owner));
		}
	}
	elseif(isset($readsess))
	{
		$event = unserialize(str_replace('O:8:"stdClass"','O:13:"calendar_time"',serialize($phpgw->session->appsession('entry','calendar'))));
		
		if($event->owner == 0)
		{
			$phpgw->calendar->add_attribute('owner',$owner);
		}
		
		$can_edit = True;
	}
	else
	{
		if($phpgw->calendar->check_perms(PHPGW_ACL_ADD) == False)
		{
			header('Location: '.$phpgw->link('/calendar/view.php','id='.$id.'&owner='.$owner));
		}

		$phpgw->calendar->open('INBOX',intval($cal_info->owner),'');
		$phpgw->calendar->event_init();
		$phpgw->calendar->add_attribute('id',0);

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

		$phpgw->calendar->set_start($thisyear,$thismonth,$thisday,$thishour,$thisminute,0);
		$phpgw->calendar->set_end($thisyear,$thismonth,$thisday,$thishour,$thisminute,0);
		$phpgw->calendar->set_title('');
		$phpgw->calendar->set_description('');
		$phpgw->calendar->add_attribute('priority',2);
		if($phpgw_info['user']['preferences']['calendar']['default_private'] == 'Y' || $phpgw_info['user']['preferences']['calendar']['default_private'] == True)
		{
			$phpgw->calendar->set_class(False);
		}
		else
		{
			$phpgw->calendar->set_class(True);
		}

		$phpgw->calendar->set_recur_none();
		$event = $phpgw->calendar->event;
	}

	$start = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $phpgw->calendar->datetime->tz_offset;
	$end = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $phpgw->calendar->datetime->tz_offset;

	$phpgw->common->phpgw_header();
	echo parse_navbar();

	$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);
	$templates = Array(
		'edit'	=>	'edit.tpl',
		'form_button'		=>	'form_button_script.tpl'
	);
	$p->set_file($templates);
	$p->set_block('edit','edit_entry','edit_entry');
	$p->set_block('edit','list','list');
	$p->set_block('edit','hr','hr');

	if($id > 0)
	{
		$action = lang('Calendar - Edit');
	}
	else
	{
		$action = lang('Calendar - Add');
	}

	if($cd)
	{
		$errormsg = $phpgw->common->check_code($cd);
	}
	else
	{
		$errormsg = '';
	}

	$common_hidden = '<input type="hidden" name="id" value="'.$event->id.'">'."\n"
						. '<input type="hidden" name="owner" value="'.$owner.'">'."\n";
						
	$vars = Array(
						'font'				=>	$phpgw_info['theme']['font'],
						'bg_color'			=>	$phpgw_info['theme']['bg_text'],
						'calendar_action'	=>	$action,
						'action_url'		=>	$phpgw->link('/calendar/edit_entry_handler.php'),
						'common_hidden'	=>	$common_hidden,
						'errormsg'			=>	$errormsg
	);
	
	$p->set_var($vars);

// Brief Description
	display_item($p,lang('Title'),'<input name="title" size="25" maxlength="80" value="'.$event->title.'">');

// Full Description
	display_item($p,lang('Full Description'),'<textarea name="description" rows="5" cols="40" wrap="virtual" maxlength="2048">'.$event->description.'</textarea>');

// Display Categories
	display_item($p,lang('Category'),'<select name="category"><option value="">'.lang('Choose the category').'</option>'.$phpgw->categories->formated_list('select','all',$event->category,True).'</select>');

// Date
	$day_html = $sb->getDays('start[mday]',intval($phpgw->common->show_date($start,'d')));
	$month_html = $sb->getMonthText('start[month]',intval($phpgw->common->show_date($start,'n')));
	$year_html = $sb->getYears('start[year]',intval($phpgw->common->show_date($start,'Y')),intval($phpgw->common->show_date($start,'Y')));
	display_item($p,lang('Start Date'),$phpgw->common->dateformatorder($year_html,$month_html,$day_html));

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

	display_item($p,lang('Start Time'),$str);

// End Date
	$day_html = $sb->getDays('end[mday]',intval($phpgw->common->show_date($end,'d')));
	$month_html = $sb->getMonthText('end[month]',intval($phpgw->common->show_date($end,'n')));
	$year_html = $sb->getYears('end[year]',intval($phpgw->common->show_date($end,'Y')),intval($phpgw->common->show_date($end,'Y')));
	display_item($p,lang('End Date'),$phpgw->common->dateformatorder($year_html,$month_html,$day_html));

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

    display_item($p,lang("End Time"),$str);

// Priority
	display_item($p,lang('Priority'),$sb->getPriority('priority',$event->priority));

// Access
	$str = '<input type="checkbox" name="private" value="private"';
	if($event->public != True)
	{
		$str .= ' checked';
	}
	$str .= '>';
	display_item($p,lang('Private'),$str);

	function build_part_list(&$users,$accounts,$owner)
	{
		global $phpgw;
		if($accounts == False)
		{
			return;
		}
		while(list($index,$id) = each($accounts))
		{
			if(intval($id) == $owner)
			{
				continue;
			}
			if(!isset($users[intval($id)]))
			{
				if($phpgw->accounts->exists(intval($id)) == True)
				{
					$users[intval($id)] = $phpgw->common->grab_owner_name(intval($id));
				}
				if($phpgw->accounts->get_type(intval($id)) == 'g')
				{
					build_part_list($users,$phpgw->acl->get_ids_for_location(intval($id),1,'phpgw_group'),$owner);
				}
			}
		}
	}

// Participants
	$accounts = $phpgw->acl->get_ids_for_location('run',1,'calendar');
	$users = Array();
	build_part_list($users,$accounts,$owner);
	while(list($key,$status) = each($event->participants))
	{
		$parts[$key] = ' selected';
	}
    
	$str = "\n".'   <select name="participants[]" multiple size="5">'."\n";
	@asort($users);
	@reset($users);
	$user = Array();
	while (list($id,$name) = each($users))
	{
		if(intval($id) == intval($owner))
		{
			continue;
		}
		else
		{
			$str .= '    <option value="' . $id . '"'.$parts[$id].'>('.$phpgw->accounts->get_type($id).') '.$name.'</option>'."\n";
		}
	}
	$str .= '   </select>';
	display_item($p,lang('Participants'),$str);

// I Participate
	$str = '<input type="checkbox" name="participants[]" value="'.$owner.'"';
	if((($id > 0) && isset($event->participants[$owner])) || !isset($id))
	{
		$str .= ' checked';
	}
	$str .= '>';
	display_item($p,$phpgw->common->grab_owner_name($owner).' '.lang('Participates'),$str);

// Repeat Type
	$p->set_var('hr_text','<hr>');
	$p->parse('row','hr',True);
	$p->set_var('hr_text','<center><b>'.lang('Repeating Event Information').'</b></center><br>');
	$p->parse('row','hr',True);
	$str = '<select name="recur_type">';
	$rpt_type = Array(
		MCAL_RECUR_NONE,
		MCAL_RECUR_DAILY,
		MCAL_RECUR_WEEKLY,
		MCAL_RECUR_MONTHLY_WDAY,
		MCAL_RECUR_MONTHLY_MDAY,
		MCAL_RECUR_YEARLY
	);
	$rpt_type_out = Array(
		MCAL_RECUR_NONE => 'None',
		MCAL_RECUR_DAILY => 'Daily',
		MCAL_RECUR_WEEKLY => 'Weekly',
		MCAL_RECUR_MONTHLY_WDAY => 'Monthly (by day)',
		MCAL_RECUR_MONTHLY_MDAY => 'Monthly (by date)',
		MCAL_RECUR_YEARLY => 'Yearly'
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
	display_item($p,lang('Repeat Type'),$str);

	$str = '<input type="checkbox" name="rpt_use_end" value="y"';

	if($event->recur_enddate->year != 0 && $event->recur_enddate->month != 0 && $event->recur_enddate->mday != 0)
	{
		$str .= ' checked';
		$recur_end = mktime($event->recur_enddate->hour,$event->recur_enddate->min,$event->recur_enddate->sec,$event->recur_enddate->month,$event->recur_enddate->mday,$event->recur_enddate->year) - $tz_offset;
	}
	else
	{
		$recur_end = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) + 86400 - $tz_offset;
	}
	
	$str .= '>'.lang('Use End Date').'  ';

	$day_html = $sb->getDays('recur_enddate[mday]',intval($phpgw->common->show_date($recur_end,'d')));
	$month_html = $sb->getMonthText('recur_enddate[month]',intval($phpgw->common->show_date($recur_end,'n')));
	$year_html = $sb->getYears('recur_enddate[year]',intval($phpgw->common->show_date($recur_end,'Y')),intval($phpgw->common->show_date($recur_end,'Y')));
	$str .= $phpgw->common->dateformatorder($year_html,$month_html,$day_html);

	display_item($p,lang('Repeat End Date'),$str);

	$str  = '<input type="checkbox" name="cal[rpt_sun]" value="'.MCAL_M_SUNDAY.'"'.(($event->recur_data & MCAL_M_SUNDAY) ?' checked':'').'> '.lang('Sunday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_mon]" value="'.MCAL_M_MONDAY.'"'.(($event->recur_data & MCAL_M_MONDAY) ?' checked':'').'> '.lang('Monday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_tue]" value="'.MCAL_M_TUESDAY.'"'.(($event->recur_data & MCAL_M_TUESDAY) ?' checked':'').'> '.lang('Tuesday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_wed]" value="'.MCAL_M_WEDNESDAY.'"'.(($event->recur_data & MCAL_M_WEDNESDAY) ?' checked':'').'> '.lang('Wednesday').' <br>';
	$str .= '<input type="checkbox" name="cal[rpt_thu]" value="'.MCAL_M_THURSDAY.'"'.(($event->recur_data & MCAL_M_THURSDAY) ?' checked':'').'> '.lang('Thursday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_fri]" value="'.MCAL_M_FRIDAY.'"'.(($event->recur_data & MCAL_M_FRIDAY) ?' checked':'').'> '.lang('Friday').' ';
	$str .= '<input type="checkbox" name="cal[rpt_sat]" value="'.MCAL_M_SATURDAY.'"'.(($event->recur_data & MCAL_M_SATURDAY) ?' checked':'').'> '.lang('Saturday').' ';

	display_item($p,lang('Repeat Day').'<br>'.lang('(for weekly)'),$str);

	display_item($p,lang('Frequency'),'<input name="recur_interval" size="4" maxlength="4" value="'.$event->recur_interval.'">');

	$p->set_var('submit_button',lang('Submit'));

	if ($id > 0)
	{
		$action_url_button = $phpgw->link('/calendar/delete.php','id='.$id);
		$action_text_button = lang('Delete');
		$action_confirm_button = "onClick=\"return confirm('".lang("Are you sure\\nyou want to \\ndelete this entry?\\n\\nThis will delete\\nthis entry for all users.")."')\"";
		$var = Array(
			'action_url_button'	=> $action_url_button,
			'action_text_button'	=> $action_text_button,
			'action_confirm_button'	=> $action_confirm_button,
			'action_extra_field'	=> ''
		);
		$p->set_var($var);
		$p->parse('delete_button','form_button');
	}
	else
	{
		$p->set_var('delete_button','');
	}
	$p->pparse('out','edit_entry');
	$phpgw->common->phpgw_footer();
?>
