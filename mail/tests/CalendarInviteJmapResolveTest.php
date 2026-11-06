<?php
/**
 * EGroupware Mail: ticket #127361 - opening a calendar invitation on a JMAP-only/Stalwart account
 * crashed with "Mail server closed the connection unexpectedly", a raw IMAP socket attempt against
 * what is actually a JMAPS endpoint on port 443 - see SmimeSignedCalendarInviteSpecialCaseTest.php
 * for the dispatch-decision half of this fix.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Imap\Jmap as StalwartImap;
use EGroupware\Api\Mail\Jmap\Imap as JmapShim;
use EGroupware\Api\Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Stalwart's resolveCalendarJmap() - like BareContentStalwartResolveTest.php, jmapClient() is a
 * simple, directly mockable instance method, so the full method is tested end-to-end here.
 */
#[AllowMockObjectsWithoutExpectations]
class CalendarInviteJmapResolveTest extends \PHPUnit\Framework\TestCase
{
	private const ICS = "BEGIN:VCALENDAR\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\nUID:fake-event\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

	/** multipart/alternative with text/plain + text/html + an inline (non-attachment) text/calendar part */
	private function calendarBodyStructure(array $calendarOverrides = []) : array
	{
		return [
			'type' => 'multipart/alternative',
			'subParts' => [
				['type' => 'text/plain', 'size' => 50],
				['type' => 'text/html', 'size' => 100],
				array_merge([
					'type' => 'text/calendar',
					'size' => strlen(self::ICS),
					'disposition' => 'inline',
					'partId' => '2',
					'blobId' => 'fake-blob-id',
					'charset' => 'utf-8',
				], $calendarOverrides),
			],
		];
	}

	private function mockJmapReturning(array $bodyStructure, string $raw) : StalwartImap
	{
		$imap = $this->getMockBuilder(StalwartImap::class)
			->disableOriginalConstructor()
			->onlyMethods(['jmapClient'])
			->getMock();

		$client = new class($bodyStructure, $raw)
		{
			public function __construct(private array $bodyStructure, private string $raw)
			{
			}

			public function emailGet($id, $properties)
			{
				return ['bodyStructure' => $this->bodyStructure];
			}

			public function downloadBlob($blobId, $name, $type)
			{
				return $this->raw;
			}
		};
		$imap->method('jmapClient')->willReturn($client);
		return $imap;
	}

	public function testFindInlineCalendarPartLocatesNestedPart() : void
	{
		$structure = $this->calendarBodyStructure();
		$found = JmapShim::findInlineCalendarPart($structure);

		$this->assertNotNull($found);
		$this->assertSame('text/calendar', $found['type']);
		$this->assertSame('2', $found['partId']);
	}

	public function testFindInlineCalendarPartIgnoresAttachedIcsFile() : void
	{
		$structure = $this->calendarBodyStructure(['disposition' => 'attachment', 'name' => 'invite.ics']);
		$this->assertNull(JmapShim::findInlineCalendarPart($structure));
	}

	public function testResolveCalendarJmapReturnsIcsBytesCharsetAndMethod() : void
	{
		$imap = $this->mockJmapReturning($this->calendarBodyStructure(), self::ICS);

		$result = $imap->resolveCalendarJmap('email-id-123', '2');

		$this->assertSame('utf-8', $result['charset']);
		$this->assertSame(self::ICS, $result['attachment']);
		$this->assertSame('REQUEST', $result['method']);
	}

	public function testResolveCalendarJmapFallsBackToUtf8WhenNoCharsetGiven() : void
	{
		$imap = $this->mockJmapReturning($this->calendarBodyStructure(['charset' => null]), self::ICS);

		$result = $imap->resolveCalendarJmap('email-id-123', '2');

		$this->assertSame('utf-8', $result['charset']);
	}

	public function testResolveCalendarJmapThrowsWhenPartIdDoesNotMatch() : void
	{
		$imap = $this->mockJmapReturning($this->calendarBodyStructure(), self::ICS);

		$this->expectException(Exception::class);
		$imap->resolveCalendarJmap('email-id-123', 'does-not-exist');
	}
}
