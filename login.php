<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* Written by Dan Kuykendall <seek3r@phpgroupware.org>                      *
	*            Joseph Engo    <jengo@phpgroupware.org>                       *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$phpgw_info = array();
	$GLOBALS['phpgw_info']['flags'] = array
	(
		'login'			=> True,
		'currentapp'	=> 'login',
		'noheader'		=> True,
	);

	if(file_exists('./header.inc.php'))
	{
		include('./header.inc.php');
	}
	else
	{
		Header('Location: setup/index.php');
		exit;
	}
	
	$GLOBALS['phpgw_info']['server']['template_dir'] = PHPGW_SERVER_ROOT . '/phpgwapi/templates/' . $GLOBALS['phpgw_info']['login_template_set'];

	if (!$GLOBALS['phpgw_info']['login_theme'])
	{
		$GLOBALS['phpgw_info']['login_theme'] = 'submarine';
	}

	$GLOBALS['phpgw']->common = CreateObject('phpgwapi.common');

	$GLOBALS['phpgw']->xslttpl = CreateObject('phpgwapi.xslttemplates',$GLOBALS['phpgw_info']['server']['template_dir']);
	$GLOBALS['phpgw']->xslttpl->add_file('login');

	$data = array
	(
		'phpgw_theme'				=> 'phpgwapi/templates/' . $GLOBALS['phpgw_info']['login_template_set'] . '/css/phpgw.css',
		'login_theme'				=> 'phpgwapi/templates/' . $GLOBALS['phpgw_info']['login_template_set'] . '/css/' . $GLOBALS['phpgw_info']['login_theme'] . '.css',
		'phpgw_head_charset'		=> lang('charset'),
		'phpgw_head_website_title'	=> $GLOBALS['phpgw_info']['server']['site_title']
	);

	$data['login_standard'] = array
	(
		'login_layout'			=> $GLOBALS['phpgw_info']['login_template_set'],
		'lang_phpgw_statustext'	=> lang('phpGroupWare --> homepage')
	);

	// This is used for system downtime, to prevent new logins.
	if ($GLOBALS['phpgw_info']['server']['deny_all_logins'])
	{
		$GLOBALS['phpgw']->xslttpl->set_var('login',$data);
		$GLOBALS['phpgw']->xslttpl->pp();
		exit;
	}

	$data['login_standard']['loginscreen'] = True;

	function show_cookie()
	{
		/* This needs to be this way, because if someone doesnt want to use cookies, we shouldnt sneak one in */
		if ($GLOBALS['HTTP_GET_VARS']['code'] != 5 && (isset($GLOBALS['phpgw_info']['server']['usecookies']) && $GLOBALS['phpgw_info']['server']['usecookies']))
		{
			return $GLOBALS['HTTP_COOKIE_VARS']['last_loginid'];
		}
	}

	function check_logoutcode()
	{
		switch($GLOBALS['HTTP_GET_VARS']['code'])
		{
			case 1:
				$GLOBALS['phpgw_info']['flags']['msgbox_data']['You have been successfully logged out'] = True;
				break;
			case 2:
				$GLOBALS['phpgw_info']['flags']['msgbox_data']['Sorry, your login has expired'] = False;
				break;
			case 5:
				$GLOBALS['phpgw_info']['flags']['msgbox_data']['Bad login or password'] = False;
				break;
			case 10:
				if($GLOBALS['phpgw_info']['server']['usecookies'])
				{
					Setcookie('sessionid');
					Setcookie('kp3');
					Setcookie('domain');
				}
				$GLOBALS['phpgw_info']['flags']['msgbox_data']['Your session could not be verified'] = False;
				break;
		}
	}

	/* Program starts here */
  
	if ($GLOBALS['phpgw_info']['server']['auth_type'] == 'http' && isset($PHP_AUTH_USER))
	{
		$submit = True;
		$login  = $PHP_AUTH_USER;
		$passwd = $PHP_AUTH_PW;
	}

	# Apache + mod_ssl style SSL certificate authentication
	# Certificate (chain) verification occurs inside mod_ssl
	if ($GLOBALS['phpgw_info']['server']['auth_type'] == 'sqlssl' && isset($HTTP_SERVER_VARS['SSL_CLIENT_S_DN']) && !isset($GLOBALS['HTTP_GET_VARS']['code']))
	{
		# an X.509 subject looks like:
		# /CN=john.doe/OU=Department/O=Company/C=xx/Email=john@comapy.tld/L=City/
		# the username is deliberately lowercase, to ease LDAP integration
		$sslattribs = explode('/',$HTTP_SERVER_VARS['SSL_CLIENT_S_DN']);
		# skip the part in front of the first '/' (nothing)
		while ($sslattrib = next($sslattribs))
		{
			list($key,$val) = explode('=',$sslattrib);
			$sslattributes[$key] = $val;
		}

		if (isset($sslattributes['Email']))
		{
			$submit = True;

			# login will be set here if the user logged out and uses a different username with
			# the same SSL-certificate.
			if (!isset($login)&&isset($sslattributes['Email']))
			{
				$login = $sslattributes['Email'];
				# not checked against the database, but delivered to authentication module
				$passwd = $HTTP_SERVER_VARS['SSL_CLIENT_S_DN'];
			}
		}
		unset($key);
		unset($val);
		unset($sslattributes);
	}

	if (isset($GLOBALS['HTTP_POST_VARS']['passwd_type']) || $submit_x || $submit_y)
//		 isset($GLOBALS['HTTP_POST_VARS']['passwd']) && $GLOBALS['HTTP_POST_VARS']['passwd']) // enable konqueror to login via Return
	{
		if (getenv(REQUEST_METHOD) != 'POST' && $_SERVER['REQUEST_METHOD'] != 'POST'
			&& !isset($PHP_AUTH_USER) && !isset($HTTP_SERVER_VARS['SSL_CLIENT_S_DN']))
		{
			$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/login.php','code=5'));
		}
		$GLOBALS['sessionid'] = $GLOBALS['phpgw']->session->create($GLOBALS['HTTP_POST_VARS']['login'],$GLOBALS['HTTP_POST_VARS']['passwd'],$GLOBALS['HTTP_POST_VARS']['passwd_type']);

		if(!isset($GLOBALS['sessionid']) || !$GLOBALS['sessionid'])
		{
			$GLOBALS['phpgw']->redirect($GLOBALS['phpgw_info']['server']['webserver_url'] . '/login.php?code=5');
		}
		else
		{
			if ($GLOBALS['phpgw_forward'])
			{
				while (list($name,$value) = each($GLOBALS['HTTP_GET_VARS']))
				{
					if (ereg('phpgw_',$name))
					{
						$extra_vars .= '&' . $name . '=' . urlencode($value);
					}
				}
			}
			$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/home.php','code=yes' . $extra_vars,True));
		}
	}
	else
	{
		// !!! DONT CHANGE THESE LINES !!!
		// If there is something wrong with this code TELL ME!
		// Commenting out the code will not fix it. (jengo)
		if (isset($GLOBALS['HTTP_COOKIE_VARS']['last_loginid']))
		{
			$accounts = CreateObject('phpgwapi.accounts');
			$prefs = CreateObject('phpgwapi.preferences', $accounts->name2id($last_loginid));

			if (! $prefs->account_id)
			{
				$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] = 'en';
			}
			else
			{
				$GLOBALS['phpgw_info']['user']['preferences'] = $prefs->read_repository();
			}
			#print 'LANG:' . $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] . '<br>';
			$GLOBALS['phpgw']->translation->add_app('login');
			$GLOBALS['phpgw']->translation->add_app('loginscreen');
			if (lang('loginscreen_message') != 'loginscreen_message*')
			{
				$data['login_standard']['phpgw_loginscreen_message'] = stripslashes(lang('loginscreen_message'));
			}
		}
		else
		{
			// If the lastloginid cookies isn't set, we will default to english.
			// Change this if you need.
			$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] = 'en';
			$GLOBALS['phpgw']->translation->add_app('login');
			$GLOBALS['phpgw']->translation->add_app('loginscreen');
			if (lang('loginscreen_message') != 'loginscreen_message*')
			{
				$data['login_standard']['phpgw_loginscreen_message'] = stripslashes(lang('loginscreen_message'));
			}
		}
	}

	if (!isset($GLOBALS['HTTP_GET_VARS']['code']) || !$GLOBALS['HTTP_GET_VARS']['code'])
	{
		$GLOBALS['HTTP_GET_VARS']['code'] = '';
	}

	if ($GLOBALS['phpgw_info']['server']['show_domain_selectbox'])
	{
		reset($phpgw_domain);
		while ($domain = each($phpgw_domain))
		{
			if ($domain[0] == $last_domain)
			{
				$select = 'selected';
			}

			$data['login_standard']['domain_select'] = array
			(
				'domain'	=> $domain[0],
				'selected'	=> $selected
			);
		}

		for ($i=0;$i<count($data['login_standard']['domain_select']);$i++)
		{
			if ($data['login_standard']['domain_select'][$i]['selected'] != 'selected')
			{
				unset($data['login_standard']['domain_select'][$i]['selected']);
			}
		}
	}

	while (list($name,$value) = each($GLOBALS['HTTP_GET_VARS']))
	{
		if (ereg('phpgw_',$name))
		{
			$extra_vars .= '&' . $name . '=' . urlencode($value);
		}
	}

	if ($extra_vars)
	{
		$extra_vars = '?' . substr($extra_vars,1,strlen($extra_vars));
	}
	check_logoutcode();

	/*$GLOBALS['phpgw']->template->set_var('phpgw_head_base',$GLOBALS['phpgw_info']['server']['webserver_url'].'/');
	$GLOBALS['phpgw']->template->set_var('registration_url','registration/');*/

	if($GLOBALS['phpgw_info']['flags']['msgbox_data'])
	{
		$data['login_standard']['msgbox_data'] = $GLOBALS['phpgw']->common->msgbox('',False);
	}

	$data['login_standard']['website_title']	= $GLOBALS['phpgw_info']['server']['site_title'];
	$data['login_standard']['login_url']		= 'login.php' . $extra_vars;
	$data['login_standard']['cookie']			= show_cookie();
	$data['login_standard']['lang_username']	= lang('username');
	$data['login_standard']['lang_powered_by']	= lang('powered by');
	$data['login_standard']['lang_version']		= lang('version');
	$data['login_standard']['phpgw_version']	= $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'];
	$data['login_standard']['lang_password']	= lang('password');
	$data['login_standard']['lang_login']		= lang('login');

	//_debug_array($data);

	$GLOBALS['phpgw']->xslttpl->set_var('login',$data,False);
?>
