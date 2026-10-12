<?php
/**
 * Test EGroupware\Api\Mail\Imap::_deleteMailbox() preserves the ORIGINAL DELETE failure
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Found live via ticket #126321: Horde_Imap_Client_Socket::_deleteMailbox() (vendor/egroupware/
 * imap-client/lib/Horde/Imap/Client/Socket.php) reacts to a failed DELETE by trying to recover
 * (expunge-and-retry) - if that recovery ALSO fails, Horde throws the recovery attempt's own
 * exception (typically an opaque "Could not open mailbox ..." from its internal openMailbox()
 * call), completely discarding the original, more specific reason the server refused the DELETE
 * in the first place. Imap::_deleteMailbox() re-implements the same recovery structure but
 * re-throws the ORIGINAL exception when recovery fails too, so the real cause reaches the user.
 */
#[AllowMockObjectsWithoutExpectations]
class ImapDeleteMailboxErrorPreservationTest extends TestCase
{
	private function mockImap(array $onlyMethods) : Imap
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(array_unique(array_merge(['getNameSpaceArray', 'login'], $onlyMethods)))
			->getMock();
		$imap->method('getNameSpaceArray')->willReturn([
			'personal' => [['delimiter' => '.', 'name' => '']],
		]);
		$imap->method('login')->willReturn(null);
		return $imap;
	}

	public function testOriginalDeleteFailureSurvivesWhenRecoveryAlsoFails()
	{
		$imap = $this->mockImap(['_sendCmd', 'expunge', '_getMboxFormatOb']);

		$originalFailure = new \Horde_Imap_Client_Exception('Mailbox has children');
		$recoveryFailure = new \Horde_Imap_Client_Exception('Could not open mailbox "INBOX.BV Klement".');

		$imap->method('_getMboxFormatOb')->willReturn('INBOX.BV Klement');
		$imap->expects($this->once())->method('_sendCmd')->willThrowException($originalFailure);
		$imap->expects($this->once())->method('expunge')->willThrowException($recoveryFailure);

		try
		{
			$imap->deleteMailbox('INBOX/BV Klement');
			$this->fail('Expected a Horde_Imap_Client_Exception');
		}
		catch (\Horde_Imap_Client_Exception $e)
		{
			$this->assertSame('Mailbox has children', $e->getMessage(),
				"must surface the ORIGINAL DELETE failure, not the recovery attempt's own opaque error");
		}
	}

	public function testSucceedsNormallyWhenTheFirstDeleteAttemptSucceeds()
	{
		$imap = $this->mockImap(['_sendCmd', '_getMboxFormatOb', '_subscribeMailbox']);

		$imap->method('_getMboxFormatOb')->willReturn('INBOX.BV Klement');
		// deleteMailbox()'s own post-delete cleanup (Base.php's _deleteMailboxPost()) always
		// unsubscribes too - stub it out so this test stays scoped to the DELETE command itself
		$imap->method('_subscribeMailbox')->willReturn(null);
		// only one _sendCmd call expected - no recovery attempt needed when DELETE succeeds first try
		$imap->expects($this->once())->method('_sendCmd');

		$imap->deleteMailbox('INBOX/BV Klement');
	}
}
