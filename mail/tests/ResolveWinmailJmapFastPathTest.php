<?php
/**
 * EGroupware Mail: end-to-end test for AttachmentJmap::resolveWinmailJmap()'s $partID/$blobId
 * fast path against a real TNEF fixture, for a plain-IMAP (non-JMAP) row
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

use EGroupware\Api\Mail\RowIdParts;
use EGroupware\Api\Mail\Jmap\Imap as JmapImap;
use EGroupware\Mail\Ui\AttachmentJmap;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Ticket #99311 / GitHub PR #302: a winmail.dat-attached calendar invite showed correctly but
 * returned an empty response when clicked from the mail PREVIEW pane (not the full display),
 * specifically for a plain-IMAP account. resolveWinmailJmap()'s $partID/$blobId fast path (used
 * by the preview, see its own docblock) never resolved $uid, so each unpacked sub-attachment's
 * "is_winmail" fingerprint ended up "@<partID>@<mimeId>" - an empty uid, for a plain-IMAP row,
 * since the old code's fallback (`$uid ?? $idParts['emailID'] ?? ''`) only has something real to
 * fall back to for a JMAP row. Api\Mail::getAttachment() later matches a click against
 * "<real IMAP uid>@<partID>@<mimeId>", so nothing matched. Nathan's fix: a dedicated
 * tnefFingerprintUid() helper falls back to the row's real msgUID for a non-JMAP row instead
 * (already unit-tested directly, in isolation, by TnefFingerprintUidTest) - this test instead
 * exercises resolveWinmailJmap() ITSELF, the method that actually wires a rowID through to that
 * helper and on to JmapImap::tnefAttachments(), with a real TNEF fixture. The fingerprint survives
 * one more rename on the way out: createAttachmentBlock() (which resolveWinmailJmap() delegates to
 * for its own return value) copies each attachment's 'is_winmail' input key to its own output
 * key 'winmailFlag' - this test asserts on the latter, the method's actual return shape.
 *
 * Why the $idParts constructor arg exists at all: resolveWinmailJmap() starts with
 * Mail::splitRowID($rowID), which unconditionally resolves $rowID against a real, DB-backed
 * account via Mail::getInstance() - there is no portable way to reach a genuine is_jmap=false
 * row from an automated test without one. The only account reachable under this suite's own
 * PHPUnit login (doc/phpunit.xml) is acc_id=1, which is Stalwart/JMAP (is_jmap=true) - confirmed
 * live: every other configured test-DB account either isn't owned by that login or isn't
 * reachable from this sandbox at all. resolveWinmailJmap()'s own optional, test-only $idParts
 * parameter (defaulting to null - every real caller is unaffected) exists specifically to let
 * this test supply a synthetic is_jmap=false RowIdParts directly, sidestepping that wall while
 * still running the method's REAL fast-path logic (fetchBlobBytes()/Mail::tnef_decoder()/
 * JmapImap::tnefAttachments()) against a real, genuine TNEF file.
 *
 * The TNEF bytes themselves are a real-world fixture already shipped with the repo for an
 * unrelated purpose (egroupware/compress's own Horde_Compress_TnefTest, which asserts the
 * Horde_Mime_Part it decodes to is a text/calendar part named "Test Meeting" - that assertion only
 * looks at the fixture's first child; it actually has a second, an application/rtf fallback body
 * named "Untitled.rtf") - reused here via fetchBlobBytes()'s zero-network "upload:<token>" branch
 * (JmapImap::uploadPath()'s own temp-file convention), so this test needs no real IMAP/JMAP
 * connection either.
 *
 * Extends AppTest (not plain TestCase) for its bootstrapped $GLOBALS['egw'] - the fixture decodes
 * to a text/calendar part, and createAttachmentBlock()'s own calendar-popup branch calls
 * Api\Egw::link(), which is null without it. This does NOT reopen the account-resolution wall
 * above: both resolveWinmailJmap() and createAttachmentBlock() take the injected $idParts instead
 * of re-deriving one from $rowID via Mail::splitRowID(), regardless of which account is logged in.
 */
class ResolveWinmailJmapFastPathTest extends \EGroupware\Api\AppTest
{
	private const FIXTURE = __DIR__.'/../../vendor/egroupware/compress/test/Horde/Compress/fixtures/winmail2.dat';

	private string $uploadPath;
	private string $uploadToken;

	protected function setUp() : void
	{
		if (!is_file(self::FIXTURE))
		{
			$this->markTestSkipped('winmail2.dat fixture not present (vendor/ not installed?)');
		}
		$this->uploadToken = bin2hex(random_bytes(16));
		$this->uploadPath = JmapImap::uploadPath($this->uploadToken);
		copy(self::FIXTURE, $this->uploadPath);
	}

	protected function tearDown() : void
	{
		if (is_file($this->uploadPath))
		{
			unlink($this->uploadPath);
		}
	}

	private function plainImapIdParts(string $msgUID) : RowIdParts
	{
		return new RowIdParts(
			['profileID' => '42', 'folderID' => null, 'emailID' => null, 'is_jmap' => false],
			fn() => ['folder' => 'INBOX', 'msgUID' => $msgUID]
		);
	}

	public function testUnpackedCalendarInviteCarriesTheRealMessageUidInItsFingerprint()
	{
		$attachments = AttachmentJmap::resolveWinmailJmap(
			'mail::42::42::INBOX::4711', '2', 'upload:'.$this->uploadToken, $this->plainImapIdParts('4711')
		);

		$this->assertIsArray($attachments);
		$this->assertCount(2, $attachments, 'the fixture decodes to the invite plus an rtf fallback body');
		$this->assertSame('text/calendar', $attachments[0]['type']);
		$this->assertSame('Test Meeting', $attachments[0]['filename']);
		$this->assertSame('4711@2@0', $attachments[0]['winmailFlag'],
			'ticket #99311: must carry the REAL message uid, not an empty prefix ("@2@0") - '.
			'that empty-uid fingerprint is exactly what Api\Mail::getAttachment() could never '.
			'match against, returning an empty response for the unpacked invite');
	}

	public function testDifferentPartIdIsReflectedInTheFingerprintToo()
	{
		$attachments = AttachmentJmap::resolveWinmailJmap(
			'mail::42::42::INBOX::4711', '5', 'upload:'.$this->uploadToken, $this->plainImapIdParts('4711')
		);

		$this->assertSame('4711@5@0', $attachments[0]['winmailFlag']);
	}

	public function testMissingBlobReturnsNullInsteadOfThrowing()
	{
		$result = AttachmentJmap::resolveWinmailJmap(
			'mail::42::42::INBOX::4711', '2', 'upload:'.bin2hex(random_bytes(16)), $this->plainImapIdParts('4711')
		);

		$this->assertNull($result, 'a vanished/never-uploaded blob must fall through, not error out');
	}

	public function testNoAccountIdInTheRowIdReturnsNullBeforeTouchingTheBlobAtAll()
	{
		$idParts = new RowIdParts(['profileID' => null, 'folderID' => null, 'emailID' => null, 'is_jmap' => false],
			function() { throw new \LogicException('msgUID must not be resolved without a profileID'); });

		$result = AttachmentJmap::resolveWinmailJmap('mail::::', '2', 'upload:'.$this->uploadToken, $idParts);

		$this->assertNull($result);
	}
}
