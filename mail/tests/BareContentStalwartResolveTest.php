<?php
/**
 * EGroupware Mail: tests for Stalwart's bare-content resolveBareJmap() (ticket #125171, see
 * BareContentSpecialCaseTest.php for the dispatch-decision half of this)
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Imap\Jmap as StalwartImap;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Stalwart's resolveBareJmap() - unlike the shim's resolveBare() (BareContentShimFetchTest.php),
 * this one has no account/DB-lookup boundary in the way: jmapClient() is a simple, directly
 * mockable instance method (same technique any Imap\Jmap-based test would use), so the full
 * method is tested end-to-end here, not just its composed primitives.
 */
#[AllowMockObjectsWithoutExpectations]
class BareContentStalwartResolveTest extends \PHPUnit\Framework\TestCase
{
	private function mockJmapReturning(string $rawMessage) : StalwartImap
	{
		$imap = $this->getMockBuilder(StalwartImap::class)
			->disableOriginalConstructor()
			->onlyMethods(['jmapClient'])
			->getMock();

		$client = new class($rawMessage)
		{
			public function __construct(private string $rawMessage)
			{
			}

			public function emailGet($id, $properties)
			{
				return ['blobId' => 'fake-blob-id-for-'.$id];
			}

			public function downloadBlob($blobId, $name, $type)
			{
				return $this->rawMessage;
			}
		};
		$imap->method('jmapClient')->willReturn($client);
		return $imap;
	}

	private function rawBareImageMessage(string $pngBytes) : string
	{
		// see BareContentShimFetchTest::rawBarePdfMessage()'s own comment - MIME-Version is
		// required for parseMessage() to honour Content-Type at all
		return "MIME-Version: 1.0\r\n".
			"Content-Type: image/png\r\n".
			"Content-Transfer-Encoding: base64\r\n".
			"\r\n".
			chunk_split(base64_encode($pngBytes));
	}

	public function testResolveBareJmapEmbedsBareImage() : void
	{
		$pngBytes = "\x89PNG\x0d\x0a\x1a\x0a fake but stable bytes for the test";
		$imap = $this->mockJmapReturning($this->rawBareImageMessage($pngBytes));

		$html = $imap->resolveBareJmap('email-id-123');

		$this->assertStringContainsString('<img ', $html);
		$this->assertStringContainsString('data:image/png;base64,'.base64_encode($pngBytes), $html);
	}

	public function testResolveBareJmapEmbedsBarePdf() : void
	{
		$pdfBytes = "%PDF-1.4 fake but stable bytes for the test, stalwart variant";
		$raw = "MIME-Version: 1.0\r\n".
			"Content-Type: application/pdf\r\n".
			"Content-Transfer-Encoding: base64\r\n".
			"\r\n".
			chunk_split(base64_encode($pdfBytes));
		$imap = $this->mockJmapReturning($raw);

		$html = $imap->resolveBareJmap('email-id-456');

		$this->assertStringContainsString('<embed ', $html);
		// data-* attribute, NOT a data: URI src - see StructureToHtmlTest's own identical
		// assertion for why (Chrome refuses to render a PDF embed from a data: URI at all)
		$this->assertStringContainsString('data-bare-pdf-base64="'.base64_encode($pdfBytes).'"', $html);
	}
}
