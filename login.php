<?php
/**
 * EGroupware - Login
 *
 * @link http://www.egroupware.org
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;

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
		Api\Hooks::process('logout');
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
	// allow template to overide login-template (without modifying header.inc.php) by setting default or forced pref
	$prefs = new Api\Preferences();
	$prefs->account_id = Api\Preferences::DEFAULT_ID;
	$prefs->read_repository();

	$class = $prefs->data['common']['template_set'].'_framework';
	if (class_exists($class) && @constant($class.'::LOGIN_TEMPLATE_SET'))
	{
		$GLOBALS['egw_info']['server']['template_set'] =
			$GLOBALS['egw_info']['login_template_set'] = $prefs->data['common']['template_set'];
	}
	if ($GLOBALS['egw_info']['login_template_set'] == 'idots')
	{
		$GLOBALS['egw_info']['server']['template_set'] =
			$GLOBALS['egw_info']['login_template_set'] = 'default';
	}
	unset($prefs); unset($class);

	$GLOBALS['egw']->framework = Framework::factory();

	// This is used for system downtime, to prevent new logins.
	if($GLOBALS['egw_info']['server']['deny_all_logins'])
	{
	   echo $GLOBALS['egw']->framework->denylogin_screen();
	   exit;
	}

	/* Program starts here */

	// some apache mod_auth_* modules use REMOTE_USER instead of PHP_AUTH_USER, thanks to Sylvain Beucler
	if ($GLOBALS['egw_info']['server']['auth_type'] == 'http' && !isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['REMOTE_USER']))
	{
		$_SERVER['PHP_AUTH_USER'] = $_SERVER['REMOTE_USER'];
	}
	$passwd = get_magic_quotes_gpc() ? stripslashes($_POST['passwd']) : $_POST['passwd'];
	$passwd_type = $_POST['passwd_type'];

	// forced password change
	if($GLOBALS['egw']->session->cd_reason != Api\Session::CD_FORCE_PASSWORD_CHANGE)
	{
		// no automatic login
	}
	// authentication via Apache
	elseif ($GLOBALS['egw_info']['server']['auth_type'] == 'http' && isset($_SERVER['PHP_AUTH_USER']))
	{
		$submit = True;
		$login  = $_SERVER['PHP_AUTH_USER'];
		$passwd = $_SERVER['PHP_AUTH_PW'];
		$passwd_type = 'text';
	}
	# Apache + mod_ssl style SSL certificate authentication
	# Certificate (chain) verification occurs inside mod_ssl
	elseif($GLOBALS['egw_info']['server']['auth_type'] == 'sqlssl' && isset($_SERVER['SSL_CLIENT_S_DN']) && !isset($_GET['cd']))
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
	else
	{
		// check if we have a sufficient access-token as cookie and no forced password change
		if ($GLOBALS['egw']->session->cd_reason != Api\Session::CD_FORCE_PASSWORD_CHANGE &&
			$GLOBALS['egw']->session->skipPasswordAuth($_COOKIE[Api\Session::REMEMBER_ME_COOKIE], $account_id))
		{
			$_SERVER['PHP_AUTH_USER'] = $login = Api\Accounts::id2name($account_id);
			$submit = true;
		}

		if(!$passwd && ($GLOBALS['egw_info']['server']['auto_anon_login']) && !$_GET['cd'])
		{
			$_SERVER['PHP_AUTH_USER'] = $login = 'anonymous';
			$_SERVER['PHP_AUTH_PW'] =  $passwd = 'anonymous';
			$passwd_type = 'text';
			$submit = True;
		}
	}


	if (isset($passwd_type) || $submit)
	{
		if($_SERVER['REQUEST_METHOD'] != 'POST' &&
			!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['SSL_CLIENT_S_DN']))
		{
			Egw::redirect_link('/login.php','cd=5');
		}

		// don't get login data again when $submit is true
		if($submit == false)
		{
			$login = $_POST['login'];
		}

		//conference - for strings like vinicius@thyamad.com@default , allows
		//that user have a login that is his e-mail. (viniciuscb)
		// remove blanks
		$login_parts = array_map('trim',explode('@',$login));
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
		$GLOBALS['sessionid'] = $GLOBALS['egw']->session->create($login, $passwd,
			$passwd_type, false, true, true, $_POST['2fa_code'], $_POST['remember_me']);	// true = let session fail on forced password change

		if (!$GLOBALS['sessionid'] && $GLOBALS['egw']->session->cd_reason == Api\Session::CD_FORCE_PASSWORD_CHANGE)
		{
			if (isset($_POST['new_passwd']))
			{
				if (($errors = preferences_password::do_change($passwd, $_POST['new_passwd'], $_POST['new_passwd2'])))
				{
					$force_password_change = implode("\n", $errors);
				}
				else
				{
					$GLOBALS['sessionid'] = $GLOBALS['egw']->session->create($login,$_POST['new_passwd'],$passwd_type);
				}
			}
			else
			{
				$force_password_change = $GLOBALS['egw']->session->reason;
			}
		}
		if (isset($force_password_change))
		{
			// will show new login-screen incl. new password field below
		}
		elseif (!isset($GLOBALS['sessionid']) || ! $GLOBALS['sessionid'])
		{
			Egw::redirect_link('/login.php?cd=' . $GLOBALS['egw']->session->cd_reason);
		}
		else
		{
			if ($_POST['lang'] && preg_match('/^[a-z]{2}(-[a-z]{2})?$/',$_POST['lang']) &&
				$_POST['lang'] != $GLOBALS['egw_info']['user']['preferences']['common']['lang'])
			{
				$GLOBALS['egw']->preferences->add('common','lang',$_POST['lang'],'session');
			}

			// check if new translations are available
			Api\Translation::check_invalidate_cache();

			$forward = isset($_GET['phpgw_forward']) ? urldecode($_GET['phpgw_forward']) : @$_POST['phpgw_forward'];
			if (!$forward)
			{
				$extra_vars['cd'] = 'yes';
				$forward = '/index.php';
			}
			else
			{
				list($forward,$extra_vars) = explode('?',$forward,2);
				// only append cd=yes, if there is not already a cd value!
				if (strpos($extra_vars, 'cd=') === false)
				{
					$extra_vars .= ($extra_vars ? '&' : '').'cd=yes';
				}
			}

			if(strpos($_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_URI']) === false) {
				// login requuest does not come from login.php
				// redirect to referer on logout
				Api\Cache::setSession('login', 'referer', $_SERVER['HTTP_REFERER']);
			}
			$strength = ($GLOBALS['egw_info']['server']['force_pwd_strength']?$GLOBALS['egw_info']['server']['force_pwd_strength']:false);
			if ($strength && $strength>5) $strength =5;
			if ($strength && $strength<0) $strength = false;
			// Check for save passwd
			if($strength && $GLOBALS['egw_info']['server']['check_save_passwd'] && !$GLOBALS['egw']->acl->check('nopasswordchange', 1, 'preferences') &&
				($unsave_msg = $GLOBALS['egw']->auth->crackcheck($passwd, $strength)))
			{
				error_log('login::'.__LINE__.' User '. $login. ' authenticated with an unsave password'.' '.$unsave_msg);
				$message = lang('eGroupWare checked your password for safetyness. You have to change your password for the following reason:')."\n";
				Egw::redirect_link('/index.php', array(
					'menuaction' => 'preferences.uipassword.change',
					'message' => $message . $unsave_msg,
					'cd' => 'yes',
				));
			}
			else
			{
				// commiting the session, before redirecting might fix racecondition in session creation
				$GLOBALS['egw']->session->commit_session();
				Egw::redirect_link($forward,$extra_vars);
			}
		}
	}
	// show login screen
	if(isset($_COOKIE['last_loginid']))
	{
		$prefs = new Api\Preferences($GLOBALS['egw']->accounts->name2id($_COOKIE['last_loginid']));

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
	if ($_COOKIE['eGW_cookie_test'] !== 'enabled')
	{
		Api\Session::egw_setcookie('eGW_cookie_test','enabled',0);
	}
	#print 'LANG:' . $GLOBALS['egw_info']['user']['preferences']['common']['lang'] . '<br>';
	Api\Translation::init();	// this will set the language according to the (new) set prefs
	Api\Translation::add_app('login');
	Api\Translation::add_app('loginscreen');
	$GLOBALS['loginscreenmessage'] = Api\Translation::translate('loginscreen_message',false,'');
	if($GLOBALS['loginscreenmessage'] == 'loginscreen_message' || empty($GLOBALS['loginscreenmessage']))
	{
		Api\Translation::add_app('loginscreen','en');	// trying the en one
		$GLOBALS['loginscreenmessage'] = Api\Translation::translate('loginscreen_message',false,'');
	}
	if($GLOBALS['loginscreenmessage'] == 'loginscreen_message' || empty($GLOBALS['loginscreenmessage']))
	{
	   // remove the global var since the lang loginscreen message and its fallback (en) is empty or not set
	   unset($GLOBALS['loginscreenmessage']);
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

	$GLOBALS['egw']->framework->login_screen($extra_vars, $force_password_change);
}
