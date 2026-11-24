<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\Imap::emailBodyFields()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

require_once realpath(__DIR__.'/../../LoggedInTest.php');

use EGroupware\Api;

/**
 * Regression tests for tickets #128441 and #128091 (both live, reported 2026-11), reproduced here
 * with synthetic messages matching the real ones' MIME shape (not the customers' own content):
 * Apple Mail (iOS/iPadOS/macOS Mail) can split ONE logical body into several sibling text/plain or
 * text/html parts with an inline, non-alternative attachment placed in between them, whenever the
 * user inserts a file mid-message - a shape Horde_Mime_Part::findBody()'s "return the FIRST
 * matching part only" cannot represent. Before the fix, emailBodyFields() used findBody() directly
 * and so: (a) silently dropped every body fragment but the first into $attachments (named
 * "Unknown_PartX.Y..." by bodyPartToJmap()), and (b) for #128441 specifically, that often meant
 * the REAL text ended up misfiled as an attachment while an empty/near-empty leading fragment was
 * shown as "the" body.
 */
class ImapEmailBodyFieldsTest extends Api\LoggedInTest
{
	private const MAILBOX = 'INBOX';
	private const UID = '1';

	/**
	 * A Horde_Imap_Client_Socket double whose fetch() answers body-part requests straight out of
	 * the already-parsed $structure (ie. the "raw" bytes IMAP would return for a part are just
	 * that part's own already-decoded content - fine here, since none of this test's text parts
	 * use a transfer-encoding).
	 */
	private function mockImap(\Horde_Mime_Part $structure) : \Horde_Imap_Client_Socket
	{
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		$imap->method('fetch')->willReturnCallback(function($mailbox, \Horde_Imap_Client_Fetch_Query $query) use ($structure)
		{
			$partId = array_key_first($query[\Horde_Imap_Client::FETCH_BODYPART]);
			$data = new \Horde_Imap_Client_Data_Fetch();
			$data->setBodyPart($partId, $structure->getPart($partId)->getContents());
			return [(int)self::UID => $data];
		});
		return $imap;
	}

	public function testInlineAttachmentBetweenTwoPlainTextFragmentsTicket128441()
	{
		$raw = <<<EOT
		Content-Type: multipart/mixed; boundary="B1"
		MIME-Version: 1.0
		From: sender@example.org
		To: recipient@example.org
		Subject: ticket 128441 repro

		--B1
		Content-Type: text/plain; charset=us-ascii

		--B1
		Content-Type: application/pdf; name="file.pdf"
		Content-Disposition: inline; filename="file.pdf"

		not-really-pdf-bytes
		--B1
		Content-Type: text/plain; charset=us-ascii

		Von meinem iPad gesendet
		--B1--

		EOT;
		$structure = \Horde_Mime_Part::parseMessage(str_replace("\n", "\r\n", $raw));

		$result = Imap::emailBodyFields($this->mockImap($structure), self::MAILBOX, self::UID, $structure);

		$this->assertCount(1, $result['textBody'], 'exactly one textBody entry expected');
		$textId = $result['textBody'][0]['partId'];
		$this->assertStringContainsString('Von meinem iPad gesendet', $result['bodyValues'][$textId]['value'],
			'the real text (sent AFTER the inline attachment) must be part of the body, not an attachment');

		$attachmentNames = array_column($result['attachments'], 'name');
		$this->assertContains('file.pdf', $attachmentNames, 'the inline PDF must still be listed as an attachment');
		$this->assertNotContains('Von meinem iPad gesendet', $attachmentNames);
		$this->assertCount(1, $result['attachments'], 'the second text/plain fragment must not become a 2nd attachment');
	}

	public function testMultipleHtmlFragmentsAroundInlineAttachmentsTicket128091()
	{
		$raw = <<<EOT
		Content-Type: multipart/alternative; boundary="A1"
		MIME-Version: 1.0
		From: sender@example.org
		To: recipient@example.org
		Subject: ticket 128091 repro

		--A1
		Content-Type: text/plain; charset=utf-8

		plain summary, not used once html is present
		--A1
		Content-Type: multipart/mixed; boundary="B2"

		--B2
		Content-Type: text/html; charset=utf-8

		<html><body>Hallo, wenn ich eine Datei anh&auml;nge</body></html>
		--B2
		Content-Type: image/png; name="logo.png"
		Content-Disposition: inline; filename="logo.png"

		not-really-png-bytes
		--B2
		Content-Type: text/html; charset=utf-8

		<html><body>wird hier unterbrochen</body></html>
		--B2
		Content-Type: application/pdf; name="doc.pdf"
		Content-Disposition: inline; filename="doc.pdf"

		not-really-pdf-bytes
		--B2
		Content-Type: text/html; charset=utf-8

		<html><body>Rest des Textes</body></html>
		--B2--

		--A1--

		EOT;
		$structure = \Horde_Mime_Part::parseMessage(str_replace("\n", "\r\n", $raw));

		$result = Imap::emailBodyFields($this->mockImap($structure), self::MAILBOX, self::UID, $structure);

		$this->assertCount(1, $result['htmlBody'], 'exactly one htmlBody entry expected (fragments concatenated)');
		$htmlId = $result['htmlBody'][0]['partId'];
		$html = $result['bodyValues'][$htmlId]['value'];
		$this->assertStringContainsString('wenn ich eine Datei', $html);
		$this->assertStringContainsString('wird hier unterbrochen', $html);
		$this->assertStringContainsString('Rest des Textes', $html);

		$attachmentNames = array_column($result['attachments'], 'name');
		$this->assertContains('logo.png', $attachmentNames);
		$this->assertContains('doc.pdf', $attachmentNames);
		$this->assertCount(2, $result['attachments'],
			'the three html fragments must not show up as separate "PartX.Y.htm" attachments');
	}
}
