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
	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags'] = array
	(
		'login'			=> True,
		'currentapp'	=> 'login',
		'noheader'		=> True,
	);

	if(file_exists('./header.inc.php'))
	{
		include('./header.inc.php');
		$GLOBALS['phpgw']->sessions = createObject('phpgwapi.sessions');
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

	function check_logoutcode()
	{
		switch($_GET['code'])
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
			case 99:
				$GLOBALS['phpgw_info']['flags']['msgbox_data']['Blocked, too many attempts'] = False;
				break;
			case 10:
				$GLOBALS['phpgw_info']['flags']['msgbox_data']['Your session could not be verified'] = False;

				$GLOBALS['phpgw']->sessions->phpgw_setcookie('sessionid');
				$GLOBALS['phpgw']->sessions->phpgw_setcookie('kp3');
				$GLOBALS['phpgw']->sessions->phpgw_setcookie('domain');

				//fix for bug php4 expired sessions bug
				if($GLOBALS['phpgw_info']['server']['sessions_type'] == 'php4')
				{
					$GLOBALS['phpgw']->sessions->phpgw_setcookie(PHPGW_PHPSESSID);
				}
				break;
		}
	}
	
	function check_langs()
	{
		//$f = fopen('/tmp/log','a'); fwrite($f,"\ncheck_langs()\n");
		if ($GLOBALS['phpgw_info']['server']['lang_ctimes'] && !is_array($GLOBALS['phpgw_info']['server']['lang_ctimes']))
		{
			$GLOBALS['phpgw_info']['server']['lang_ctimes'] = unserialize($GLOBALS['phpgw_info']['server']['lang_ctimes']);
		}
		
		$lang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
		$apps = $GLOBALS['phpgw_info']['user']['apps'];
		$apps['phpgwapi'] = true;	// check the api too
		while (list($app,$data) = each($apps))
		{
			$fname = PHPGW_SERVER_ROOT . "/$app/setup/phpgw_$lang.lang";
			
			if (file_exists($fname))
			{
				$ctime = filectime($fname);
				$ltime = intval($GLOBALS['phpgw_info']['server']['lang_ctimes'][$lang][$app]);
				//fwrite($f,"checking lang='$lang', app='$app', ctime='$ctime', ltime='$ltime'\n");
				
				if ($ctime != $ltime)
				{
					//fwrite($f,"\nupdate_langs()\n");
		
					update_langs();		// update all langs
					break;
				}
			}
		}
		//fclose ($f);
	}
	
	function update_langs()
	{
		$GLOBALS['phpgw_setup'] = CreateObject('phpgwapi.setup');
		$GLOBALS['phpgw_setup']->db = $GLOBALS['phpgw']->db;
		
		$GLOBALS['phpgw_setup']->detection->check_lang(false);	// get installed langs
		$langs = $GLOBALS['phpgw_info']['setup']['installed_langs'];
		while (list($lang) = @each($langs))
		{
			$langs[$lang] = $lang;
		}
		$_POST['submit'] = true;
		$_POST['lang_selected'] = $langs;
		$_POST['upgrademethod'] = 'dumpold';
		$included = 'from_login';
		
		include(PHPGW_SERVER_ROOT . '/setup/lang.php');
	}

	/* Program starts here */
	if ($GLOBALS['phpgw_info']['server']['auth_type'] == 'http' && isset($_SERVER['PHP_AUTH_USER']))
	{
		$submit = True;
		$login  = $_SERVER['PHP_AUTH_USER'];
		$passwd = $_SERVER['PHP_AUTH_PW'];
	}

	# Apache + mod_ssl style SSL certificate authentication
	# Certificate (chain) verification occurs inside mod_ssl
	if ($GLOBALS['phpgw_info']['server']['auth_type'] == 'sqlssl' && isset($_SERVER['SSL_CLIENT_S_DN']) && !isset($_GET['code']))
	{
		# an X.509 subject looks like:
		# /CN=john.doe/OU=Department/O=Company/C=xx/Email=john@comapy.tld/L=City/
		# the username is deliberately lowercase, to ease LDAP integration
		$sslattribs = explode('/',$_SERVER['SSL_CLIENT_S_DN']);
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
			if (!isset($_POST['login'])&&isset($sslattributes['Email'])) {
				$login = $sslattributes['Email'];
				# not checked against the database, but delivered to authentication module
				$passwd = $_SERVER['SSL_CLIENT_S_DN'];
			}
		}
		unset($key);
		unset($val);
		unset($sslattributes);
	}

	if (isset($_POST['passwd_type']) || $_POST['submit_x'] || $_POST['submit_y'] || $submit)
//		 isset($_POST['passwd']) && $_POST['passwd']) // enable konqueror to login via Return
	{
		if (getenv(REQUEST_METHOD) != 'POST' && $_SERVER['REQUEST_METHOD'] != 'POST'
			&& !isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['SSL_CLIENT_S_DN']))
		{
			$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/login.php','code=5'));
		}
		$login = $_POST['login'];
		if (strstr($login,'@') === False && isset($_POST['logindomain']))
		{
			$login .= '@' . $_POST['logindomain'];
		}
		$GLOBALS['sessionid'] = $GLOBALS['phpgw']->session->create($login,$_POST['passwd'],$_POST['passwd_type']);

		if(!isset($GLOBALS['sessionid']) || !$GLOBALS['sessionid'])
		{
			$GLOBALS['phpgw']->redirect($GLOBALS['phpgw_info']['server']['webserver_url'] . '/login.php?code=' . $GLOBALS['phpgw']->session->cd_reason);
		}
		else
		{
			if ($GLOBALS['phpgw_forward'])
			{
				while (list($name,$value) = each($_GET))
				{
					if (ereg('phpgw_',$name))
					{
						$extra_vars .= '&' . $name . '=' . urlencode($value);
					}
				}
			}
			check_langs();
			
			$GLOBALS['phpgw']->redirect_link('/home.php','cd=yes' . $extra_vars);
		}
	}
	else
	{
		// !!! DONT CHANGE THESE LINES !!!
		// If there is something wrong with this code TELL ME!
		// Commenting out the code will not fix it. (jengo)
		if (isset($_COOKIE['last_loginid']))
		{
			$accounts = CreateObject('phpgwapi.accounts');
			$prefs = CreateObject('phpgwapi.preferences', $accounts->name2id($_COOKIE['last_loginid']));

			if (! $prefs->account_id)
			{
				$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] = 'en';
			}
			else
			{
				$GLOBALS['phpgw_info']['user']['preferences'] = $prefs->read_repository();
			}
			#print 'LANG:' . $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] . '<br>';
		}
		else
		{
			// If the lastloginid cookies isn't set, we will default to english.
			// Change this if you need.
			$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] = 'en';
		}
		$GLOBALS['phpgw']->translation->add_app('login');
		$GLOBALS['phpgw']->translation->add_app('loginscreen');
		if (lang('loginscreen_message') != 'loginscreen_message*')
		{
			$data['login_standard']['phpgw_loginscreen_message'] = stripslashes(lang('loginscreen_message'));
		}
	}

	$last_loginid = $_COOKIE['last_loginid'];
	if ($GLOBALS['phpgw_info']['server']['show_domain_selectbox'])
	{
		foreach ($phpgw_domain as $domain => $domain_data)
		{
			$ds = array('domain' => $domain);
			if ($domain == $_COOKIE['last_domain'])
			{
				$ds['selected'] = 'selected';
			}
			$data['login_standard']['domain_select'][] = $ds;
		}
	}
	elseif ($last_loginid !== '')
	{
		reset($GLOBALS['phpgw_domain']);
		list($default_domain) = each($GLOBALS['phpgw_domain']);
		if ($_COOKIE['last_domain'] != $default_domain && !empty($_COOKIE['last_domain']))
		{
			$last_loginid .= '@' . $_COOKIE['last_domain'];
		}
	}

	while (list($name,$value) = each($_GET))
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

	$data['login_standard']['website_title']	= ($GLOBALS['phpgw_info']['server']['site_title']?$GLOBALS['phpgw_info']['server']['site_title']:'phpGroupWare');
	$data['login_standard']['login_url']		= 'login.php' . $extra_vars;
	$data['login_standard']['cookie']			= $last_loginid;
	$data['login_standard']['lang_username']	= lang('username');
	$data['login_standard']['lang_powered_by']	= lang('powered by');
	$data['login_standard']['lang_version']		= lang('version');
	$data['login_standard']['phpgw_version']	= $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'];
	$data['login_standard']['lang_password']	= lang('password');
	$data['login_standard']['lang_login']		= lang('login');

	//_debug_array($data);

	$GLOBALS['phpgw']->xslttpl->set_var('login',$data,False);
?>
