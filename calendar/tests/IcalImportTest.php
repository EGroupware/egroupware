<?php

/**
 * Tests importing recurring iCal fixtures internally (without CalDAV HTTP)
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use Egroupware\Api;

class IcalImportTest extends \EGroupware\Api\AppTest
{
	const EVENT_UID = 'calendar-77761-recurring-anonymized';
	const EVENT_FIXTURE = __DIR__.'/fixtures/recurring-import-anonymized.ics';
	const EXCHANGE_TZID_FIXTURE = __DIR__.'/fixtures/exchange-2010-invalid-tzid.ics';

	/**
	 * @var \calendar_ical
	 */
	protected $ical_bo;

	/**
	 * @var int[]
	 */
	protected $event_ids = [];

	protected function setUp() : void
	{
		parent::setUp();
		$this->ical_bo = new \calendar_ical();
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->event_ids) as $event_id)
		{
			$this->ical_bo->delete($event_id, 0, true);
			// Delete again to remove from delete history
			$this->ical_bo->delete($event_id, 0, true);
		}
		parent::tearDown();
	}

	/**
	 * Import recurring ICS fixture and validate recurrence semantics survive round-trip.
	 *
	 * Setup:
	 * - Load anonymized fixture from disk and import through calendar_ical::importVCal().
	 *
	 * Pass criteria:
	 * - Import returns a valid event id and event can be read.
	 * - Event stays recurring with expected UID/title and recurrence end date.
	 * - At least one recurrence is materialized (environment-dependent horizon safe).
	 * - Exported ICS still contains weekly Thursday RRULE details.
	 */
	public function testImportRecurringIcalFixture()
	{
		$ical = file_get_contents(self::EVENT_FIXTURE);
		$this->assertNotSame(false, $ical, 'Unable to load recurring iCal fixture');

		$cal_id = $this->ical_bo->importVCal($ical, -1, null, false, 0, '', $GLOBALS['egw_info']['user']['account_id']);
		$this->assertNotFalse($cal_id, 'Import failed');
		$this->assertTrue(is_int($cal_id) || ctype_digit((string)$cal_id), 'Import did not return a numeric cal_id');

		$cal_id = (int)$cal_id;
		$this->assertGreaterThan(0, $cal_id, 'Import did not return a valid cal_id');
		$this->event_ids[] = $cal_id;

		$event = $this->ical_bo->read($cal_id);
		$this->assertIsArray($event, 'Imported event can not be read back');
		$this->assertEquals(self::EVENT_UID, $event['uid']);
		$this->assertEquals('Developer Meeting', $event['title']);
		$this->assertNotEquals(MCAL_RECUR_NONE, $event['recur_type'], 'Imported event is not recurring');

		$recur_enddate = $event['recur_enddate'] instanceof Api\DateTime ?
			clone $event['recur_enddate'] : new Api\DateTime($event['recur_enddate'], Api\DateTime::$server_timezone);
		$recur_enddate->setTimezone(new \DateTimeZone($event['tzid']));
		$this->assertEquals('20261217', $recur_enddate->format('Ymd'), 'Unexpected recurrence end date');

		$so = new \calendar_so();
		$recurrences = $so->get_recurrences($cal_id);
		unset($recurrences[0]);	// master event

		// Depending on environment/config only a horizon of recurrences may be materialized
		// in egw_cal_user. Assert recurrence import worked without requiring full expansion.
		$this->assertGreaterThan(0, count($recurrences), 'Expected at least one generated recurrence');
		foreach(array_keys($recurrences) as $recur_start)
		{
			$occurrence = new \DateTime('@'.$recur_start);
			$occurrence->setTimezone(new \DateTimeZone($event['tzid']));
			$this->assertEquals(4, (int)$occurrence->format('N'), 'Recurrence does not fall on Thursday');
		}

		$export = $this->ical_bo->exportVCal($cal_id, '2.0');
		$this->assertIsString($export);
		$this->assertStringContainsString('RRULE:', $export);
		$this->assertStringContainsString('FREQ=WEEKLY', $export);
		$this->assertStringContainsString('BYDAY=TH', $export);
	}

	/**
	 * Regression test for Exchange/Outlook Windows TZID values in DTSTART/DTEND.
	 *
	 * Setup:
	 * - Force user timezone to Europe/Athens and server timezone to UTC.
	 * - Import Exchange 2010 fixture with Windows TZID.
	 *
	 * Pass criteria:
	 * - Event TZID is mapped to a canonical IANA timezone.
	 * - Event wall-time in Europe/Athens reflects source timezone offset (+1 hour here).
	 */
	public function testImportWindowsTzidFromExchangeFixture()
	{
		$server_tz = $GLOBALS['egw_info']['server']['server_timezone'] ?? 'UTC';
		$user_tz = $GLOBALS['egw_info']['user']['preferences']['common']['tz'] ?? 'UTC';

		$GLOBALS['egw_info']['server']['server_timezone'] = 'UTC';
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = 'Europe/Athens';
		date_default_timezone_set('UTC');
		Api\DateTime::init();

		$ical = file_get_contents(self::EXCHANGE_TZID_FIXTURE);
		$this->assertNotSame(false, $ical, 'Unable to load Exchange TZID fixture');

		try
		{
			$cal_id = $this->ical_bo->importVCal($ical, -1, null, false, 0, '', $GLOBALS['egw_info']['user']['account_id']);
			$this->assertNotFalse($cal_id, 'Import failed for Exchange TZID fixture');
			$this->event_ids[] = (int)$cal_id;

			$loaded = $this->ical_bo->read([(int)$cal_id], null, false, 'server');
			$event = $loaded[(int)$cal_id];

			$this->assertEquals('Europe/Berlin', $event['tzid'], 'Imported TZID mapping mismatch');
			$this->assertEquals(
				'18:00:00',
				Api\DateTime::to(Api\DateTime::server2user($event['start']), 'H:i:s'),
				'Unexpected start time in Europe/Athens'
			);
			$this->assertEquals(
				'19:00:00',
				Api\DateTime::to(Api\DateTime::server2user($event['end']), 'H:i:s'),
				'Unexpected end time in Europe/Athens'
			);
		}
		finally
		{
			$GLOBALS['egw_info']['server']['server_timezone'] = $server_tz;
			$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $user_tz;
			date_default_timezone_set($server_tz);
			Api\DateTime::init();
		}
	}
}
