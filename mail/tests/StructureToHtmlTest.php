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
}
