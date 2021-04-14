<?php
/**
 * EGroupware - simple / non-CalDAV freebusy URL eg. exported as FBURL in vCard of users
 *
 * Usage:
 * - https://egw.example.org/egroupware/calendar/freebusy.php?user=%NAME%
 * - https://egw.example.org/egroupware/calendar/freebusy.php?email=%NAME%@%SERVER%
 * Authentication is required unless explicitly switched off in calendar preferences of the requested user:
 *   + EGroupware "sessionid" cookie
 *   + basic auth credentials of an EGroupware user
 *   + "password" GET parameter with a configured password from the requested user's preferences
 *   + "cred" GET parameter with base64 encoded "<username>:<password>" of an EGroupware user
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage export
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'calendar',
		'noheader'   => True,
		'nofooter'   => True,
	),
);
// check if we are already logged in
require_once __DIR__.'/../api/src/autoload.php';
if (!($logged_in = !empty(Api\Session::get_sessionid())))
{
	// support basic auth for regular user-credentials
	if (!empty($_SERVER['PHP_AUTH_PW']) || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))
	{
		$GLOBALS['egw_info']['flags']['autocreate_session_callback'] = Api\Header\Authenticate::class.'::autocreate_session_callback';
		$logged_in = true;	// header sends 401, if not authenticated
	}
	else
	{
		$GLOBALS['egw_info']['flags']['currentapp'] = 'login';
		$GLOBALS['egw_info']['flags']['noapi'] = True;
	}
}
include ('../header.inc.php');

function fail_exit($msg)
{
	echo "<html>\n<head>\n<title>$msg</title>\n<meta http-equiv=\"content-type\" content=\"text/html; charset=".
		Api\Translation::charset()."\" />\n</head>\n<body><h1>$msg</h1>\n</body>\n</html>\n";

	header('WWW-Authenticate: Basic realm="'.($GLOBALS['egw_info']['flags']['auth_realm'] ?: 'EGroupware').'"');
	http_response_code(401);
	exit;
}

if (!$logged_in)
{
	include ('../api/src/loader.php');
	$GLOBALS['egw_info']['flags']['currentapp'] = 'calendar';
}
// fix for SOGo connector, which does not decode the = in our f/b url
if (strpos($_SERVER['QUERY_STRING'],'=3D') !== false && substr($_GET['user'],0,2) == '3D')
{
	foreach(['user', 'email', 'password', 'cred'] as $name)
	{
		if (isset($_GET[$name])) $_GET[$name] = substr($_GET[$name],2);
	}
}
if (isset($_GET['user']) && !is_numeric($user = $_GET['user']))
{
	// check if user contains the current domain --> remove it
	list(, $domain) = explode('@', $user);
	if ($domain === $GLOBALS['egw_info']['user']['domain'])
	{
		list($user) = explode('@', $user);
	}
	$user = $GLOBALS['egw']->accounts->name2id($user, 'account_lid', 'u');
}
elseif (isset($_GET['email']))
{
	$user = $GLOBALS['egw']->accounts->name2id($_GET['email'], 'account_email', 'u');
}
if ($user === false || !($username = $GLOBALS['egw']->accounts->id2name($user)))
{
	fail_exit(lang("freebusy: unknown user '%1', wrong password or not available to not logged in users !!!"." $username($user)",$_GET['user']));
}
if (!$logged_in)
{
	if (empty($_GET['cred']))
	{
		$GLOBALS['egw_info']['user']['account_id'] = $user;
		$GLOBALS['egw_info']['user']['account_lid'] = $username;
		$GLOBALS['egw']->preferences->account_id = $user;
		$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
		$cal_prefs = &$GLOBALS['egw_info']['user']['preferences']['calendar'];
		$logged_in = !empty($cal_prefs['freebusy']) &&
			(empty($cal_prefs['freebusy_pw']) || $cal_prefs['freebusy_pw'] == $_GET['password']);
	}
	else
	{
		$credentials = base64_decode($_GET['cred']);
		list($authuser, $password) = explode(':', $credentials, 2);
		if (strpos($authuser, '@') === false)
		{
			$domain = $GLOBALS['egw_info']['server']['default_domain'];
			$authuser .= '@' . $domain;
		}
		else
		{
			list(, $domain) = explode('@',$authuser, 2);
		}
		if (array_key_exists($domain, $GLOBALS['egw_domain']))
		{
			$_POST['login'] = $authuser;
			$_REQUEST['domain'] = $domain;
			$GLOBALS['egw_info']['server']['default_domain'] = $domain;
			$GLOBALS['egw_info']['user']['domain'] = $domain;
			$GLOBALS['egw_info']['flags']['currentapp'] = 'login';
			$GLOBALS['egw_info']['flags']['noapi'] = false;
			$logged_in =  $GLOBALS['egw']->session->create($authuser, $password, 'text');
			session_unset();
			session_destroy();
		}
	}
	if (!$logged_in)
	{
		fail_exit(lang("freebusy: unknown user '%1', or not available for unauthenticated users!", $_GET['user']));
	}
}
if ($_GET['debug'])
{
	echo "<pre>";
}
else
{
	Api\Header\Content::type('freebusy.ifb','text/calendar');
}
$ical = new calendar_ical();
echo $ical->freebusy($user, $_GET['end']);
