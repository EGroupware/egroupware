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
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
	),
);
// check if we are already logged in
require_once __DIR__.'/../api/src/autoload.php';
if (!($logged_in = !empty(Api\Session::get_sessionid())))
{
	// support basic auth and $_GET[cred] for regular user-credentials
	if (!empty($_SERVER['PHP_AUTH_PW']) || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) || !empty($_GET['cred']))
	{
		$GLOBALS['egw_info']['flags']['autocreate_session_callback'] = Api\Header\Authenticate::class.'::autocreate_session_callback';
		$logged_in = true;	// header sends 401, if not authenticated
		// make $_GET[cred] work by using REDIRECT_HTTP_AUTHORIZATION
		if (!empty($_GET['cred']))
		{
			$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Basic '.$_GET['cred'];
		}
	}
	else
	{
		$GLOBALS['egw_info']['flags']['currentapp'] = 'login';
		$GLOBALS['egw_info']['flags']['noapi'] = True;
	}
}
include ('../header.inc.php');

if (!$logged_in)
{
	include ('../api/src/loader.php');
	$GLOBALS['egw_info']['flags']['currentapp'] = 'calendar';
}
// fix for SOGo connector, which does not decode the = in our f/b url
if (strpos($_SERVER['QUERY_STRING'],'=3D') !== false && substr($_GET['user'],0,2) == '3D')
{
	foreach(['user', 'email', 'password'] as $name)
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
	throw new Api\Exception\NoPermission\AuthenticationRequired(lang("freebusy: unknown user '%1', wrong password or not available to not logged in users !!!"." $username($user)", $_GET['user']));
}
if (!$logged_in)
{
	$GLOBALS['egw_info']['user']['account_id'] = $user;
	$GLOBALS['egw_info']['user']['account_lid'] = $username;
	$GLOBALS['egw']->preferences->account_id = $user;
	$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
	$cal_prefs = &$GLOBALS['egw_info']['user']['preferences']['calendar'];

	if (!($logged_in = !empty($cal_prefs['freebusy']) &&
		(empty($cal_prefs['freebusy_pw']) || $cal_prefs['freebusy_pw'] == $_GET['password'])))
	{
		throw new Api\Exception\NoPermission\AuthenticationRequired(lang("freebusy: unknown user '%1', or not available for unauthenticated users!", $_GET['user']));
	}
}
if ($_GET['debug'])
{
	echo "<pre>";
}
else
{
	Api\Header\Content::type('freebusy.vfb','text/calendar');
}
$ical = new calendar_ical();
echo $ical->freebusy($user, $_GET['end']);