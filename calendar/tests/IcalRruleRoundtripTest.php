<?php
/**
 * Integration-level round-trip tests for the RRULE-specific import/export workarounds in
 * calendar_ical - i.e. the concrete places where an externally-authored iCal RRULE is silently
 * normalized, collapsed, or (in one case) allowed to clobber a different recurrence property
 * entirely, because calendar_rrule's storage model is a reduced, non-standard subset of RFC 5545.
 *
 * calendar/tests/RruleTest.php pins down calendar_rrule's own iteration/parsing logic in
 * isolation; this file pins down the same gaps at the full "real .ics text in, real .ics text
 * (or event array) out" integration level, via calendar_ical::icaltoegw()/exportVCal() - matching
 * the sibling round-trip tests in IcalDateRoundtripTest.php. See
 * doc/ai/projects/calendar-rrule-standards-gap.md for the full gap map.
 *
 * None of these tests need database writes: icaltoegw() is a pure parse, and exportVCal() accepts
 * a plain in-memory event array (id=-1), exactly as IcalDateRoundtripTest.php already established.
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

class IcalRruleRoundtripTest extends Api\AppTest
{
	/**
	 * A calendar_ical instance restricted to just the fields under test (same subset as
	 * IcalDateRoundtripTest::minimalIcal()), forced to export in UTC so the fixtures below
	 * (all UTC, no VTIMEZONE needed) stay simple.
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

	protected function wrap(string $uid, string $vevent_lines) : string
	{
		return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\n".
			"DTSTAMP:20240101T000000Z\r\nUID:$uid\r\n".
			$vevent_lines.
			"END:VEVENT\r\nEND:VCALENDAR\r\n";
	}

	/**
	 * KNOWN GAP: COUNT is parsed but immediately, irreversibly converted into a concrete UNTIL
	 * (calendar_ical.inc.php ~3385-3400, via calendar_rrule::count2date()) and discarded.
	 *
	 * Pass criteria:
	 * - Importing an external RRULE with COUNT=10 yields recur_enddate on the 10th occurrence's
	 *   day (2024-01-22, a Monday - same Mon/Wed/Fri series verified by hand in RruleTest), and no
	 *   recur_count left on the parsed event.
	 * - Re-exporting that event emits RRULE;...UNTIL=... and never COUNT=, confirming the loss is
	 *   one-directional (the original COUNT intent cannot be recovered from what we stored).
	 */
	public function testCountConvertedToUntilIrreversibly() : void
	{
		$uid = 'count-roundtrip-'.uniqid();
		$ical = $this->wrap($uid,
			"DTSTART:20240101T090000Z\r\nDTEND:20240101T100000Z\r\n".
			"RRULE:FREQ=WEEKLY;BYDAY=MO,WE,FR;COUNT=10\r\n"
		);

		$parsed = (new \calendar_ical())->icaltoegw($ical);
		$this->assertNotEmpty($parsed, 'Import failed');
		$imported = $parsed[0];

		$this->assertArrayNotHasKey('recur_count', $imported, 'recur_count should not survive import');
		$this->assertNotEmpty($imported['recur_enddate'], 'recur_enddate should be set from COUNT');
		$recur_enddate = $imported['recur_enddate'] instanceof Api\DateTime ?
			$imported['recur_enddate'] : new Api\DateTime($imported['recur_enddate'], Api\DateTime::$server_timezone);
		$this->assertEquals('2024-01-22', $recur_enddate->format('Y-m-d'), 'Unexpected COUNT->UNTIL conversion result');

		$export_event = ['id' => -1] + $imported;
		$exported = $this->minimalIcal()->exportVCal([$export_event], '2.0', 'PUBLISH');
		$this->assertIsString($exported, 'Re-export failed');
		$this->assertStringContainsString('UNTIL=', $exported, 'Expected UNTIL in re-exported RRULE');
		$this->assertStringNotContainsString('COUNT=', $exported, 'COUNT should never reappear after import');
	}

	/**
	 * KNOWN GAP: MONTHLY_WDAY/YEARLY store exactly one implicit BYDAY (weekday+ordinal), derived
	 * from DTSTART - never the BYDAY property's own value(s). A multi-value BYDAY collapses to
	 * whatever ordinal/weekday DTSTART itself falls on.
	 *
	 * Pass criteria:
	 * - Importing BYDAY=2TU,4TH with DTSTART on a 2nd Tuesday yields a MONTHLY_WDAY event whose
	 *   only representable day is "2nd Tuesday" (matching DTSTART); "4TH" is silently dropped.
	 * - Re-exporting emits BYDAY=2TU only - the 4TH occurrence series member is unrecoverable.
	 */
	public function testMultiByDayCollapsesToSingleDayFromDtstart() : void
	{
		$uid = 'multibyday-roundtrip-'.uniqid();
		// 2024-01-09 is the 2nd Tuesday of January 2024
		$ical = $this->wrap($uid,
			"DTSTART:20240109T080000Z\r\nDTEND:20240109T090000Z\r\n".
			"RRULE:FREQ=MONTHLY;BYDAY=2TU,4TH;UNTIL=20240601T000000Z\r\n"
		);

		$parsed = (new \calendar_ical())->icaltoegw($ical);
		$this->assertNotEmpty($parsed, 'Import failed');
		$imported = $parsed[0];

		$this->assertEquals(MCAL_RECUR_MONTHLY_WDAY, $imported['recur_type'], 'Expected MONTHLY_WDAY');

		$export_event = ['id' => -1] + $imported;
		$exported = $this->minimalIcal()->exportVCal([$export_event], '2.0', 'PUBLISH');
		$this->assertIsString($exported, 'Re-export failed');
		$this->assertMatchesRegularExpression('/BYDAY=2TU\b/', $exported, 'Expected only the DTSTART-derived 2TU');
		$this->assertStringNotContainsString('4TH', $exported, 'The 4TH member must not survive - confirms the collapse');
	}

	/**
	 * KNOWN GAP / concrete bug: a VEVENT with BOTH RRULE and RDATE (a common real-world shape -
	 * e.g. a weekly series plus one ad-hoc extra date) is not "RRULE wins, RDATE ignored" or vice
	 * versa consistently - calendar_ical.inc.php's per-property switch sets
	 * `$vcardData['recur_type'] = calendar_rrule::RDATE` unconditionally whenever an RDATE
	 * property is encountered (~line 2909), with NO check for an already-parsed RRULE. Since a
	 * VEVENT's properties are processed in the order they appear in the source text, the OUTCOME
	 * DEPENDS ON PROPERTY ORDER: if RDATE comes after RRULE in the file, it silently overwrites
	 * recur_type from the RRULE's real type (WEEKLY here) to RDATE - the entire weekly rule is
	 * lost, and only the one explicit extra date remains as the "recurrence". Both RRULEs here
	 * are deliberately open-ended (no UNTIL/COUNT) so this test isolates the recur_type clobbering
	 * alone; see testRruleWithUntilThenRdateCrashesOnImport() for what happens when UNTIL is
	 * combined with this same ordering.
	 *
	 * Pass criteria (documents the bug, not a desired behaviour):
	 * - RRULE-then-RDATE order: final recur_type is RDATE (9), NOT WEEKLY - the rule is lost.
	 * - RDATE-then-RRULE order: final recur_type IS WEEKLY - same input properties, opposite
	 *   outcome, purely from reordering two lines in the source .ics.
	 */
	public function testRruleAndRdateTogetherIsOrderDependent() : void
	{
		$uid_rrule_first = 'rrule-then-rdate-'.uniqid();
		$ical_rrule_first = $this->wrap($uid_rrule_first,
			"DTSTART:20240101T090000Z\r\nDTEND:20240101T100000Z\r\n".
			"RRULE:FREQ=WEEKLY;BYDAY=MO\r\n".
			"RDATE:20240213T090000Z\r\n"
		);
		$parsed_rrule_first = (new \calendar_ical())->icaltoegw($ical_rrule_first)[0];
		$this->assertEquals(\calendar_rrule::RDATE, $parsed_rrule_first['recur_type'],
			'RRULE-then-RDATE: expected the RDATE property to clobber recur_type (documents the bug)');

		$uid_rdate_first = 'rdate-then-rrule-'.uniqid();
		$ical_rdate_first = $this->wrap($uid_rdate_first,
			"DTSTART:20240101T090000Z\r\nDTEND:20240101T100000Z\r\n".
			"RDATE:20240213T090000Z\r\n".
			"RRULE:FREQ=WEEKLY;BYDAY=MO\r\n"
		);
		$parsed_rdate_first = (new \calendar_ical())->icaltoegw($ical_rdate_first)[0];
		$this->assertEquals(MCAL_RECUR_WEEKLY, $parsed_rdate_first['recur_type'],
			'RDATE-then-RRULE: same properties, opposite order, opposite recur_type - confirms order-dependence');
	}

	/**
	 * CONCRETE BUG (crash, not just data loss): combining the RRULE-then-RDATE clobbering above
	 * with an RRULE that has an UNTIL crashes the import outright, instead of silently losing data.
	 *
	 * Mechanism: after clobbering, `$event['recur_type']` is RDATE(9) but `$event['recur_enddate']`
	 * is still the RRULE's UNTIL - a date normally later than any of the (now sole) explicit
	 * RDATE(s). calendar_ical's post-parse normalization calls
	 * `calendar_rrule::event2rrule($event, false)->normalize_enddate()`
	 * (class.calendar_ical.inc.php ~3366-3367), which builds an RDATE-type calendar_rrule and
	 * calls `rewind()` then repeatedly `next_no_exception()` while
	 * `$this->current < $this->enddate`. Once the RDATE-type iterator runs out of dates,
	 * `next_no_exception()` sets `$this->current = null` (calendar_rrule.inc.php ~513) - but PHP's
	 * comparison rules treat `null < $enddate` (a DateTime) as true, so the loop runs one more
	 * time and calls `clone $this->current` on that null (calendar_rrule.inc.php ~582), which
	 * throws `TypeError: clone(): Argument #1 ($object) must be of type object, null given` under
	 * PHP 8's strict `clone` typing.
	 *
	 * This is filed here as a mapped, reproducible defect for the future rewrite to fix - not
	 * something this test suite patches over. It is NOT specific to the RRULE+RDATE clobbering:
	 * any RDATE-type event whose `recur_enddate` ends up later than its last RDATE (which
	 * shouldn't normally happen via the UI, but evidently can via this import path) hits the same
	 * crash.
	 *
	 * Pass criteria: reproduces the exact TypeError above; a future fix replacing this with a
	 * graceful result should update/remove this test intentionally, not by accident.
	 */
	public function testRruleWithUntilThenRdateCrashesOnImport() : void
	{
		$uid = 'rrule-until-then-rdate-crash-'.uniqid();
		$ical = $this->wrap($uid,
			"DTSTART:20240101T090000Z\r\nDTEND:20240101T100000Z\r\n".
			"RRULE:FREQ=WEEKLY;BYDAY=MO;UNTIL=20240301T000000Z\r\n".
			"RDATE:20240213T090000Z\r\n"
		);

		$this->expectException(\TypeError::class);
		$this->expectExceptionMessageMatches('/clone\(\).*null given/');
		(new \calendar_ical())->icaltoegw($ical);
	}
}
