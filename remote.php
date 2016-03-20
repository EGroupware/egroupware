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
 * @copyright (c) 2006-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Vfs;

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
	return Api\Header\Authenticate::autocreate_session_callback($account);
}

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => True,
		'noheader'  => True,
		'currentapp' => preg_match('|/remote.php/webdav/apps/([A-Za-z0-9_-]+)/|', $_SERVER['REQUEST_URI'], $matches) ? $matches[1] : 'filemanager',
		'autocreate_session_callback' => 'check_access',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
		'auth_realm' => 'EGroupware WebDAV server',	// cant use Vfs\WebDAV::REALM as autoloading and include path not yet setup!
	)
);

// if you move this file somewhere else, you need to adapt the path to the header!
try
{
	include(__DIR__.'/header.inc.php');
}
catch (Api\Exception\NoPermission\App $e)
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
if (strpos($_SERVER['REQUEST_URI'],'/remote.php/webdav/clientsync') !== false &&
	($fstab=Vfs::mount()) && !isset($fstab['/clientsync']))
{
	$is_root_backup = Vfs::$is_root;
	Vfs::$is_root = true;
	$ok = Vfs::mount($url='vfs://default/home/$user', $clientsync='/clientsync', null, false);
	Vfs::$is_root = $is_root_backup;
	//error_log("mounting ownCloud default '$clientsync' as '$url' ".($ok ? 'successful' : 'failed!'));
}

// webdav is stateless: we dont need to keep the session open, it only blocks other calls to same basic-auth session
$GLOBALS['egw']->session->commit_session();

$webdav_server = new Vfs\WebDAV();
$webdav_server->ServeRequest('/webdav');
//error_log(sprintf('WebDAV %s request: status "%s", took %5.3f s'.($headertime?' (header include took %5.3f s)':''),$_SERVER['REQUEST_METHOD'].' '.$_SERVER['PATH_INFO'],$webdav_server->_http_status,microtime(true)-$starttime,$headertime-$starttime));
