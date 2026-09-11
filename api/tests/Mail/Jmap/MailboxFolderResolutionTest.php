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

	public function testGetMailboxIdChainsOneQueryPerPathSegmentViaParentIdBackReferences()
	{
		$session = $this->fakeSession('acc1', function() {
			return ['methodResponses' => [
				['Mailbox/query', ['ids' => ['inbox-id']], '0'],
				['Mailbox/query', ['ids' => ['sub-id']], '1'],
			]];
		});
		$mailbox = new Mailbox($session);

		$id = $mailbox->getMailboxId('INBOX/Sub');

		$this->assertSame('sub-id', $id, "must return the LAST segment's own id, not an ancestor's");
		$this->assertCount(1, $session->calls, "all segments are sent as one chained batch, not one jmapCall() per segment");
		$methodCalls = $session->calls[0];
		$this->assertSame(['Mailbox/query', ['accountId' => 'acc1', 'filter' => ['name' => 'INBOX']], '0'], $methodCalls[0]);
		$this->assertSame('Mailbox/query', $methodCalls[1][0]);
		$this->assertSame('acc1', $methodCalls[1][1]['accountId']);
		$this->assertSame(['name' => 'Sub'], $methodCalls[1][1]['filter']);
		$this->assertSame(['name' => 'Mailbox/query', 'path' => '/ids', 'resultOf' => '0'], $methodCalls[1][1]['#parentId'],
			"the second segment's query must be scoped to the first segment's own result via a JMAP back-reference");
	}

	/**
	 * Regression test for a bug found while writing this coverage (2026-09-09): each segment's
	 * own #parentId.resultOf was referencing ITS OWN about-to-be-assigned call id instead of the
	 * PRECEDING segment's id - a self-reference RFC 8620 §3.7 forbids (resultOf must name an
	 * earlier method call), which would never actually resolve against a real JMAP server for any
	 * folder path more than one segment deep. Three segments makes the off-by-one visible: a
	 * naive "always reference call 0" fix would also pass the two-segment test above.
	 */
	public function testGetMailboxIdEachSegmentReferencesTheImmediatelyPrecedingSegmentNotAlwaysTheFirst()
	{
		$session = $this->fakeSession('acc1', function() {
			return ['methodResponses' => [
				['Mailbox/query', ['ids' => ['a-id']], '0'],
				['Mailbox/query', ['ids' => ['b-id']], '1'],
				['Mailbox/query', ['ids' => ['c-id']], '2'],
			]];
		});
		$mailbox = new Mailbox($session);

		$mailbox->getMailboxId('A/B/C');

		$methodCalls = $session->calls[0];
		$this->assertSame('0', $methodCalls[1][1]['#parentId']['resultOf'], "segment 'B' must reference segment 'A' (call 0)");
		$this->assertSame('1', $methodCalls[2][1]['#parentId']['resultOf'], "segment 'C' must reference segment 'B' (call 1), not call 0");
	}

	public function testGetMailboxIdSingleSegmentPathHasNoParentIdBackReference()
	{
		$session = $this->fakeSession('acc1', function() {
			return ['methodResponses' => [['Mailbox/query', ['ids' => ['inbox-id']], '0']]];
		});
		$mailbox = new Mailbox($session);

		$mailbox->getMailboxId('INBOX');

		$this->assertArrayNotHasKey('#parentId', $session->calls[0][0][1]);
	}

	public function testGetMailboxIdUsesAnExplicitAccountIdOverTheSessionsOwnDefault()
	{
		$session = $this->fakeSession('session-default-acc', function() {
			return ['methodResponses' => [['Mailbox/query', ['ids' => ['x']], '0']]];
		});
		$mailbox = new Mailbox($session);

		$mailbox->getMailboxId('INBOX', 'explicit-acc');

		$this->assertSame('explicit-acc', $session->calls[0][0][1]['accountId']);
	}

	public function testGetMailboxIdReturnsNullWhenTheFinalSegmentIsNotFound()
	{
		$session = $this->fakeSession('acc1', function() {
			return ['methodResponses' => [['Mailbox/query', ['ids' => []], '0']]];
		});
		$mailbox = new Mailbox($session);

		$this->assertNull($mailbox->getMailboxId('Nonexistent'));
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
