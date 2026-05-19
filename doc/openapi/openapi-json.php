<?php
/**
 * EGroupware - REST API OpenAPI description
 *
 * The description is generated from app-specific JSON-files / artifacts in this directory.
 * Nginx uses this PHP index, if someone requests openapi.json in this directory:
 *
 * location = /egroupware/doc/openapi/openapi.json {
 *    fastcgi_pass $egroupware:9000;
 *    include fastcgi_params;
 *    fastcgi_param SCRIPT_FILENAME /var/www/egroupware/doc/openapi/index.php;
 * }
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage caldav/rest
 * @author Ralf Becker <rb-at-egroupware.org>
 * @copyright (c) 2026 by Ralf Becker <rb-at-egroupware.org>
 */

use EGroupware\Api;

// we're included by another endpoint, probably groupdav.php
if (empty($GLOBALS['egw_info']))
{
	$GLOBALS['egw_info'] = [
		'flags' => [
			'currentapp' => 'groupdav',
			'noheader' => true,
		],
	];
	try {
		require_once('../../header.inc.php');
	}
	catch (Api\Exception\NoPermission\App $e) {
		// ignore app rights, they are only used to limit the returned API's
	}
}
// allow unauthenticated access from everywhere, e.g. to use Swagger Editor (https://editor.swagger.io/) to view it
header('Access-Control-Allow-Origin: '.($GLOBALS['egw_info']['flags']['currentapp'] === 'login' ? '*' : rtrim(Api\Framework::getUrl('/'), '/')));

// Open WebUI seems to have a problem with references in parameters --> inline all parameters
$inline_parameters = preg_match(Api\CalDAV\OpenAPI::OPENWEBUI_USER_AGENT, $_SERVER['HTTP_USER_AGENT']);
$config = Api\CalDAV\OpenAPI::getUserAgentConfig($_SERVER['HTTP_USER_AGENT']);

$json = Api\CalDAV\OpenAPI::scan($inline_parameters, $config['operationIds'] ?? [], !($config['allow'] ?? false));

$content = json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
$etag = '"'.md5($content).'"';

// headers to allow caching
Api\Session::cache_control(864000);	// cache for 10 days
header('Content-type: application/json');
header('ETag: '.$etag);

// if servers send an If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	exit;
}
header('Content-Length: '.strlen($content));
echo $content;