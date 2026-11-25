<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\Mailbox's folder-path <-> Mailbox-id resolution
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Jmap\Base;

/**
 * doc/ai/projects/mail-test-coverage.md's priority-2 entry: the real-JMAP-facing layer has
 * essentially zero tests - Mailbox.php's getMailboxId()/folderId2path() (chained per-segment/
 * per-level `#`-reference JMAP batch calls) had none. Both only ever call $this->jmap->jmapCall()
 * (never touch a real HTTP/IMAP connection directly), so a minimal fake session standing in for
 * Api\Jmap - the only concrete Base subclass that actually implements jmapCall() - is enough to
 * test the request-shape/response-parsing logic in isolation.
 *
 * getMailboxId()'s test cases were rewritten 2026-11-25 twice the same day:
 * - First for the Mailbox/query+get+exact-match fix (Nordenham/stesie01 "Fälle" vs "Sterbefälle"
 *   substring-collision bug) - each segment became one Mailbox/query (candidate net) + one
 *   Mailbox/get (name+parentId verification), all sent as a single batched jmapCall().
 * - Then AGAIN, same day, for ticket #128691 (Dovecot/shim accounts: "Folder 'INBOX/Trash' not
 *   found"/"Folder 'user/fm' not found" on every nested path) - that first fix's batched form
 *   never put a resolved parentId into any segment beyond the first one's own query filter, which
 *   `Mail\Jmap\Imap::mailboxQuery()` (the local plain-IMAP shim's emulation) REQUIRES to do
 *   anything beyond a bogus top-level-only path synthesis - see Mailbox.php's own docblock for the
 *   full writeup. Each segment now gets its own jmapCall() round trip (one segment's resolved id
 *   is needed to build the NEXT segment's own filter.parentId), and that filter is asserted
 *   explicitly in several tests below.
 *
 * folderId2path() caches its result in a function-static array keyed by folderId, for the
 * lifetime of the PHP process - every test here therefore uses its own never-reused folderId, to
 * stay independent of test execution order/count (see testFolderId2PathCachesItsResultAcrossCalls
 * for the one test that deliberately exercises the cache itself).
 */
class MailboxFolderResolutionTest extends \PHPUnit\Framework\TestCase
{
	private function fakeSession(string $accountId, callable $responder) : Base
	{
		return new class($accountId, $responder) extends Base {
			public array $calls = [];
			public function __construct(public string $accountId, private $responder) {}
			public function jmapCall(array $methodCalls, $using=null, bool $emulate=false)
			{
				$this->calls[] = $methodCalls;
				return ($this->responder)($methodCalls, count($this->calls));
			}
		};
	}

	/**
	 * Builds one segment's own Mailbox/query+get response - getMailboxId() now issues one
	 * jmapCall() round trip PER segment (callIds are always plain 'q'/'g', not 'q0'/'g1' etc.,
	 * since each round trip is independent).
	 */
	private function segmentResponse(array $candidates) : array
	{
		return ['methodResponses' => [
			['Mailbox/query', ['ids' => array_column($candidates, 'id')], 'q'],
			['Mailbox/get', ['list' => $candidates], 'g'],
		]];
	}

	public function testGetMailboxIdIssuesOneQueryPlusGetRoundTripPerPathSegment()
	{
		$session = $this->fakeSession('acc1', function($methodCalls, $callNum) {
			return $callNum === 1
				? $this->segmentResponse([['id' => 'inbox-id', 'name' => 'INBOX', 'parentId' => null]])
				: $this->segmentResponse([['id' => 'sub-id', 'name' => 'Sub', 'parentId' => 'inbox-id']]);
		});
		$mailbox = new Mailbox($session);

		$id = $mailbox->getMailboxId('INBOX/Sub');

		$this->assertSame('sub-id', $id, "must return the LAST segment's own id, not an ancestor's");
		$this->assertCount(2, $session->calls,
			"one jmapCall() round trip per path segment - a later segment's own query needs the " .
			"earlier segment's already-RESOLVED id as its filter.parentId, which isn't known until " .
			"that earlier round trip's response comes back, so they can't be sent as one batch");
		$this->assertSame(['Mailbox/query', ['accountId' => 'acc1', 'filter' => ['name' => 'INBOX', 'parentId' => null], 'limit' => 50], 'q'],
			$session->calls[0][0]);
		$this->assertSame('Mailbox/get', $session->calls[0][1][0]);
		$this->assertSame(['name' => 'Mailbox/query', 'path' => '/ids', 'resultOf' => 'q'], $session->calls[0][1][1]['#ids'],
			"the Mailbox/get verifying segment 0's candidates must back-reference segment 0's own query");
		$this->assertSame(['Mailbox/query', ['accountId' => 'acc1', 'filter' => ['name' => 'Sub', 'parentId' => 'inbox-id'], 'limit' => 50], 'q'],
			$session->calls[1][0],
			"segment 1's own query filter must carry segment 0's resolved id as parentId - required " .
			"by the local shim's mailboxQuery(), see Mailbox.php's own docblock (ticket #128691)");
	}

	/**
	 * Regression test (2026-11-25, Nordenham/stesie01 live report): RFC 8621's Mailbox/query
	 * "name" filter is a case-insensitive SUBSTRING match, so querying for 'Fälle' also returns
	 * the sibling 'Sterbefälle'. Taking ids[0] picked whichever of the two the server happened to
	 * list first - here 'Sterbefälle', so opening "Fälle" showed "Sterbefälle"'s messages. Both
	 * are top-level (parentId=null), so a parentId filter can't disambiguate them either; only an
	 * exact name-equality check (via the follow-up Mailbox/get) can.
	 */
	public function testGetMailboxIdPicksTheExactNameNotJustTheFirstSubstringMatch()
	{
		$session = $this->fakeSession('acc1', function() {
			return $this->segmentResponse([
				['id' => 'sterbefaelle-id', 'name' => 'Sterbefälle', 'parentId' => null],
				['id' => 'faelle-id', 'name' => 'Fälle', 'parentId' => null],
			]);
		});
		$mailbox = new Mailbox($session);

		$this->assertSame('faelle-id', $mailbox->getMailboxId('Fälle'));
	}

	/** The reverse collision: querying for the longer name must not match the shorter sibling. */
	public function testGetMailboxIdSubstringCollisionResolvesCorrectlyInEitherDirection()
	{
		$session = $this->fakeSession('acc1', function() {
			return $this->segmentResponse([
				['id' => 'sterbefaelle-id', 'name' => 'Sterbefälle', 'parentId' => null],
				['id' => 'faelle-id', 'name' => 'Fälle', 'parentId' => null],
			]);
		});
		$mailbox = new Mailbox($session);

		$this->assertSame('sterbefaelle-id', $mailbox->getMailboxId('Sterbefälle'));
	}

	/**
	 * A deeper path's final segment must also be checked against the CORRECT parentId, not just
	 * matched by name - two same-named folders under different parents must not cross-resolve.
	 */
	public function testGetMailboxIdMatchesExactParentIdNotJustExactName()
	{
		$session = $this->fakeSession('acc1', function($methodCalls, $callNum) {
			return $callNum === 1
				? $this->segmentResponse([['id' => 'parent-a', 'name' => 'ParentA', 'parentId' => null]])
				// same leaf name under a DIFFERENT, unrelated parent - must be rejected
				: $this->segmentResponse([
					['id' => 'wrong-child', 'name' => 'Child', 'parentId' => 'parent-b'],
					['id' => 'right-child', 'name' => 'Child', 'parentId' => 'parent-a'],
				]);
		});
		$mailbox = new Mailbox($session);

		$this->assertSame('right-child', $mailbox->getMailboxId('ParentA/Child'));
	}

	/**
	 * Ticket #128691: each segment beyond the first must carry the PRECEDING segment's own
	 * resolved id as its query's filter.parentId - the local plain-IMAP shim's mailboxQuery()
	 * synthesizes a bogus TOP-LEVEL-only path otherwise (eg. "C" instead of "A/B/C"), which this
	 * method's own exact-match-by-parentId check then correctly rejects as "not found" - see
	 * Mailbox.php's own docblock for the full writeup.
	 */
	public function testGetMailboxIdScopesEachSegmentsQueryToThePrecedingSegmentsResolvedId()
	{
		$session = $this->fakeSession('acc1', function($methodCalls, $callNum) {
			return match ($callNum) {
				1 => $this->segmentResponse([['id' => 'a-id', 'name' => 'A', 'parentId' => null]]),
				2 => $this->segmentResponse([['id' => 'b-id', 'name' => 'B', 'parentId' => 'a-id']]),
				3 => $this->segmentResponse([['id' => 'c-id', 'name' => 'C', 'parentId' => 'b-id']]),
			};
		});
		$mailbox = new Mailbox($session);

		$id = $mailbox->getMailboxId('A/B/C');

		$this->assertSame('c-id', $id);
		$this->assertSame(['name' => 'A', 'parentId' => null], $session->calls[0][0][1]['filter']);
		$this->assertSame(['name' => 'B', 'parentId' => 'a-id'], $session->calls[1][0][1]['filter']);
		$this->assertSame(['name' => 'C', 'parentId' => 'b-id'], $session->calls[2][0][1]['filter']);
	}

	public function testGetMailboxIdUsesAnExplicitAccountIdOverTheSessionsOwnDefault()
	{
		$session = $this->fakeSession('session-default-acc', function() {
			return $this->segmentResponse([['id' => 'x', 'name' => 'INBOX', 'parentId' => null]]);
		});
		$mailbox = new Mailbox($session);

		$mailbox->getMailboxId('INBOX', 'explicit-acc');

		$this->assertSame('explicit-acc', $session->calls[0][0][1]['accountId']);
	}

	public function testGetMailboxIdReturnsNullWhenTheFinalSegmentIsNotFound()
	{
		$session = $this->fakeSession('acc1', function() {
			return $this->segmentResponse([]);
		});
		$mailbox = new Mailbox($session);

		$this->assertNull($mailbox->getMailboxId('Nonexistent'));
	}

	/**
	 * Even if a LATER segment's candidates would contain a name match, an unresolved EARLIER
	 * segment must short-circuit to null rather than letting a later-segment match through - and,
	 * since each segment is now its own round trip, must never even ISSUE that later segment's
	 * jmapCall() at all.
	 */
	public function testGetMailboxIdReturnsNullWhenAnIntermediateSegmentIsNotFound()
	{
		$session = $this->fakeSession('acc1', function() {
			return $this->segmentResponse([]);
		});
		$mailbox = new Mailbox($session);

		$this->assertNull($mailbox->getMailboxId('Missing/Sub'));
		$this->assertCount(1, $session->calls, "segment 1 ('Sub') must never be queried once segment 0 ('Missing') fails to resolve");
	}

	public function testFolderId2PathWalksUpToTheRootAccumulatingEachAncestorsName()
	{
		$session = $this->fakeSession('acc1', function() {
			return ['methodResponses' => [
				['Mailbox/get', ['list' => [['name' => 'Sub', 'parentId' => 'inbox-id-1']]], 'f0'],
				['Mailbox/get', ['list' => [['name' => 'Inbox', 'parentId' => null]]], 'f1'],
				['Mailbox/get', ['list' => []], 'f2'],
				['Mailbox/get', ['list' => []], 'f3'],
			]];
		});
		$mailbox = new Mailbox($session);

		// documenting actual current behaviour, not necessarily ideal: the "normalize to
		// uppercase INBOX" rule only fires for the FIRST name appended overall (see the next
		// test) - here that's the leaf "Sub", so the root ancestor keeps the server's own
		// "Inbox" capitalization instead of being normalized to "INBOX" like a direct root query
		// gets. Pre-existing behaviour, not something this test-coverage pass changes.
		$this->assertSame('Inbox/Sub', $mailbox->folderId2path('nested-fid-cov1'));
	}

	public function testFolderId2PathNormalizesInboxToUppercaseWhenItIsTheDirectlyQueriedFolder()
	{
		$session = $this->fakeSession('acc1', function() {
			return ['methodResponses' => [
				['Mailbox/get', ['list' => [['name' => 'Inbox', 'parentId' => null]]], 'f0'],
				['Mailbox/get', ['list' => []], 'f1'],
				['Mailbox/get', ['list' => []], 'f2'],
				['Mailbox/get', ['list' => []], 'f3'],
			]];
		});
		$mailbox = new Mailbox($session);

		$this->assertSame('INBOX', $mailbox->folderId2path('root-fid-cov2'));
	}

	public function testFolderId2PathIssuesASecondBatchWhenTheHierarchyIsDeeperThanFourLevels()
	{
		$session = $this->fakeSession('acc1', function($methodCalls, $callNum) {
			if ($callNum === 1)
			{
				return ['methodResponses' => [
					['Mailbox/get', ['list' => [['name' => 'E', 'parentId' => 'd-id']]], 'f0'],
					['Mailbox/get', ['list' => [['name' => 'D', 'parentId' => 'c-id']]], 'f1'],
					['Mailbox/get', ['list' => [['name' => 'C', 'parentId' => 'b-id']]], 'f2'],
					['Mailbox/get', ['list' => [['name' => 'B', 'parentId' => 'a-id']]], 'f3'],
				]];
			}
			return ['methodResponses' => [
				['Mailbox/get', ['list' => [['name' => 'A', 'parentId' => null]]], 'f0'],
				['Mailbox/get', ['list' => []], 'f1'],
				['Mailbox/get', ['list' => []], 'f2'],
				['Mailbox/get', ['list' => []], 'f3'],
			]];
		});
		$mailbox = new Mailbox($session);

		$path = $mailbox->folderId2path('deep-fid-cov3');

		$this->assertSame('A/B/C/D/E', $path);
		$this->assertCount(2, $session->calls, "a 5-level-deep hierarchy needs a second 4-level batch to reach the root");
	}

	public function testFolderId2PathReturnsAnEmptyStringWhenTheFolderCannotBeResolvedAtAll()
	{
		$session = $this->fakeSession('acc1', function() {
			return ['methodResponses' => [
				['Mailbox/get', ['list' => []], 'f0'],
				['Mailbox/get', ['list' => []], 'f1'],
				['Mailbox/get', ['list' => []], 'f2'],
				['Mailbox/get', ['list' => []], 'f3'],
			]];
		});
		$mailbox = new Mailbox($session);

		$this->assertSame('', $mailbox->folderId2path('gone-fid-cov4'));
	}

	/**
	 * folderId2path()'s own cache is a function-static array, shared process-wide across every
	 * Mailbox instance (not an instance property) - a repeat lookup of the SAME folderId never
	 * calls jmapCall() again, even against a brand new Mailbox/session object.
	 */
	public function testFolderId2PathCachesItsResultAcrossCallsAndEvenAcrossInstances()
	{
		$callCount = 0;
		$responder = function() use (&$callCount) {
			$callCount++;
			return ['methodResponses' => [
				['Mailbox/get', ['list' => [['name' => 'CachedOnce', 'parentId' => null]]], 'f0'],
				['Mailbox/get', ['list' => []], 'f1'],
				['Mailbox/get', ['list' => []], 'f2'],
				['Mailbox/get', ['list' => []], 'f3'],
			]];
		};
		$mailboxA = new Mailbox($this->fakeSession('acc1', $responder));
		$first = $mailboxA->folderId2path('cache-fid-cov5');

		$mailboxB = new Mailbox($this->fakeSession('acc1', $responder));
		$second = $mailboxB->folderId2path('cache-fid-cov5');

		$this->assertSame('CachedOnce', $first);
		$this->assertSame('CachedOnce', $second);
		$this->assertSame(1, $callCount, "the second lookup must be served entirely from the static cache");
	}
}
