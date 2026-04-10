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

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => 'login',
	],
];
require_once('../../header.inc.php');

$json = [
	"openapi" => "3.1.0",
	"info" => [
		"title" => "EGroupware API",
		"description" => "Index of all EGroupware OpenAPI descriptions",
		"version" => $GLOBALS['egw_info']['server']['versions']['maintenance_release'],
	],
	"servers" => [
		[
			"url" => Api\Framework::getUrl(Api\Framework::link("/groupdav.php")),
			"description" => "EGroupware CalDAV/CardDAV/REST Server"
		],
	],
	"security" => [
		[
			"basicAuth" => []
		],
		[
			"bearerAuth" => []
		],
	],
	"paths" => [],  // paths are added from separate app-specific JSON-files below
	"components" => [
		"securitySchemes" => [
			"basicAuth" => [
				"type" => "http",
				"scheme" => "basic",
				"description" => "HTTP Basic Authentication using EGroupware username and password (or app password)."
			],
			"bearerAuth" => [
				"type" => "http",
				"scheme" => "bearer",
				"description" => "HTTP Bearer Token Authentication for API access with and OpenIDConnect/OAuth access token."
			]
		],
		"schemas" => [],    // schemas are added from separate app-specific JSON-files below
	],
];

foreach(scandir(__DIR__) as $file)
{
	if (str_ends_with($file, ".json"))
	{
		$app_json = json_decode(file_get_contents(__DIR__.'/'.$file), true);
		$json['paths'] += $app_json['paths'] ?? [];
		$json['components']['schemas'] += $app_json['components']['schemas'] ?? [];
	}
}

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