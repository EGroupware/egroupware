<?php
/**
 * Ticket-driven regression (2026-09-22, ralf, relaying a real customer's PHP error log): a
 * download/blob request whose partId no longer resolves against the message's current
 * Horde_Mime_Part structure crashed the whole request outright - "PHP Fatal error: Uncaught
 * Error: Call to a member function setContents() on null in .../Mail/Jmap/Imap.php:3828" - instead
 * of the graceful null (404-for-download/thrown-exception-for-TNEF) every one of its callers was
 * already written to expect. Same root-cause shape as preview()'s own getPart() miss, fixed
 * earlier the same session (see JmapShimPreviewCharsetTest.php).
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

class JmapShimFetchRawPartTest extends \PHPUnit\Framework\TestCase
{
	private function stubImap(\Horde_Mime_Part $structure, string $partId, string $rawBytes, string $decode, ?string $mimeHeaderText = null) : \Horde_Imap_Client_Socket
	{
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		$fetchFixture = new \Horde_Imap_Client_Data_Fetch();
		$fetchFixture->setStructure($structure);
		$fetchFixture->setBodyPart($partId, $rawBytes, $decode);
		if ($mimeHeaderText !== null)
		{
			$fetchFixture->setMimeHeader($partId, $mimeHeaderText);
		}
		$imap->method('fetch')->willReturn([1 => $fetchFixture]);
		return $imap;
	}

	public function testReturnsTheDecodedBytesForAPartThatExists()
	{
		$structure = new \Horde_Mime_Part();
		$structure->setType('application/pdf');
		$structure->setMimeId('1');

		$imap = $this->stubImap($structure, '1', 'raw pdf bytes', '8bit');

		$this->assertSame('raw pdf bytes', JmapShim::fetchRawPart($imap, 'INBOX', '1', '1'));
	}

	/**
	 * The exact crash from the real error log: a partId that doesn't resolve against the
	 * structure's own getPart() (eg. a stale download link for a part the message no longer has,
	 * or never had) must return null - the documented "not found" contract every caller already
	 * relies on - not crash the whole request.
	 */
	public function testReturnsNullInsteadOfCrashingWhenThePartIdDoesNotResolve()
	{
		$structure = new \Horde_Mime_Part();
		$structure->setType('application/pdf');
		$structure->setMimeId('1');

		// fetch a partId ('99') that isn't '1' - getPart('99') resolves to null
		$imap = $this->stubImap($structure, '1', 'raw pdf bytes', '8bit');

		$this->assertNull(JmapShim::fetchRawPart($imap, 'INBOX', '1', '99'));
	}

	public function testReturnsNullWhenTheMessageItselfWasNotFound()
	{
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		$imap->method('fetch')->willReturn([]);

		$this->assertNull(JmapShim::fetchRawPart($imap, 'INBOX', '1', '1'));
	}

	/**
	 * Ticket #125561: a message/rfc822 attachment, re-fetched by emailSubmissionSet()'s own
	 * "rebuild the mailer from the just-saved draft" flow, arrived at the actual send still
	 * base64-encoded - never decoded. Root cause: Horde_Imap_Client_Socket's own BINARY-fetch
	 * fallback ("Dovecot bug ... try again with non-decoded body") - Dovecot correctly refuses to
	 * server-side-decode a message/rfc822 part declaring base64 (RFC 2046 [5.2.1] permits only
	 * 7bit/8bit/binary there), so Horde retries WITHOUT decoding and labels the STILL-ENCODED raw
	 * bytes it gets back with the exact same generic '8bit' a genuinely successful decode would
	 * also report - this fixture reproduces exactly that shape (real base64 text as the "raw"
	 * body, decode label '8bit', but the part's own MIME header truthfully says base64).
	 * fetchRawPart() must trust that header over the misleading decode label.
	 */
	public function testDecodesBase64ContentEvenWhenTheServersDecodeNegotiationMislabelsIt8bit()
	{
		$structure = new \Horde_Mime_Part();
		$structure->setType('message/rfc822');
		$structure->setMimeId('2');

		$originalMessageBytes = "Subject: test\r\n\r\nBody with a real embedded NUL: \0 and high bytes: \xC8\xC8.\r\n";
		$stillEncodedRaw = base64_encode($originalMessageBytes);

		$imap = $this->stubImap($structure, '2', $stillEncodedRaw, '8bit',
			"Content-Type: message/rfc822\r\nContent-Transfer-Encoding: base64\r\n\r\n");

		$this->assertSame($originalMessageBytes, JmapShim::fetchRawPart($imap, 'INBOX', '1', '2'));
	}
}
