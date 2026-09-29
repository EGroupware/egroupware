<?php
/**
 * EGroupware API: Mail\Imap\Jmap::splitRowID()'s 'msgUID' fallback for a JMAP-only account
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Mail;

/**
 * Live report 2026-09-29 (ralf, copying an NDN bounce from the real-JMAP/Stalwart test account
 * into a plain-IMAP one): "Email '' not found via Email/get". Root cause: RowIdParts' 'msgUID'
 * key is lazily resolved via emailId2uid() - a REAL raw IMAP EMAILID search - which always
 * returns null for a genuine JMAP-only account (eg. Stalwart, no real IMAP protocol to search at
 * all). Every caller reading 'msgUID' (MessageActionHandler::copyMessages()/flagMessages()/
 * deleteMessages()/sendMDN()/saveMessage(), each passing it straight into an Api\Mail method with
 * its own jmapXxx() fast path expecting a real JMAP id) silently received null/'' instead of
 * something usable. Fixed by falling back to the already-known JMAP Email.id itself instead of
 * null, whenever the real-UID search comes back empty.
 *
 * DB-free bare TestCase, same as JmapCrossAccountTransferTest - relies on Mail\Imap::init_static()
 * now only touching the DB when one is actually available (see that method's own docblock).
 */
class JmapSplitRowIdMsgUidFallbackTest extends \PHPUnit\Framework\TestCase
{
	/** @param ?int $resolvedUid what emailId2uid() should pretend to have found - null = "not found" */
	private function fakeJmapAccount(?int $resolvedUid, string $resolvedFolder = 'INBOX') : Mail\Imap\Jmap
	{
		return new class($resolvedUid, $resolvedFolder) extends Mail\Imap\Jmap
		{
			private ?int $resolvedUid;
			private string $resolvedFolder;
			public function __construct(?int $resolvedUid, string $resolvedFolder)
			{
				$this->resolvedUid = $resolvedUid;
				$this->resolvedFolder = $resolvedFolder;
			}
			protected function emailId2uid(string $emailId, string $messageId, string $folderId, ?string &$folder = null) : ?int
			{
				$folder = $this->resolvedFolder;
				return $this->resolvedUid;
			}
		};
	}

	public function testFallsBackToTheJmapEmailIdWhenNoRealImapUidCanBeResolved()
	{
		$account = $this->fakeJmapAccount(null);

		$parts = $account->splitRowID('mailbox-id-1', 'jjaaaacki');

		$this->assertTrue($parts['is_jmap']);
		$this->assertSame('jjaaaacki', $parts['emailID']);
		$this->assertSame('jjaaaacki', $parts['msgUID'],
			"a JMAP-only account can never resolve a real IMAP UID - callers must still get something usable");
		$this->assertSame('INBOX', $parts['folder']);
	}

	public function testStillUsesTheRealImapUidWhenOneActuallyResolves()
	{
		$account = $this->fakeJmapAccount(42);

		$parts = $account->splitRowID('mailbox-id-1', 'jjaaaacki');

		$this->assertSame('42', $parts['msgUID'], "must prefer a genuinely resolved real UID over the JMAP id fallback");
	}

	/** A numeric uid (classic-fallback row) never goes through the JMAP-id branch at all. */
	public function testNumericUidBypassesTheJmapBranchEntirely()
	{
		$account = $this->fakeJmapAccount(null);

		$parts = $account->splitRowID('INBOX', '123');

		$this->assertFalse($parts['is_jmap']);
		$this->assertSame('123', $parts['msgUID']);
	}
}
