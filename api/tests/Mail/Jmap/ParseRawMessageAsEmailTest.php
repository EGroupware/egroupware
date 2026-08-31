<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\Imap::parseRawMessageAsEmail()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

use PHPUnit\Framework\TestCase;

/**
 * doc/ai/projects/mail-compose-jmap-migration.md's follow-up (2026-08-31, ralf: "go ahead with
 * that server-side blob-parse approach") - parseRawMessageAsEmail() is the pure parsing half of
 * parseBlobAsEmail(), built so a message/rfc822 attachment (eg. a bounce/NDM's own original
 * message) can be viewed without a live IMAP/JMAP connection at all (JMAP has no "parse this blob
 * as a real Email" verb). Pure function (raw bytes in, JMAP-shaped array out) - no database,
 * session, or IMAP/SMTP connection touched, so this doesn't need LoggedInTest at all, same
 * reasoning as SmimeTest.
 */
class ParseRawMessageAsEmailTest extends TestCase
{
	private const SOURCE_BLOB_ID = 'phpunit-source-blob';

	public function testPlainTextMessageParsesHeadersAndBody()
	{
		$raw = "From: Sender Name <sender@example.org>\r\n"
			."To: Recipient <recipient@example.org>\r\n"
			."Subject: Plain test\r\n"
			."Date: Mon, 31 Aug 2026 12:00:00 +0000\r\n"
			."Content-Type: text/plain; charset=utf-8\r\n\r\n"
			."Hello world\r\n";

		$result = Imap::parseRawMessageAsEmail($raw, self::SOURCE_BLOB_ID);

		$this->assertSame([['email' => 'sender@example.org', 'name' => 'Sender Name']], $result['from']);
		$this->assertSame([['email' => 'recipient@example.org', 'name' => 'Recipient']], $result['to']);
		$this->assertSame([], $result['cc']);
		$this->assertSame('Plain test', $result['subject']);
		$this->assertStringContainsString('2026', $result['date']);
		$this->assertCount(1, $result['textBody']);
		$this->assertSame([], $result['htmlBody']);
		$this->assertSame([], $result['attachments']);
		$partId = $result['textBody'][0]['partId'];
		$this->assertSame('Hello world', trim($result['bodyValues'][$partId]['value']));
	}

	public function testMultipartAlternativeParsesBothBodyParts()
	{
		$boundary = 'phpunit-boundary';
		$raw = "From: sender@example.org\r\n"
			."To: recipient@example.org\r\n"
			."Subject: Alternative test\r\n"
			."Date: Mon, 31 Aug 2026 12:00:00 +0000\r\n"
			."MIME-Version: 1.0\r\n"
			."Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n"
			."--$boundary\r\n"
			."Content-Type: text/plain; charset=utf-8\r\n\r\n"
			."plain version\r\n"
			."--$boundary\r\n"
			."Content-Type: text/html; charset=utf-8\r\n\r\n"
			."<p>html version</p>\r\n"
			."--$boundary--\r\n";

		$result = Imap::parseRawMessageAsEmail($raw, self::SOURCE_BLOB_ID);

		$this->assertCount(1, $result['textBody']);
		$this->assertCount(1, $result['htmlBody']);
		$textPartId = $result['textBody'][0]['partId'];
		$htmlPartId = $result['htmlBody'][0]['partId'];
		$this->assertSame('plain version', trim($result['bodyValues'][$textPartId]['value']));
		$this->assertSame('<p>html version</p>', trim($result['bodyValues'][$htmlPartId]['value']));
		$this->assertSame([], $result['attachments'], 'body parts must never be listed as attachments');
	}

	/**
	 * Matches the actual live scenario this was built for: a bounce/NDM's own multipart/report,
	 * carrying a text/plain delivery report AND a nested message/rfc822 attachment (the original
	 * failed message).
	 */
	public function testNestedMessageRfc822IsListedAsAttachmentNotBody()
	{
		$boundary = 'phpunit-report-boundary';
		$nested = "From: original-sender@example.org\r\nTo: original-to@example.org\r\n"
			."Subject: original\r\nDate: Mon, 31 Aug 2026 11:00:00 +0000\r\n\r\noriginal body\r\n";
		$raw = "From: \"Mail Delivery Subsystem\" <MAILER-DAEMON@example.org>\r\n"
			."To: sender@example.org\r\n"
			."Subject: Failed to deliver message\r\n"
			."Date: Mon, 31 Aug 2026 12:00:00 +0000\r\n"
			."MIME-Version: 1.0\r\n"
			."Content-Type: multipart/report; report-type=\"delivery-status\"; boundary=\"$boundary\"\r\n\r\n"
			."--$boundary\r\n"
			."Content-Type: text/plain; charset=utf-8\r\n\r\n"
			."Your message could not be delivered.\r\n"
			."--$boundary\r\n"
			."Content-Type: message/rfc822\r\n\r\n"
			.$nested
			."--$boundary--\r\n";

		$result = Imap::parseRawMessageAsEmail($raw, self::SOURCE_BLOB_ID);

		$this->assertSame('Failed to deliver message', $result['subject']);
		$this->assertCount(1, $result['textBody']);
		$textPartId = $result['textBody'][0]['partId'];
		$this->assertStringContainsString('could not be delivered', $result['bodyValues'][$textPartId]['value']);

		$this->assertCount(1, $result['attachments'], 'the nested message/rfc822 part must be listed as an attachment');
		$attachment = $result['attachments'][0];
		$this->assertSame('message/rfc822', $attachment['type']);
		$this->assertStringStartsWith('parsed:'.self::SOURCE_BLOB_ID.':', $attachment['blobId'],
			'a nested attachment blobId must be prefixed with the SOURCE blob id, not the outer message\'s own');
	}

	public function testEmptyCcOmittedNotEmptyArrayVsMissingHeaderAmbiguity()
	{
		$raw = "From: sender@example.org\r\nTo: recipient@example.org\r\nSubject: No cc\r\n"
			."Date: Mon, 31 Aug 2026 12:00:00 +0000\r\nContent-Type: text/plain\r\n\r\nx\r\n";

		$result = Imap::parseRawMessageAsEmail($raw, self::SOURCE_BLOB_ID);

		$this->assertSame([], $result['cc']);
	}
}
