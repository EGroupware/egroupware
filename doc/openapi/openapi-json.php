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
$json = [
	"openapi" => $_GET['openapi'] ?? "3.1.0",   // allow to set openapi version, as Swagger UI seems to choke on 3.1.x
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
				"description" => "HTTP Bearer Token Authentication for API access with an OpenIDConnect/OAuth access token."
			]
		],
		"parameters" => [], // parameters are added from separate app-specific JSON-files below
		"schemas" => [],    // schemas are added from separate app-specific JSON-files below
		"responses" => [],  // responses are added from separate app-specific JSON-files below
	],
];

foreach(scandir(__DIR__) as $file)
{
	if (str_ends_with($file, ".json"))
	{
		// if we're authenticated only show API's of apps the user has access too
		if (isset($GLOBALS['egw_info']['user']['apps']) && !isset($GLOBALS['egw_info']['user']['apps'][basename($file, '.json')]))
		{
			continue;
		}
		$app_json = json_decode(file_get_contents(__DIR__.'/'.$file), true);
		// Open WebUI seems to have a problem with references in parameters --> inline all parameters
		// ToDo: check other references like schemas
		$inline_parameters = preg_match('#^Python/[0-9.]+ aiohttp/[0-9.]+$#', $_SERVER['HTTP_USER_AGENT']);
		if ($inline_parameters)
		{
			$operationIds = [];
			foreach($app_json['paths'] as $path => &$methods)
			{
				foreach($methods as $method => &$data)
				{
					if (empty($data['operationId']) || isset($operationIds[$data['operationId']]))
					{
						throw new \Exception("$method $path requires an unique operationId".
							(isset($operationIds[$data['operationId']]) ? "('$data[operationId]' already used by ".$operationIds[$data['operationId']].')' : '').'!');
					}
					$operationIds[$data['operationId']] = $method.' '.$path;
					foreach($data['parameters'] as &$parameter)
					{
						if (isset($parameter['$ref']) && str_starts_with($parameter['$ref'], '#/components/parameters/'))
						{
							if (!isset($app_json['components']['parameters'][$name = explode('/', $parameter['$ref'])[3] ?? '']))
							{
								throw new \Exception("$method $path: Parameter reference {$parameter['$ref']} not found!");
							}
							$parameter = $app_json['components']['parameters'][$name];
						}
					}
				}
			}
			unset($app_json['parameters']);
		}
		$json['paths'] += $app_json['paths'] ?? [];
		$json['components']['parameters'] += $app_json['components']['parameters'] ?? [];
		$json['components']['schemas'] += $app_json['components']['schemas'] ?? [];
		$json['components']['responses'] += $app_json['components']['responses'] ?? [];
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