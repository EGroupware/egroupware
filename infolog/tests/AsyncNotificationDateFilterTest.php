<?php

/**
 * Test harness for infolog_bo::dateFilterSuffix(), extracted out of async_notification()
 * as part of phase 2 of doc/ai/projects/infolog-storage-migration.md ("clean up
 * infolog_bo's own manual date math" - migrating async_notification()'s raw
 * date()/time() filter-string construction to Api\DateTime).
 *
 * async_notification() itself (impersonating every user with open entries, sending real
 * notifications) is out of scope for direct testing here - dateFilterSuffix() was
 * extracted specifically so the one-line change (date('Y-m-d',time()+24*60*60*$n) ->
 * Api\DateTime-based calendar-day arithmetic) is unit-testable in isolation, and so its
 * output can be verified to still drive infolog_bo::search()'s "open-*-date"/"open-*-
 * enddate" filters (infolog_so::dateFilter()'s 'date'/'enddate' cases) exactly as before.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;

class AsyncNotificationDateFilterTest extends \EGroupware\Api\AppTest
{
	protected $bo;

	protected $info_ids = array();

	protected function setUp() : void
	{
		$this->bo = new \infolog_bo();
		$this->mockTracking($this->bo, 'infolog_tracking');
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->info_ids) as $info_id)
		{
			$this->bo->delete($info_id);
			$this->bo->delete($info_id);
		}
		$this->info_ids = array();
		$this->bo = null;
	}

	protected function makeInfolog(array $fields = array())
	{
		$info = array('info_type' => 'task', 'info_subject' => 'AsyncNotificationDateFilterTest '.$this->name());
		foreach($fields as $field => $value) { $info[$field] = $value; }
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	protected function callDateFilterSuffix($days_from_now)
	{
		$ref = new \ReflectionMethod($this->bo, 'dateFilterSuffix');
		$ref->setAccessible(true);
		return $ref->invoke($this->bo, $days_from_now);
	}

	protected function daysFromNow($days)
	{
		return Api\DateTime::to((new Api\DateTime('now'))->modify($days.' days'), 'ts');
	}

	/**
	 * dateFilterSuffix(N) must equal today's date (server time) plus N calendar days.
	 */
	public function testDateFilterSuffixMatchesTodayPlusDays()
	{
		$expected = (new Api\DateTime('now', Api\DateTime::$server_timezone))->modify('5 days')->format('Y-m-d');

		$this->assertSame($expected, $this->callDateFilterSuffix(5));
	}

	public function testDateFilterSuffixZeroDaysIsToday()
	{
		$expected = (new Api\DateTime('now', Api\DateTime::$server_timezone))->format('Y-m-d');

		$this->assertSame($expected, $this->callDateFilterSuffix(0));
	}

	/**
	 * End-to-end: a filter string built the same way async_notification() builds it
	 * ('open-responsible-enddate' + dateFilterSuffix($n)) must match an entry whose
	 * info_enddate falls exactly n days from now, and must NOT match an entry due on a
	 * different day - locking down that dateFilterSuffix()'s output still integrates
	 * correctly with infolog_so::dateFilter()'s existing 'enddate' case, unchanged by
	 * this phase.
	 */
	public function testEnddateNotificationFilterMatchesEntryDueInNDays()
	{
		$due_id = $this->makeInfolog(array('info_enddate' => $this->daysFromNow(3)));
		$other_id = $this->makeInfolog(array('info_enddate' => $this->daysFromNow(10)));

		$filter = 'open-responsible-enddate'.$this->callDateFilterSuffix(3);
		$query = array('filter' => $filter, 'order' => 'info_datemodified', 'sort' => 'DESC');
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($due_id, $ret,
			'an entry due exactly N days from now must match the "open-*-enddate" + dateFilterSuffix(N) filter');
		$this->assertArrayNotHasKey($other_id, $ret,
			'an entry due on a different day must NOT match');
	}

	/**
	 * Same as above, for the 'date' (info_startdate) case used by the
	 * notify_start_responsible/notify_start_delegated preferences.
	 */
	public function testStartdateNotificationFilterMatchesEntryStartingInNDays()
	{
		$starting_id = $this->makeInfolog(array('info_startdate' => $this->daysFromNow(2)));
		$other_id = $this->makeInfolog(array('info_startdate' => $this->daysFromNow(9)));

		$filter = 'open-responsible-date'.$this->callDateFilterSuffix(2);
		$query = array('filter' => $filter, 'order' => 'info_datemodified', 'sort' => 'DESC');
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($starting_id, $ret,
			'an entry starting exactly N days from now must match the "open-*-date" + dateFilterSuffix(N) filter');
		$this->assertArrayNotHasKey($other_id, $ret,
			'an entry starting on a different day must NOT match');
	}
}
