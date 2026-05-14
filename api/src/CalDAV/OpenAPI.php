<?php
/**
 * EGroupware - REST API OpenAPI specification generator
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage caldav/rest
 * @author Ralf Becker <rb-at-egroupware.org>
 * @copyright (c) 2026 by Ralf Becker <rb-at-egroupware.org>
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api;
use BenMorel\OpenApiSchemaToJsonSchema\Converter\SchemaConverter;
use BenMorel\OpenApiSchemaToJsonSchema\Converter\ParameterConverter;
use BenMorel\OpenApiSchemaToJsonSchema\Options;

/**
 * Generates combined OpenAPI specification from app-specific JSON files
 */
class OpenAPI
{
	/**
	 * Scan directory for app-specific JSON files and merge them into a single OpenAPI spec
	 *
	 * @param bool $inline_parameters true: replace parameter references with the actual data
	 * @param array $operationIdFilter to allow or deny given operationId's
	 * @param bool $deny true: deny/filter out given operationIds, false: only allow/return given operationIds
	 * @return array Combined OpenAPI specification
	 * @throws \Exception
	 */
	public static function scan(bool $inline_parameters=false, array $operationIdFilter=[], bool $deny=true): array
	{
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

		foreach(scandir($base_dir = EGW_SERVER_ROOT.'/doc/openapi') as $file)
		{
			if (str_ends_with($file, ".json"))
			{
				// if we're authenticated only show API's of apps the user has access too or are independent of an app like "links.json"
				if (isset($GLOBALS['egw_info']['apps'][$app=basename($file, '.json')]) &&
					isset($GLOBALS['egw_info']['user']['apps']) && !isset($GLOBALS['egw_info']['user']['apps'][$app]))
				{
					continue;
				}
				$app_json = json_decode(file_get_contents($base_dir.'/'.$file), true);

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
						// do we need to filter out this operationId
						if (in_array($data['operationId'], $operationIdFilter) === $deny)
						{
							unset($methods[$method]);
							continue;
						}
						if ($inline_parameters)
						{
							foreach ($data['parameters'] as &$parameter)
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
					if (!$methods)
					{
						unset($app_json['path'][$path]);
					}
				}
				if ($inline_parameters)
				{
					unset($app_json['parameters']);
				}
				// check if app's $operationIds have not been completely filtered out
				if ($app_json['paths'])
				{
					$json['paths'] += $app_json['paths'] ?? [];
					$json['components']['parameters'] += $app_json['components']['parameters'] ?? [];
					$json['components']['schemas'] += $app_json['components']['schemas'] ?? [];
					$json['components']['responses'] += $app_json['components']['responses'] ?? [];
				}
			}
		}
		return $json;
	}

	/**
	 * Return all operationIds as select-options
	 *
	 * @return array value => array with values for keys value, label and title
	 * @throws \Exception
	 */
	public static function operationIds()
	{
		$values = [];
		foreach(self::scan()['paths'] as $methods)
		{
			foreach ($methods as $data)
			{
				$values[$data['operationId']] = [
					'value' => $data['operationId'],
					'label' => $data['summary'],
					'title' => $data['description'],
				];
			}
		}
		return $values;
	}

	/**
	 * Call a tool named by its operationId
	 *
	 * @param string $operationId
	 * @param array $params
	 * @param array $operationIdFilter allowed or denied operationIds
	 * @param bool $deny
	 * @return array|string
	 */
	public static function toolCall(string $operationId, array $params = [], array $operationIdFilter=[], bool $deny=false)
	{
		// to not block on the session
		$GLOBALS['egw']->session->commit_session();

		try
		{
			// check if the tool-call is allowed
			if (in_array($operationId, $operationIdFilter) !== $deny)
			{
				throw new \Exception("Operation '$operationId' is NOT permitted!");
			}
			foreach (self::scan(false, )['paths'] as $path => $methods)
			{
				foreach ($methods as $method => $data)
				{
					if ($data['operationId'] === $operationId)
					{
						$uri = preg_replace_callback('/{([a-z]+)}/', static function ($matches) use ($params) {
							$ret = $params[$matches[1]] ?? throw new \Exception("Missing path-parameter '$matches[1]'!");
							unset($params[$matches[1]]);
							return $ret;
						}, $path);
						$headers = [
							'Content-Type' => 'application/json',
							'Accept' => 'application/json',
							'Prefer' => 'return=representation',
						];
						$status = Api\CalDAV::runRequest($uri, $method, $params, $headers, $response, $response_headers);
						if (($success = ((string)$status)[0] === '2' && !isset($response['error'])) && isset($response_headers['Location']))
						{
							[, $location] = explode('/groupdav.php', $response_headers['Location'], 2);
						}
						return [
							'status' => $status,
							'success' => $success,
							'message' => $success ? "Successful called '$operationId'".(isset($location) ? ": new entry at location $location created" : '').
								': '.json_encode($response) :
								"Failed calling '$operationId'".(isset($response['error']) ? ": {$response['error']}" : '').'!',
							'response' => $response,
						];
					}
				}
			}
		}
		catch (\Exception $e) {
			return [
				'error' => $e->getMessage(),
			];
		}
		return [
			'error' => "Invalid operationId '$operationId'!",
		];
	}

	/**
	 * Return OpenAI tool description for the given operationIds
	 *
	 * @param array $operationIdFilter to allow or deny given operationId's
	 * @param bool $deny true: deny/filter out given operationIds, false: only allow/return given operationIds
	 * @return array
	 */
	public static function tools(array $operationIdFilter=[], bool $deny=false) : array
	{
		$openapi=self::scan(true, $operationIdFilter, $deny);
		$tools = [];
		foreach($openapi['paths'] as $methods)
		{
			foreach ($methods as $method)
			{
				if (in_array($method['operationId'], $operationIdFilter) === $deny) continue;

				$tool = [
					'type' => 'function',
					'function' => [
						'name' => $method['operationId'],
						'description' => $method['description'],
						'parameters' => [
							'type' => 'object',
							'properties' => [],
							'required' => [],
						],
					],
					//'openapi' => $method,
				];
				$options = new Options();
				if (!empty($method['parameters']))
				{
					foreach ($method['parameters'] as $parameter)
					{
						if (isset($parameter['in']) && $parameter['in'] === 'header') continue;
						$tool['function']['parameters']['properties'][$parameter['name']] = ParameterConverter::convertFromParameter((object)(['schema' => (object)$parameter['schema']]+$parameter), $options);
						if (!empty($parameter['required']))
						{
							$tool['function']['parameters']['required'][] = $parameter['name'];
						}
					}
				}
				if (!empty($method['requestBody']['required']))
				{
					if (($body = $method['requestBody']['content']['application/json-patch+json'] ?? $method['requestBody']['content']['application/json-patch+json'] ?? null) &&
						($ref = $body['schema']['$ref'] ?? null))
					{
						$json_schema = SchemaConverter::convertFromSchema((object)self::getReference($openapi, $ref), $options);
						$tool['function']['parameters']['properties'] = array_merge($tool['function']['parameters']['properties'], $json_schema->properties);
						if ($json_schema->required)
						{
							$tool['function']['parameters']['required'] = array_merge($tool['function']['parameters']['required'], $json_schema->required);
						}
					}
				}
				// remove not used/required $schema property
				foreach($tool['function']['parameters']['properties'] as &$property)
				{
					unset($property->{'$schema'});
				}
				$tools[] = $tool;
			}
		}
		return $tools;
	}

	protected static function getReference($object, $ref)
	{
		if (substr($ref, 0, 2) !== '#/')
		{
			throw new Api\Exception("Unsupported reference '$ref'!");
		}
		foreach(explode('/', substr($ref, 2)) as $part)
		{
			if (!isset($object[$part]))
			{
				throw new Api\Exception("Unsupported reference part '$part'!");
			}
			$object = $object[$part];
		}
		return $object;
	}
}

if (str_ends_with(__FILE__, $_SERVER['REQUEST_URI']))
{
	$GLOBALS['egw_info'] = [
		'flags' => [
			'currentapp' => 'groupdav',
			'noheader' => true,
		],
	];
	require_once '../../../header.inc.php';

	header('Content-type: application/json');
	echo json_encode(OpenAPI::tools([], true, ['createContact']), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
	exit;
}