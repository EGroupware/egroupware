<?php
	/**************************************************************************\
	* eGroupWare login                                                         *
	* http://www.egroupware.org                                                *
	* Originaly written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	*                      Joseph Engo    <jengo@phpgroupware.org>             *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_info = array();
	$submit = False;			// set to some initial value

	$GLOBALS['phpgw_info']['flags'] = array(
		'disable_Template_class' => True,
		'login'                  => True,
		'currentapp'             => 'login',
		'noheader'               => True
	);

	if(file_exists('./header.inc.php'))
	{
		include('./header.inc.php');
		if(function_exists('CreateObject'))
		{
			$GLOBALS['phpgw']->session = CreateObject('phpgwapi.sessions',array_keys($GLOBALS['phpgw_domain']));
		}
		else
		{
			Header('Location: setup/index.php');
			exit;
		}
	}
	else
	{
		Header('Location: setup/index.php');
		exit;
	}

	$GLOBALS['phpgw_info']['server']['template_dir'] = PHPGW_SERVER_ROOT . '/phpgwapi/templates/' . $GLOBALS['phpgw_info']['login_template_set'];
    $tmpl = CreateObject('phpgwapi.Template', $GLOBALS['phpgw_info']['server']['template_dir']);
	

	// read the images from the login-template-set, not the (maybe not even set) users template-set
	$GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'] = $GLOBALS['phpgw_info']['login_template_set'];

	// This is used for system downtime, to prevent new logins.
	if($GLOBALS['phpgw_info']['server']['deny_all_logins'])
	{
		$deny_msg=lang('Oops! You caught us in the middle of system maintainance.<br/>
		Please, check back with us shortly.');

		$tmpl->set_file(array
		(
			'login_form' => 'login_denylogin.tpl'
		));

		$tmpl->set_var('template_set','default');
		$tmpl->set_var('deny_msg',$deny_msg);
		$tmpl->pfp('loginout','login_form');
		exit;
	}
	$tmpl->set_file(array('login_form' => 'login.tpl'));
	
	// !! NOTE !!
	// Do NOT and I repeat, do NOT touch ANYTHING to do with lang in this file.
	// If there is a problem, tell me and I will fix it. (jengo)

	// whoooo scaring
/*
	if($GLOBALS['phpgw_info']['server']['usecookies'] == True)
	{
		$GLOBALS['phpgw']->session->phpgw_setcookie('eGroupWareLoginTime', time());
	}
*/
/*
	if($_GET['cd'] != 10 && $GLOBALS['phpgw_info']['server']['usecookies'] == False)
	{
		$GLOBALS['phpgw']->session->setcookie('sessionid');
		$GLOBALS['phpgw']->session->setcookie('kp3');
		$GLOBALS['phpgw']->session->setcookie('domain');
	}
*/

/* This is not working yet because I need to figure out a way to clear the $cd =1
	if(isset($_SERVER['PHP_AUTH_USER']) && $_GET['cd'] == '1')
	{
		Header('HTTP/1.0 401 Unauthorized');
		Header('WWW-Authenticate: Basic realm="phpGroupWare"'); 
		echo 'You have to re-authentificate yourself'; 
		exit;
	}
*/

	function check_logoutcode($code)
	{
		switch($code)
		{
			case 1:
				return lang('You have been successfully logged out');
				break;
			case 2:
				return lang('Sorry, your login has expired');
				break;
			case 4:
				return lang('Cookies are required to login to this site.');
				break;
			case 5:
				return '<font color="FF0000">' . lang('Bad login or password') . '</font>';
				break;
			case 98:
				return '<font color="FF0000">' . lang('Account is expired') . '</font>';
				break;
			case 99:
				return '<font color="FF0000">' . lang('Blocked, too many attempts') . '</font>';
				break;
			case 10:
				$GLOBALS['phpgw']->session->phpgw_setcookie('sessionid');
				$GLOBALS['phpgw']->session->phpgw_setcookie('kp3');
				$GLOBALS['phpgw']->session->phpgw_setcookie('domain');

				//fix for bug php4 expired sessions bug
				if($GLOBALS['phpgw_info']['server']['sessions_type'] == 'php4')
				{
					$GLOBALS['phpgw']->session->phpgw_setcookie(PHPGW_PHPSESSID);
				}

				return '<font color="#FF0000">' . lang('Your session could not be verified.') . '</font>';
				break;
			default:
				return '&nbsp;';
		}
	}
	
	/* Program starts here */

	if($GLOBALS['phpgw_info']['server']['auth_type'] == 'http' && isset($_SERVER['PHP_AUTH_USER']))
	{
		$submit = True;
		$login  = $_SERVER['PHP_AUTH_USER'];
		$passwd = $_SERVER['PHP_AUTH_PW'];
		$passwd_type = 'text';
	}
	else
	{
		$passwd = $_POST['passwd'];
		$passwd_type = $_POST['passwd_type'];
	}

	# Apache + mod_ssl style SSL certificate authentication
	# Certificate (chain) verification occurs inside mod_ssl
	if($GLOBALS['phpgw_info']['server']['auth_type'] == 'sqlssl' && isset($_SERVER['SSL_CLIENT_S_DN']) && !isset($_GET['cd']))
	{
		# an X.509 subject looks like:
		# /CN=john.doe/OU=Department/O=Company/C=xx/Email=john@comapy.tld/L=City/
		# the username is deliberately lowercase, to ease LDAP integration
		$sslattribs = explode('/',$_SERVER['SSL_CLIENT_S_DN']);
		# skip the part in front of the first '/' (nothing)
		while($sslattrib = next($sslattribs))
		{
			list($key,$val) = explode('=',$sslattrib);
			$sslattributes[$key] = $val;
		}

		if(isset($sslattributes['Email']))
		{
			$submit = True;

			# login will be set here if the user logged out and uses a different username with
			# the same SSL-certificate.
			if(!isset($_POST['login'])&&isset($sslattributes['Email']))
			{
				$login = $sslattributes['Email'];
				# not checked against the database, but delivered to authentication module
				$passwd = $_SERVER['SSL_CLIENT_S_DN'];
			}
		}
		unset($key);
		unset($val);
		unset($sslattributes);
	}

	if(isset($passwd_type) || $_POST['submitit_x'] || $_POST['submitit_y'] || $submit)
//		isset($_POST['passwd']) && $_POST['passwd']) // enable konqueror to login via Return
	{
		if(getenv('REQUEST_METHOD') != 'POST' && $_SERVER['REQUEST_METHOD'] != 'POST' &&
			!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['SSL_CLIENT_S_DN']))
		{
			$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/login.php','cd=5'));
		}
		#if(!isset($_COOKIE['eGroupWareLoginTime']))
		#{
		#	$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/login.php','cd=4'));
		#}
		
		// don't get login data again when $submit is true
		if($submit == false)
		{
			$login = $_POST['login'];
		}
	
		//conference - for strings like vinicius@thyamad.com@default , allows
		//that user have a login that is his e-mail. (viniciuscb)
		$login_parts = explode('@',$login);
		$got_login = false;
		if (count($login_parts) > 1)
		{
			//Last part of login string, when separated by @, is a domain name
			if (array_key_exists(array_pop($login_parts),$GLOBALS['phpgw_domain']))
			{
				$got_login = true;
			}
		}

		if (!$got_login)
		{
			if(isset($_POST['logindomain']))
			{
				$login .= '@' . $_POST['logindomain'];
			}
			elseif(!isset($GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['user']['domain']]))
			{
				$login .= '@'.$GLOBALS['phpgw_info']['server']['default_domain'];
			}
		}
		$GLOBALS['sessionid'] = $GLOBALS['phpgw']->session->create($login,$passwd,$passwd_type,'u');

		if(!isset($GLOBALS['sessionid']) || ! $GLOBALS['sessionid'])
		{
			$GLOBALS['phpgw']->redirect($GLOBALS['phpgw_info']['server']['webserver_url'] . '/login.php?cd=' . $GLOBALS['phpgw']->session->cd_reason);
		}
		else
		{
			if ($_POST['lang'] && preg_match('/^[a-z]{2}(-[a-z]{2}){0,1}$/',$_POST['lang']) &&
			    $_POST['lang'] != $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'])
			{
				$GLOBALS['phpgw']->preferences->add('common','lang',$_POST['lang'],'session');
			}

			if(!$GLOBALS['phpgw_info']['server']['disable_autoload_langfiles'])
			{
				$GLOBALS['phpgw']->translation->autoload_changed_langfiles();
			}
			$forward = isset($_GET['phpgw_forward']) ? urldecode($_GET['phpgw_forward']) : @$_POST['phpgw_forward'];
			if (!$forward)
			{
				$extra_vars['cd'] = 'yes';
				$forward = '/home.php';
			}
			else
			{
				list($forward,$extra_vars) = explode('?',$forward,2);
			}
			//echo "redirecting to ".$GLOBALS['phpgw']->link($forward,$extra_vars);
			$GLOBALS['phpgw']->redirect_link($forward,$extra_vars);
		}
	}
	else
	{
		// !!! DONT CHANGE THESE LINES !!!
		// If there is something wrong with this code TELL ME!
		// Commenting out the code will not fix it. (jengo)
		if(isset($_COOKIE['last_loginid']))
		{
			$accounts = CreateObject('phpgwapi.accounts');
			$prefs = CreateObject('phpgwapi.preferences', $accounts->name2id($_COOKIE['last_loginid']));

			if($prefs->account_id)
			{
				$GLOBALS['phpgw_info']['user']['preferences'] = $prefs->read_repository();
			}
		}
		if ($_GET['lang'])
		{
			$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] = $_GET['lang'];
		}
		elseif(!isset($_COOKIE['last_loginid']) || !$prefs->account_id)
		{
			// If the lastloginid cookies isn't set, we will default to the first language,
			// the users browser accepts.
			list($lang) = explode(',',$_SERVER['HTTP_ACCEPT_LANGUAGE']);
			if(strlen($lang) > 2)
			{
				$lang = substr($lang,0,2);
			}
			$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] = $lang;
		}
		#print 'LANG:' . $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] . '<br>';

		$GLOBALS['phpgw']->translation->init();	// this will set the language according to the (new) set prefs
		$GLOBALS['phpgw']->translation->add_app('login');
		$GLOBALS['phpgw']->translation->add_app('loginscreen');
		if(lang('loginscreen_message') == 'loginscreen_message*')
		{
			$GLOBALS['phpgw']->translation->add_app('loginscreen','en');	// trying the en one
		}
		if(lang('loginscreen_message') != 'loginscreen_message*')
		{
			$tmpl->set_var('lang_message',stripslashes(lang('loginscreen_message')));
		}
	}

	$tmpl->set_block('login_form','domain_selection');
	$domain_select = '&nbsp;';
	$lang_domain_select = '&nbsp;';
	$last_loginid = $_COOKIE['last_loginid'];
	if($GLOBALS['phpgw_info']['server']['show_domain_selectbox'])
	{
		$domain_select = "<select name=\"logindomain\">\n";
		foreach($GLOBALS['phpgw_domain'] as $domain_name => $domain_vars)
		{
			$domain_select .= '<option value="' . $domain_name . '"';

			if($domain_name == $_COOKIE['last_domain'])
			{
				$domain_select .= ' selected';
			}
			$domain_select .= '>' . $domain_name . "</option>\n";
		}
		$domain_select .= "</select>\n";
		$lang_domain_select = lang('Domain');
	}
	elseif($last_loginid !== '')
	{
		reset($GLOBALS['phpgw_domain']);
		list($default_domain) = each($GLOBALS['phpgw_domain']);

		if($_COOKIE['last_domain'] != $default_domain && !empty($_COOKIE['last_domain']))
		{
			$last_loginid .= '@' . $_COOKIE['last_domain'];
		}
	}
	$tmpl->set_var('lang_select_domain',$lang_domain_select);
	$tmpl->set_var('select_domain',$domain_select);
	
	if(!$GLOBALS['phpgw_info']['server']['show_domain_selectbox'])
	{
	   /* trick to make domain selection disappear */
		$tmpl->set_var('domain_selection','');
	}

	foreach($_GET as $name => $value)
	{
		if(ereg('phpgw_',$name))
		{
			$extra_vars .= '&' . $name . '=' . urlencode($value);
		}
	}

	if($extra_vars)
	{
		$extra_vars = '?' . substr($extra_vars,1);
	}

	/********************************************************\
	* Check is the registration app is installed, activated  *
	* And if the register link must be placed                *
	\********************************************************/
	
	$cnf_reg = createobject('phpgwapi.config','registration');
	$cnf_reg->read_repository();
	$config_reg = $cnf_reg->config_data;

	if($config_reg[enable_registration]=='True')
	{
	   if ($config_reg[register_link]=='True')
	   {
		  $reg_link='&nbsp;<a href="registration/">'.lang('Not a user yet? Register now').'</a><br/>';
	   }
	   if ($config_reg[lostpassword_link]=='True')
	   {
		  $lostpw_link='&nbsp;<a href="registration/main.php?menuaction=registration.boreg.lostpw1">'.lang('Lost password').'</a><br/>';
	   }
	   if ($config_reg[lostid_link]=='True')
	   {
		  $lostid_link='&nbsp;<a href="registration/main.php?menuaction=registration.boreg.lostid1">'.lang('Lost Login Id').'</a><br/>';
	   }

	   /* if at least one option of "registration" is activated display the registration section */
	   if($config_reg[register_link]=='True' || $config_reg[lostpassword_link]=='True' || $config_reg[lostid_link]=='True')
	   {
		  $tmpl->set_var('register_link',$reg_link);
		  $tmpl->set_var('lostpassword_link',$lostpw_link);
		  $tmpl->set_var('lostid_link',$lostid_link) ;
		  
		  //$tmpl->set_var('registration_url',$GLOBALS['phpgw_info']['server']['webserver_url'] . '/registration/');
	   }
	   else
	   {
		  /* trick to make registration section disapear */
		  $tmpl->set_block('login_form','registration');
		  $tmpl->set_var('registration','');
	   }
	}

	// add a content-type header to overwrite an existing default charset in apache (AddDefaultCharset directiv)
	header('Content-type: text/html; charset='.$GLOBALS['phpgw']->translation->charset());
	
	$GLOBALS['phpgw_info']['server']['template_set'] = $GLOBALS['phpgw_info']['login_template_set'];

	$tmpl->set_var('charset',$GLOBALS['phpgw']->translation->charset());
	$tmpl->set_var('login_url', $GLOBALS['phpgw_info']['server']['webserver_url'] . '/login.php' . $extra_vars);
	$tmpl->set_var('version',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
	$tmpl->set_var('cd',check_logoutcode($_GET['cd']));
	$tmpl->set_var('cookie',$last_loginid);

	$tmpl->set_var('lang_username',lang('username'));
	$tmpl->set_var('lang_password',lang('password'));
	$tmpl->set_var('lang_login',lang('login'));

	$tmpl->set_var('website_title', $GLOBALS['phpgw_info']['server']['site_title']);
	$tmpl->set_var('template_set',$GLOBALS['phpgw_info']['login_template_set']);
	$tmpl->set_var('bg_color',($GLOBALS['phpgw_info']['server']['login_bg_color']?$GLOBALS['phpgw_info']['server']['login_bg_color']:'FFFFFF'));
	$tmpl->set_var('bg_color_title',($GLOBALS['phpgw_info']['server']['login_bg_color_title']?$GLOBALS['phpgw_info']['server']['login_bg_color_title']:'486591'));

	if (substr($GLOBALS['phpgw_info']['server']['login_logo_file'],0,4) == 'http')
	{
		$var['logo_file'] = $GLOBALS['phpgw_info']['server']['login_logo_file'];
	}
	else
	{
		$var['logo_file'] = $GLOBALS['phpgw']->common->image('phpgwapi',$GLOBALS['phpgw_info']['server']['login_logo_file']?$GLOBALS['phpgw_info']['server']['login_logo_file']:'logo');
	}
	$var['logo_url'] = $GLOBALS['phpgw_info']['server']['login_logo_url']?$GLOBALS['phpgw_info']['server']['login_logo_url']:'http://www.eGroupWare.org';
	if (substr($var['logo_url'],0,4) != 'http')
	{
		$var['logo_url'] = 'http://'.$var['logo_url'];
	}
	$var['logo_title'] = $GLOBALS['phpgw_info']['server']['login_logo_title']?$GLOBALS['phpgw_info']['server']['login_logo_title']:'www.eGroupWare.org';
	$tmpl->set_var($var);


	/* language section if activated in site config */
	if (@$GLOBALS['phpgw_info']['server']['login_show_language_selection'])
	{
		$select_lang = '<select name="lang" onchange="'."if (this.form.login.value && this.form.passwd.value) this.form.submit(); else location.href=location.href+(location.search?'&':'?')+'lang='+this.value".'">';
		$langs = $GLOBALS['phpgw']->translation->get_installed_langs();
		uasort($langs,'strcasecmp');
		foreach ($langs as $key => $name)	// if we have a translation use it
		{
			$select_lang .= "\n\t".'<option value="'.$key.'"'.($key == $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] ? ' selected="1"' : '').'>'.$name.'</option>';
		}
		$select_lang .= "\n</select>\n";
		$tmpl->set_var(array(
			'lang_language' => lang('Language'),
			'select_language' => $select_lang,
		));
	}
	else
	{
	   /* trick to make language section disapear */
		$tmpl->set_block('login_form','language_select');
		$tmpl->set_var('language_select','');
	}

	$tmpl->set_var('autocomplete', ($GLOBALS['phpgw_info']['server']['autocomplete_login'] ? 'autocomplete="off"' : ''));

	$tmpl->pfp('loginout','login_form');
?>
