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
	 * @param bool $verify_peer false: disable curl's TLS certificate verification (default true,
	 *  curl's own default) - only ever pass false for a caller-managed, deliberate opt-out (eg.
	 *  Mail\Jmap's per-account acc_imap_ssl verification state), never as a general default
	 * @param int|null $connect_timeout seconds to wait for the TCP/TLS connect phase specifically
	 *  (CURLOPT_CONNECTTIMEOUT) before giving up - null (default) leaves curl's own compiled-in
	 *  default in place (commonly ~75-300s depending on platform/build - found live 2026-08-26 to
	 *  make an unreachable JMAP host's wizard auto-detection hang far longer than the comparable
	 *  IMAP/TCP probes elsewhere in the mail wizard, which use single-digit-second timeouts, eg.
	 *  Mail\Account::diagnoseConnection()/probeCertVerification()). Only bounds the connect phase,
	 *  not the whole request - a slow-but-reachable server answering a real method call is
	 *  unaffected.
	 * @return array|string array of decoded JSON or string body
	 * @throws \JsonException for invalid JSON
	 * @throws \InvalidArgumentException if $only_public and $url or redirects resolve to a non-public IP address
	 * @throws HttpException with code=0: opening http connection, code=HTTP status, if status is NOT 2xx
	 */
	public function api(string $url, string $method='GET', $body='', array $header=['Content-Type: application/json'], ?array &$response_header=null,
		int $follow=3, bool $only_public=true, bool $verify_peer=true, ?int $connect_timeout=null)
	{
		if ($url[0] === '/')
		{
			$url = $this->base_url . $url;
		}
		if (in_array(strtoupper($method), ['GET', 'DELETE', 'HEAD']) && $body && !is_resource($body))
		{
			$url .= '?' . (is_array($body) ? http_build_query($body) : $body);
		}

		// redirects are followed by hand below, validating (if $only_public) every hop's target
		// BEFORE connecting to it - CURLOPT_FOLLOWLOCATION would have curl follow the whole chain
		// internally in one curl_exec(), so by the time this code could inspect a redirect target
		// the request to it would already have gone out over the network
		$redirects_left = $follow;
		while (true)
		{
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
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
			curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			if (!$verify_peer)
			{
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
			}
			if (isset($connect_timeout))
			{
				curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
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
			// built fresh from the caller's own $header each hop, so re-looping never accumulates
			// duplicate/stale entries (eg. a second "Accept: application/json" fallback)
			$request_header = $header+['User-Agent' => static::class];
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
			if (!isset($request_header['Accept']) && !array_filter($request_header, static function($h)
			{
				return stripos($h, 'Accept:') === 0;
			}))
			{
				$request_header[] = 'Accept: application/json';
			}
			curl_setopt($curl, CURLOPT_HTTPHEADER, $request_header);
			$response_header = [];
			if (($response = curl_exec($curl)) === false)
			{
				throw new HttpException(curl_error($curl), 0, $method, $url, $body);
			}
			curl_close($curl);
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

			// a 3xx status without a Location header (eg. 304 Not Modified) has nothing to follow
			if ($http_status[0] === '3' && $redirects_left > 0 && isset($response_header['location']))
			{
				// resolve scheme-relative ("//host/path") and absolute-path ("/path") Locations
				// against the CURRENT $url first, THEN loop back to the top to validate the
				// resolved target - a scheme-relative Location also starts with '/', but curl (and
				// every browser) resolves it against a DIFFERENT host, same as an absolute one
				$url = self::resolveRedirectUrl($response_header['location'], $url);
				$redirects_left--;
				continue;
			}
			break;
		}

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
	 * Resolve a redirect Location header against the URL it was received from
	 *
	 * @param string $location Location header value: absolute URL, scheme-relative ("//host/path"),
	 *  absolute-path ("/path") or relative ("path")
	 * @param string $base URL the response with that Location header came from
	 * @return string absolute URL
	 */
	private static function resolveRedirectUrl(string $location, string $base): string
	{
		if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location))
		{
			return $location;
		}
		$base_scheme = parse_url($base, PHP_URL_SCHEME);
		// scheme-relative: resolves against a DIFFERENT host, same as a full absolute URL
		if (str_starts_with($location, '//'))
		{
			return $base_scheme.':'.$location;
		}
		$base_authority = $base_scheme.'://'.parse_url($base, PHP_URL_HOST).
			(($port = parse_url($base, PHP_URL_PORT)) ? ':'.$port : '');
		if ($location[0] === '/')
		{
			return $base_authority.$location;
		}
		// relative path: resolve against the base URL's own directory
		$base_path = parse_url($base, PHP_URL_PATH) ?? '/';
		return $base_authority.substr($base_path, 0, strrpos($base_path, '/') + 1).$location;
	}

	/**
	 * Check host does not resolve to a private or reserved IP address
	 *
	 * @param string $url hostname or URL
	 * @throws \InvalidArgumentException if $host is or resolved to private or reserved IP address, or $url has a non-http(s) scheme
	 * @return void
	 */
	public function checkPublicIP(string $url)
	{
		// reject any explicit non-http(s) scheme (eg. file://, gopher://) - a bare hostname (no
		// scheme at all) is left alone, parse_url() then returns null for PHP_URL_SCHEME
		if (($scheme = parse_url($url, PHP_URL_SCHEME)) !== null && !in_array(strtolower($scheme), ['http', 'https'], true))
		{
			throw new \InvalidArgumentException("URL scheme '{$scheme}' is not allowed, only http/https!");
		}
		$host = parse_url($url, PHP_URL_HOST) ?? $url;

		// parse_url() keeps a URL's bracketed IPv6 literal ("[::1]") as-is - filter_var() only
		// recognizes the bare form ("::1"), so without stripping the brackets here, EVERY IPv6
		// literal (not just loopback/private ones) would silently miss the IP check below and fall
		// through to the dns_get_record() branch, where a literal address resolves nothing and
		// passes unchecked while curl still connects to it directly
		$host = trim($host, '[]');

		// an IPv6 literal may carry a zone/scope ID ("%lo", or percent-encoded "%25lo" per RFC
		// 6874) - curl honors it and still connects to the address it's attached to (which does
		// not have to be an actually-scoped link-local address - curl accepts a zone ID on any
		// IPv6 literal, including loopback), but filter_var() rejects the whole string once a zone
		// ID is appended, so without stripping it here this would fall through to the (always
		// empty) dns_get_record() branch and pass unchecked
		if (($zone_pos = strpos($host, '%')) !== false)
		{
			$host = substr($host, 0, $zone_pos);
		}

		// check if host is already an IP address
		if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6))
		{
			if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 |
				FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE))
			{
				throw new \InvalidArgumentException("Host '{$host}' is a private or reserved IP address!");
			}
		}
		// curl (via the system resolver's inet_aton-style numeric-host parsing) also accepts
		// decimal ("2130706433"), hex ("0x7f000001"), octal ("0177.0.0.1") and "short" dotted
		// (fewer than 4 parts, eg. "127.1") IPv4 encodings - none of which filter_var() recognizes
		// as an IP, so they'd otherwise fall through to the dns_get_record() branch below, find no
		// records, and pass unchecked while curl still resolves and connects to the address they
		// encode. No legitimate hostname is ever written this way, so reject the whole class
		// outright rather than replicating that parsing here.
		elseif (preg_match('/^(0x[0-9a-f]+|0[0-9]+|[0-9]+)(\.(0x[0-9a-f]+|0[0-9]+|[0-9]+)){0,3}\.?$/i', $host))
		{
			throw new \InvalidArgumentException("Host '{$host}' uses a non-standard IP notation, which is not allowed!");
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
