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
	 * Methods callable via menuaction
	 */
	public $public_functions = [
		'configuration' => true,
	];

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

		$operationIds = [];
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
						unset($app_json['paths'][$path]);
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
	public static function toolCall(string $operationId, array $params = [], array $operationIdFilter=[], bool $deny=true)
	{
		// to not block on the session
		$GLOBALS['egw']->session->commit_session();

		try
		{
			// check if the tool-call is allowed
			if (in_array($operationId, $operationIdFilter) === $deny)
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
						// handle GET parameters like filters[search]=... or props[]=... correct
						if (in_array(strtolower($method), ['get', 'head', 'options', 'delete']))
						{
							$params = self::fixGetParameters($params);
						}
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
	 * Convert params with square brackets like filters[search]=... or props[]=... to an array like $_GET / parsed GET parameters
	 *
	 * @param array $params
	 * @return array
	 */
	protected static function fixGetParameters(array $params): array
	{
		foreach($params as $name => $value)
		{
			if (str_ends_with($name, '[]'))
			{
				$name = substr($name, 0, -2);
				$params[$name] ??= [];
				if (is_array($value))
				{
					$params[$name] = array_merge_recursive($params[$name], $value);
				}
				else
				{
					$params[$name][] = $value;
				}
				unset($params[$name]);
			}
			elseif (strpos($name, '[') !== false)
			{
				parse_str($name.'='.urlencode($value), $result);
				$params = array_merge_recursive($params, $result);
				unset($params[$name]);
			}
		}
		return $params;
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
						// convert some not existing schema like "datetime"
						switch ($parameter['schema']['type']??null)
						{
							case 'datetime':
								$parameter['schema'] = (object)['type' => 'string', 'format' => 'data-time', 'examples' => ['2026-05-16', '2026-05-16 12:00:00']];
								break;
						}
						$tool['function']['parameters']['properties'][$parameter['name']] = ParameterConverter::convertFromParameter((object)(['schema' => (object)$parameter['schema']]+$parameter), $options);
						if (!empty($parameter['required']))
						{
							$tool['function']['parameters']['required'][] = $parameter['name'];
						}
					}
				}
				if (!empty($method['requestBody']['required']))
				{
					if (($body = $method['requestBody']['content']['application/json-patch+json'] ?? $method['requestBody']['content']['application/json'] ?? null) &&
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

	/**
	 * Open WebUI User-Agent regular expression
	 */
	const OPENWEBUI_USER_AGENT = '#^Python/[0-9.]+ aiohttp/[0-9.]+$#';

	/**
	 * OpenAPI configuration
	 *
	 * @param ?array $content
	 */
	public static function configuration(?array $content=null): void
	{
		if (empty($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			throw new Api\Exception\NoPermission\Admin();
		}
		if (!isset($content) || !is_array($content))
		{
			$content = ['config' => Api\Config::read('caldav')['openapi'] ?? self::defaultConfig()];
		}
		elseif (!empty($content['button']) || !empty($content['config']['delete']))
		{
			$button = key($content['button']??[])??'delete';
			$delete = key($content['config']['delete'] ?? []);
			unset($content['button']);
			$content['config'] = array_values(array_filter($content['config'], static fn($row) => !empty($row['user-agent']??null) || !empty($row['regexp']??null)));
			switch ($button)
			{
				case 'save':
				case 'apply':
					foreach($content['config'] as $n => $row)
					{
						preg_match($row['regexp'], '');
						if (preg_last_error() !== PREG_NO_ERROR)
						{
							Api\Etemplate::set_validation_error('config['.++$n.'][regexp]', lang('Invalid regular expression').': '.preg_last_error_msg());
							break 2;
						}
					}
					Api\Config::save_value('openapi', $content['config'], 'caldav');
					Api\Framework::message('Configuration saved.');
					if ($button == 'apply') break;
					// fall-through
				case 'cancel':
					Api\Framework::redirect_link('/index.php', 'menuaction=admin.admin_ui.index&ajax=true');
					break;
				case 'delete':
					unset($content['config'][$delete-1]);
					$content['config'] = array_values($content['config'] ?? []);
					break;
			}
		}
		// account for 1 header-rows and one empty row below
		array_unshift($content['config'], false);
		$content['config'][] = ['user-agent' => ''];

		$etemplate = new Api\Etemplate('api.openapi-config');
		$etemplate->exec('api.'.self::class.'.configuration', $content, [
			'operationIds' => self::operationIds(),
		]);
	}

	/**
	 * Return default configuration, if config has not been saved
	 *
	 * @return array[]
	 */
	public static function defaultConfig()
	{
		return [
			[
				'user-agent' => 'Open WebUI',
				'regexp' => self::OPENWEBUI_USER_AGENT,
				'allow' => '',
				'operationIds' => ['sendMail', 'sendMailFor'],
				'default-matches' => 5,
			],
		];
	}

	/**
	 * Get the user-agent specific configuration
	 *
	 * @param ?string $user_agent default current user-agent
	 * @return array|null values for keys "allow", "operationIds" and "default-matches"
	 */
	public static function getUserAgentConfig(?string $user_agent=null) : ?array
	{
		if (empty($user_agent))
		{
			$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		}
		foreach(Api\Config::read('caldav')['openapi'] ?? self::defaultConfig() as $config)
		{
			if (preg_match($config['regexp'], $user_agent))
			{
				return $config;
			}
		}
		return null;
	}

	/**
	 * Check if given operationId is allowed for the user-agent
	 *
	 * @param string $operationId
	 * @param string|null $user_agent
	 * @return bool
	 */
	public static function checkOperationId(string $operationId, ?string $user_agent=null) : bool
	{
		if (!($config = self::getUserAgentConfig($user_agent)))
		{
			return true;
		}
		return in_array($operationId, $config['operationIds'] ?? []) === (bool)($config['allow']??false);
	}

	/**
	 * Get default number of matches for given user-agent
	 *
	 * @param string|null $user_agent
	 * @return int|null null if no limit is configured
	 */
	public static function defaultMatches(?string $user_agent=null) : ?int
	{
		if (!($config = self::getUserAgentConfig($user_agent)))
		{
			return null;
		}
		return isset($config['default-matches']) && $config['default-matches'] !== '' ? (int)$config['default-matches'] : null;
	}
}

if (str_ends_with(__FILE__, $_SERVER['SCRIPT_NAME']))
{
	$GLOBALS['egw_info'] = [
		'flags' => [
			'currentapp' => 'groupdav',
			'noheader' => true,
		],
	];
	require_once '../../../header.inc.php';

	header('Content-type: application/json');
	if (!empty($_GET['run']))
	{
		echo json_encode(OpenApi::toolCall($_GET['run'], array_diff_key($_GET, array_flip(['run']))), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
	}
	else
	{
		echo json_encode(OpenAPI::tools($_GET['operationIds'] ?? $_GET['operationids'] ?? [], empty($_GET['operationIds'] ?? $_GET['operationids'])),
				JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
	}
	exit;
}