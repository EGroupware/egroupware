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

  function parse_navbar($force = False)
  {
		global $phpgw_info, $phpgw, $menuaction, $obj;

		$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
		$tpl->set_unknowns('remove');

		$tpl->set_file(
			array(
				'navbar'		=> 'navbar.tpl',
				'navbar_app'	=> 'navbar_app.tpl'
			)
		);

		$tpl->set_var('navbar_color',$phpgw_info['theme']['navbar_bg']);

		if ($phpgw_info['flags']['navbar_target'])
		{
			$target = ' target="' . $phpgw_info['flags']['navbar_target'] . '"';
		}

		$i = 1;
		while ($app = each($phpgw_info['navbar']))
		{
			if ($phpgw_info['user']['preferences']['common']['navbar_format'] == 'text')
			{
				$tabs[$i]['label'] = lang($app[1]['title']);
				$tabs[$i]['link']  = $app[1]['url'];
				if (ereg($phpgw_info['navbar'][$app[0]],$PHP_SELF))
				{
					$selected = $i;
				}
				$i++;
			}
			else
			{
				$title = '<img src="' . $app[1]['icon'] . '" alt="' . lang($app[1]['title']) . '" title="'
					. lang($app[1]['title']) . '" border="0">';
				if ($phpgw_info['user']['preferences']['common']['navbar_format'] == 'icons_and_text')
				{
					$title .= "<br>" . lang($app[1]['title']);
					$tpl->set_var('width','7%');
				}
				else
				{
					$tpl->set_var('width','3%');
				}
   
				$tpl->set_var('value','<a href="' . $app[1]['url'] . '"' . $target . '>' . $title . '</a>');
				$tpl->set_var('align','center');
				$tpl->parse('applications','navbar_app',True);
			}
		}
		if ($phpgw_info['user']['preferences']['common']['navbar_format'] == 'text')
		{
			$tpl->set_var('navbar_color',$phpgw_info['theme']['bg_color']);
			$tpl->set_var('align','right');
			$tpl->set_var('value',$phpgw->common->create_tabs($tabs,$selected,-1));
			$tpl->parse('applications','navbar_app',True);
		}

		if ($phpgw_info['server']['showpoweredbyon'] == 'top')
		{
			$tpl->set_var('powered_by',lang('Powered by phpGroupWare version x',$phpgw_info['server']['versions']['phpgwapi']));
		}
		if (isset($phpgw_info['navbar']['admin']) && isset($phpgw_info['user']['preferences']['common']['show_currentusers']))
		{
			$db  = $phpgw->db;
			$db->query("select count(session_id) from phpgw_sessions where session_flags != 'A'");
			$db->next_record();
			$tpl->set_var('current_users','<a href="' . $phpgw->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions')
				. '">&nbsp;' . lang('Current users') . ': ' . $db->f(0) . '</a>');
		}
		$tpl->set_var('user_info',$phpgw->common->display_fullname() . " - "
				. lang($phpgw->common->show_date(time(),"l")) . " "
				. lang($phpgw->common->show_date(time(),"F")) . " "
				. $phpgw->common->show_date(time(),"d, Y"));

		// Maybe we should create a common function in the phpgw_accounts_shared.inc.php file
		// to get rid of duplicate code.
		if ($phpgw_info['user']['lastpasswd_change'] == 0)
		{
			$api_messages = lang('You are required to change your password during your first login')
				. '<br> Click this image on the navbar: <img src="'
				. $phpgw->common->image('preferences','navbar.gif').'">';
		}
		elseif ($phpgw_info['user']['lastpasswd_change'] < time() - (86400*30))
		{
			$api_messages = lang('it has been more then x days since you changed your password',30);
		}
 
		// This is gonna change
		if (isset($cd))
		{
			$tpl->set_var('messages',$api_messages . '<br>' . checkcode($cd));
		}

		$tpl->pfp('out','navbar');
		// If the application has a header include, we now include it
		if (!@$phpgw_info['flags']['noappheader'] && $menuaction)
		{
			if (is_array($obj->public_functions) && $obj->public_functions['header'])
			{
				eval("\$obj->header();");
			}
		}
		$phpgw->common->hook('after_navbar');
		return;
	}

	function parse_navbar_end()
	{
		global $phpgw_info, $phpgw;
		if ($phpgw_info['server']['showpoweredbyon'] == 'bottom')
		{
			$msg = "<P><P>\n" . lang('Powered by phpGroupWare version x', $phpgw_info['server']['versions']['phpgwapi']);
 
			$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
			$tpl->set_unknowns('remove');
   
			$tpl->set_file(
				array(
					'footer' => 'footer.tpl'
				)
			);
			$tpl->set_var('table_bg_color',$phpgw_info['theme']['navbar_bg']);
			$tpl->set_var('msg',$msg);
			$tpl->set_var('version',$phpgw_info['server']['versions']['phpgwapi']);

			$phpgw->common->hook('navbar_end');
			$tpl->pfp('out','footer');
		}
	}
