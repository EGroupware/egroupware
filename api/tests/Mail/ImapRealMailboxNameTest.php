<?php
/**
 * Test EGroupware\Api\Mail\Imap's translation of an EGroupware-canonical "/"-joined folder path
 * to the account's real IMAP hierarchy delimiter (JMAP-CANONICAL-PATH-FIX) - see
 * realMailboxName()'s own docblock (api/src/Mail/Imap.php) for the full rationale: every folder
 * id / row-id folder-segment handed to the client always uses "/" regardless of the real server's
 * delimiter, and this connection-object-level translation is what makes every raw IMAP call
 * (openMailbox() etc.) work transparently for a non-"/"-delimited server (eg. ticket #124401,
 * where the customer's real external IMAP server uses "." instead of "/").
 *
 * No live IMAP server is needed - same mocking style as mail/tests/JmapShimMailboxGetTest.php:
 * getNameSpaceArray() is stubbed to report a fixed delimiter, and the protected Horde-internal
 * methods each public wrapper ultimately calls are stubbed too, so the real (non-mocked)
 * openMailbox()/etc. overrides run for real and can be asserted on.
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[AllowMockObjectsWithoutExpectations]
class ImapRealMailboxNameTest extends TestCase
{
	private function mockImap(string $personalDelimiter, array $onlyMethods = []) : Imap
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(array_unique(array_merge(
				['getNameSpaceArray', 'login'],
				$onlyMethods,
			)))
			->getMock();
		$imap->method('getNameSpaceArray')->willReturn([
			'personal' => [['delimiter' => $personalDelimiter, 'name' => '']],
			'others' => [['delimiter' => '.', 'name' => 'user.']],
		]);
		$imap->method('login')->willReturn(null);
		return $imap;
	}

	private function invokeRealMailboxName(Imap $imap, $mailbox)
	{
		$reflection = new ReflectionMethod(Imap::class, 'realMailboxName');
		$reflection->setAccessible(true);
		return $reflection->invoke($imap, $mailbox);
	}

	public function testTranslatesCanonicalPathToRealDelimiter()
	{
		$imap = $this->mockImap('.');
		$this->assertSame('INBOX.Sub.Folder', $this->invokeRealMailboxName($imap, 'INBOX/Sub/Folder'));
	}

	public function testNoOpWhenRealDelimiterIsSlash()
	{
		$imap = $this->mockImap('/');
		$this->assertSame('INBOX/Sub/Folder', $this->invokeRealMailboxName($imap, 'INBOX/Sub/Folder'));
	}

	public function testIdempotentOnAlreadyTranslatedName()
	{
		// applying the translation twice (eg. a caller that already translates, PLUS this
		// connection-level wrapper) must not double-mangle an already-real name
		$imap = $this->mockImap('.');
		$once = $this->invokeRealMailboxName($imap, 'INBOX/Sub/Folder');
		$twice = $this->invokeRealMailboxName($imap, $once);
		$this->assertSame($once, $twice);
	}

	public function testEmptyStringPassesThroughUnchanged()
	{
		$imap = $this->mockImap('.');
		$this->assertSame('', $this->invokeRealMailboxName($imap, ''));
	}

	public function testNonStringPassesThroughUnchanged()
	{
		$imap = $this->mockImap('.');
		$this->assertNull($this->invokeRealMailboxName($imap, null));
		$obj = new \stdClass();
		$this->assertSame($obj, $this->invokeRealMailboxName($imap, $obj));
	}

	public function testArrayIsTranslatedElementwise()
	{
		$imap = $this->mockImap('.');
		$this->assertSame(
			['INBOX.Sub', 'INBOX.Other'],
			$this->invokeRealMailboxName($imap, ['INBOX/Sub', 'INBOX/Other'])
		);
	}

	/**
	 * End-to-end proof for the wrapper pattern itself (not just realMailboxName() in isolation):
	 * openMailbox() is the one the customer's own trace hit directly (ticket #124401) - mock
	 * Horde_Imap_Client_Base's own protected _openMailbox() (what Mail\Imap's real, non-mocked
	 * openMailbox() override ultimately delegates to via parent::openMailbox()) and assert it
	 * receives the REAL-delimiter name, not the canonical "/"-path this test calls with.
	 */
	public function testOpenMailboxTranslatesBeforeReachingHorde()
	{
		$imap = $this->mockImap('.', ['_openMailbox']);
		$imap->expects($this->once())
			->method('_openMailbox')
			->with($this->callback(fn($mailbox) => (string)$mailbox === 'INBOX.Sub.Folder'));

		$imap->openMailbox('INBOX/Sub/Folder');
	}
}
