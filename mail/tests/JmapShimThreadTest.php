<?php
/**
 * Test EGroupware\Mail\JmapShim's IMAP THREAD emulation (doc/ai/projects/mail-threaded-view.md,
 * Phase 2) - threadCriteria()/threadMap() (private helpers, exercised via ReflectionMethod against
 * a mocked Mail\Imap connection, same style JmapShimMailboxGetTest.php/JmapShimMailboxSetTest.php
 * already use) and jsonPath()'s "*" wildcard-flattening extension (public, pure, no mocking
 * needed).
 *
 * emailQuery()/emailGet()/threadGet() themselves are NOT covered here, for the same reason
 * JmapShimMailboxSetTest.php's own docblock gives for mailboxSet(): they call self::imapServer()
 * internally for any real (non-"0") accountId, which needs a live DB connection this test suite
 * doesn't have - only the private helpers that take an already-constructed $imap as a parameter
 * can be unit-tested here.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

use EGroupware\Api\Mail\Imap;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class JmapShimThreadTest extends \PHPUnit\Framework\TestCase
{
	private function mockImap(array $onlyMethods = []) : Imap
	{
		return $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(array_unique(array_merge(['queryCapability', 'thread'], $onlyMethods)))
			->getMock();
	}

	private function invokePrivate(string $method, array $args)
	{
		$reflection = new \ReflectionMethod(JmapShim::class, $method);
		$reflection->setAccessible(true);
		return $reflection->invoke(null, ...$args);
	}

	/**
	 * threadMap()'s "array &$context" out-param needs invokeArgs() (which passes $args through
	 * without copying), not invoke(null, ...$args) - the "..." spread operator does not preserve
	 * by-reference array elements, so a by-ref parameter silently gets a fresh copy instead of the
	 * caller's actual variable, defeating the whole point of testing memoization.
	 */
	private function invokeThreadMap(\Horde_Imap_Client_Socket $imap, string $mailbox, array &$context, string $accountId)
	{
		$reflection = new \ReflectionMethod(JmapShim::class, 'threadMap');
		$reflection->setAccessible(true);
		return $reflection->invokeArgs(null, [$imap, $mailbox, &$context, $accountId]);
	}

	/**
	 * Build a Horde_Imap_Client_Data_Thread fixture the same shape Horde's own thread() returns:
	 * each $groups entry is one thread, in iteration order [uid => level, ...] - the first entry
	 * is the thread's base/root (Horde_Imap_Client_Data_Thread::getThreads()' own convention, a
	 * single-entry group has no base, i.e. is a singleton thread).
	 */
	private function threadFixture(array $groups) : \Horde_Imap_Client_Data_Thread
	{
		return new \Horde_Imap_Client_Data_Thread(array_values($groups), 'uid');
	}

	// --- jsonPath() "*" wildcard flattening ---------------------------------------------------

	public function testJsonPathPlainPathUnchanged()
	{
		$this->assertSame(['a', 'b'], JmapShim::jsonPath(['ids' => ['a', 'b']], '/ids'));
	}

	public function testJsonPathPlainPathMissingKeyReturnsNull()
	{
		$this->assertNull(JmapShim::jsonPath(['ids' => ['a']], '/nope'));
	}

	public function testJsonPathWildcardFlattensListOfLists()
	{
		// exactly Thread/get -> Email/get's '/list/*/emailIds' shape
		$value = ['list' => [
			['id' => 't1', 'emailIds' => ['1', '2']],
			['id' => 't2', 'emailIds' => ['3']],
		]];
		$this->assertSame(['1', '2', '3'], JmapShim::jsonPath($value, '/list/*/emailIds'));
	}

	public function testJsonPathWildcardCollectsScalarsWithoutFlattening()
	{
		$value = ['list' => [['id' => 't1'], ['id' => 't2']]];
		$this->assertSame(['t1', 't2'], JmapShim::jsonPath($value, '/list/*/id'));
	}

	public function testJsonPathWildcardOnNonListReturnsNull()
	{
		$this->assertNull(JmapShim::jsonPath(['list' => 'not-a-list'], '/list/*/id'));
	}

	public function testJsonPathWildcardSkipsMissingFieldsPerItem()
	{
		// one item genuinely has no emailIds - must not throw or insert a null placeholder
		$value = ['list' => [
			['id' => 't1', 'emailIds' => ['1']],
			['id' => 't2'],
		]];
		$this->assertSame(['1'], JmapShim::jsonPath($value, '/list/*/emailIds'));
	}

	// --- threadCriteria() ----------------------------------------------------------------------

	public function testThreadCriteriaPrefersReferencesOverRefs()
	{
		$imap = $this->mockImap();
		$imap->method('queryCapability')->willReturn(['ORDEREDSUBJECT', 'REFERENCES', 'REFS']);

		$this->assertSame(\Horde_Imap_Client::THREAD_REFERENCES, $this->invokePrivate('threadCriteria', [$imap]));
	}

	public function testThreadCriteriaFallsBackToRefsWithoutReferences()
	{
		$imap = $this->mockImap();
		$imap->method('queryCapability')->willReturn(['ORDEREDSUBJECT', 'REFS']);

		$this->assertSame(\Horde_Imap_Client::THREAD_REFS, $this->invokePrivate('threadCriteria', [$imap]));
	}

	/**
	 * doc/ai/projects/mail-threaded-view.md Phase 1 decision: THREAD=ORDEREDSUBJECT-only is
	 * deliberately never treated as "supported" - too weak (subject+date only) to offer as
	 * threading support at all.
	 */
	public function testThreadCriteriaIgnoresOrderedsubjectOnly()
	{
		$imap = $this->mockImap();
		$imap->method('queryCapability')->willReturn(['ORDEREDSUBJECT']);

		$this->assertNull($this->invokePrivate('threadCriteria', [$imap]));
	}

	public function testThreadCriteriaNullWhenNoThreadCapabilityAtAll()
	{
		$imap = $this->mockImap();
		$imap->method('queryCapability')->willReturn(false);

		$this->assertNull($this->invokePrivate('threadCriteria', [$imap]));
	}

	// --- threadMap() -----------------------------------------------------------------------------

	public function testThreadMapAssignsSharedBaseToMultiMessageThread()
	{
		$imap = $this->mockImap();
		$imap->method('queryCapability')->willReturn(['REFERENCES']);
		$imap->expects($this->once())->method('thread')
			->with('INBOX', ['criteria' => \Horde_Imap_Client::THREAD_REFERENCES])
			->willReturn($this->threadFixture([[10 => 0, 11 => 1], [20 => 0]]));

		$context = [];
		$map = $this->invokeThreadMap($imap, 'INBOX', $context, 'acc1');

		$this->assertSame(['10' => '10', '11' => '10', '20' => '20'], $map);
	}

	public function testThreadMapMemoizesPerMailboxWithinOneContext()
	{
		$imap = $this->mockImap();
		$imap->method('queryCapability')->willReturn(['REFERENCES']);
		$imap->expects($this->once())->method('thread')
			->willReturn($this->threadFixture([[10 => 0]]));

		$context = [];
		$first = $this->invokeThreadMap($imap, 'INBOX', $context, 'acc1');
		$second = $this->invokeThreadMap($imap, 'INBOX', $context, 'acc1');

		$this->assertSame($first, $second);
	}

	/**
	 * No server-side THREAD=REFERENCES/REFS at all - every message must fall back to being its own
	 * singleton thread, and the (comparatively expensive) real IMAP THREAD command must never be
	 * issued at all, not even once.
	 */
	public function testThreadMapReturnsEmptyMapWithoutCallingThreadWhenUnsupported()
	{
		$imap = $this->mockImap();
		$imap->method('queryCapability')->willReturn(['ORDEREDSUBJECT']);
		$imap->expects($this->never())->method('thread');

		$context = [];
		$map = $this->invokeThreadMap($imap, 'INBOX', $context, 'acc1');

		$this->assertSame([], $map);
	}

	public function testThreadMapKeepsSeparateMailboxesIndependent()
	{
		$imap = $this->mockImap();
		$imap->method('queryCapability')->willReturn(['REFERENCES']);
		$imap->method('thread')->willReturnMap([
			['INBOX', ['criteria' => \Horde_Imap_Client::THREAD_REFERENCES], $this->threadFixture([[10 => 0]])],
			['Sent', ['criteria' => \Horde_Imap_Client::THREAD_REFERENCES], $this->threadFixture([[20 => 0]])],
		]);

		$context = [];
		$inbox = $this->invokeThreadMap($imap, 'INBOX', $context, 'acc1');
		$sent = $this->invokeThreadMap($imap, 'Sent', $context, 'acc1');

		$this->assertSame(['10' => '10'], $inbox);
		$this->assertSame(['20' => '20'], $sent);
	}
}
