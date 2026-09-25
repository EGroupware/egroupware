<?php
/**
 * EGroupware Mail: tests for the local shim's bare-content fetch/render chain (ticket #125171,
 * see BareContentSpecialCaseTest.php for the dispatch-decision half of this)
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;
use EGroupware\Api\Mail\Imap;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * The local shim's resolveBare() - a thin (accountId, mailboxId, uid) -> imapServer() lookup
 * wrapper around two already-independently-tested primitives: fetchRawMessage() (a plain $imap
 * in, raw bytes out - mockable directly, no account/DB lookup) and structureToHtml() (fully
 * covered by StructureToHtmlTest.php). Mirrors JmapShimMailboxGetTest.php's own established
 * "mocked Mail\Imap connection, no live server" pattern - resolveBare() itself isn't invoked
 * directly here since its own imapServer()/hordeMailbox()/folderPath() glue needs a real DB-backed
 * Mail\Account to resolve an accountId, the same live-server boundary AttachmentLinksBodyTest.php's
 * own class docblock documents for its "real end-to-end" cases; this instead proves the exact
 * fetch->parse->render chain resolveBare() performs, via the same mocked-connection technique.
 */
#[AllowMockObjectsWithoutExpectations]
class BareContentShimFetchTest extends \PHPUnit\Framework\TestCase
{
	private function mockImapReturning(string $rawMessage, \Horde_Mime_Part $structure) : Imap
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(['fetch'])
			->getMock();

		$results = new \Horde_Imap_Client_Fetch_Results();
		$data = $results->get(1);
		$data->setStructure($structure);
		$data->setFullMsg($rawMessage);
		// both structureGet()'s structure()-only query and fetchRawMessage()'s fullText()-only
		// query land on the SAME pre-populated Data_Fetch object regardless of which was asked for
		// - resolveBare() only ever needs fetchRawMessage()'s side of this in practice, but this
		// mirrors resolveSmime()'s identical "fetch the whole raw message, reparse" recipe closely
		// enough to stay a faithful stand-in for what resolveBare() itself does.
		$imap->method('fetch')->willReturn($results);
		return $imap;
	}

	private function rawBarePdfMessage(string $pdfBytes) : string
	{
		// Horde_Mime_Part::parseMessage() needs an explicit MIME-Version header to honour the
		// Content-Type at all - without it, it falls back to treating the whole thing as a bare
		// text/plain body (found via this test itself failing with the literal base64 text showing
		// up wrapped in a <pre>, instead of an <embed>)
		return "MIME-Version: 1.0\r\n".
			"Content-Type: application/pdf\r\n".
			"Content-Transfer-Encoding: base64\r\n".
			"\r\n".
			chunk_split(base64_encode($pdfBytes));
	}

	public function testFetchRawMessageThenStructureToHtmlEmbedsBarePdf() : void
	{
		$pdfBytes = "%PDF-1.4 fake but stable bytes for the test";
		$raw = $this->rawBarePdfMessage($pdfBytes);
		// the structure object passed to setStructure() is irrelevant here - fetchRawMessage()
		// never touches it, only getFullMsg()
		$imap = $this->mockImapReturning($raw, new \Horde_Mime_Part());

		$fetchedRaw = JmapShim::fetchRawMessage($imap, 'INBOX', '1');
		$this->assertNotNull($fetchedRaw);

		$html = JmapShim::structureToHtml(\Horde_Mime_Part::parseMessage($fetchedRaw));

		$this->assertStringContainsString('<embed ', $html);
		// data-* attribute, NOT a data: URI src - see StructureToHtmlTest's own identical
		// assertion for why (Chrome refuses to render a PDF embed from a data: URI at all)
		$this->assertStringContainsString('data-bare-pdf-base64="'.base64_encode($pdfBytes).'"', $html);
	}

	public function testFetchRawMessageReturnsNullForAMissingMessage() : void
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(['fetch'])
			->getMock();
		// no matching uid in the results at all - the "message not found" shape resolveBare()
		// turns into an Exception
		$imap->method('fetch')->willReturn(new \Horde_Imap_Client_Fetch_Results());

		$this->assertNull(JmapShim::fetchRawMessage($imap, 'INBOX', '999'));
	}
}
