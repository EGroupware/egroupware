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

	$phpgw_flags = Array(
		'currentapp'					=>	'calendar',
		'enable_nextmatchs_class'	=>	True,
	);

	$phpgw_info['flags'] = $phpgw_flags;

	include('../header.inc.php');

	$datetime = mktime(0,0,0,$thismonth,$thisday,$thisyear) - $phpgw->calendar->datetime->tz_offset;

	$sb = CreateObject('phpgwapi.sbox');
	
	$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);

	$templates = Array(
		'mq'			=> 'matrix_query.tpl',
		'form_button'		=>	'form_button_script.tpl'
	);
	$p->set_file($templates);
	$p->set_block('mq','matrix_query','matrix_query');
	$p->set_block('mq','list','list');

	$var = Array(
		'matrix_action'			=>	lang('Daily Matrix View'),
		'action_url'				=> $phpgw->link('/calendar/viewmatrix.php')
	);

	$p->set_var($var);

// Date
	$day_html = $sb->getDays('day',intval($phpgw->common->show_date($datetime,'d')));
	$month_html = $sb->getMonthText('month',intval($phpgw->common->show_date($datetime,'n')));
	$year_html = $sb->getYears('year',intval($phpgw->common->show_date($datetime,'Y')),intval($phpgw->common->show_date($datetime,'Y')));

	$var = Array(
		'field'	=>	lang('Date'),
		'data'	=>	$phpgw->common->dateformatorder($year_html,$month_html,$day_html)
	);
		
	$p->set_var($var);
	$p->parse('rows','list',True);
	
// View type
	$str  = '<select name="matrixtype">';
	$str .= '<option value="free/busy" selected>'.lang('free/busy').'</option>'."\n";
	$str .= '<option value="weekly">'.lang('Weekly').'</option>'."\n";
	$str .= '</select>'."\n";

	$var = Array(
		'field'	=>	lang('View'),
		'data'	=>	$str
	);

	$p->set_var($var);
	$p->parse('rows','list',True);

// Participants
	$accounts = $phpgw->acl->get_ids_for_location('run',1,'calendar');
	$users = Array();
	for($i=0;$i<count($accounts);$i++)
	{
	   $user = $accounts[$i];
		if(!isset($users[$user]))
		{
			$users[$user] = $phpgw->common->grab_owner_name($user);
			if($phpgw->accounts->get_type($user) == 'g')
			{
				$group_members = $phpgw->acl->get_ids_for_location($user,1,'phpgw_group');
				if($group_members != False)
				{
					for($j=0;$j<count($group_members);$j++)
					{
						if(!isset($users[$group_members[$j]]))
						{
							$users[$group_members[$j]] = $phpgw->common->grab_owner_name($group_members[$j]);
						}
					}
				}
			}
		}
	}

	if ($num_users > 50)
	{
		$size = 15;
	}
	elseif ($num_users > 5)
	{
		$size = 5;
	}
	else
	{
		$size = $num_users;
	}
	$str = "\n".'   <select name="participants[]" multiple size="'.$size.'">'."\n";
	@asort($users);
	@reset($users);
	while ($user = each($users))
	{
		if((($phpgw->accounts->exists($user[0]) == True) && ($grants[$user[0]] && PHPGW_ACL_READ)) || ($user[0] == $owner) || $phpgw->accounts->get_type($user[0]) == 'g')
		{
			$str .= '    <option value="' . $user[0] . '">('.$phpgw->accounts->get_type($user[0]).') '.$user[1].'</option>'."\n";
		}
	}
	$str .= '   </select>';

	$var = Array(
		'field'	=>	lang('Participants'),
		'data'	=>	$str
	);

	$p->set_var($var);
	$p->parse('rows','list',True);

	$var = Array(
		'submit_button'		=> lang('Submit'),
		'action_url_button'	=> '',
		'action_text_button'	=> lang('Cancel'),
		'action_confirm_button'	=> 'onClick="history.back(-1)"',
		'action_extra_field'	=> ''
	);

	$p->set_var($var);
	$p->parse('cancel_button','form_button');

	$p->pparse('out','matrix_query');

	$phpgw->common->phpgw_footer();
?>
