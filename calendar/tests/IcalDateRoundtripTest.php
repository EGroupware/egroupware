<?php
/**
 * Round-trip tests for calendar_ical date handling (DTSTART/DTEND/EXDATE/RDATE/RECURRENCE-ID)
 *
 * Regression tests for a bug where EXDATE/RDATE were exported with the wrong wall-clock time
 * whenever PHP's default/server timezone was not UTC: Horde_Icalendar re-interpreted the
 * already timezone-correct digits calendar_ical had computed using the *server* timezone
 * instead of treating them as floating (i.e. verbatim) when a TZID parameter was present.
 * DTSTART/DTEND/RECURRENCE-ID were not affected, but are covered here too.
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;

class IcalDateRoundtripTest extends \EGroupware\Api\AppTest
{
	const EVENT_TZID = 'Europe/Berlin';

	/**
	 * @var \calendar_boupdate
	 */
	protected $bo;

	/**
	 * @var int[]
	 */
	protected $event_ids = [];

	protected static $orig_date_tz;
	protected static $orig_server_timezone;
	protected static $orig_user_tz;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		self::$orig_date_tz = date_default_timezone_get();
		self::$orig_server_timezone = $GLOBALS['egw_info']['server']['server_timezone'] ?? 'UTC';
		self::$orig_user_tz = $GLOBALS['egw_info']['user']['preferences']['common']['tz'] ?? 'UTC';
	}

	public static function tearDownAfterClass() : void
	{
		date_default_timezone_set(self::$orig_date_tz);
		$GLOBALS['egw_info']['server']['server_timezone'] = self::$orig_server_timezone;
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = self::$orig_user_tz;
		Api\DateTime::init();
		parent::tearDownAfterClass();
	}

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \calendar_boupdate();
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->event_ids) as $id)
		{
			$this->bo->delete($id, 0, true);
			// Delete again to remove from delete history
			$this->bo->delete($id, 0, true);
		}
		$this->event_ids = [];

		date_default_timezone_set(self::$orig_date_tz);
		$GLOBALS['egw_info']['server']['server_timezone'] = self::$orig_server_timezone;
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = self::$orig_user_tz;
		Api\DateTime::init();

		parent::tearDown();
	}

	/**
	 * Set the simulated server timezone under test.
	 *
	 * The client/user timezone is pinned to EVENT_TZID too, so the only variable under
	 * test is the server timezone (matching how the bug was described: it only manifests
	 * when the server timezone is not UTC, independent of the event's own timezone).
	 */
	protected function setServerTimezone(string $server_timezone) : void
	{
		$GLOBALS['egw_info']['server']['server_timezone'] = $server_timezone;
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = self::EVENT_TZID;
		date_default_timezone_set($server_timezone);
		Api\DateTime::init();
	}

	public static function timezoneWholeDayProvider() : array
	{
		$cases = [];
		foreach(['UTC', 'Europe/Berlin'] as $tz)
		{
			foreach([false, true] as $whole_day)
			{
				$cases['server='.$tz.' whole_day='.($whole_day ? 'yes' : 'no')] = [$tz, $whole_day];
			}
		}
		return $cases;
	}

	/**
	 * A calendar_ical instance restricted to just the fields under test and forced to
	 * export in the event's own timezone (tzid=false), so DTSTART/DTEND/EXDATE/RDATE all
	 * get a TZID parameter - exactly the situation the fixed bug depended on.
	 */
	protected function minimalIcal() : \calendar_ical
	{
		$ical = new \calendar_ical();
		$ical->tzid = false;	// use event's own tzid, not UTC
		$ical->supportedFields = [
			'title'           => 'title',
			'start'           => 'start',
			'end'             => 'end',
			'uid'             => 'uid',
			'recur_type'      => 'recur_type',
			'recur_interval'  => 'recur_interval',
			'recur_data'      => 'recur_data',
			'recur_enddate'   => 'recur_enddate',
			'recur_exception' => 'recur_exception',
			'recur_rdates'    => 'recur_rdates',
		];
		return $ical;
	}

	protected function baseEvent() : array
	{
		return [
			'id'              => -1,
			'tzid'            => self::EVENT_TZID,
			'recur_interval'  => 0,
			'recur_data'      => 0,
			'recur_enddate'   => null,
			'recur_exception' => [],
			'recur_rdates'    => [],
			'created'         => null,
			'modified'        => null,
			'alarm'           => [],
		];
	}

	protected function startEnd(bool $whole_day, string $base = '2026-01-10') : array
	{
		$tz = new \DateTimeZone(self::EVENT_TZID);
		if ($whole_day)
		{
			$start = new Api\DateTime($base.' 00:00:00', $tz);
			$end = clone $start;
			$end->modify('+1 day');
			$end->modify('-1 second');
		}
		else
		{
			// close to midnight, so a server-timezone offset error shifts the calendar day
			$start = new Api\DateTime($base.' 00:30:00', $tz);
			$end = clone $start;
			$end->modify('+1 hour');
		}
		return [$start, $end];
	}

	protected function context(string $server_timezone, bool $whole_day) : string
	{
		return ' [server='.$server_timezone.', whole_day='.($whole_day ? 'yes' : 'no').']';
	}

	protected function assertSameInstant($expected, $actual, string $message) : void
	{
		$this->assertInstanceOf(Api\DateTime::class, $actual, $message.' (not an Api\\DateTime)');
		$this->assertEquals($expected->getTimestamp(), $actual->getTimestamp(), $message);
	}

	/**
	 * Whole-day values are floating (timezone-less) calendar dates. This codebase's
	 * convention (see calendar_bo::isWholeDay()/db2data()) represents them as
	 * 00:00:00-23:59:59 in *some* timezone (server or event, depending on the code
	 * path) rather than a fixed one, so the calendar day must be read off each object
	 * as-is, without re-projecting through setTimezone() - that would treat the
	 * floating date as a precise instant and could shift it onto the wrong day.
	 */
	protected function assertSameDate($expected, $actual, string $message) : void
	{
		$this->assertInstanceOf(Api\DateTime::class, $actual, $message.' (not an Api\\DateTime)');
		$this->assertEquals($expected->format('Y-m-d'), $actual->format('Y-m-d'), $message);
	}

	/**
	 * DTSTART/DTEND roundtrip for a single (non-recurring) event.
	 *
	 * Pass criteria:
	 * - Exported iCal, re-imported through calendar_ical, yields the same start/end
	 *   (same instant for timed events, same calendar day for whole-day events),
	 *   both when the server timezone is UTC and Europe/Berlin.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('timezoneWholeDayProvider')]
	public function testDtstartDtendRoundtrip(string $server_timezone, bool $whole_day)
	{
		$this->setServerTimezone($server_timezone);
		$context = $this->context($server_timezone, $whole_day);

		[$start, $end] = $this->startEnd($whole_day);

		$event = [
			'uid'        => 'dtstart-dtend-'.uniqid(),
			'title'      => 'DTSTART/DTEND roundtrip',
			'start'      => $start,
			'end'        => $end,
			'recur_type' => MCAL_RECUR_NONE,
		] + $this->baseEvent();

		$ics = $this->minimalIcal()->exportVCal([$event], '2.0', 'PUBLISH');
		$this->assertIsString($ics, 'Export failed'.$context);

		$parsed = (new \calendar_ical())->icaltoegw($ics);
		$this->assertNotEmpty($parsed, 'Re-import failed'.$context);
		$imported = $parsed[0];

		$assert = $whole_day ? 'assertSameDate' : 'assertSameInstant';
		$this->$assert($start, $imported['start'], 'DTSTART'.$context);
		$this->$assert($end, $imported['end'], 'DTEND'.$context);
	}

	/**
	 * EXDATE roundtrip on a daily recurring event.
	 *
	 * Pass criteria:
	 * - The exception date exported as EXDATE (with a TZID parameter, since the event
	 *   has a timezone) re-imports to the same calendar day it was created with, both
	 *   for server timezone UTC and Europe/Berlin. Before the fix, a non-UTC server
	 *   timezone could shift the exported EXDATE onto the wrong day.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('timezoneWholeDayProvider')]
	public function testExdateRoundtrip(string $server_timezone, bool $whole_day)
	{
		$this->setServerTimezone($server_timezone);
		$context = $this->context($server_timezone, $whole_day);

		[$start, $end] = $this->startEnd($whole_day);
		$recur_enddate = clone $start;
		$recur_enddate->modify('+6 days');
		$exception_date = clone $start;
		$exception_date->modify('+2 days');

		$event = [
			'uid'             => 'exdate-'.uniqid(),
			'title'           => 'EXDATE roundtrip',
			'start'           => $start,
			'end'             => $end,
			'recur_type'      => MCAL_RECUR_DAILY,
			'recur_interval'  => 1,
			'recur_enddate'   => $recur_enddate,
			'recur_exception' => [clone $exception_date],
		] + $this->baseEvent();

		$ics = $this->minimalIcal()->exportVCal([$event], '2.0', 'PUBLISH');
		$this->assertIsString($ics, 'Export failed'.$context);
		$this->assertMatchesRegularExpression('/^EXDATE/m', $ics, 'EXDATE missing from export'.$context);

		$parsed = (new \calendar_ical())->icaltoegw($ics);
		$this->assertNotEmpty($parsed, 'Re-import failed'.$context);
		$imported = $parsed[0];

		$this->assertCount(1, $imported['recur_exception'] ?? [], 'EXDATE count'.$context);
		$this->assertSameDate($exception_date, $imported['recur_exception'][0], 'EXDATE'.$context);
	}

	/**
	 * RDATE roundtrip on an explicit-recurrence-dates (RDATE) event.
	 *
	 * Pass criteria:
	 * - The additional date exported as RDATE (with a TZID parameter) re-imports to the
	 *   same value it was created with, both for server timezone UTC and Europe/Berlin.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('timezoneWholeDayProvider')]
	public function testRdateRoundtrip(string $server_timezone, bool $whole_day)
	{
		$this->setServerTimezone($server_timezone);
		$context = $this->context($server_timezone, $whole_day);

		[$start, $end] = $this->startEnd($whole_day);
		[$extra] = $this->startEnd($whole_day, '2026-01-25');

		$event = [
			'uid'          => 'rdate-'.uniqid(),
			'title'        => 'RDATE roundtrip',
			'start'        => $start,
			'end'          => $end,
			'recur_type'   => \calendar_rrule::RDATE,
			'recur_rdates' => [clone $start, clone $extra],
		] + $this->baseEvent();

		$ics = $this->minimalIcal()->exportVCal([$event], '2.0', 'PUBLISH');
		$this->assertIsString($ics, 'Export failed'.$context);
		$this->assertMatchesRegularExpression('/^RDATE/m', $ics, 'RDATE missing from export'.$context);

		$parsed = (new \calendar_ical())->icaltoegw($ics);
		$this->assertNotEmpty($parsed, 'Re-import failed'.$context);
		$imported = $parsed[0];

		// the first RDATE (identical to DTSTART) is not re-exported, only the extra one is
		$this->assertCount(1, $imported['recur_rdates'] ?? [], 'RDATE count'.$context);
		$assert = $whole_day ? 'assertSameDate' : 'assertSameInstant';
		$this->$assert($extra, $imported['recur_rdates'][0], 'RDATE'.$context);
	}

	/**
	 * Create a real daily recurring master event plus a genuine exception (override)
	 * event for one of its occurrences, so RECURRENCE-ID export can be exercised
	 * (it requires reading the referenced master event from the database).
	 *
	 * @return array [cal_id, exception_id, original occurrence start]
	 */
	protected function createMasterWithException(bool $whole_day) : array
	{
		[$start, $end] = $this->startEnd($whole_day);
		$recur_enddate = clone $start;
		$recur_enddate->modify('+6 days');

		$master = [
			'title'         => 'RECURRENCE-ID roundtrip '.uniqid(),
			'owner'         => $GLOBALS['egw_info']['user']['account_id'],
			'start'         => $start,
			'end'           => $end,
			'tzid'          => self::EVENT_TZID,
			'recur_type'    => MCAL_RECUR_DAILY,
			'recur_enddate' => $recur_enddate,
			'whole_day'     => $whole_day,
			'participants'  => [
				$GLOBALS['egw_info']['user']['account_id'] => 'A',
			],
		];
		$cal_id = $this->bo->save($master);
		$this->assertGreaterThan(0, $cal_id, 'Master event could not be created');
		$this->event_ids[] = (int)$cal_id;

		$occurrence_start = clone $start;
		$occurrence_start->modify('+2 days');
		$occurrence_end = clone $end;
		$occurrence_end->modify('+2 days');

		$exception = $this->bo->read($cal_id);
		unset($exception['id']);
		$exception['reference'] = (int)$cal_id;
		$exception['recurrence'] = clone $occurrence_start;
		$moved_start = clone $occurrence_start;
		$moved_start->modify($whole_day ? '+1 day' : '-2 hours');
		$moved_end = clone $occurrence_end;
		$moved_end->modify($whole_day ? '+1 day' : '-2 hours');
		$exception['start'] = $moved_start;
		$exception['end'] = $moved_end;
		$exception['recur_type'] = MCAL_RECUR_NONE;
		foreach(['recur_enddate', 'recur_interval', 'recur_exception', 'recur_data', 'recur_rdates'] as $name)
		{
			unset($exception[$name]);
		}

		$exception_id = (int)$this->bo->save($exception, true);
		$this->assertGreaterThan(0, $exception_id, 'Exception event could not be created');
		$this->event_ids[] = $exception_id;

		return [(int)$cal_id, $exception_id, $occurrence_start];
	}

	/**
	 * RECURRENCE-ID roundtrip for an exception (override) event.
	 *
	 * Pass criteria:
	 * - The exported RECURRENCE-ID (with a TZID parameter) re-imports to the original,
	 *   un-moved occurrence date/time, both for server timezone UTC and Europe/Berlin.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('timezoneWholeDayProvider')]
	public function testRecurrenceIdRoundtrip(string $server_timezone, bool $whole_day)
	{
		$this->setServerTimezone($server_timezone);
		$context = $this->context($server_timezone, $whole_day);

		[$cal_id, $exception_id, $expected_recurrence] = $this->createMasterWithException($whole_day);
		unset($cal_id);

		$ics = (new \calendar_ical())->exportVCal($exception_id, '2.0', 'PUBLISH');
		$this->assertIsString($ics, 'Export failed'.$context);
		$this->assertStringContainsString(
			$whole_day ? 'RECURRENCE-ID;VALUE=DATE' : 'RECURRENCE-ID',
			$ics, 'RECURRENCE-ID missing from export'.$context
		);

		$parsed = (new \calendar_ical())->icaltoegw($ics);
		$this->assertNotEmpty($parsed, 'Re-import failed'.$context);
		$imported = $parsed[0];

		$assert = $whole_day ? 'assertSameDate' : 'assertSameInstant';
		$this->$assert($expected_recurrence, $imported['recurrence'], 'RECURRENCE-ID'.$context);
	}
}
