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
		global $phpgw_info, $phpgw;

		$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
		$tpl->set_unknowns('remove');

		$tpl->set_file(array(
			'navbar' => 'navbar.tpl'
		));

		$tpl->set_block('navbar','B_powered_top','V_powered_top');

		$tpl->set_var('img_root',PHPGW_IMAGES_DIR);
		$tpl->set_var('img_root_roll',PHPGW_IMAGES_DIR . '/rollover');
		$tpl->set_var('table_bg_color',$phpgw_info['theme']['navbar_bg']);

		#  echo '<pre>'; print_r($phpgw_info['navbar']); echo '</pre>';
		$applications = '';
		while ($app = each($phpgw_info['navbar']))
		{
			if ($app[1]['title'] != 'Home' && $app[1]['title'] != 'preferences' && ! ereg('About',$app[1]['title']) && $app[1]['title'] != 'Logout')
			{
				$title = '<img src="' . $app[1]['icon'] . '" alt="' . $app[1]['title'] . '" title="'
					. lang($app[1]['title']) . '" border="0" name="' . $app[0] . '">';

				$img_src_over = $phpgw->common->image($app[0],'navbar-over.gif');
				$img_src_out = $phpgw->common->image($app[0],'navbar.gif');

				// onMouseOver="two.src='rollover/admin_over.gif'" onMouseOut="two.src='images/admin.gif'"><img src="images/admin.gif" border="0" name="two"
				$applications .= '<tr><td><a href="' . $app[1]['url'] . '"';
				if (isset($phpgw_info['flags']['navbar_target']))
				{
					$applications .= ' target="' . $phpgw_info['flags']['navbar_target'] . '"';
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
			$img_src_over = $phpgw->common->image($app[0],'navbar-over.gif');
			if($img_src_over)
			{
				$pre_load[] = $img_src_over;
			}
		}

		$tpl->set_var('app_images',implode("','",$pre_load));

		$tpl->set_var('applications',$applications);
     
		$tpl->set_var('home_link',$phpgw_info['navbar']['home']['url']);
		$tpl->set_var('preferences_link',$phpgw_info['navbar']['preferences']['url']);
		$tpl->set_var('logout_link',$phpgw_info['navbar']['logout']['url']);
		$tpl->set_var('help_link',$phpgw_info['navbar']['about']['url']);

		if ($phpgw_info['flags']['currentapp'] == 'home')
		{
			$tpl->set_var('welcome_img',PHPGW_IMAGES_DIR . '/welcome-red.gif');
		}
		else
		{
			$tpl->set_var('welcome_img',PHPGW_IMAGES_DIR . '/welcome-grey.gif');
		}

		if ($phpgw_info['flags']['currentapp'] == 'preferences')
		{
			$tpl->set_var('preferences_img',PHPGW_IMAGES_DIR . '/preferences-red.gif');
		}
		else
		{
			$tpl->set_var('preferences_img',PHPGW_IMAGES_DIR . '/preferences-grey.gif');
		}
		$tpl->set_var('logout_img',PHPGW_IMAGES_DIR . '/logout-grey.gif');

		if ($phpgw_info['server']['showpoweredbyon'] == 'top')
		{
			$tpl->set_var("powered_by",lang("Powered by phpGroupWare version x",$phpgw_info["server"]["versions"]["phpgwapi"]));
			$tpl->set_var('power_size','2');
			$tpl->parse('V_powered_top','B_powered_top');
		}
		else
		{
			$tpl->set_var('V_powered_top','');
		}

		if (isset($phpgw_info['navbar']['admin']) && isset($phpgw_info['user']['preferences']['common']['show_currentusers']))
		{
			$db  = $phpgw->db;
			$db->query('select count(session_id) from phpgw_sessions');
			$db->next_record();
			$tpl->set_var("current_users",'<a href="' . $phpgw->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions')
			 	. '">&nbsp;' . lang('Current users') . ': ' . $db->f(0) . '</a>');
		}
		$tpl->set_var('user_info',$phpgw->common->display_fullname() . ' - '
			. lang($phpgw->common->show_date(time(),'l')) . ' '
			. lang($phpgw->common->show_date(time(),'F')) . ' '
			. $phpgw->common->show_date(time(),'d, Y'));

		// Maybe we should create a common function in the phpgw_accounts_shared.inc.php file
		// to get rid of duplicate code.
/*		if ($phpgw_info['user']['lastpasswd_change'] == 0)
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
*/
		$tpl->pfp('out','navbar');
		// If the application has a header include, we now include it
		if (!@$GLOBALS['phpgw_info']['flags']['noappheader'] && $GLOBALS['menuaction'])
		{
			if (is_array($GLOBALS['obj']->public_functions) && $GLOBALS['obj']->public_functions['header'])
			{
				eval("\$GLOBALS['obj']->header();");
			}
		}
		$phpgw->common->hook('after_navbar');
		return;
	}

	function parse_navbar_end()
	{
		global $phpgw_info, $phpgw;
		$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
		$tpl->set_unknowns('remove');

		$tpl->set_file(array('footer' => 'footer.tpl'));
		$tpl->set_block('footer','B_powered_bottom','V_powered_bottom');

		if ($phpgw_info["server"]["showpoweredbyon"] == "bottom")
		{
			$powered = lang("Powered by phpGroupWare version x", $phpgw_info["server"]["versions"]["phpgwapi"]);
			$tpl->set_var('powered',$powered);
			$tpl->set_var('img_root',PHPGW_IMAGES_DIR);
			$tpl->set_var('power_backcolor',$phpgw_info['theme']['navbar_bg']);
			$tpl->set_var('power_textcolor',$phpgw_info['theme']['navbar_text']);
			//$tpl->set_var('version',$phpgw_info['server']['versions']['phpgwapi']);
 			$tpl->parse('V_powered_bottom','B_powered_bottom');
		}
		else
		{
			$tpl->set_var('V_powered_bottom','');
		}

		$phpgw->common->hook('navbar_end');
		$tpl->pfp('out','footer');
	}
