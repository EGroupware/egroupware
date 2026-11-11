<?php
/**
 * EGroupware timesheet: regression test for Events::ajax_event()'s ownership guard
 *
 * @link http://www.egroupware.org
 * @package timesheet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Storage\Base;
use EGroupware\Timesheet\Events;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Before the fix, Events::ajax_event() took $ts_id straight from client-controlled
 * $state['specific']['app_id'] with no READ/EDIT check on that timesheet before
 * persisting a new egw_timesheet_events row against it.
 */
class EventsTest extends \EGroupware\Api\AppTest
{
	/** @var int|null ts_id created by the current test, cleaned up in tearDown */
	private $ts_id;

	protected function setUp(): void
	{
		parent::setUp();
		// ajax_event() ends with Api\Json\Response::get()->data(...) - that singleton refuses a
		// second data response within the same process, so each test needs a fresh one
		\EGroupware\Api\Json\Response::get()->initResponseArray();
	}

	protected function tearDown(): void
	{
		if ($this->ts_id)
		{
			(new Base('timesheet', 'egw_timesheet_events'))->delete(array('ts_id' => $this->ts_id));
			(new Base('timesheet', 'egw_timesheet'))->delete(array('ts_id' => $this->ts_id));
			$this->ts_id = null;
		}
	}

	private function createTimesheet(int $owner): int
	{
		$so = new Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_events_'.bin2hex(random_bytes(6)),
			'ts_start'    => time(),
			'ts_duration' => 60,
			'ts_quantity' => 1.0,
			'ts_owner'    => $owner,
			'ts_created'  => time(),
			'ts_modified' => time(),
			'ts_modifier' => $owner,
		);
		$so->save();

		return (int)$so->data['ts_id'];
	}

	private function countEventsFor(int $ts_id): int
	{
		return count((new Base('timesheet', 'egw_timesheet_events'))->search(array('ts_id' => $ts_id), false) ?: array());
	}

	/**
	 * Pass criteria: ajax_event() must throw and must not persist an event row for a
	 * timesheet the caller has no EDIT access to.
	 */
	public function testRejectsEventForInaccessibleTimesheet()
	{
		// an account id the current test-user has no ACL grant for
		$this->ts_id = $this->createTimesheet(999999);

		$this->expectException(\EGroupware\Api\Exception\NoPermission::class);

		try
		{
			(new Events())->ajax_event(array(
				'action' => 'specific-start',
				'ts' => time(),
				'specific' => array('app_id' => 'timesheet::'.$this->ts_id),
			));
		}
		finally
		{
			$this->assertSame(0, $this->countEventsFor($this->ts_id),
				'no event row must be created for an inaccessible timesheet');
		}
	}

	/**
	 * Pass criteria: ajax_event() must still succeed for the caller's own timesheet
	 * (no regression).
	 */
	public function testAllowsEventForOwnTimesheet()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$this->ts_id = $this->createTimesheet($account_id);

		(new Events())->ajax_event(array(
			'action' => 'specific-start',
			'ts' => time(),
			'specific' => array('app_id' => 'timesheet::'.$this->ts_id),
		));

		$this->assertSame(1, $this->countEventsFor($this->ts_id),
			'an event row must be created for the caller\'s own timesheet');
	}

	/**
	 * getPending()'s filter array builds a raw "NOT (tse_type & ...)"/"(tse_type & ...)" condition
	 * at an integer key, unwrapped in Api\Db\SqlFragment::raw() - column_data_implode()'s
	 * requireSqlFragmentObjects() enforcement rejects any such unwrapped value, so stopping the
	 * overall timer - which calls storeWorkingTime(), which calls getPending(true) to collect the
	 * completed start/stop sequence to store as a timesheet entry - threw instead (reported live:
	 * "Fehler beim Speichern der Arbeitszeit").
	 *
	 * getPending() only returns a sequence once it ends in a stop event (an in-progress, not yet
	 * stopped sequence is deliberately excluded - see get()'s own "remove open / unstopped timer
	 * events" step), so this creates a start+stop pair directly rather than going through
	 * ajax_event() (which would swallow the exception into a JSON error message instead of
	 * throwing - exactly how this surfaced live, as an error, not a hard crash).
	 */
	public function testGetPendingDoesNotThrow()
	{
		// getPending() filters by Events::getInstance()->user, a lazily-created singleton that can
		// already exist (and carry a DIFFERENT user) from an earlier test in the same CI process -
		// use its actual resolved user, not $GLOBALS['egw_info']['user']['account_id'] directly, or
		// the fixture rows below silently filter out of its own query in a full-suite run
		$getInstance = new \ReflectionMethod(Events::class, 'getInstance');
		$getInstance->setAccessible(true);
		$userProp = new \ReflectionProperty(Events::class, 'user');
		$userProp->setAccessible(true);
		$account_id = $userProp->getValue($getInstance->invoke(null));

		$common = array(
			'account_id' => $account_id,
			'tse_modifier' => $account_id,
			'tse_app' => null,
			'tse_app_id' => null,
			'ts_id' => null,
		);

		$start = new \EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet_events');
		$start->data = $common + array(
			'tse_timestamp' => time(), 'tse_time' => time() - 60, 'tse_type' => Events::OVERALL | Events::START,
		);
		$start->save();

		$stop = new \EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet_events');
		$stop->data = $common + array(
			'tse_timestamp' => time(), 'tse_time' => time(), 'tse_type' => Events::OVERALL | Events::STOP,
		);
		$stop->save();
		$tse_ids = array($start->data['tse_id'], $stop->data['tse_id']);

		try
		{
			$pending = Events::getPending(true, $time, $paused);

			$this->assertIsArray($pending, 'getPending() must return an array, not throw');
			$this->assertNotEmpty($pending, 'the completed start/stop sequence must be returned');
			$this->assertGreaterThan(0, $time, 'elapsed time of the sequence must be computed');
		}
		finally
		{
			$start->delete(array('tse_id' => $tse_ids));
		}
	}
}
