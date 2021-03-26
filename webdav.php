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
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006-21 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 */

use EGroupware\Api;
use EGroupware\Api\Vfs;

//$starttime = microtime(true);

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
		'currentapp' => (static function($uri)
		{
			if (preg_match('#/webdav.php/(etemplates|apps/([A-Za-z0-9_-]+)|home/'.
				preg_quote($GLOBALS['egw_info']['user']['account_lid'], '#').'/.tmp)/#', $uri, $matches))
			{
				if (!empty($matches[2]))
				{
					$app = $matches[2];
				}
				// allow access to mounted eTemplates and temp file upload
				else
				{
					return 'api';
				}
			}
			else
			{
				$app = 'filemanager';
			}
			if (empty($GLOBALS['egw_info']['user']['apps'][$app]) &&
				(!empty($GLOBALS['egw_info']['user']['apps'][$a='filemanager']) ||
					!empty($GLOBALS['egw_info']['user']['apps'][$a='sitemgr-link'])))
			{
				$app = $a;
			}
			return $app;
		})($_SERVER['REQUEST_URI']),
		'autocreate_session_callback' => 'check_access',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
		'auth_realm' => 'EGroupware WebDAV server',	// cant use Vfs\WebDAV::REALM as autoloading and include path not yet setup!
	)
);

// if you move this file somewhere else, you need to adapt the path to the header!
require_once __DIR__.'/header.inc.php';
//$headertime = microtime(true);

// webdav is stateless: we dont need to keep the session open, it only blocks other calls to same basic-auth session
$GLOBALS['egw']->session->commit_session();

$webdav_server = new Vfs\WebDAV();
$webdav_server->ServeRequest();
//error_log(sprintf('WebDAV %s request: status "%s", took %5.3f s'.($headertime?' (header include took %5.3f s)':''),$_SERVER['REQUEST_METHOD'].' '.$_SERVER['PATH_INFO'],$webdav_server->_http_status,microtime(true)-$starttime,$headertime-$starttime));
