<?php
/**
 * CalDAV GET of an all-day yearly series, which first occurrence (= series start) is excluded
 *
 * Help forum "CalDAV: GET of all-day yearly recurring event returns HTTP 200 with empty body (exception on first
 * occurrence)": DAVx5 aborted the whole sync of a calendar, because the multiget answered for such an event with
 * status 200 but without calendar data.
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once __DIR__.'/../../../api/tests/CalDAVTest.php';

use EGroupware\Api\CalDAVTest;
use GuzzleHttp\RequestOptions;

class YearlyAllDayFirstOccurrenceExcludedTest extends CalDAVTest
{
	/** @var string account_lid of the calendar owner */
	protected static $owner;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		self::$owner = 'yearly-ad-'.bin2hex(random_bytes(3));
		$data = [];
		self::createUser(self::$owner, $data);
	}

	/**
	 * @param string $start all-day start, eg. "2026-11-01"
	 * @param string[] $exdates dates (Y-m-d) to exclude
	 */
	protected function ical(string $uid, string $start, array $exdates) : string
	{
		$day = fn($d, $add='') => (new \DateTimeImmutable($d))->modify($add ?: '+0 day')->format('Ymd');
		$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//EGroupware//Test//EN\r\nBEGIN:VEVENT\r\nUID:$uid\r\n".
			"DTSTAMP:20260801T120000Z\r\nSUMMARY:Yearly all-day\r\n".
			"DTSTART;VALUE=DATE:".$day($start)."\r\nDTEND;VALUE=DATE:".$day($start, '+1 day')."\r\nRRULE:FREQ=YEARLY\r\n";
		foreach($exdates as $exdate)
		{
			$ics .= "EXDATE;VALUE=DATE:".$day($exdate)."\r\n";
		}
		return $ics."END:VEVENT\r\nEND:VCALENDAR\r\n";
	}

	public static function seriesProvider() : array
	{
		$in = fn($modify) => (new \DateTimeImmutable('today'))->modify($modify)->format('Y-m-d');
		return [
			'starts in 30 days, first occurrence excluded (the report)' => [$in('+30 days'), [$in('+30 days')]],
			'starts in 30 days, no exception'                          => [$in('+30 days'), []],
			'started 2 years ago, first occurrence excluded'           => [$in('-2 years'), [$in('-2 years')]],
			'starts in 2 years, no exception'                          => [$in('+2 years'), []],
			'starts in 2 years, first occurrence excluded'             => [$in('+2 years'), [$in('+2 years')]],
		];
	}

	/**
	 * Pass criteria: PUT and GET of the series return a VEVENT with the RRULE (and EXDATE), never an empty body
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('seriesProvider')]
	public function testGetReturnsTheEvent(string $start, array $exdates)
	{
		$uid = $this->makeUid('yearly-ad');
		$path = $this->eventUrlFor(self::$owner, $uid);

		$response = $this->putResource($path, 'text/calendar', $this->ical($uid, $start, $exdates), self::$owner);
		$this->assertHttpStatus([201, 204], $response, 'PUT');

		$response = $this->getClient(self::$owner)->get($this->url($path));
		$this->assertHttpStatus(200, $response, 'GET');
		$body = (string)$response->getBody();
		$this->assertNotSame('', $body, 'GET returned status 200 with an empty body, Content-Length: '.$response->getHeaderLine('Content-Length'));
		$this->assertStringContainsString('BEGIN:VEVENT', $body);
		$this->assertStringContainsString('RRULE:FREQ=YEARLY', $body);
		if ($exdates)
		{
			$this->assertMatchesRegularExpression('/^EXDATE/m', $body, 'EXDATE missing');
		}

		$this->deleteResource($path, self::$owner);
	}

	/**
	 * What DAVx5 does after a sync-token change: calendar-multiget REPORT for the changed events
	 *
	 * Pass criteria: the response contains calendar-data with the VEVENT for the series, not an empty one
	 * ("Received multi-get response without data")
	 */
	public function testMultigetReturnsCalendarData()
	{
		$start = (new \DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d');
		$uid = $this->makeUid('yearly-ad-mg');
		$path = $this->eventUrlFor(self::$owner, $uid);
		$this->assertHttpStatus([201, 204], $this->putResource($path, 'text/calendar', $this->ical($uid, $start, [$start]), self::$owner), 'PUT');

		$response = $this->getClient(self::$owner)->request('REPORT', $this->url('/'.self::$owner.'/calendar/'), [
			RequestOptions::HEADERS => ['Content-Type' => 'text/xml; charset=utf-8', 'Depth' => '1'],
			RequestOptions::BODY => '<?xml version="1.0" encoding="utf-8"?>'.
				'<C:calendar-multiget xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav"><D:prop><D:getetag/><C:calendar-data/></D:prop>'.
				'<D:href>/egroupware/groupdav.php'.$path.'</D:href></C:calendar-multiget>',
		]);
		$this->assertHttpStatus([200, 207], $response, 'REPORT');
		$xml = (string)$response->getBody();
		$this->assertStringContainsString('calendar-data', $xml);
		$this->assertStringContainsString('BEGIN:VEVENT', $xml, 'multiget response without calendar data: '.substr($xml, 0, 600));
		$this->assertStringContainsString('RRULE:FREQ=YEARLY', $xml);

		$this->deleteResource($path, self::$owner);
	}
}
