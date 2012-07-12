<?php
/**
 * FileManger - WebDAV access for ownCloud clients
 *
 * ownCloud clients sync by default local ownCloud dir to /clientsync on server.
 *
 * EGroupware now temporary mounts vfs://default/home/$user on /clientsync,
 * so ownCloud clients syncs with users home-dir, unless admin mounts an other directory.
 *
 * @link http://owncloud.org/sync-clients/
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
require_once('../phpgwapi/inc/class.egw_digest_auth.inc.php');

// if you move this file somewhere else, you need to adapt the path to the header!
try
{
	include(dirname(__DIR__).'/header.inc.php');
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

// temporary mount ownCloud default /clientsync as /home/$user, if not explicitly mounted
// so ownCloud dir contains users home-dir by default
if (strpos($_SERVER['REQUEST_URI'],'/webdav.php/clientsync') !== false &&
	($fstab=egw_vfs::mount()) && !isset($fstab['/clientsync']))
{
	$is_root_backup = egw_vfs::$is_root;
	egw_vfs::$is_root = true;
	$ok = egw_vfs::mount($url='vfs://default/home/$user', $clientsync='/clientsync', null, false);
	egw_vfs::$is_root = $is_root_backup;
	//error_log("mounting ownCloud default '$clientsync' as '$url' ".($ok ? 'successful' : 'failed!'));
}

// webdav is stateless: we dont need to keep the session open, it only blocks other calls to same basic-auth session
$GLOBALS['egw']->session->commit_session();

$webdav_server = new vfs_webdav_server();
$user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
if (strstr($user_agent, 'microsoft-webdav') !== false ||
	strstr($user_agent, 'neon') !== false ||
	strstr($user_agent, 'bitkinex') !== false)
{
	// Windows 7 et.al. special treatment
	$webdav_server->cnrnd = true;
}
$webdav_server->ServeRequest();
//error_log(sprintf('WebDAV %s request: status "%s", took %5.3f s'.($headertime?' (header include took %5.3f s)':''),$_SERVER['REQUEST_METHOD'].' '.$_SERVER['PATH_INFO'],$webdav_server->_http_status,microtime(true)-$starttime,$headertime-$starttime));
