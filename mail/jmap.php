<?php
/**
 * EGroupware Mail: local JMAP server for plain IMAP accounts
 *
 * mail/js/jmap.ts (MailJmap) already speaks JMAP client-side for Stalwart-backed
 * accounts, talking directly to Stalwart's real JMAP server. Plain IMAP accounts
 * (Dovecot, Cyrus, ...) have no JMAP server at all, so this file acts as one -
 * but only implements the handful of methods MailJmap actually sends
 * (Mailbox/query, Email/query, Email/get, plus RFC 8620 §3.7 result-reference
 * resolution for its batched request), backed directly by
 * Api\Mail\Account::read()->imapServer() (a Horde_Imap_Client_Socket) via plain
 * IMAP search()/fetch() calls. It deliberately does NOT go through mail_ui or
 * Api\Mail (mail_bo) - those stay the legacy/actions layer for now.
 *
 * Row-id compatibility: emailID here is a plain IMAP UID and folderID is
 * base64(folder path), same as mail_ui::generateRowID()'s classic scheme - so
 * rows fetched via this file are indistinguishable from mail_ui::get_rows()'s
 * own output to every existing action handler. This is unlike Stalwart-sourced
 * rows, which use opaque JMAP ids and still need the (unfinished) emailId2uid()
 * translation before actions can understand them.
 *
 * accountId "0" is never a real account - it's served from an in-file fixture,
 * to give the client-side code a stable target for testing without a real
 * mailbox, e.g.: app.mail.jmap.getRows({selectedFolder: '0::INBOX', ...})
 *
 * Auth is the ordinary EGroupware session cookie (this is a same-origin fetch
 * from the browser) - see mail_ui::ajax_jmapBootstrap()'s local-shim branch,
 * which hands the client a fixed dummy bearer-token string, NEVER the real
 * session id, purely to satisfy jmap-jam's required config field.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2026 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * Called by Api\Egw::verify_session(), if there's no valid EGroupware session
 *
 * We're an API endpoint consumed by fetch(), not a page navigation, so we
 * answer with a JMAP-shaped 401 instead of the usual HTML redirect to login.
 *
 * @param mixed &$account unused, required by the autocreate_session_callback signature
 */
function mail_jmap_unauthorized(&$account)
{
	unset($account);
	http_response_code(401);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['type' => 'urn:ietf:params:jmap:error:notAuthorized'], JSON_UNESCAPED_SLASHES);
	exit;
}

use EGroupware\Api;
use EGroupware\Api\Mail\Account;

// Front-controller bootstrap/dispatch only runs when this file is the actual HTTP entrypoint.
// mail/tests/JmapTest.php defines MAIL_JMAP_TESTS before require()'ing this file, so it can
// call the (pure, testable) functions below directly without booting a full EGroupware session.
if (!defined('MAIL_JMAP_TESTS'))
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'disable_Template_class' => true,
			'noheader' => true,
			'currentapp' => 'mail',
			'autocreate_session_callback' => 'mail_jmap_unauthorized',
			'no_exception_handler' => true,
		),
	);
	include(dirname(__DIR__).'/header.inc.php');

	header('Content-Type: application/json; charset=utf-8');

	try
	{
		if ($_SERVER['REQUEST_METHOD'] === 'POST')
		{
			$request = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
			echo json_encode([
				'methodResponses' => mail_jmap_dispatch((array)($request['methodCalls'] ?? [])),
				'sessionState' => '0',
			], JSON_UNESCAPED_SLASHES);
		}
		else
		{
			echo json_encode(mail_jmap_session(), JSON_UNESCAPED_SLASHES);
		}
	}
	catch (\Throwable $e)
	{
		http_response_code(500);
		echo json_encode(['type' => 'serverFail', 'description' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
	}
	exit;
}

/**
 * JMAP session-discovery object, fetched once by jmap-jam's JamClient on construction
 *
 * Only 'apiUrl' is actually used by jmap-jam (verified against its bundled source) -
 * the rest is filled in for a plausible-looking, spec-shaped response.
 *
 * @return array
 */
function mail_jmap_session() : array
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
function mail_jmap_dispatch(array $methodCalls) : array
{
	$responses = [];
	$context = [];	// request-scoped state, e.g. remembering which mailbox an Email/query's ids came from

	foreach ($methodCalls as $call)
	{
		[$method, $args, $callId] = ((array)$call) + [null, [], null];
		try
		{
			$args = mail_jmap_resolve_refs((array)$args, $responses);
			$accountId = (string)($args['accountId'] ?? '0');

			switch ($method)
			{
				case 'Mailbox/query':
					$result = mail_jmap_mailbox_query($args);
					break;
				case 'Email/query':
					$result = mail_jmap_email_query($accountId, $args, $context);
					break;
				case 'Email/get':
					$result = mail_jmap_email_get($accountId, $args, $context);
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
function mail_jmap_resolve_refs(array $args, array $responses) : array
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
				$args[$name] = mail_jmap_json_path($response[1], $ref['path']);
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
function mail_jmap_json_path(array $value, string $path)
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
function mail_jmap_mailbox_query(array $args) : array
{
	$name = (string)($args['filter']['name'] ?? '');
	$parentPath = !empty($args['filter']['parentId']) ? mail_jmap_folder_path($args['filter']['parentId']) : '';
	$path = $parentPath !== '' ? $parentPath.'/'.$name : $name;

	return ['ids' => [base64_encode($path)]];
}

/**
 * @param string $folderId base64-encoded folder path
 * @return string
 */
function mail_jmap_folder_path(string $folderId) : string
{
	return $folderId === '' ? '' : (string)base64_decode($folderId);
}

/**
 * Get (and cache, per request) the Horde_Imap_Client for a real account, or null for the demo account
 *
 * @param string $accountId
 * @return \Horde_Imap_Client_Socket|null
 */
function mail_jmap_imap_server(string $accountId) : ?\Horde_Imap_Client_Socket
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
function mail_jmap_horde_mailbox(\Horde_Imap_Client_Socket $imap, string $path) : string
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
 *  globally-unique ids real JMAP requires - see the file docblock)
 * @return array {accountId: string, ids: string[], total: int}
 */
function mail_jmap_email_query(string $accountId, array $args, array &$context) : array
{
	$filter = (array)($args['filter'] ?? []);
	$folder = mail_jmap_folder_path((string)mail_jmap_find_in_mailbox($filter));

	if ($accountId === '0')
	{
		return mail_jmap_demo_email_query($folder, $args, $context);
	}

	$imap = mail_jmap_imap_server($accountId);
	$mailbox = mail_jmap_horde_mailbox($imap, $folder);

	$query = mail_jmap_filter_to_query($filter);
	// JMAP never exposes messages with the IMAP \Deleted flag (RFC 8621 §4.1.1) - match that
	$query->flag(\Horde_Imap_Client::FLAG_DELETED, false);

	$sorted = $imap->search($mailbox, $query, [
		'sort' => mail_jmap_build_sort((array)($args['sort'] ?? [])),
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
function mail_jmap_find_in_mailbox(array $filter) : ?string
{
	if (isset($filter['inMailbox']))
	{
		return $filter['inMailbox'];
	}
	foreach ((array)($filter['conditions'] ?? []) as $condition)
	{
		if (($id = mail_jmap_find_in_mailbox((array)$condition)) !== null)
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
function mail_jmap_filter_to_query(array $filter, bool $negate=false) : \Horde_Imap_Client_Search_Query
{
	$query = new \Horde_Imap_Client_Search_Query();

	if (isset($filter['operator']))
	{
		if ($filter['operator'] === 'NOT')
		{
			return mail_jmap_filter_to_query((array)$filter['conditions'][0], !$negate);
		}
		$isAnd = $filter['operator'] === 'AND';
		$effectiveAnd = $negate ? !$isAnd : $isAnd;
		foreach ((array)$filter['conditions'] as $condition)
		{
			$sub = mail_jmap_filter_to_query((array)$condition, $negate);
			$effectiveAnd ? $query->andSearch($sub) : $query->orSearch($sub);
		}
		return $query;
	}

	foreach ($filter as $key => $value)
	{
		mail_jmap_apply_condition($query, (string)$key, $value, $negate);
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
function mail_jmap_apply_condition(\Horde_Imap_Client_Search_Query $query, string $key, $value, bool $not) : void
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
			$query->flag(mail_jmap_keyword_to_flag((string)$value), !$not);
			break;
		case 'notKeyword':
			$query->flag(mail_jmap_keyword_to_flag((string)$value), $not);
			break;
		case 'inMailbox':
			break;	// handled separately by mail_jmap_find_in_mailbox(), not a search criterion
	}
}

/**
 * @param string $keyword JMAP keyword, e.g. "$flagged"
 * @return string flag/keyword name as Horde_Imap_Client_Search_Query::flag() expects
 */
function mail_jmap_keyword_to_flag(string $keyword) : string
{
	return match ($keyword)
	{
		'$seen' => 'Seen',
		'$answered' => 'Answered',
		'$flagged' => 'Flagged',
		default => ltrim($keyword, '$'),
	};
}

/**
 * Translate MailJmap.buildSort()'s Comparator array into Horde's SORT_* option array
 *
 * @param array $sortSpec {property: string, isAscending: bool}[]
 * @return array
 */
function mail_jmap_build_sort(array $sortSpec) : array
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
 * Email/get: fetch exactly the properties MailJmap.getRows() requests for a batch of ids
 *
 * @param string $accountId
 * @param array $args {ids: string[]}
 * @param array &$context see mail_jmap_email_query()
 * @return array {accountId: string, list: array[], notFound: string[]}
 */
function mail_jmap_email_get(string $accountId, array $args, array &$context) : array
{
	$ids = array_map('strval', (array)($args['ids'] ?? []));
	// absent/empty "properties" means "all", per RFC 8621 - our own client always sends an
	// explicit list though, and only includes "preview" when the "Sneak preview in list"
	// toggle is on (mirrors mail_ui::get_rows()'s fetchPreview), to skip the extra IMAP work
	$properties = (array)($args['properties'] ?? []);
	$wantPreview = !$properties || in_array('preview', $properties, true);

	if ($accountId === '0')
	{
		return mail_jmap_demo_email_get($ids, $wantPreview);
	}
	if (!$ids)
	{
		return ['accountId' => $accountId, 'list' => [], 'notFound' => []];
	}
	$mailbox = $context['mailbox'][$accountId] ?? null;
	if ($mailbox === null)
	{
		throw new \Exception('Email/get without a preceding Email/query for the same accountId in this request');
	}
	$imap = mail_jmap_imap_server($accountId);

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
			$list[] = mail_jmap_email_from_fetch($imap, $mailbox, $id, $data, $wantPreview);
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
 * Map a single fetched message to a JMAP-shaped Email object (only the properties
 * MailJmap.getRows() requests - see the file docblock for the full scope note)
 *
 * @param \Horde_Imap_Client_Socket $imap
 * @param string $mailbox
 * @param string $uid
 * @param \Horde_Imap_Client_Data_Fetch $data
 * @param bool $wantPreview false skips the (extra IMAP round-trip) preview snippet entirely
 * @return array
 */
function mail_jmap_email_from_fetch(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid, \Horde_Imap_Client_Data_Fetch $data, bool $wantPreview = true) : array
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
		'keywords' => mail_jmap_flags_to_keywords($data->getFlags()),
		'size' => $data->getSize(),
		'receivedAt' => mail_jmap_imap_date($data->getImapDate()),
		'sentAt' => mail_jmap_imap_date($envelope->date),
		'subject' => (string)$envelope->subject,
		'preview' => $wantPreview ? mail_jmap_preview($imap, $mailbox, $uid, $structure, $data) : '',
		'from' => mail_jmap_address_list($envelope->from),
		'to' => mail_jmap_address_list($envelope->to),
		'cc' => mail_jmap_address_list($envelope->cc),
		'bcc' => mail_jmap_address_list($envelope->bcc),
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
function mail_jmap_preview(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid, ?\Horde_Mime_Part $structure, \Horde_Imap_Client_Data_Fetch $data) : string
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
				return mail_jmap_clean_preview((string)$partData->getBodyPart($bodyId));
			}
		}
		catch (\Throwable $e)
		{
			// fall through to the (possibly noisy) top-level body text below
		}
	}
	return mail_jmap_clean_preview((string)$data->getBodyText(0));
}

/**
 * Strip MIME header/boundary lines and collapse whitespace, then truncate
 *
 * @param string $text
 * @return string
 */
function mail_jmap_clean_preview(string $text) : string
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
function mail_jmap_address_list($list) : array
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
function mail_jmap_flags_to_keywords(array $flags) : array
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
function mail_jmap_imap_date(?\DateTime $date) : ?string
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
 * Static in-file fixture (2 mailboxes with mail, plus one always-empty one), used only for
 * accountId "0" - lets the client-side JMAP code be exercised/tested without a real mailbox.
 *
 * @return array {mailboxes: string[], emails: array<string, array>} keyed by (fake) UID
 */
function mail_jmap_demo_fixture() : array
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
		$when = mail_jmap_imap_date(new \DateTime('-'.$i.' hours', new \DateTimeZone('UTC')));
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
function mail_jmap_demo_email_query(string $folder, array $args, array &$context) : array
{
	unset($context);
	$folder = $folder ?: 'INBOX';
	// array_keys() would silently cast these numeric-string ids back to int (PHP array-key
	// coercion), so re-stringify to keep ids consistent with the real (non-demo) IMAP path
	$ids = array_map('strval', array_keys(array_filter(
		mail_jmap_demo_fixture()['emails'],
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
function mail_jmap_demo_email_get(array $ids, bool $wantPreview = true) : array
{
	$emails = mail_jmap_demo_fixture()['emails'];
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
