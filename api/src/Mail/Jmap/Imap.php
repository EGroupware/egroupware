<?php
/**
 * EGroupware Api: local JMAP server for plain IMAP accounts - Mail\Jmap\Imap session
 *
 * Formerly `EGroupware\Mail\JmapShim` (mail/src/JmapShim.php) - promoted into the
 * Api\Jmap/Api\Mail\Jmap class hierarchy (see doc/ai/projects/mail-jmap-imap-inversion.md)
 * as this app's IMAP-backed implementation of the generic JMAP session contract, alongside
 * `Api\Mail\Jmap\Http` (the real-JMAP-over-HTTP implementation, for Stalwart).
 *
 * Deliberately kept almost entirely as-is in this promotion (same static methods, same
 * bodies) rather than deeply restructured - this class is large, intricate (MIME/S-MIME/TNEF
 * handling, a real admin-impersonation security boundary via $calledFor, ...) and already
 * tested; a blind mechanical rewrite risked real regressions for uncertain benefit. What's new
 * here is a thin instance layer: a constructor holding $accountId/$calledFor and $types
 * (satisfying Api\Jmap's lazy per-type-object contract via Imap\Mailbox/Email/Thread/Quota,
 * see the Imap/ subdirectory) - dispatch() and every static method below are UNCHANGED and
 * keep working exactly as before for the existing browser-facing entrypoint (mail/jmap.php).
 *
 * mail/js/jmap.ts (MailJmap) already speaks JMAP client-side for Stalwart-backed
 * accounts, talking directly to Stalwart's real JMAP server. Plain IMAP accounts
 * (Dovecot, Cyrus, ...) have no JMAP server at all, so this class acts as one -
 * but only implements the handful of methods MailJmap actually sends
 * (Mailbox/query, Email/query, Email/get, Email/set, Thread/get, plus RFC 8620 §3.7
 * result-reference resolution (including the "*" list-flattening extension Thread/get ->
 * Email/get chaining needs) for its batched request), backed directly by
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
 * @package api
 * @subpackage mail
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api;
use EGroupware\Api\Jmap;
use EGroupware\Api\Mail\Account;
use EGroupware\Api\Mail\CustomLabels;

class Imap extends Jmap\Base
{
	/**
	 * type-name => concrete per-type class, satisfying Api\Jmap's lazy accessor contract
	 * (eg. $session->mailbox) - see Imap/Mailbox.php etc, each a thin adapter delegating back
	 * to this class's own (unchanged) static methods.
	 *
	 * @var array<string,class-string<Jmap\Type>>
	 */
	protected array $types = [
		'mailbox' => Imap\Mailbox::class,
		'email' => Imap\Email::class,
		'thread' => Imap\Thread::class,
		'quota' => Imap\Quota::class,
	];

	/**
	 * @param string $accountId
	 * @param string|null $calledFor account_id of the mailbox owner to impersonate (admin only,
	 *  see mailboxSet()'s docblock's SECURITY note) - null for the caller's own mailbox
	 */
	public function __construct(protected string $accountId, protected ?string $calledFor=null)
	{
	}

	/**
	 * Request-scoped state shared between this session's per-type objects (which mailbox a
	 * preceding Email/query's ids came from, thread maps, ...) - same $context dispatch()
	 * already threads between calls in one batch, now shared via the owning session instead so
	 * eg. Imap\Thread::get() can see a mailbox Imap\Email::query() remembered, matching
	 * threadGet()'s documented fallback (see its own docblock).
	 *
	 * @var array
	 */
	public array $context = [];

	/**
	 * @return mixed accountId (matching Api\Jmap's own readonly property, so per-type
	 *  classes can treat both session flavours uniformly) or calledFor
	 */
	public function __get(string $name)
	{
		return match ($name) {
			'accountId' => $this->accountId,
			'calledFor' => $this->calledFor,
			default => parent::__get($name),
		};
	}

	/**
	 * JMAP session-discovery object, fetched once by jmap-jam's JamClient on construction
	 *
	 * Only 'apiUrl'/'downloadUrl'/'uploadUrl' are actually used by our own shipped code (verified
	 * against jmap-jam's bundled source and MailJmap's own accountId resolution, which goes through
	 * ajax_jmapBootstrap()'s response, not session parsing) - 'accounts'/'primaryAccounts' are
	 * populated correctly too (see $accountId below) for spec-completeness/any other JMAP-generic
	 * code that might call jmap-jam's own getPrimaryAccount(), but nothing shipped depends on them.
	 *
	 * @return array
	 */
	public static function session() : array
	{
		$url = Api\Framework::getUrl(Api\Framework::link('/mail/jmap.php'));

		// accountId comes from ProfileHandler::localBootstrap()'s sessionUrl (mail/src/Ui/ProfileHandler.php) - session()
		// has no other way to know which account a given JamClient instance belongs to, since it's
		// otherwise a shared, generic endpoint. Not present when called directly/without a bootstrap
		// (e.g. the demo fixture) - "accounts"/"primaryAccounts" then stay empty, matching before;
		// nothing shipped relies on them (the app resolves its own accountId via
		// ajax_jmapBootstrap()'s response, not jmap-jam's session parsing).
		$accountId = (string)($_GET['accountId'] ?? '');
		$accounts = $primaryAccounts = new \stdClass();
		if ($accountId !== '')
		{
			$accounts = [$accountId => [
				'name' => (string)($GLOBALS['egw_info']['user']['account_lid'] ?? ''),
				'isPersonal' => true,
				'isReadOnly' => false,
				'accountCapabilities' => [
					'urn:ietf:params:jmap:mail' => new \stdClass(),
				],
			]];
			$primaryAccounts = ['urn:ietf:params:jmap:mail' => $accountId];
		}

		return [
			'capabilities' => [
				Http::JMAP_CORE => new \stdClass(),
				'urn:ietf:params:jmap:mail' => new \stdClass(),
				Http::JMAP_QUOTA => new \stdClass(),
			],
			'accounts' => $accounts,
			'primaryAccounts' => $primaryAccounts,
			'username' => (string)($GLOBALS['egw_info']['user']['account_lid'] ?? ''),
			'apiUrl' => $url,
			// jmap-jam's downloadBlob() substitutes these 4 placeholders verbatim (no URL-encoding
			// of the substituted values - see urlsafeB64Encode()'s docblock) and does a plain GET,
			// handled by mail/jmap.php's "download" branch -> JmapShim::download()
			'downloadUrl' => $url.'?download=1&accountId={accountId}&blobId={blobId}&type={type}&name={name}',
			// RFC 8620 §6.1 requires the {accountId} template - jmap-jam's uploadBlob() substitutes
			// it before POSTing the raw bytes, handled by mail/jmap.php's "upload" branch ->
			// JmapShim::upload()
			'uploadUrl' => $url.'?upload=1&accountId={accountId}',
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
			// the client already gave up on this whole batch (eg. closed the preview before a
			// slower earlier call in it finished) - no point doing further IMAP work for calls
			// whose result will never be read
			if (connection_aborted()) exit;

			[$method, $args, $callId] = ((array)$call) + [null, [], null];
			try
			{
				$args = self::resolveRefs((array)$args, $responses);
				$accountId = (string)($args['accountId'] ?? '0');

				switch ($method)
				{
					case 'Mailbox/query':
						$result = self::mailboxQuery($accountId, $args);
						break;
					case 'Mailbox/get':
						// $calledFor deliberately omitted, same reasoning as Mailbox/set below
						$result = self::mailboxGet($accountId, $args);
						break;
					case 'Mailbox/set':
						// $calledFor deliberately omitted (stays null = caller's own mailbox) -
						// see mailboxSet()'s docblock: this client-facing dispatch() must never
						// be how an admin-impersonated connection gets reached
						$result = self::mailboxSet($accountId, $args);
						break;
					case 'Email/query':
						$result = self::emailQuery($accountId, $args, $context);
						break;
					case 'Email/get':
						$result = self::emailGet($accountId, $args, $context);
						break;
					case 'Thread/get':
						$result = self::threadGet($accountId, $args, $context);
						break;
					case 'Email/set':
						$result = self::emailSet($accountId, $args);
						break;
					case 'Email/import':
						$result = self::emailImport($accountId, $args);
						break;
					case 'Quota/get':
						$result = self::quotaGet($accountId, $args);
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
				if ($response[2] !== $ref['resultOf'])
				{
					continue;
				}
				if ($response[0] === $ref['name'])
				{
					$args[$name] = self::jsonPath($response[1], $ref['path']);
					continue 2;
				}
				// the referenced call itself failed (recorded as an "error" response, so its
				// method name never matches $ref['name']) - propagate its real error instead of
				// the generic, misleading "failed to resolve reference" message below
				if ($response[0] === 'error')
				{
					throw new \Exception($response[1]['description'] ?? $response[1]['type'] ?? "referenced call '{$ref['resultOf']}' failed");
				}
			}
			throw new \Exception("Failed to resolve result reference for '$name'");
		}
		return $args;
	}

	/**
	 * Minimal JSON-pointer-ish path lookup, e.g. "/ids" -> $value['ids']
	 *
	 * RFC 8620 §3.7 result references also allow a "*" path segment: "if the result of the
	 * previous path is a list, apply the following path to each item, and concatenate all the
	 * resulting lists into a single list". Needed for Thread/get -> Email/get chaining
	 * ("/list/*\/emailIds": flatten every returned thread's emailIds into one combined id list) -
	 * doc/ai/projects/mail-threaded-view.md, Phase 2. A plain (no "*") path behaves exactly as
	 * before.
	 *
	 * @param array $value
	 * @param string $path
	 * @return mixed|null
	 */
	public static function jsonPath(array $value, string $path)
	{
		return self::jsonPathParts($value, explode('/', ltrim($path, '/')));
	}

	/**
	 * @param mixed $value
	 * @param string[] $parts remaining path segments
	 * @return mixed|null
	 */
	private static function jsonPathParts($value, array $parts)
	{
		if (!$parts)
		{
			return $value;
		}
		$part = array_shift($parts);
		if ($part === '*')
		{
			if (!is_array($value))
			{
				return null;
			}
			$result = [];
			foreach ($value as $item)
			{
				$resolved = self::jsonPathParts($item, $parts);
				if (is_array($resolved))
				{
					// the referenced field is itself a list (e.g. Thread.emailIds) - concatenate,
					// don't nest, per RFC 8620 §3.7's "list-of-lists ... concatenated into a
					// single list"
					array_push($result, ...array_values($resolved));
				}
				elseif ($resolved !== null)
				{
					$result[] = $resolved;
				}
			}
			return $result;
		}
		if (!is_array($value) || !isset($value[$part]))
		{
			return null;
		}
		return self::jsonPathParts($value[$part], $parts);
	}

	/**
	 * Mailbox/query - two modes, matching real JMAP's own MailboxFilterCondition semantics
	 * (parentId and name are independent, combinable filter keys):
	 *
	 * - filter.name given (optionally with parentId): resolve a single known path-segment to a
	 *   folder id. Id is just base64(EGroupware-canonical "/"-joined folder path) - a pure
	 *   encoding, not a lookup, so no IMAP round-trip is needed. Existence is implicitly
	 *   verified later, when Email/query actually searches that mailbox. This is the mode
	 *   MailJmap.mailboxId() (mail/js/jmap.ts) uses for per-segment path resolution - must stay
	 *   exactly as cheap as before, no regression.
	 * - filter.name absent (only parentId, or neither for the top level): list every direct
	 *   child of that parent - a real one-level Horde listMailboxes() LIST call, the lazy
	 *   per-level folder-tree loading primitive (see doc/ai/projects/mail-folder-tree-jmap.md).
	 *   Requesting the 'children' option lets mailboxGet() report hasChildren cheaply from the
	 *   same attributes, mirroring mail_tree.inc.php's own nodeHasChildren(). filter.isSubscribed
	 *   (RFC 8621 MailboxFilterCondition) mirrors classic mail_ui's own default: normal browsing
	 *   only lists subscribed mailboxes unless the "show all folders" preference is on (see
	 *   MailJmap.getMailboxChildren(), mail/js/jmap.ts, which sets this from that preference) -
	 *   without it, MBOX_ALL_SUBSCRIBED below returns literally everything regardless of
	 *   subscription (a confusingly-named Horde constant - "ALL" is the operative word, it does
	 *   NOT mean "only subscribed"), flooding the tree with stale/unsubscribed mailboxes classic
	 *   never showed by default.
	 *
	 * @param string $accountId
	 * @param array $args {filter?: {name?: string, parentId?: string, isSubscribed?: bool}}
	 * @return array {ids: string[]}
	 */
	public static function mailboxQuery(string $accountId, array $args) : array
	{
		$name = (string)($args['filter']['name'] ?? '');
		$parentPath = !empty($args['filter']['parentId']) ? self::folderPath($args['filter']['parentId']) : '';

		if ($name !== '')
		{
			$path = $parentPath !== '' ? $parentPath.'/'.$name : $name;
			return ['ids' => [base64_encode($path)]];
		}

		if ($accountId === '0' || !($imap = self::imapServer($accountId)))
		{
			return ['ids' => []];
		}
		$subscribedOnly = array_key_exists('isSubscribed', (array)($args['filter'] ?? [])) &&
			(bool)$args['filter']['isSubscribed'];
		return ['ids' => self::listChildIds($imap, $parentPath, $subscribedOnly)];
	}

	/**
	 * Quota/get (RFC 9425) for the local plain-IMAP shim - wraps the same classic IMAP QUOTA
	 * extension lookup Api\Mail::getQuotaRoot() already uses, so a plain-IMAP account exposes
	 * quota via JMAP too instead of the client needing a classic ajax_refreshQuotaDisplay()
	 * fallback: for a shim account, that fallback would just run the exact same IMAP QUOTA
	 * lookup anyway, one layer further down - there's nothing to be gained by declining here.
	 *
	 * @param string $accountId
	 * @param array $args {ids?: ?string[]}
	 * @return array {accountId, state, list: array[], notFound: string[]}
	 */
	public static function quotaGet(string $accountId, array $args) : array
	{
		$ids = $args['ids'] ?? null;
		$list = ($accountId !== '0' && ($imap = self::imapServer($accountId))) ? self::quotaFromImap($imap) : [];
		if (is_array($ids))
		{
			$list = array_values(array_filter($list, static fn($q) => in_array($q['id'], $ids)));
		}
		return [
			'accountId' => $accountId,
			'state' => '0',
			'list' => $list,
			'notFound' => is_array($ids) ? array_values(array_diff($ids, array_column($list, 'id'))) : [],
		];
	}

	/**
	 * The IMAP side of quotaGet(), split out so it can be exercised directly (via ReflectionMethod)
	 * against a mocked connection in tests, same pattern listChildIds() already uses.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @return array[] empty if the server has no QUOTA capability or no quota root on INBOX
	 */
	private static function quotaFromImap(\Horde_Imap_Client_Socket $imap) : array
	{
		// Many IMAP servers (Dovecot included) advertise a smaller, pre-authentication
		// capability set than the real, post-login one - QUOTA is commonly post-auth-only
		// (RFC 2087, per-user data), so checking hasCapability() before ensuring the
		// connection is actually logged in sees the wrong (pre-auth) list and silently
		// concludes QUOTA isn't supported even when it is. login() is safe/idempotent if the
		// connection is already authenticated (confirmed live 2026-08-26: acc_id=42/90, both
		// real Dovecot QUOTA-supporting accounts, showed hasCapability(QUOTA)=false without this).
		$imap->login();

		if (!$imap->hasCapability('QUOTA'))
		{
			return [];
		}
		$quota = $imap->getStorageQuotaRoot('INBOX');
		if (!is_array($quota) || !isset($quota['QMAX']))
		{
			return [];
		}
		return [[
			'id' => 'mail',
			'resourceType' => 'octets',
			'used' => (int)$quota['USED'] * 1024,
			'hardLimit' => (int)$quota['QMAX'] * 1024,
			'scope' => 'account',
			'name' => 'Mail',
			'types' => ['Mailbox', 'Email'],
		]];
	}

	/**
	 * The IMAP side of mailboxQuery()'s "list children" mode, split out so it can be exercised
	 * directly (via ReflectionMethod) against a mocked connection in tests, same pattern
	 * mailboxCreate()/mailboxUpdate()/mailboxDestroy() already use.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $parentPath canonical "/"-joined path, '' for the top level
	 * @param bool $subscribedOnly see mailboxQuery()'s docblock
	 * @return string[] base64-encoded canonical paths of every direct child
	 */
	private static function listChildIds(\Horde_Imap_Client_Socket $imap, string $parentPath, bool $subscribedOnly = false) : array
	{
		$parentMailbox = self::hordeMailbox($imap, $parentPath);
		$delimiter = self::namespaceDelimiter($imap, 'personal');
		// IMAP '%' matches any characters except the hierarchy delimiter - i.e. exactly one
		// level, never grandchildren, and never the parent itself (which needs at least one
		// more character after the delimiter to match)
		$pattern = $parentPath === '' ? '%' : $parentMailbox.$delimiter.'%';

		$mailboxes = $subscribedOnly ?
			$imap->listMailboxes($pattern, \Horde_Imap_Client::MBOX_SUBSCRIBED, ['children' => true]) : null;
		// same defensive fallback as Api\Mail\Imap::getMailboxes()'s own "cyrus workaround": some
		// accounts/servers never report ANY mailbox (not even INBOX) as subscribed at all - rather
		// than show a folder level that's completely empty (including the account's OWN top
		// level, which classic never did), fall back to the unfiltered listing for this request
		if ($mailboxes === null || empty($mailboxes))
		{
			$mailboxes = $imap->listMailboxes($pattern, \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, ['children' => true]);
		}
		elseif ($subscribedOnly && $parentPath === '')
		{
			$mailboxes += self::namespaceRootsMissingFrom($imap, $mailboxes);
		}

		$ids = [];
		foreach ($mailboxes as $mailboxName => $info)
		{
			$ids[] = base64_encode(self::canonicalPath($imap, $mailboxName));
		}
		return $ids;
	}

	/**
	 * Namespace roots ("user"/"shared" style Other-Users/Shared containers) are structural
	 * navigation doorways, not individually-subscribable mailboxes in the normal IMAP sense -
	 * classic mail_tree.inc.php's own namespace handling (setOutStructure()) always shows them
	 * regardless of subscription state. filter.isSubscribed's strict MBOX_SUBSCRIBED mode would
	 * otherwise hide the only way into "Other Users"/"Shared Folders" entirely, since the
	 * namespace root itself is essentially never individually \Subscribed.
	 *
	 * Matches by the conventional literal names ("user"/"shared", same as jmap.ts's own
	 * sortTopLevel()'s isNamespaceRoot check) rather than $imap->getNameSpaceArray()'s reported
	 * NAMESPACE-extension prefixes: a real account hit exactly this gap - IMAP NAMESPACE either
	 * wasn't advertised or wasn't reported as an "others" entry for that server, even though
	 * "user" was a perfectly real, browsable mailbox with real accessible children underneath it.
	 *
	 * Only included when the namespace actually has at least one accessible child (some other
	 * user's folder shared with this one via IMAP ACL) - classic suppresses an empty namespace
	 * root the same way, since an always-visible-but-empty "user"/"shared" entry is a confusing
	 * dead end for the (much more common) case of a user with nothing granted to them at all.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param array $mailboxes already-found top-level mailboxes, keyed by real IMAP name
	 * @return array additional {mailboxName: info} entries for any missing, non-empty namespace root
	 */
	private static function namespaceRootsMissingFrom(\Horde_Imap_Client_Socket $imap, array $mailboxes) : array
	{
		$missing = [];
		$delimiter = self::namespaceDelimiter($imap, 'others');
		foreach (['user', 'shared'] as $name)
		{
			if (isset($mailboxes[$name]))
			{
				continue;
			}
			// MBOX_SUBSCRIBED (not MBOX_ALL_SUBSCRIBED - see this class's own listMailboxes() docs a
			// few lines up) - this whole function only ever runs for a subscribedOnly request (see
			// listChildIds()'s calling `elseif`), so "granted" here must mean "granted AND
			// subscribed", or an always-visible root would be a dead end whenever something is
			// shared with this user but they haven't subscribed to any of it yet (ralf's report) -
			// still findable via the subscription dialog, which never calls with subscribedOnly true.
			$hasGrantedChildren = $imap->listMailboxes($name.$delimiter.'%', \Horde_Imap_Client::MBOX_SUBSCRIBED, []);
			if (empty($hasGrantedChildren))
			{
				continue;
			}
			$info = $imap->listMailboxes($name, \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, ['children' => true]);
			if (!empty($info))
			{
				$missing += $info;
			}
		}
		return $missing;
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
	 * Translate a real IMAP mailbox name to the EGroupware-canonical "/"-joined folder path -
	 * the reverse of hordeMailbox(), needed to build id/parentId for mailboxGet()'s results.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailboxName
	 * @return string
	 */
	public static function canonicalPath(\Horde_Imap_Client_Socket $imap, string $mailboxName) : string
	{
		if (strtoupper($mailboxName) === 'INBOX')
		{
			return 'INBOX';
		}
		$delimiter = self::namespaceDelimiter($imap, 'personal');
		return $delimiter === '/' ? $mailboxName : str_replace($delimiter, '/', $mailboxName);
	}

	/**
	 * Resolve a mailbox's JMAP role from IMAP SPECIAL-USE attributes, falling back to the
	 * account's own configured special-folder names when the server doesn't support
	 * SPECIAL-USE (Horde silently drops the 'special_use' listMailboxes() option in that case -
	 * see Base.php's createMailbox()/listMailboxes() - so live attributes alone aren't reliable
	 * across all servers). Same information Api\Mail::getSpecialUseFolders() uses classically,
	 * reached directly off $imap (acc_folder_*) rather than through Api\Mail/mail_bo, to keep
	 * this class's "never goes through mail_ui/Api\Mail" discipline.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailboxName real IMAP mailbox name
	 * @param string[] $attributes lower-cased LIST attributes for this mailbox
	 * @return string|null one of inbox/trash/sent/drafts/junk/archive (real RFC 8621 MailboxRole
	 *  values) plus the EGroupware-specific extensions templates/outbox (classic mail_tree.inc.php's
	 *  own $definedFolders concept - no IMAP SPECIAL-USE or JMAP role exists for either, only the
	 *  account's own acc_folder_template/acc_folder_outbox config), or null
	 */
	public static function roleFor(\Horde_Imap_Client_Socket $imap, string $mailboxName, array $attributes) : ?string
	{
		if (strtoupper($mailboxName) === 'INBOX')
		{
			return 'inbox';
		}
		static $specialUse = [
			'\\trash' => 'trash', '\\sent' => 'sent', '\\drafts' => 'drafts',
			'\\junk' => 'junk', '\\archive' => 'archive',
		];
		foreach ($attributes as $attribute)
		{
			if (isset($specialUse[strtolower($attribute)]))
			{
				return $specialUse[strtolower($attribute)];
			}
		}
		static $accFolders = [
			'acc_folder_trash' => 'trash', 'acc_folder_sent' => 'sent', 'acc_folder_draft' => 'drafts',
			'acc_folder_junk' => 'junk', 'acc_folder_archive' => 'archive',
			'acc_folder_template' => 'templates', 'acc_folder_outbox' => 'outbox',
		];
		foreach ($accFolders as $property => $role)
		{
			// read into a local var first - Mail\Imap has no __isset(), so empty()/isset()
			// directly on the magic property would never even call __get() and always report
			// "not set", silently defeating this whole fallback
			$folderName = $imap->$property;
			if (!empty($folderName) && strcasecmp($folderName, $mailboxName) === 0)
			{
				return $role;
			}
		}
		return null;
	}

	/**
	 * Mailbox/get (RFC 8621 §2.6) for the local plain-IMAP shim - full node data for a set of
	 * mailboxes, typically the ids a preceding Mailbox/query just listed (see mailboxQuery()'s
	 * "list children" mode) - the lazy per-level folder-tree loading pair, batched together the
	 * same way MailJmap.getRows() already batches Email/query+Email/get via a result-reference
	 * (see MailJmap.getMailboxChildren(), mail/js/jmap.ts).
	 *
	 * SECURITY: see mailboxSet()'s docblock - $calledFor is never derived from client-supplied
	 * $args, and dispatch()'s own 'Mailbox/get' case never passes it.
	 *
	 * @param string $accountId
	 * @param array $args {ids?: string[]|null} null (or omitted) means "all mailboxes" (RFC 8620
	 *  Get semantics) - supported for completeness, though the lazy per-level path above always
	 *  passes explicit ids from a preceding query.
	 * @param string|null $calledFor account_id of the mailbox owner to impersonate (admin only,
	 *  see mailboxSet()), or null for the caller's own mailbox
	 * @return array {list: array[], notFound: string[]}
	 */
	public static function mailboxGet(string $accountId, array $args, ?string $calledFor = null) : array
	{
		if ($accountId === '0' || !($imap = self::imapServer($accountId, $calledFor)))
		{
			return ['list' => [], 'notFound' => array_values((array)($args['ids'] ?? []))];
		}
		$requestedIds = array_key_exists('ids', $args) ? $args['ids'] : null;
		return self::mailboxGetInternal($imap, $requestedIds, $calledFor);
	}

	/**
	 * The IMAP side of mailboxGet(), split out so it can be exercised directly (via
	 * ReflectionMethod) against a mocked connection in tests, same pattern
	 * mailboxCreate()/mailboxUpdate()/mailboxDestroy() already use.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string[]|null $requestedIds null = every mailbox (RFC 8620 "ids: null")
	 * @param string|null $calledFor see mailboxGet()
	 * @return array {list: array[], notFound: string[]}
	 */
	private static function mailboxGetInternal(\Horde_Imap_Client_Socket $imap, ?array $requestedIds, ?string $calledFor) : array
	{
		if ($requestedIds === null)
		{
			// RFC 8620 "ids: null" = all - a full-account scan is the correct/only way to
			// answer this, unlike the explicit-ids case below. 'status' batches message/unseen
			// counts into this same LIST call (same fix as the explicit-ids branch below) -
			// without it, an account with hundreds of folders (the exact case this whole-account
			// mode exists for, see the subscribe-management popup) would need hundreds of
			// separate STATUS round-trips just to render the popup.
			$list = [];
			foreach ($imap->listMailboxes('*', \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, [
				'attributes' => true, 'special_use' => true, 'children' => true,
				'status' => \Horde_Imap_Client::STATUS_MESSAGES | \Horde_Imap_Client::STATUS_UNSEEN,
			]) as $mailboxName => $info)
			{
				$list[] = self::mailboxNode($imap, $mailboxName, (array)($info['attributes'] ?? []), $info['status'] ?? null);
			}
			return ['list' => $list, 'notFound' => []];
		}

		// explicit ids (the lazy per-level path's normal case): look up every requested mailbox
		// in ONE batched LIST(+STATUS, via Horde's LIST-STATUS support) call - critically, NOT a
		// '*' full-account scan, which would defeat the whole point of lazy per-level loading for
		// accounts with hundreds of folders. Previously this looped one listMailboxes() +
		// mailboxNode()'s own separate status() call PER id - up to 2 sequential IMAP round-trips
		// for every single mailbox, which for a level with dozens of siblings could take many
		// seconds and made the whole request likely to time out or exceed the client's own
		// timeout, causing a SILENT fallback to the classic ajax_foldertree path with no visible
		// error at all (see doc/ai/projects/mail-folder-tree-jmap.md).
		$mailboxNames = [];
		$idByName = [];
		foreach ($requestedIds as $id)
		{
			$mailboxName = self::hordeMailbox($imap, self::folderPath((string)$id), $calledFor);
			$mailboxNames[] = $mailboxName;
			$idByName[$mailboxName] = (string)$id;
		}
		$infos = $mailboxNames ? $imap->listMailboxes($mailboxNames, \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, [
			'attributes' => true, 'special_use' => true, 'children' => true,
			'status' => \Horde_Imap_Client::STATUS_MESSAGES | \Horde_Imap_Client::STATUS_UNSEEN,
		]) : [];

		$list = [];
		$notFound = [];
		foreach ($mailboxNames as $mailboxName)
		{
			if (empty($infos[$mailboxName]))
			{
				$notFound[] = $idByName[$mailboxName];
				continue;
			}
			$list[] = self::mailboxNode($imap, $mailboxName, (array)($infos[$mailboxName]['attributes'] ?? []),
				$infos[$mailboxName]['status'] ?? null);
		}
		return ['list' => $list, 'notFound' => $notFound];
	}

	/**
	 * Build one Mailbox/get result entry - shared by both mailboxGet() modes
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailboxName real IMAP mailbox name
	 * @param string[] $rawAttributes LIST attributes as returned by Horde (mixed case)
	 * @param array|null $status pre-fetched {messages, unseen} (from listMailboxes()'s own
	 *  'status' option, see mailboxGetInternal()'s explicit-ids batch) - avoids a separate STATUS
	 *  round-trip per mailbox; null means "fetch it here" (the ids:null full-scan mode, which
	 *  doesn't request 'status' on its own listMailboxes() call)
	 * @return array
	 */
	private static function mailboxNode(\Horde_Imap_Client_Socket $imap, string $mailboxName, array $rawAttributes, ?array $status = null) : array
	{
		$path = self::canonicalPath($imap, $mailboxName);
		[$parentPath, $leafName] = self::splitPath($path);
		$attributes = array_map('strtolower', $rawAttributes);

		$counts = ['messages' => 0, 'unseen' => 0];
		if ($status === null)
		{
			try
			{
				$status = $imap->status($mailboxName, \Horde_Imap_Client::STATUS_MESSAGES | \Horde_Imap_Client::STATUS_UNSEEN);
			}
			catch (\Throwable $e)
			{
				// \Noselect namespace-separator mailboxes (and similar) can throw on STATUS -
				// leave the zero-defaults rather than failing the whole mailboxGet() call
				$status = [];
			}
		}
		$counts['messages'] = (int)($status['messages'] ?? 0);
		$counts['unseen'] = (int)($status['unseen'] ?? 0);

		return [
			'id' => base64_encode($path),
			'name' => $path === 'INBOX' ? 'INBOX' : $leafName,
			'parentId' => $parentPath !== '' ? base64_encode($parentPath) : null,
			'sortOrder' => 0,
			'isSubscribed' => in_array('\\subscribed', $attributes, true),
			'totalEmails' => $counts['messages'],
			'unreadEmails' => $counts['unseen'],
			'role' => self::roleFor($imap, $mailboxName, $attributes),
			'hasChildren' => in_array('\\haschildren', $attributes, true) ? true :
				(in_array('\\hasnochildren', $attributes, true) ? false : true),
			// classic mail_tree.inc.php's own "Set Acl capability for INBOX" - only ever checked
			// there, since ACL editing is an account-level feature, not a per-folder one; a live
			// IMAP connection to this account is already open by the time any of its mailboxes are
			// fetched, so queryCapability() (an in-memory lookup against the already-fetched
			// CAPABILITY response) costs nothing extra here - see mail/js/app.ts's aclEnabled()
			// (reads it back via folderTree.ts's buildNode(), as node.data.acl) and
			// MailJmap.resolveAclCapable() for the real-JMAP/Stalwart equivalent.
			'aclCapable' => $path === 'INBOX' && $imap->queryCapability('ACL'),
		];
	}

	/**
	 * Mailbox/set (RFC 8621 §2.5): create/rename/move/(un)subscribe/delete real IMAP mailboxes
	 * for the local-shim path, backed directly by Horde's createMailbox()/renameMailbox()/
	 * deleteMailbox()/subscribeMailbox() - the same primitives Api\Mail::createFolder()/
	 * renameFolder()/deleteFolder() use classically (see doc/ai/projects/mail-folder-tree-jmap.md).
	 *
	 * Unlike emailSet() (which relies on dispatch()'s outer try/catch for the whole call), every
	 * create/update/destroy entry here is individually try/caught into its own notCreated/
	 * notUpdated/notDestroyed SetError - folder operations routinely fail per-item ("already
	 * exists", "not empty", permission denied) in ways a batch of otherwise-independent folder
	 * edits shouldn't all abort for.
	 *
	 * SECURITY: $calledFor is how an admin-impersonated connection (managing another user's
	 * mailbox) reaches this method - it is NEVER read from client-supplied $args, and
	 * dispatch()'s own 'Mailbox/set' case NEVER passes it. Per
	 * doc/ai/projects/mail-folder-tree-jmap.md's hard constraint, the ordinary client-facing
	 * JMAP endpoint (mail/jmap.php -> dispatch()) must never let a browser session act as anyone
	 * but the logged-in user. $calledFor exists solely for a *trusted server-side PHP caller* to
	 * call this method directly, after running its own admin-permission check - mirroring
	 * mail_acl::_require_admin_permission()'s existing gate for classic ACL editing. This isn't
	 * a hypothetical caller shape: admin >> Manage users >> (edit a mail account) already reaches
	 * mail_acl.inc.php exactly this way today, for ACL editing specifically - see
	 * mail_hooks::emailadmin_edit()'s 'mail_acl' action, a hook-registered toolbar button linking
	 * to menuaction mail.mail_acl.edit with an explicit acc_id+account_id (the same hook also
	 * wires up 'mail_vacation' -> mail_sieve.editVacation the same way). If a folder-CRUD admin
	 * screen is ever built, that same hook + $_GET['account_id'] + _require_admin_permission()
	 * wiring is the template to follow - mail_acl.inc.php itself just doesn't do folder CRUD
	 * (create/rename/delete/subscribe), only ACL grants, so no caller reaches this method's
	 * $calledFor branch yet. Never wire $calledFor to anything reachable from raw HTTP request
	 * data.
	 *
	 * @param string $accountId
	 * @param array $args {create?: {creationId: {name, parentId?, isSubscribed?}},
	 *  update?: {mailboxId: {name?, parentId?, isSubscribed?}}, destroy?: [mailboxId],
	 *  onDestroyRemoveEmails?: bool}
	 * @param string|null $calledFor account_id of the mailbox owner to impersonate (admin only,
	 *  see SECURITY above), or null for the caller's own mailbox
	 * @return array RFC 8620 §5.3 Set response shape
	 */
	public static function mailboxSet(string $accountId, array $args, ?string $calledFor = null) : array
	{
		$created = [];
		$notCreated = [];
		$updated = [];
		$notUpdated = [];
		$destroyed = [];
		$notDestroyed = [];
		$imap = $accountId !== '0' ? self::imapServer($accountId, $calledFor) : null;

		foreach ((array)($args['create'] ?? []) as $creationId => $props)
		{
			$creationId = (string)$creationId;
			if (!$imap)
			{
				$notCreated[$creationId] = ['type' => 'forbidden', 'description' => 'No mailbox connection'];
				continue;
			}
			try
			{
				$created[$creationId] = self::mailboxCreate($imap, (array)$props, $calledFor);
			}
			catch (\Throwable $e)
			{
				$notCreated[$creationId] = ['type' => 'invalidProperties', 'description' => $e->getMessage()];
			}
		}

		foreach ((array)($args['update'] ?? []) as $id => $patch)
		{
			$id = (string)$id;
			if (!$imap)
			{
				$notUpdated[$id] = ['type' => 'forbidden', 'description' => 'No mailbox connection'];
				continue;
			}
			try
			{
				self::mailboxUpdate($imap, $id, (array)$patch, $calledFor);
				$updated[$id] = null;
			}
			catch (\Throwable $e)
			{
				$notUpdated[$id] = ['type' => 'notFound', 'description' => $e->getMessage()];
			}
		}

		$removeEmails = !empty($args['onDestroyRemoveEmails']);
		foreach ((array)($args['destroy'] ?? []) as $id)
		{
			$id = (string)$id;
			if (!$imap)
			{
				$notDestroyed[$id] = ['type' => 'forbidden', 'description' => 'No mailbox connection'];
				continue;
			}
			try
			{
				self::mailboxDestroy($imap, $id, $removeEmails, $calledFor);
				$destroyed[] = $id;
			}
			catch (\Throwable $e)
			{
				$notDestroyed[$id] = [
					'type' => $e->getMessage() === 'mailboxHasEmail' ? 'mailboxHasEmail' : 'notFound',
					'description' => $e->getMessage(),
				];
			}
		}

		return [
			'accountId' => $accountId,
			'oldState' => '0',
			'newState' => '0',
			'created' => (object)$created,
			'notCreated' => (object)$notCreated,
			'updated' => (object)$updated,
			'notUpdated' => (object)$notUpdated,
			'destroyed' => $destroyed,
			'notDestroyed' => (object)$notDestroyed,
		];
	}

	/**
	 * Split a canonical "/"-joined folder path into its parent path and leaf name
	 *
	 * @param string $path
	 * @return array{0: string, 1: string} [$parentPath, $leafName]
	 */
	public static function splitPath(string $path) : array
	{
		$pos = strrpos($path, '/');
		return $pos === false ? ['', $path] : [substr($path, 0, $pos), substr($path, $pos + 1)];
	}

	/**
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param array $props {name: string, parentId?: string, isSubscribed?: bool}
	 * @param string|null $calledFor see mailboxSet()
	 * @return array {id: string}
	 */
	private static function mailboxCreate(\Horde_Imap_Client_Socket $imap, array $props, ?string $calledFor) : array
	{
		$name = (string)($props['name'] ?? '');
		if ($name === '')
		{
			throw new \InvalidArgumentException("'name' is required");
		}
		$parentPath = !empty($props['parentId']) ? self::folderPath((string)$props['parentId']) : '';
		$path = $parentPath !== '' ? $parentPath.'/'.$name : $name;
		$mailbox = self::hordeMailbox($imap, $path, $calledFor);

		$imap->createMailbox($mailbox);
		// default to subscribed, matching Api\Mail::createFolder()'s classic behaviour, unless
		// the client explicitly asked otherwise
		$imap->subscribeMailbox($mailbox, !array_key_exists('isSubscribed', $props) || (bool)$props['isSubscribed']);

		return ['id' => base64_encode($path)];
	}

	/**
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $id base64-encoded folder path
	 * @param array $patch {name?: string, parentId?: string|null, isSubscribed?: bool}
	 * @param string|null $calledFor see mailboxSet()
	 */
	private static function mailboxUpdate(\Horde_Imap_Client_Socket $imap, string $id, array $patch, ?string $calledFor) : void
	{
		if (($unknown = array_diff(array_keys($patch), ['name', 'parentId', 'isSubscribed'])))
		{
			throw new \InvalidArgumentException('Unsupported propert'.(count($unknown) > 1 ? 'ies' : 'y').': '.implode(', ', $unknown));
		}
		$path = self::folderPath($id);
		if ($path === '')
		{
			throw new \InvalidArgumentException('Cannot update the mailbox root');
		}
		[$parentPath, $leafName] = self::splitPath($path);

		$newParentPath = array_key_exists('parentId', $patch)
			? ($patch['parentId'] !== null ? self::folderPath((string)$patch['parentId']) : '')
			: $parentPath;
		$newLeafName = array_key_exists('name', $patch) ? (string)$patch['name'] : $leafName;
		$newPath = $newParentPath !== '' ? $newParentPath.'/'.$newLeafName : $newLeafName;

		if ($newPath !== $path)
		{
			$imap->renameMailbox(self::hordeMailbox($imap, $path, $calledFor), self::hordeMailbox($imap, $newPath, $calledFor));
		}
		if (array_key_exists('isSubscribed', $patch))
		{
			$imap->subscribeMailbox(self::hordeMailbox($imap, $newPath, $calledFor), (bool)$patch['isSubscribed']);
		}
	}

	/**
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $id base64-encoded folder path
	 * @param bool $removeEmails RFC 8621 onDestroyRemoveEmails - false rejects (throws with
	 *  message 'mailboxHasEmail', matched by mailboxSet()'s catch) a non-empty mailbox instead
	 *  of silently deleting its messages along with it
	 * @param string|null $calledFor see mailboxSet()
	 */
	private static function mailboxDestroy(\Horde_Imap_Client_Socket $imap, string $id, bool $removeEmails, ?string $calledFor) : void
	{
		$path = self::folderPath($id);
		if ($path === '')
		{
			throw new \InvalidArgumentException('Cannot destroy the mailbox root');
		}
		$mailbox = self::hordeMailbox($imap, $path, $calledFor);

		if (!$removeEmails)
		{
			$status = $imap->status($mailbox, \Horde_Imap_Client::STATUS_MESSAGES);
			if (!empty($status['messages']))
			{
				throw new \RuntimeException('mailboxHasEmail');
			}
		}
		// same order Api\Mail::deleteFolder() uses classically: unsubscribe first, then delete
		$imap->subscribeMailbox($mailbox, false);
		$imap->deleteMailbox($mailbox);
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
	 * $calledFor requests an admin-impersonated connection for another user's mailbox - same
	 * meaning as Mail\Account::read()'s $called_for / Mail\Account::imapServer()'s
	 * $_adminConnection, and same security expectation: the CALLER is responsible for verifying
	 * the current user is actually allowed to impersonate $calledFor (mirrors
	 * mail_acl::_require_admin_permission()) before ever passing it here - this method itself
	 * does no such check, it only opens the connection. See mailboxSet()'s docblock for why
	 * dispatch() (the client-facing entry point) must never be the source of a non-null
	 * $calledFor.
	 *
	 * @param string $accountId
	 * @param string|null $calledFor account_id of the mailbox owner to impersonate (admin only), or
	 *  null for the caller's own mailbox
	 * @return \Horde_Imap_Client_Socket|null
	 */
	public static function imapServer(string $accountId, ?string $calledFor = null) : ?\Horde_Imap_Client_Socket
	{
		static $servers = [];
		if ($accountId === '0')
		{
			return null;
		}
		$key = $accountId.'|'.($calledFor ?? '');
		return $servers[$key] ??= Account::read((int)$accountId, $calledFor)->imapServer($calledFor !== null ? (int)$calledFor : false);
	}

	/**
	 * Translate an EGroupware-canonical "/"-joined folder path to the account's real IMAP mailbox name
	 *
	 * With $calledFor set, the path is resolved under the impersonated user's own mailbox
	 * namespace root instead of the connection-owner's personal one - same root
	 * mail_acl::edit() resolves via getUserMailboxString($account_id) for its own (ACL-only)
	 * admin-impersonated screens, joined with the "others" namespace's own delimiter (which can
	 * differ from the "personal" one $imap's own account normally uses).
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $path
	 * @param string|null $calledFor account_id of the mailbox owner to impersonate (admin only), or
	 *  null for $imap's own connection owner
	 * @return string
	 */
	public static function hordeMailbox(\Horde_Imap_Client_Socket $imap, string $path, ?string $calledFor = null) : string
	{
		if ($calledFor !== null)
		{
			if ($path === '')
			{
				return $imap->getUserMailboxString($calledFor);
			}
			$delimiter = self::namespaceDelimiter($imap, 'others');

			return $imap->getUserMailboxString($calledFor, str_replace('/', $delimiter, $path));
		}

		if ($path === '' || strtoupper($path) === 'INBOX')
		{
			return 'INBOX';
		}
		// A path under the shared/other-users namespace root ("user/..."/"shared/...", see
		// isNamespaceRootPath()) lives in that namespace, not "personal" - its delimiter can differ
		// (same reasoning as the $calledFor branch above, and namespaceRootsMissingFrom()'s own use
		// of the "others" delimiter for both root names).
		$delimiter = self::namespaceDelimiter($imap, self::isNamespaceRootPath($path) ? 'others' : 'personal');

		return $delimiter === '/' ? $path : str_replace('/', $delimiter, $path);
	}

	/**
	 * Whether $path's first "/"-segment is a shared/other-users namespace root ("user"/"shared") -
	 * PHP counterpart of folderTree.ts's isNamespaceRootName(), same convention (case-insensitive,
	 * matched by literal name only, not by the server's advertised NAMESPACE prefixes).
	 *
	 * @param string $path
	 * @return bool
	 */
	private static function isNamespaceRootPath(string $path) : bool
	{
		return (bool)preg_match('#^(user|shared)(/|$)#i', $path);
	}

	/**
	 * Get (and cache, per request/connection) one of $imap's namespace delimiters
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $namespace 'personal'|'others'|'shared' (Horde_Imap_Client_Socket::getNameSpaceArray()'s keys)
	 * @return string
	 */
	private static function namespaceDelimiter(\Horde_Imap_Client_Socket $imap, string $namespace) : string
	{
		static $delimiters = [];
		$key = spl_object_id($imap).'|'.$namespace;
		return $delimiters[$key] ??= $imap->getNameSpaceArray()[$namespace][0]['delimiter'] ?? '/';
	}

	/**
	 * The best server-side IMAP THREAD algorithm this account can use, or null if none - ORDEREDSUBJECT
	 * is deliberately never returned even if it's the only one advertised (doc/ai/projects/
	 * mail-threaded-view.md, Phase 1 decision: too weak - subject+date only, no real reply-chain
	 * awareness - to offer as "threading support" at all), matching
	 * ProfileHandler::jmapBootstrap()'s identical REFERENCES/REFS-only capability gate for
	 * supportsThreading.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @return int|null one of Horde_Imap_Client::THREAD_REFERENCES/THREAD_REFS (both plain int
	 *  constants - NOT ?string, which would silently coerce them to "2"/"3")
	 */
	private static function threadCriteria(\Horde_Imap_Client_Socket $imap) : ?int
	{
		$algorithms = (array)($imap->queryCapability('THREAD') ?: []);
		if (in_array('REFERENCES', $algorithms, true))
		{
			return \Horde_Imap_Client::THREAD_REFERENCES;
		}
		if (in_array('REFS', $algorithms, true))
		{
			return \Horde_Imap_Client::THREAD_REFS;
		}
		return null;
	}

	/**
	 * uid -> threadId map for every message in $mailbox, via the server's real IMAP THREAD command
	 * (Horde_Imap_Client_Base::thread(), which transparently uses Horde's own IMAP result cache -
	 * no bespoke caching needed here, same as search() below already relies on). Memoized per
	 * mailbox in $context so one dispatch() batch never issues the underlying THREAD command twice
	 * (Email/query+Email/get both wanting it, or a standalone Thread/get).
	 *
	 * threadId is simply the thread's lowest/root uid (Horde's own "base", RFC 5256 terminology) -
	 * a singleton thread (no other message references it) has no base, so it's its own threadId,
	 * matching real JMAP's Email.threadId semantics for an unthreaded message.
	 *
	 * Servers with no THREAD=REFERENCES/REFS support (threadCriteria() returns null) get an empty
	 * map back - every lookup then falls back to "this message is its own thread", i.e. behaves
	 * exactly like collapseThreads/threadId were never requested. Reachable only defensively: real
	 * callers only ever ask for this once ProfileHandler::jmapBootstrap() has already gated
	 * supportsThreading on the same capability check.
	 *
	 * @param \Horde_Imap_Client_Socket $imap
	 * @param string $mailbox
	 * @param array &$context see emailQuery()
	 * @param string $accountId
	 * @return array uid(string) => threadId(string)
	 */
	private static function threadMap(\Horde_Imap_Client_Socket $imap, string $mailbox, array &$context, string $accountId) : array
	{
		if (isset($context['threadMap'][$accountId][$mailbox]))
		{
			return $context['threadMap'][$accountId][$mailbox];
		}
		$criteria = self::threadCriteria($imap);
		if (!$criteria)
		{
			return $context['threadMap'][$accountId][$mailbox] = [];
		}
		$map = [];
		foreach ($imap->thread($mailbox, ['criteria' => $criteria])->getThreads() as $group)
		{
			foreach ($group as $uid => $info)
			{
				$map[(string)$uid] = (string)($info->base ?? $uid);
			}
		}
		return $context['threadMap'][$accountId][$mailbox] = $map;
	}

	/**
	 * Email/query: translate MailJmap.buildFilter()'s filter tree + buildSort()'s sort into a
	 * single Horde_Imap_Client::search() call, mirroring mail_ui::get_rows()'s pagination.
	 *
	 * collapseThreads (RFC 8621 §4.4.4, doc/ai/projects/mail-threaded-view.md Phase 2): fold the
	 * already-sorted id list down to one representative per thread, keeping each thread's first
	 * (in sort order) message - same semantics real JMAP servers use, computed here via
	 * threadMap() instead of a server-side collapse operation IMAP has no equivalent for.
	 *
	 * @param string $accountId
	 * @param array $args {filter: array, sort?: array, position?: int, limit?: int, collapseThreads?: bool}
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

		if (!empty($args['collapseThreads']))
		{
			$map = self::threadMap($imap, $mailbox, $context, $accountId);
			$seenThreads = $ids = [];
			foreach (array_values($sorted['match']->ids ?? []) as $uid)
			{
				$threadId = $map[(string)$uid] ?? (string)$uid;
				if (isset($seenThreads[$threadId]))
				{
					continue;
				}
				$seenThreads[$threadId] = true;
				$ids[] = $uid;
			}
			$total = count($ids);
		}

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
		// MDN (read-receipt) prompt detection - MailJmap.email2row() reads this same property
		// name back verbatim, matching how a real JMAP server echoes header:X:form property keys
		$wantMdn = !$properties || in_array(self::MDN_HEADER_PROPERTY, $properties, true);
		// whole-message blobId (RFC 8621 top-level Email.blobId) - MailJmap.fetchRawHeader()'s
		// "view header" fast path, no extra IMAP work needed (same self-describing scheme
		// bodyPartToJmap() uses per-part, just with an empty partId - see download())
		$wantBlobId = !$properties || in_array('blobId', $properties, true);
		// doc/ai/projects/mail-threaded-view.md, Phase 2 - only computed (an extra, Horde-cached
		// IMAP THREAD command via threadMap()) when actually requested, since every ordinary
		// (non-threaded) row fetch has no use for it
		$wantThreadId = !$properties || in_array('threadId', $properties, true);

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
		// From/To/Cc/Bcc are re-parsed from the raw header text below (addressListFromHeader()),
		// not trusted from $envelope->from/to/cc/bcc as-is - the IMAP server's own ENVELOPE address
		// parser isn't RFC 2047-aware and can misparse a sending MUA's malformed encoded-word (eg.
		// one containing a literal, unencoded comma inside a quoted display name), splitting it into
		// bogus extra addresses. Api\Mail::parseAddressList() already has the repair logic for
		// exactly this (see its "no mailbox or host part" handling) that the classic pre-JMAP code
		// path has long relied on - this restores that same robustness for the JMAP-native path.
		$query->headers('addresses', ['From', 'To', 'Cc', 'Bcc'], ['cache' => true, 'peek' => true]);
		$query->flags();
		$query->size();
		$query->structure();
		// without this, Horde_Imap_Client_Data_Fetch::getImapDate() silently falls back to "now"
		// instead of the message's real INTERNALDATE - emailFromFetch() relies on it for receivedAt
		$query->imapDate();
		if ($wantPreview)
		{
			$query->bodyText(['length' => 800, 'peek' => true]);
		}
		if ($wantMdn)
		{
			// same 3-header priority Api\Mail::getHeaders() uses (DISPOSITION-NOTIFICATION-TO,
			// falling back to the older RETURN-RECEIPT-TO/X-CONFIRM-READING-TO conventions)
			$query->headers('mdn', ['Disposition-Notification-To', 'Return-Receipt-To', 'X-Confirm-Reading-To'],
				['cache' => true, 'peek' => true]);
		}

		$results = $imap->fetch($mailbox, $query, [
			'ids' => new \Horde_Imap_Client_Ids(array_map('intval', $ids)),
		]);
		// the client may have already navigated away while this (pre)view fetch was in flight -
		// the further per-message IMAP round trips below (preview()/emailBodyFields()) are the
		// expensive part, not worth starting for a response nobody will read
		if (connection_aborted()) exit;

		// IMAP FETCH responses come back in whatever order the server chooses (typically ascending
		// UID, NOT the order of the id-set given), so $results must NOT be iterated directly - that
		// would silently undo Email/query's sort (e.g. turning "newest first" into "oldest first"
		// within the page). Rebuild the list in the order Email/query already determined instead.
		$threadMap = $wantThreadId ? self::threadMap($imap, $mailbox, $context, $accountId) : [];
		$list = [];
		foreach ($ids as $id)
		{
			if (($data = $results[(int)$id] ?? null))
			{
				/** @var \Horde_Imap_Client_Data_Fetch $data */
				$email = self::emailFromFetch($imap, $mailbox, $id, $data, $wantPreview, (bool)$wantBody, $wantMdn, $wantBlobId);
				if ($wantThreadId)
				{
					$email['threadId'] = $threadMap[$id] ?? $id;
				}
				$list[] = $email;
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
	 * Thread/get (RFC 8621 §3.3), doc/ai/projects/mail-threaded-view.md Phase 2.
	 *
	 * Requires our own local-only 'mailboxId' extension (same reasoning as Email/get's - IMAP
	 * UIDs/threads are per-mailbox, not globally unique the way real JMAP ids are): a real JMAP
	 * thread id is inherently account-scoped, not mailbox-scoped, but this IMAP-backed emulation
	 * has no way to know which mailbox to search without it. MailJmap only ever calls this for a
	 * thread row it already knows the mailboxId of (embedded in the thread row's own row_id, see
	 * jmap.ts's emails2threadRow()/getThreadMemberRows()/threadMemberRowIds()), so the extension
	 * is always available in practice - falls back to a preceding Email/query's remembered mailbox
	 * in the same batch otherwise, mirroring emailGet()'s identical fallback.
	 *
	 * @param string $accountId
	 * @param array $args {ids: string[], mailboxId?: string}
	 * @param array &$context see emailQuery()
	 * @return array {accountId: string, list: {id: string, emailIds: string[]}[], notFound: string[]}
	 */
	public static function threadGet(string $accountId, array $args, array &$context) : array
	{
		$ids = array_map('strval', (array)($args['ids'] ?? []));
		if ($accountId === '0' || !$ids)
		{
			return ['accountId' => $accountId, 'list' => [], 'notFound' => $ids];
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
				throw new \Exception('Thread/get without a preceding Email/query or a mailboxId for the same accountId in this request');
			}
		}

		$membersByThread = [];
		foreach (self::threadMap($imap, $mailbox, $context, $accountId) as $uid => $threadId)
		{
			$membersByThread[$threadId][] = $uid;
		}

		$list = $notFound = [];
		foreach ($ids as $threadId)
		{
			if (isset($membersByThread[$threadId]))
			{
				$list[] = ['id' => $threadId, 'emailIds' => $membersByThread[$threadId]];
			}
			else
			{
				$notFound[] = $threadId;
			}
		}
		return ['accountId' => $accountId, 'list' => $list, 'notFound' => $notFound];
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
	 * Email/import (RFC 8621 §4.8): append an uploaded (or existing) blob to a mailbox as a new
	 * message - the local-shim counterpart of a real JMAP server's import, needed for client-side
	 * message composition (e.g. saving to Sent) without going through mail_ui/Api\Mail. Same
	 * Horde_Imap_Client_Socket::append() primitive Mail::appendMessage() uses.
	 *
	 * Only a single target mailbox per email is supported (the common case, and all MailJmap
	 * currently sends) - if more than one truthy id is given, the first is used.
	 *
	 * @param string $accountId
	 * @param array $args {emails: {creationId: {blobId, mailboxIds, keywords?}}}
	 * @return array
	 */
	public static function emailImport(string $accountId, array $args) : array
	{
		$imap = self::imapServer($accountId);
		$created = [];
		$notCreated = [];

		foreach ((array)($args['emails'] ?? []) as $creationId => $email)
		{
			$creationId = (string)$creationId;
			try
			{
				if (!$imap)
				{
					throw new \InvalidArgumentException('Unknown account');
				}
				$blobId = (string)($email['blobId'] ?? '');
				$raw = $blobId !== '' ? self::readUploadedBlob($accountId, $blobId) : null;
				if ($raw === null)
				{
					$notCreated[$creationId] = ['type' => 'invalidProperties', 'properties' => ['blobId']];
					continue;
				}
				$target = array_key_first(array_filter((array)($email['mailboxIds'] ?? [])));
				$folder = $target !== null ? self::folderPath((string)$target) : '';
				if ($folder === '')
				{
					$notCreated[$creationId] = ['type' => 'invalidProperties', 'properties' => ['mailboxIds']];
					continue;
				}
				$mailbox = self::hordeMailbox($imap, $folder);

				$flags = [];
				foreach ((array)($email['keywords'] ?? []) as $keyword => $set)
				{
					if ($set && ($flag = self::importKeywordToFlag(strtolower((string)$keyword))) !== null)
					{
						$flags[] = $flag;
					}
				}

				$ret = $imap->append($mailbox, [['data' => $raw, 'flags' => $flags]]);
				$uid = is_object($ret) && isset($ret->ids) ? (string)current($ret->ids) : null;
				if ($uid === null || $uid === '')
				{
					// server didn't report UIDPLUS-style ids (append() returned plain true) - same
					// fallback Mail::appendMessage() uses: the just-appended message is always the
					// newest by arrival, found directly rather than via Api\Mail (see class docblock)
					$sorted = $imap->search($mailbox, new \Horde_Imap_Client_Search_Query(), [
						'sort' => [\Horde_Imap_Client::SORT_REVERSE, \Horde_Imap_Client::SORT_ARRIVAL],
					]);
					$uid = (string)(array_values($sorted['match']->ids ?? [])[0] ?? '');
				}
				if ($uid === '')
				{
					throw new \Exception('IMAP server did not report the new message UID');
				}

				$created[$creationId] = ['id' => $uid, 'blobId' => $blobId, 'threadId' => $uid, 'size' => strlen($raw)];
				if (str_starts_with($blobId, 'upload:'))
				{
					@unlink(self::uploadPath(substr($blobId, strlen('upload:'))));
				}
			}
			catch (\Throwable $e)
			{
				$notCreated[$creationId] = ['type' => 'serverFail', 'description' => $e->getMessage()];
			}
		}

		return [
			'accountId' => $accountId,
			'oldState' => '0',
			'newState' => '0',
			'created' => (object)$created,
			'notCreated' => (object)$notCreated,
		];
	}

	/**
	 * Standard IMAP flags an imported message's "keywords" may set - broader than
	 * writableKeywords() (which only covers what the UI may *mutate* on an existing message via
	 * Email/set - labels/customflags/$flagged, deliberately excluding \Seen/\Draft/\Answered).
	 * Unrecognised keywords are silently ignored rather than failing the whole import.
	 *
	 * @param string $keyword lowercased JMAP keyword, e.g. "$seen"
	 * @return ?string IMAP flag, or null if not a recognised standard keyword
	 */
	private static function importKeywordToFlag(string $keyword) : ?string
	{
		return match ($keyword)
		{
			'$seen' => '\\Seen',
			'$answered' => '\\Answered',
			'$flagged' => '\\Flagged',
			'$draft' => '\\Draft',
			default => self::writableKeywords()[$keyword] ?? null,
		};
	}

	/**
	 * Keywords the Mail UI is allowed to mutate through the local shim.
	 *
	 * @return array<string,string> JMAP keyword to IMAP flag / keyword
	 */
	public static function writableKeywords() : array
	{
		// 'MDNSent'/'MDNnotSent' (no '$' prefix) is the real IMAP keyword classic
		// Api\Mail::flagMessages() already writes - matched here so a message flagged through
		// either code path is recognized identically by the other. $seen is here for
		// MailJmap.setSystemFlag()'s explicit-selection bulk read/unread action.
		$keywords = ['$flagged' => '\\Flagged', '$seen' => '\\Seen', '$mdnsent' => 'MDNSent', '$mdnnotsent' => 'MDNnotSent'];
		foreach (['label1', 'label2', 'label3', 'label4', 'label5',
			'customflag1', 'customflag2', 'customflag3', 'customflag4', 'customflag5'] as $keyword)
		{
			$keywords['$'.$keyword] = '$'.$keyword;
		}
		foreach (array_keys(CustomLabels::getCustomLabels()) as $keyword)
		{
			$keywords['$'.strtolower($keyword)] = '$'.strtolower($keyword);
		}
		return $keywords;
	}

	/**
	 * Whether a message's parsed structure counts as "has an attachment" for the row-level
	 * hasAttachment flag - mirrors Api\Mail's old per-row heuristic (getHeaders(), classic
	 * pre-JMAP code) rather than Horde_Mime_Part::isAttachment(): that method has no carve-out
	 * for an inline image with no Content-ID (can never be resolved as a cid: reference, so it
	 * must be listed/downloadable) or for image/tiff (browsers can't display it inline regardless
	 * of disposition), both of which the classic per-row flag always treated as "has an
	 * attachment".
	 *
	 * @param \Horde_Mime_Part $structure
	 * @return bool
	 */
	public static function structureHasAttachment(\Horde_Mime_Part $structure) : bool
	{
		foreach ($structure->partIterator() as $part)
		{
			/** @var \Horde_Mime_Part $part */
			$partDisposition = $part->getDisposition();
			$partPrimaryType = $part->getPrimaryType();
			if ($partDisposition === 'attachment' ||
				($partDisposition === 'inline' && $partPrimaryType === 'image' && $part->getType() === 'image/tiff') ||
				($partDisposition === 'inline' && $partPrimaryType === 'image' && !$part->getContentId()) ||
				($partDisposition === 'inline' && $partPrimaryType !== 'image' && $partPrimaryType !== 'multipart' && $partPrimaryType !== 'text'))
			{
				return true;
			}
		}
		return false;
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
	 * @param bool $wantMdn true adds the MDN_HEADER_PROPERTY field (needs a preceding
	 *  $query->headers('mdn', ...) call, see emailGet())
	 * @param bool $wantBlobId true adds the whole-message 'blobId' field (RFC 8621 top-level
	 *  Email.blobId) - no extra IMAP work, same self-describing scheme bodyPartToJmap() uses
	 * @return array
	 */
	public static function emailFromFetch(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid, \Horde_Imap_Client_Data_Fetch $data, bool $wantPreview = true, bool $wantBody = false, bool $wantMdn = false, bool $wantBlobId = false) : array
	{
		$envelope = $data->getEnvelope();
		$structure = $data->getStructure();
		$addressHeaders = $data->getHeaders('addresses', \Horde_Imap_Client_Data_Fetch::HEADER_PARSE);

		$hasAttachment = $structure && self::structureHasAttachment($structure);

		$email = [
			'id' => $uid,
			'keywords' => self::flagsToKeywords($data->getFlags()),
			'size' => $data->getSize(),
			'receivedAt' => self::imapDate($data->getImapDate()),
			'sentAt' => self::imapDate($envelope->date),
			'subject' => (string)$envelope->subject,
			'preview' => $wantPreview ? self::preview($imap, $mailbox, $uid, $structure, $data) : '',
			'from' => self::addressListFromHeader($addressHeaders, 'From') ?? self::addressList($envelope->from),
			'to' => self::addressListFromHeader($addressHeaders, 'To') ?? self::addressList($envelope->to),
			'cc' => self::addressListFromHeader($addressHeaders, 'Cc') ?? self::addressList($envelope->cc),
			'bcc' => self::addressListFromHeader($addressHeaders, 'Bcc') ?? self::addressList($envelope->bcc),
			'hasAttachment' => $hasAttachment,
		];
		if ($wantBlobId)
		{
			$email['blobId'] = self::urlsafeB64Encode($mailbox).':'.$uid.':';
		}
		if ($wantMdn)
		{
			$mdnHeaders = $data->getHeaders('mdn', \Horde_Imap_Client_Data_Fetch::HEADER_PARSE);
			$email[self::MDN_HEADER_PROPERTY] = $mdnHeaders ? self::firstHeaderValue($mdnHeaders,
				['Disposition-Notification-To', 'Return-Receipt-To', 'X-Confirm-Reading-To']) : null;
		}
		if ($wantBody && $structure)
		{
			$email += self::emailBodyFields($imap, $mailbox, $uid, $structure);
		}
		return $email;
	}

	/**
	 * RFC 8621 §4.1.3 header-property name for the MDN (read-receipt) prompt - MailJmap.email2row()
	 * (mail/js/jmap.ts) reads this exact key back from both backends' Email/get response, matching
	 * how a real JMAP server echoes "header:X:form" property keys verbatim.
	 */
	const MDN_HEADER_PROPERTY = 'header:disposition-notification-to:asText';

	/**
	 * First non-empty value among a priority list of header names, decoded (RFC 2047) and trimmed -
	 * same 3-header priority/decoding Api\Mail::getHeaders() uses, deliberately reimplemented here
	 * rather than calling that mail_bo-coupled method (see class docblock).
	 *
	 * @param \Horde_Mime_Headers $headers
	 * @param string[] $names tried in order, first present+non-empty wins
	 * @return ?string
	 */
	private static function firstHeaderValue(\Horde_Mime_Headers $headers, array $names) : ?string
	{
		$arr = array_change_key_case($headers->toArray(), CASE_UPPER);
		foreach ($names as $name)
		{
			$value = $arr[strtoupper($name)] ?? null;
			$value = is_array($value) ? reset($value) : $value;
			if ($value !== null && trim((string)$value) !== '')
			{
				return \iconv_mime_decode(trim((string)$value), 0, 'UTF-8');
			}
		}
		return null;
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
		if (connection_aborted()) exit;
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
	 * Blob upload (RFC 8620 §6.3): plain POST of raw bytes matching the "uploadUrl" template from
	 * session() above - a prerequisite for Email/import (composing a new message client-side and
	 * saving it, e.g. to Sent, without going through mail_ui/Api\Mail).
	 *
	 * Unlike downloaded/existing-message blobs (self-describing "mailbox:uid:partId", resolved live
	 * via IMAP FETCH - see class docblock), an uploaded blob has no IMAP message to describe yet, so
	 * it needs actual temporary storage: written to a randomly-named file under temp_dir, referenced
	 * by an "upload:<token>" blobId. The token is generated here (never client-supplied), so there's
	 * no path-traversal risk from a crafted blobId. Consumed (and deleted) by emailImport() below;
	 * see readUploadedBlob()'s docblock for the case where import never follows.
	 *
	 * @param string $accountId
	 */
	public static function upload(string $accountId) : void
	{
		$type = $_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream';
		$bytes = file_get_contents('php://input');
		$token = bin2hex(random_bytes(16));
		file_put_contents(self::uploadPath($token), $bytes);

		echo json_encode([
			'accountId' => $accountId,
			'blobId' => 'upload:'.$token,
			'type' => $type,
			'size' => strlen($bytes),
		], JSON_UNESCAPED_SLASHES);
	}

	/**
	 * @param string $token hex string only - never build this from unvalidated client input
	 * @return string absolute path of the temp file backing an "upload:<token>" blobId
	 */
	private static function uploadPath(string $token) : string
	{
		if (!ctype_xdigit($token) || $token === '')
		{
			throw new \InvalidArgumentException('Invalid upload token');
		}
		return $GLOBALS['egw_info']['server']['temp_dir'].'/jmap_upload_'.$token;
	}

	/**
	 * Resolve an Email/import blobId to raw bytes - either a freshly uploaded blob ("upload:<token>",
	 * see upload() above) or an existing message/part already on the server (the same self-describing
	 * "mailbox:uid:partId" scheme download() decodes), for re-importing already-downloaded content.
	 *
	 * Uploaded blobs are single-use: deleted by emailImport() after a successful import. If upload()
	 * is called but import never follows (e.g. the user aborts composing), the temp file is orphaned -
	 * accepted as a known limitation given how small/rare this is, rather than adding a GC sweep for
	 * it; a stale jmap_upload_* file is always safe to delete manually if this ever matters in practice.
	 *
	 * @param string $accountId
	 * @param string $blobId
	 * @return ?string null if not found/resolvable
	 */
	private static function readUploadedBlob(string $accountId, string $blobId) : ?string
	{
		if (str_starts_with($blobId, 'upload:'))
		{
			$path = self::uploadPath(substr($blobId, strlen('upload:')));
			return is_file($path) ? file_get_contents($path) : null;
		}
		[$mailboxB64, $uid, $partId] = array_pad(explode(':', $blobId, 3), 3, null);
		$imap = ($mailboxB64 !== null && $uid) ? self::imapServer($accountId) : null;
		if (!$imap)
		{
			return null;
		}
		$mailbox = self::urlsafeB64Decode($mailboxB64);
		return $partId !== '' ? self::fetchRawPart($imap, $mailbox, $uid, $partId) : self::fetchRawMessage($imap, $mailbox, $uid);
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
	 * Build the flat attachment-array shape EGroupware\Mail\Ui\AttachmentJmap::createAttachmentBlock()
	 * expects, from a
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
	 * Attachment listing is still out of scope here (has its own mechanism for the normal mail
	 * path) - but inline cid: images ARE resolved (see inlineCidImages()), unconditionally as
	 * data: URIs regardless of size: unlike the classic resolve_inline_image_byType()'s size-gated
	 * data-URI-vs-link choice (avoiding a second round trip for a fresh IMAP fetch), every part
	 * here is already fully fetched/decrypted in memory - a data: URI is strictly cheaper than a
	 * link that would otherwise require redoing the whole decrypt from scratch.
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
			$raw = self::inlineCidImages($raw, $structure);
			$htmLawed = new Api\Html\HtmLawed();
			return $htmLawed->run($raw, Api\Mail::$htmLawed_config);
		}
		return '<pre>'.htmlspecialchars($raw, ENT_QUOTES, 'UTF-8').'</pre>';
	}

	/**
	 * Replace src="cid:..." / background="cid:..." references in an HTML body with data: URIs,
	 * looked up against an already-fully-parsed-in-memory Horde_Mime_Part tree (see
	 * structureToHtml()). No fetch/network access - every referenced part's contents are already
	 * populated (Horde_Mime_Part::parseMessage() populates every part up front).
	 *
	 * @param string $html
	 * @param \Horde_Mime_Part $structure
	 * @return string $html with every resolvable cid: reference replaced by a data: URI (any cid:
	 *  with no matching part is left untouched)
	 */
	private static function inlineCidImages(string $html, \Horde_Mime_Part $structure) : string
	{
		if (stripos($html, 'cid:') === false)
		{
			return $html;
		}
		$dataUris = [];
		$resolve = static function(string $cid) use ($structure, &$dataUris)
		{
			$cid = trim($cid, '<>');
			if (array_key_exists($cid, $dataUris))
			{
				return $dataUris[$cid];
			}
			foreach ($structure->partIterator() as $part)
			{
				/** @var \Horde_Mime_Part $part */
				if (trim((string)$part->getContentId(), '<>') === $cid)
				{
					return $dataUris[$cid] = 'data:'.$part->getType().';base64,'.base64_encode($part->getContents());
				}
			}
			return $dataUris[$cid] = null;
		};
		return preg_replace_callback('#((?:src|background)\s*=\s*)(["\'])cid:([^"\']+)\2#i',
			static function(array $matches) use ($resolve)
			{
				$dataUri = $resolve(urldecode($matches[3]));
				return $dataUri ? $matches[1].$matches[2].$dataUri.$matches[2] : $matches[0];
			}, $html);
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
	 * @return array{body: string, smime: ?array} sanitized HTML body, plus the decrypt/verify
	 *  metadata (Api\Mail\Smime::resolveMessage()'s 'X-EGroupware-Smime' convention) for the
	 *  caller to push to the client (app.mail.setSmimeFlags) - never sent to the client itself
	 * @throws Api\Mail\Smime\PassphraseMissing
	 * @throws \Exception message/mailbox not found
	 */
	public static function resolveSmime(string $accountId, string $mailboxId, string $uid, string $topLevelType,
		string $fromAddress, string $htmlOptions='', string $passphrase='') : array
	{
		$imap = self::imapServer($accountId);
		$mailbox = self::hordeMailbox($imap, self::folderPath($mailboxId));
		$raw = self::fetchRawMessage($imap, $mailbox, $uid);
		if ($raw === null)
		{
			throw new \Exception("Message '$uid' not found in '$mailbox'!");
		}
		$structure = Api\Mail\Smime::resolveMessage((int)$accountId, $raw, $topLevelType, $passphrase, $fromAddress);
		return [
			'body' => self::structureToHtml($structure, $htmlOptions),
			'smime' => $structure->getMetadata('X-EGroupware-Smime'),
		];
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
				if (connection_aborted()) exit;
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
	 * Re-parse a raw From/To/Cc/Bcc header via Api\Mail::parseAddressList() instead of trusting
	 * the IMAP server's own ENVELOPE-parsed addresses - see emailGet()'s query() comment for why
	 * (a sending MUA's malformed encoded-word containing a literal, unencoded comma inside a
	 * quoted display name can trip up the server's own, not-2047-aware address splitter, which
	 * Api\Mail::parseAddressList() already has repair logic for - see its "no mailbox or host
	 * part" handling, long relied on by the classic pre-JMAP code path).
	 *
	 * Returns null (caller falls back to the envelope-derived list) when the header is missing
	 * or empty - NOT the same as "parsed to zero addresses", which is a valid, different result
	 * for a genuinely absent header (eg. no Cc) that the caller must not fall back away from.
	 *
	 * @param ?\Horde_Mime_Headers $headers
	 * @param string $name header name, eg. "From"
	 * @return ?array [{name?: string, email: string}]
	 */
	private static function addressListFromHeader(?\Horde_Mime_Headers $headers, string $name) : ?array
	{
		if (!$headers)
		{
			return null;
		}
		$arr = array_change_key_case($headers->toArray(), CASE_UPPER);
		$value = $arr[strtoupper($name)] ?? null;
		$value = is_array($value) ? reset($value) : $value;
		if ($value === null || trim((string)$value) === '')
		{
			return null;
		}
		return self::addressList(Api\Mail::parseAddressList((string)$value));
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
		// RFC 8621 UTCDate: real UTC, matching what a real JMAP server (Stalwart) returns - IMAP's
		// INTERNALDATE and RFC 5322's Date: header both always carry an explicit offset, so Horde's
		// DateTime objects already know their true instant; converting to UTC needs nothing from
		// Api\DateTime (no dependency on $user_timezone/$server_timezone). The eTemplate/get_rows()
		// "fake Z, user-tz digits" convention is applied client-side instead (MailJmap.jmapUtcToUserTz()),
		// uniformly for both backends, so this shim doesn't need to special-case it here.
		return (clone $date)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
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
