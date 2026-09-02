<?php

/**
 * Characterization tests for infolog_bo::time2time()'s truthy-$fromTZId branch, written
 * BEFORE any refactor of time2time() (doc/ai/projects/infolog-storage-migration.md's Phase 2),
 * per ralf's direction: this branch is exercised by infolog_ical.inc.php's importVTODO()
 * (line ~576, `$this->time2time($taskData, $this->tzid, false)`) and infolog_bo::findInfo()
 * (`$this->time2time($infoData, $tzid, false)`) - both real, but with ZERO prior test
 * coverage, unlike the read()/write() paths (always $fromTZId=false/null) already covered by
 * DateHandlingTest.php.
 *
 * Deliberately calls time2time() directly (it's a plain public method) with synthetic
 * $values, rather than going through the full VTODO-import/Horde_Icalendar pipeline:
 * vtodotoegw()'s OWN date-parsing (Horde_Icalendar::_parseDateTime() falling back to PHP's
 * *current default timezone* when a VTODO value has neither "Z" nor a TZID param - which is
 * exactly why importVTODO() temporarily calls date_default_timezone_set($this->tzid) around
 * its vtodotoegw() call) is a SEPARATE, independently-confusing piece of legacy logic, not
 * itself part of what Phase 2 is refactoring. Characterizing time2time() as a pure
 * ($values, $fromTZId, $toTZId, $type) -> $values transformation, independent of how its
 * inputs get constructed upstream, is the correctly-scoped target for this phase.
 *
 * Every assertion below was verified by actually RUNNING this file against the current,
 * unmodified time2time() and recording the observed result - not derived from reading the
 * code alone - per this project's established "test empirically, correct assumptions"
 * discipline (see eg. the text-customfield case-sensitivity finding earlier in this project).
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;

class Time2TimeTzidTest extends \EGroupware\Api\AppTest
{
	protected static $server_tz;

	// setTimezones() also overwrites this egw-config value (separate from PHP's own default
	// timezone, which $server_tz above already tracks) - restore it too, or it leaks into
	// whatever test class runs next in the same PHPUnit process.
	protected static $server_config_tz;

	protected $bo;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		static::$server_tz = date_default_timezone_get();
		static::$server_config_tz = $GLOBALS['egw_info']['server']['server_timezone'] ?? null;
	}

	public static function tearDownAfterClass() : void
	{
		date_default_timezone_set(static::$server_tz);
		$GLOBALS['egw_info']['server']['server_timezone'] = static::$server_config_tz;

		parent::tearDownAfterClass();
	}

	protected function setUp() : void
	{
		$this->bo = new \infolog_bo();
	}

	protected function tearDown() : void
	{
		$this->bo = null;

		$GLOBALS['egw']->preferences->__construct($GLOBALS['egw_info']['user']['account_id']);
		$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository(false);
		Api\DateTime::init();
	}

	protected function setTimezones($server, $client)
	{
		$GLOBALS['egw_info']['server']['server_timezone'] = $server;
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $client;

		date_default_timezone_set($server);

		Api\DateTime::init();
	}

	/**
	 * A TIMED (non-midnight) value: converting server -> a real TZID -> server (the exact
	 * shape infolog_ical.inc.php's/findInfo()'s calls use, time2time($values, $tzid, false))
	 * round-trips back to the SAME raw value unchanged, for a non-DST-transition date - the
	 * "from" and "to" real-instant conversions cancel out, since both ultimately describe the
	 * same absolute instant.
	 *
	 * $fromTZId is only used for a REAL timezone conversion here (not a "relabel"); this test
	 * exists to document that fact plainly, since it's easy to assume $fromTZId's purpose is
	 * to reinterpret the raw digits as having been authored in that zone - it is not, for a
	 * timed value.
	 */
	public function testTimedValueRoundTripsUnchangedThroughFromTzidToServer()
	{
		$this->setTimezones('UTC', 'UTC');

		$raw = Api\DateTime::to('2026-01-15 14:00:00', 'ts');	// interpreted under server (UTC) default

		$values = array('info_startdate' => $raw);
		$this->bo->time2time($values, 'America/New_York', false);

		$this->assertSame($raw, $values['info_startdate'],
			'a timed value round-tripped server -> America/New_York -> server must come back unchanged (real round trip, same instant)');
	}

	/**
	 * All-day/midnight value, as measured FROM THE TZID's OWN perspective (not the server's):
	 * this is the actual case the whole truthy-$fromTZId mechanism exists for. A raw value
	 * that is midnight when displayed in America/New_York (but NOT midnight in server/UTC
	 * time) gets its CALENDAR DATE preserved as America/New_York would see it, re-expressed as
	 * that same calendar date at server-time midnight - NOT naively converted to whatever UTC
	 * instant the raw value truly represents (which would shift the date by 5 hours short of
	 * midnight, not a full calendar day, but demonstrating the "keep the calendar date, not
	 * the true instant" behavior is the point).
	 *
	 * Server = UTC, tzid = America/New_York (EST = UTC-5, 2026-01-15 is outside DST so the
	 * offset is fixed and unambiguous). NY midnight on 2026-01-15 = 2026-01-15 05:00:00 UTC.
	 */
	public function testMidnightInTzidPreservesThatCalendarDateAtServerMidnight()
	{
		$this->setTimezones('UTC', 'UTC');

		$raw = Api\DateTime::to('2026-01-15 05:00:00', 'ts');	// = midnight 2026-01-15 in America/New_York (EST, UTC-5)

		$values = array('info_startdate' => $raw);
		$this->bo->time2time($values, 'America/New_York', false);

		$expected = Api\DateTime::to('2026-01-15 00:00:00', 'ts');	// same calendar date, but at SERVER midnight now
		$this->assertSame($expected, $values['info_startdate'],
			'a value that is midnight in the TZID must come out as that SAME calendar date at server midnight, not the raw UTC instant');
	}

	/**
	 * Same all-day scenario, but converting server time -> a TZID that is AHEAD of server
	 * time (Europe/Berlin, UTC+1 in January, no DST) rather than behind it - confirms the
	 * calendar-date-preserving behavior isn't an artifact of the specific from/to offset
	 * direction picked above.
	 *
	 * Berlin midnight on 2026-01-15 = 2026-01-14 23:00:00 UTC.
	 */
	public function testMidnightInTzidAheadOfServerAlsoPreservesCalendarDate()
	{
		$this->setTimezones('UTC', 'UTC');

		$raw = Api\DateTime::to('2026-01-14 23:00:00', 'ts');	// = midnight 2026-01-15 in Europe/Berlin (UTC+1)

		$values = array('info_startdate' => $raw);
		$this->bo->time2time($values, 'Europe/Berlin', false);

		$expected = Api\DateTime::to('2026-01-15 00:00:00', 'ts');
		$this->assertSame($expected, $values['info_startdate'],
			'a value that is midnight in the (ahead-of-server) TZID must come out as that SAME calendar date at server midnight');
	}

	/**
	 * $fromTZId as a real TZID string that happens to legitimately resolve via
	 * calendar_timezones::DateTimeZone()'s alias table, not just a plain IANA name directly
	 * accepted by \DateTimeZone - confirms time2time() goes through that alias-resolving
	 * lookup (not a bare `new \DateTimeZone($fromTZId)`) for the truthy branch, since a naive
	 * refactor replacing that call could silently break legacy/Windows-style TZIDs a real
	 * CalDAV/ActiveSync client might send. 'GMT Standard Time' is a genuine Windows zone name
	 * calendar_timezones' alias table maps to 'Europe/London'.
	 */
	public function testFromTzidResolvesLegacyWindowsStyleAlias()
	{
		$this->setTimezones('UTC', 'UTC');

		// Europe/London is UTC+0 in January (no DST) - same offset as UTC, so a value that's
		// midnight in UTC is ALSO midnight in "GMT Standard Time" for this date, and the
		// all-day branch is a no-op here. Use a TIMED value instead, at an hour that would
		// behave identically whether or not alias resolution actually happened, EXCEPT that
		// resolution failure would throw (calendar_timezones::DateTimeZone() ultimately calls
		// `new DateTimeZone($tzid)`, which throws for a raw, non-resolved Windows zone name) -
		// so simply not throwing is itself the meaningful assertion.
		$raw = Api\DateTime::to('2026-01-15 14:00:00', 'ts');
		$values = array('info_startdate' => $raw);

		$this->bo->time2time($values, 'GMT Standard Time', false);

		$this->assertSame($raw, $values['info_startdate'],
			'"GMT Standard Time" must resolve via the alias table to Europe/London (UTC+0 in January) without throwing, round-tripping unchanged like any other same-offset zone');
	}

	/**
	 * A falsy raw value (0/unset) must be left alone entirely, not converted into some
	 * spurious "epoch" date - matches the same guard the null/false-branch tests in
	 * DateHandlingTest.php already rely on implicitly.
	 */
	public function testFalsyValueIsLeftUntouched()
	{
		$this->setTimezones('UTC', 'UTC');

		$values = array('info_startdate' => 0, 'info_enddate' => null);
		$this->bo->time2time($values, 'America/New_York', false);

		$this->assertSame(0, $values['info_startdate']);
		$this->assertNull($values['info_enddate']);
	}
}
