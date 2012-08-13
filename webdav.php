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
 * @copyright (c) 2006-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	if (isset($_GET['auth']))
	{
		list($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']) = explode(':',base64_decode($_GET['auth']),2);
	}
	return egw_digest_auth::autocreate_session_callback($account);
}

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => True,
		'noheader'  => True,
		'currentapp' => preg_match('|/webdav.php/apps/([A-Za-z0-9_-]+)/|', $_SERVER['REQUEST_URI'], $matches) ? $matches[1] : 'filemanager',
		'autocreate_session_callback' => 'check_access',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
		'auth_realm' => 'EGroupware WebDAV server',	// cant use vfs_webdav_server::REALM as autoloading and include path not yet setup!
	)
);
require_once('phpgwapi/inc/class.egw_digest_auth.inc.php');

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
//$headertime = microtime(true);

// webdav is stateless: we dont need to keep the session open, it only blocks other calls to same basic-auth session
$GLOBALS['egw']->session->commit_session();

$webdav_server = new vfs_webdav_server();
$webdav_server->ServeRequest();
//error_log(sprintf("WebDAV %s request took %5.3f s (header include took %5.3f s)",$_SERVER['REQUEST_METHOD'],microtime(true)-$starttime,$headertime-$starttime));
