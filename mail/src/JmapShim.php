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
			'downloadUrl' => $url,
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
				$list[] = self::emailFromFetch($imap, $mailbox, $id, $data, $wantPreview);
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
			foreach ($patch as $path => $value)
			{
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
				$operations[] = [$keyword, $value === true];
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
			$updated[$id] = null;
		}

		if ($accountId === '0')
		{
			return [
				'accountId' => $accountId,
				'oldState' => '0',
				'newState' => '0',
				'updated' => (object)$updated,
				'notUpdated' => (object)$notUpdated,
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

		return [
			'accountId' => $accountId,
			'oldState' => '0',
			'newState' => '0',
			'updated' => (object)$updated,
			'notUpdated' => (object)$notUpdated,
		];
	}

	/**
	 * Keywords the Mail UI is allowed to mutate through the local shim.
	 *
	 * @return array<string,true>
	 */
	public static function writableKeywords() : array
	{
		$keywords = [];
		foreach (['label1', 'label2', 'label3', 'label4', 'label5',
			'customflag1', 'customflag2', 'customflag3', 'customflag4', 'customflag5'] as $keyword)
		{
			$keywords['$'.$keyword] = true;
		}
		foreach (array_keys(Api\Mail::getCustomLabels()) as $keyword)
		{
			$keywords['$'.strtolower($keyword)] = true;
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
	public static function emailFromFetch(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid, \Horde_Imap_Client_Data_Fetch $data, bool $wantPreview = true) : array
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

		return [
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
