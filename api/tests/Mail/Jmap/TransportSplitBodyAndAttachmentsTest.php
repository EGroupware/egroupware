<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\Transport::splitBodyAndAttachments()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

/**
 * Meeting requests sent through an account submitting via JMAP (Stalwart) instead of classic
 * SMTP arrived unusable: a live customer report showed the .ics attached as a bare
 * "Content-Type: text/calendar" file with "Content-Disposition: attachment", instead of the
 * RFC 5546 "text/calendar; method=REQUEST" alternative body mail clients need to show
 * Accept/Decline buttons.
 *
 * Root cause: Transport::sendJmap() re-parses Api\Mailer's already-rendered MIME (Mailer builds
 * the calendar part correctly, see Mail/Jmap/TransportSplitBodyAndAttachmentsTest's own fixture
 * below) and used to hand every non-plain/non-html part straight to
 * Horde_Mime_Part::getType() - which drops ALL Content-Type parameters, including "method=" -
 * then defaulted its JMAP 'disposition' to 'attachment' whenever the part had none set (which
 * Mailer::setAlternativBody()'s calendar part never does, since it isn't a file attachment).
 *
 * Fixed by re-attaching "method=" from the part's own Content-Type parameter, and only
 * defaulting disposition to 'attachment' for parts that AREN'T a method-bearing (iTip/iMip) part.
 */
class TransportSplitBodyAndAttachmentsTest extends \PHPUnit\Framework\TestCase
{
	private function split(\Horde_Mime_Part $mime) : array
	{
		$method = new \ReflectionMethod(Transport::class, 'splitBodyAndAttachments');
		$method->setAccessible(true);
		return $method->invoke(null, $mime);
	}

	/**
	 * Same structure Api\Mailer::send() builds for a meeting invite: multipart/alternative of
	 * [text/plain, text/calendar;charset=utf-8;method=REQUEST] - see Mailer::setAlternativBody().
	 */
	private function inviteMime() : \Horde_Mime_Part
	{
		$raw = "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"b\"\r\n\r\n".
			"--b\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nbody\r\n".
			"--b\r\nContent-Type: text/calendar; charset=utf-8; method=REQUEST\r\n\r\n".
			"BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n--b--\r\n";
		return \Horde_Mime_Part::parseMessage($raw);
	}

	public function testCalendarInviteKeepsMethodParameterAndIsNotAnAttachment()
	{
		[, , , $attachments] = $this->split($this->inviteMime());

		$this->assertCount(1, $attachments);
		$this->assertSame('text/calendar; method=REQUEST', $attachments[0]['type']);
		$this->assertNull($attachments[0]['disposition'], 'an iTip part must not be flagged as a generic attachment');
	}

	public function testPlainAndCalendarBodyValuesAreExtracted()
	{
		[$bodyValues, $textBody, $htmlBody] = $this->split($this->inviteMime());

		$this->assertSame('body', $bodyValues['plain']['value']);
		$this->assertSame('plain', $textBody[0]['partId']);
		$this->assertNull($htmlBody);
	}

	/**
	 * A real file attachment (eg. a PDF) alongside a plain body must still default to
	 * 'attachment' - only a method-bearing part is exempted.
	 */
	public function testRegularAttachmentStillDefaultsToAttachmentDisposition()
	{
		$raw = "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"b\"\r\n\r\n".
			"--b\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nbody\r\n".
			"--b\r\nContent-Type: application/pdf\r\nContent-Transfer-Encoding: base64\r\n\r\n".
			base64_encode('%PDF-1.4')."\r\n--b--\r\n";
		$mime = \Horde_Mime_Part::parseMessage($raw);

		[, , , $attachments] = $this->split($mime);

		$this->assertCount(1, $attachments);
		$this->assertSame('application/pdf', $attachments[0]['type']);
		$this->assertSame('attachment', $attachments[0]['disposition']);
	}
}
