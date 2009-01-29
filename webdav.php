<?php
/**
 * FileManger - WebDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	if (!isset($_SERVER['PHP_AUTH_USER']) || !($sessionid = $GLOBALS['egw']->session->create($account)))
	{
		header('WWW-Authenticate: Basic realm="'.vfs_webdav_server::REALM.
			// if the session class gives a reason why the login failed --> append it to the REALM
			($GLOBALS['egw']->session->reason ? ': '.$GLOBALS['egw']->session->reason : '').'"');
		header("HTTP/1.1 401 Unauthorized");
		header("X-WebDAV-Status: 401 Unauthorized", true);
		exit;
	}
	return $sessionid;
}

// if we are called with a /apps/$app path, use that $app as currentapp, to not require filemanager rights for the links
$parts = explode('/',$_SERVER['PATH_INFO']);
//error_log("webdav: explode".print_r($parts,true));
if(count($parts)== 1){
	echo "Malformed Url: missing slash" ;
	exit;
}

$app = count($parts) > 3 && $parts[1] == 'apps' ? $parts[2] : 'filemanager';

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => True,
		'noheader'  => True,
		'currentapp' => $app,
		'autocreate_session_callback' => 'check_access',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
	)
);
// if you move this file somewhere else, you need to adapt the path to the header!
include(dirname(__FILE__).'/header.inc.php');

$headertime = microtime(true);

$webdav_server = new vfs_webdav_server();
$webdav_server->ServeRequest();
//error_log(sprintf("GroupDAV %s request took %5.3f s (header include took %5.3f s)",$_SERVER['REQUEST_METHOD'],microtime(true)-$starttime,$headertime-$starttime));

