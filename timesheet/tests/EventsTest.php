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
}
