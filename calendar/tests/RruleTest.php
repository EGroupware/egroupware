<?php
/**
 * Unit tests for calendar_rrule - the recurrence expansion/generation/parsing engine
 *
 * calendar_rrule (calendar/inc/class.calendar_rrule.inc.php) is EGroupware's whole recurrence
 * engine: it is used identically for the UI description string, DB-backed occurrence expansion,
 * iCal import/export (calendar_ical), and the JMAP/JSCalendar REST read path (Api\CalDAV\JsCalendar).
 * It implements a deliberately reduced, non-standard subset of RFC 5545 RRULE (see
 * doc/ai/projects/calendar-rrule-standards-gap.md for the full map).
 *
 * This test class pins down calendar_rrule's *current* behaviour with concrete, verified
 * expectations - including known-lossy/limited behaviour - so a future RFC-5545-conformant
 * rewrite has an exact contract to diff itself against, rather than "gap vs. real RRULE" being
 * guesswork. Tests that document a known limitation say so explicitly in their docblock; they are
 * NOT bugs to fix here, and are deliberately not skipped/xfailed, so an accidental future
 * behaviour change shows up as a red test instead of passing silently either way.
 *
 * All expected values below were derived by actually running calendar_rrule (not hand-computed),
 * inside the same Docker container used for real test runs, before being written into assertions.
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

class RruleTest extends Api\AppTest
{
	const TZ = 'UTC';

	protected static $orig_weekdaystarts;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		self::$orig_weekdaystarts = $GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'] ?? null;
	}

	public static function tearDownAfterClass() : void
	{
		$GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'] = self::$orig_weekdaystarts;
		parent::tearDownAfterClass();
	}

	protected function tearDown() : void
	{
		$GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'] = self::$orig_weekdaystarts;
		parent::tearDown();
	}

	/**
	 * Iterate a calendar_rrule and return the occurrences as "Y-m-d H:i:s" strings.
	 */
	protected function occurrences(\calendar_rrule $rrule, int $limit=50) : array
	{
		$dates = [];
		$n = 0;
		foreach ($rrule as $date)
		{
			$dates[] = $date->format('Y-m-d H:i:s');
			if (++$n >= $limit) break;	// safety net, none of our test enddates need this many
		}
		return $dates;
	}

	protected function dt(string $time) : Api\DateTime
	{
		return new Api\DateTime($time, new \DateTimeZone(self::TZ));
	}

	/**
	 * Plain DAILY recurrence: one occurrence per day until (exclusive) enddate.
	 * Pass: exactly 2024-01-01 .. 2024-01-05 at 10:00, enddate day itself excluded.
	 */
	public function testDaily() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-01 10:00:00'), \calendar_rrule::DAILY, 1,
			$this->dt('2024-01-06 00:00:00'));

		self::assertSame([
			'2024-01-01 10:00:00', '2024-01-02 10:00:00', '2024-01-03 10:00:00',
			'2024-01-04 10:00:00', '2024-01-05 10:00:00',
		], $this->occurrences($rrule));
	}

	/**
	 * DAILY recurrence with an EXDATE (exception) removed from the expansion.
	 * Pass: same as testDaily() but 2024-01-03 is skipped.
	 */
	public function testDailyWithException() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-01 10:00:00'), \calendar_rrule::DAILY, 1,
			$this->dt('2024-01-06 00:00:00'), 0, [$this->dt('2024-01-03 10:00:00')]);

		self::assertSame([
			'2024-01-01 10:00:00', '2024-01-02 10:00:00',
			'2024-01-04 10:00:00', '2024-01-05 10:00:00',
		], $this->occurrences($rrule));
	}

	/**
	 * WEEKLY, interval=1, two weekdays (Mon+Wed) starting on a Monday.
	 * Pass: alternating Mon/Wed every single week.
	 */
	public function testWeeklyIntervalOne() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-01 09:00:00'), \calendar_rrule::WEEKLY, 1,
			$this->dt('2024-01-22 00:00:00'), \calendar_rrule::MONDAY | \calendar_rrule::WEDNESDAY);

		self::assertSame([
			'2024-01-01 09:00:00', '2024-01-03 09:00:00', '2024-01-08 09:00:00',
			'2024-01-10 09:00:00', '2024-01-15 09:00:00', '2024-01-17 09:00:00',
		], $this->occurrences($rrule));
	}

	/**
	 * WEEKLY, interval=2, two weekdays (Mon+Wed): every OTHER week's Mon+Wed.
	 * Pass: weeks of (Jan1,3), (Jan15,17), (Jan29,31) - i.e. every 2nd week is skipped entirely.
	 */
	public function testWeeklyIntervalTwo() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-01 09:00:00'), \calendar_rrule::WEEKLY, 2,
			$this->dt('2024-02-05 00:00:00'), \calendar_rrule::MONDAY | \calendar_rrule::WEDNESDAY);

		self::assertSame([
			'2024-01-01 09:00:00', '2024-01-03 09:00:00', '2024-01-15 09:00:00',
			'2024-01-17 09:00:00', '2024-01-29 09:00:00', '2024-01-31 09:00:00',
		], $this->occurrences($rrule));
	}

	/**
	 * KNOWN GAP: WKST (week-start day) is not stored per-event at all - calendar_rrule's
	 * constructor reads it live from the *current viewing user's* 'weekdaystarts' preference
	 * (calendar/inc/class.calendar_rrule.inc.php ~line 264), whereas RFC 5545 defines WKST as a
	 * fixed part of the rule itself.
	 *
	 * Empirically (this test), the 3 supported preference values produce the SAME occurrence
	 * dates for a WEEKLY interval>1 case: the interval-jump logic crosses exactly one
	 * "last day of week" pivot per step regardless of which weekday that pivot is, so the choice
	 * happens not to change elapsed time here. This is a property of the current single-pivot
	 * scan implementation, not a guarantee - it is NOT the same thing as correct WKST support
	 * (e.g. BYWEEKNO, unsupported anyway, would need real WKST semantics). Documented here so a
	 * future rewrite's behaviour can be compared against today's for this exact case.
	 */
	public function testWeeklyIntervalTwoInvariantAcrossWeekdaystartsPreference() : void
	{
		$expected = [
			'2024-01-01 09:00:00', '2024-01-03 09:00:00', '2024-01-15 09:00:00',
			'2024-01-17 09:00:00', '2024-01-29 09:00:00', '2024-01-31 09:00:00',
		];
		foreach (['Monday', 'Sunday', 'Saturday'] as $weekdaystarts)
		{
			$GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'] = $weekdaystarts;
			$rrule = new \calendar_rrule($this->dt('2024-01-01 09:00:00'), \calendar_rrule::WEEKLY, 2,
				$this->dt('2024-02-05 00:00:00'), \calendar_rrule::MONDAY | \calendar_rrule::WEDNESDAY);

			self::assertSame($expected, $this->occurrences($rrule),
				"weekdaystarts=$weekdaystarts changed the occurrence set");
		}
	}

	/**
	 * MONTHLY_MDAY: the day-of-month is only ever implicitly derived from the start date, never
	 * stored explicitly (KNOWN GAP: only a single BYMONTHDAY is representable). Starting on the
	 * 31st (month-end) must be recognised as "-1" (last day of month), not literally "31".
	 * Pass: monthly_bymonthday becomes -1, and the series lands on the LAST day of every month.
	 */
	public function testMonthlyMdayLastDayOfMonth() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-31 08:00:00'), \calendar_rrule::MONTHLY_MDAY, 1,
			$this->dt('2024-06-01 00:00:00'));

		self::assertSame(-1, $rrule->monthly_bymonthday);
		self::assertSame([
			'2024-01-31 08:00:00', '2024-02-29 08:00:00', '2024-03-31 08:00:00',
			'2024-04-30 08:00:00', '2024-05-31 08:00:00',
		], $this->occurrences($rrule));
	}

	/**
	 * MONTHLY_WDAY starting on the LAST Friday of a month must be recognised as "-1FR", not "4FR".
	 * Pass: monthly_byday_num becomes -1, and the series lands on the last Friday of every month.
	 */
	public function testMonthlyWdayLastWeekdayOfMonth() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-26 08:00:00'), \calendar_rrule::MONTHLY_WDAY, 1,
			$this->dt('2024-06-01 00:00:00'));

		self::assertSame(-1, $rrule->monthly_byday_num);
		self::assertSame([
			'2024-01-26 08:00:00', '2024-02-23 08:00:00', '2024-03-29 08:00:00',
			'2024-04-26 08:00:00', '2024-05-31 08:00:00',
		], $this->occurrences($rrule));
	}

	/**
	 * MONTHLY_WDAY starting on the 2nd Tuesday of a month: ordinary (non-last) ordinal case.
	 * Pass: monthly_byday_num becomes the int 2, series lands on the 2nd Tuesday of every month.
	 *
	 * Regression test for a type quirk: the non-last branch used to compute this via
	 * `1 + floor(...)` uncast, yielding a float (2.0) despite the property's docblock declaring
	 * `int` (unlike the `-1` last-week branch, which always assigned a literal int). Fixed to
	 * cast to int.
	 */
	public function testMonthlyWdaySecondWeekdayOfMonth() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-09 08:00:00'), \calendar_rrule::MONTHLY_WDAY, 1,
			$this->dt('2024-06-01 00:00:00'));

		self::assertSame(2, $rrule->monthly_byday_num);
		self::assertSame([
			'2024-01-09 08:00:00', '2024-02-13 08:00:00', '2024-03-12 08:00:00',
			'2024-04-09 08:00:00', '2024-05-14 08:00:00',
		], $this->occurrences($rrule));
	}

	/**
	 * YEARLY starting on a leap day (Feb 29). KNOWN QUIRK: PHP DateTime's '+1 year' modify rolls
	 * a Feb-29 start onto Mar-1 in non-leap years (there is no BYMONTHDAY/BYMONTH fallback logic
	 * in calendar_rrule to pin it back to Feb-28 or re-align to Feb-29 in the next leap year).
	 * Pass: 2024-02-29, then 2025/26/27/28 all fall on Mar-1 (2028 is the next leap year but the
	 * series has already drifted to Mar-1 by then, since nothing re-aligns it).
	 */
	public function testYearlyLeapDayStart() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-02-29 08:00:00'), \calendar_rrule::YEARLY, 1,
			$this->dt('2029-01-01 00:00:00'));

		self::assertSame([
			'2024-02-29 08:00:00', '2025-03-01 08:00:00', '2026-03-01 08:00:00',
			'2027-03-01 08:00:00', '2028-03-01 08:00:00',
		], $this->occurrences($rrule));
	}

	/**
	 * HOURLY, interval=3 (only reachable via $support_below_daily=true elsewhere, but the
	 * iterator itself has no such guard).
	 * Pass: every 3rd hour from midnight.
	 */
	public function testHourly() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-01 00:00:00'), \calendar_rrule::HOURLY, 3,
			$this->dt('2024-01-01 13:00:00'));

		self::assertSame([
			'2024-01-01 00:00:00', '2024-01-01 03:00:00', '2024-01-01 06:00:00',
			'2024-01-01 09:00:00', '2024-01-01 12:00:00',
		], $this->occurrences($rrule));
	}

	/**
	 * MINUTELY, interval=90.
	 * Pass: every 90 minutes from midnight.
	 */
	public function testMinutely() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-01 00:00:00'), \calendar_rrule::MINUTELY, 90,
			$this->dt('2024-01-01 06:00:00'));

		self::assertSame([
			'2024-01-01 00:00:00', '2024-01-01 01:30:00',
			'2024-01-01 03:00:00', '2024-01-01 04:30:00',
		], $this->occurrences($rrule));
	}

	/**
	 * RDATE (explicit date list, KNOWN GAP: mutually exclusive with any RRULE-shaped type -
	 * a series is either a rule or an explicit list, never both).
	 * Pass: the exact 3 given dates are the only occurrences, in order.
	 */
	public function testRdateExplicitList() : void
	{
		$start = $this->dt('2024-01-01 10:00:00');
		$rdates = [$start, $this->dt('2024-01-15 10:00:00'), $this->dt('2024-02-01 10:00:00')];

		$rrule = new \calendar_rrule($start, \calendar_rrule::RDATE, 1, null, 0, null, $rdates);

		self::assertSame([
			'2024-01-01 10:00:00', '2024-01-15 10:00:00', '2024-02-01 10:00:00',
		], $this->occurrences($rrule));
	}

	/**
	 * count2date(): translates an RRULE COUNT into the Nth occurrence's start date.
	 * calendar_ical uses exactly this to convert an imported COUNT= into a concrete recur_enddate
	 * (see class.calendar_ical.inc.php ~3385-3400) - this is that conversion in isolation.
	 * Pass: the 10th occurrence of a Mon/Wed/Fri weekly series starting Monday is the 4th Monday.
	 */
	public function testCountToDate() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-01 09:00:00'), \calendar_rrule::WEEKLY, 1, null,
			\calendar_rrule::MONDAY | \calendar_rrule::WEDNESDAY | \calendar_rrule::FRIDAY);

		self::assertSame('2024-01-22 09:00:00', $rrule->count2date(10)->format('Y-m-d H:i:s'));
	}

	/**
	 * generate_rrule('2.0'): RRULE component generation for the iCal-2.0/RFC-5545 style.
	 * Pass: exact FREQ/BYDAY/BYMONTHDAY/UNTIL components for one representative case per type
	 * that carries a by-x component (WEEKLY, MONTHLY_MDAY, MONTHLY_WDAY).
	 */
	public function testGenerateRruleWeekly() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-01 09:00:00'), \calendar_rrule::WEEKLY, 1,
			$this->dt('2024-02-01 00:00:00'), \calendar_rrule::MONDAY | \calendar_rrule::WEDNESDAY);

		$generated = $rrule->generate_rrule('2.0');

		self::assertSame('WEEKLY', $generated['FREQ']);
		self::assertSame('MO,WE', $generated['BYDAY']);
		self::assertSame('2024-01-31 09:00:00', $generated['UNTIL']->format('Y-m-d H:i:s'));
	}

	public function testGenerateRruleMonthlyMday() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-31 08:00:00'), \calendar_rrule::MONTHLY_MDAY, 1,
			$this->dt('2024-06-01 00:00:00'));

		$generated = $rrule->generate_rrule('2.0');

		self::assertSame('MONTHLY', $generated['FREQ']);
		self::assertSame(-1, $generated['BYMONTHDAY']);
		self::assertSame('2024-05-31 08:00:00', $generated['UNTIL']->format('Y-m-d H:i:s'));
	}

	public function testGenerateRruleMonthlyWday() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-01-26 08:00:00'), \calendar_rrule::MONTHLY_WDAY, 1,
			$this->dt('2024-06-01 00:00:00'));

		$generated = $rrule->generate_rrule('2.0');

		self::assertSame('MONTHLY', $generated['FREQ']);
		self::assertSame('-1FR', $generated['BYDAY']);
		self::assertSame('2024-05-31 08:00:00', $generated['UNTIL']->format('Y-m-d H:i:s'));
	}

	/**
	 * KNOWN GAP: YEARLY has no independent BYMONTH - generate_rrule() never emits BYMONTH at all,
	 * it's only ever implicit in DTSTART's own month.
	 * Pass: FREQ=YEARLY with only an UNTIL, no BYMONTH/BYDAY.
	 */
	public function testGenerateRruleYearlyHasNoByMonth() : void
	{
		$rrule = new \calendar_rrule($this->dt('2024-03-15 08:00:00'), \calendar_rrule::YEARLY, 1,
			$this->dt('2027-01-01 00:00:00'));

		$generated = $rrule->generate_rrule('2.0');

		self::assertSame('YEARLY', $generated['FREQ']);
		self::assertArrayNotHasKey('BYMONTH', $generated);
		self::assertArrayNotHasKey('BYDAY', $generated);
		self::assertSame('2026-03-15 08:00:00', $generated['UNTIL']->format('Y-m-d H:i:s'));
	}

	/**
	 * parseRrule() battery: real-world-shaped RRULE strings, asserting the CURRENT (sometimes
	 * lossy) parse result. See inline notes per case for which known gap each one documents.
	 *
	 * @return array<string, array{0:string,1:array}>
	 */
	public static function rruleStringProvider() : array
	{
		return [
			'plain weekly multi-day' => [
				'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE,FR',
				['recur_type' => \calendar_rrule::WEEKLY, 'recur_interval' => 1,
					'recur_data' => \calendar_rrule::MONDAY | \calendar_rrule::WEDNESDAY | \calendar_rrule::FRIDAY],
			],
			'daily with count' => [
				'FREQ=DAILY;INTERVAL=2;COUNT=10',
				['recur_type' => \calendar_rrule::DAILY, 'recur_interval' => 2, 'recur_count' => 10],
			],
			'monthly by-monthday with until' => [
				'FREQ=MONTHLY;UNTIL=20241231T000000Z;BYMONTHDAY=15',
				['recur_type' => \calendar_rrule::MONTHLY_MDAY, 'recur_interval' => 1],
			],
			// KNOWN GAP: BYMONTHDAY's actual value (15) is discarded here - parseRrule() only
			// uses the presence of BYMONTHDAY to pick MONTHLY_MDAY; the day-of-month is filled
			// in later, elsewhere, from DTSTART - never from this parsed value.
			'monthly by-monthday multi-value collapses' => [
				'FREQ=MONTHLY;BYMONTHDAY=1,15',
				['recur_type' => \calendar_rrule::MONTHLY_MDAY, 'recur_interval' => 1, 'recur_data' => 0],
			],
			// KNOWN GAP: BYDAY's value (2TU) is discarded the same way for MONTHLY_WDAY - only
			// used to pick the type, never to set recur_data here (that also comes from DTSTART
			// elsewhere). recur_count IS preserved (COUNT->UNTIL conversion happens later, in
			// calendar_ical, not in parseRrule() itself).
			'monthly by-weekday with count' => [
				'FREQ=MONTHLY;COUNT=5;BYDAY=2TU',
				['recur_type' => \calendar_rrule::MONTHLY_WDAY, 'recur_interval' => 1, 'recur_count' => 5,
					'recur_data' => 0],
			],
			// KNOWN GAP (the YEARLY+BYDAY hack): re-interpreted as MONTHLY with interval x12, and
			// BYMONTH is silently dropped entirely - never used anywhere in calendar_rrule.
			'yearly by-weekday+bymonth becomes monthly x12' => [
				'FREQ=YEARLY;BYDAY=2TU;BYMONTH=3',
				['recur_type' => \calendar_rrule::MONTHLY_WDAY, 'recur_interval' => 12, 'recur_data' => 0],
			],
			// KNOWN GAP: BYSETPOS is not recognised at all - BYDAY is parsed as an unrestricted
			// weekly set (both Mon AND Wed every week), losing the "1st matching day only" intent.
			'bysetpos is silently ignored' => [
				'FREQ=WEEKLY;BYDAY=MO,WE;BYSETPOS=1',
				['recur_type' => \calendar_rrule::WEEKLY, 'recur_interval' => 1,
					'recur_data' => \calendar_rrule::MONDAY | \calendar_rrule::WEDNESDAY],
			],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('rruleStringProvider')]
	public function testParseRrule(string $rrule_string, array $expected_subset) : void
	{
		$parsed = \calendar_rrule::parseRrule($rrule_string, false,
			['start' => $this->dt('2024-01-01 09:00:00')]);

		foreach ($expected_subset as $key => $value)
		{
			self::assertSame($value, $parsed[$key] ?? null, "key '$key' for RRULE '$rrule_string'");
		}
	}
}
