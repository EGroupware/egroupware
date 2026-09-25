<?php
/**
 * Test EGroupware\Mail\JmapShim::structureToHtml()'s inline cid: image resolution, used by the
 * JMAP-native S/MIME/TNEF resolvers (JmapShim::resolveSmime()/resolveTnef(), Imap\Jmap's Stalwart
 * equivalents) to render an already-fully-parsed-in-memory Horde_Mime_Part tree.
 *
 * Pure in-memory Horde_Mime_Part fixtures - no database, session or IMAP connection required.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

class StructureToHtmlTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Builds a multipart/related structure: an HTML body referencing an inline image via cid:,
	 * plus that image part itself - mirrors what Horde_Mime_Part::parseMessage() produces for a
	 * real signed/encrypted message once decrypted (Api\Mail\Smime::resolveMessage()'s output),
	 * with every part's contents already populated (no fetch involved).
	 */
	protected static function relatedStructureWithInlineImage(string $cid, string $pngBytes) : \Horde_Mime_Part
	{
		$html = new \Horde_Mime_Part();
		$html->setType('text/html');
		$html->setContents('<html><body><p>Hello</p><img src="cid:'.$cid.'"></body></html>');

		$image = new \Horde_Mime_Part();
		$image->setType('image/png');
		$image->setContents($pngBytes);
		$image->setContentId($cid);
		$image->setDisposition('inline');

		$related = new \Horde_Mime_Part();
		$related->setType('multipart/related');
		$related->addPart($html);
		$related->addPart($image);

		return $related;
	}

	public function testInlineCidImageResolvedToDataUri()
	{
		$cid = 'image1@egroupware';
		$pngBytes = "\x89PNG\x0d\x0a\x1a\x0a fake but stable bytes for the test";
		$structure = self::relatedStructureWithInlineImage($cid, $pngBytes);

		$html = JmapShim::structureToHtml($structure);

		$this->assertStringContainsString(
			'data:image/png;base64,'.base64_encode($pngBytes),
			$html
		);
		$this->assertStringNotContainsString('cid:', $html);
	}

	public function testUnresolvableCidLeftUntouched()
	{
		$structure = self::relatedStructureWithInlineImage('image1@egroupware', 'irrelevant');

		// reference a *different* cid than the one the image part actually has
		$html = JmapShim::structureToHtml($structure);
		$this->assertStringNotContainsString('cid:unknown@nowhere', $html);

		$htmlPart = $structure->getPart($structure->findBody('html'));
		$htmlPart->setContents('<img src="cid:unknown@nowhere">');
		$html = JmapShim::structureToHtml($structure);

		$this->assertStringContainsString('cid:unknown@nowhere', $html);
	}

	/**
	 * A plain-text part with NO charset parameter at all - found live 2026-09-03 (ralf: a
	 * shim-account plain-text reply with German umlauts previewed as mojibake, while Thunderbird
	 * displayed the identical raw message correctly, since it also assumes utf-8 rather than
	 * us-ascii for an undeclared charset). Our own outgoing plain-text is always utf-8 (fixed the
	 * same day to also always DECLARE it, Api\Mailer::getRaw()), but this covers any OTHER message
	 * (a stale draft from before that fix, or from any 3rd-party sender) that still lacks one.
	 */
	public function testUndeclaredCharsetDefaultsToUtf8NotMojibake()
	{
		$part = new \Horde_Mime_Part();
		$part->setType('text/plain');
		// deliberately NOT calling setCharset() - the exact "no charset declared" shape reported
		$part->setContents('Mit freundlichen Grüßen');

		$html = JmapShim::structureToHtml($part);

		$this->assertStringContainsString('Grüßen', $html);
	}

	/**
	 * Ticket #125171: a message whose ENTIRE content is a single application/pdf part - no
	 * multipart/mixed wrapper, no separate text/plain or text/html body anywhere at all (found
	 * live: SAP NetWeaver sends purchase-order emails with a bare top-level
	 * "Content-Type: application/pdf", nothing else). findBody('plain')/findBody('html') both
	 * return null for this structure, so this used to fall straight through to the generic
	 * "no body at all" branch and render a completely blank body - classic
	 * Api\Mail::getMessageBody() already special-cases exactly this (streaming the PDF/image
	 * directly as the response instead), this mirrors that for the JMAP-native path.
	 *
	 * Deliberately checks for a data-bare-pdf-base64 attribute, NOT a data: URI src - Chrome's
	 * built-in PDF viewer refuses to render a PDF from a data: URI at all (confirmed live: neither
	 * <embed> nor <iframe> renders anything but a blank/broken-plugin area for one, no CSP/
	 * iframe-nesting involved), only a real blob: URL does - preview.js (mail/js/preview.js)
	 * converts this attribute into one client-side after the page loads, since only client-side
	 * code can construct a blob: URL at all.
	 */
	public function testBarePdfMessageEmbedsItselfAsTheBody()
	{
		$pdfBytes = "%PDF-1.4 fake but stable bytes for the test";
		$part = new \Horde_Mime_Part();
		$part->setType('application/pdf');
		$part->setName('Bestellung 4500195131.pdf');
		$part->setContents($pdfBytes);

		$html = JmapShim::structureToHtml($part);

		$this->assertStringContainsString('data-bare-pdf-base64="'.base64_encode($pdfBytes).'"', $html);
		$this->assertStringContainsString('<embed ', $html);
		$this->assertStringNotContainsString('src="data:application/pdf', $html,
			'a data: URI src would never render in Chrome for a PDF embed - must stay a data-* attribute for preview.js to resolve into a blob: URL instead');
	}

	/** Same as the bare-PDF case, for a message whose entire content is a single image instead. */
	public function testBareImageMessageEmbedsItselfAsTheBody()
	{
		$pngBytes = "\x89PNG\x0d\x0a\x1a\x0a fake but stable bytes for the test";
		$part = new \Horde_Mime_Part();
		$part->setType('image/png');
		$part->setContents($pngBytes);

		$html = JmapShim::structureToHtml($part);

		$this->assertStringContainsString('data:image/png;base64,'.base64_encode($pngBytes), $html);
		$this->assertStringContainsString('<img ', $html);
	}

	/**
	 * A bare, bodyless message that is neither a PDF nor an image (eg. some other attachment
	 * type with no MIME wrapper) must still fall back to an empty body, not error out or embed
	 * something nonsensical - matches classic getMessageBody()'s own "application" primary-type
	 * branch, which also just returns an empty body for this case.
	 */
	public function testBareNonPdfNonImageMessageStillReturnsEmptyBody()
	{
		$part = new \Horde_Mime_Part();
		$part->setType('application/octet-stream');
		$part->setContents('irrelevant binary content');

		$this->assertSame('', JmapShim::structureToHtml($part));
	}

	/**
	 * Ticket #125171 follow-up, found live: a bare PDF message can ALSO be missing its
	 * Content-Transfer-Encoding header entirely, while its body is nonetheless still literal
	 * base64 TEXT (the sender's own mail system encoded the binary but never declared it) -
	 * Horde_Mime_Part then has no encoding to reverse, so getContents() returns that base64 text
	 * as-is. Without BodyDecoding::decodeIfStillBase64() this would embed the base64 TEXT
	 * double-encoded (garbage, not a valid PDF) instead of the real bytes.
	 */
	public function testBarePdfWithoutContentTransferEncodingStillDecodesCorrectly()
	{
		$pdfBytes = "%PDF-1.4 fake but stable bytes for the test";
		$raw = "MIME-Version: 1.0\r\n".
			"Content-Type: application/pdf\r\n".
			// deliberately NO Content-Transfer-Encoding header
			"\r\n".
			chunk_split(base64_encode($pdfBytes));
		$part = \Horde_Mime_Part::parseMessage($raw);

		$html = JmapShim::structureToHtml($part);

		$this->assertStringContainsString('data-bare-pdf-base64="'.base64_encode($pdfBytes).'"', $html);
		$this->assertStringContainsString('<embed ', $html);
	}
}
