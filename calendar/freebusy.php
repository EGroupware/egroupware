<?php
/**
 * iCal import and export via Horde iCalendar classes
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage export
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'calendar',
		'noheader'   => True,
		'nofooter'   => True,
	),
);
// check if we are loged in, by checking sessionid and kp3, as the sessionid get set automaticaly by php for php4-sessions
if (!($loged_in = @$_REQUEST['sessionid'] && @$_REQUEST['kp3']))
{
	$GLOBALS['egw_info']['flags']['currentapp'] = 'login';
	$GLOBALS['egw_info']['flags']['noapi'] = True;
}
include ('../header.inc.php');

function fail_exit($msg)
{
	echo "<html>\n<head>\n<title>$msg</title>\n<meta http-equiv=\"content-type\" content=\"text/html; charset=".
		$GLOBALS['egw']->translation->charset()."\" />\n</head>\n<body><h1>$msg</h1>\n</body>\n</html>\n";

	$GLOBALS['egw']->common->egw_exit();
}

if (!$loged_in)
{
	include ('../phpgwapi/inc/functions.inc.php');
	$GLOBALS['egw_info']['flags']['currentapp'] = 'calendar';
}
// fix for SOGo connector, which does not decode the = in our f/b url
if (strpos($_SERVER['QUERY_STRING'],'=3D') !== false && substr($_GET['user'],0,2) == '3D')
{
	$_GET['user'] = substr($_GET['user'],2);
	if (isset($_GET['password'])) $_GET['password'] = substr($_GET['password'],2);
	if (isset($_GET['cred'])) $_GET['cred'] = substr($_GET['cred'],2);
}
if (!is_numeric($user = $_GET['user']))
{
	// check if user contains the current domain --> remove it
	list(,$domain) = explode('@',$user);
	if ($domain === $GLOBALS['egw_info']['user']['domain'])
	list($user) = explode('@',$user);
	$user = $GLOBALS['egw']->accounts->name2id($user,'account_lid','u');
}
if ($user === false || !($username = $GLOBALS['egw']->accounts->id2name($user)))
{
	fail_exit(lang("freebusy: Unknow user '%1', wrong password or not availible to not loged in users !!!"." $username($user)",$_GET['user']));
}
if (!$loged_in)
{
	if (empty($_GET['cred']))
	{
		$GLOBALS['egw_info']['user']['account_id'] = $user;
		$GLOBALS['egw_info']['user']['account_lid'] = $username;
		$GLOBALS['egw']->preferences->account_id = $user;
		$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
		$cal_prefs = &$GLOBALS['egw_info']['user']['preferences']['calendar'];
		$loged_in = !empty($cal_prefs['freebusy']) &&
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
			$_POST['login'] = $authname;
			$_REQUEST['domain'] = $domain;
			$GLOBALS['egw_info']['server']['default_domain'] = $domain;
			$GLOBALS['egw_info']['user']['domain'] = $domain;
			$GLOBALS['egw_info']['flags']['currentapp'] = 'login';
			$GLOBALS['egw_info']['flags']['noapi'] = false;
			require_once(EGW_API_INC . '/functions.inc.php');
			$loged_in =  $GLOBALS['egw']->session->create($authuser, $password, 'text');
			session_unset();
			session_destroy();
		}
	}
	if (!$loged_in)
	{
		fail_exit(lang("freebusy: Unknow user '%1', or not available for unauthenticated users!", $_GET['user']));
	}
}
if ($_GET['debug'])
{
	echo "<pre>";
}
else
{
	ExecMethod2('phpgwapi.browser.content_header','freebusy.ifb','text/calendar');
}
echo ExecMethod2('calendar.calendar_ical.freebusy',$user,$_GET['end']);
