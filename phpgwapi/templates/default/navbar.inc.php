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
		$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
		$tpl->set_unknowns('remove');

		$tpl->set_file(
			array(
				'navbar'		=> 'navbar.tpl',
				'navbar_app'	=> 'navbar_app.tpl'
			)
		);

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
				$tpl->set_var($var);
				$tpl->parse('applications','navbar_app',True);
			}
		}
		if ($GLOBALS['phpgw_info']['user']['preferences']['common']['navbar_format'] == 'text')
		{
			$var['navbar_color'] = $GLOBALS['phpgw_info']['theme']['bg_color'];
			$var['align'] = 'right';
			$var['value'] = $GLOBALS['phpgw']->common->create_tabs($tabs,$selected,-1);
			$tpl->set_var($var);
			$tpl->parse('applications','navbar_app',True);
		}

		if ($GLOBALS['phpgw_info']['server']['showpoweredbyon'] == 'top')
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
		$var['user_info'] = $GLOBALS['phpgw']->common->display_fullname() . " - "
				. lang($GLOBALS['phpgw']->common->show_date(time(),"l")) . " "
				. lang($GLOBALS['phpgw']->common->show_date(time(),"F")) . " "
				. $GLOBALS['phpgw']->common->show_date(time(),"d, Y");

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
		$tpl->set_Var($var);
		$tpl->pfp('out','navbar');
		// If the application has a header include, we now include it
		if (!@$GLOBALS['phpgw_info']['flags']['noappheader'] && $GLOBALS['menuaction'])
		{
			if (is_array($GLOBALS['obj']->public_functions) && $GLOBALS['obj']->public_functions['header'])
			{
				eval("\$GLOBALS['obj']->header();");
			}
		}
		$GLOBALS['phpgw']->common->hook('after_navbar');
		return;
	}

	function parse_navbar_end()
	{
		if ($GLOBALS['phpgw_info']['server']['showpoweredbyon'] == 'bottom')
		{
			$msg = "<P><P>\n" . lang('Powered by phpGroupWare version x', $GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
 
			$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
			$tpl->set_unknowns('remove');
   
			$tpl->set_file(
				array(
					'footer' => 'footer.tpl'
				)
			);
			$var = Array(
				'table_bg_color'	=> $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'msg'			=> $msg,
				'version'		=> $GLOBALS['phpgw_info']['server']['versions']['phpgwapi']
			);
			$tpl->set_var($var);
			$GLOBALS['phpgw']->common->hook('navbar_end');
			$tpl->pfp('out','footer');
		}
	}
