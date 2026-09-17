<?php
/**
 * EGroupware - Logout
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => True,
		'currentapp'             => 'logout',
		'noheader'               => True,
		'nofooter'               => True,
		'nonavbar'               => True
	)
);
include('./header.inc.php');

$GLOBALS['sessionid'] = Api\Session::get_sessionid();
$GLOBALS['kp3']       = Api\Session::get_request('kp3');

$verified = $GLOBALS['egw']->session->verify();

if(!($redirectTarget = Api\Cache::getSession('login', 'referer')))
{
	$redirectTarget = $GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=1&domain='.$GLOBALS['egw_info']['user']['domain'];
}
elseif(strpos($redirectTarget, '[?&]cd=') !== false)
{
	$redirectTarget = preg_replace('/([?&])cd=[^&]+/', '$1cd=1', $redirectTarget);
}

if ($verified)
{
	$auth = new Api\Auth();

	// remove remember me cookie on explicit logout, unless it is a second factor
	if ($GLOBALS['egw']->session->removeRememberMeTokenOnLogout())
	{
		Api\Session::egw_setcookie('eGW_remember','',0,'/');
	}
	Api\Hooks::process('logout');
	$GLOBALS['egw']->session->destroy($GLOBALS['sessionid'],$GLOBALS['kp3']);
}

Api\Session::egw_setcookie(Api\Session::EGW_SESSION_NAME);
Api\Session::egw_setcookie('kp3');
Api\Session::egw_setcookie(Api\Session::EGW_DOMAIN_NAME);
Api\Session::egw_setcookie('domain');   // also remove old domain cookie

// SSO Logout (does not return for SSO systems)
if (isset($auth)) $auth->logout();

// $GLOBALS['egw']->redirect($redirectTarget);
?>
<head>
<meta http-equiv="refresh" content="1;url=<?php echo htmlspecialchars($redirectTarget, ENT_QUOTES) ?>">
</head>
<body>
<a href="<?php echo htmlspecialchars($redirectTarget, ENT_QUOTES) ?>">Logout in progress...</a>
</body>