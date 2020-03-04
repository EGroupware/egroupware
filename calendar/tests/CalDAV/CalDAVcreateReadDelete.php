<?php
/**
 * CalDAV tests: create, read and delete an event
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package calendar
 * @subpackage tests
 * @copyright (c) 2020 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once __DIR__.'/../../../api/tests/CalDAVTest.php';

use EGroupware\Api\CalDAVTest;
use GuzzleHttp\RequestOptions;

class CalDAVcreateReadDelete extends CalDAVTest
{
	/**
	 * Test accessing CalDAV without authentication
	 */
	public function testNoAuth()
	{
		$response = $this->getClient([])->get($this->url('/'));

		$this->assertHttpStatus(401, $response);
	}

	/**
	 * Test accessing CalDAV with authentication
	 */
	public function testAuth()
	{
		$response = $this->getClient()->get($this->url('/'));

		$this->assertHttpStatus(200, $response);
	}

	const EVENT_URL = '/demo/calendar/new-event-1233456789-new.ics';
	const EVENT_ICAL = <<<EOICAL
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:20110406T210000
DTEND;TZID=Europe/Berlin:20110406T220000
DTSTAMP:20110406T183747Z
LAST-MODIFIED:20110406T183747Z
LOCATION:Somewhere
SUMMARY:Tonight
UID:new-event-1233456789-new
END:VEVENT
END:VCALENDAR
EOICAL;

	/**
	 * Create an event
	 */
	public function testCreate()
	{
		$response = $this->getClient()->put($this->url(self::EVENT_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'If-None-Match' => '*',
			],
			RequestOptions::BODY => self::EVENT_ICAL,
		]);

		$this->assertHttpStatus(201, $response);
	}

	/**
	 * Read created event
	 */
	public function testRead()
	{
		$response = $this->getClient()->get($this->url(self::EVENT_URL));

		$this->assertHttpStatus(200, $response);
		$this->assertIcal(self::EVENT_ICAL, $response->getBody());
	}

	/**
	 * Delete created event
	 */
	public function testDelete()
	{
		$response = $this->getClient()->delete($this->url(self::EVENT_URL));

		$this->assertHttpStatus(204, $response);
	}

	/**
	 * Read created event
	 */
	public function testReadDeleted()
	{
		$response = $this->getClient()->get($this->url(self::EVENT_URL));

		$this->assertHttpStatus(404, $response);
	}
}