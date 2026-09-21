<?php
/**
 * EGroupware - Calendar holidays timezone regression test
 *
 * Reproduces https://help.egroupware.org/t/feiertage-werden-teilweise-an-zwei-tagen-angezeigt-hat-noch-jemand-das-problem/80027
 * where a whole-day holiday is shown on two consecutive calendar days when
 * server_timezone differs from the user's timezone.
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;
use PHPUnit\Framework\Attributes\DataProvider;

class HolidayTimezoneTest extends \EGroupware\Api\AppTest
{
	protected static $orig_date_tz;
	protected $fixture;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$orig_date_tz = date_default_timezone_get();
	}

	public static function tearDownAfterClass(): void
	{
		date_default_timezone_set(self::$orig_date_tz);
		parent::tearDownAfterClass();
	}

	protected function tearDown(): void
	{
		if ($this->fixture && file_exists($this->fixture))
		{
			unlink($this->fixture);
		}
		parent::tearDown();
	}

	protected function setTimezones(string $client, string $server)
	{
		$GLOBALS['egw_info']['server']['server_timezone'] = $server;
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $client;
		date_default_timezone_set($server);
		Api\DateTime::init();
	}

	/**
	 * Write a minimal single whole-day-event iCal feed and return a file:// url for it
	 *
	 * @param string $ymd eg. "20260101"
	 * @param string $title
	 * @return string file:// url
	 */
	protected function writeHolidayIcal(string $ymd, string $title)
	{
		$next = new \DateTime($ymd);
		$next->add(new \DateInterval('P1D'));

		$ics = "BEGIN:VCALENDAR\r\n".
			"VERSION:2.0\r\n".
			"PRODID:-//EGroupware//HolidayTimezoneTest//EN\r\n".
			"BEGIN:VEVENT\r\n".
			"UID:holiday-timezone-test-$ymd@egroupware.org\r\n".
			"DTSTAMP:20260101T000000Z\r\n".
			"DTSTART;VALUE=DATE:$ymd\r\n".
			"DTEND;VALUE=DATE:".$next->format('Ymd')."\r\n".
			"SUMMARY:$title\r\n".
			"END:VEVENT\r\n".
			"END:VCALENDAR\r\n";

		$this->fixture = tempnam(sys_get_temp_dir(), 'holiday-test-').'.ics';
		file_put_contents($this->fixture, $ics);

		return 'file://'.$this->fixture;
	}

	/**
	 * A whole-day holiday must always render on exactly the day it was defined for,
	 * regardless of the (possibly differing) server- and user-timezone
	 *
	 */
	#[DataProvider('timezoneProvider')]
	public function testHolidayKeepsSingleDay(string $server, string $user)
	{
		$this->setTimezones($user, $server);

		$url = $this->writeHolidayIcal('20260101', 'New Year');

		$years = \calendar_holidays::render($url, 2026, 2026);

		$this->assertArrayHasKey(2026, $years);
		$this->assertCount(1, $years[2026],
			"Holiday rendered on ".count($years[2026])." days instead of 1 (server=$server, user=$user): ".
			implode(', ', array_keys($years[2026])));
		$this->assertArrayHasKey('20260101', $years[2026]);
	}

	public static function timezoneProvider()
	{
		return [
			'server=user=UTC' => ['UTC', 'UTC'],
			'server=UTC, user=Berlin' => ['UTC', 'Europe/Berlin'],
			'server=Berlin, user=Berlin' => ['Europe/Berlin', 'Europe/Berlin'],
			'server=New_York, user=Berlin' => ['America/New_York', 'Europe/Berlin'],
			'server=New_York, user=UTC' => ['America/New_York', 'UTC'],
			'server=New_York, user=Sofia' => ['America/New_York', 'Europe/Sofia'],
			'server=New_York, user=New_York' => ['America/New_York', 'America/New_York'],
		];
	}
}
