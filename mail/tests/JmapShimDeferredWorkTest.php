<?php
/**
 * Test EGroupware\Api\Mail\Jmap\Imap's deferred-work queue (queueDeferredWork()/runDeferredWork()/
 * chunkIds()) - the machinery emailSet()'s bulk move/copy/destroy handling queues its actual IMAP
 * COPY/STORE/EXPUNGE calls onto, run only AFTER the JMAP response has already been sent
 * (mail/jmap.php, via fastcgi_finish_request()). See doc/ai/projects/mail-test-coverage.md's
 * priority-1 entry (bulk move/copy/delete) - emailSet()'s actual IMAP execution branches
 * themselves need a live/mocked Horde_Imap_Client_Socket behind a real (non-"0") account's
 * self::imapServer(), which has no injection seam (same documented limitation
 * JmapShimMailboxGetTest.php/JmapShimThreadTest.php already note for their own class) - but the
 * deferred-work queue itself and the id-chunking helper are pure, DB/IMAP-free, and were
 * previously completely untested despite a real production bug already found in this exact area
 * (2026-09-07: "a bare message alone wasn't enough to even confirm a deferred move had failed at
 * all, let alone why" - the reason runDeferredWork() logs via _egw_log_exception() now).
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

class JmapShimDeferredWorkTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * $deferredWork is a private static array shared process-wide - defensively drain it before
	 * each test so an earlier test (or, in theory, unrelated code sharing the same PHPUnit
	 * process) can never leak queued work into this one. runDeferredWork() itself is what does
	 * the draining, so this is just "run whatever might already be queued and discard the result".
	 */
	protected function setUp() : void
	{
		parent::setUp();
		JmapShim::runDeferredWork();
	}

	private function chunkIds(array $ids) : array
	{
		$method = new \ReflectionMethod(JmapShim::class, 'chunkIds');
		$method->setAccessible(true);
		return $method->invoke(null, $ids);
	}

	public function testChunkIdsSplitsIntoFixedSizeChunksOfFifty()
	{
		$ids = range(1, 125);

		$chunks = $this->chunkIds($ids);

		$this->assertCount(3, $chunks);
		$this->assertCount(50, $chunks[0]);
		$this->assertCount(50, $chunks[1]);
		$this->assertCount(25, $chunks[2]);
		$this->assertSame($ids, array_merge(...$chunks), "every id must appear exactly once, in order, across all chunks");
	}

	public function testChunkIdsReturnsASingleChunkWhenUnderTheLimit()
	{
		$ids = range(1, 10);

		$chunks = $this->chunkIds($ids);

		$this->assertCount(1, $chunks);
		$this->assertSame($ids, $chunks[0]);
	}

	public function testChunkIdsHandlesAnEmptyArray()
	{
		$this->assertSame([], $this->chunkIds([]));
	}

	public function testChunkIdsHandlesExactlyOneChunkBoundary()
	{
		$ids = range(1, 50);

		$chunks = $this->chunkIds($ids);

		$this->assertCount(1, $chunks, "exactly ID_CHUNK_SIZE ids must not spill into a spurious empty second chunk");
	}

	public function testRunDeferredWorkExecutesQueuedWorkInOrderThenClearsTheQueue()
	{
		$order = [];
		JmapShim::queueDeferredWork(function() use (&$order) { $order[] = 'first'; });
		JmapShim::queueDeferredWork(function() use (&$order) { $order[] = 'second'; });

		JmapShim::runDeferredWork();

		$this->assertSame(['first', 'second'], $order);

		// running again with nothing newly queued must not re-run stale work
		JmapShim::runDeferredWork();
		$this->assertSame(['first', 'second'], $order, "the queue must be cleared after running, not re-executed");
	}

	public function testRunDeferredWorkContinuesPastAFailingClosureInsteadOfAbortingTheWholeBatch()
	{
		$ran = [];
		JmapShim::queueDeferredWork(function() { throw new \RuntimeException('simulated IMAP failure'); });
		JmapShim::queueDeferredWork(function() use (&$ran) { $ran[] = 'second still ran'; });

		// must not throw/propagate - a failure here can no longer reach the client (see class
		// docblock/runDeferredWork()'s own docblock: the JMAP response already went out)
		JmapShim::runDeferredWork();

		$this->assertSame(['second still ran'], $ran,
			"one closure throwing must not prevent a LATER queued closure (e.g. a different chunk's ".
			"move, or an unrelated deferred destroy) from still running");
	}

	public function testQueueDeferredWorkAccumulatesAcrossMultipleCalls()
	{
		$count = 0;
		JmapShim::queueDeferredWork(function() use (&$count) { $count++; });
		JmapShim::queueDeferredWork(function() use (&$count) { $count++; });
		JmapShim::queueDeferredWork(function() use (&$count) { $count++; });

		JmapShim::runDeferredWork();

		$this->assertSame(3, $count, "each queueDeferredWork() call must add to the queue, not replace it");
	}
}
