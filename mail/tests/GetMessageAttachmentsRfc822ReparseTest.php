<?php
/**
 * EGroupware API: Mail::getMessageAttachments() re-parse coverage for a base64-wrapped
 * message/rfc822 attachment
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;

/**
 * Ticket #125561's own follow-up (ralf, live: "I can open the forwarded eml, but it does not
 * show the original attachment, but the eml again"): Rfc822AttachmentPart declares
 * Content-Transfer-Encoding: base64 for a forward-as-attachment's carried message (RFC 2046
 * [5.2.1] only permits 7bit/8bit/binary there - base64 was needed to sidestep a real-world
 * delivery-path corruption bug, see that class's own docblock). Dovecot correctly refuses to
 * decode that (illegal for that content-type) when building its own BODYSTRUCTURE for the
 * embedded message, so the nested structure it reports collapses to a single degenerate, empty
 * text/plain leaf instead of the real one - live-verified 2026-09-29 against a real forwarded
 * message: the wrapper's own reported child was a single empty text/plain part, its real
 * attachment (a JPEG) nowhere to be seen.
 *
 * getMessageAttachments() now detects a message/rfc822 slice and re-parses that part's own true,
 * decoded bytes (Mail\Jmap\Imap::fetchRawPart(), already fixed for the exact same reason) instead
 * of trusting Dovecot's own (degenerate) BODYSTRUCTURE for it. This fixture reproduces that exact
 * degenerate shape as the fake IMAP server's own structure response, and supplies the real,
 * base64-encoded nested message as the fetchRawPart() response - reproducing the live bug
 * end-to-end, without a real IMAP server.
 */
class GetMessageAttachmentsRfc822ReparseTest extends TestCase
{
	/**
	 * Dovecot's own degenerate view: a message/rfc822 wrapper part whose ONLY reported child is a
	 * single, empty text/plain leaf - never the real nested structure.
	 */
	private function degenerateOuterStructure() : array
	{
		$emptyChild = new \Horde_Mime_Part();
		$emptyChild->setType('text/plain');

		$wrapper = new \Horde_Mime_Part();
		$wrapper->setType('message/rfc822');
		$wrapper->setDisposition('attachment');
		$wrapper->setDispositionParameter('filename', 'forwarded.eml');
		$wrapper[] = $emptyChild;

		$plainBody = new \Horde_Mime_Part();
		$plainBody->setType('text/plain');
		$plainBody->setContents('Mit freundlichen Gruessen');

		$outer = new \Horde_Mime_Part();
		$outer->setType('multipart/mixed');
		$outer[] = $plainBody;
		$outer[] = $wrapper;
		$outer->buildMimeIds();

		return [$outer, $wrapper->getMimeId()];
	}

	/**
	 * The real nested message ("Fehler", a real attached JPEG) - what the wrapper's own true,
	 * decoded bytes actually are, once you bypass Dovecot's degenerate BODYSTRUCTURE for it.
	 */
	private function realNestedMessageRaw() : string
	{
		$jpegBytes = "\xFF\xD8\xFF\xE0fake jpeg bytes\xFF\xD9";
		return "Subject: Fehler\r\n".
			"From: Aleceia Bonilla <abonilla@steamvalve.com>\r\n".
			"Content-Type: multipart/mixed; boundary=\"BOUND\"\r\n".
			"\r\n".
			"--BOUND\r\n".
			"Content-Type: text/plain\r\n".
			"\r\n".
			"Thank you.\r\n".
			"--BOUND\r\n".
			"Content-Type: image/jpeg\r\n".
			"Content-Disposition: attachment; filename=\"egw error.JPG\"\r\n".
			"Content-Transfer-Encoding: base64\r\n".
			"\r\n".
			base64_encode($jpegBytes)."\r\n".
			"--BOUND--\r\n";
	}

	private function fakeIcServer(\Horde_Mime_Part $outerStructure, string $wrapperPartId, string $nestedRaw) : \Horde_Imap_Client_Socket
	{
		$base64Wrapper = base64_encode($nestedRaw);
		$icServer = $this->createStub(\Horde_Imap_Client_Socket::class);
		$icServer->method('fetch')->willReturnCallback(
			function($mailbox, $query, $options) use ($outerStructure, $wrapperPartId, $base64Wrapper)
			{
				$results = new \Horde_Imap_Client_Fetch_Results();
				$fetch = new \Horde_Imap_Client_Data_Fetch();
				$fetch->setStructure($outerStructure);
				if ($query->contains(\Horde_Imap_Client::FETCH_BODYPART))
				{
					// decode label deliberately mislabeled '8bit' (matches Horde's own BINARY-fetch
					// fallback, see fetchRawPart()'s own docblock) - fetchRawPart() must trust the
					// MIME header below over this, exactly like the real live bug
					$fetch->setBodyPart($wrapperPartId, $base64Wrapper, '8bit');
					$fetch->setMimeHeader($wrapperPartId,
						"Content-Type: message/rfc822\r\nContent-Transfer-Encoding: base64\r\n\r\n");
				}
				$uid = null;
				foreach ($options['ids'] as $id)
				{
					$uid = $id;
					break;
				}
				$results[$uid] = $fetch;
				return $results;
			}
		);
		return $icServer;
	}

	public function testFindsTheRealNestedAttachmentInsteadOfDovecotsDegenerateStructure()
	{
		[$outerStructure, $wrapperPartId] = $this->degenerateOuterStructure();
		$nestedRaw = $this->realNestedMessageRaw();

		$mail = new class extends Mail {
			public function __construct()
			{
			}
		};
		$mail->icServer = $this->fakeIcServer($outerStructure, $wrapperPartId, $nestedRaw);

		$attachments = $mail->getMessageAttachments('1', $wrapperPartId, null, false, false, false, 'INBOX');

		$this->assertCount(1, $attachments,
			"Dovecot's own degenerate structure (a single empty text/plain leaf) must never be ".
			"what gets listed - the real, re-parsed nested attachment must be");
		$this->assertSame('image/jpeg', $attachments[0]['mimeType']);
		$this->assertSame('egw error.JPG.jpg', $attachments[0]['name']);
		// a compound partID (wrapper:local) - see resolvePart()'s own docblock for why a LATER
		// view/download of this exact entry needs both halves to be resolvable again
		$this->assertStringStartsWith($wrapperPartId.':', $attachments[0]['partID']);
	}

	/**
	 * getAttachment() must resolve that same compound partID back to the real image bytes -
	 * the "view"/"download" half of the same round trip, not just the listing.
	 */
	public function testGetAttachmentResolvesTheCompoundPartIdBackToTheRealImageBytes()
	{
		[$outerStructure, $wrapperPartId] = $this->degenerateOuterStructure();
		$nestedRaw = $this->realNestedMessageRaw();

		$mail = new class extends Mail {
			public function __construct()
			{
			}
		};
		$mail->icServer = $this->fakeIcServer($outerStructure, $wrapperPartId, $nestedRaw);

		$attachments = $mail->getMessageAttachments('1', $wrapperPartId, null, false, false, false, 'INBOX');
		$compoundPartId = $attachments[0]['partID'];

		$result = $mail->getAttachment('1', $compoundPartId, 0, false, false, 'INBOX');

		$this->assertSame('image/jpeg', $result['type']);
		$this->assertSame('egw error.JPG.jpg', $result['filename']);
		$this->assertSame("\xFF\xD8\xFF\xE0fake jpeg bytes\xFF\xD9", $result['attachment']);
	}
}
