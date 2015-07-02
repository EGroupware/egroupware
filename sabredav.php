<?php
/**
 * FileManger - WebDAV access
 *
 * For Apache FCGI you need the following rewrite rule:
 *
 * 	RewriteEngine on
 * 	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
 *
 * Otherwise authentication request will be send over and over again, as password is NOT available to PHP!
 *
 * Using Sabre/Dav package (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2006-15 by Ralf Becker <rb@stylite.de>
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
		'currentapp' => preg_match('#/(sabre|web)dav.php/apps/([A-Za-z0-9_-]+)/#', $_SERVER['REQUEST_URI'], $matches) ? $matches[1] : 'filemanager',
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

use EGroupware\Api\Vfs;
use Sabre\DAV;

// Now we're creating a whole bunch of objects
$rootDirectory = new Vfs\Dav\Directory('/');

// The server object is responsible for making sense out of the WebDAV protocol
$server = new DAV\Server($rootDirectory);

// If your server is not on your webroot, make sure the following line has the
// correct information
$server->setBaseUri($_SERVER['SCRIPT_NAME']);

// The lock manager is reponsible for making sure users don't overwrite
// each others changes.
/*$lockBackend = new DAV\Locks\Backend\File('data/locks');
$lockPlugin = new DAV\Locks\Plugin($lockBackend);
$server->addPlugin($lockPlugin);*/

// This ensures that we get a pretty index in the browser, but it is
// optional.
$server->addPlugin(new DAV\Browser\Plugin());

// All we need to do now, is to fire up the server
$server->exec();

//error_log(sprintf('WebDAV %s request: status "%s", took %5.3f s'.($headertime?' (header include took %5.3f s)':''),$_SERVER['REQUEST_METHOD'].' '.$_SERVER['PATH_INFO'],$webdav_server->_http_status,microtime(true)-$starttime,$headertime-$starttime));
