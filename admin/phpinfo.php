<?php
/**
 * EGgroupware administration
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

$GLOBALS['egw_info']['flags'] = array(
	'noheader'   => True,
	'nonavbar'   => True,
	'currentapp' => 'admin'
);
// autoloading is not yet setup, so we have to include this file explicitly
require_once __DIR__.'/../api/src/Header/Authenticate.php';

// allow authenticating via basic auth (eg. curl -u admin:password) to debug how a web-server passes credentials to PHP,
// but only if credentials are given: otherwise we'd send a basic auth challenge instead of redirecting to the login
if (Api\Header\Authenticate::hasCredentials())
{
	$GLOBALS['egw_info']['flags']['autocreate_session_callback'] = 'EGroupware\\Api\\Header\\Authenticate::autocreate_session_callback';
	$GLOBALS['egw_info']['flags']['auth_realm'] = 'EGroupware admin';
}
include('../header.inc.php');

if ($GLOBALS['egw']->acl->checkAdminDeny('info_access', 1))
{
	$GLOBALS['egw']->redirect_link('/index.php');
}

$cache_provider = Api\Cache::getProvider();
$cache_info = '<table><tbody><tr>';
$cache_info .= '<td class="e">EGroupware caching provider</td><td class="v">'.Api\Cache::getProvider();
if ($cache_provider == 'EGroupware\\Api\\Cache\\Apcu')
{
	$cache_info .= ' <a href="'.htmlspecialchars(Api\Egw::link('/admin/apcu.php')).'">View APCu stats</a>';
}
$cache_info .= '</td></tr></tbody></table>'."\n";

ob_start();
phpinfo();
// phpinfo() shows PHP_AUTH_PW, the Authorization header and cookies in clear, redact them
$phpinfo = Api\Header\Authenticate::redactPhpinfo(ob_get_clean());

$info = str_ireplace('<body><div class="center">', '<body><div class="center">'."\n".$cache_info, $phpinfo);
if ($info == $phpinfo)
{
	$info = $cache_info.$info;
}
if (($format = Api\Header\Authenticate::preferredTextFormat()))	// Accept: text/plain or text/markdown, eg. curl -H 'Accept: text/plain' ...
{
	header('Content-Type: '.$format.'; charset=utf-8');
	echo Api\Header\Authenticate::phpinfoToMarkdown($info);
}
else
{
	echo $info;
}
