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
		global $phpgw_info, $phpgw, $PHP_SELF, $menuaction, $obj;

		$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);

		$tpl->set_file(array(
			'navbar' => 'navbar.tpl'
		));

		$tpl->set_var('img_root',$phpgw_info['server']['webserver_url'] . '/phpgwapi/templates/verdilak/images');
		$tpl->set_var('table_bg_color',$phpgw_info['theme']['navbar_bg']);
		$applications = '';
		while ($app = each($phpgw_info['navbar']))
		{
			if ($app[1]['title'] != 'Home' && $app[1]['title'] != 'preferences' && ! ereg('About',$app[1]['title']) && $app[1]['title'] != 'Logout')
			{
				if ($phpgw_info['user']['preferences']['common']['navbar_format'] != 'text')
				{
					$title = '<img src="' . $app[1]['icon'] . '" alt="' . lang($app[1]['title']) . '" title="'
						. lang($app[1]['title']) . '" border="0">';
				}

				if ($phpgw_info['user']['preferences']['common']['navbar_format'] != 'icons')
				{
					$title .= '<br>' . lang($app[1]['title']);
				}
				$applications .= '<br><a href="' . $app[1]['url'] . '"';
				if (isset($phpgw_info['flags']['navbar_target']) &&
				    $phpgw_info['flags']['navbar_target'])
				{
					$applications .= ' target="' . $phpgw_info['flags']['navbar_target'] . '"';
				}
				$applications .= '>' . $title . '</a>';
				unset($title);
			}
		}
		$tpl->set_var('applications',$applications);

		if (isset($phpgw_info['theme']['special_logo']))
		{
			$tpl->set_var('logo',$phpgw_info['theme']['special_logo']);
		}
		else
		{
			$tpl->set_var('logo','logo.gif');
		}

		$tpl->set_var('home_link',$phpgw_info['navbar']['home']['url']);
		$tpl->set_var('preferences_link',$phpgw_info['navbar']['preferences']['url']);
		$tpl->set_var('logout_link',$phpgw_info['navbar']['logout']['url']);
		$tpl->set_var('help_link',$phpgw_info['navbar']['about']['url']);

		$ir = $phpgw_info['server']['webserver_url'] . '/phpgwapi/templates/verdilak/images';
		if ($phpgw_info['flags']['currentapp'] == 'home')
		{
			$tpl->set_var('welcome_img',$ir . '/welcome-red.gif');
		}
		else
		{
			$tpl->set_var('welcome_img',$ir . '/welcome-grey.gif');
		}

		if ($phpgw_info['flags']['currentapp'] == 'preferences')
		{
			$tpl->set_var('preferences_img',$ir . '/preferences-red.gif');
		}
		else
		{
			$tpl->set_var('preferences_img',$ir . '/preferences-grey.gif');
		}
		$tpl->set_var('logout_img',$ir . '/logout-grey.gif');

		$tpl->set_var('powered_by',lang('Powered by phpGroupWare version x',$phpgw_info['server']['versions']['phpgwapi']));

		if (isset($phpgw_info['navbar']['admin']) && isset($phpgw_info['user']['preferences']['common']['show_currentusers']))
		{
			$db  = $phpgw->db;
			$db->query("select count(*) from phpgw_sessions where session_flags != 'A'");
			$db->next_record();
			$tpl->set_var('current_users','<a style="font-family: Geneva,Arial,Helvetica,sans-serif; font-size: 12pt;" href="' . $phpgw->link('/admin/currentusers.php') . '">&nbsp;'
				. lang('Current users') . ': ' . $db->f(0) . '</a>');
		}
		$tpl->set_var('user_info',$phpgw->common->display_fullname() . ' - '
                             . lang($phpgw->common->show_date(time(),'l')) . ' '
                             . lang($phpgw->common->show_date(time(),'F')) . ' '
                             . $phpgw->common->show_date(time(),'d, Y'));

		// Maybe we should create a common function in the phpgw_accounts_shared.inc.php file
		// to get rid of duplicate code.
		if ($phpgw_info['user']['lastpasswd_change'] == 0)
		{
			$api_messages = lang('You are required to change your password during your first login')
                      . '<br> Click this image on the navbar: <img src="'
                      . $phpgw->common->image('preferences','navbar.gif').'">';
		}
		else if ($phpgw_info['user']['lastpasswd_change'] < time() - (86400*30))
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
		$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
  
		$tpl->set_file(array(
			'footer' => 'footer.tpl'
		));
		$tpl->set_var('img_root',$phpgw_info['server']['webserver_url'] . '/phpgwapi/templates/verdilak/images');
		$tpl->set_var('table_bg_color',$phpgw_info['theme']['navbar_bg']);
		$tpl->set_var('version',$phpgw_info['server']['versions']['phpgwapi']);

		$phpgw->common->hook('navbar_end');
		echo $tpl->pfp('out','footer');
	}
