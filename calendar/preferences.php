<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	$phpgw_flags = Array(
		'currentapp'			=>	'calendar',
		'enable_nextmatchs_class'	=>	True,
		'noheader'			=>	True,
		'nonavbar'			=>	True,
		'noappheader'			=>	True,
		'noappfooter'			=>	True
	);

	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	if ($submit)
	{
		$phpgw->preferences->read_repository();
		$phpgw->preferences->add('calendar','weekdaystarts');
		$phpgw->preferences->add('calendar','workdaystarts');
		$phpgw->preferences->add('calendar','workdayends');
		$phpgw->preferences->add('calendar','defaultcalendar');
		$phpgw->preferences->add('calendar','defaultfilter');
		$phpgw->preferences->add('calendar','interval');
		if ($mainscreen_showevents == True)
		{
			$phpgw->preferences->add('calendar','mainscreen_showevents');
		}
		else
		{
			$phpgw->preferences->delete('calendar','mainscreen_showevents');
		}
		if ($send_updates == True)
		{
			$phpgw->preferences->add('calendar','send_updates');
		}
		else
		{
			$phpgw->preferences->delete('calendar','send_updates');
		}
		
		if ($display_status == True)
		{
			$phpgw->preferences->add('calendar','display_status');
		}
		else
		{
			$phpgw->preferences->delete('calendar','display_status');
		}

		if ($default_private == True)
		{
			$phpgw->preferences->add('calendar','default_private');
		}
		else
		{
			$phpgw->preferences->delete('calendar','default_private');
		}

		if ($display_minicals == True)
		{
			$phpgw->preferences->add('calendar','display_minicals');
		}
		else
		{
			$phpgw->preferences->delete('calendar','display_minicals');
		}

		if ($print_black_white == True)
		{
			$phpgw->preferences->add('calendar','print_black_white');
		}
		else
		{
			$phpgw->preferences->delete('calendar','print_black_white');
		}

		$phpgw->preferences->save_repository(True);
     
		Header('Location: '.$phpgw->link('/preferences/index.php'));
		$phpgw->common->phpgw_exit();
	}

	function display_item($field,$data)
	{
		global $phpgw, $p, $tr_color;

		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$var = Array(
			'bg_color'	=>	$tr_color,
			'field'		=>	$field,
			'data'		=>	$data
		);
		$p->set_var($var);
		$p->parse('row','pref_list',True);
	}

	$phpgw->common->phpgw_header();

	$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
	$templates = Array(
		'pref'		=>	'pref.tpl',
		'pref_colspan'	=>	'pref_colspan.tpl',
		'pref_list'	=>	'pref_list.tpl'
	);
	$p->set_file($templates);

	$var = Array(
		'title'		=>	lang('Calendar preferences'),
		'action_url'	=>	$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/preferences.php'),
		'bg_color'	=>	$phpgw_info['theme']['th_bg'],
		'submit_lang'	=>	lang('submit')
	);
	
	$p->set_var($var);
	$p->set_var('text','&nbsp;');
	$p->parse('row','pref_colspan',True);

//	if ($totalerrors)
//	{
//		echo '<p><center>' . $phpgw->common->error_list($errors) . '</center>';
//	}

	$str = '<input type="checkbox" name="mainscreen_showevents" value="True"'.($phpgw_info['user']['preferences']['calendar']['mainscreen_showevents'] == 'Y' || $phpgw_info['user']['preferences']['calendar']['mainscreen_showevents'] == True?' checked':'').'>';
	display_item(lang('show day view on main screen'),$str);

	$t_weekday[$phpgw_info['user']['preferences']['calendar']['weekdaystarts']] = ' selected';
	$str = '<select name="weekdaystarts">'
		. '<option value="Monday"'.$t_weekday['Monday'].'>'.lang('Monday').'</option>'
		. '<option value="Sunday"'.$t_weekday['Sunday'].'>'.lang('Sunday').'</option>'
// The following is for Arabic support.....
		. '<option value="Saturday"'.$t_weekday['Saturday'].'>'.lang('Saturday').'</option>'
		. '</select>';
	display_item(lang('weekday starts on'),$str);

	$t_workdaystarts[$phpgw_info['user']['preferences']['calendar']['workdaystarts']] = ' selected';
	$str = '<select name="workdaystarts">';
	for ($i=0; $i<24; $i++)
	{
		$str .= '<option value="'.$i.'"'.$t_workdaystarts[$i].'>'
			. $phpgw->common->formattime($i,'00') . '</option>';
	}
	$str .= '</select>';
	display_item(lang('work day starts on'),$str);
  
	$t_workdayends[$phpgw_info['user']['preferences']['calendar']['workdayends']] = ' selected';
	$str = '<select name="workdayends">';
	for ($i=0; $i<24; $i++)
	{
		$str .= '<option value="'.$i.'"'.$t_workdayends[$i].'>'
			. $phpgw->common->formattime($i,'00') . '</option>';
	}
	$str .= '</select>';
	display_item(lang('work day ends on'),$str);

	$selected[$phpgw_info['user']['preferences']['calendar']['defaultcalendar']] = ' selected';
	if (!isset($phpgw_info['user']['preferences']['calendar']['defaultcalendar']))
	{
		$selected['month.php'] = ' selected';
	}
	$str = '<select name="defaultcalendar">'
		. '<option value="year.php"'.$selected['year.php'].'>'.lang('Yearly').'</option>'
		. '<option value="month.php"'.$selected['month.php'].'>'.lang('Monthly').'</option>'
		. '<option value="week.php"'.$selected['week.php'].'>'.lang('Weekly').'</option>'
		. '<option value="day.php"'.$selected['day.php'].'>'.lang('Daily').'</option>'
		. '</select>';
	display_item(lang('default calendar view'),$str);


	$selected = array();
	$selected[$phpgw_info['user']['preferences']['calendar']['defaultfilter']] = ' selected';
	if (! isset($phpgw_info['user']['preferences']['calendar']['defaultfilter']) || $phpgw_info['user']['preferences']['calendar']['defaultfilter'] == 'private')
	{
		$selected['private'] = ' selected';
	}
	$str = '<select name="defaultfilter">'
		. '<option value="all"'.$selected['all'].'>'.lang('all').'</option>'
		. '<option value="private"'.$selected['private'].'>'.lang('private only').'</option>'
//		. '<option value="public"'.$selected['public'].'>'.lang('global public only').'</option>'
//		. '<option value="group"'.$selected['group'].'>'.lang('group public only').'</option>'
//		. '<option value="private+public"'.$selected['private+public'].'>'.lang('private and global public').'</option>'
//		. '<option value="private+group"'.$selected['private+group'].'>'.lang('private and group public').'</option>'
//		. '<option value="public+group"'.$selected['public+group'].'>'.lang('global public and group public').'</option>'
		. '</select>';
	display_item(lang('Default calendar filter'),$str);

	$selected = array();
	$selected[intval($phpgw_info['user']['preferences']['calendar']['interval'])] = ' selected';
	if (! isset($phpgw_info['user']['preferences']['calendar']['interval']))
	{
		$selected[60] = ' selected';
	}
	$var = Array(
		5	=> '5',
		10	=> '10',
		15	=> '15',
		20	=> '20',
		30	=> '30',
		45	=> '45',
		60	=> '60'
	);
	
	$str = '<select name="interval">';
	while(list($key,$value) = each($var))
	{
		$str .= '<option value="'.$key.'"'.$selected[$key].'>'.$value.'</option>';
	}
	$str .= '</select>';
	display_item(lang('Display interval in Day View'),$str);

	$str = '<input type="checkbox" name="send_updates" value="True"'.($phpgw_info['user']['preferences']['calendar']['send_updates'] == 'Y' || $phpgw_info['user']['preferences']['calendar']['send_updates'] == True?' checked':'').'>';
	display_item(lang('Send/receive updates via email'),$str);

	$str = '<input type="checkbox" name="display_status" value="True"'.($phpgw_info['user']['preferences']['calendar']['display_status'] == 'Y' || $phpgw_info['user']['preferences']['calendar']['display_status'] == True?' checked':'').'>';
	display_item(lang('Display status of events'),$str);

	$str = '<input type="checkbox" name="default_private" value="True"'.($phpgw_info['user']['preferences']['calendar']['default_private'] == 'Y' || $phpgw_info['user']['preferences']['calendar']['default_private'] == True?' checked':'').'>';
	display_item(lang('When creating new events default set to private'),$str);

	$str = '<input type="checkbox" name="display_minicals" value="True"'.($phpgw_info['user']['preferences']['calendar']['display_minicals'] == 'Y' || $phpgw_info['user']['preferences']['calendar']['display_minicals'] == True?' checked':'').'>';
	display_item(lang('Display mini calendars when printing'),$str);

	$str = '<input type="checkbox" name="print_black_white" value="True"'.($phpgw_info['user']['preferences']['calendar']['print_black_white'] == 'Y' || $phpgw_info['user']['preferences']['calendar']['print_black_white'] == True?' checked':'').'>';
	display_item(lang('Print calendars in black & white'),$str);

	$p->pparse('out','pref');
?>
