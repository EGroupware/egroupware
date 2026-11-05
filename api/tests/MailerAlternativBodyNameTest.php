<?php
/**
 * EGroupware Api: Test Mailer's calendar-invite alternative body keeps its filename
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__ . '/LoggedInTest.php';

/**
 * Ralf, 2026-11-05: "we still not setting the part-name causing the UI to display 'Unbekannter
 * Part N.ics', instead of the event.ics we're setting in calendar, before the mail was reparsed
 * for JMAP sending". Root cause: addAttachment()/addStringAttachment() divert a
 * "text/calendar; method=..." part to setAlternativBody() (so it becomes an RFC 5546 inline
 * alternative, not a file attachment - see MailerInviteMethodTest/TransportSplitBodyAndAttachments
 * Test's own history) - but setAlternativBody() never took a filename at all, so the caller's own
 * $name/$filename argument (eg. calendar_boupdate.inc.php's 'cal.ics') was silently dropped. This
 * pre-dates the JMAP work; it just never surfaced visibly over classic SMTP (no attachment icon to
 * mislabel there), while Stalwart/JMAP's attachment listing shows the part's lack of a name as
 * "Unbekannter Part"/"Unknown part".
 *
 * Fixed by including 'name' in the Content-Type parameters passed to setAlternativBody() -
 * deliberately NOT via Horde_Mime_Part::setName() (which also sets Content-Disposition's
 * filename, implicitly flipping an unset disposition to "attachment" - see
 * Horde_Mime_Part::setDispositionParameter() - exactly what setAlternativBody() exists to avoid).
 */
class MailerAlternativBodyNameTest extends LoggedInTest
{
	private function alternativBody(Mailer $mailer) : \Horde_Mime_Part
	{
		$prop = new \ReflectionProperty(Mailer::class, '_alternativBody');
		$prop->setAccessible(true);
		return $prop->getValue($mailer);
	}

	public function testAddStringAttachmentKeepsNameWithoutBecomingAnAttachment()
	{
		$mailer = new Mailer('initbasic');
		$mailer->addStringAttachment('BEGIN:VCALENDAR
END:VCALENDAR', 'cal.ics', '8bit', 'text/calendar; method=REQUEST');

		$part = $this->alternativBody($mailer);
		$this->assertSame('cal.ics', $part->getName());
		$this->assertSame('', $part->getDisposition(), 'must stay without a disposition - setName() would imply "attachment"');
		$this->assertSame('REQUEST', $part->getContentTypeParameter('method'));
	}

	public function testAddAttachmentKeepsNameWithoutBecomingAnAttachment()
	{
		$mailer = new Mailer('initbasic');
		$file = tempnam(sys_get_temp_dir(), 'ics');
		file_put_contents($file, "BEGIN:VCALENDAR\nEND:VCALENDAR");
		try
		{
			$mailer->addAttachment($file, 'event.ics', 'text/calendar; method=CANCEL');
		}
		finally
		{
			unlink($file);
		}

		$part = $this->alternativBody($mailer);
		$this->assertSame('event.ics', $part->getName());
		$this->assertSame('', $part->getDisposition());
		$this->assertSame('CANCEL', $part->getContentTypeParameter('method'));
	}

	/**
	 * No name given at all (eg. a caller that only ever passed one through addStringAttachment())
	 * must not crash and must not invent a disposition either.
	 */
	public function testNoNameStaysWithoutDispositionOrName()
	{
		$mailer = new Mailer('initbasic');
		$mailer->addStringAttachment('BEGIN:VCALENDAR
END:VCALENDAR', '', '8bit', 'text/calendar; method=REQUEST');

		$part = $this->alternativBody($mailer);
		$this->assertSame('', (string)$part->getName());
		$this->assertSame('', $part->getDisposition());
	}
}
