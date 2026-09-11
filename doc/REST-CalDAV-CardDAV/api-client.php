<?php
/**
 * EGroupware - REST API client for PHP
 *
 * @deprecated this is now only a backward-compatibility shim for old standalone scripts
 *   (global functions/variables, no namespace). New code should use
 *   \EGroupware\Api\CalDAV\RestClientTrait and \EGroupware\Api\Exception\Http directly
 *   (api/src/CalDAV/RestClientTrait.php, api/src/Exception/Http.php) - see those for the
 *   real implementation and documentation. This shim keeps the old global
 *   $base_url/$authorization (hostname => "Authorization: ..." header) variable-driven
 *   interface working, which the trait itself deliberately does NOT have.
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage caldav/rest
 * @author Ralf Becker <rb-at-egroupware.org>
 * @copyright (c) 2024 by Ralf Becker <rb-at-egroupware.org>
 */

/* Example usage of this client:
require_once('/path/to/egroupware/doc/api-client.php');

if (PHP_SAPI !== 'cli')
{
	die('This script can only be run from the command line.');
}

set_exception_handler('http_exception_handler');

$base_url = 'https://egw.example.org/egroupware/groupdav.php';
$authorization[parse_url($base_url, PHP_URL_HOST)] = 'Authorization: Basic '.base64_encode('sysop:secret');

$params = [
	'filters[info_status]' => 'archive',
];
$courses = [];
foreach(apiIterator('/infolog/', $params) as $infolog)
{
	echo json_encode($infolog, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    foreach($infolog['participants'] as $account_id => $participant)
    {
        if ($participant['roles']['owner'] ?? false)
        {
            echo json_encode($contact=api('/addressbook-accounts/'.$account_id),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
            break;
        }
    }

}
*/

require_once __DIR__.'/../../api/src/autoload.php';

if (!class_exists('HttpException', false))
{
	class_alias(\EGroupware\Api\Exception\Http::class, 'HttpException');
}

if (!function_exists('api'))
{
	/**
	 * Make an API call to given URL
	 *
	 * Authorization is added from global $authorization array indexed by host-name of $url or $base_url
	 *
	 * @param string $url either path (starting with / and prepending global $base_url) or full URL
	 * @param string $method
	 * @param string|array|resource $body for GET&DELETE this is added as query and must not be a resource/file-handle
	 * @param array $header
	 * @param array|null $response_header associative array of response headers, key 0 has HTTP status
	 * @param int $follow how many redirects to follow, default 3, can be set to 0 to NOT follow
	 * @param bool $only_public true: reject to connect or return results from private or reserved IP addresses
	 * @return array|string array of decoded JSON or string body
	 * @throws JsonException for invalid JSON
	 * @throws InvalidArgumentException if $only_public and $url or redirects resolve to a non-public IP address
	 * @throws HttpException with code=0: opening http connection, code=HTTP status, if status is NOT 2xx
	 */
	function api(string $url, string $method='GET', $body='', array $header=['Content-Type: application/json'], ?array &$response_header=null,
		int $follow=3, bool $only_public=true)
	{
		global $base_url, $authorization;

		static $client = null;
		if (!$client)
		{
			$client = new class { use \EGroupware\Api\CalDAV\RestClientTrait; };
		}
		$client->base_url = $base_url ?? '';

		$lookup_url = $url[0] === '/' ? $client->base_url.$url : $url;
		if (isset($authorization[parse_url($lookup_url, PHP_URL_HOST) ?: $lookup_url]))
		{
			$header[] = $authorization[parse_url($lookup_url, PHP_URL_HOST) ?: $lookup_url];
		}
		return $client->api($url, $method, $body, $header, $response_header, $follow, $only_public);
	}
}

if (!function_exists('apiIterator'))
{
	/**
	 * Iterate through API calls on collections
	 *
	 * This function only queries a limited number of entries (default 100) and uses sync-token to query more.
	 *
	 * @param string $url either path (starting with / and prepending global $base_url) or full URL
	 * @param array& $params can contain optional "sync-token" (default="") and "nresults" (default=100) and returns final "sync-token"
	 * @param bool $only_public true: reject to connect or return results from private or reserved IP addresses
	 * @return Generator<array> yields array with additional value for key "@self" containing the key of the responses-object yielded
	 * @throws JsonException|Exception see api
	 */
	function apiIterator(string $url, array &$params=[], bool $only_public=true)
	{
		while(true)
		{
			if (!isset($params['nresults']))
			{
				$params['nresults'] = 100;
			}
			if (!isset($params['sync-token']))
			{
				$params['sync-token']='';
			}
			$responses = api($url, 'GET', $params, only_public: $only_public);
			if (!isset($responses['responses']))
			{
				throw new \Exception('Invalid respose: '.(is_scalar($responses) ? $responses : json_encode($responses)));
			}
			foreach($responses['responses'] as $self => $response)
			{
				$response['@self'] = $self;

				yield $response;
			}
			$params['sync-token'] = $responses['sync-token'] ?? '';
			if (empty($responses['more-results']))
			{
				return;
			}
		}
	}
}

if (!function_exists('http_exception_handler'))
{
	/**
	 * HttpException handler dumping a failed HTTP request
	 *
	 * To be used as:
	 * - set_exception_handler('http_exception_handler')
	 * - set_exception_handler(static function($ex) { http_exception_handler($ex, $trace, $exit); })
	 *
	 * @param Throwable $exception
	 * @param bool $trace true: show a trace
	 * @param bool $exit true: exit with $exception->code, false: don't exit
	 */
	function http_exception_handler(Throwable $exception, bool $trace=true, bool $exit=true)
	{
		echo $exception->getMessage()."\n\n";
		if ($exception instanceof HTTPException)
		{
			echo $exception->method.' '.$exception->request_uri."\n";
			if (is_string($exception->request_body))
			{
				echo $exception->request_body."\n";
			}
			if (isset($exception->response_headers))
			{
				echo "\n".implode("\n", array_map(static function($name, $value)
				{
					return (is_int($name) ? '' :
						implode('-', array_map('ucfirst', explode('-', $name))).': ').$value;
				}, array_keys($exception->response_headers), $exception->response_headers))."\n\n";
				if (!empty($exception->response))
				{
					echo $exception->response."\n\n";
				}
			}
		}
		if ($trace)
		{
			echo $exception->getTraceAsString()."\n";
		}
		if ($exit)
		{
			exit($exception->getCode() ?: 500);
		}
	}
}
