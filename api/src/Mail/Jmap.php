<?php
/**
 * EGroupware Api: Mail JMAP protocol
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
use EGroupware\Api\Cache;
use Jumbojett\OpenIDConnectClientException;

// ToDo: make this an auto-loadable class or trait
require_once __DIR__ . '/../../../doc/REST-CalDAV-CardDAV/api-client.php';

/**
 * JMAP client library
 * - just a start to bootstrap JMAP
 * - subscribe for PushSubscription
 *
 * @link https://datatracker.ietf.org/doc/html/rfc8620 The JSON Meta Application Protocol (JMAP)
 */
class Jmap
{
	protected string $url;
	protected string $user;
	protected string $secret;
	protected array $well_known;

	protected string $accountId;

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

		// EGroupware Mail "mail" service
		if ($this->url === 'mail')
		{
			$this->url = Api\Framework::getUrl('/jmap/');
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
			if (empty($accountId))
			{
				$this->bootstrap(false, $accountId);
			}
		}
		$this->accountId = $accountId;
	}

	/**
	 * Get JMAP apiUrl from /.well-known/jmap and fill $this->well_known for later reference
	 *
	 * @param bool $use_well_known true: request https://$host/.well-known/jmap, false: $url/
	 * @param string|null $accountId
	 * @param array|null $capabilities
	 * @return string
	 * @throws Api\Exception
	 * @throws \JsonException
	 */
	public function bootstrap(bool $use_well_known=true, ?string &$accountId=null, ?array &$capabilities=null) : string
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
			$response = api($url.'/.well-known/jmap');
		}
		// as I can't figure out what the Stalwart URL for the session object is, I use .well-know/jmap for now
		elseif (empty($accountId))
		{
			$response = api($url.'/.well-known/jmap');
		}
		foreach($response['accounts'] ?? [] as $id => $account)
		{
			if ($account['isPersonal'])
			{
				$accountId = $id;
				break;
			}
		}
		$capabilities = $response['capabilities'] ?? null;

		return !$use_well_known ? $this->url : $this->well_known['apiUrl'] ?? throw new Api\Exception("$this->url is NOT a JMAP server!");
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
	 * Make a JMAP call - emulating multiple methodCalls with single calls and resolving references
	 *
	 * This fixes what seems to be a bug in Stalwart 0.11.x reference implementation
	 *
	 * @link https://github.com/stalwartlabs/mail-server/discussions/1508
	 * @ToDo throw exceptions on JMAP errors
	 * @param array $methodCalls [string $method, array $args][]
	 * @param string|array $using ='urn:ietf:params:jmap:mail'
	 * @param bool $emulate true: emulate multiple methodCalls, false: send them in one call to the server
	 * @return array response
	 */
	public function jmapCall(array $methodCalls, $using='urn:ietf:params:jmap:mail', bool $emulate=true)
	{
		if (!$emulate)
		{
			return api($this->url, 'POST', [
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
	 * Get PushSubscriptions
	 * -see https://github.com/jmapio/jmap/blob/master/spec/jmap/push.mdown
	 *
	 * @param string|null &$sessionState
	 * @return array ["PushSubscription/get", {"list": [{"id": ..., "deviceClientId": ..., "verificationCode": ..., "expires": ..., "types": [...]}, ...], "notFound": []}, "0"]
	 */
	public function getPushSubscriptions(?string &$sessionState=null)
	{
		$response = $this->jmapCall([[ "PushSubscription/get", [
            "ids" => null,
		], "0" ]]);
		$sessionState = $response['sessionState'] ?? null;
		return $response['methodResponses'][0][1] ?? throw new Api\Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

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
	 * @param DateTime|null &$expires
	 * @param string|null &$sessionState
	 * @return array with values for keys "id", "keys", "expires"
	 * @throws Api\Exception
	 */
	public function createPushSubscription(string $deviceClientId, string $url, ?array $types=null, ?DateTime $expires=null, ?string &$sessionState=null)
	{
		$id = md5($deviceClientId.$url);
		$response = $this->jmapCall([[ "PushSubscription/set", [
			"create" => [
				$id => [
					'deviceClientId' => $deviceClientId,
					'url' => $url,
					'types' => $types,
					'expires' => $expires ? $expires->format('YYYY-mm-ddThh:MM:ssZ') : null,
				],
			]
		], "0"]]);
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
		], "0"]]);
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
	public function getStates(string $folder='INBOX', string $accountId=null, ?string &$sessionState=null) : array
	{
		$response = $this->jmapCall([
			['Mailbox/query', ['accountId' => $accountId ?: $this->accountId, 'filter' => ['name' => $folder]], 't0'],
			['Email/get', ['accountId' => $accountId ?: $this->accountId, '#inMailbox' => ['name' => 'Mailbox/query', 'path' => '/ids', 'resultOf' => 't0'], 'ids' => []], 't1'],
		]);
		$sessionState = $response['sessionState'] ?? null;
		return [
			'Mailbox' => $response['methodResponses'][0][1]['queryState'] ?? throw new Api\Exception("Could not query Mailbox state using folder '$folder'!"),
			'Email' => $response['methodResponses'][1][1]['state'] ?? throw new Api\Exception("Could not query Email state of folder '$folder'!"),
		];
	}

	/**
	 * Get id of a folder-path e.g. INBOX/folder/subfolder (id corresponds to subfolder in INBOX/folder!)
	 *
	 * @param string $folder folder-path
	 * @param string|null $accountId
	 * @return string|null null = not found
	 */
	protected function getMailboxId(string $folder, ?string $accountId=null) : ?string
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
		$response = $this->jmapCall($methodCalls);
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
				]);
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
	 * @return array[] with responses for keys "(email|mailbox=-(changes|created|updated|destroyed)"
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
			// Fetch any mailboxes that have been deleted
			["Mailbox/get", [
				"accountId" => $accountId ?: $this->accountId,
				"#ids" => [
					"name" => "Mailbox/changes",
					"path" => "/destroyed",
					"resultOf" => "mailbox-changes"
				]
			], "mailbox-destroyed"],
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
				["Email/get", [
					"accountId" => $accountId ?: $this->accountId,
					"#ids" => [
						"name" => "Email/changes",
						"path" => "/destroyed",
						"resultOf" => "email-changes"
					],
					"properties" => ["id", "mailboxIds", "messageId"],
				], "email-destroyed"],
			]);
		}
		$response = $this->jmapCall($methodCalls);
		$sessionState = $response['sessionState'] ?? null;
		$ret = [];
		foreach($response['methodResponses'] as $methodResponse)
		{
			$ret[$methodResponse[2]] = $methodResponse[1];
		}
		return $ret;
	}
}