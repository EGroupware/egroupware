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
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	if (!isset($_SERVER['PHP_AUTH_USER']) ||
		!($sessionid = $GLOBALS['egw']->session->create($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW'],'text')))
	{
		header('WWW-Authenticate: Basic realm="'.groupdav::REALM.
			// if the session class gives a reason why the login failed --> append it to the REALM
			($GLOBALS['egw']->session->reason ? ': '.$GLOBALS['egw']->session->reason : '').'"');
		header('HTTP/1.1 401 Unauthorized');
		header('X-WebDAV-Status: 401 Unauthorized', true);
		echo "<html>\n<head>\n<title>401 Unauthorized</title>\n<body>\nAuthorization failed.\n</body>\n</html>\n";
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
$egw_dir = dirname(__FILE__);
require_once($egw_dir.'/phpgwapi/inc/class.egw_digest_auth.inc.php');
include($egw_dir.'/header.inc.php');

$headertime = microtime(true);

$groupdav = new groupdav();
$groupdav->ServeRequest();
//error_log(sprintf("GroupDAV %s request took %5.3f s (header include took %5.3f s)",$_SERVER['REQUEST_METHOD'],microtime(true)-$starttime,$headertime-$starttime));
