<?php
/**
 * EGroupware API: REST API client for PHP
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage caldav/rest
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2024 by Ralf Becker <rb@egroupware.org>
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api\Exception\Http as HttpException;

/**
 * REST API client, to be used by any class needing to make outgoing HTTP/REST calls
 *
 * Originally doc/REST-CalDAV-CardDAV/api-client.php's global functions, moved here as a
 * reusable trait since it's used by production code (Api\Mail\Jmap, CalDAV\Sync,
 * news_admin_import), not just documentation/example scripts. doc/REST-CalDAV-CardDAV/
 * api-client.php now only keeps a thin backward-compatible shim for old standalone scripts.
 *
 * Unlike the old global functions, there is deliberately NO built-in credential-storage
 * mechanism (the old global $authorization array, keyed by hostname): pass any
 * Authorization header explicitly via $header on each api()/apiIterator() call, the same
 * way CalDAV\Sync::header() already does. A caller that genuinely needs per-host
 * credentials spanning multiple calls (eg. Api\Mail\Jmap, which can talk to more than one
 * host) should keep that lookup as its own concern, not push it into this trait.
 */
trait RestClientTrait
{
	/**
	 * Base URL prepended to any $url starting with '/', eg. "https://example.org/egroupware/groupdav.php"
	 *
	 * @var string
	 */
	public string $base_url = '';

	/**
	 * Iterate through API calls on collections
	 *
	 * This function only queries a limited number of entries (default 100) and uses sync-token to query more.
	 *
	 * @param string $url either path (starting with / and prepending $this->base_url) or full URL
	 * @param array& $params can contain optional "sync-token" (default="") and "nresults" (default=100) and returns final "sync-token"
	 * @param bool $only_public true: reject to connect or return results from private or reserved IP addresses
	 * @return \Generator<array> yields array with additional value for key "@self" containing the key of the responses-object yielded
	 * @throws \JsonException|\Exception see api()
	 */
	public function apiIterator(string $url, array &$params=[], bool $only_public=true)
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
			$responses = $this->api($url, 'GET', $params, only_public: $only_public);
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

	/**
	 * Make an API call to given URL
	 *
	 * @param string $url either path (starting with / and prepending $this->base_url) or full URL
	 * @param string $method
	 * @param string|array|resource $body for GET&DELETE this is added as query and must not be a resource/file-handle
	 * @param array $header eg. an "Authorization: ..." header, if the target requires authentication
	 * @param array|null $response_header associative array of response headers, key 0 has HTTP status
	 * @param int $follow how many redirects to follow, default 3, can be set to 0 to NOT follow
	 * @param bool $only_public true: reject to connect or return results from private or reserved IP addresses
	 * @return array|string array of decoded JSON or string body
	 * @throws \JsonException for invalid JSON
	 * @throws \InvalidArgumentException if $only_public and $url or redirects resolve to a non-public IP address
	 * @throws HttpException with code=0: opening http connection, code=HTTP status, if status is NOT 2xx
	 */
	public function api(string $url, string $method='GET', $body='', array $header=['Content-Type: application/json'], ?array &$response_header=null,
		int $follow=3, bool $only_public=true)
	{
		if ($url[0] === '/')
		{
			$url = $this->base_url . $url;
		}
		if (in_array(strtoupper($method), ['GET', 'DELETE', 'HEAD']) && $body && !is_resource($body))
		{
			$url .= '?' . (is_array($body) ? http_build_query($body) : $body);
		}
		if ($only_public)
		{
			$this->checkPublicIP($url);
		}
		if (!($curl = curl_init($url)))
		{
			throw new \Exception(curl_error($curl));
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		if ($follow > 0)
		{
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($curl, CURLOPT_MAXREDIRS, $follow);
		}

		switch (strtoupper($method))
		{
			case 'POST':
				curl_setopt($curl, CURLOPT_POST, true);
				break;
			default:
			case 'PUT':
			case 'DELETE':
			case 'PATCH':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
				break;
			case 'GET':
				curl_setopt($curl, CURLOPT_HTTPGET, true);
				break;
			case 'HEAD':
				curl_setopt($curl, CURLOPT_NOBODY, true);
				break;
		}
		$header = $header+['User-Agent' => static::class];
		if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'REPORT', 'PROPFIND', 'PROPPATCH']))
		{
			if (is_resource($body))
			{
				fseek($body, 0, SEEK_END);
				curl_setopt($curl, CURLOPT_INFILESIZE, ftell($body));
				fseek($body, 0);
			}
			curl_setopt($curl, is_resource($body) ? CURLOPT_INFILE : CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
		}
		if (!isset($header['Accept']) && !array_filter($header, static function($header)
		{
			return stripos($header, 'Accept:') === 0;
		}))
		{
			$header[] = 'Accept: application/json';
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		$response_header = [];
		if (($response = curl_exec($curl)) === false)
		{
			throw new HttpException(curl_error($curl), 0, $method, $url, $body);
		}
		do {
			[$rheader, $response] = explode("\r\n\r\n", $response, 2);
			foreach (explode("\r\n", $rheader) as $line)
			{
				list($key, $value) = explode(':', $line, 2) + [null, null];
				if (!isset($value))
				{
					$response_header[0] = $key;
				}
				else
				{
					$response_header[strtolower($key)] = trim($value);
				}
			}
			[, $http_status] = explode(' ', $response_header[0], 2);

			// if we got a redirect, check that the location is either on the same server or also has a valid public IP
			// (a 3xx status without a Location header, eg. 304 Not Modified, has nothing to check)
			if ($only_public && $http_status[0] === '3' && $follow && isset($response_header['location']) &&
				$response_header['location'][0] !== '/')
			{
				$this->checkPublicIP($response_header['location']);
			}
		}
		while ($http_status[0] === '3' && $follow && preg_match('#^HTTP/[\d.]+ \d+#', $response));

		if ($http_status[0] !== '2')
		{
			throw new HttpException("Unexpected HTTP status code $http_status: ".
				($response_header['www-authenticate'] ?? ''), (int)$http_status,
				$method, $url, $body, $response_header, $response);
		}
		if ($response !== '' && preg_match('#^application/([^+; ]+\+)?json(;|$)#', $response_header['content-type']))
		{
			return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
		}
		return $response;
	}

	/**
	 * Check host does not resolve to a private or reserved IP address
	 *
	 * @param string $url hostname or URL
	 * @throws \InvalidArgumentException if $host is or resolved to private or reserved IP address
	 * @return void
	 */
	public function checkPublicIP(string $url)
	{
		$host = parse_url($url, PHP_URL_HOST) ?? $url;

		// check if host is already an IP address
		if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6))
		{
			if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 |
				FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE))
			{
				throw new \InvalidArgumentException("Host '{$host}' is a private or reserved IP address!");
			}
		}
		// if not try to resolve it
		else
		{
			foreach (dns_get_record($host) as $record)
			{
				// dns_get_record() returns the address under 'ip' for A records, but 'ipv6' for AAAA
				$ip = $record['type'] === 'AAAA' ? ($record['ipv6'] ?? null) : ($record['ip'] ?? null);
				if (in_array($record['type'], ['A', 'AAAA'], true) && $ip !== null &&
					!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 |
						FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
				{
					throw new \InvalidArgumentException("Host '{$host}' resolves to private or reserved IP address '{$ip}'!");
				}
			}
		}
	}
}
