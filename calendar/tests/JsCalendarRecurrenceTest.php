<?php
/**
 * Read-path pin-down tests for recurring events in Api\CalDAV\JsCalendar (the JMAP/JSCalendar
 * REST representation).
 *
 * Writing is blocked outright today: JsCalendar::parseJsEvent() throws for any of
 * 'recurrenceRules'/'recurrenceOverrides'/'excludedRecurrenceRules' (JsCalendar.php ~173-177,
 * "Creating or modifying recurring events is NOT (yet) implemented!"). This file pins down BOTH
 * that write-guard and the exact shape of today's read-only output - the JSCalendar RecurrenceRule
 * this produces is itself constrained by calendar_rrule's reduced model (JsCalendar.php's own
 * comment at ~946 says as much: "EGroupware only supports a subset of iCal recurrence rules (e.g.
 * only byDay and byMonthDay, no other by-types)!") - so future write support has an existing read
 * contract it must not break, and a concrete baseline for what "supporting recurring events via
 * REST" actually has to add. See doc/ai/projects/calendar-rrule-standards-gap.md.
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;
use EGroupware\Api\CalDAV\JsCalendar;

class JsCalendarRecurrenceTest extends Api\AppTest
{
	/**
	 * @var \calendar_boupdate
	 */
	protected $bo;

	/**
	 * @var int[]
	 */
	protected $event_ids = [];

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \calendar_boupdate();
	}

	protected function tearDown() : void
	{
		foreach (array_unique($this->event_ids) as $id)
		{
			$this->bo->delete($id, 0, true);
			$this->bo->delete($id, 0, true);
		}
		$this->event_ids = [];
		parent::tearDown();
	}

	/**
	 * Create a real MONTHLY_WDAY (2nd Tuesday) recurring event with an UNTIL enddate, via the
	 * same calendar_boupdate::save() path the UI uses - so JsCalendar reads a fully realistic
	 * event, not a hand-built fixture array.
	 */
	protected function createMonthlySecondTuesday() : int
	{
		// computed relative to "now" so the fixture never drifts into the past
		$start = new Api\DateTime('second tuesday of next month 08:00:00', new \DateTimeZone('UTC'));
		$end = clone $start;
		$end->modify('+1 hour');
		$recur_enddate = clone $start;
		$recur_enddate->modify('+5 months');

		$event = [
			'title'         => 'JsCalendar recurrence read-path test '.uniqid(),
			'owner'         => $GLOBALS['egw_info']['user']['account_id'],
			'start'         => $start,
			'end'           => $end,
			'tzid'          => 'UTC',
			'recur_type'    => MCAL_RECUR_MONTHLY_WDAY,
			'recur_enddate' => $recur_enddate,
			'participants'  => [$GLOBALS['egw_info']['user']['account_id'] => 'A'],
		];
		$cal_id = (int)$this->bo->save($event);
		$this->assertGreaterThan(0, $cal_id, 'Could not create fixture event');
		$this->event_ids[] = $cal_id;
		return $cal_id;
	}

	/**
	 * Pass criteria:
	 * - recurrenceRules has exactly ONE rule (only one RRULE per event is representable at all).
	 * - frequency is 'monthly', byDay is [{day:'tu', nthOfPeriod:2}] - the DTSTART-derived
	 *   ordinal+weekday, NOT an explicit, independently-settable value (KNOWN GAP, see
	 *   RruleTest::testMonthlyWdaySecondWeekdayOfMonth() for the same limitation at the engine
	 *   level).
	 * - Regression test for a spec-shape bug fixed alongside this: per RFC 8984, RecurrenceRule's
	 *   `byDay` must be a JSON *array* of NDay objects; JsCalendar.php used to assign a single NDay
	 *   object directly (no outer `[...]`) - a real client parsing strict JSCalendar could have
	 *   rejected it. Asserted here as a 1-element list.
	 * - until is present (recur_enddate converted to JSCalendar's 'until').
	 * - no byMonth/byWeekNo/bySetPosition/byHour/byMinute/bySecond keys are ever produced -
	 *   confirms none of those RFC 8984/5545 by-x rule parts are supported.
	 */
	public function testRecurrenceRulesReadShape() : void
	{
		$cal_id = $this->createMonthlySecondTuesday();

		$data = JsCalendar::JsEvent($cal_id, false);

		$this->assertArrayHasKey('recurrenceRules', $data);
		$this->assertCount(1, $data['recurrenceRules'], 'Only a single RRULE is ever representable');

		$rule = $data['recurrenceRules'][0];
		$this->assertSame('monthly', $rule['frequency']);
		$this->assertArrayHasKey('until', $rule, 'Expected recur_enddate to surface as until');
		$this->assertArrayHasKey('byDay', $rule);
		$this->assertTrue(array_is_list($rule['byDay']), 'byDay must be a JSON array of NDay objects per RFC 8984');
		$this->assertCount(1, $rule['byDay'], 'Only one NDay entry is ever representable');
		$this->assertSame('tu', $rule['byDay'][0]['day']);
		$this->assertSame(2, $rule['byDay'][0]['nthOfPeriod'], 'Expected 2nd-Tuesday ordinal');

		foreach (['byMonth', 'byWeekNo', 'bySetPosition', 'byYearDay', 'byHour', 'byMinute', 'bySecond'] as $unsupported)
		{
			$this->assertArrayNotHasKey($unsupported, $rule, "'$unsupported' should never appear - unsupported RRULE part");
		}
	}

	/**
	 * Pass criteria: attempting to PUT/POST a JsEvent JSON body containing 'recurrenceRules'
	 * throws, confirming recurring-event writes are still blocked outright today
	 * (JsCalendar.php ~173-177). This is the exact contract the future REST-recurrence feature
	 * needs to replace deliberately, not something that should silently start working.
	 */
	public function testWritingRecurrenceRulesIsBlocked() : void
	{
		$json = json_encode([
			'@type' => 'Event',
			'uid' => 'jscalendar-write-guard-'.uniqid(),
			'title' => 'Attempted recurring event write',
			'start' => '2024-01-09T08:00:00',
			'timeZone' => 'UTC',
			'duration' => 'PT1H',
			'recurrenceRules' => [[
				'@type' => 'RecurrenceRule',
				'frequency' => 'monthly',
			]],
		]);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Creating or modifying recurring events is NOT (yet) implemented!');
		JsCalendar::parseJsEvent($json);
	}
}
