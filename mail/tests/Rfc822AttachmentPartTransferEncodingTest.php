<?php
/**
 * Test EGroupware\Api\Mail\Jmap\Rfc822AttachmentPart's Content-Transfer-Encoding handling
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap;
use EGroupware\Api\Mail\Jmap\Rfc822AttachmentPart;

// Rfc822AttachmentPart lives at the bottom of Imap.php as a second class in the same file - PSR-4
// autoloading only ever maps Imap::class to that file, so referencing Imap::class here first is
// what actually makes the file (and therefore Rfc822AttachmentPart too) get loaded at all.
class_exists(Imap::class);

/**
 * Ticket #125561 (a real customer, "Weiterleitung als Anhang - Attachments korrupt" - forwarding
 * a message with images as a message/rfc822 attachment corrupted those images): the carried
 * message's raw bytes are embedded verbatim (Imap::addAttachmentPart()) - Horde_Mime_Part::
 * addMimeHeaders() has a hard-coded early return for message/* parts ("message/* parts require
 * no additional header information", RFC 2046 [5.2.1]) that skips computing a
 * Content-Transfer-Encoding entirely, unlike every other part type. RFC 2045 [6.1]'s default when
 * the header is omitted is '7bit' - a claim genuinely 8bit/binary content (a real image
 * attachment, or any part Horde itself defaults to 'binary') flatly contradicts.
 *
 * A first fix attempt declared 8bit/binary (RFC 2046 [5.2.1]'s own allowed labels, leaving the
 * bytes untouched) - insufficient: live-verified 2026-09-29 that even with the correct header,
 * the embedded image's own bytes arrived corrupted (every NUL replaced by an overlong-UTF8
 * sequence), apparently something along the delivery path "fixing up" the BINARYMIME/8BITMIME
 * transport that declaring 8bit/binary triggers. Rfc822AttachmentPart now base64-encodes non-
 * 7bit-clean content instead - guaranteed plain 7bit ASCII on the wire, no BINARYMIME/8BITMIME
 * needed at all, same as every other (non-message) attachment already gets sent as. See that
 * class's own docblock for the full history and why the encoding has to happen in setContents()
 * itself rather than being left to Horde's normal per-type encoding step.
 */
class Rfc822AttachmentPartTransferEncodingTest extends \PHPUnit\Framework\TestCase
{
	private function buildPart(string $content) : Rfc822AttachmentPart
	{
		$part = new Rfc822AttachmentPart();
		$part->setType('message/rfc822');
		$part->setContents($content);
		$part->setName('forwarded.eml');
		$part->setDisposition('attachment');
		return $part;
	}

	public function testDeclaresNoEncodingForGenuinely7bitCleanContent()
	{
		$content = "Subject: test\r\n\r\nPlain 7bit body, nothing special.\r\n";
		$part = $this->buildPart($content);

		$headers = $part->addMimeHeaders();

		$this->assertNull($headers['content-transfer-encoding'],
			"genuinely 7bit content must not get an explicit header - RFC 2045's own default, matches every other part type's existing behaviour");
		$this->assertSame($content, $part->getContents(),
			"7bit-clean content must be stored untouched, not base64-encoded");
	}

	/**
	 * The exact failure mode: a real image attachment inside the carried message, embedded with
	 * actual raw high-bit bytes (eg. Horde's own DEFAULT_ENCODING='binary' for a freshly-added
	 * attachment) - a real-world .jpg/.png always contains bytes >= 0x80.
	 */
	public function test8bitContentGetsBase64Encoded()
	{
		$raw8bit = "Subject: test\r\n\r\n".str_repeat(chr(200), 50)."\r\n";
		$part = $this->buildPart($raw8bit);

		$headers = $part->addMimeHeaders();

		$this->assertNotNull($headers['content-transfer-encoding'],
			"non-7bit-clean content must get an explicit Content-Transfer-Encoding - omitting it falsely claims 7bit per RFC 2045 [6.1]'s own default");
		$this->assertSame('base64', $headers['content-transfer-encoding']->value,
			"base64 (not 8bit/binary) - see Rfc822AttachmentPart's own docblock for why: those triggered a real-world delivery-path corruption for ticket #125561");
	}

	/** A genuine NUL byte (real binary data, eg. inside a jpeg) must also end up base64-encoded. */
	public function testBinaryContentWithNulBytesGetsBase64Encoded()
	{
		$raw = "Subject: test\r\n\r\n".str_repeat(chr(200), 10)."\0".str_repeat(chr(200), 10)."\r\n";
		$part = $this->buildPart($raw);

		$headers = $part->addMimeHeaders();

		$this->assertSame('base64', $headers['content-transfer-encoding']->value);
	}

	/**
	 * The actual regression this ticket is about: the wire bytes (what getContents() now
	 * returns, since setContents() itself does the base64 transform) must decode back to the
	 * exact original bytes - byte for byte, including the embedded NUL.
	 */
	public function testBase64EncodedContentRoundTripsToTheOriginalBytesExactly()
	{
		$original = "Subject: test\r\n\r\n".str_repeat(chr(200), 10)."\0".str_repeat(chr(200), 10)."\r\n";
		$part = $this->buildPart($original);

		$this->assertSame('base64', $part->addMimeHeaders()['content-transfer-encoding']->value);
		$this->assertSame($original, base64_decode($part->getContents()));
	}

	/** Content-Disposition (the previously-fixed part of this same class) must still work alongside the new header. */
	public function testContentDispositionIsStillPresent()
	{
		$part = $this->buildPart("Subject: test\r\n\r\n".str_repeat(chr(200), 50)."\r\n");

		$headers = $part->addMimeHeaders();

		$this->assertNotNull($headers['content-disposition']);
		$this->assertSame('attachment', $headers['content-disposition']->value);
	}
}
