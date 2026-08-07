<?php
/**
 * EGroupware Mail: local JMAP server for plain IMAP accounts
 *
 * mail/js/jmap.ts (MailJmap) already speaks JMAP client-side for Stalwart-backed
 * accounts, talking directly to Stalwart's real JMAP server. Plain IMAP accounts
 * (Dovecot, Cyrus, ...) have no JMAP server at all, so this class acts as one -
 * but only implements the handful of methods MailJmap actually sends
 * (Mailbox/query, Email/query, Email/get, Email/set, plus RFC 8620 §3.7 result-reference
 * resolution for its batched request), backed directly by
 * Api\Mail\Account::read()->imapServer() (a Horde_Imap_Client_Socket) via plain
 * IMAP search()/fetch()/store() calls. It deliberately does NOT go through
 * mail_ui or Api\Mail (mail_bo).
 *
 * Row-id compatibility: emailID here is a plain IMAP UID and folderID is
 * base64(folder path), same as mail_ui::generateRowID()'s classic scheme - so
 * rows fetched via this class are indistinguishable from mail_ui::get_rows()'s
 * own output to legacy action handlers. Stalwart-sourced rows use opaque JMAP
 * ids, resolved by the Imap\Jmap row-id implementation when a legacy handler
 * still needs an IMAP UID.
 *
 * accountId "0" is never a real account - it's served from an in-class fixture,
 * to give the client-side code a stable target for testing without a real
 * mailbox, e.g.: app.mail.jmap.getRows({selectedFolder: '0::INBOX', ...})
 *
 * Auth is the ordinary EGroupware session cookie (this is a same-origin fetch
 * from the browser) - see mail_ui::ajax_jmapBootstrap()'s local-shim branch,
 * which hands the client a fixed dummy bearer-token string, NEVER the real
 * session id, purely to satisfy jmap-jam's required config field.
 *
 * The actual HTTP entrypoint is mail/jmap.php, a thin front-controller that
 * just boots EGroupware and forwards to session()/dispatch() below.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2026 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api;
use EGroupware\Api\Mail\Account;

class JmapShim
{
	/**
	 * JMAP session-discovery object, fetched once by jmap-jam's JamClient on construction
	 *
	 * Only 'apiUrl' is actually used by jmap-jam (verified against its bundled source) -
	 * the rest is filled in for a plausible-looking, spec-shaped response.
	 *
	 * @return array
	 */
	public static function session() : array
	{
		$url = Api\Framework::getUrl(Api\Framework::link('/mail/jmap.php'));

		return [
			'capabilities' => [
				Api\Mail\Jmap::JMAP_CORE => new \stdClass(),
				'urn:ietf:params:jmap:mail' => new \stdClass(),
			],
			'accounts' => new \stdClass(),
			'primaryAccounts' => new \stdClass(),
			'username' => (string)($GLOBALS['egw_info']['user']['account_lid'] ?? ''),
			'apiUrl' => $url,
			// jmap-jam's downloadBlob() substitutes these 4 placeholders verbatim (no URL-encoding
			// of the substituted values - see urlsafeB64Encode()'s docblock) and does a plain GET,
			// handled by mail/jmap.php's "download" branch -> JmapShim::download()
			'downloadUrl' => $url.'?download=1&accountId={accountId}&blobId={blobId}&type={type}&name={name}',
			'uploadUrl' => $url,
			'eventSourceUrl' => $url,
			'state' => '0',
		];
	}

	/**
	 * Run a batch of JMAP method calls, resolving RFC 8620 §3.7 result-references between them
	 *
	 * @param array $methodCalls [string $method, array $args, string $callId][]
	 * @return array [string $method, array $result, string $callId][] (or "error" method on failure)
	 */
	public static function dispatch(array $methodCalls) : array
	{
		$responses = [];
		$context = [];	// request-scoped state, e.g. remembering which mailbox an Email/query's ids came from

		foreach ($methodCalls as $call)
		{
			[$method, $args, $callId] = ((array)$call) + [null, [], null];
			try
			{
				$args = self::resolveRefs((array)$args, $responses);
				$accountId = (string)($args['accountId'] ?? '0');

				switch ($method)
				{
					case 'Mailbox/query':
						$result = self::mailboxQuery($args);
						break;
					case 'Email/query':
						$result = self::emailQuery($accountId, $args, $context);
						break;
					case 'Email/get':
						$result = self::emailGet($accountId, $args, $context);
						break;
					case 'Email/set':
						$result = self::emailSet($accountId, $args);
						break;
					default:
						throw new \Exception("Unsupported method '$method'");
				}
				$responses[] = [$method, $result, $callId];
			}
			catch (\Throwable $e)
			{
				$responses[] = ['error', ['type' => 'serverFail', 'description' => $e->getMessage()], $callId];
			}
		}
		return $responses;
	}

	/**
	 * Resolve "#"-prefixed result-reference args against already-collected responses (RFC 8620 §3.7)
	 *
	 * @param array $args
	 * @param array $responses methodResponses collected so far in this request
	 * @return array $args with every "#name" key resolved into a plain "name" key
	 */
	public static function resolveRefs(array $args, array $responses) : array
	{
		foreach ($args as $key => $ref)
		{
			if ($key === '' || $key[0] !== '#')
			{
				continue;
			}
			$name = substr($key, 1);
			unset($args[$key]);

			foreach ($responses as $response)
			{
				if ($response[2] === $ref['resultOf'] && $response[0] === $ref['name'])
				{
					$args[$name] = self::jsonPath($response[1], $ref['path']);
					continue 2;
				}
			}
			throw new \Exception("Failed to resolve result reference for '$name'");
		}
		return $args;
	}

	/**
	 * Minimal JSON-pointer-ish path lookup, e.g. "/ids" -> $value['ids']
	 *
	 * @param array $value
	 * @param string $path
	 * @return mixed|null
	 */
	public static function jsonPath(array $value, string $path)
	{
		foreach (explode('/', ltrim($path, '/')) as $part)
		{
			if (!isset($value[$part]))
			{
				return null;
			}
			$value = $value[$part];
		}
		return $value;
	}

	/**
	 * Mailbox/query: resolve a single path-segment (optionally under a parent) to a folder id
	 *
	 * Id is just base64(EGroupware-canonical "/"-joined folder path) - a pure encoding,
	 * not a lookup, so no IMAP round-trip is needed here. Existence is implicitly
	 * verified later, when Email/query actually searches that mailbox.
	 *
	 * @param array $args {filter: {name: string, parentId?: string}}
	 * @return array {ids: string[]}
	 */
	public static function mailboxQuery(array $args) : array
	{
		$name = (string)($args['filter']['name'] ?? '');
		$parentPath = !empty($args['filter']['parentId']) ? self::folderPath($args['filter']['parentId']) : '';
		$path = $parentPath !== '' ? $parentPath.'/'.$name : $name;

		return ['ids' => [base64_encode($path)]];
	}

	/**
	 * @param string $folderId base64-encoded folder path
	 * @return string
	 */
	public static function folderPath(string $folderId) : string
	{
		return $folderId === '' ? '' : (string)base64_decode($folderId);
	}

	/**
	 * URL-safe base64 (RFC 4648 §5) - used for blobId (see bodyPartToJmap()), since jmap-jam's
	 * downloadBlob() substitutes it into a URL template *without* URL-encoding the value first, so
	 * plain base64's '+', '/', '=' would otherwise corrupt the value ('+' in particular is decoded
	 * as a space by PHP's own $_GET parsing).
	 */
	public static function urlsafeB64Encode(string $data) : string
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	public static function urlsafeB64Decode(string $data) : string
	{
		return (string)base64_decode(strtr($data, '-_', '+/'));
	}

	/**
	 * Get (and cache, per request) the Horde_Imap_Client for a real account, or null for the demo account
	 *
	 * @param string $accountId
	 * @return \Horde_Imap_Client_Socket|null
	 */
	public static function imapServer(string $accountId) : ?\Horde_Imap_Client_Socket
	{
		static $servers = [];
		if ($accountId === '0')
		{
			return null;
		}
		return $servers[$accountId] ??= Account::read((int)$accountId)->imapServer();
	}

	/**
	 * Translate an EGroupware-canonical "/"-joined folder path to the account's real IMAP mailbox name
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $path
	 * @return string
	 */
	public static function hordeMailbox(\Horde_Imap_Client_Socket $imap, string $path) : string
	{
		if ($path === '' || strtoupper($path) === 'INBOX')
		{
			return 'INBOX';
		}
		static $delimiters = [];
		$key = spl_object_id($imap);
		$delimiters[$key] ??= $imap->getNameSpaceArray()['personal'][0]['delimiter'] ?? '/';

		return $delimiters[$key] === '/' ? $path : str_replace('/', $delimiters[$key], $path);
	}

	/**
	 * Email/query: translate MailJmap.buildFilter()'s filter tree + buildSort()'s sort into a
	 * single Horde_Imap_Client::search() call, mirroring mail_ui::get_rows()'s pagination.
	 *
	 * @param string $accountId
	 * @param array $args {filter: array, sort?: array, position?: int, limit?: int}
	 * @param array &$context request-scoped state, used by the matching Email/get to know which
	 *  mailbox the returned ids belong to (our ids are plain per-mailbox IMAP UIDs, not the
	 *  globally-unique ids real JMAP requires - see the class docblock)
	 * @return array {accountId: string, ids: string[], total: int}
	 */
	public static function emailQuery(string $accountId, array $args, array &$context) : array
	{
		$filter = (array)($args['filter'] ?? []);
		$folder = self::folderPath((string)self::findInMailbox($filter));

		if ($accountId === '0')
		{
			return self::demoEmailQuery($folder, $args, $context);
		}

		$imap = self::imapServer($accountId);
		$mailbox = self::hordeMailbox($imap, $folder);

		$query = self::filterToQuery($filter);
		// JMAP never exposes messages with the IMAP \Deleted flag (RFC 8621 §4.1.1) - match that
		$query->flag(\Horde_Imap_Client::FLAG_DELETED, false);

		$sorted = $imap->search($mailbox, $query, [
			'sort' => self::buildSort((array)($args['sort'] ?? [])),
		]);
		$ids = array_values($sorted['match']->ids ?? []);
		$total = (int)($sorted['count'] ?? count($ids));

		$position = max(0, (int)($args['position'] ?? 0));
		$limit = (int)($args['limit'] ?? 50) ?: 50;
		$page = array_slice($ids, $position, $limit);

		// remembered for the Email/get that MailJmap always batches right after this call
		$context['mailbox'][$accountId] = $mailbox;

		return [
			'accountId' => $accountId,
			'ids' => array_map('strval', $page),
			'total' => $total,
		];
	}

	/**
	 * Find the (always exactly one) "inMailbox" condition anywhere in a filter tree
	 *
	 * @param array $filter
	 * @return string|null
	 */
	public static function findInMailbox(array $filter) : ?string
	{
		if (isset($filter['inMailbox']))
		{
			return $filter['inMailbox'];
		}
		foreach ((array)($filter['conditions'] ?? []) as $condition)
		{
			if (($id = self::findInMailbox((array)$condition)) !== null)
			{
				return $id;
			}
		}
		return null;
	}

	/**
	 * Translate a filter tree (AND/OR/NOT of conditions, exactly what MailJmap.buildFilter() sends)
	 * into a Horde_Imap_Client_Search_Query, pushing NOT down to each leaf's own $not flag via
	 * De Morgan's laws (Horde has no query-level negation, only per-condition $not).
	 *
	 * @param array $filter
	 * @param bool $negate
	 * @return \Horde_Imap_Client_Search_Query
	 */
	public static function filterToQuery(array $filter, bool $negate=false) : \Horde_Imap_Client_Search_Query
	{
		$query = new \Horde_Imap_Client_Search_Query();

		if (isset($filter['operator']))
		{
			if ($filter['operator'] === 'NOT')
			{
				return self::filterToQuery((array)$filter['conditions'][0], !$negate);
			}
			$isAnd = $filter['operator'] === 'AND';
			$effectiveAnd = $negate ? !$isAnd : $isAnd;
			foreach ((array)$filter['conditions'] as $condition)
			{
				$sub = self::filterToQuery((array)$condition, $negate);
				$effectiveAnd ? $query->andSearch($sub) : $query->orSearch($sub);
			}
			return $query;
		}

		foreach ($filter as $key => $value)
		{
			self::applyCondition($query, (string)$key, $value, $negate);
		}
		return $query;
	}

	/**
	 * Apply a single leaf filter condition (as sent by MailJmap.buildFilter()) to a search query
	 *
	 * @param \Horde_Imap_Client_Search_Query $query
	 * @param string $key
	 * @param mixed $value
	 * @param bool $not
	 */
	public static function applyCondition(\Horde_Imap_Client_Search_Query $query, string $key, $value, bool $not) : void
	{
		switch ($key)
		{
			case 'subject':
				$query->headerText('SUBJECT', (string)$value, $not);
				break;
			case 'from':
				$query->headerText('FROM', (string)$value, $not);
				break;
			case 'to':
				$query->headerText('TO', (string)$value, $not);
				break;
			case 'cc':
				$query->headerText('CC', (string)$value, $not);
				break;
			case 'body':
				$query->text((string)$value, true, $not);
				break;
			case 'text':
				$query->text((string)$value, false, $not);
				break;
			case 'minSize':
				$query->size((float)$value, true, $not);
				break;
			case 'maxSize':
				$query->size((float)$value, false, $not);
				break;
			case 'after':
				$query->dateSearch(new \DateTime((string)$value), \Horde_Imap_Client_Search_Query::DATE_SINCE, true, $not);
				break;
			case 'before':
				$query->dateSearch(new \DateTime((string)$value), \Horde_Imap_Client_Search_Query::DATE_BEFORE, true, $not);
				break;
			case 'hasKeyword':
				$query->flag(self::keywordToFlag((string)$value), !$not);
				break;
			case 'notKeyword':
				$query->flag(self::keywordToFlag((string)$value), $not);
				break;
			case 'inMailbox':
				break;	// handled separately by findInMailbox(), not a search criterion
		}
	}

	/**
	 * @param string $keyword JMAP keyword, e.g. "$flagged"
	 * @return string flag/keyword name as Horde_Imap_Client_Search_Query::flag() expects
	 */
	public static function keywordToFlag(string $keyword) : string
	{
		return match ($keyword)
		{
			'$seen' => 'Seen',
			'$answered' => 'Answered',
			'$flagged' => 'Flagged',
			default => $keyword,
		};
	}

	/**
	 * Translate MailJmap.buildSort()'s Comparator array into Horde's SORT_* option array
	 *
	 * @param array $sortSpec {property: string, isAscending: bool}[]
	 * @return array
	 */
	public static function buildSort(array $sortSpec) : array
	{
		static $map = [
			'subject' => \Horde_Imap_Client::SORT_SUBJECT,
			'size' => \Horde_Imap_Client::SORT_SIZE,
			'from' => \Horde_Imap_Client::SORT_FROM,
			'to' => \Horde_Imap_Client::SORT_TO,
			'receivedAt' => \Horde_Imap_Client::SORT_ARRIVAL,
			'sentAt' => \Horde_Imap_Client::SORT_DATE,
		];
		$sort = [];
		foreach ($sortSpec as $criterion)
		{
			if (empty($criterion['isAscending']))
			{
				$sort[] = \Horde_Imap_Client::SORT_REVERSE;
			}
			$sort[] = $map[$criterion['property'] ?? ''] ?? \Horde_Imap_Client::SORT_DATE;
		}
		return $sort ?: [\Horde_Imap_Client::SORT_REVERSE, \Horde_Imap_Client::SORT_DATE];
	}

	/**
	 * Email/get: fetch exactly the properties MailJmap.getRows()/refreshRows() request for a
	 * batch of ids
	 *
	 * Needs to know which mailbox $ids live in (they're plain per-mailbox IMAP UIDs, not
	 * globally-unique real-JMAP ids - see the class docblock): either taken from the request's
	 * preceding Email/query (MailJmap.getRows()'s normal batching), or, when called standalone
	 * (MailJmap.refreshRows()), from an explicit "mailboxId" argument - our own local-only
	 * extension, same idea as emailSet()'s.
	 *
	 * @param string $accountId
	 * @param array $args {ids: string[], mailboxId?: string}
	 * @param array &$context see emailQuery()
	 * @return array {accountId: string, list: array[], notFound: string[]}
	 */
	public static function emailGet(string $accountId, array $args, array &$context) : array
	{
		$ids = array_map('strval', (array)($args['ids'] ?? []));
		// absent/empty "properties" means "all", per RFC 8621 - our own client always sends an
		// explicit list though, and only includes "preview" when the "Sneak preview in list"
		// toggle is on (mirrors mail_ui::get_rows()'s fetchPreview), to skip the extra IMAP work
		$properties = (array)($args['properties'] ?? []);
		$wantPreview = !$properties || in_array('preview', $properties, true);
		// body fields (mail/js/jmap.ts's MailJmap.fetchBody()) are only requested one id at a
		// time, and cost an extra per-message IMAP round trip below - skip for the row-list fetch
		static $bodyProperties = ['bodyStructure', 'textBody', 'htmlBody', 'attachments', 'bodyValues'];
		$wantBody = !$properties || array_intersect($properties, $bodyProperties);

		if ($accountId === '0')
		{
			return self::demoEmailGet($ids, $wantPreview);
		}
		if (!$ids)
		{
			return ['accountId' => $accountId, 'list' => [], 'notFound' => []];
		}
		$imap = self::imapServer($accountId);
		if (!empty($args['mailboxId']))
		{
			$mailbox = self::hordeMailbox($imap, self::folderPath((string)$args['mailboxId']));
		}
		else
		{
			$mailbox = $context['mailbox'][$accountId] ?? null;
			if ($mailbox === null)
			{
				throw new \Exception('Email/get without a preceding Email/query or a mailboxId for the same accountId in this request');
			}
		}

		$query = new \Horde_Imap_Client_Fetch_Query();
		$query->envelope();
		$query->flags();
		$query->size();
		$query->structure();
		if ($wantPreview)
		{
			$query->bodyText(['length' => 800, 'peek' => true]);
		}

		$results = $imap->fetch($mailbox, $query, [
			'ids' => new \Horde_Imap_Client_Ids(array_map('intval', $ids)),
		]);

		// IMAP FETCH responses come back in whatever order the server chooses (typically ascending
		// UID, NOT the order of the id-set given), so $results must NOT be iterated directly - that
		// would silently undo Email/query's sort (e.g. turning "newest first" into "oldest first"
		// within the page). Rebuild the list in the order Email/query already determined instead.
		$list = [];
		foreach ($ids as $id)
		{
			if (($data = $results[(int)$id] ?? null))
			{
				/** @var \Horde_Imap_Client_Data_Fetch $data */
				$list[] = self::emailFromFetch($imap, $mailbox, $id, $data, $wantPreview, (bool)$wantBody);
			}
		}
		$found = array_column($list, 'id');

		return [
			'accountId' => $accountId,
			'list' => $list,
			'notFound' => array_values(array_diff($ids, $found)),
		];
	}

	/**
	 * Email/set: apply the supported JMAP keyword patches directly through Horde IMAP.
	 *
	 * The local shim uses mailbox-local numeric IMAP UIDs as Email ids, therefore the
	 * browser includes our local-only mailboxId extension.  Real JMAP servers never
	 * receive that extension.
	 *
	 * @param string $accountId
	 * @param array $args {mailboxId:string, update:array<string,array<string,bool|null>>}
	 * @return array JMAP Email/set response
	 */
	public static function emailSet(string $accountId, array $args) : array
	{
		$updated = [];
		$notUpdated = [];
		$add = [];
		$remove = [];
		// id => target folder path (base64-decoded from the mailboxIds patch's one truthy key) -
		// a move (full-property replacement, {"mailboxIds": {newId: true}}), see
		// MailJmap.moveMessages() (mail/js/jmap.ts) - there's always exactly one truthy entry to
		// look at
		$moves = [];
		// id => target folder path, from a "mailboxIds/<id>": true PatchObject path (RFC 8620
		// §5.3) - a copy (adds the target mailbox without touching existing ones), see
		// MailJmap.copyMessages() (mail/js/jmap.ts)
		$copies = [];
		$allowed = self::writableKeywords();

		foreach ((array)($args['update'] ?? []) as $id => $patch)
		{
			$id = (string)$id;
			if (!ctype_digit($id) || !is_array($patch))
			{
				$notUpdated[$id] = ['type' => 'invalidArguments'];
				continue;
			}
			$operations = [];
			$moveTo = null;
			$copyTo = null;
			foreach ($patch as $path => $value)
			{
				if ((string)$path === 'mailboxIds' && is_array($value))
				{
					$target = array_key_first(array_filter($value));
					if ($target === null)
					{
						$notUpdated[$id] = ['type' => 'invalidProperties', 'properties' => ['mailboxIds']];
						continue 2;
					}
					$moveTo = self::folderPath((string)$target);
					continue;
				}
				if (str_starts_with((string)$path, 'mailboxIds/') && $value === true)
				{
					$copyTo = self::folderPath(substr((string)$path, strlen('mailboxIds/')));
					continue;
				}
				if (!str_starts_with((string)$path, 'keywords/'))
				{
					$notUpdated[$id] = ['type' => 'invalidProperties', 'properties' => [(string)$path]];
					continue 2;
				}
				$keyword = strtolower(substr((string)$path, strlen('keywords/')));
				if (!isset($allowed[$keyword]) || ($value !== true && $value !== null))
				{
					$notUpdated[$id] = ['type' => 'invalidProperties', 'properties' => [(string)$path]];
					continue 2;
				}
				$operations[] = [$allowed[$keyword], $value === true];
			}
			foreach ($operations as [$keyword, $set])
			{
				if ($set)
				{
					$add[$keyword][] = $id;
				}
				else
				{
					$remove[$keyword][] = $id;
				}
			}
			if ($moveTo !== null)
			{
				$moves[$moveTo][] = $id;
			}
			if ($copyTo !== null)
			{
				$copies[$copyTo][] = $id;
			}
			$updated[$id] = null;
		}

		$destroyed = [];
		$notDestroyed = [];
		foreach ((array)($args['destroy'] ?? []) as $id)
		{
			$id = (string)$id;
			if (!ctype_digit($id))
			{
				$notDestroyed[$id] = ['type' => 'invalidArguments'];
				continue;
			}
			$destroyed[] = $id;
		}

		if ($accountId === '0')
		{
			return [
				'accountId' => $accountId,
				'oldState' => '0',
				'newState' => '0',
				'updated' => (object)$updated,
				'notUpdated' => (object)$notUpdated,
				'destroyed' => $destroyed,
				'notDestroyed' => (object)$notDestroyed,
			];
		}

		$mailboxId = (string)($args['mailboxId'] ?? '');
		$folder = self::folderPath($mailboxId);
		if ($folder === '')
		{
			throw new \InvalidArgumentException('Email/set requires mailboxId for the local IMAP shim');
		}
		$imap = self::imapServer($accountId);
		$mailbox = self::hordeMailbox($imap, $folder);
		// Remove first so replacing a custom flag never leaves two selected if the
		// following add fails.
		foreach ([['remove', $remove], ['add', $add]] as [$operation, $operations])
		{
			foreach ($operations as $keyword => $ids)
			{
				$imap->store($mailbox, [
					$operation => [$keyword],
					'ids' => new \Horde_Imap_Client_Ids(array_map('intval', $ids)),
				]);
			}
		}
		// same primitive Mail::moveMessages()'s same-account branch uses (api/src/Mail.php),
		// called directly - a server-internal IMAP COPY+move, no message bytes handled by us
		foreach ($moves as $targetFolder => $ids)
		{
			$targetMailbox = self::hordeMailbox($imap, $targetFolder);
			$imap->copy($mailbox, $targetMailbox, [
				'ids' => new \Horde_Imap_Client_Ids(array_map('intval', $ids)),
				'move' => true,
			]);
		}
		// same IMAP COPY primitive, without 'move' - the message stays in $mailbox too, see
		// MailJmap.copyMessages()
		foreach ($copies as $targetFolder => $ids)
		{
			$targetMailbox = self::hordeMailbox($imap, $targetFolder);
			$imap->copy($mailbox, $targetMailbox, [
				'ids' => new \Horde_Imap_Client_Ids(array_map('intval', $ids)),
			]);
		}
		if ($destroyed)
		{
			// same primitive Mail::deleteMessages()'s "remove_immediately" mode uses
			$imap->store($mailbox, [
				'add' => ['\\Deleted'],
				'ids' => new \Horde_Imap_Client_Ids(array_map('intval', $destroyed)),
			]);
			$imap->expunge($mailbox);
		}

		return [
			'accountId' => $accountId,
			'oldState' => '0',
			'newState' => '0',
			'updated' => (object)$updated,
			'notUpdated' => (object)$notUpdated,
			'destroyed' => $destroyed,
			'notDestroyed' => (object)$notDestroyed,
		];
	}

	/**
	 * Keywords the Mail UI is allowed to mutate through the local shim.
	 *
	 * @return array<string,string> JMAP keyword to IMAP flag / keyword
	 */
	public static function writableKeywords() : array
	{
		$keywords = ['$flagged' => '\\Flagged'];
		foreach (['label1', 'label2', 'label3', 'label4', 'label5',
			'customflag1', 'customflag2', 'customflag3', 'customflag4', 'customflag5'] as $keyword)
		{
			$keywords['$'.$keyword] = '$'.$keyword;
		}
		foreach (array_keys(Api\Mail::getCustomLabels()) as $keyword)
		{
			$keywords['$'.strtolower($keyword)] = '$'.strtolower($keyword);
		}
		return $keywords;
	}

	/**
	 * Map a single fetched message to a JMAP-shaped Email object (only the properties
	 * MailJmap.getRows() requests - see the class docblock for the full scope note)
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailbox
	 * @param string $uid
	 * @param \Horde_Imap_Client_Data_Fetch $data
	 * @param bool $wantPreview false skips the (extra IMAP round-trip) preview snippet entirely
	 * @return array
	 */
	public static function emailFromFetch(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid, \Horde_Imap_Client_Data_Fetch $data, bool $wantPreview = true, bool $wantBody = false) : array
	{
		$envelope = $data->getEnvelope();
		$structure = $data->getStructure();

		$hasAttachment = false;
		if ($structure)
		{
			foreach ($structure->partIterator() as $part)
			{
				/** @var \Horde_Mime_Part $part */
				if ($part->isAttachment())
				{
					$hasAttachment = true;
					break;
				}
			}
		}

		$email = [
			'id' => $uid,
			'keywords' => self::flagsToKeywords($data->getFlags()),
			'size' => $data->getSize(),
			'receivedAt' => self::imapDate($data->getImapDate()),
			'sentAt' => self::imapDate($envelope->date),
			'subject' => (string)$envelope->subject,
			'preview' => $wantPreview ? self::preview($imap, $mailbox, $uid, $structure, $data) : '',
			'from' => self::addressList($envelope->from),
			'to' => self::addressList($envelope->to),
			'cc' => self::addressList($envelope->cc),
			'bcc' => self::addressList($envelope->bcc),
			'hasAttachment' => $hasAttachment,
		];
		if ($wantBody && $structure)
		{
			$email += self::emailBodyFields($imap, $mailbox, $uid, $structure);
		}
		return $email;
	}

	/**
	 * Build the RFC 8621 body fields (bodyStructure/textBody/htmlBody/attachments/bodyValues) for
	 * one message - direct Imap/Horde MIME work, deliberately NOT going through mail_ui/Api\Mail
	 * (see class docblock). Ported subset of Mail::getMultipartAlternative()/getTextPart()'s
	 * essential logic (best-body selection via Horde_Mime_Part::findBody(), transfer-decode +
	 * charset-convert), not a call into those methods.
	 *
	 * mail/js/jmap.ts's MailJmap.fetchBody() decides client-side (from bodyStructure/attachments
	 * alone, before ever calling this) whether a message needs the legacy server-side path instead
	 * (S/MIME, winmail.dat, meeting invites) - this method is only reached for the remaining "plain
	 * mail" case (which now includes PGP/MIME - Mailvelope decrypts it entirely client-side, see
	 * MailJmap.findPgpPart()/downloadPartText(), fetching the raw ciphertext part via the blobId
	 * scheme below), so it doesn't need to special-case any of those itself.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailbox
	 * @param string $uid
	 * @param \Horde_Mime_Part $structure
	 * @return array {bodyStructure: array, textBody: array[], htmlBody: array[], attachments: array[], bodyValues: array}
	 */
	public static function emailBodyFields(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid, \Horde_Mime_Part $structure) : array
	{
		$textId = $structure->findBody('plain');
		$htmlId = $structure->findBody('html');

		$attachments = [];
		foreach ($structure->partIterator() as $part)
		{
			/** @var \Horde_Mime_Part $part */
			$id = $part->getMimeId();
			if ($part->getPrimaryType() === 'multipart' || $id === $textId || $id === $htmlId)
			{
				continue;
			}
			$attachments[] = self::bodyPartToJmap($part, $mailbox, $uid);
		}

		$bodyValues = [];
		foreach (array_unique(array_filter([$textId, $htmlId])) as $partId)
		{
			$bodyValues[$partId] = self::fetchBodyValue($imap, $mailbox, $uid, $structure, $partId);
		}

		return [
			'bodyStructure' => self::bodyPartToJmap($structure, $mailbox, $uid),
			'textBody' => $textId !== null ? [self::bodyPartToJmap($structure->getPart($textId), $mailbox, $uid)] : [],
			'htmlBody' => $htmlId !== null ? [self::bodyPartToJmap($structure->getPart($htmlId), $mailbox, $uid)] : [],
			'attachments' => $attachments,
			'bodyValues' => $bodyValues,
		];
	}

	/**
	 * One Horde_Mime_Part -> one RFC 8621 EmailBodyPart, recursing into subParts for multipart
	 *
	 * blobId is self-describing (base64($mailbox).':'.$uid.':'.partId) since the local shim has no
	 * separate blob store/registry to look one up in later, unlike a real JMAP server - download()
	 * below decodes it back. Mirrors how row-ids already encode base64(folder)/uid for the same
	 * reason (see class docblock).
	 *
	 * @param \Horde_Mime_Part $part
	 * @param string $mailbox real (already delimiter-translated) IMAP mailbox name
	 * @param string $uid
	 * @return array
	 */
	public static function bodyPartToJmap(\Horde_Mime_Part $part, string $mailbox, string $uid) : array
	{
		$contentId = $part->getContentId();
		$result = [
			'partId' => $part->getMimeId(),
			'blobId' => self::urlsafeB64Encode($mailbox).':'.$uid.':'.$part->getMimeId(),
			'size' => $part->getBytes(),
			'name' => $part->getName() ?: null,
			'type' => strtolower((string)$part->getType()),
			'charset' => $part->getContentTypeParameter('charset') ?: null,
			'disposition' => $part->getDisposition() ?: null,
			'cid' => $contentId ? trim($contentId, '<>') : null,
		];
		if ($part->getPrimaryType() === 'multipart')
		{
			$result['subParts'] = array_map(static fn($sub) => self::bodyPartToJmap($sub, $mailbox, $uid), $part->getParts());
		}
		return $result;
	}

	/**
	 * Fetch + transfer-decode + charset-convert (to UTF-8) one body part's text content
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailbox
	 * @param string $uid
	 * @param \Horde_Mime_Part $structure
	 * @param string $partId
	 * @return array {value: string, isEncodingProblem: bool, isTruncated: bool}
	 */
	public static function fetchBodyValue(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid, \Horde_Mime_Part $structure, string $partId) : array
	{
		$query = new \Horde_Imap_Client_Fetch_Query();
		// 'decode' asks the IMAP server to reverse Content-Transfer-Encoding itself (RFC 3516
		// BINARY) - not guaranteed to happen (Dovecot commonly doesn't for quoted-printable/base64
		// text parts), so getBodyPartDecode() below must still be checked and, same as
		// Mail::fetchPartContents()'s established recipe, decoded client-side via the part's own
		// setContents()/getContents() (which falls back to the part's own parsed
		// Content-Transfer-Encoding header when $encoding is null) whenever it didn't
		$query->bodyPart($partId, ['decode' => true, 'peek' => true]);
		$results = $imap->fetch($mailbox, $query, [
			'ids' => new \Horde_Imap_Client_Ids([(int)$uid]),
		]);
		$partData = $results[(int)$uid] ?? null;
		$raw = $partData ? (string)$partData->getBodyPart($partId) : '';
		$encoding = $partData ? $partData->getBodyPartDecode($partId) : null;

		$part = $structure->getPart($partId);
		$part->setContents($raw, ['encoding' => $encoding]);
		$charset = $part->getContentTypeParameter('charset') ?: 'us-ascii';

		return [
			'value' => Api\Translation::convert($part->getContents(), $charset, 'utf-8'),
			'isEncodingProblem' => false,
			'isTruncated' => false,
		];
	}

	/**
	 * JMAP Blob download (RFC 8620 §6.2) for the local shim - streams one part's raw,
	 * transfer-decoded bytes (or the whole raw RFC822 message, blobId's partId segment empty).
	 *
	 * Called from mail/jmap.php's GET route matching the "downloadUrl" template returned by
	 * session() - authenticated the same way as everything else here (same-origin session cookie,
	 * see class docblock), not by the bearer token in the Authorization header jmap-jam sends (that
	 * header is otherwise unused/ignored by this shim, same as the JSON POST dispatch).
	 *
	 * @param string $accountId
	 * @param string $blobId see bodyPartToJmap() - base64($mailbox).':'.$uid.':'.$partId
	 * @param string $name suggested filename for Content-Disposition
	 * @param string $type Content-Type to send
	 */
	public static function download(string $accountId, string $blobId, string $name, string $type) : void
	{
		[$mailboxB64, $uid, $partId] = array_pad(explode(':', $blobId, 3), 3, null);
		$imap = ($mailboxB64 !== null && $uid) ? self::imapServer($accountId) : null;
		if (!$imap)
		{
			http_response_code(404);
			return;
		}
		$mailbox = self::urlsafeB64Decode($mailboxB64);

		$bytes = $partId !== '' ? self::fetchRawPart($imap, $mailbox, $uid, $partId) : self::fetchRawMessage($imap, $mailbox, $uid);
		if ($bytes === null)
		{
			http_response_code(404);
			return;
		}

		header('Content-Type: '.$type);
		header('Content-Disposition: inline; filename="'.addslashes($name).'"');
		header('Content-Length: '.strlen($bytes));
		echo $bytes;
	}

	/**
	 * Fetch one body-part's raw, transfer-decoded bytes - deliberately WITHOUT fetchBodyValue()'s
	 * charset conversion, since this must return exact original bytes (binary attachments, PGP
	 * data, a TNEF/winmail.dat attachment for Mail::tnef_decoder()'s Horde_Compress input, ...).
	 *
	 * Direct Imap/Horde MIME work, deliberately NOT going through mail_ui/Api\Mail (see class
	 * docblock) - reused by download() (the browser-facing JMAP Blob download) and by the
	 * server-side JMAP-native S/MIME/TNEF resolvers (Imap\Jmap for Stalwart, this class for the
	 * local shim - see plan) fetching a part in-process, no HTTP round trip needed.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailbox
	 * @param string $uid
	 * @param string $partId
	 * @return ?string null if the message/part wasn't found
	 */
	public static function fetchRawPart(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid, string $partId) : ?string
	{
		$query = new \Horde_Imap_Client_Fetch_Query();
		$query->structure();
		$query->bodyPart($partId, ['decode' => true, 'peek' => true]);
		$results = $imap->fetch($mailbox, $query, [
			'ids' => new \Horde_Imap_Client_Ids([(int)$uid]),
		]);
		$data = $results[(int)$uid] ?? null;
		if (!$data)
		{
			return null;
		}
		// same transfer-decode recipe as fetchBodyValue()
		$raw = (string)$data->getBodyPart($partId);
		$encoding = $data->getBodyPartDecode($partId);
		$part = $data->getStructure()->getPart($partId);
		$part->setContents($raw, ['encoding' => $encoding]);
		return $part->getContents();
	}

	/**
	 * Fetch the whole raw RFC822 message - the JMAP-native equivalent of Mail::getMessageRawBody()
	 * (which uses the exact same Horde_Imap_Client_Fetch_Query::fullText() primitive, just via
	 * mail_bo), needed by the S/MIME resolver (Mail\Smime/Horde_Crypt_Smime decrypt/verify the
	 * *whole* message, not one part).
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailbox
	 * @param string $uid
	 * @return ?string null if the message wasn't found
	 */
	public static function fetchRawMessage(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid) : ?string
	{
		$query = new \Horde_Imap_Client_Fetch_Query();
		$query->fullText(['peek' => true]);
		$results = $imap->fetch($mailbox, $query, [
			'ids' => new \Horde_Imap_Client_Ids([(int)$uid]),
		]);
		$data = $results[(int)$uid] ?? null;
		return $data ? (string)$data->getFullMsg() : null;
	}

	/**
	 * Bare structure-only fetch (no body/preview) - used by mail_ui::get_load_email_data()'s
	 * JMAP-native dispatch (see plan) to cheaply decide, before fetching any body content, whether
	 * a message needs the S/MIME/TNEF resolvers or falls through to the classic path.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailbox
	 * @param string $uid
	 * @return ?\Horde_Mime_Part null if not found
	 */
	public static function structureGet(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid) : ?\Horde_Mime_Part
	{
		$query = new \Horde_Imap_Client_Fetch_Query();
		$query->structure();
		$results = $imap->fetch($mailbox, $query, [
			'ids' => new \Horde_Imap_Client_Ids([(int)$uid]),
		]);
		$data = $results[(int)$uid] ?? null;
		return $data ? $data->getStructure() : null;
	}

	// top-level content-types that need the JMAP-native S/MIME resolver (resolveSmime()/
	// resolveSmimeJmap()) - same list mail/js/jmap.ts's MailJmap.SPECIAL_CASE_TYPES uses for S/MIME
	private const SMIME_TYPES = [
		'multipart/signed', 'application/pkcs7-mime', 'application/x-pkcs7-mime',
		'application/pkcs7-signature', 'application/x-pkcs7-signature',
	];

	/**
	 * Decide whether a message's *top-level* bodyStructure needs the JMAP-native S/MIME or TNEF
	 * resolver - top-level only (not a recursive walk), matching both Mail::getStructure()'s own
	 * original S/MIME check (top-level type/protocol only) and this plan's TNEF scoping (only the
	 * whole-message-is-TNEF case, not TNEF-as-a-regular-attachment - see JmapShim::resolveTnef()'s
	 * docblock).
	 *
	 * @param array $bodyStructure RFC 8621 EmailBodyPart shape (bodyPartToJmap()/Email/get), or a
	 *  plain {type: string, ...} - only the top-level "type" is inspected
	 * @return string|null 'smime', 'tnef', or null (not a special case, fall through to the
	 *  classic path - meeting invites and anything else)
	 */
	public static function specialCaseType(array $bodyStructure) : ?string
	{
		$type = strtolower($bodyStructure['type'] ?? '');
		if (in_array($type, self::SMIME_TYPES, true))
		{
			return 'smime';
		}
		if ($type === 'application/ms-tnef')
		{
			return 'tnef';
		}
		return null;
	}

	/**
	 * Build the flat attachment-array shape mail_ui::createAttachmentBlock() expects, from a
	 * TNEF-decoded Horde_Mime_Part tree (Mail::tnef_decoder()'s output) - ported from the
	 * equivalent loop in Mail::getMessageAttachments() (api/src/Mail.php:6254-6286), not a call
	 * into it, since that method also does the (unrelated, IMAP-based) surrounding attachment
	 * enumeration this doesn't need - used by mail_ui::ajax_resolveWinmail()'s JMAP-native path,
	 * for a winmail.dat attached alongside a normal message (as opposed to the whole-message-is-
	 * TNEF case, see resolveTnef()/specialCaseType() above, an unrelated/rarer case).
	 *
	 * @param string $uid original message uid (the winmail.dat attachment's own container message)
	 * @param string $partID original winmail.dat part's MIME id within that message
	 * @param \Horde_Mime_Part $decoded Mail::tnef_decoder()'s output
	 * @return array[] attachment entries, same shape Mail::getMessageAttachments() produces
	 */
	public static function tnefAttachments(string $uid, string $partID, \Horde_Mime_Part $decoded) : array
	{
		$attachments = [];
		foreach ($decoded->getParts() as $mime_id => $part)
		{
			/** @var \Horde_Mime_Part $part */
			$attachment = $part->getAllDispositionParameters();
			$attachment['disposition'] = $part->getDisposition();
			$attachment['mimeType'] = $part->getType();
			$attachment['uid'] = $uid;
			$attachment['partID'] = $partID;
			$attachment['is_winmail'] = $uid.'@'.$partID.'@'.$mime_id;
			if (empty($attachment['name']))
			{
				$attachment['name'] = Api\Mail::attachmentName($part);
			}
			$attachment['size'] = $part->getBytes();
			if (($cid = $part->getContentId()))
			{
				$attachment['cid'] = $cid;
			}
			if (empty($attachment['name']))
			{
				$attachment['name'] = (!empty($attachment['cid']) ? $attachment['cid'] :
					'unknown_Uid'.$uid.'_Part'.$mime_id).'.'.Api\MimeMagic::mime2ext($attachment['mimeType']);
			}
			$attachments[] = $attachment;
		}
		return $attachments;
	}

	/**
	 * Render an already-fully-parsed (in-memory) Horde_Mime_Part tree to final sanitized HTML -
	 * shared by the JMAP-native S/MIME and TNEF resolvers (resolveSmime()/resolveTnef() below, and
	 * Imap\Jmap's equivalents for Stalwart). Deliberately new code, not a call into
	 * mail_ui::getdisplayableBody()/showBody() - mirrors mail/js/jmap.ts's
	 * MailJmap.assembleBodyHtml()'s selection logic (best-body via findBody(), prefer html unless
	 * "only_if_no_text"), server-side HTML sanitization via the existing generic Api\Html\HtmLawed
	 * (not mail-specific, same allowlist config the client-side DOMPurify path is modelled on)
	 * instead of the classic getdisplayableBody()/htmLawed-with-tidy pipeline.
	 *
	 * Known, accepted limitation (rare fallback path): no inline cid: image resolution or
	 * attachment list - both already have their own mechanisms for the normal mail path and are
	 * out of scope here (see plan).
	 *
	 * @param \Horde_Mime_Part $structure all parts' contents already populated (true after
	 *  Horde_Mime_Part::parseMessage() parses a complete raw message) - no fetch happens in here
	 * @param string $htmlOptions 'only_if_no_text' prefers text/plain if present, else default
	 *  (prefer html when present)
	 * @return string sanitized HTML body only (no document wrapper - callers still use
	 *  mail_ui::get_email_header()/showBody() for that, unchanged page chrome)
	 */
	public static function structureToHtml(\Horde_Mime_Part $structure, string $htmlOptions='') : string
	{
		$textId = $structure->findBody('plain');
		$htmlId = $structure->findBody('html');
		$useHtml = $htmlId !== null && $htmlOptions !== 'only_if_no_text';
		$partId = $useHtml ? $htmlId : ($textId ?? $htmlId);

		if ($partId === null)
		{
			return '';
		}
		$part = $structure->getPart($partId);
		$charset = $part->getContentTypeParameter('charset') ?: 'us-ascii';
		$raw = Api\Translation::convert($part->getContents(), $charset, 'utf-8');

		if ($useHtml && $partId === $htmlId)
		{
			$htmLawed = new Api\Html\HtmLawed();
			return $htmLawed->run($raw, Api\Mail::$htmLawed_config);
		}
		return '<pre>'.htmlspecialchars($raw, ENT_QUOTES, 'UTF-8').'</pre>';
	}

	/**
	 * JMAP-native S/MIME resolution for the local shim - fetches the raw message directly (no
	 * mail_ui/Api\Mail involved, see class docblock), decrypts/verifies via the existing
	 * Api\Mail\Smime::resolveMessage() (shared with Imap\Jmap's Stalwart equivalent), and renders
	 * via structureToHtml() above.
	 *
	 * @param string $accountId
	 * @param string $mailboxId JMAP Mailbox id (base64 folder path)
	 * @param string $uid
	 * @param string $topLevelType see Api\Mail\Smime::resolveMessage()
	 * @param string $fromAddress
	 * @param string $htmlOptions
	 * @param string $passphrase
	 * @return string sanitized HTML body
	 * @throws Api\Mail\Smime\PassphraseMissing
	 * @throws \Exception message/mailbox not found
	 */
	public static function resolveSmime(string $accountId, string $mailboxId, string $uid, string $topLevelType,
		string $fromAddress, string $htmlOptions='', string $passphrase='') : string
	{
		$imap = self::imapServer($accountId);
		$mailbox = self::hordeMailbox($imap, self::folderPath($mailboxId));
		$raw = self::fetchRawMessage($imap, $mailbox, $uid);
		if ($raw === null)
		{
			throw new \Exception("Message '$uid' not found in '$mailbox'!");
		}
		$structure = Api\Mail\Smime::resolveMessage((int)$accountId, $raw, $topLevelType, $passphrase, $fromAddress);
		return self::structureToHtml($structure, $htmlOptions);
	}

	/**
	 * JMAP-native TNEF resolution for the local shim, for the case where the *entire* message is a
	 * TNEF/winmail.dat blob (single-part application/ms-tnef, no separate real body - see
	 * MailJmap.isSpecialCase()'s client-side detection this mirrors). Uses the existing
	 * Api\Mail::tnef_decoder() (Horde_Compress, transport-agnostic, unchanged) fed JMAP-fetched
	 * bytes instead of an IMAP-fetched attachment.
	 *
	 * @param string $accountId
	 * @param string $mailboxId JMAP Mailbox id (base64 folder path)
	 * @param string $uid
	 * @param string $partId the whole-message TNEF part's id (usually "1")
	 * @param string $htmlOptions
	 * @return string sanitized HTML body
	 * @throws \Exception message/part not found, or TNEF decoding failed
	 */
	public static function resolveTnef(string $accountId, string $mailboxId, string $uid, string $partId, string $htmlOptions='') : string
	{
		$imap = self::imapServer($accountId);
		$mailbox = self::hordeMailbox($imap, self::folderPath($mailboxId));
		$raw = self::fetchRawPart($imap, $mailbox, $uid, $partId);
		if ($raw === null)
		{
			throw new \Exception("Message '$uid' part '$partId' not found in '$mailbox'!");
		}
		// Mail::tnef_decoder() is pure Horde_Compress orchestration with no $this/IMAP usage at
		// all - made static (was an unnecessary instance method) so it's callable directly here
		$decoded = Api\Mail::tnef_decoder($raw);
		if (!$decoded)
		{
			throw new \Exception('Could not decode TNEF data');
		}
		return self::structureToHtml($decoded, $htmlOptions);
	}

	/**
	 * Get a clean preview snippet.
	 *
	 * For a multipart message, the base message's body-text (used directly for a
	 * singlepart message) is the *raw* multipart content - MIME boundaries, sub-part
	 * headers and all - so for multipart messages we do one extra, per-message
	 * bodyPart() fetch for the actual first text part (Horde_Mime_Part::findBody()),
	 * instead of showing that raw MIME structure to the user.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailbox
	 * @param string $uid
	 * @param \Horde_Mime_Part|null $structure
	 * @param \Horde_Imap_Client_Data_Fetch $data
	 * @return string
	 */
	public static function preview(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid, ?\Horde_Mime_Part $structure, \Horde_Imap_Client_Data_Fetch $data) : string
	{
		if ($structure && stripos((string)$structure->getType(), 'multipart/') === 0 &&
			($bodyId = $structure->findBody('plain') ?? $structure->findBody()) !== null)
		{
			try
			{
				$partQuery = new \Horde_Imap_Client_Fetch_Query();
				$partQuery->bodyPart($bodyId, ['decode' => true, 'length' => 800, 'peek' => true]);
				$partResults = $imap->fetch($mailbox, $partQuery, [
					'ids' => new \Horde_Imap_Client_Ids([(int)$uid]),
				]);
				if (($partData = $partResults[(int)$uid] ?? null))
				{
					/** @var \Horde_Imap_Client_Data_Fetch $partData */
					return self::cleanPreview((string)$partData->getBodyPart($bodyId));
				}
			}
			catch (\Throwable $e)
			{
				// fall through to the (possibly noisy) top-level body text below
			}
		}
		return self::cleanPreview((string)$data->getBodyText(0));
	}

	/**
	 * Strip MIME header/boundary lines and collapse whitespace, then truncate
	 *
	 * @param string $text
	 * @return string
	 */
	public static function cleanPreview(string $text) : string
	{
		$lines = array_filter(preg_split('/\r?\n/', $text), static function ($line)
		{
			$line = trim($line);
			return $line !== '' && !preg_match('/^(--|[\w-]+:\s)/', $line);
		});
		$preview = trim(preg_replace('/\s+/u', ' ', strip_tags(implode(' ', $lines))));
		return mb_substr($preview, 0, 200);
	}

	/**
	 * @param iterable|null $list Horde_Mail_Rfc822_List
	 * @return array [{name?: string, email: string}]
	 */
	public static function addressList($list) : array
	{
		$result = [];
		foreach ($list ?? [] as $address)
		{
			/** @var \Horde_Mail_Rfc822_Address $address */
			$entry = ['email' => $address->bare_address];
			if (($personal = (string)$address->personal) !== '')
			{
				$entry['name'] = $personal;
			}
			$result[] = $entry;
		}
		return $result;
	}

	/**
	 * IMAP flags/keywords (e.g. "\Seen", "$Forwarded", "$label1") -> JMAP keywords object
	 * with the exact "$seen"/"$answered"/"$flagged"/"$forwarded"/"$labelN" naming
	 * MailJmap.email2row() already expects.
	 *
	 * @param string[] $flags
	 * @return array<string, true>
	 */
	public static function flagsToKeywords(array $flags) : array
	{
		$keywords = [];
		foreach ($flags as $flag)
		{
			$flag = ltrim($flag, '\\');
			$keywords[strtolower($flag[0] === '$' ? $flag : '$'.$flag)] = true;
		}
		return $keywords;
	}

	/**
	 * @param \DateTime|null $date
	 * @return string|null
	 */
	public static function imapDate(?\DateTime $date) : ?string
	{
		if (!$date)
		{
			return null;
		}
		// eTemplate/get_rows convention (NOT real UTC, despite the "Z"): dates are converted to the
		// *user's* timezone (Api\DateTime::$user_timezone, from prefs), then formatted with a
		// literal "Z" suffix so the browser displays those wall-clock numbers as-is instead of
		// re-applying its own (browser-local) timezone conversion on top. Horde's DateTime objects
		// carry the server's timezone, not the user's, so this conversion is required, not optional -
		// Api\DateTime::to() handles it the same way classic get_rows()/Nextmatch.php do.
		return Api\DateTime::to($date, Api\DateTime::ET2);
	}

	/**
	 * Static in-class fixture (2 mailboxes with mail, plus one always-empty one), used only for
	 * accountId "0" - lets the client-side JMAP code be exercised/tested without a real mailbox.
	 *
	 * @return array {mailboxes: string[], emails: array<string, array>} keyed by (fake) UID
	 */
	public static function demoFixture() : array
	{
		static $fixture = null;
		if ($fixture !== null)
		{
			return $fixture;
		}

		$subjects = [
			['Welcome to EGroupware', 'info', 'Info', ['$seen'], 'INBOX'],
			['Your invoice #1042', 'billing', 'Billing', ['$seen', '$flagged'], 'INBOX'],
			['Re: Project kickoff', 'alice', 'Alice Adams', ['$seen', '$answered'], 'INBOX'],
			['Meeting notes 2026-08-04', 'bob', 'Bob Brown', [], 'INBOX'],
			['Fwd: Quarterly report', 'carol', 'Carol Clark', ['$forwarded'], 'INBOX'],
			['Server maintenance window', 'ops', 'Ops Team', ['$seen'], 'INBOX'],
			['Newsletter August 2026', 'news', 'Newsletter', [], 'INBOX'],
			['Action required: password expiry', 'security', 'Security', ['$flagged'], 'INBOX'],
			['Re: Your ticket', 'support', 'Support', ['$seen', '$answered'], 'INBOX/Sent'],
		];

		$emails = [];
		foreach ($subjects as $i => [$subject, $local, $name, $keywords, $mailbox])
		{
			$id = (string)($i + 1);
			$when = self::imapDate(new \DateTime('-'.$i.' hours', new \DateTimeZone('UTC')));
			$emails[$id] = [
				'id' => $id,
				'mailbox' => $mailbox,
				'keywords' => array_fill_keys($keywords, true),
				'size' => 1024 * (2 + $i),
				'receivedAt' => $when,
				'sentAt' => $when,
				'subject' => $subject,
				'preview' => 'This is a demo message body, used to exercise the client-side JMAP get_rows() path without a real mailbox.',
				'from' => [['name' => $name, 'email' => $local.'@example.com']],
				'to' => [['email' => 'demo@example.com']],
				'cc' => [],
				'bcc' => [],
				'hasAttachment' => $i % 3 === 0,
			];
		}
		return $fixture = [
			'mailboxes' => ['INBOX', 'INBOX/Sent', 'INBOX/Drafts'],
			'emails' => $emails,
		];
	}

	/**
	 * @param string $folder
	 * @param array $args
	 * @param array &$context unused for the demo account, kept for signature symmetry
	 * @return array {accountId: string, ids: string[], total: int}
	 */
	public static function demoEmailQuery(string $folder, array $args, array &$context) : array
	{
		unset($context);
		$folder = $folder ?: 'INBOX';
		// array_keys() would silently cast these numeric-string ids back to int (PHP array-key
		// coercion), so re-stringify to keep ids consistent with the real (non-demo) IMAP path
		$ids = array_map('strval', array_keys(array_filter(
			self::demoFixture()['emails'],
			static fn($email) => $email['mailbox'] === $folder,
		)));
		$total = count($ids);

		$position = max(0, (int)($args['position'] ?? 0));
		$limit = (int)($args['limit'] ?? 50) ?: 50;

		return [
			'accountId' => '0',
			'ids' => array_slice($ids, $position, $limit),
			'total' => $total,
		];
	}

	/**
	 * @param string[] $ids
	 * @param bool $wantPreview false blanks the "preview" property, mirroring the real-account path
	 * @return array {accountId: string, list: array[], notFound: string[]}
	 */
	public static function demoEmailGet(array $ids, bool $wantPreview = true) : array
	{
		$emails = self::demoFixture()['emails'];
		$list = [];
		foreach ($ids as $id)
		{
			if (isset($emails[$id]))
			{
				$list[] = $wantPreview ? $emails[$id] : ['preview' => ''] + $emails[$id];
			}
		}
		$found = array_column($list, 'id');

		return [
			'accountId' => '0',
			'list' => $list,
			'notFound' => array_values(array_diff($ids, $found)),
		];
	}
}
