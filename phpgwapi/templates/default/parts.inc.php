<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$GLOBALS['phpgw']->template->set_var('phpgw_top_height','10%');
	$GLOBALS['phpgw']->template->set_var('phpgw_left_width','0');
	$GLOBALS['phpgw']->template->set_var('phpgw_right_width','0');
	$GLOBALS['phpgw']->template->set_var('phpgw_bottom_height','5%');

	function parse_toppart($output)
  {
		$GLOBALS['phpgw']->template->set_file('parts','parts.tpl');
		$GLOBALS['phpgw']->template->set_block('parts','top_part');
		$GLOBALS['phpgw']->template->set_block('parts','top_part_app');

		$var['navbar_color'] = $GLOBALS['phpgw_info']['theme']['navbar_bg'];

		if ($GLOBALS['phpgw_info']['flags']['navbar_target'])
		{
			$target = ' target="' . $GLOBALS['phpgw_info']['flags']['navbar_target'] . '"';
		}

		$i = 1;
		while ($app = each($GLOBALS['phpgw_info']['navbar']))
		{
			if ($GLOBALS['phpgw_info']['user']['preferences']['common']['navbar_format'] == 'text')
			{
				$tabs[$i]['label'] = lang($app[1]['title']);
				$tabs[$i]['link']  = $app[1]['url'];
				if (ereg($GLOBALS['phpgw_info']['navbar'][$app[0]],$PHP_SELF))
				{
					$selected = $i;
				}
				$i++;
			}
			else
			{
				$title = '<img src="' . $app[1]['icon'] . '" alt="' . lang($app[1]['title']) . '" title="'
					. lang($app[1]['title']) . '" border="0">';
				if ($GLOBALS['phpgw_info']['user']['preferences']['common']['navbar_format'] == 'icons_and_text')
				{
					$title .= "<br>" . lang($app[1]['title']);
					$var['width'] = '7%';
				}
				else
				{
					$var['width']  = '3%';
				}
   
				$var['value'] = '<a href="' . $app[1]['url'] . '"' . $target . '>' . $title . '</a>';
				$var['align'] = 'center';
				$GLOBALS['phpgw']->template->set_var($var);
				$GLOBALS['phpgw']->template->parse('applications','top_part_app',True);
			}
		}
		if ($GLOBALS['phpgw_info']['user']['preferences']['common']['navbar_format'] == 'text')
		{
			$var['navbar_color'] = $GLOBALS['phpgw_info']['theme']['bg_color'];
			$var['align'] = 'right';
			$var['value'] = $GLOBALS['phpgw']->common->create_tabs($tabs,$selected,-1);
			$GLOBALS['phpgw']->template->set_var($var);
			$GLOBALS['phpgw']->template->parse('applications','top_part_app',True);
		}

		if	 ($GLOBALS['phpgw_info']['server']['showpoweredbyon'] == 'top')
		{
			$var['powered_by'] = lang('Powered by phpGroupWare version x',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
		}
		if (isset($GLOBALS['phpgw_info']['navbar']['admin']) && isset($GLOBALS['phpgw_info']['user']['preferences']['common']['show_currentusers']))
		{
			$db  = $GLOBALS['phpgw']->db;
			$db->query("select count(session_id) from phpgw_sessions where session_flags != 'A'");
			$db->next_record();
			$var['current_users'] = '<a href="' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions')
				. '">&nbsp;' . lang('Current users') . ': ' . $db->f(0) . '</a>';
		}
		$now = time();
		$var['user_info'] = $GLOBALS['phpgw']->common->display_fullname() . ' - '
				. lang($GLOBALS['phpgw']->common->show_date($now,'l')) . ' '
				. $GLOBALS['phpgw']->common->show_date($now,$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
//				. lang($GLOBALS['phpgw']->common->show_date($now,'F')) . ' '
//				. $GLOBALS['phpgw']->common->show_date($now,'d, Y');

		// Maybe we should create a common function in the phpgw_accounts_shared.inc.php file
		// to get rid of duplicate code.
		if ($GLOBALS['phpgw_info']['user']['lastpasswd_change'] == 0)
		{
			$api_messages = lang('You are required to change your password during your first login')
				. '<br> Click this image on the navbar: <img src="'
				. $GLOBALS['phpgw']->common->image('preferences','navbar.gif').'">';
		}
		elseif ($GLOBALS['phpgw_info']['user']['lastpasswd_change'] < time() - (86400*30))
		{
			$api_messages = lang('it has been more then x days since you changed your password',30);
		}
 
		// This is gonna change
		if (isset($cd))
		{
			$var['messages'] = $api_messages . '<br>' . checkcode($cd);
		}
		$GLOBALS['phpgw']->template->set_var($var);
		$GLOBALS['phpgw']->template->fp($output,'top_part');
	}

	function parse_bottompart($output)
	{
		if ($GLOBALS['phpgw_info']['server']['showpoweredbyon'] == 'bottom')
		{
			$GLOBALS['phpgw']->template->set_file('parts','parts.tpl');
			$GLOBALS['phpgw']->template->set_block('parts','bottom_part');
			$var = Array(
				'bg_color' => $GLOBALS['phpgw_info']['theme']['bg_color'],
				'msg'			=> "<p><p>\n".lang('Powered by phpGroupWare version x',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']),
				'version'		=> $GLOBALS['phpgw_info']['server']['versions']['phpgwapi']
			);
			$GLOBALS['phpgw']->template->set_var($var);
			$GLOBALS['phpgw']->template->fp($output,'bottom_part');
		}
		else
		{
			$GLOBALS['phpgw']->template->set_var($output,'');
		}
	}
