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

		//$tpl->set_block('navbar','B_powered_top','V_powered_top');
		//$tpl->set_block('navbar','B_num_users','V_num_users');

		$var['img_root'] = PHPGW_IMAGES_DIR;
		$var['img_root_roll'] = PHPGW_IMAGES_DIR . '/rollover';
		$var['table_bg_color'] = $GLOBALS['phpgw_info']['theme']['navbar_bg'];

		#  echo '<pre>'; print_r($GLOBALS['phpgw_info']['navbar']); echo '</pre>';
		$applications = '';
		while ($app = each($GLOBALS['phpgw_info']['navbar']))
		{
			if ($app[1]['title'] != 'Home' && $app[1]['title'] != 'preferences' && ! ereg('About',$app[1]['title']) && $app[1]['title'] != 'Logout')
			{
				$title = lang($app[1]['title']);

				$applications .= '<tr><td class="main_menu_apps"><a class="main_menu" href="' . $app[1]['url'] . '"';
				if (isset($GLOBALS['phpgw_info']['flags']['navbar_target']))
				{
					$applications .= ' target="' . $GLOBALS['phpgw_info']['flags']['navbar_target'] . '"';
				}

				$applications .= '>'.$title.'</a></td></tr>'."\r\n";
			}
			$img_src_over = $GLOBALS['phpgw']->common->image($app[0],'navbar-over.gif');
			if($img_src_over)
			{
				$pre_load[] = $img_src_over;
			}
		}

		$var['applications'] = $applications;
     
		$var['home_link'] 	= $GLOBALS['phpgw_info']['navbar']['home']['url'];
		$var['preferences_link'] = $GLOBALS['phpgw_info']['navbar']['preferences']['url'];
		$var['logout_link'] 	= $GLOBALS['phpgw_info']['navbar']['logout']['url'];
		$var['help_link'] 	= $GLOBALS['phpgw_info']['navbar']['about']['url'];
		$var['lang_welcome']	= lang('welcome');
		$var['lang_preferences']	= lang('preferences');
		$var['lang_logout']	= lang('logout');
		$var['lang_help']	= lang('help');

		// "powered_by_color" and "_size" are is also used by number of current users thing
		$var['powered_by_size'] = '2';
		$var['powered_by_color'] = '#ffffff';
		if ($GLOBALS['phpgw_info']['server']['showpoweredbyon'] == 'top')
		{
			$var['powered_by'] = lang('Powered by phpGroupWare version x',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
			$tpl->set_var($var);
		}
		else
		{
			$var['powered_by'] = '';
			$tpl->set_var($var);
		}
		$var['phpgw_version'] = lang("version").": ".$GLOBALS['phpgw_info']['server']['versions']['phpgwapi'];
		$tpl->set_var($var);

		if (isset($GLOBALS['phpgw_info']['navbar']['admin']) && isset($GLOBALS['phpgw_info']['user']['preferences']['common']['show_currentusers']))
		{
			$db  = $GLOBALS['phpgw']->db;
			$db->query('select count(session_id) from phpgw_sessions');
			$db->next_record();
			$var['current_users'] = '<a href="' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions')
			 	. '">&nbsp;' . lang('Current users') . ': ' . $db->f(0) . '</a>';
			$tpl->set_var($var);
		}
		else
		{
			$var['current_users'] = '';
			$tpl->set_var($var);
		}

		$var['user_info_name'] = $GLOBALS['phpgw']->common->display_fullname();
		$now = time();
		$var['user_info_date'] =
				  lang($GLOBALS['phpgw']->common->show_date($now,'l')) . ' '
				. $GLOBALS['phpgw']->common->show_date($now,$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
//				. lang($GLOBALS['phpgw']->common->show_date($now,'F')) . ' '
//				. $GLOBALS['phpgw']->common->show_date($now,'d, Y');
		$var['user_info'] = $var['user_info_name'] .' - ' .$var['user_info_date'];
		$var['user_info_size'] = '2';
		$var['user_info_color'] = '#000000';

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
		if (!@$GLOBALS['phpgw_info']['flags']['noappheader'] && @isset($GLOBALS['HTTP_GET_VARS']['menuaction']))
		{
			list($app,$class,$method) = explode('.',$GLOBALS['HTTP_GET_VARS']['menuaction']);
			if (is_array($GLOBALS[$class]->public_functions) && $GLOBALS[$class]->public_functions['header'])
			{
				$GLOBALS[$class]->header();
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
