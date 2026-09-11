<?php
/**
 * Test EGroupware\Mail\JmapShim::structureHasAttachment() - the row-level "hasAttachment" flag
 * computation for the local IMAP shim, which must match Api\Mail's old per-row heuristic
 * (classic pre-JMAP getHeaders() code) rather than Horde_Mime_Part::isAttachment()'s stricter
 * rules - see the method's docblock for why.
 *
 * Pure in-memory Horde_Mime_Part fixtures - no database, session or IMAP connection required.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

class HasAttachmentTest extends \PHPUnit\Framework\TestCase
{
	protected static function textOnlyMessage() : \Horde_Mime_Part
	{
		$text = new \Horde_Mime_Part();
		$text->setType('text/plain');
		$text->setContents('just some text, no attachments');
		return $text;
	}

	protected static function withImagePart(string $type, ?string $disposition, ?string $cid) : \Horde_Mime_Part
	{
		$text = new \Horde_Mime_Part();
		$text->setType('text/plain');
		$text->setContents('body text');

		$image = new \Horde_Mime_Part();
		$image->setType($type);
		$image->setContents('irrelevant bytes');
		if ($disposition !== null)
		{
			$image->setDisposition($disposition);
		}
		if ($cid !== null)
		{
			$image->setContentId($cid);
		}

		$mixed = new \Horde_Mime_Part();
		$mixed->setType('multipart/mixed');
		$mixed->addPart($text);
		$mixed->addPart($image);

		return $mixed;
	}

	public function testPlainTextOnlyHasNoAttachment()
	{
		$this->assertFalse(JmapShim::structureHasAttachment(self::textOnlyMessage()));
	}

	public function testAttachmentDispositionCountsAsAttachment()
	{
		$structure = self::withImagePart('image/png', 'attachment', 'image1@egroupware');
		$this->assertTrue(JmapShim::structureHasAttachment($structure));
	}

	public function testInlineImageWithCidIsNotAnAttachment()
	{
		// resolvable via cid: substitution in the body - not listed separately
		$structure = self::withImagePart('image/png', 'inline', 'image1@egroupware');
		$this->assertFalse(JmapShim::structureHasAttachment($structure));
	}

	public function testInlineImageWithoutCidIsAnAttachment()
	{
		// can never be resolved as a cid: reference - must be listed/downloadable
		$structure = self::withImagePart('image/png', 'inline', null);
		$this->assertTrue(JmapShim::structureHasAttachment($structure));
	}

	public function testInlineTiffIsAlwaysAnAttachment()
	{
		// browsers can't display tiff inline, regardless of disposition/cid
		$structure = self::withImagePart('image/tiff', 'inline', 'image1@egroupware');
		$this->assertTrue(JmapShim::structureHasAttachment($structure));
	}
}
