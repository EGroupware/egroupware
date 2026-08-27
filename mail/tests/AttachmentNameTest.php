<?php
/**
 * EGroupware Mail: tests for Api\Mail::attachmentName()'s mime-type-driven filename logic
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail;

/**
 * Api\Mail::attachmentName() decides the display filename for an attachment/mime-part: keep the
 * part's own filename if its extension already matches the mime type (or the mime type is the
 * generic application/octet-stream), otherwise append the extension MimeMagic derives from the
 * mime type. Pure function of a Horde_Mime_Part - no database, session or IMAP connection
 * required, same fixture style as HasAttachmentTest.php.
 */
class AttachmentNameTest extends \PHPUnit\Framework\TestCase
{
	private function part(string $type, ?string $name, ?string $description = null) : \Horde_Mime_Part
	{
		$part = new \Horde_Mime_Part();
		$part->setType($type);
		if ($name !== null)
		{
			$part->setName($name);
		}
		if ($description !== null)
		{
			$part->setDescription($description);
		}
		return $part;
	}

	/**
	 * Pass criteria: a filename whose extension already matches its real mime type is returned
	 * unchanged - no redundant double extension gets appended.
	 */
	public function testMatchingExtensionIsKeptUnchanged()
	{
		$this->assertSame('report.pdf', Mail::attachmentName($this->part('application/pdf', 'report.pdf')));
		$this->assertSame('photo.jpg', Mail::attachmentName($this->part('image/jpeg', 'photo.jpg')));
	}

	/**
	 * Pass criteria: application/octet-stream is the "don't know the real type" mime type, so a
	 * filename with any extension is trusted as-is, even though MimeMagic wouldn't map that
	 * extension back to octet-stream itself.
	 */
	public function testOctetStreamKeepsWhateverExtensionIsGiven()
	{
		$this->assertSame('archive.xyz', Mail::attachmentName($this->part('application/octet-stream', 'archive.xyz')));
	}

	/**
	 * Pass criteria: when the filename's extension does NOT match the real mime type, the
	 * correct extension for that mime type is appended rather than trusting the wrong one -
	 * proving this is a genuine mime-type-driven decision, not just "use whatever name is given".
	 */
	public function testMismatchedExtensionGetsCorrectExtensionAppended()
	{
		$name = Mail::attachmentName($this->part('application/pdf', 'report.txt'));
		$this->assertStringEndsWith('.pdf', $name, 'a mime-mismatched extension must be corrected to match the real mime type');
	}

	/**
	 * Pass criteria: a part with no filename at all falls back to a generic mime-derived name,
	 * and still carries the right extension for its mime type.
	 */
	public function testMissingNameFallsBackToGenericNameWithMimeExtension()
	{
		$name = Mail::attachmentName($this->part('image/png', null));
		$this->assertStringEndsWith('.png', $name, 'a nameless image part must still get a .png name');
	}

	/**
	 * Pass criteria: a nameless message/rfc822 part (a forwarded message attached as an eml) is
	 * given the specific "forwarded message" fallback name, not the generic "attachment" one.
	 */
	public function testNamelessForwardedMessageGetsForwardedMessageFallbackName()
	{
		$name = Mail::attachmentName($this->part('message/rfc822', null));
		$this->assertStringStartsNotWith('attachment', $name,
			'a forwarded-message part must use the message-specific fallback name, not the generic one');
	}

	/**
	 * Regression guard: if the filename already ends in "_<ext>" (a shape produced elsewhere when
	 * the real extension was previously unknown and substituted into the name itself), a second,
	 * redundant "._<ext>.<ext>" must not be produced - the stray "_<ext>" suffix is stripped before
	 * appending the real one.
	 */
	public function testRedundantUnderscoreExtensionSuffixIsNotDuplicated()
	{
		$name = Mail::attachmentName($this->part('application/pdf', 'report_pdf'));
		$this->assertSame('report.pdf', $name);
	}
}
