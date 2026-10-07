<?php
/**
 * EGroupware Mail: tests for the uid embedded in unpacked TNEF sub-attachments' "is_winmail" fingerprint
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Mail\RowIdParts;
use EGroupware\Api\Mail\Jmap\Imap as JmapImap;
use EGroupware\Mail\Ui\AttachmentJmap;
use PHPUnit\Framework\TestCase;

/**
 * An unpacked winmail.dat sub-attachment is addressed by "<uid>@<partID>@<mimeId>"; Api\Mail::
 * getAttachment() only finds it again if that uid is the row's real message UID. Pure tests, no
 * session, database or IMAP connection needed.
 */
class TnefFingerprintUidTest extends TestCase
{
	/**
	 * A plain-IMAP row, as the preview's partID/blobId fast path sees it: nothing resolved yet,
	 * msgUID only available through the row-id parts.
	 */
	public function testPlainImapRowUsesNumericMessageUid()
	{
		$parts = new RowIdParts(['folderID' => null, 'emailID' => null, 'is_jmap' => false],
			fn() => ['folder' => 'INBOX', 'msgUID' => '4711']);

		$this->assertSame('4711', AttachmentJmap::tnefFingerprintUid(null, $parts));
	}

	public function testAlreadyResolvedUidWins()
	{
		$parts = new RowIdParts(['folderID' => null, 'emailID' => null, 'is_jmap' => false],
			fn() => ['folder' => 'INBOX', 'msgUID' => '1']);

		$this->assertSame('4711', AttachmentJmap::tnefFingerprintUid('4711', $parts));
	}

	/**
	 * A real JMAP row must not trigger the IMAP EMAILID search a numeric UID would need.
	 */
	public function testRealJmapRowUsesEmailIdWithoutResolvingUid()
	{
		$parts = new RowIdParts(['folderID' => 'f1', 'emailID' => 'Mabc123', 'is_jmap' => true],
			function()
			{
				throw new \LogicException('msgUID must not be resolved for a JMAP-only row');
			});

		$this->assertSame('Mabc123', AttachmentJmap::tnefFingerprintUid(null, $parts));
	}

	public function testNothingKnownGivesEmptyString()
	{
		$this->assertSame('', AttachmentJmap::tnefFingerprintUid(null, ['emailID' => null, 'is_jmap' => false]));
	}

	/**
	 * End to end for the fingerprint itself: the value the click handler compares against
	 * ("<uid>@<partID>@<mimeId>") must not start with an empty uid.
	 */
	public function testSubAttachmentFingerprintCarriesTheUid()
	{
		$decoded = new \Horde_Mime_Part();
		$decoded->setType('multipart/mixed');
		foreach (['example.ics' => 'text/calendar', 'example.pdf' => 'application/pdf'] as $name => $type)
		{
			$part = new \Horde_Mime_Part();
			$part->setType($type);
			$part->setName($name);
			$part->setContents('x');
			$decoded->addPart($part);
		}
		$decoded->buildMimeIds();
		$parts = new RowIdParts(['folderID' => null, 'emailID' => null, 'is_jmap' => false],
			fn() => ['folder' => 'INBOX', 'msgUID' => '4711']);

		$attachments = JmapImap::tnefAttachments(AttachmentJmap::tnefFingerprintUid(null, $parts), '2', $decoded);

		$this->assertCount(2, $attachments);
		$this->assertSame('4711@2@0', $attachments[0]['is_winmail']);
		$this->assertSame('4711@2@1', $attachments[1]['is_winmail']);
	}
}
