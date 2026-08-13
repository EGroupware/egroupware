<?php
/**
 * Test mail_ui::jmapAttachmentsToLegacy() - translates RFC 8621 EmailBodyPart attachment
 * entries (Stalwart's real Email/get "attachments", or JmapShim::emailBodyFields()'s
 * "attachments") into the shape createAttachmentBlock() expects.
 *
 * Pure data-transform, no database/session/IMAP connection required.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

class JmapAttachmentsToLegacyTest extends \PHPUnit\Framework\TestCase
{
	private function call(array $jmapAttachments, bool $fetchEmbeddedImages) : array
	{
		$ref = new ReflectionMethod(\mail_ui::class, 'jmapAttachmentsToLegacy');
		$ref->setAccessible(true);
		return $ref->invoke(null, $jmapAttachments, $fetchEmbeddedImages);
	}

	/**
	 * Regression test: some mobile mail clients (eg. Gmail's Android/iOS app) tag a genuinely
	 * attached photo with BOTH Content-Disposition: attachment AND a Content-ID - real bug report
	 * was a message with 4 such images, imported into a Drafts folder, whose attachment list came
	 * back completely empty (attachmentsBlock: []) because the old code dropped any part with a
	 * cid regardless of its disposition. An explicit "attachment" disposition must always win.
	 */
	public function testAttachmentDispositionWithCidIsKept()
	{
		$legacy = $this->call([
			['partId' => '2', 'blobId' => 'blob2', 'size' => 12345, 'name' => '1000214413.jpg',
				'type' => 'image/jpeg', 'cid' => '19fef5c5f1e96aa7d733', 'disposition' => 'attachment'],
		], false);

		$this->assertCount(1, $legacy, 'an explicitly attached part must not be dropped just because it also has a cid');
		$this->assertSame('1000214413.jpg', $legacy[0]['name']);
		$this->assertSame(12345, $legacy[0]['size']);
	}

	/**
	 * All 4 attachments from the real bug report must survive together, in order.
	 */
	public function testMultipleAttachmentDispositionPartsWithCidAreAllKept()
	{
		$jmapAttachments = [];
		foreach (['1000214413.jpg', '1000214410.jpg', '1000214419.jpg', '1000214416.jpg'] as $i => $name)
		{
			$jmapAttachments[] = ['partId' => (string)($i + 2), 'blobId' => 'blob'.$i, 'size' => 1000 + $i,
				'name' => $name, 'type' => 'image/jpeg', 'cid' => 'cid'.$i.'@example', 'disposition' => 'attachment'];
		}

		$legacy = $this->call($jmapAttachments, false);

		$this->assertCount(4, $legacy);
		$this->assertSame(['1000214413.jpg', '1000214410.jpg', '1000214419.jpg', '1000214416.jpg'],
			array_column($legacy, 'name'));
	}

	/**
	 * Unchanged existing behaviour: a genuinely inline image (no "attachment" disposition, just a
	 * cid for <img src="cid:..."> resolution in the HTML body) must still be dropped by default -
	 * it's already shown inline, listing it separately would be a duplicate/confusing entry.
	 */
	public function testInlineImageWithCidIsDroppedByDefault()
	{
		$legacy = $this->call([
			['partId' => '2', 'blobId' => 'blob2', 'size' => 123, 'name' => 'signature.png',
				'type' => 'image/png', 'cid' => 'sig@example', 'disposition' => 'inline'],
		], false);

		$this->assertSame([], $legacy);
	}

	/**
	 * Same inline image, but with no disposition header at all (common for simple inline images) -
	 * must be dropped too, same as an explicit "inline".
	 */
	public function testInlineImageWithCidAndNoDispositionIsDroppedByDefault()
	{
		$legacy = $this->call([
			['partId' => '2', 'blobId' => 'blob2', 'size' => 123, 'name' => 'signature.png',
				'type' => 'image/png', 'cid' => 'sig@example', 'disposition' => null],
		], false);

		$this->assertSame([], $legacy);
	}

	/**
	 * Unchanged existing behaviour: when the caller explicitly asks for embedded images too
	 * ($fetchEmbeddedImages=true), an inline cid part must be kept.
	 */
	public function testInlineImageWithCidKeptWhenFetchEmbeddedImagesRequested()
	{
		$legacy = $this->call([
			['partId' => '2', 'blobId' => 'blob2', 'size' => 123, 'name' => 'signature.png',
				'type' => 'image/png', 'cid' => 'sig@example', 'disposition' => 'inline'],
		], true);

		$this->assertCount(1, $legacy);
		$this->assertSame('signature.png', $legacy[0]['name']);
	}

	/**
	 * Baseline sanity: a plain attachment without any cid at all must always be kept.
	 */
	public function testAttachmentWithoutCidIsKept()
	{
		$legacy = $this->call([
			['partId' => '2', 'blobId' => 'blob2', 'size' => 456, 'name' => 'invoice.pdf',
				'type' => 'application/pdf', 'cid' => null, 'disposition' => 'attachment'],
		], false);

		$this->assertCount(1, $legacy);
		$this->assertSame('invoice.pdf', $legacy[0]['name']);
	}
}
