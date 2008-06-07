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
}
$user  = is_numeric($_GET['user']) ? (int) $_GET['user'] : $GLOBALS['egw']->accounts->name2id($_GET['user'],'account_lid','u');

if (!($username = $GLOBALS['egw']->accounts->id2name($user)))
{
	fail_exit(lang("freebusy: Unknow user '%1', wrong password or not availible to not loged in users !!!"." $username($user)",$_GET['user']));
}
if (!$loged_in)
{
	$GLOBALS['egw']->preferences->account_id = $user;
	$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
	$GLOBALS['egw_info']['user']['account_id'] = $user;
	$GLOBALS['egw_info']['user']['account_lid'] = $username;

	$cal_prefs = &$GLOBALS['egw_info']['user']['preferences']['calendar'];
	if (!$cal_prefs['freebusy'] || !empty($cal_prefs['freebusy_pw']) && $cal_prefs['freebusy_pw'] != $_GET['password'])
	{
		fail_exit(lang("freebusy: Unknow user '%1', wrong password or not availible to not loged in users !!!",$_GET['user']));
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
