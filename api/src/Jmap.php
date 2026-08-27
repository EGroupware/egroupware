<?php
/**
 * EGroupware Api: generic real-JMAP-over-HTTP session
 *
 * @link https://www.egroupware.org
 * @package api
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api;

use EGroupware\Api\CalDAV\RestClientTrait;

/**
 * Real JMAP-over-HTTP session (RFC 8620 Core) - data-type-agnostic transport, session bootstrap
 * and OAuth handling, plus the Core-layer operations that turn out to have nothing mail-specific
 * about them: blob upload/download (RFC 8620 §6), PushSubscription CRUD, and the generic
 * "/changes"-based sync mechanism. Everything RFC 8621 (Mail)-specific lives in `Api\Mail\Jmap`
 * instead - see doc/ai/projects/mail-jmap-imap-inversion.md.
 *
 * Absorbed from the former `Api\Mail\Jmap` client class (api/src/Mail/Jmap.php) - that class's
 * mail-specific sentinel-hostname resolution and Mailbox/Email convenience methods now live in
 * `Api\Mail\Jmap\Http` and `Api\Mail\Jmap`'s per-type classes instead.
 *
 * Named `Jmap` directly under `Api\`, not `Api\Jmap\Http` - matching this codebase's own
 * `Api\Storage`/`Api\Storage\Base` convention: the concrete, actually-used class lives where you
 * look first, the abstract base (`Jmap\Base`) one level down instead (ralf, 2026-08-27).
 *
 * @property-read string $accountId JMAP accountId set during bootstrap / constructor
 * @property-read array $capabilities JMAP capabilities
 * @property-read array $accountCapabilities JMAP accountCapabilities of $this->accountId
 * @property-read string $apiUrl e.g. "https://example.org:443/jmap/"
 * @property-read string $downloadUrl e.g. "https://example.org:443/jmap/download/{accountId}/{blobId}/{name}?accept={type}"
 * @property-read string $uploadUrl e.g. "https://example.org:443/jmap/upload/{accountId}/"
 * @property-read string $eventSourceUrl e.g. "https://example.org:443/jmap/eventsource/?types={types}&closeafter={closeafter}&ping={ping}"
 */
class Jmap extends Jmap\Base
{
	use RestClientTrait {
		api as protected httpApi;
	}

	/**
	 * Seconds to wait for the TCP/TLS connect phase of a JMAP HTTP call, before giving up -
	 * deliberately short: without this, curl's own compiled-in default (commonly ~75-300s,
	 * confirmed live 2026-08-26 against an unreachable host) made the mail wizard's JMAP
	 * auto-detection step (admin_mail::tryJmap()) hang far longer than the comparable IMAP/TCP
	 * probes it falls back to on failure. 5s matches ralf's own guidance: this only bounds the
	 * TCP/TLS *handshake*, not the whole request - a handshake that hasn't completed by then
	 * essentially never will, whereas a slow-but-reachable real JMAP server answering an actual
	 * method call afterwards is completely unaffected (no timeout on that phase at all).
	 */
	const CONNECT_TIMEOUT = 5;

	protected string $url;
	protected string $user;
	protected string $secret;
	protected array $well_known;

	/**
	 * Authorization header to send, indexed by hostname (or, before bootstrap resolves a bare
	 * service-name into a full URL, by that raw value) - a single session can talk to more than
	 * one host (eg. the JMAP host and a different OAuth provider host).
	 *
	 * @var array
	 */
	protected array $authorization = [];

	protected string $accountId;
	protected array $capabilities;
	protected array $accountCapabilities;

	protected string $apiUrl;
	protected string $downloadUrl;
	protected string $uploadUrl;
	protected string $eventSourceUrl;

	/**
	 * @var string|null path to log to, or null to disable logging
	 */
	protected ?string $log = null;

	/**
	 * Verify the server's TLS certificate - curl (used for all JMAP HTTP calls) verifies by
	 * default already, so this is only ever an opt-OUT, never an opt-in.
	 *
	 * @var bool
	 */
	protected bool $verify = true;

	/**
	 * Capability URNs to declare in every JMAP call's "using" - data-type-specific (eg. Mail
	 * needs urn:ietf:params:jmap:mail), so passed in by whichever app constructs this session
	 * rather than hardcoded here.
	 *
	 * @var string[]
	 */
	protected array $using;

	/**
	 * Constructor
	 *
	 * @param string $url JMAP url, or hostname to bootstrap via "https://$host/.well-known/jmap"
	 * @param string $user username
	 * @param string $secret password
	 * @param string[] $using capability URNs to declare in every call, eg. Api\Mail\Jmap\Http::JMAP_MAIL
	 * @param string|null &$accountId jmap accountId
	 * @param bool $verify =true false: disable TLS certificate verification for this connection -
	 *  never pass false as a blanket default
	 */
	public function __construct(string $url, string $user, string $secret, array $using, ?string &$accountId=null, bool $verify=true)
	{
		$this->url = $url;
		$this->user = $user;
		$this->secret = $secret;
		$this->using = $using;
		$this->verify = $verify;

		if (!preg_match('#^https?://#', $this->url))
		{
			$this->authorization[$this->url] = 'Authorization: Basic '.base64_encode($user.':'.$secret);

			$this->url = $this->bootstrap(true, $accountId);
		}
		else
		{
			$this->authorization[parse_url($this->url, PHP_URL_HOST)] = 'Authorization: Basic '.base64_encode($user.':'.$secret);

			$this->bootstrap(false, $accountId);
			// bootstrap() populates $this->apiUrl from the session document but (for this
			// $use_well_known=false branch) does NOT update $this->url itself - jmapCall()
			// posts to $this->url directly, so leaving it as the bare host/scheme would send
			// every JMAP method call to the server's ROOT instead of its actual JMAP API
			// endpoint (found live 2026-08-24).
			if ($this->apiUrl)
			{
				$this->url = $this->apiUrl;
			}
		}
		$this->accountId = $accountId;
	}

	/**
	 * Make an API call to given URL
	 *
	 * Authorization is added from $this->authorization, indexed by host-name of $url
	 *
	 * @param string $url either path (starting with / and prepending global $base_url) or full URL
	 * @param string $method
	 * @param string|array|resource $body for GET&DELETE this is added as query and must not be a resource/file-handle
	 * @param array $header
	 * @param array|null $response_header associative array of response headers, key 0 has HTTP status
	 * @param int $follow how many redirects to follow, default 3, can be set to 0 to NOT follow
	 * @return array|string array of decoded JSON or string body
	 * @throws \JsonException for invalid JSON
	 * @throws Exception\Http with code=0: opening http connection, code=HTTP status, if status is NOT 2xx
	 */
	function api(string $url, string $method='GET', $body='', array $header=['Content-Type: application/json'], ?array &$response_header=null, int $follow=3)
	{
		// Stalwart (v0.16.8) chokes on escaped slashes in method names like 'SieveScript/get'
		if (is_array($body))
		{
			$body = json_encode($body, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);

			if (!array_filter($header, fn ($value) => str_starts_with($value, 'Content-Type:')))
			{
				$header[] = 'Content-Type: application/json';
			}
		}
		if (($auth = $this->authorization[parse_url($url, PHP_URL_HOST) ?: $url] ?? null) !== null)
		{
			$header[] = $auth;
		}
		if (!$this->log)
		{
			return $this->httpApi($url, $method, $body, $header, $response_header, $follow, only_public: false, verify_peer: $this->verify, connect_timeout: self::CONNECT_TIMEOUT);
		}
		// logging request and response
		file_put_contents($this->log, date('Y-m-d H:i:s O')." $method $url\n".implode("\n", $header)."\n\n".$body."\n\n", FILE_APPEND);

		try {
			$response = $this->httpApi($url, $method, $body, $header, $response_header, $follow, only_public: false, verify_peer: $this->verify, connect_timeout: self::CONNECT_TIMEOUT);

			file_put_contents($this->log, date('Y-m-d H:i:s O').' '.
				implode("\n", array_map(fn($value, $key) => $key === 0 ? $value : "$key: $value", array_values($response_header), array_keys($response_header)))."\n\n".
					(is_scalar($response) ? $response : json_encode($response, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT))."\n\n", FILE_APPEND);

			return $response;
		}
		catch (\Throwable $e) {
			file_put_contents($this->log, date('Y-m-d H:i:s O').' '.$e->getMessage()."\n\n".$e->getTraceAsString()."\n\n", FILE_APPEND);

			if ($e instanceof Exception\Http)
			{
				file_put_contents($this->log,
					implode("\n", array_map(fn($value, $key) => $key === 0 ? $value : "$key: $value", array_values($e->response_headers), array_keys($e->response_headers)))."\n\n".
					(is_scalar($e->response) ? $e->response : json_encode($e->response, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT))."\n\n", FILE_APPEND);
			}
			throw $e;
		}
	}

	/**
	 * Get JMAP apiUrl from /.well-known/jmap and fill $this->well_known for later reference
	 *
	 * @param bool $use_well_known true: request https://$host/.well-known/jmap, false: $url/
	 * @param string|null $accountId
	 * @return string
	 * @throws Exception
	 * @throws \JsonException
	 */
	public function bootstrap(bool $use_well_known=true, ?string &$accountId=null) : string
	{
		$url = $this->url;
		if (!preg_match('#^https?://#', $url))
		{
			$url = 'https://'.$url;
		}
		else
		{
			$url = preg_replace('#^(https?://[^/]+)(/.*)$#', '$1', $url);
		}
		$response = $this->api($url.'/.well-known/jmap');

		foreach($response['accounts'] ?? [] as $id => $account)
		{
			if ($account['isPersonal'])
			{
				$accountId = $id;
				$this->accountCapabilities = $account['accountCapabilities'] ?? [];
				break;
			}
		}
		$this->capabilities = $response['capabilities'] ?? [];
		$this->apiUrl = $response['apiUrl'] ?? null;
		$this->downloadUrl = $response['downloadUrl'] ?? null;
		$this->uploadUrl = $response['uploadUrl'] ?? null;
		$this->eventSourceUrl = $response['eventSourceUrl'] ?? null;

		return !$use_well_known ? $this->url : $this->apiUrl ?? throw new Exception("$this->url is NOT a JMAP server!");
	}

	/**
	 * client_id we present to the server's OAuth endpoint
	 */
	const OAUTH_CLIENT_ID = 'egroupware';

	/**
	 * Never dereferenced by the server, only compared between the auth and token calls of a
	 * single exchange, so an arbitrary (but https) URL is fine.
	 */
	const OAUTH_REDIRECT_URI = 'https://egroupware.org/oauth/stalwart-imap';

	/**
	 * Switch authentication from Basic username/password to a Bearer access-token
	 *
	 * @param string $token
	 */
	public function setBearerToken(string $token)
	{
		$this->authorization[parse_url($this->url, PHP_URL_HOST) ?: $this->url] = 'Authorization: Bearer '.$token;
	}

	/**
	 * Authenticate directly with $username/$password against Stalwart's own OAuth server to
	 * obtain an access- and refresh-token, without any browser or user interaction.
	 *
	 * Stalwart does not implement the OAuth2 "password" grant (RFC 6749 §4.3). We instead
	 * replicate what Stalwart's own WebUI does on login: POST credentials to /api/auth (the
	 * single, unavoidable bcrypt check) to get a one-time client-code, then exchange that code
	 * for tokens at the regular /auth/token endpoint using the "authorization_code" grant - all
	 * server-side, no redirect or user interaction required.
	 *
	 * @link https://github.com/stalwartlabs/stalwart/blob/main/crates/http/src/auth/oauth/auth.rs
	 * @param string $username
	 * @param string $password
	 * @return array|null null if NOT a Stalwart server, wrong credentials, or MFA is required,
	 *  otherwise values for keys "access_token", "refresh_token", "expires_in"
	 */
	public function passwordGrant(string $username, string $password) : ?array
	{
		try {
			$response = $this->api($this->oauthBaseUrl().'/api/auth', 'POST', [
				'type' => 'authCode',
				'accountName' => $username,
				'accountSecret' => $password,
				'clientId' => self::OAUTH_CLIENT_ID,
				'redirectUri' => self::OAUTH_REDIRECT_URI,
			]);
			if (($response['type'] ?? null) !== 'authenticated' || empty($response['client_code']))
			{
				return null;	// wrong credentials, MFA required, or request not accepted
			}
			return $this->exchangeToken('authorization_code', [
				'code' => $response['client_code'],
				'redirect_uri' => self::OAUTH_REDIRECT_URI,
			]);
		}
		catch (\Throwable $e) {
			return null;	// not a Stalwart server, network- or other error
		}
	}

	/**
	 * Use a refresh-token to get a new access-token (and possibly a new refresh-token)
	 *
	 * @param string $refresh_token
	 * @return array|null null if refresh failed (token revoked or expired), otherwise values
	 *  for keys "access_token", "refresh_token", "expires_in"
	 */
	public function refreshToken(string $refresh_token) : ?array
	{
		try {
			return $this->exchangeToken('refresh_token', [
				'refresh_token' => $refresh_token,
			]);
		}
		catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * Call the server's /auth/token endpoint
	 *
	 * @param string $grant_type "authorization_code" or "refresh_token"
	 * @param array $params grant-specific parameters
	 * @return array|null null on error (RFC 6749 §5.2), otherwise values for keys
	 *  "access_token", "token_type", "expires_in", "refresh_token", "scope"
	 */
	protected function exchangeToken(string $grant_type, array $params) : ?array
	{
		$response = $this->api($this->oauthBaseUrl().'/auth/token', 'POST',
			http_build_query(['grant_type' => $grant_type, 'client_id' => self::OAUTH_CLIENT_ID]+$params),
			['Content-Type: application/x-www-form-urlencoded']);

		return isset($response['access_token']) ? $response : null;
	}

	/**
	 * Get scheme+host of the JMAP server, to build OAuth endpoint URLs from
	 *
	 * @return string e.g. "https://example.org:443"
	 */
	protected function oauthBaseUrl() : string
	{
		return preg_replace('#^(https?://[^/]+)(/.*)$#', '$1', $this->apiUrl ?: $this->url);
	}

	/**
	 * Get the JMAP session-discovery URL (RFC 8620 .well-known/jmap)
	 *
	 * Used by clients (e.g. the browser via jmap-jam) that need to bootstrap their own JMAP
	 * session, given only a bearer access-token, without ever seeing the password.
	 *
	 * @return string e.g. "https://example.org/.well-known/jmap"
	 */
	public function sessionUrl() : string
	{
		return $this->oauthBaseUrl().'/.well-known/jmap';
	}

	/**
	 * Simple JSON path implementation
	 *
	 * @param array $value
	 * @param string $path
	 * @return null|mixed null if value not found, or value at $path
	 */
	protected static function jsonPath(array $value, string $path)
	{
		if ($path[0] !== '/')
		{
			return null;
		}
		foreach(explode('/', substr($path, 1)) as $component)
		{
			if (!isset($value[$component]))
			{
				return null;
			}
			$value = $value[$component];
		}
		return $value;
	}

	/**
	 * JMAP core
	 */
	const JMAP_CORE = "urn:ietf:params:jmap:core";

	/**
	 * Make a JMAP call - emulating multiple methodCalls with single calls and resolving references
	 *
	 * This fixes what seems to be a bug in Stalwart 0.11.x reference implementation
	 *
	 * @link https://github.com/stalwartlabs/mail-server/discussions/1508
	 * @param array $methodCalls [string $method, array $args, string $id][]
	 * @param string|array|null $using capability URNs, defaults to $this->using
	 * @param bool $emulate true: emulate multiple methodCalls, false: send them in one call to the server
	 * @return array response
	 */
	public function jmapCall(array $methodCalls, string|array|null $using=null, bool $emulate=false)
	{
		$using ??= $this->using;

		// some basic checks
		foreach($methodCalls as $n => &$call)
		{
			if (!is_array($call) || count($call) !== 3 ||
				!is_string($call[0]) || !is_array($call[1]) || !is_string($call[2]))
			{
				throw new \InvalidArgumentException("Invalid method call #$n: ".json_encode($call, JSON_UNESCAPED_SLASHES));
			}
		}
		if (!$emulate || count($methodCalls) === 1)
		{
			return $this->api($this->url, 'POST', [
				'using' => (array)$using,
				'methodCalls' => $methodCalls,
			]);
		}
		$responses = [];
		foreach($methodCalls as $methodCall)
		{
			foreach($methodCall[1] as $name => $value)
			{
				if ($name[0] === '#')
				{
					unset($methodCall[1][$name]);
					$name = substr($name, 1);
					if (count($reference = array_values(array_filter($responses, function($response) use ($value) {
						return $response[2] === $value['resultOf'];
					}))) !== 1 || $reference[0][0] !== $value['name'] ||
						!isset($value['path']) || !is_array($reference[0][1] ?? null) ||
						($methodCall[1][$name] = self::jsonPath($reference[0][1], $value['path'])) === null)
					{
						$responses[] = ['error', [
							'type' => 'invalidResultReference',
							'description' => 'Failed to evaluate '.json_encode($value).' result reference.',
						], $methodCall[2]];
						continue 2;
					}
					// no need to run a call with ids === [], it will always return an empty list
					// more importantly, it might not have updatedProperties and therefore generate a reference error
					if ($name === 'ids' && !count($methodCall[1][$name]))
					{
						$responses[] = [$methodCall[0], ['list' => [], 'notFound' => []], $methodCall[2]];
						continue 2;
					}
				}
			}
			$responses[] = ($response = $this->jmapCall([$methodCall], $using, false))['methodResponses'][0];
		}
		return [
			'methodResponses' => $responses,
			'sessionState' => $response['sessionState'] ?? null,
		];
	}

	/**
	 * @inheritDoc
	 *
	 * Wraps a single method call in jmapCall()'s batch shape and unwraps the one response.
	 */
	public function call(string $method, array $args) : array
	{
		$response = $this->jmapCall([[$method, $args, '0']]);
		return $response['methodResponses'][0][1] ?? throw new Exception(__METHOD__.": no response for $method: ".json_encode($response));
	}

	/**
	 * Download a Blob (RFC 8620 §6.2)
	 *
	 * @param string $blobId
	 * @param string $name suggested filename, substituted into the download URL only
	 * @param string $type expected mime-type, substituted into the download URL only
	 * @return string raw bytes
	 * @throws Exception|Exception\Http
	 */
	public function downloadBlob(string $blobId, string $name='blob', string $type='application/octet-stream') : string
	{
		$url = strtr($this->downloadUrl, [
			'{accountId}' => rawurlencode($this->accountId),
			'{blobId}' => rawurlencode($blobId),
			'{name}' => rawurlencode($name),
			'{type}' => rawurlencode($type),
		]);
		return (string)$this->api($url, 'GET', '', ['Accept: */*']);
	}

	/**
	 * Blob upload (RFC 8620 §6.3) - POST raw bytes, get back a blobId
	 *
	 * @param string $raw
	 * @param string $type mime-type, e.g. "message/rfc822"
	 * @return string new blobId
	 * @throws Exception|Exception\Http
	 */
	public function uploadBlob(string $raw, string $type='message/rfc822') : string
	{
		$url = strtr($this->uploadUrl, ['{accountId}' => rawurlencode($this->accountId)]);
		$response = $this->api($url, 'POST', $raw, ['Content-Type: '.$type]);
		return (is_array($response) ? $response['blobId'] ?? null : null) ??
			throw new Exception('Blob upload failed: '.json_encode($response));
	}

	/**
	 * Get PushSubscriptions
	 * @link https://github.com/jmapio/jmap/blob/master/spec/jmap/push.mdown
	 *
	 * @param string|null &$sessionState
	 * @return array {"list": [{"id": ..., "deviceClientId": ..., "verificationCode": ..., "expires": ..., "types": [...]}, ...], "notFound": []}
	 * @throws Exception on error
	 */
	public function getPushSubscriptions(?string &$sessionState=null)
	{
		$response = $this->jmapCall([[ "PushSubscription/get", [
            "ids" => null,
		], "0" ]]);
		$sessionState = $response['sessionState'] ?? null;
		return $response['methodResponses'][0][1] ?? throw new Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	const DATETIME_UTC_FORMAT = 'Y-m-d\\TH:i:s\\Z';

	/**
	 * Create a PushSubscription
	 * @link https://github.com/jmapio/jmap/blob/master/spec/jmap/push.mdown
	 *
	 * @param string $deviceClientId
	 * @param string $url
	 * @param array|null $types
	 * @param \DateTime|null $expires
	 * @param string|null &$sessionState
	 * @return array with values for keys "id", "keys", "expires"
	 * @throws Exception
	 */
	public function createPushSubscription(string $deviceClientId, string $url, ?array $types=null, ?\DateTime $expires=null, ?string &$sessionState=null)
	{
		$id = md5($deviceClientId.$url);
		$response = $this->jmapCall([[ "PushSubscription/set", [
			"create" => [
				$id => [
					'deviceClientId' => $deviceClientId,
					'url' => $url,
					'types' => $types,
					'expires' => $expires ? $expires->format(self::DATETIME_UTC_FORMAT) : null,
				],
			]
		], "0"]]);
		$sessionState = $response['sessionState'] ?? null;
		return $response['methodResponses'][0][1]['created'][$id] ?? throw new Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	/**
	 * Update push subscription
	 *
	 * @param string $pushSubscriptionId
	 * @param array $values
	 * @param string|null &$sessionState
	 * @return mixed
	 * @throws Exception
	 */
	public function updatePushSubscription(string $pushSubscriptionId, array $values, ?string &$sessionState=null)
	{
		$response = $this->jmapCall([[ "PushSubscription/set", [
			"update" => [
				$pushSubscriptionId => $values,
			]
		], "0"]]);
		$sessionState = $response['sessionState'] ?? null;
		return $response['methodResponses'][0][1] ?? throw new Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	/**
	 * Destroy a push subscription, so the JMAP server stops calling us
	 *
	 * @param string $pushSubscriptionId
	 * @param string|null &$sessionState
	 * @return array with values for keys "destroyed" and "notDestroyed"
	 * @throws Exception
	 */
	public function destroyPushSubscription(string $pushSubscriptionId, ?string &$sessionState=null)
	{
		$response = $this->jmapCall([[ "PushSubscription/set", [
			"destroy" => [$pushSubscriptionId],
		], "0"]]);
		$sessionState = $response['sessionState'] ?? null;
		return $response['methodResponses'][0][1] ?? throw new Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	/**
	 * Make some protected variable available readonly
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get(string $name)
	{
		switch ($name)
		{
			case 'accountId':
				return $this->accountId;
			case 'accountCapabilities':
				return $this->accountCapabilities;
			case 'capabilities':
				return $this->capabilities;
			case 'downloadUrl':
				return $this->downloadUrl;
			case 'uploadUrl':
				return $this->uploadUrl;
			default:
				return parent::__get($name);
		}
	}
}
