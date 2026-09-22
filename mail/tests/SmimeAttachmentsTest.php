<?php
/**
 * Ticket #124661 (2026-09-22, a real customer, "Testmail s/mime encrypted with attachment(s)"):
 * an S/MIME-encrypted message's real attachments were never surfaced to the client at all -
 * resolveSmime()/resolveSmimeJmap() decrypted the message into a full Horde_Mime_Part structure
 * (needed to render the body) but discarded it right after, so the client fell back to the
 * UNDECRYPTED top-level email's own single opaque application/pkcs7-mime "attachment".
 *
 * JmapShim::smimeAttachments() is a pure function of an already-decrypted, in-memory
 * Horde_Mime_Part tree - no database, session or IMAP connection required. Its blobId scheme
 * ("smime:<b64 rowId>:<b64 topLevelType>:<b64 fromAddress>:<partId>") embeds everything a later
 * download()-time re-decrypt needs, see the method's own docblock.
 *
 * JmapShim::smimeAttachmentBytes() (download()'s counterpart, re-decrypts and extracts one part
 * on demand) is deliberately NOT exercised end-to-end here - like resolveSmime() itself, it's too
 * entangled with a real DB-backed account/IMAP connection to usefully mock (see
 * JmapShimFetchRawPartTest.php's narrower stub-only approach for the one piece of that that IS
 * mockable). Only its defensive, DB-free early-return branches are covered below; the rest is
 * live-verified.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

class SmimeAttachmentsTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * multipart/mixed: text/plain body, text/html body, a named PDF attachment, and an inline
	 * cid-referenced image - mirrors a real decrypted CMS EnvelopedData structure
	 * (Api\Mail\Smime::resolveMessage()'s own return value).
	 */
	private function decryptedStructureWithAttachments() : \Horde_Mime_Part
	{
		$plain = new \Horde_Mime_Part();
		$plain->setType('text/plain');
		$plain->setContents('Hello');

		$html = new \Horde_Mime_Part();
		$html->setType('text/html');
		$html->setContents('<p>Hello</p>');

		$alternative = new \Horde_Mime_Part();
		$alternative->setType('multipart/alternative');
		$alternative->addPart($plain);
		$alternative->addPart($html);

		$pdf = new \Horde_Mime_Part();
		$pdf->setType('application/pdf');
		$pdf->setName('invoice.pdf');
		$pdf->setContents(str_repeat('x', 1234));
		$pdf->setDisposition('attachment');

		$image = new \Horde_Mime_Part();
		$image->setType('image/png');
		$image->setContents('fake png bytes');
		$image->setContentId('image1@egroupware');
		$image->setDisposition('inline');

		$root = new \Horde_Mime_Part();
		$root->setType('multipart/mixed');
		$root->addPart($alternative);
		$root->addPart($pdf);
		$root->addPart($image);

		return $root;
	}

	public function testSkipsMultipartContainersAndTheTextHtmlBodyParts()
	{
		$structure = $this->decryptedStructureWithAttachments();

		$attachments = JmapShim::smimeAttachments($structure, 'mail::1::2::Rk9M::42', 'multipart/encrypted', 'sender@example.com');

		$this->assertCount(2, $attachments);
		$types = array_column($attachments, 'type');
		$this->assertContains('application/pdf', $types);
		$this->assertContains('image/png', $types);
	}

	public function testAttachmentMetadataAndBlobIdRoundTrip()
	{
		$structure = $this->decryptedStructureWithAttachments();
		$rowId = 'mail::1::2::Rk9M::42';
		$topLevelType = 'multipart/encrypted';
		$fromAddress = 'sender@example.com';

		$attachments = JmapShim::smimeAttachments($structure, $rowId, $topLevelType, $fromAddress);
		$pdf = current(array_filter($attachments, static fn($a) => $a['type'] === 'application/pdf'));

		$this->assertNotFalse($pdf);
		$this->assertSame('invoice.pdf', $pdf['name']);
		$this->assertSame(1234, $pdf['size']);
		$this->assertSame('attachment', $pdf['disposition']);
		$this->assertNull($pdf['cid']);
		$this->assertStringStartsWith('smime:', $pdf['blobId']);
		$this->assertStringEndsWith(':'.$pdf['partId'], $pdf['blobId']);

		// every piece needed to re-decrypt is recoverable from the blobId alone, urlsafe-b64-encoded
		[, $rowIdB64, $topLevelTypeB64, $fromB64, $partId] = explode(':', $pdf['blobId']);
		$this->assertSame($rowId, JmapShim::urlsafeB64Decode($rowIdB64));
		$this->assertSame($topLevelType, JmapShim::urlsafeB64Decode($topLevelTypeB64));
		$this->assertSame($fromAddress, JmapShim::urlsafeB64Decode($fromB64));
		$this->assertSame($pdf['partId'], $partId);
	}

	public function testInlineImageKeepsItsContentId()
	{
		$structure = $this->decryptedStructureWithAttachments();

		$attachments = JmapShim::smimeAttachments($structure, 'mail::1::2::Rk9M::42', 'multipart/encrypted', 'sender@example.com');
		$image = current(array_filter($attachments, static fn($a) => $a['type'] === 'image/png'));

		$this->assertNotFalse($image);
		$this->assertSame('image1@egroupware', $image['cid']);
		$this->assertSame('inline', $image['disposition']);
	}

	/**
	 * An empty $rowId (a caller with no rowId in scope, eg. a context resolveSpecialCaseBody()
	 * never had one for) still gets correct attachment METADATA - only the resulting blobId would
	 * never resolve to anything real via download() later. Documented behaviour, see the method's
	 * own docblock.
	 */
	public function testEmptyRowIdStillProducesMetadataWithAnUnresolvableBlobId()
	{
		$structure = $this->decryptedStructureWithAttachments();

		$attachments = JmapShim::smimeAttachments($structure, '', 'multipart/encrypted', 'sender@example.com');
		$pdf = current(array_filter($attachments, static fn($a) => $a['type'] === 'application/pdf'));

		$this->assertNotFalse($pdf);
		$this->assertSame('invoice.pdf', $pdf['name']);
		$this->assertStringStartsWith('smime:'.JmapShim::urlsafeB64Encode('').':', $pdf['blobId']);
	}

	private function callSmimeAttachmentBytes(string $blobId)
	{
		$method = new \ReflectionMethod(JmapShim::class, 'smimeAttachmentBytes');
		$method->setAccessible(true);
		return $method->invoke(null, $blobId);
	}

	/**
	 * A malformed blobId (wrong number of ':'-joined segments) must be rejected before anything
	 * DB/IMAP-backed is even touched - download()'s existing "unresolvable -> null -> 404" contract,
	 * same as every other blobId scheme it already handles.
	 */
	public function testMalformedBlobIdReturnsNullWithoutTouchingAnyBackend()
	{
		$this->assertNull($this->callSmimeAttachmentBytes('smime:onlyOneSegment'));
		$this->assertNull($this->callSmimeAttachmentBytes('smime:a:b:c'));
	}

	/**
	 * An empty/unparseable rowId resolves to a null profileID (Api\Mail::splitRowID()'s own
	 * documented behaviour for that case, verified elsewhere) purely in-memory - no DB access -
	 * so smimeAttachmentBytes() must bail out right there instead of calling imapServer('').
	 */
	public function testUnresolvableRowIdReturnsNullWithoutTouchingAnyBackend()
	{
		$blobId = 'smime:'.JmapShim::urlsafeB64Encode('').':'.JmapShim::urlsafeB64Encode('multipart/encrypted').
			':'.JmapShim::urlsafeB64Encode('sender@example.com').':1';

		$this->assertNull($this->callSmimeAttachmentBytes($blobId));
	}
}
