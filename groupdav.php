<?php
/**
 * eGroupWare - GroupDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007/8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

$starttime = microtime(true);

/**
 * check if the given user has access
 *
 * Create a session or if the user has no account return authenticate header and 401 Unauthorized
 *
 * @param array &$account
 * @return int session-id
 */
function check_access(&$account)
{
	$account = array(
		'login'  => $_SERVER['PHP_AUTH_USER'],
		'passwd' => $_SERVER['PHP_AUTH_PW'],
		'passwd_type' => 'text',
	);
	// no session for clients known to NOT use it (no cookie support)
	$agent = strtolower($_SERVER['HTTP_USER_AGENT']);
	foreach(array(
		'davkit',	// Apple iCal
	//	'bionicmessage.net',
		'zideone',
		'lightning',
	) as $test)
	{
		if (($no_session = strpos($agent,$test) !== false)) break;
	}
	//error_log("GroupDAV PHP_AUTH_USER={$_SERVER['PHP_AUTH_USER']}, HTTP_USER_AGENT={$_SERVER['HTTP_USER_AGENT']} --> no_session=".(int)$no_session);

	if (!isset($_SERVER['PHP_AUTH_USER']) || !($sessionid = $GLOBALS['egw']->session->create($account,'','',$no_session)))
	{
		header('WWW-Authenticate: Basic realm="'.groupdav::REALM.
			// if the session class gives a reason why the login failed --> append it to the REALM
			($GLOBALS['egw']->session->reason ? ': '.$GLOBALS['egw']->session->reason : '').'"');
		header('HTTP/1.1 401 Unauthorized');
		header('X-WebDAV-Status: 401 Unauthorized', true);
		exit;
	}
	return $sessionid;
}

$GLOBALS['egw_info']['flags'] = array(
	'noheader'  => True,
	'currentapp' => 'groupdav',
	'autocreate_session_callback' => 'check_access',
	'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
);
// if you move this file somewhere else, you need to adapt the path to the header!
include(dirname(__FILE__).'/header.inc.php');

$headertime = microtime(true);

$groupdav = new groupdav();
$groupdav->ServeRequest();
//error_log(sprintf("GroupDAV %s request took %5.3f s (header include took %5.3f s)",$_SERVER['REQUEST_METHOD'],microtime(true)-$starttime,$headertime-$starttime));
