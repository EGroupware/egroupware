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
 * @copyright (c) 2006-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	if (!isset($_SERVER['PHP_AUTH_USER']) ||
		!($sessionid = $GLOBALS['egw']->session->create($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW'],'text')))
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
if(count($parts) == 1)
{
	error_log(__METHOD__. "Malformed Url: missing slash:\n".$_SERVER['SERVER_NAME']."\n PATH_INFO:".$_SERVER['PATH_INFO'].
		"\n REQUEST_URI".$_SERVER['REQUEST_URI']."\n ORIG_SCRIPT_NAME:".$_SERVER['ORIG_SCRIPT_NAME'].
		"\n REMOTE_ADDR:".$_SERVER['REMOTE_ADDR']."\n PATH_INFO:".$_SERVER['PATH_INFO']."\n HTTP_USER_AGENT:".$_SERVER['HTTP_USER_AGENT']) ;
	header("HTTP/1.1 501  Not implemented");
	header("X-WebDAV-Status: 501  Not implemented", true);
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
try
{
	include(dirname(__FILE__).'/header.inc.php');
}
catch (egw_exception_no_permission_app $e)
{
	if (isset($GLOBALS['egw_info']['user']['apps']['filemanager']))
	{
		$GLOBALS['egw_info']['currentapp'] = 'filemanager';
	}
	elseif (isset($GLOBALS['egw_info']['user']['apps']['sitemgr-link']))
	{
		$GLOBALS['egw_info']['currentapp'] = 'sitemgr-link';
	}
	else
	{
		throw $e;
	}
}

$headertime = microtime(true);

$webdav_server = new vfs_webdav_server();
$webdav_server->ServeRequest();
//error_log(sprintf("WebDAV %s request took %5.3f s (header include took %5.3f s)",$_SERVER['REQUEST_METHOD'],microtime(true)-$starttime,$headertime-$starttime));

