<?php
/**
 * Ticket #126631 (2026-10-20, a real customer): .jpg attachments sent from a Samsung phone's
 * built-in mail app showed a broken/red-X image in EGroupware's display, while downloading the
 * same attachment and opening it in other mail clients worked fine. Birgit Becker's (EGroupware
 * support) own analysis of a reproduction .eml the reporter supplied found the cause: that
 * Samsung app version sends a literal "Content-Type: image/*" header - a wildcard subtype only
 * ever valid in an HTTP Accept header, never an actual message part's own Content-Type - instead
 * of a concrete "image/jpeg". The raw bytes are still a perfectly ordinary JPEG (download still
 * works, and other clients render it fine, apparently falling back to the file extension), but a
 * browser cannot decode a Blob/<img> whose declared MIME type is "image/*" - exactly the reported
 * broken-image/red-X symptom, since JmapShim::bodyPartToJmap()/smimeAttachments() previously
 * passed the raw, unsanitized Content-Type straight through to the client unexamined.
 *
 * JmapShim::concreteMimeType() prefers sniffing the real bytes (Api\MimeMagic::analyze_data(),
 * ralf: "we also have a MimeMagic class, which detects the filetype by looking at the first bytes
 * of a file ... maybe that makes more sense than guessing from the extension, and would also
 * work if no filename is given") over the filename-extension guess, which only ever applies when
 * no content bytes are available yet (bodyPartToJmap()'s own BODYSTRUCTURE-only part).
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * JmapShim::bodyPartToJmap()/smimeAttachments() are pure functions of an in-memory
 * Horde_Mime_Part - no database, session or IMAP connection required.
 */
class AttachmentWildcardMimeTypeTest extends \PHPUnit\Framework\TestCase
{
	// a real minimal 1x1 transparent PNG - enough actual magic bytes for fileinfo to recognize
	private const PNG_BYTES = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\xf3\xffa\x00\x00\x00\x0dIDATx\x9cc\x64\xf8\xff\xff?\x00\x05\x84\x02\x7f\xc2[\x1e*\x00\x00\x00\x00IEND\xaeB`\x82";
	// enough JFIF header bytes for fileinfo to recognize as a JPEG, not a decodable image
	private const JPEG_BYTES = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

	private function part(string $type, ?string $name, string $contents = '') : \Horde_Mime_Part
	{
		$part = new \Horde_Mime_Part();
		$part->setType($type);
		if ($name)
		{
			$part->setName($name);
		}
		if ($contents !== '')
		{
			$part->setContents($contents);
		}
		$part->setDisposition('attachment');
		return $part;
	}

	// --- bodyPartToJmap(): BODYSTRUCTURE-only, no content bytes yet - filename guess only -------

	public static function wildcardTypeByFilenameProvider() : array
	{
		return [
			'image/* with a .jpg name resolves via the filename' => ['image/*', '20261019_132412.jpg', 'image/jpeg'],
			'image/* with a .png name resolves via the filename' => ['image/*', 'photo.png', 'image/png'],
			'image/* with an unknown extension falls back to octet-stream' => ['image/*', 'photo.unknownext', 'application/octet-stream'],
			'image/* with no name at all is left unchanged (nothing to guess from)' => ['image/*', null, 'image/*'],
		];
	}

	#[DataProvider('wildcardTypeByFilenameProvider')]
	public function testBodyPartToJmapSanitizesWildcardSubtypeByFilename(string $rawType, ?string $name, string $expectedType) : void
	{
		// no content bytes populated - matches a real structure-only BODYSTRUCTURE fetch
		$result = JmapShim::bodyPartToJmap($this->part($rawType, $name), 'INBOX', '1');

		$this->assertSame($expectedType, $result['type']);
		// filename itself is reported unchanged, only the type is sanitized
		$this->assertSame($name, $result['name']);
	}

	public function testBodyPartToJmapLeavesAConcreteTypeUnchanged() : void
	{
		$result = JmapShim::bodyPartToJmap($this->part('image/jpeg', 'photo.jpg'), 'INBOX', '1');

		$this->assertSame('image/jpeg', $result['type']);
	}

	// --- real content bytes available (eg. an already-decrypted S/MIME part): sniffing wins -----

	public function testWildcardTypeIsResolvedBySniffingTheRealBytesEvenWithoutAFilename() : void
	{
		$result = JmapShim::bodyPartToJmap($this->part('image/*', null, self::JPEG_BYTES), 'INBOX', '1');

		$this->assertSame('image/jpeg', $result['type']);
	}

	public function testSniffedTypeTakesPriorityOverAMisleadingFilenameExtension() : void
	{
		// filename says .png, real bytes are a JPEG - the sniffed type must win
		$result = JmapShim::bodyPartToJmap($this->part('image/*', 'photo.png', self::JPEG_BYTES), 'INBOX', '1');

		$this->assertSame('image/jpeg', $result['type']);
	}

	public function testSmimeAttachmentsSniffsTheRealBytesToo() : void
	{
		$part = $this->part('image/*', '20261019_132412.jpg', self::PNG_BYTES);
		$root = new \Horde_Mime_Part();
		$root->setType('multipart/mixed');
		$root->addPart($part);

		$attachments = JmapShim::smimeAttachments($root, 'mail::1::2::Rk9M::42', 'multipart/encrypted', 'sender@example.com');

		$this->assertCount(1, $attachments);
		// real bytes are a PNG despite the .jpg filename - sniffing must win
		$this->assertSame('image/png', $attachments[0]['type']);
	}
}
