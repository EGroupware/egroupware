<?php
/**
 * EGroupware Mail: unit tests for the JMAP-lite REST endpoints' pure-logic pieces
 *
 * Covers what's testable without a live IMAP/JMAP connection or DB (see
 * doc/ai/projects/mail-rest-jmap-lite.md for the design and mail/tests/REST/ for the
 * live-HTTP-round-trip style used for the *existing* mail REST endpoints - a live-server
 * follow-up in that same style is still needed for these new ones):
 * - ApiHandler's own protected static helpers (urlSafeId()/fromUrlSafeId(), jsonMailbox()/
 *   jsonEmail(), queryEmailFilter()/queryEmailSort(), listAllFolders()) via ReflectionMethod,
 *   same style JmapShimMailboxGetTest.php already uses.
 * - Api\Jmap\Type::query()/get()'s widened argument-building (position/limit/calculateTotal/
 *   fetchAllBodyValues), against a fake Base capturing call() arguments.
 * - The mailboxIds fix in Api\Mail\Jmap\Imap::emailFromFetch(), against a mocked non-INBOX
 *   mailbox (exercising canonicalPath()'s real, non-fast-path branch).
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Jmap\Base as JmapBase;
use EGroupware\Api\Jmap\Type as JmapType;
use EGroupware\Api\Mail\Jmap\Imap as JmapShim;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class ApiHandlerJmapRestTest extends \PHPUnit\Framework\TestCase
{
	private function invokeApiHandler(string $method, array $args)
	{
		$reflection = new \ReflectionMethod(ApiHandler::class, $method);
		$reflection->setAccessible(true);
		return $reflection->invoke(null, ...$args);
	}

	protected function tearDown() : void
	{
		unset($_GET['properties'], $_GET['subscribedOnly'], $_GET['position'], $_GET['limit'],
			$_GET['sort'], $_GET['filter']);
		parent::tearDown();
	}

	// --- urlSafeId()/fromUrlSafeId() --------------------------------------------------------

	public function testUrlSafeIdSubstitutesBase64SpecialCharsAndStripsPadding()
	{
		// chosen so its base64 encoding ("+/8=") genuinely contains '+', '/' AND trailing '=' padding
		$raw = base64_encode("\xfb\xff");
		$this->assertStringContainsString('+', $raw, 'fixture should actually exercise the substitution');
		$this->assertStringContainsString('/', $raw, 'fixture should actually exercise the substitution');
		$this->assertStringEndsWith('=', $raw, 'fixture should actually exercise the substitution');

		$safe = $this->invokeApiHandler('urlSafeId', [$raw]);

		$this->assertStringNotContainsString('/', $safe);
		$this->assertStringNotContainsString('+', $safe);
		$this->assertStringNotContainsString('=', $safe);
		// round-trips back to the same raw bytes once decoded - base64_decode() tolerates
		// missing padding, same as Api\Mail\Jmap\Imap::urlsafeB64Decode() already relies on
		$restored = $this->invokeApiHandler('fromUrlSafeId', [$safe]);
		$this->assertSame("\xfb\xff", base64_decode($restored));
	}

	/**
	 * urlSafeId() (the encode direction) is unconditionally a no-op for ANY id containing no
	 * literal '+'/'/'  - true for both a real JMAP id (RFC 8620 mandates the url-safe alphabet,
	 * which legitimately allows '-'/'_' as ordinary characters) and a plain IMAP UID.
	 */
	public function testUrlSafeIdIsNoOpForIdsWithNoPlusOrSlash()
	{
		foreach (['Mabc-123_xyz', '42'] as $id)
		{
			$this->assertSame($id, $this->invokeApiHandler('urlSafeId', [$id]));
		}
	}

	/**
	 * fromUrlSafeId() (the decode direction) is deliberately NOT safe to call unconditionally on
	 * a real JMAP id containing '-'/'_' - see its own docblock; callers must gate it on
	 * isRealJmapSession() first (getFolder()/listEmails() do). It's still a true no-op for
	 * anything with no '-'/'_' at all, like a plain IMAP UID.
	 */
	public function testFromUrlSafeIdIsOnlyANoOpWhenNoHyphenOrUnderscoreIsPresent()
	{
		$this->assertSame('42', $this->invokeApiHandler('fromUrlSafeId', ['42']));
		$this->assertNotSame('Mabc-123_xyz', $this->invokeApiHandler('fromUrlSafeId', ['Mabc-123_xyz']),
			'documents the known asymmetry - this is exactly why call sites must guard with isRealJmapSession() first');
	}

	// --- parseDoubleColonFolderPath() (the "::"-joined literal-path REST syntax) --------------

	public function testParseDoubleColonFolderPathReturnsNullWhenNoDoubleColonIsPresent()
	{
		// a real id (either backend's) can never legitimately look like this - documents the
		// "not this syntax, fall back to normal id handling" contract
		$this->assertNull($this->invokeApiHandler('parseDoubleColonFolderPath', ['Mabc-123_xyz']));
		$this->assertNull($this->invokeApiHandler('parseDoubleColonFolderPath', ['SU5CT1g']));
	}

	public function testParseDoubleColonFolderPathJoinsSegmentsWithASlash()
	{
		$this->assertSame('INBOX/Sent', $this->invokeApiHandler('parseDoubleColonFolderPath', ['INBOX::Sent']));
		$this->assertSame('INBOX/Archive/2026', $this->invokeApiHandler('parseDoubleColonFolderPath', ['INBOX::Archive::2026']));
	}

	/**
	 * IMAP's own INBOX case-insensitivity (RFC 3501 §5.1) applies only to that literal top-level
	 * mailbox name - normalized to uppercase here to match this codebase's own canonical-path
	 * convention (INBOX always spelled uppercase, see eg. Imap::hordeMailbox()'s equivalent check).
	 */
	public function testParseDoubleColonFolderPathNormalizesTheFirstSegmentsInboxCasing()
	{
		$this->assertSame('INBOX/Sent', $this->invokeApiHandler('parseDoubleColonFolderPath', ['inbox::Sent']));
		$this->assertSame('INBOX/Sent', $this->invokeApiHandler('parseDoubleColonFolderPath', ['Inbox::Sent']));
		$this->assertSame('INBOX/Sent', $this->invokeApiHandler('parseDoubleColonFolderPath', ['INBOX::Sent']));
	}

	/**
	 * The special-casing is ONLY for the actual top-level Inbox - a sub-mailbox that merely
	 * happens to be named "inbox" too (legal, if unusual, in real IMAP) is an ordinary folder
	 * name, never normalized.
	 */
	public function testParseDoubleColonFolderPathDoesNotNormalizeANonFirstSegmentNamedInbox()
	{
		$this->assertSame('Archive/inbox', $this->invokeApiHandler('parseDoubleColonFolderPath', ['Archive::inbox']));
	}

	/**
	 * A single, isolated colon INSIDE one segment (not touching the "::" join) round-trips
	 * correctly - only a segment starting/ending with ':' right at the join is the genuinely
	 * ambiguous, deliberately-unsupported edge case (see this method's own docblock).
	 */
	public function testParseDoubleColonFolderPathPreservesAnIsolatedColonWithinASegment()
	{
		$this->assertSame('INBOX/Foo:Bar', $this->invokeApiHandler('parseDoubleColonFolderPath', ['INBOX::Foo:Bar']));
	}

	// --- resolveFolderId() (getFolder()/listEmails()'s shared folder-id resolution) ------------

	public function testResolveFolderIdPassesARealIdThroughUnchangedForARealJmapSession()
	{
		$session = new FakeRealJmapHttpSession();

		$folderId = $this->invokeApiHandler('resolveFolderId', [$session, 'some-real-jmap-id']);

		$this->assertSame('some-real-jmap-id', $folderId);
	}

	public function testResolveFolderIdDecodesTheUrlSafeIdForAShimSession()
	{
		$session = new FakeJmapSessionForFolderWalk();
		$folderIdUrlSafe = $this->invokeApiHandler('urlSafeId', [base64_encode('INBOX/Sub')]);

		$folderId = $this->invokeApiHandler('resolveFolderId', [$session, $folderIdUrlSafe]);

		$this->assertSame(base64_encode('INBOX/Sub'), $folderId);
	}

	public function testResolveFolderIdResolvesADoubleColonPathViaGetMailboxId()
	{
		$session = new FakeJmapSessionWithMailboxIdLookup(['INBOX/Sent' => 'resolved-id-123']);

		$folderId = $this->invokeApiHandler('resolveFolderId', [$session, 'INBOX::Sent']);

		$this->assertSame('resolved-id-123', $folderId);
		$this->assertSame(['INBOX/Sent'], $session->mailbox->getMailboxIdCalls);
	}

	public function testResolveFolderIdThrows404ForADoubleColonPathThatDoesNotResolve()
	{
		$session = new FakeJmapSessionWithMailboxIdLookup([]);

		try
		{
			$this->invokeApiHandler('resolveFolderId', [$session, 'INBOX::Nonexistent']);
			$this->fail('expected an Exception');
		}
		catch (\Exception $e)
		{
			$this->assertSame(404, $e->getCode());
			$this->assertStringContainsString('INBOX/Nonexistent', $e->getMessage());
		}
	}

	// --- mailboxIdForEmailGet() (getEmail()/getAttachment()'s shared $mailboxId computation) ----

	/**
	 * Same regression as testTypeGetForwardsMailboxIdOnlyWhenGiven() above, one level up: the
	 * REST-facing helper that decides WHAT to pass as Type::get()'s new $mailboxId, for a shim
	 * session - real JMAP never reaches this branch (isRealJmapSession() below).
	 */
	public function testMailboxIdForEmailGetDecodesTheFolderIdForAShimSession()
	{
		$session = new FakeJmapSessionForFolderWalk();
		// isRealJmapSession() checks `instanceof Api\Mail\Jmap\Http` - FakeJmapSessionForFolderWalk
		// extends the generic Base directly, so it correctly takes the "shim" branch here
		$folderIdUrlSafe = $this->invokeApiHandler('urlSafeId', [base64_encode('INBOX/Sub')]);

		$mailboxId = $this->invokeApiHandler('mailboxIdForEmailGet', [$session, $folderIdUrlSafe]);

		$this->assertSame(base64_encode('INBOX/Sub'), $mailboxId);
	}

	public function testMailboxIdForEmailGetIsNullForARealJmapSessionEvenThoughTheIdLooksDecodable()
	{
		$session = new FakeRealJmapHttpSession();

		$mailboxId = $this->invokeApiHandler('mailboxIdForEmailGet', [$session, 'some-real-jmap-id']);

		$this->assertNull($mailboxId, 'RFC 8620 §3.6.1: a real JMAP server may reject an argument its method does not define');
	}

	/**
	 * .../emails/<emailId>?mailboxId=INBOX::Sent (or the equivalent for an attachment download) -
	 * resolves the "::"-path via getMailboxId(), same as resolveFolderId() does for a folder-id
	 * URL segment.
	 */
	public function testMailboxIdForEmailGetResolvesADoubleColonPathViaGetMailboxId()
	{
		$session = new FakeJmapSessionWithMailboxIdLookup(['INBOX/Sent' => 'resolved-id-123']);

		$mailboxId = $this->invokeApiHandler('mailboxIdForEmailGet', [$session, 'INBOX::Sent']);

		$this->assertSame('resolved-id-123', $mailboxId);
	}

	// --- jsonMailbox()/jsonEmail() re-keying --------------------------------------------------

	public function testJsonMailboxReEncodesIdAndParentIdOnly()
	{
		$mailbox = [
			'id' => base64_encode('INBOX/Sub'),
			'name' => 'Sub',
			'parentId' => base64_encode('INBOX'),
			'role' => null,
			'totalEmails' => 3,
		];
		$result = $this->invokeApiHandler('jsonMailbox', [$mailbox]);

		$this->assertSame($this->invokeApiHandler('urlSafeId', [$mailbox['id']]), $result['id']);
		$this->assertSame($this->invokeApiHandler('urlSafeId', [$mailbox['parentId']]), $result['parentId']);
		// every other field untouched - proxy, not reshape
		$this->assertSame('Sub', $result['name']);
		$this->assertNull($result['role']);
		$this->assertSame(3, $result['totalEmails']);
	}

	public function testJsonMailboxToleratesNullParentId()
	{
		$result = $this->invokeApiHandler('jsonMailbox', [['id' => base64_encode('INBOX'), 'parentId' => null]]);
		$this->assertNull($result['parentId']);
	}

	public function testJsonEmailReEncodesMailboxIdsKeysOnly()
	{
		$folderId = base64_encode('INBOX/Sub');
		$email = [
			'id' => '42',
			'mailboxIds' => [$folderId => true],
			'subject' => 'Test',
		];
		$result = $this->invokeApiHandler('jsonEmail', [$email]);

		$this->assertSame([$this->invokeApiHandler('urlSafeId', [$folderId]) => true], $result['mailboxIds']);
		// Email.id is a plain IMAP UID (or a real, already url-safe JMAP id) - never touched
		$this->assertSame('42', $result['id']);
		$this->assertSame('Test', $result['subject']);
	}

	public function testJsonEmailToleratesMissingMailboxIds()
	{
		$result = $this->invokeApiHandler('jsonEmail', [['id' => '42']]);
		$this->assertSame(['id' => '42'], $result);
	}

	// --- queryEmailFilter()/queryEmailSort() ($_GET-driven) -----------------------------------

	public function testQueryEmailFilterPassesThroughRfc8621PropertyNamesVerbatim()
	{
		$_GET['filter'] = ['before' => '2026-01-01', 'hasAttachment' => 'true', 'hasKeyword' => '$flagged'];

		$filter = $this->invokeApiHandler('queryEmailFilter', []);

		$this->assertSame('2026-01-01', $filter['before']);
		$this->assertTrue($filter['hasAttachment']);
		$this->assertSame('$flagged', $filter['hasKeyword']);
	}

	public function testQueryEmailFilterCoercesHasAttachmentToBoolean()
	{
		$_GET['filter'] = ['hasAttachment' => '0'];
		$this->assertFalse($this->invokeApiHandler('queryEmailFilter', [])['hasAttachment']);
	}

	public function testQueryEmailFilterRejectsUnsupportedAttribute()
	{
		$_GET['filter'] = ['subject' => 'not a supported REST filter key'];
		$this->expectException(\Exception::class);
		$this->expectExceptionCode(400);
		$this->invokeApiHandler('queryEmailFilter', []);
	}

	public function testQueryEmailSortDefaultsToReceivedAtDescending()
	{
		$sort = $this->invokeApiHandler('queryEmailSort', []);
		$this->assertSame([['property' => 'receivedAt', 'isAscending' => false]], $sort);
	}

	public function testQueryEmailSortParsesExplicitAscending()
	{
		$_GET['sort'] = 'subject asc';
		$sort = $this->invokeApiHandler('queryEmailSort', []);
		$this->assertSame([['property' => 'subject', 'isAscending' => true]], $sort);
	}

	// --- listAllFolders() recursive tree-flatten ----------------------------------------------

	public function testListAllFoldersFlattensEveryLevelAndAlwaysPassesExplicitParentId()
	{
		$session = new FakeJmapSessionForFolderWalk();

		$folders = $this->invokeApiHandler('listAllFolders', [$session, true, null]);

		// root has A, B; A has A1; B and A1 have none - depth-first flatten
		$this->assertSame(['A', 'A1', 'B'], array_column($folders, 'id'));
		// parentId is ALWAYS an explicit filter key (even null for the root level), and
		// isSubscribed is only ever sent as `true`, never `false` - see this method's own
		// docblock on why an omitted/false-valued key would mean something different per backend
		$this->assertSame(
			[
				['parentId' => null, 'isSubscribed' => true],
				['parentId' => 'A', 'isSubscribed' => true],
				['parentId' => 'A1', 'isSubscribed' => true],
				['parentId' => 'B', 'isSubscribed' => true],
			],
			$session->mailbox->queryCalls
		);
	}

	public function testListAllFoldersOmitsIsSubscribedEntirelyWhenNotWanted()
	{
		$session = new FakeJmapSessionForFolderWalk();
		$this->invokeApiHandler('listAllFolders', [$session, false, null]);
		foreach ($session->mailbox->queryCalls as $call)
		{
			$this->assertArrayNotHasKey('isSubscribed', $call);
		}
	}

	/**
	 * The new `path` field (this session's own consistency companion to the "::"-path REST
	 * syntax) is computed for free during this existing recursive walk - each node's path is its
	 * parent's path plus its own name, joined with '/', matching resolveFolderId()'s own
	 * canonical-path shape (not the "::"-joined REST wire syntax, which is only ever an INPUT
	 * form - see parseDoubleColonFolderPath()'s own docblock).
	 */
	public function testListAllFoldersThreadsAnAccumulatedPathThroughEveryLevel()
	{
		$session = new FakeJmapSessionForFolderWalk();

		$folders = $this->invokeApiHandler('listAllFolders', [$session, true, null]);

		$pathsById = array_combine(array_column($folders, 'id'), array_column($folders, 'path'));
		$this->assertSame(['A' => 'A', 'A1' => 'A/A1', 'B' => 'B'], $pathsById);
	}

	// --- Api\Jmap\Type::query()/get() widened argument-building ---------------------------------

	public function testTypeQueryForwardsPositionLimitAndCalculateTotal()
	{
		$session = new FakeJmapBase();
		$session->email->query(['inMailbox' => 'x'], [['property' => 'receivedAt', 'isAscending' => false]], 10, 25, true);

		$this->assertSame('Email/query', $session->calls[0][0]);
		$this->assertSame([
			'accountId' => 'acc',
			'filter' => ['inMailbox' => 'x'],
			'sort' => [['property' => 'receivedAt', 'isAscending' => false]],
			'position' => 10,
			'limit' => 25,
			'calculateTotal' => true,
		], $session->calls[0][1]);
	}

	public function testTypeQueryOmitsPaginationArgsWhenNotGiven()
	{
		$session = new FakeJmapBase();
		$session->mailbox->query(['parentId' => null]);

		$this->assertArrayNotHasKey('position', $session->calls[0][1]);
		$this->assertArrayNotHasKey('limit', $session->calls[0][1]);
		$this->assertArrayNotHasKey('calculateTotal', $session->calls[0][1]);
	}

	public function testTypeGetForwardsFetchAllBodyValuesOnlyWhenTrue()
	{
		$session = new FakeJmapBase();
		$session->email->get(['1'], ['subject'], true);
		$this->assertTrue($session->calls[0][1]['fetchAllBodyValues']);

		$session2 = new FakeJmapBase();
		$session2->mailbox->get(['1'], ['name']);
		$this->assertArrayNotHasKey('fetchAllBodyValues', $session2->calls[0][1]);
	}

	/**
	 * Regression coverage for a real bug found live 2026-09-10: GET .../emails/<emailId> was a
	 * hard 500 ("Email/get without a preceding Email/query or a mailboxId..."), and attachment
	 * downloads silently lost their real filename/type - both getEmail()/getAttachment() call
	 * Email/get standalone (no listing first), so the shim has no other way to know which mailbox
	 * to fetch from. Type::get() needed a new $mailboxId param to carry it - RFC 8620 §3.6.1 means
	 * it must only ever be SENT for a shim session, never a real JMAP one.
	 */
	public function testTypeGetForwardsMailboxIdOnlyWhenGiven()
	{
		$session = new FakeJmapBase();
		$session->email->get(['1'], ['subject'], false, base64_encode('INBOX/Sub'));
		$this->assertSame(base64_encode('INBOX/Sub'), $session->calls[0][1]['mailboxId']);

		$session2 = new FakeJmapBase();
		$session2->email->get(['1'], ['subject']);
		$this->assertArrayNotHasKey('mailboxId', $session2->calls[0][1]);
	}

	// --- Api\Mail\Jmap\Imap::emailFromFetch()'s new mailboxIds field ----------------------------

	public function testEmailFromFetchPopulatesMailboxIdsForANonInboxFolder()
	{
		// getNameSpaceArray() is EGroupware's own addition (api/src/Mail/Imap.php), not part of
		// the real Horde_Imap_Client_Socket - mock the app's own subclass, same as
		// JmapShimMailboxGetTest.php's mockImap() already does, not the raw Horde class.
		$imap = $this->getMockBuilder(\EGroupware\Api\Mail\Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(['getNameSpaceArray'])
			->getMock();
		$imap->method('getNameSpaceArray')->willReturn([
			'personal' => [['delimiter' => '.', 'name' => '']],
			'others' => [['delimiter' => '/', 'name' => 'user']],
		]);

		$data = new \Horde_Imap_Client_Data_Fetch();
		// wantPreview/wantBody/wantMdn/wantBlobId/wantContentType/wantThreadHeaders all false -
		// isolates this assertion to just the new, unconditional mailboxIds computation
		$email = JmapShim::emailFromFetch($imap, 'INBOX.Project', '42', $data,
			false, false, false, false, false, false);

		$this->assertSame([base64_encode('INBOX/Project') => true], $email['mailboxIds']);
	}

	public function testEmailFromFetchMailboxIdsUsesTheInboxFastPathWithoutTouchingImap()
	{
		// a bare stub with NO configured methods - if this didn't hit canonicalPath()'s "INBOX"
		// fast path, any call on it would return null and likely error deep in namespace lookup
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		$data = new \Horde_Imap_Client_Data_Fetch();

		$email = JmapShim::emailFromFetch($imap, 'INBOX', '1', $data, false, false, false, false, false, false);

		$this->assertSame([base64_encode('INBOX') => true], $email['mailboxIds']);
	}
}

/**
 * A minimal, never-actually-connected instance of the REAL Api\Mail\Jmap\Http class - only so
 * isRealJmapSession()'s `instanceof Api\Mail\Jmap\Http` check sees a genuine match. Http's real
 * constructor does a live HTTP bootstrap, so it's overridden to a no-op, same technique
 * TransportSendTest.php's own fakeHttp() already established for this exact class.
 */
class FakeRealJmapHttpSession extends \EGroupware\Api\Mail\Jmap\Http
{
	public function __construct() {}
}

/**
 * Fake Api\Jmap\Base + minimal Mailbox Type double for testListAllFolders*() above - a tiny
 * in-memory 3-node tree (root: A, B; A: A1), recording every query() filter it was called with.
 */
class FakeJmapSessionForFolderWalk extends JmapBase
{
	protected array $types = ['mailbox' => FakeJmapMailboxForFolderWalk::class];
	public string $accountId = 'acc';
}

class FakeJmapMailboxForFolderWalk extends JmapType
{
	const TYPE_NAME = 'Mailbox';
	public array $queryCalls = [];

	private const TREE = [
		'A' => ['id' => 'A', 'name' => 'A', 'parentId' => null],
		'B' => ['id' => 'B', 'name' => 'B', 'parentId' => null],
		'A1' => ['id' => 'A1', 'name' => 'A1', 'parentId' => 'A'],
	];

	public function query(array $filter=[], array $sort=[], ?int $position=null, ?int $limit=null, bool $calculateTotal=false) : array
	{
		$this->queryCalls[] = $filter;
		$parentId = $filter['parentId'] ?? null;
		$ids = array_keys(array_filter(self::TREE, static fn($node) => $node['parentId'] === $parentId));
		return ['ids' => $ids];
	}

	public function get(?array $ids=null, ?array $properties=null, bool $fetchAllBodyValues=false, ?string $mailboxId=null) : array
	{
		return ['list' => array_values(array_intersect_key(self::TREE, array_flip((array)$ids)))];
	}
}

/**
 * Fake Api\Jmap\Base for the Api\Jmap\Type::query()/get() argument-building tests above -
 * captures every call() invocation instead of actually sending anything anywhere.
 */
class FakeJmapBase extends JmapBase
{
	protected array $types = ['mailbox' => FakeJmapMailboxTypeCapturingCalls::class, 'email' => FakeJmapEmailTypeCapturingCalls::class];
	public string $accountId = 'acc';
	public array $calls = [];

	public function call(string $method, array $args) : array
	{
		$this->calls[] = [$method, $args];
		return [];
	}
}

class FakeJmapMailboxTypeCapturingCalls extends JmapType
{
	const TYPE_NAME = 'Mailbox';
}

/**
 * Fake Api\Jmap\Base + minimal Mailbox Type double for resolveFolderId()'s "::"-path tests above -
 * getMailboxId() answers from a constructor-supplied path=>id lookup table (empty/missing => null,
 * same contract as both real getMailboxId() implementations), recording every path it was asked
 * about into $mailbox->getMailboxIdCalls.
 */
class FakeJmapSessionWithMailboxIdLookup extends JmapBase
{
	protected array $types = ['mailbox' => FakeJmapMailboxWithMailboxIdLookup::class];
	public string $accountId = 'acc';

	public function __construct(array $pathToId)
	{
		FakeJmapMailboxWithMailboxIdLookup::$pathToId = $pathToId;
	}
}

class FakeJmapMailboxWithMailboxIdLookup extends JmapType
{
	const TYPE_NAME = 'Mailbox';
	public static array $pathToId = [];
	public array $getMailboxIdCalls = [];

	public function getMailboxId(string $folder) : ?string
	{
		$this->getMailboxIdCalls[] = $folder;
		return self::$pathToId[$folder] ?? null;
	}
}

class FakeJmapEmailTypeCapturingCalls extends JmapType
{
	const TYPE_NAME = 'Email';
}
