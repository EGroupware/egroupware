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
	$GLOBALS['phpgw_info']['flags'] = array(
//		'disable_template_class' => True,
		'login'                  => True,
		'currentapp'             => 'login',
		'noheader'               => True
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
	$GLOBALS['phpgw']->template = CreateObject('phpgwapi.Template', $GLOBALS['phpgw_info']['server']['template_dir']);
	$GLOBALS['phpgw']->template->set_file('phpgw', 'phpgw.tpl');
	$GLOBALS['phpgw']->template->set_file('login','login.tpl');
	$GLOBALS['phpgw']->template->set_file('msgbox', 'msgbox.tpl');

	// This is used for system downtime, to prevent new logins.
	if ($GLOBALS['phpgw_info']['server']['deny_all_logins'])
	{
		$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_basic_start','phpgw_main_start');
		$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_basic_end','phpgw_main_end');
		$GLOBALS['phpgw']->template->set_block('login','login_form_deny','login_form');
		$GLOBALS['phpgw']->template->set_var('template_set','default');
		$GLOBALS['phpgw']->template->set_var('phpgw_head_tags','<script><!-- if (window!= top) top.location.href=location.href// --></script>');
		$GLOBALS['phpgw']->template->fp('phpgw_body','login_form');
		$GLOBALS['phpgw']->template->pfp('out','phpgw_main_start');
		$GLOBALS['phpgw']->template->pfp('out','phpgw_main_end');
		exit;
	}

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
		//$GLOBALS['phpgw']->template = CreateObject('phpgwapi.Template');
		$GLOBALS['phpgw']->common = CreateObject('phpgwapi.common');
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
				$GLOBALS['phpgw']->template->set_var('phpgw_loginscreen_message',stripslashes(lang('loginscreen_message')));
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
				$GLOBALS['phpgw']->template->set_var('phpgw_loginscreen_message',stripslashes(lang('loginscreen_message')));
			}
		}
	}

	if (!isset($GLOBALS['HTTP_GET_VARS']['code']) || !$GLOBALS['HTTP_GET_VARS']['code'])
	{
		$GLOBALS['HTTP_GET_VARS']['code'] = '';
	}

	if ($GLOBALS['phpgw_info']['server']['show_domain_selectbox'])
	{
		$GLOBALS['phpgw']->template->set_block('login','login_form_select_domain','login_form');
		reset($phpgw_domain);
		unset($domain_select);      // For security ... just in case
		while ($domain = each($phpgw_domain))
		{
			$domain_select .= '<option value="' . $domain[0] . '"';
			if ($domain[0] == $last_domain)
			{
				$domain_select .= ' selected';
			}
			$domain_select .= '>' . $domain[0] . '</option>';
		}
		$GLOBALS['phpgw']->template->set_var('select_domain',$domain_select);
	}
	else
	{
		$GLOBALS['phpgw']->template->set_block('login','login_form_standard','login_form');
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
	$GLOBALS['phpgw']->common->msgbox('', False,'phpgw_login_msgbox');
	
	$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_basic_start','phpgw_main_start');
	$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_basic_end','phpgw_main_end');
	$GLOBALS['phpgw']->template->set_var('phpgw_head_charset',lang('charset'));
	$GLOBALS['phpgw']->template->set_var('phpgw_head_description','phpGroupWare - Login Page');
	$GLOBALS['phpgw']->template->set_var('phpgw_head_keywords','phpGroupWare');
	$GLOBALS['phpgw']->template->set_var('phpgw_head_base',$GLOBALS['phpgw_info']['server']['webserver_url'].'/');
	$GLOBALS['phpgw']->template->set_var('phpgw_head_target','_self');
	$GLOBALS['phpgw']->template->set_var('phpgw_head_browser_ico','favicon.ico');
	$GLOBALS['phpgw']->template->set_var('phpgw_head_website_title', $GLOBALS['phpgw_info']['server']['site_title']);
	$GLOBALS['phpgw']->template->set_var('phpgw_body_tags','bgcolor="#FFFFFF"');
	$GLOBALS['phpgw']->template->set_var('login_url', 'login.php' . $extra_vars);
	$GLOBALS['phpgw']->template->set_var('registration_url','registration/');
	$GLOBALS['phpgw']->template->set_var('cookie',show_cookie());
	$GLOBALS['phpgw']->template->set_var('lang_username',lang('username'));
	$GLOBALS['phpgw']->template->set_var('lang_phpgw_login',lang('phpGroupWare login'));
	$GLOBALS['phpgw']->template->set_var('version',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
	$GLOBALS['phpgw']->template->set_var('lang_password',lang('password'));
	$GLOBALS['phpgw']->template->set_var('lang_login',lang('login'));
	$GLOBALS['phpgw']->template->set_var('template_set',$GLOBALS['phpgw_info']['login_template_set']);
	$GLOBALS['phpgw']->template->fp('phpgw_body','login_form');
	$GLOBALS['phpgw']->template->pfp('out','phpgw_main_start');
	$GLOBALS['phpgw']->template->pfp('out','phpgw_main_end');
?>
