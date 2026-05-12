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

class RecurringImportTest extends \EGroupware\Api\AppTest
{
	const EVENT_UID = 'calendar-77761-recurring-anonymized';
	const EVENT_FIXTURE = __DIR__.'/fixtures/recurring-import-anonymized.ics';

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

		$recur_enddate = new Api\DateTime($event['recur_enddate']);
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
}
