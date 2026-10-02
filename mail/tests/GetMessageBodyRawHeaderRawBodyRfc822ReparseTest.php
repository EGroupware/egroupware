<?php
/**
 * EGroupware API: Mail::getMessageBody()/getMessageRawHeader()/getMessageRawBody() re-parse
 * coverage for a base64-wrapped message/rfc822 attachment
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;

/**
 * Ticket #125561's own follow-up (ralf, live: "for me the body of the forward eml is still
 * empty" / "viewing the source or header from the eml shows the forwarded message"):
 * getMessageAttachments() already needed to stop trusting Dovecot's own (degenerate)
 * BODYSTRUCTURE for a base64-wrapped message/rfc822 part's nested content (see resolvePart()'s/
 * reparseMessagePart()'s own docblocks) - getMessageBody(), getMessageRawHeader() and
 * getMessageRawBody() all had the exact same blind spot: each sliced Dovecot's own degenerate
 * structure (or did a plain per-part IMAP HEADER/BODY fetch keyed by partID, which fails the
 * same way) instead of re-parsing the wrapper's own true, decoded bytes.
 *
 * Same fixture as GetMessageAttachmentsRfc822ReparseTest.php: Dovecot's own degenerate view of
 * the wrapper (a single empty text/plain leaf) as the fake IMAP server's structure response, the
 * real nested message ("Fehler", a real text body) as fetchRawPart()'s response.
 */
class GetMessageBodyRawHeaderRawBodyRfc822ReparseTest extends TestCase
{
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

	private function realNestedMessageRaw() : string
	{
		return "Subject: Fehler\r\n".
			"From: Aleceia Bonilla <abonilla@steamvalve.com>\r\n".
			"Content-Type: text/plain\r\n".
			"\r\n".
			"Thank you, best regards, Scott.\r\n";
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

	private function mail(\Horde_Imap_Client_Socket $icServer) : Mail
	{
		$mail = new class extends Mail {
			public function __construct()
			{
			}
		};
		$mail->icServer = $icServer;
		return $mail;
	}

	public function testGetMessageBodyFindsTheRealNestedBodyInsteadOfDovecotsEmptyPlaceholder()
	{
		[$outerStructure, $wrapperPartId] = $this->degenerateOuterStructure();
		$mail = $this->mail($this->fakeIcServer($outerStructure, $wrapperPartId, $this->realNestedMessageRaw()));

		$bodyParts = $mail->getMessageBody('1', '', $wrapperPartId, null, false, 'INBOX');

		$this->assertNotEmpty($bodyParts);
		$this->assertStringContainsString('Thank you, best regards, Scott.', $bodyParts[0]['body'] ?? '');
	}

	public function testGetMessageRawHeaderReturnsTheCarriedMessagesOwnHeaderNotTheContainingOne()
	{
		[$outerStructure, $wrapperPartId] = $this->degenerateOuterStructure();
		$mail = $this->mail($this->fakeIcServer($outerStructure, $wrapperPartId, $this->realNestedMessageRaw()));

		$rawHeader = $mail->getMessageRawHeader('1', $wrapperPartId, 'INBOX');

		$this->assertStringContainsString('Subject: Fehler', $rawHeader);
		$this->assertStringContainsString('From: Aleceia Bonilla', $rawHeader);
		$this->assertStringNotContainsString('Thank you, best regards, Scott.', $rawHeader,
			"a HEADER fetch must never include the body");
	}

	public function testGetMessageRawBodyReturnsTheCarriedMessagesOwnFullRawBytes()
	{
		[$outerStructure, $wrapperPartId] = $this->degenerateOuterStructure();
		$nestedRaw = $this->realNestedMessageRaw();
		$mail = $this->mail($this->fakeIcServer($outerStructure, $wrapperPartId, $nestedRaw));

		$raw = $mail->getMessageRawBody('1', $wrapperPartId, 'INBOX');

		$this->assertSame($nestedRaw, $raw);
	}
}
