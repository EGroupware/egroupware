<?php
/**
 * eGroupWare - Login
 *
 * @link http://www.egroupware.org
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

$submit = False;			// set to some initial value

$GLOBALS['egw_info'] = array('flags' => array(
	'disable_Template_class'  => True,
	'login'                   => True,
	'currentapp'              => 'login',
));

if(file_exists('./header.inc.php'))
{
	include('./header.inc.php');
	if(!function_exists('CreateObject'))
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

/*
 * Destroy any existing anonymous session.
 * Copied from logout.php. Maybe make it a common function?
 */
if(isset($GLOBALS['sitemgr_info']) && $GLOBALS['egw_info']['user']['userid'] == $GLOBALS['sitemgr_info']['anonymous_user'])
{
	if($GLOBALS['egw']->session->verify())
	{
		$GLOBALS['egw']->hooks->process('logout');
		$GLOBALS['egw']->session->destroy($GLOBALS['sessionid'],$GLOBALS['kp3']);
	}
}

// CAS :
if($GLOBALS['egw_info']['server']['auth_type'] == 'cas')
{
	ob_end_clean();

	require_once('CAS/CAS.php');

	//phpCAS::setDebug('/var/log/log_phpcas.php');

	if($GLOBALS['egw_info']['server']['cas_authentication_mode'] == 'Proxy')
	{
		phpCAS::proxy(CAS_VERSION_2_0,
			$GLOBALS['egw_info']['server']['cas_server_host_name'],
			(int) $GLOBALS['egw_info']['server']['cas_server_port'],
			$GLOBALS['egw_info']['server']['cas_server_uri'] );
	}
	else
	{
		phpCAS::client(CAS_VERSION_2_0,
			$GLOBALS['egw_info']['server']['cas_server_host_name'],
			(int) $GLOBALS['egw_info']['server']['cas_server_port'],
			$GLOBALS['egw_info']['server']['cas_server_uri'] );
	}

	if($GLOBALS['egw_info']['server']['cas_ssl_validation'] == 'PEMCertificate')
	{
		// Set the certificate of the CAS server (PEM Certificate)
		phpCAS::setCasServerCert($GLOBALS['egw_info']['server']['cas_cert']);
	}
	elseif($GLOBALS['egw_info']['server']['cas_ssl_validation'] == 'CACertificate')
	{
		// Set the CA certificate of the CAS server
		phpCAS::setCasServerCACert($GLOBALS['egw_info']['server']['cas_cert']);
	}
	elseif($GLOBALS['egw_info']['server']['cas_ssl_validation'] == 'No')
	{
		// no SSL validation for the CAS server
		phpCAS::setNoCasServerValidation();
	}

	phpCAS::forceAuthentication();

	ob_start();

	$login = phpCAS::getUser();
	$password = phpCAS::retrievePT("imap://".$GLOBALS['egw_info']['server']['mail_server'],$err_code,$output);
	$GLOBALS['sessionid'] = $GLOBALS['egw']->session->create($login,$password,'text');

	/* set auth_cookie */
	$GLOBALS['egw']->redirect_link($forward,$extra_vars);
}
else
{
	$GLOBALS['egw_info']['server']['template_dir'] = EGW_SERVER_ROOT . '/phpgwapi/templates/' . $GLOBALS['egw_info']['login_template_set'];

	// read the images from the login-template-set, not the (maybe not even set) users template-set
	$GLOBALS['egw_info']['user']['preferences']['common']['template_set'] = $GLOBALS['egw_info']['login_template_set'];

	$class = $GLOBALS['egw_info']['login_template_set'].'_framework';
	if (!class_exists($class))
	{
		if(!file_exists($framework = $GLOBALS['egw_info']['server']['template_dir'].'/class.'.$class.'.inc.php'))
		{
			$framework = EGW_SERVER_ROOT . '/phpgwapi/templates/idots/class.'.($class='idots_framework').'.inc.php';
		}
		require_once($framework);
	}
	$GLOBALS['egw']->framework = new $class($GLOBALS['egw_info']['login_template_set']);
	unset($framework); unset($class);

	// This is used for system downtime, to prevent new logins.
	if($GLOBALS['egw_info']['server']['deny_all_logins'])
	{
	   echo $GLOBALS['egw']->framework->denylogin_screen();
	   exit;
	}

	function check_logoutcode($code)
	{
		switch($code)
		{
			case 1:
				return lang('You have been successfully logged out');
			case 2:
				return lang('Sorry, your login has expired');
			case 4:
				return lang('Cookies are required to login to this site.');
			case 5:
				return '<font color="red">' . lang('Bad login or password') . '</font>';
			case 98:
				return '<font color="red">' . lang('Account is expired') . '</font>';
			case 99:
				return '<font color="red">' . lang('Blocked, too many attempts') . '</font>';
			case 10:
				$GLOBALS['egw']->session->egw_setcookie('sessionid');
				$GLOBALS['egw']->session->egw_setcookie('kp3');
				$GLOBALS['egw']->session->egw_setcookie('domain');
				return '<font color="red">' . lang('Your session could not be verified.') . '</font>';
			default:
				if (!$code)
				{
					return '&nbsp;';
				}
				return htmlspecialchars($code);
		}
	}

	/* Program starts here */

	// some apache mod_auth_* modules use REMOTE_USER instead of PHP_AUTH_USER, thanks to Sylvain Beucler
	if ($GLOBALS['egw_info']['server']['auth_type'] == 'http' && !isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['REMOTE_USER']))
	{
        	$_SERVER['PHP_AUTH_USER'] = $_SERVER['REMOTE_USER'];
	}
	if($GLOBALS['egw_info']['server']['auth_type'] == 'http' && isset($_SERVER['PHP_AUTH_USER']))
	{
		$submit = True;
		$login  = $_SERVER['PHP_AUTH_USER'];
		$passwd = $_SERVER['PHP_AUTH_PW'];
		$passwd_type = 'text';
	}
	else
	{
		$passwd = get_magic_quotes_gpc() ? stripslashes($_POST['passwd']) : $_POST['passwd'];
		$passwd_type = $_POST['passwd_type'];

		if($GLOBALS['egw_info']['server']['allow_cookie_auth'])
		{
			$eGW_remember = explode('::::',get_magic_quotes_gpc() ? stripslashes($_COOKIE['eGW_remember']) : $_COOKIE['eGW_remember']);

			if($eGW_remember[0] && $eGW_remember[1] && $eGW_remember[2])
			{
				$_SERVER['PHP_AUTH_USER'] = $login = $eGW_remember[0];
				$_SERVER['PHP_AUTH_PW'] = $passwd = $eGW_remember[1];
				$passwd_type = $eGW_remember[2];
				$submit = True;
			}
		}
		if(!$passwd && ($GLOBALS['egw_info']['server']['auto_anon_login']) && !$_GET['cd'])
		{
			$_SERVER['PHP_AUTH_USER'] = $login = 'anonymous';
			$_SERVER['PHP_AUTH_PW'] =  $passwd = 'anonymous';
			$passwd_type = 'text';
			$submit = True;
		}
	}

	# Apache + mod_ssl style SSL certificate authentication
	# Certificate (chain) verification occurs inside mod_ssl
	if($GLOBALS['egw_info']['server']['auth_type'] == 'sqlssl' && isset($_SERVER['SSL_CLIENT_S_DN']) && !isset($_GET['cd']))
	{
	   // an X.509 subject looks like:
	   // CN=john.doe/OU=Department/O=Company/C=xx/Email=john@comapy.tld/L=City/
	   // the username is deliberately lowercase, to ease LDAP integration
	   $sslattribs = explode('/',$_SERVER['SSL_CLIENT_S_DN']);
	   # skip the part in front of the first '/' (nothing)
	   while(($sslattrib = next($sslattribs)))
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
		  $GLOBALS['egw']->session->egw_setcookie('eGW_remember','',0,'/');
		  egw::redirect_link('/login.php','cd=5');
	   }
		#if(!isset($_COOKIE['eGroupWareLoginTime']))
		#{
		#	$GLOBALS['egw']->redirect($GLOBALS['egw']->link('/login.php','cd=4'));
		#}

		// don't get login data again when $submit is true
		if($submit == false)
		{
			$login = $_POST['login'];
		}

		//conference - for strings like vinicius@thyamad.com@default , allows
		//that user have a login that is his e-mail. (viniciuscb)
		$login_parts = explode('@',$login);
		// remove blanks
		$login_parts = array_map('trim',$login_parts);
		$login = implode('@',$login_parts);

		$got_login = false;
		if (count($login_parts) > 1)
		{
			//Last part of login string, when separated by @, is a domain name
			if (array_key_exists(array_pop($login_parts),$GLOBALS['egw_domain']))
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
			elseif(!isset($GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]))
			{
				$login .= '@'.$GLOBALS['egw_info']['server']['default_domain'];
			}
		}
		$GLOBALS['sessionid'] = $GLOBALS['egw']->session->create($login,$passwd,$passwd_type);

		if(!isset($GLOBALS['sessionid']) || ! $GLOBALS['sessionid'])
		{
			$GLOBALS['egw']->session->egw_setcookie('eGW_remember','',0,'/');
			egw::redirect_link('/login.php?cd=' . $GLOBALS['egw']->session->cd_reason);
		}
		else
		{
			/* set auth_cookie  */
			if($GLOBALS['egw_info']['server']['allow_cookie_auth'] && $_POST['remember_me'] && $_POST['passwd'])
			{
				switch ($_POST['remember_me'])
				{
					case '1hour' :
						$remember_time = time()+60*60;
						break;
					case '1day' :
						$remember_time = time()+60*60*24;
						break;
					case '1week' :
						$remember_time = time()+60*60*24*7;
						break;
					case '1month' :
						$remember_time = time()+60*60*24*30;
						break;
					case 'forever' :
					default:
						$remember_time = 2147483647;
						break;
				}
				$GLOBALS['egw']->session->egw_setcookie('eGW_remember',implode('::::',array(
					'login' => $login,
					'passwd' => $passwd,
					'passwd_type' => $passwd_type)),
					$remember_time,'/');	// make the cookie valid for the whole site (incl. sitemgr) and not only the eGW install-dir
			}

			if ($_POST['lang'] && preg_match('/^[a-z]{2}(-[a-z]{2})?$/',$_POST['lang']) &&
				$_POST['lang'] != $GLOBALS['egw_info']['user']['preferences']['common']['lang'])
			{
				$GLOBALS['egw']->preferences->add('common','lang',$_POST['lang'],'session');
			}

			if(!$GLOBALS['egw_info']['server']['disable_autoload_langfiles'])
			{
				translation::autoload_changed_langfiles();
			}
			$forward = isset($_GET['phpgw_forward']) ? urldecode($_GET['phpgw_forward']) : @$_POST['phpgw_forward'];
			if (!$forward)
			{
				$extra_vars['cd'] = 'yes';
				if($GLOBALS['egw']->hooks->single('hasUpdates', 'home'))
				{
					$extra_vars['hasupdates'] = 'yes';
				}
				$forward = '/index.php';
			}
			else
			{
				list($forward,$extra_vars) = explode('?',$forward,2);
				$extra_vars .= ($extra_vars ? '&' : '').'cd=yes';
			}

			if(strpos($_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_URI']) === false) {
				// login requuest does not come from login.php
				// redirect to referer on logout
				$GLOBALS['egw']->session->appsession('referer', 'login', $_SERVER['HTTP_REFERER']);
			}

			// Check for save passwd
			if($GLOBALS['egw_info']['server']['check_save_passwd'] && $GLOBALS['egw']->acl->check('changepassword', 1, 'preferences') &&
				($unsave_msg = $GLOBALS['egw']->auth->crackcheck($passwd)))
			{
				$GLOBALS['egw']->log->write(array('text'=>'D-message, User '. $login. ' authenticated with an unsave password','file' => __FILE__,'line'=>__LINE__));
				$message = lang('eGroupWare checked your password for safetyness. You have to change your password for the following reason:')."\n";
				egw::redirect_link('/index.php', array(
					'menuaction' => 'preferences.uipassword.change',
					'message' => $message . $unsave_msg,
					'cd' => 'yes',
				));
			}
			else
			{
				// commiting the session, before redirecting might fix racecondition in session creation
				$GLOBALS['egw']->session->commit_session();
				egw::redirect_link($forward,$extra_vars);
			}
		}
	}
	else
	{
		if(isset($_COOKIE['last_loginid']))
		{
			$accounts =& CreateObject('phpgwapi.accounts');
			$prefs =& CreateObject('phpgwapi.preferences', $accounts->name2id($_COOKIE['last_loginid']));

			if($prefs->account_id)
			{
				$GLOBALS['egw_info']['user']['preferences'] = $prefs->read_repository();
			}
		}
		if ($_GET['lang'] && preg_match('/^[a-z]{2}(-[a-z]{2})?$/',$_GET['lang']))
		{
			$GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $_GET['lang'];
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
			$GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $lang;
		}
		#print 'LANG:' . $GLOBALS['egw_info']['user']['preferences']['common']['lang'] . '<br>';
		translation::init();	// this will set the language according to the (new) set prefs
		translation::add_app('login');
		translation::add_app('loginscreen');
		$GLOBALS['loginscreenmessage'] = translation::translate('loginscreen_message',false,'');
		if($GLOBALS['loginscreenmessage'] == 'loginscreen_message' || empty($GLOBALS['loginscreenmessage']))
		{
			translation::add_app('loginscreen','en');	// trying the en one
			$GLOBALS['loginscreenmessage'] = translation::translate('loginscreen_message',false,'');
		}
		if($GLOBALS['loginscreenmessage'] == 'loginscreen_message' || empty($GLOBALS['loginscreenmessage']))
		{
		   // remove the global var since the lang loginscreen message and its fallback (en) is empty or not set
		   unset($GLOBALS['loginscreenmessage']);
		}
	}

	foreach($_GET as $name => $value)
	{
		if(strpos($name,'phpgw_') !== false)
		{
			$extra_vars .= '&' . $name . '=' . urlencode($value);
		}
	}

	if($extra_vars)
	{
		$extra_vars = '?' . substr($extra_vars,1);
	}

	$GLOBALS['egw']->framework->login_screen($extra_vars);
}
