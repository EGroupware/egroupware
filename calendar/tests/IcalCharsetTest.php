<?php
/**
 * Tests for the charset of an iCal given to calendar_ical::icaltoegw()
 *
 * The charset of a meeting request is the one of its mail part, which is often wrong:
 * iCalendar is UTF-8 by default (RFC 5545), but IMAP servers like Dovecot and JMAP (RFC 8621)
 * report "us-ascii" for a part without charset, and some senders declare one not matching the content.
 * Converting UTF-8 from such a charset showed eg. "BÃ¼ro" instead of "Büro" in the meeting request.
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

use EGroupware\Api;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

class IcalCharsetTest extends Api\LoggedInTest
{
	const TITLE = 'Besprechung ab 8:00 Uhr im Büro';

	/**
	 * Title of the event parsed from an iCal
	 *
	 * @param string $title in the charset of the iCal
	 * @param ?string $charset charset given for the iCal
	 * @return string
	 */
	protected function importedTitle(string $title, ?string $charset) : string
	{
		$ical = implode("\r\n", [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//EGroupware//NONSGML EGroupware Calendar Test//EN',
			'METHOD:REQUEST',
			'BEGIN:VEVENT',
			'UID:ical-charset-test-'.md5($title.$charset),
			'DTSTAMP:20261120T100000Z',
			'DTSTART;VALUE=DATE:20261127',
			'DTEND;VALUE=DATE:20261128',
			'SUMMARY:'.$title,
			'ORGANIZER;CN=Organizer:mailto:organizer@example.org',
			'END:VEVENT',
			'END:VCALENDAR',
		])."\r\n";

		$events = (new \calendar_ical())->icaltoegw($ical, '', $charset);

		$this->assertIsArray($events);
		$this->assertCount(1, $events);

		return $events[0]['title'];
	}

	public static function wrongCharsets() : array
	{
		return [['us-ascii'], ['US-ASCII'], ['iso-8859-1'], ['ISO-8859-15'], ['windows-1252']];
	}

	/**
	 * UTF-8 content is not converted, whatever charset is given for it
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('wrongCharsets')]
	public function testUtf8ContentWithWrongCharset(string $charset) : void
	{
		$this->assertSame(self::TITLE, $this->importedTitle(self::TITLE, $charset));
	}

	public static function utf8Charsets() : array
	{
		return [[null], ['utf-8'], ['UTF-8']];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('utf8Charsets')]
	public function testUtf8Content(?string $charset) : void
	{
		$this->assertSame(self::TITLE, $this->importedTitle(self::TITLE, $charset));
	}

	/**
	 * Content really in the given charset is still converted
	 */
	public function testLatin1Content() : void
	{
		$this->assertSame(self::TITLE, $this->importedTitle(mb_convert_encoding(self::TITLE, 'ISO-8859-1', 'UTF-8'), 'iso-8859-1'));
	}

	/**
	 * Content without special characters is the same in all these charsets
	 */
	public function testAsciiContent() : void
	{
		$this->assertSame('Meeting', $this->importedTitle('Meeting', 'us-ascii'));
	}
}
