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
		$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
		$tpl->set_unknowns('remove');

		$tpl->set_file(
			array(
				'navbar' => 'navbar.tpl'
			)
		);

		$tpl->set_block('navbar','B_powered_top','V_powered_top');

		$var['img_root'] = PHPGW_IMAGES_DIR;
		$var['img_root_roll'] = PHPGW_IMAGES_DIR . '/rollover';
		$var['table_bg_color'] = $GLOBALS['phpgw_info']['theme']['navbar_bg'];

		#  echo '<pre>'; print_r($GLOBALS['phpgw_info']['navbar']); echo '</pre>';
		$applications = '';
		while ($app = each($GLOBALS['phpgw_info']['navbar']))
		{
			if ($app[1]['title'] != 'Home' && $app[1]['title'] != 'preferences' && ! ereg('About',$app[1]['title']) && $app[1]['title'] != 'Logout')
			{
				$title = '<img src="' . $app[1]['icon'] . '" alt="' . $app[1]['title'] . '" title="'
					. lang($app[1]['title']) . '" border="0" name="' . $app[0] . '">';

				$img_src_over = $GLOBALS['phpgw']->common->image($app[0],'navbar-over.gif');
				$img_src_out = $GLOBALS['phpgw']->common->image($app[0],'navbar.gif');

				// onMouseOver="two.src='rollover/admin_over.gif'" onMouseOut="two.src='images/admin.gif'"><img src="images/admin.gif" border="0" name="two"
				$applications .= '<tr><td><a href="' . $app[1]['url'] . '"';
				if (isset($GLOBALS['phpgw_info']['flags']['navbar_target']))
				{
					$applications .= ' target="' . $GLOBALS['phpgw_info']['flags']['navbar_target'] . '"';
				}

				if($img_src_over != '')
				{
					$applications .= ' onMouseOver="' . $app[0] . '.src=\'' . $img_src_over . '\'" ';
				}
				if($img_src_out != '')
				{
					$applications .= ' onMouseOut="' . $app[0] . '.src=\'' . $img_src_out . '\'"';
				}
				$applications .= '>'.$title.'</a></td></tr>'."\n";
			}
			$img_src_over = $GLOBALS['phpgw']->common->image($app[0],'navbar-over.gif');
			if($img_src_over)
			{
				$pre_load[] = $img_src_over;
			}
		}

		$var['app_images'] = implode("','",$pre_load);

		$var['applications'] = $applications;
     
		$var['home_link'] = $GLOBALS['phpgw_info']['navbar']['home']['url'];
		$var['preferences_link'] = $GLOBALS['phpgw_info']['navbar']['preferences']['url'];
		$var['logout_link'] = $GLOBALS['phpgw_info']['navbar']['logout']['url'];
		$var['help_link'] = $GLOBALS['phpgw_info']['navbar']['about']['url'];

		if ($GLOBALS['phpgw_info']['flags']['currentapp'] == 'home')
		{
			$var['welcome_img'] = PHPGW_IMAGES_DIR . '/welcome-red.gif';
		}
		else
		{
			$var['welcome_img'] = PHPGW_IMAGES_DIR . '/welcome-grey.gif';
		}

		if ($GLOBALS['phpgw_info']['flags']['currentapp'] == 'preferences')
		{
			$var['preferences_img'] = PHPGW_IMAGES_DIR . '/preferences-red.gif';
		}
		else
		{
			$var['preferences_img'] = PHPGW_IMAGES_DIR . '/preferences-grey.gif';
		}
		$var['logout_img'] = PHPGW_IMAGES_DIR . '/logout-grey.gif';

		if ($GLOBALS['phpgw_info']['server']['showpoweredbyon'] == 'top')
		{
			$var['powered_by'] = lang('Powered by phpGroupWare version x',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
			$var['power_size'] = '2';
			$tpl->set_var($var);
			$tpl->parse('V_powered_top','B_powered_top');
		}
		else
		{
			$var['V_powered_top'] = '';
		}

		if (isset($GLOBALS['phpgw_info']['navbar']['admin']) && isset($GLOBALS['phpgw_info']['user']['preferences']['common']['show_currentusers']))
		{
			$db  = $GLOBALS['phpgw']->db;
			$db->query('select count(session_id) from phpgw_sessions');
			$db->next_record();
			$var['current_users'] = '<a href="' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions')
			 	. '">&nbsp;' . lang('Current users') . ': ' . $db->f(0) . '</a>';
		}
		$var['user_info'] = $GLOBALS['phpgw']->common->display_fullname() . ' - '
			. lang($GLOBALS['phpgw']->common->show_date(time(),'l')) . ' '
			. lang($GLOBALS['phpgw']->common->show_date(time(),'F')) . ' '
			. $GLOBALS['phpgw']->common->show_date(time(),'d, Y');

		// Maybe we should create a common function in the phpgw_accounts_shared.inc.php file
		// to get rid of duplicate code.
/*		if ($GLOBALS['phpgw_info']['user']['lastpasswd_change'] == 0)
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
*/
		$tpl->set_var($var);
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
		$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
		$tpl->set_unknowns('remove');

		$tpl->set_file(array('footer' => 'footer.tpl'));
		$tpl->set_block('footer','B_powered_bottom','V_powered_bottom');

		if ($GLOBALS['phpgw_info']['server']['showpoweredbyon'] == 'bottom')
		{
			$var = Array(
				'powered'	=> lang('Powered by phpGroupWare version x', $GLOBALS['phpgw_info']['server']['versions']['phpgwapi']),
				'img_root'	=> PHPGW_IMAGES_DIR,
				'power_backcolor'	=> $GLOBALS['phpgw_info']['theme']['navbar_bg'],
				'power_textcolor'	=> $GLOBALS['phpgw_info']['theme']['navbar_text']
//				'version'	=> $GLOBALS['phpgw_info']['server']['versions']['phpgwapi']
			);
			$tpl->set_var($var);
 			$tpl->parse('V_powered_bottom','B_powered_bottom');
		}
		else
		{
			$tpl->set_var('V_powered_bottom','');
		}

		$GLOBALS['phpgw']->common->hook('navbar_end');
		$tpl->pfp('out','footer');
	}
