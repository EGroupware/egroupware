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

		$tpl->set_file(
			array(
				'navbartpl' => 'navbar.tpl'
			)
		);
		$tpl->set_block('navbartpl','preferences');
		$tpl->set_block('navbartpl','navbar');

		$var['img_root'] = $GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/templates/verdilak/images';
		$var['table_bg_color'] = $GLOBALS['phpgw_info']['theme']['navbar_bg'];
		$var['navbar_text'] = $GLOBALS['phpgw_info']['theme']['navbar_text'];
		$applications = '';
		foreach($GLOBALS['phpgw_info']['navbar'] as $app => $app_data)
		{
			if ($app != 'home' && $app != 'preferences' && ! ereg('about',$app) && $app != 'logout')
			{
				if ($GLOBALS['phpgw_info']['user']['preferences']['common']['navbar_format'] != 'text')
				{
					$title = '<img src="' . $app_data['icon'] . '" alt="' . $app_data['title'] . '" title="'
						. $app_data['title'] . '" border="0">';
				}

				if ($GLOBALS['phpgw_info']['user']['preferences']['common']['navbar_format'] != 'icons')
				{
					$title .= '<br>' . $app_data['title'];
				}
				$applications .= '<br><a href="' . $app_data['url'] . '"';
				if (isset($GLOBALS['phpgw_info']['flags']['navbar_target']) &&
				    $GLOBALS['phpgw_info']['flags']['navbar_target'])
				{
					$applications .= ' target="' . $GLOBALS['phpgw_info']['flags']['navbar_target'] . '"';
				}
				$applications .= '>' . $title . '</a>';
				unset($title);
			}
		}
		$var['applications'] = $applications;

		if (isset($GLOBALS['phpgw_info']['theme']['special_logo']))
		{
			$var['logo'] = $GLOBALS['phpgw_info']['theme']['special_logo'];
		}
		else
		{
			$var['logo'] = 'logo.gif';
		}

		$var['home_link'] = $GLOBALS['phpgw_info']['navbar']['home']['url'];
		$var['preferences_link'] = $GLOBALS['phpgw_info']['navbar']['preferences']['url'];
		$var['logout_link'] = $GLOBALS['phpgw_info']['navbar']['logout']['url'];
		$var['help_link'] = $GLOBALS['phpgw_info']['navbar']['about']['url'];

		if ($GLOBALS['phpgw_info']['flags']['currentapp'] == 'home')
		{
			$var['welcome_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','welcome-red');
		}
		else
		{
			$var['welcome_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','welcome-grey');
		}

		if ($GLOBALS['phpgw_info']['flags']['currentapp'] == 'preferences')
		{
			$var['preferences_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','preferences-red');
		}
		else
		{
			$var['preferences_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','preferences-grey');
		}
		$var['logout_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','logout-grey');

		$var['powered_by'] = lang('Powered by phpGroupWare version %1',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);

		if (isset($GLOBALS['phpgw_info']['navbar']['admin']) && $GLOBALS['phpgw_info']['user']['preferences']['common']['show_currentusers'])
		{
			$var['current_users'] = '<a style="font-family: Geneva,Arial,Helvetica,sans-serif; font-size: 12pt;" href="'
				. $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions') . '">&nbsp;'
				. lang('Current users') . ': ' . $GLOBALS['phpgw']->session->total() . '</a>';
		}
		$now = time();
		$var['user_info'] = $GLOBALS['phpgw']->common->display_fullname() . ' - '
			. lang($GLOBALS['phpgw']->common->show_date($now,'l')) . ' '
			. $GLOBALS['phpgw']->common->show_date($now,$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
//			. lang($GLOBALS['phpgw']->common->show_date($now,'F')) . ' '
//			. $GLOBALS['phpgw']->common->show_date($now,'d, Y');

		// Maybe we should create a common function in the phpgw_accounts_shared.inc.php file
		// to get rid of duplicate code.
		if ($GLOBALS['phpgw_info']['user']['lastpasswd_change'] == 0)
		{
			$api_messages = lang('You are required to change your password during your first login')
                      . '<br> Click this image on the navbar: <img src="'
                      . $GLOBALS['phpgw']->common->image('preferences','navbar.gif').'">';
		}
		else if ($GLOBALS['phpgw_info']['user']['lastpasswd_change'] < time() - (86400*30))
		{
			$api_messages = lang('it has been more then %1 days since you changed your password',30);
		}
 
		// This is gonna change
		if (isset($cd))
		{
			$var['messages'] = $api_messages . '<br>' . checkcode($cd);
		}
		if (isset($GLOBALS['phpgw_info']['flags']['app_header']))
		{
			$var['current_app_header'] = $GLOBALS['phpgw_info']['flags']['app_header'];
			$var['th_bg'] = $GLOBALS['phpgw_info']['theme']['th_bg'];
		}
		else
		{
			$tpl->set_block('navbar','app_header','app_header');
			$var['app_header'] = '';
		}
		$tpl->set_var($var);
		// check if user is allowed to change his prefs
		if ($GLOBALS['phpgw_info']['user']['apps']['preferences'])
		{
			$tpl->parse('preferences_icon','preferences');
		}
		else
		{
			$tpl->set_var('preferences_icon','');
		}
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
		$GLOBALS['phpgw']->hooks->process('after_navbar');
		return;
	}

	function parse_navbar_end()
	{
		$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
  
		$tpl->set_file(
			array(
				'footer' => 'footer.tpl'
			)
		);
		$var = Array(
			'img_root'	=> $GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/templates/verdilak/images',
			'table_bg_color'	=> $GLOBALS['phpgw_info']['theme']['navbar_bg'],
			'version'	=> $GLOBALS['phpgw_info']['server']['versions']['phpgwapi']
		);
		if (isset($GLOBALS['phpgw_info']['navbar']['admin']) && $GLOBALS['phpgw_info']['user']['preferences']['common']['show_currentusers'])
		{
			$var['current_users'] = '<a style="font-family: Geneva,Arial,Helvetica,sans-serif; font-size: 12pt;" href="'
				. $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions') . '">&nbsp;'
				. '<font color="white">'.lang('Current users') . ': ' . $GLOBALS['phpgw']->session->total() . '</font></a>';
		}
		$now = time();
		$var['user_info'] = $GLOBALS['phpgw']->common->display_fullname() . ' - '
			. lang($GLOBALS['phpgw']->common->show_date($now,'l')) . ' '
			. $GLOBALS['phpgw']->common->show_date($now,$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
		$var['powered_by'] = lang('Powered by phpGroupWare version %1',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);

		$tpl->set_var($var);
		$GLOBALS['phpgw']->hooks->process('navbar_end');
		echo $tpl->pfp('out','footer');
	}
