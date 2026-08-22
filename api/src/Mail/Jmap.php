<?php
/**
 * EGroupware Api: JMAP client
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2025 by Ralf Becker <rb-AT-egroupware.org>
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;

// ToDo: make this an auto-loadable class or trait
require_once __DIR__ . '/../../../doc/REST-CalDAV-CardDAV/api-client.php';

/**
 * JMAP client library
 * - just a start to bootstrap JMAP
 * - subscribe for PushSubscription
 *
 * @link https://datatracker.ietf.org/doc/html/rfc8620 The JSON Meta Application Protocol (JMAP)
 *
 * @property-read string $accountId JMAP accountId set during bootstrap / constructor
 * @property-read array $capabilities JMAP capabilities
 * @property-read array $accountCapabilities JMAP accountCapabilities of $this->accountId
 * @property-read string $apiUrl e.g. "https://example.org:443/jmap/"
 * @property-read string $downloadUrl e.g. "https://example.org:443/jmap/download/{accountId}/{blobId}/{name}?accept={type}"
 * @property-read string $uploadUrl e.g. "https://example.org:443/jmap/upload/{accountId}/"
 * @property-read string $eventSourceUrl e.g. "https://example.org:443/jmap/eventsource/?types={types}&closeafter={closeafter}&ping={ping}"
 */
class Jmap
{
	protected string $url;
	protected string $user;
	protected string $secret;
	protected array $well_known;

	protected string $accountId;
	protected array $capabilities;
	protected array $accountCapabilities;

	/**
	 * @var string e.g. "https://example.org:443/jmap/",
	 */
	protected string $apiUrl;
	/**
	 * @var string e.g. "https://example.org:443/jmap/download/{accountId}/{blobId}/{name}?accept={type}"
	 */
	protected string $downloadUrl;
	/**
	 * @var string e.g. "https://example.org:443/jmap/upload/{accountId}/"
	 */
	protected string $uploadUrl;
	/**
	 * @var string e.g. "https://example.org:443/jmap/eventsource/?types={types}&closeafter={closeafter}&ping={ping}"
	 */
	protected string $eventSourceUrl;

	/**
	 * @var string|null path to log to, or null to disable logging
	 */
	protected ?string $log=null;

	/**
	 * Constructor
	 *
	 * @param string $host_or_url JMAP url, or hostname to bootstrap via "https://$host_or_url/.well-known/jmap"
	 * @param string $user username
	 * @param string $secret password
	 * @param string|null &$accountId jmap accountId
	 */
	public function __construct(string $host_or_url, string $user, string $secret, ?string &$accountId=null)
	{
		global $authorization;

		$this->url = $host_or_url;
		$this->user = $user;
		$this->secret = $secret;

		//$this->log = '/var/lib/egroupware/'.$_SERVER['HTTP_HOST'].'/jmap.log';

		// EGroupware Mail "mail" service
		if ($this->url === 'mail')
		{
			$this->url = Api\Framework::getUrl('/jmap/');
		}
		// EGroupware Hosting
		elseif($this->url === 'stalwart' || $this->url === 'internal.k8s.farm.egroupware.org')
		{
			$this->url = 'https://stalwart.egroupware.org/jmap/';
		}

		if (!str_starts_with($this->url, 'https://'))
		{
			$authorization[$this->url] = 'Authorization: Basic '.base64_encode($user.':'.$secret);

			$this->url = $this->bootstrap(true, $accountId);
		}
		else
		{
			$authorization[parse_url($this->url, PHP_URL_HOST)] = 'Authorization: Basic '.base64_encode($user.':'.$secret);

			// need to bootstrap to get the JMAP accountId
			// we need other stuff set in bootstrap e.g. the downloadUrl //if (empty($accountId))
			{
				$this->bootstrap(false, $accountId);
			}
		}
		$this->accountId = $accountId;
	}

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
	 * @return array|string array of decoded JSON or string body
	 * @throws \JsonException for invalid JSON
	 * @throws \HttpException with code=0: opening http connection, code=HTTP status, if status is NOT 2xx
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
		if (!$this->log)
		{
			return api($url, $method, $body, $header, $response_header, $follow, only_public: false);
		}
		// logging request and response
		file_put_contents($this->log, date('Y-m-d H:i:s O')." $method $url\n".implode("\n", $header)."\n\n".$body."\n\n", FILE_APPEND);

		try {
			$response = api($url, $method, $body, $header, $response_header, $follow, only_public: false);

			file_put_contents($this->log, date('Y-m-d H:i:s O').' '.
				implode("\n", array_map(fn($value, $key) => $key === 0 ? $value : "$key: $value", array_values($response_header), array_keys($response_header)))."\n\n".
					(is_scalar($response) ? $response : json_encode($response, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT))."\n\n", FILE_APPEND);

			return $response;
		}
		catch (\Throwable $e) {
			file_put_contents($this->log, date('Y-m-d H:i:s O').' '.$e->getMessage()."\n\n".$e->getTraceAsString()."\n\n", FILE_APPEND);

			if ($e instanceof \HttpException)
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
	 * @throws Api\Exception
	 * @throws \JsonException
	 */
	public function bootstrap(bool $use_well_known=true, ?string &$accountId=null) : string
	{
		$url = $this->url;
		if (!str_starts_with($url, 'https://'))
		{
			$url = 'https://'.$url;
		}
		else
		{
			$url = preg_replace('#^(https?://[^/]+)(/.*)$#', '$1', $url);
		}
		if ($use_well_known)
		{
			$response = $this->api($url.'/.well-known/jmap');
		}
		// as I can't figure out what the Stalwart URL for the session object is, I use .well-know/jmap for now
		// it's: /jmap/session
		else//if (empty($accountId))
		{
			$response = $this->api($url.'/.well-known/jmap');
		}
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

		return !$use_well_known ? $this->url : $this->apiUrl ?? throw new Api\Exception("$this->url is NOT a JMAP server!");
	}

	/**
	 * client_id we present to Stalwart's OAuth server
	 *
	 * Stalwart accepts any client_id by default and does not require prior registration,
	 * see https://stalw.art/docs/auth/oauth/client-registration/
	 */
	const OAUTH_CLIENT_ID = 'egroupware';

	/**
	 * Never dereferenced by Stalwart, only compared between the /api/auth and /auth/token
	 * calls of a single exchange, so an arbitrary (but https) URL is fine.
	 */
	const OAUTH_REDIRECT_URI = 'https://egroupware.org/oauth/stalwart-imap';

	/**
	 * Switch authentication from Basic username/password to a Bearer access-token
	 *
	 * Used by Imap\Stalwart to avoid the cost of a bcrypt password check on every request.
	 *
	 * @param string $token
	 */
	public function setBearerToken(string $token)
	{
		global $authorization;

		$authorization[parse_url($this->url, PHP_URL_HOST) ?: $this->url] = 'Authorization: Bearer '.$token;
	}

	/**
	 * Authenticate directly with $username/$password against Stalwart's own OAuth server to
	 * obtain an access- and refresh-token, without any browser or user interaction.
	 *
	 * Stalwart does not implement the OAuth2 "password" grant (RFC 6749 §4.3). We instead
	 * replicate what Stalwart's own WebUI does on login: POST credentials to /api/auth (the
	 * single, unavoidable bcrypt check) to get a one-time client-code, then exchange that
	 * code for tokens at the regular /auth/token endpoint using the "authorization_code"
	 * grant - all server-side, no redirect or user interaction required.
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
	 * @return array|null null if refresh failed (token revoked or expired), otherwise
	 *  values for keys "access_token", "refresh_token", "expires_in"
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
	 * Call Stalwart's /auth/token endpoint
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
	 * Used by clients (e.g. the browser via jmap-jam) that need to bootstrap their own
	 * JMAP session, given only a bearer access-token, without ever seeing the password.
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
	 * JMAP mail (includes core!)
	 */
	const JMAP_MAIL = [self::JMAP_CORE, "urn:ietf:params:jmap:mail"];
	/**
	 * JMAP quota extension, see https://www.rfc-editor.org/rfc/rfc9425
	 */
	const JMAP_QUOTA = "urn:ietf:params:jmap:quota";
	/**
	 * JMAP mail sharing extension (editable Mailbox shareWith/myRights), see
	 * https://www.ietf.org/archive/id/draft-ietf-jmap-mail-sharing (base RFC 8621's myRights
	 * is read-only)
	 */
	const JMAP_MAIL_SHARE = "urn:ietf:params:jmap:mail:share";
	/**
	 * JMAP principals extension, needed to resolve shareWith's identifiers (Principal ids, NOT
	 * IMAP usernames/emails), see https://www.rfc-editor.org/rfc/rfc9670
	 */
	const JMAP_PRINCIPALS = "urn:ietf:params:jmap:principals";

	/**
	 * Make a JMAP call - emulating multiple methodCalls with single calls and resolving references
	 *
	 * This fixes what seems to be a bug in Stalwart 0.11.x reference implementation
	 *
	 * @link https://github.com/stalwartlabs/mail-server/discussions/1508
	 * @ToDo throw exceptions on JMAP errors
	 * @param array $methodCalls [string $method, array $args][]
	 * @param string|array $using
	 * @param bool $emulate true: emulate multiple methodCalls, false: send them in one call to the server
	 * @return array response
	 */
	public function jmapCall(array $methodCalls, string|array $using, bool $emulate=false)
	{
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
	 * Get quota via the JMAP Quota extension (RFC 9425), if the server advertises it
	 *
	 * @param string|null $accountId
	 * @return array[]|null list of Quota objects (keys "id", "resourceType", "used", "hardLimit",
	 *  "scope", ...), or null if the server does NOT advertise the urn:ietf:params:jmap:quota
	 *  capability - callers should fall back to a non-JMAP way of getting the quota in that case
	 * @throws Api\Exception on error
	 */
	public function getQuota(?string $accountId=null) : ?array
	{
		if (!in_array(self::JMAP_QUOTA, $this->capabilities))
		{
			return null;
		}
		$response = $this->jmapCall([[ "Quota/get", [
			"accountId" => $accountId ?: $this->accountId,
			"ids" => null,
		], "0" ]], [self::JMAP_CORE, self::JMAP_QUOTA]);
		return $response['methodResponses'][0][1]['list'] ?? throw new Api\Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	/**
	 * Get PushSubscriptions
	 * -see https://github.com/jmapio/jmap/blob/master/spec/jmap/push.mdown
	 *
	 * @param string|null &$sessionState
	 * @return array {"list": [{"id": ..., "deviceClientId": ..., "verificationCode": ..., "expires": ..., "types": [...]}, ...], "notFound": []}
	 * @throws Api\Exception on error
	 */
	public function getPushSubscriptions(?string &$sessionState=null)
	{
		$response = $this->jmapCall([[ "PushSubscription/get", [
            "ids" => null,
		], "0" ]], self::JMAP_MAIL);
		$sessionState = $response['sessionState'] ?? null;
		return $response['methodResponses'][0][1] ?? throw new Api\Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	const DATETIME_UTC_FORMAT = 'Y-m-d\\TH:i:s\\Z';

	/**
	 * Create a PushSubscription
	 * -see https://github.com/jmapio/jmap/blob/master/spec/jmap/push.mdown
	 *
	 * JMAP server will immediately call $url with a POST request with the following body:
	 * {
	 *   "@type": "PushVerification",
	 *   "pushSubscriptionId": string,
	 *   "verificationCode": string
	 * }
	 * To which one need to respond with a 200 OK and a JSON body containing the following:
	 * [[ "PushSubscription/set", {
	 *  "update": {
	 *      "P43dcfa4-1dd4-41ef-9156-2c89b3b19c60": {
	 *          "verificationCode": "da1f097b11ca17f06424e30bf02bfa67"
	 *      }
	 *  }
	 * }, "0" ]]
	 *
	 * @param string $deviceClientId
	 * @param string $url
	 * @param array|null $types
	 * @param \DateTime|null &$expires
	 * @param string|null &$sessionState
	 * @return array with values for keys "id", "keys", "expires"
	 * @throws Api\Exception
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
		], "0"]], self::JMAP_MAIL);
		$sessionState = $response['sessionState'] ?? null;
		return $response['methodResponses'][0][1]['created'][$id] ?? throw new Api\Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	/**
	 * Update push subscription
	 *
	 * @param string $pushSubscriptionId
	 * @param array $values
	 * @param string|null &$sessionState
	 * @return mixed
	 * @throws Api\Exception
	 */
	public function updatePushSubscription(string $pushSubscriptionId, array $values, ?string &$sessionState=null)
	{
		$response = $this->jmapCall([[ "PushSubscription/set", [
			"update" => [
				$pushSubscriptionId => $values,
			]
		], "0"]], self::JMAP_MAIL);
		$sessionState = $response['sessionState'] ?? null;
		return $response['methodResponses'][0][1] ?? throw new Api\Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	/**
	 * Destroy a push subscription, so the JMAP server stops calling us
	 *
	 * @param string $pushSubscriptionId
	 * @param string|null &$sessionState
	 * @return array with values for keys "destroyed" and "notDestroyed"
	 * @throws Api\Exception
	 */
	public function destroyPushSubscription(string $pushSubscriptionId, ?string &$sessionState=null)
	{
		$response = $this->jmapCall([[ "PushSubscription/set", [
			"destroy" => [$pushSubscriptionId],
		], "0"]], self::JMAP_MAIL);
		$sessionState = $response['sessionState'] ?? null;
		return $response['methodResponses'][0][1] ?? throw new Api\Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	/**
	 * Query Mailbox and Email state for give folder
	 *
	 * @param string $folder
	 * @param string|null $accountId
	 * @param string|null &$sessionState
	 * @return string[] states for keys "Mailbox" and "Email"
	 * @throws Api\Exception
	 */
	public function getStates(string $folder='INBOX', ?string $accountId=null, ?string &$sessionState=null) : array
	{
		$response = $this->jmapCall([
			['Mailbox/query', ['accountId' => $accountId ?: $this->accountId, 'filter' => ['name' => $folder]], 't0'],
			['Email/get', ['accountId' => $accountId ?: $this->accountId, '#inMailbox' => ['name' => 'Mailbox/query', 'path' => '/ids', 'resultOf' => 't0'], 'ids' => []], 't1'],
		], self::JMAP_MAIL);
		$sessionState = $response['sessionState'] ?? null;
		return [
			'Mailbox' => $response['methodResponses'][0][1]['queryState'] ?? throw new Api\Exception("Could not query Mailbox state using folder '$folder'!"),
			'Email' => $response['methodResponses'][1][1]['state'] ?? throw new Api\Exception("Could not query Email state of folder '$folder'!"),
		];
	}

	/**
	 * Fetch one Email via Email/get with the given properties
	 *
	 * Used by Imap\Jmap's JMAP-native S/MIME/TNEF resolvers (see api/src/Mail/Imap/Jmap.php) to
	 * fetch bodyStructure/attachments etc. server-side, instead of an IMAP FETCH - same
	 * request shape mail/js/jmap.ts's MailJmap.fetchBody() already uses client-side.
	 *
	 * @param string $id JMAP Email id
	 * @param array $properties e.g. ['bodyStructure','textBody','htmlBody','attachments','bodyValues']
	 * @param bool $fetchAllBodyValues
	 * @return array the Email object
	 * @throws Api\Exception if not found
	 */
	public function emailGet(string $id, array $properties, bool $fetchAllBodyValues=true) : array
	{
		$args = [
			'accountId' => $this->accountId,
			'ids' => [$id],
			'properties' => $properties,
		];
		if ($fetchAllBodyValues)
		{
			$args['fetchAllBodyValues'] = true;
		}
		$response = $this->jmapCall([['Email/get', $args, '0']], self::JMAP_MAIL);
		return $response['methodResponses'][0][1]['list'][0] ??
			throw new Api\Exception("Email '$id' not found via Email/get");
	}

	/**
	 * Query Email ids in a folder matching simple keyword/text conditions, sorted.
	 *
	 * Used by Imap\Jmap::getSortedList()'s narrow JMAP-native translation (see that method's
	 * docblock for exactly which Api\Mail::getSortedList() filter shapes this covers - NOT a
	 * general replacement for the full IMAP search-filter language).
	 *
	 * @param string $folder folder-path e.g. "INBOX" (resolved to a Mailbox id internally)
	 * @param array $conditions JMAP FilterCondition objects, ANDed together (e.g. ['notKeyword' => '$seen'])
	 * @param string $sortProperty 'receivedAt'|'sentAt'|'subject'|'from'|'to'|'size'
	 * @param bool $ascending
	 * @param int $limit
	 * @return string[] JMAP Email ids, in sort order
	 * @throws Api\Exception folder not found, or on any JMAP error
	 */
	public function emailQuery(string $folder, array $conditions, string $sortProperty='receivedAt',
		bool $ascending=false, int $limit=1000) : array
	{
		$mailboxId = $this->getMailboxId($folder);
		if ($mailboxId === null)
		{
			throw new Api\Exception("Folder '$folder' not found");
		}
		$response = $this->jmapCall([['Email/query', [
			'accountId' => $this->accountId,
			'filter' => ['operator' => 'AND', 'conditions' => array_merge([['inMailbox' => $mailboxId]], $conditions)],
			'sort' => [['property' => $sortProperty, 'isAscending' => $ascending]],
			'limit' => $limit,
		], '0']], self::JMAP_MAIL);
		return $response['methodResponses'][0][1]['ids'] ??
			throw new Api\Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	/**
	 * Download a Blob (RFC 8620 §6.2) - the raw bytes of one Email body-part, or (whole-message
	 * blobId) the raw RFC822 message. Unlike an IMAP FETCH body-part, a JMAP blob is already the
	 * final decoded representation - no Content-Transfer-Encoding reversal needed here.
	 *
	 * @param string $blobId
	 * @param string $name suggested filename, substituted into the download URL only
	 * @param string $type expected mime-type, substituted into the download URL only
	 * @return string raw bytes
	 * @throws Api\Exception|\HttpException
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
	 * Blob upload (RFC 8620 §6.3) - POST raw bytes, get back a blobId usable by emailImport().
	 *
	 * @param string $raw
	 * @param string $type mime-type, e.g. "message/rfc822"
	 * @return string new blobId
	 * @throws Api\Exception|\HttpException
	 */
	public function uploadBlob(string $raw, string $type='message/rfc822') : string
	{
		$url = strtr($this->uploadUrl, ['{accountId}' => rawurlencode($this->accountId)]);
		$response = $this->api($url, 'POST', $raw, ['Content-Type: '.$type]);
		return (is_array($response) ? $response['blobId'] ?? null : null) ??
			throw new Api\Exception('Blob upload failed: '.json_encode($response));
	}

	/**
	 * RFC 8621 §4.8 Email/import - create a new message from an uploaded blob (see uploadBlob())
	 * into a mailbox.
	 *
	 * Used by mail_ui::ajax_saveModifiedMessageSubject()'s Stalwart transport (real Email/import +
	 * emailDestroy(), replacing the classic IMAP APPEND+STORE+EXPUNGE round trip that method still
	 * uses for the local IMAP-shim case, which has no such protocol-level win available).
	 *
	 * @param string $blobId from uploadBlob()
	 * @param string $folder folder-path e.g. "INBOX/Drafts" (resolved to a Mailbox id internally)
	 * @param array $keywords JMAP keyword => true, e.g. ['$seen' => true]
	 * @return string new Email.id
	 * @throws Api\Exception
	 */
	public function emailImport(string $blobId, string $folder, array $keywords=[]) : string
	{
		$mailboxId = $this->getMailboxId($folder);
		if (!$mailboxId)
		{
			throw new Api\Exception("Mailbox '$folder' not found");
		}
		$args = [
			'accountId' => $this->accountId,
			'emails' => [
				'x' => [
					'blobId' => $blobId,
					'mailboxIds' => [$mailboxId => true],
					'keywords' => $keywords ?: new \stdClass(),
				],
			],
		];
		$response = $this->jmapCall([['Email/import', $args, '0']], self::JMAP_MAIL);
		return $response['methodResponses'][0][1]['created']['x']['id'] ??
			throw new Api\Exception('Email/import failed: '.json_encode($response['methodResponses'][0][1]['notCreated'] ?? []));
	}

	/**
	 * RFC 8620/8621 Email/set{destroy} - permanently delete messages by id.
	 *
	 * @param string[] $ids
	 * @throws Api\Exception
	 */
	public function emailDestroy(array $ids) : void
	{
		$response = $this->jmapCall([['Email/set', ['accountId' => $this->accountId, 'destroy' => $ids], '0']], self::JMAP_MAIL);
		$notDestroyed = $response['methodResponses'][0][1]['notDestroyed'] ?? [];
		if ($notDestroyed)
		{
			throw new Api\Exception('Email/set destroy failed: '.json_encode($notDestroyed));
		}
	}

	/**
	 * Patch keywords on one or more Emails via Email/set (RFC 8621 - a "keywords/$x": true|null
	 * PatchObject leaves every other keyword untouched, unlike a full keywords replace).
	 *
	 * Used by Imap\Jmap::flagMessages()'s JMAP-native path.
	 *
	 * @param string[] $ids JMAP Email ids
	 * @param array<string,bool|null> $patch e.g. ['keywords/$seen' => true, 'keywords/$flagged' => null]
	 * @throws Api\Exception on any failure
	 */
	public function emailSetKeywords(array $ids, array $patch) : void
	{
		if (!$ids)
		{
			return;
		}
		$response = $this->jmapCall([['Email/set', [
			'accountId' => $this->accountId,
			'update' => array_fill_keys($ids, $patch),
		], '0']], self::JMAP_MAIL);
		$notUpdated = $response['methodResponses'][0][1]['notUpdated'] ?? [];
		if ($notUpdated)
		{
			throw new Api\Exception('Email/set update failed: '.json_encode($notUpdated));
		}
	}

	/**
	 * Move one or more Emails to a different mailbox - a full mailboxIds replace (RFC 8621:
	 * moving is "set mailboxIds to just the target", unlike a mailboxIds/<id> patch which adds
	 * without removing, see emailImport()'s sibling use of the patch form elsewhere).
	 *
	 * Used by Imap\Jmap::deleteMessages()'s JMAP-native "move to trash" path.
	 *
	 * @param string[] $ids JMAP Email ids
	 * @param string $targetFolder folder-path e.g. "INBOX/Trash" (resolved to a Mailbox id internally)
	 * @throws Api\Exception folder not found, or on any failure
	 */
	public function emailMove(array $ids, string $targetFolder) : void
	{
		if (!$ids)
		{
			return;
		}
		$targetMailboxId = $this->getMailboxId($targetFolder);
		if ($targetMailboxId === null)
		{
			throw new Api\Exception("Folder '$targetFolder' not found");
		}
		$response = $this->jmapCall([['Email/set', [
			'accountId' => $this->accountId,
			'update' => array_fill_keys($ids, ['mailboxIds' => [$targetMailboxId => true]]),
		], '0']], self::JMAP_MAIL);
		$notUpdated = $response['methodResponses'][0][1]['notUpdated'] ?? [];
		if ($notUpdated)
		{
			throw new Api\Exception('Email/set move failed: '.json_encode($notUpdated));
		}
	}

	/**
	 * Get id of a folder-path e.g. INBOX/folder/subfolder (id corresponds to subfolder in INBOX/folder!)
	 *
	 * @param string $folder folder-path
	 * @param string|null $accountId
	 * @return string|null null = not found
	 */
	public function getMailboxId(string $folder, ?string $accountId=null) : ?string
	{
		$methodCalls = [];
		$key = 0;
		foreach(explode('/', $folder) as $part)
		{
			$query = [
				'accountId' => $accountId ?: $this->accountId,
				'filter' => ['name' => $part],
			];
			if ($key)
			{
				$query['#parentId'] = [
					'name' => 'Mailbox/query',
					'path' => '/ids',
					'resultOf' => (string)$key,
				];
			}
			$methodCalls[] = ['Mailbox/query', $query, (string)$key++];
		}
		$response = $this->jmapCall($methodCalls, self::JMAP_MAIL);
		$lastMethodResponse = array_pop($response['methodResponses']);
		return $lastMethodResponse[1]['ids'][0] ?? null;
	}

	/**
	 * Convert a folderId to the full path e.g. INBOX/folder/subfolder
	 *
	 * @param string $folderId
	 * @return string
	 */
	function folderId2path(string $folderId)
	{
		static $folderPaths = [];

		if (!isset($folderPaths[$folderId]))
		{
			$id = $folderId;
			$parts = [];
			while ($id)
			{
				$response = $this->jmapCall([
					['Mailbox/get', [
						'accountId' => $this->accountId,
						'ids' => [$folderId],
						'properties' => ['parentId', 'name'],
					], 'f0'],
					['Mailbox/get', [
						'accountId' => $this->accountId,
						'#ids' => [
							"name" => "Mailbox/get",
							"path" => "/parentId",
							"resultOf" => "f0"
						],
						'properties' => ['parentId', 'name'],
					], 'f1'],
					['Mailbox/get', [
						'accountId' => $this->accountId,
						'#ids' => [
							"name" => "Mailbox/get",
							"path" => "/parentId",
							"resultOf" => "f1"
						],
						'properties' => ['parentId', 'name'],
					], 'f2'],
					['Mailbox/get', [
						'accountId' => $this->accountId,
						'#ids' => [
							"name" => "Mailbox/get",
							"path" => "/parentId",
							"resultOf" => "f2"
						],
						'properties' => ['parentId', 'name'],
					], 'f3'],
				], self::JMAP_MAIL);
				foreach ($response['methodResponses'] as $methodResponse)
				{
					if ($methodResponse[1]['list'])
					{
						if (!$parts && strtolower($methodResponse[1]['list'][0]['name']) === 'inbox')
						{
							$parts[] = 'INBOX';
						}
						else
						{
							$parts[] = $methodResponse[1]['list'][0]['name'];
						}
						if (empty($methodResponse[1]['list'][0]['parentId']))
						{
							break;
						}
					}
				}
				$id = $methodResponse[1]['list'][0]['parentId'] ?? null;
			}
			$folderPaths[$folderId] = implode('/', array_reverse($parts));
		}
		return $folderPaths[$folderId] ?? null;
	}

	/**
	 * Query changes from a subscription push
	 *
	 * @link https://jmap.io/client.html#staying-in-sync
	 * @param ?string $accountId defaults to $this->accountId
	 * @param array $states state-object (e.g. "Email" or "Mailbox") => sinceState pairs
	 * @param string|null $sessionState
	 * @return array[] with responses for keys "(email|mailbox)-(changes|created|updated)" - no
	 *  "-destroyed" key (see the "destroyed" comments in this method's body for why); the plain
	 *  destroyed-id list is in "(email|mailbox)-changes"' own "destroyed" property instead
	 */
	public function getChanges(?string $accountId, array $states, string $mailbox='INBOX', ?string &$sessionState=null)
	{
		static $mailboxIds = ['inbox' => 'a'];
		if (strtolower($mailbox) === 'inbox')
		{
			$mailbox = 'inbox';
		}
		elseif (!isset($mailboxIds[$mailbox]))
		{
			$mailboxIds[$mailbox] = $this->getMailboxId($mailbox, $accountId);
		}
		$mailboxId = $mailboxIds[$mailbox];

		$methodCalls = !isset($states['Mailbox']) ? [] : [
			// Fetch a list of mailbox ids that have changed
			["Mailbox/changes", [
				"accountId" => $accountId ?: $this->accountId,
				"sinceState" => $states['Mailbox'],
			], "mailbox-changes"],
			// Fetch any mailboxes that have been created
			["Mailbox/get", [
				"accountId" => $accountId ?: $this->accountId,
				"#ids" => [
					"name" => "Mailbox/changes",
					"path" => "/created",
					"resultOf" => "mailbox-changes"
				]
			], "mailbox-created"],
			// Fetch any mailboxes that have been updated
			["Mailbox/get", [
				"accountId" => $accountId ?: $this->accountId,
				"#ids" => [
					"name" => "Mailbox/changes",
					"path" => "/updated",
					"resultOf" => "mailbox-changes"
				],
				"#properties" => [
					"name" => "Mailbox/changes",
					"path" => "/updatedProperties",
					"resultOf" => "mailbox-changes"
				]
			], "mailbox-updated"],
			// Deliberately no "mailbox-destroyed" Mailbox/get call: a destroyed mailbox can never
			// be fetched (always resolves to notFound, never list - JMAP semantics), so it could
			// only ever return an empty list even when it works. The plain destroyed-id list is
			// already in "mailbox-changes" itself (its own "destroyed" property) - pushCallback()
			// reads that directly instead.
		];
		if (isset($states['Email']))
		{
			$methodCalls = array_merge($methodCalls, [
				// Fetch a list of created/updated/deleted Emails
				["Email/changes", [
					"accountId" => $accountId ?: $this->accountId,
					"sinceState" => $states['Email'],
					"maxChanges" => 30
				], "email-changes"],
				["Email/get", [
					"accountId" => $accountId ?: $this->accountId,
					"#ids" => [
						"name" => "Email/changes",
						"path" => "/created",
						"resultOf" => "email-changes"
					],
					"properties" => ["id", "mailboxIds", "from", "subject", "preview", "messageId"],
				], "email-created"],
				["Email/get", [
					"accountId" => $accountId ?: $this->accountId,
					"#ids" => [
						"name" => "Email/changes",
						"path" => "/updated",
						"resultOf" => "email-changes"
					],
					"properties" => ["id", "mailboxIds", "messageId", "keywords"],
				], "email-updated"],
				// Deliberately no "email-destroyed" Email/get call - same reasoning as
				// "mailbox-destroyed" above; pushCallback() reads "email-changes"' own "destroyed"
				// property directly instead.
			]);
		}
		$response = $this->jmapCall($methodCalls, self::JMAP_MAIL);
		$sessionState = $response['sessionState'] ?? null;
		$ret = [];
		foreach($response['methodResponses'] as $methodResponse)
		{
			$ret[$methodResponse[2]] = $methodResponse[1];
		}
		return $ret;
	}

	/**
	 * Boolean filter conditions
	 *
	 * @param string $operator "AND", "OR" or "NOT"
	 * @param array $filters e.g. ["name" => ["nameA", "nameB"]]
	 * @return array
	 */
	public static function filterConditions(string $operator, array $filters)
	{
		if (!in_array($operator, ['AND', 'OR', 'NOT'])) throw new \InvalidArgumentException("Invalid operator '$operator'!");

		$conditions = [];
		foreach($filters as $name => $values)
		{
			if (is_int($name))
			{
				$conditions[] = $values;
				continue;
			}
			foreach ((array)$values as $value)
			{
				$conditions[] = [$name => $value];
			}
		}
		return [
			'operator' => $operator,
			'conditions' => $conditions,
		];
	}

	/**
	 * Generate a JMAP patch from current IDs and optional old IDs with values true for added and null for removed
	 *
	 * @param array $new new ids
	 * @param array|null $old old ids
	 * @return object with id => true or null pairs
	 */
	public static function boolPatch(array $new, array $old=null) : object
	{
		$patch = [];
		if (($added = $old ? array_diff_key($new, $old) : $new))
		{
			$patch = array_combine($added, array_fill(0, count($added), true));
		}
		if ($old && (($removed = array_diff_key($old, $new))))
		{
			$patch += array_combine($removed, array_fill(0, count($removed), null));
		}
		return (object)$patch;
	}

	/**
	 * Make some protected variable available readonly
	 *
	 * @param string $name
	 * @return string|null
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
				return null;
		}
	}
}