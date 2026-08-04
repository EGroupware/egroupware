<?php
/**
 * Round-trip tests for Api\CalDAV\JsCalendar date handling (start/duration/timeZone,
 * recurrenceOverrides for EXDATE/RDATE/RECURRENCE-ID equivalents), mirroring
 * calendar/tests/IcalDateRoundtripTest.php for the JsCalendar (RFC 8984) format.
 *
 * JsCalendar::DateTime()/parseDateTime() always convert through explicit DateTimeZone
 * objects (never PHP's default/server timezone), so unlike the historical iCalendar
 * EXDATE/RDATE bug, these are not expected to depend on the server timezone - these
 * tests exist to prove and preserve that invariant.
 *
 * Note: JsCalendar::parseJsEvent() does not (yet) support parsing recurrenceRules /
 * recurrenceOverrides (it throws), so the EXDATE/RDATE/RECURRENCE-ID tests only cover
 * the export (JsEvent) direction, reading the produced LocalDateTime values back into
 * Api\DateTime objects ourselves for comparison.
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage caldav
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

require_once realpath(__DIR__.'/../AppTest.php');	// Application test base

use EGroupware\Api;

class JsCalendarDateRoundtripTest extends Api\AppTest
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
	 * Set the simulated server timezone under test. The client/user timezone is
	 * pinned to EVENT_TZID too, so the server timezone is the only variable under test.
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

	protected function context(string $server_timezone, bool $whole_day) : string
	{
		return ' [server='.$server_timezone.', whole_day='.($whole_day ? 'yes' : 'no').']';
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
			// close to midnight, so a timezone offset error would shift the calendar day
			$start = new Api\DateTime($base.' 00:30:00', $tz);
			$end = clone $start;
			$end->modify('+1 hour');
		}
		return [$start, $end];
	}

	/**
	 * Parse a JsCalendar LocalDateTime value (no timezone suffix, RFC 8984 ¶1.4.5)
	 * back into an Api\DateTime, using the timezone it was rendered in - mirrors
	 * JsBase::parseDateTime().
	 */
	protected function parseLocal(string $value, string $tzid) : Api\DateTime
	{
		return new Api\DateTime($value, new \DateTimeZone($tzid));
	}

	protected function assertSameInstant(Api\DateTime $expected, Api\DateTime $actual, string $message) : void
	{
		$this->assertEquals($expected->getTimestamp(), $actual->getTimestamp(), $message);
	}

	/**
	 * See IcalDateRoundtripTest::assertSameDate() - whole-day values are floating
	 * (timezone-less) calendar dates, so compare the date part as-is.
	 */
	protected function assertSameDate(Api\DateTime $expected, Api\DateTime $actual, string $message) : void
	{
		$this->assertEquals($expected->format('Y-m-d'), $actual->format('Y-m-d'), $message);
	}

	/**
	 * calendar_boupdate::save()/update() normalize Api\DateTime fields in place (e.g.
	 * convert their timezone), mutating the very objects passed in. Clone anything
	 * that's still needed afterwards before handing it to save()/update(), so local
	 * expectation variables stay pristine.
	 */
	protected function cloneDates(array $event) : array
	{
		foreach($event as $name => $value)
		{
			if ($value instanceof Api\DateTime)
			{
				$event[$name] = clone $value;
			}
			elseif (is_array($value) && in_array($name, ['recur_exception', 'recur_rdates'], true))
			{
				$event[$name] = array_map(static function ($date)
				{
					return $date instanceof Api\DateTime ? clone $date : $date;
				}, $value);
			}
		}
		return $event;
	}

	protected function createEvent(array $overrides) : int
	{
		$event = $this->cloneDates($overrides) + [
			'title'        => 'JsCalendar roundtrip '.uniqid(),
			'owner'        => $GLOBALS['egw_info']['user']['account_id'],
			'tzid'         => self::EVENT_TZID,
			'participants' => [
				$GLOBALS['egw_info']['user']['account_id'] => 'A',
			],
		];
		$cal_id = $this->bo->save($event);
		$this->assertGreaterThan(0, $cal_id, 'Could not create event');
		$this->event_ids[] = (int)$cal_id;
		return (int)$cal_id;
	}

	/**
	 * calendar_bo::read() caches the last scalar-id read, so re-reading the same id
	 * right after save()/update() can return stale data. Passing the id as an array
	 * bypasses that cache (same workaround used elsewhere in the calendar test suite).
	 */
	protected function readFresh(int $cal_id) : array
	{
		$events = $this->bo->read([$cal_id]);
		return $events[$cal_id];
	}

	protected function updateEvent(array $event, bool $ignore_conflicts = true)
	{
		return $this->bo->update($this->cloneDates($event), $ignore_conflicts);
	}

	/**
	 * A fully-populated (but never saved) event array for JsCalendar::JsEvent(), which
	 * reads many fields unconditionally. Persisting a recur_exception/recur_rdates
	 * requires it to exactly match an already-materialized occurrence row in the
	 * database, which is DB-internal plumbing unrelated to what's under test here -
	 * JsEvent() is a pure function, so build the event in memory instead.
	 */
	protected function fullEvent(array $overrides) : array
	{
		return $overrides + [
			'uid'             => 'jscal-'.uniqid(),
			'etag'            => 0,
			'created'         => null,
			'modified'        => null,
			'title'           => 'JsCalendar export test',
			'description'     => '',
			'location'        => '',
			'owner'           => $GLOBALS['egw_info']['user']['account_id'],
			'tzid'            => self::EVENT_TZID,
			'whole_day'       => false,
			'non_blocking'    => 0,
			'participants'    => [
				$GLOBALS['egw_info']['user']['account_id'] => 'A',
			],
			'alarm'           => [],
			'deleted'         => null,
			'priority'        => 0,
			'category'        => '',
			'public'          => 1,
			'recur_interval'  => 0,
			'recur_data'      => 0,
			'recur_enddate'   => null,
			'recur_exception' => [],
			'recur_rdates'    => [],
		];
	}

	/**
	 * start/duration/timeZone/showWithoutTime roundtrip for a single (non-recurring) event.
	 *
	 * Pass criteria:
	 * - JsCalendar::JsEvent()'s start/duration, re-parsed through parseJsEvent(),
	 *   yields the same start/end (same instant for timed events, same calendar day
	 *   for whole-day events) and the same whole_day flag, both when the server
	 *   timezone is UTC and Europe/Berlin.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('timezoneWholeDayProvider')]
	public function testStartDurationRoundtrip(string $server_timezone, bool $whole_day)
	{
		$this->setServerTimezone($server_timezone);
		$context = $this->context($server_timezone, $whole_day);

		[$start, $end] = $this->startEnd($whole_day);
		$cal_id = $this->createEvent([
			'start'      => $start,
			'end'        => $end,
			'recur_type' => MCAL_RECUR_NONE,
			'whole_day'  => $whole_day,
		]);
		$event = $this->readFresh($cal_id);

		$data = JsCalendar::JsEvent($event, false);
		$this->assertArrayHasKey('start', $data, 'JsEvent missing start'.$context);
		$this->assertArrayNotHasKey('recurrenceOverrides', $data, 'Unexpected recurrenceOverrides'.$context);

		$parsed = JsCalendar::parseJsEvent(
			json_encode($data), [], 'application/jscalendar+json', 'PUT',
			$GLOBALS['egw_info']['user']['account_id']
		);
		$this->assertInstanceOf(Api\DateTime::class, $parsed['start'], 'parsed start not Api\\DateTime'.$context);
		$this->assertInstanceOf(Api\DateTime::class, $parsed['end'], 'parsed end not Api\\DateTime'.$context);

		$assert = $whole_day ? 'assertSameDate' : 'assertSameInstant';
		$this->$assert($start, $parsed['start'], 'start'.$context);
		$this->$assert($end, $parsed['end'], 'end'.$context);
		$this->assertSame($whole_day, $parsed['whole_day'], 'whole_day'.$context);
	}

	/**
	 * EXDATE equivalent: a recur_exception on a daily recurring event must show up
	 * as a recurrenceOverrides entry with "excluded": true, keyed by the correct
	 * LocalDateTime, both for server timezone UTC and Europe/Berlin.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('timezoneWholeDayProvider')]
	public function testExdateOverrideExport(string $server_timezone, bool $whole_day)
	{
		$this->setServerTimezone($server_timezone);
		$context = $this->context($server_timezone, $whole_day);

		[$start, $end] = $this->startEnd($whole_day);
		$recur_enddate = clone $start;
		$recur_enddate->modify('+6 days');
		$exception_date = clone $start;
		$exception_date->modify('+2 days');

		$event = $this->fullEvent([
			'start'           => $start,
			'end'             => $end,
			'whole_day'       => $whole_day,
			'recur_type'      => MCAL_RECUR_DAILY,
			'recur_interval'  => 1,
			'recur_enddate'   => $recur_enddate,
			'recur_exception' => [clone $exception_date],
		]);

		$data = JsCalendar::JsEvent($event, false);
		$this->assertArrayHasKey('recurrenceOverrides', $data, 'Missing recurrenceOverrides'.$context);

		$excluded_key = null;
		foreach($data['recurrenceOverrides'] as $key => $override)
		{
			if (!empty($override['excluded']))
			{
				$excluded_key = $key;
				break;
			}
		}
		$this->assertNotNull($excluded_key, 'No excluded override found'.$context);

		$actual = $this->parseLocal($excluded_key, $event['tzid']);
		$assert = $whole_day ? 'assertSameDate' : 'assertSameInstant';
		$this->$assert($exception_date, $actual, 'EXDATE override'.$context);
	}

	/**
	 * RDATE equivalent: an additional date on an explicit-recurrence-dates (RDATE)
	 * event must show up as a recurrenceOverrides entry with its own "start", keyed
	 * by the correct LocalDateTime, both for server timezone UTC and Europe/Berlin.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('timezoneWholeDayProvider')]
	public function testRdateOverrideExport(string $server_timezone, bool $whole_day)
	{
		$this->setServerTimezone($server_timezone);
		$context = $this->context($server_timezone, $whole_day);

		[$start, $end] = $this->startEnd($whole_day);
		[$extra] = $this->startEnd($whole_day, '2026-01-25');

		$event = $this->fullEvent([
			'start'        => $start,
			'end'          => $end,
			'whole_day'    => $whole_day,
			'recur_type'   => \calendar_rrule::RDATE,
			'recur_rdates' => [clone $start, clone $extra],
		]);

		$data = JsCalendar::JsEvent($event, false);
		$this->assertArrayHasKey('recurrenceOverrides', $data, 'Missing recurrenceOverrides'.$context);

		$rdate_key = null;
		foreach($data['recurrenceOverrides'] as $key => $override)
		{
			if (empty($override['excluded']) && isset($override['start']))
			{
				$rdate_key = $key;
				break;
			}
		}
		$this->assertNotNull($rdate_key, 'No rdate override found'.$context);

		$assert = $whole_day ? 'assertSameDate' : 'assertSameInstant';
		$actual_key = $this->parseLocal($rdate_key, $event['tzid']);
		$this->$assert($extra, $actual_key, 'RDATE override key'.$context);

		$actual_start = $this->parseLocal($data['recurrenceOverrides'][$rdate_key]['start'], $event['tzid']);
		$this->$assert($extra, $actual_start, 'RDATE override start'.$context);
	}

	/**
	 * RECURRENCE-ID equivalent: a real exception (override) event for one occurrence
	 * of a daily recurring series must show up as a recurrenceOverrides entry keyed
	 * by the original (un-moved) occurrence, with its own moved "start", both for
	 * server timezone UTC and Europe/Berlin.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('timezoneWholeDayProvider')]
	public function testRecurrenceIdOverrideExport(string $server_timezone, bool $whole_day)
	{
		$this->setServerTimezone($server_timezone);
		$context = $this->context($server_timezone, $whole_day);

		[$start, $end] = $this->startEnd($whole_day);
		$recur_enddate = clone $start;
		$recur_enddate->modify('+6 days');
		$cal_id = $this->createEvent([
			'start'         => $start,
			'end'           => $end,
			'recur_type'    => MCAL_RECUR_DAILY,
			'recur_enddate' => $recur_enddate,
			'whole_day'     => $whole_day,
		]);

		$occurrence_start = clone $start;
		$occurrence_start->modify('+2 days');
		$occurrence_end = clone $end;
		$occurrence_end->modify('+2 days');

		$exception = $this->readFresh($cal_id);
		unset($exception['id']);
		$exception['reference'] = $cal_id;
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
		$exception_id = (int)$this->bo->save($this->cloneDates($exception), true);
		$this->assertGreaterThan(0, $exception_id, 'Could not create exception'.$context);
		$this->event_ids[] = $exception_id;

		$master = $this->readFresh($cal_id);
		$exception_event = $this->readFresh($exception_id);

		$data = JsCalendar::JsEvent($master, false, [$exception_event]);
		$this->assertArrayHasKey('recurrenceOverrides', $data, 'Missing recurrenceOverrides'.$context);
		$key = array_key_first($data['recurrenceOverrides']);
		$this->assertNotNull($key, 'No override found'.$context);

		$assert = $whole_day ? 'assertSameDate' : 'assertSameInstant';
		$actual_key = $this->parseLocal($key, $master['tzid']);
		$this->$assert($occurrence_start, $actual_key, 'RECURRENCE-ID override key'.$context);

		$override = $data['recurrenceOverrides'][$key];
		$this->assertArrayHasKey('start', $override, 'Override missing moved start'.$context);
		$override_start = $this->parseLocal($override['start'], $master['tzid']);
		$this->$assert($moved_start, $override_start, 'RECURRENCE-ID override start'.$context);
	}
}
