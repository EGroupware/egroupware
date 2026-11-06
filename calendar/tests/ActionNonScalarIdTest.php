<?php
/**
 * EGroupware calendar: regression test for calendar_uilist::action() input handling
 *
 * @link https://www.egroupware.org
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

/**
 * A spy that records every id action() hands to read(), without changing its behaviour.
 */
class RecordingCalendarBo extends \calendar_boupdate
{
	public array $read_ids = [];

	function read($ids, Api\DateTime|int|null $date = null, $ignore_acl = false, $date_format = 'ts',
		$clear_private_infos_users = null, $read_recurrence = false)
	{
		$this->read_ids[] = $ids;
		return parent::read($ids, $date, $ignore_acl, $date_format, $clear_private_infos_users, $read_recurrence);
	}
}

/**
 * action($action, $checked, ...) is documented to take a list of plain event ids in $checked. This
 * pins that each element is actually treated as such, and that an unexpected (non-scalar, and
 * without a usable 'id' sub-key) element is simply ignored instead of being passed on to bo->read().
 */
class ActionNonScalarIdTest extends LoggedInTest
{
	protected function setUp() : void
	{
		parent::setUp();
		Api\Json\Response::get()->initResponseArray();
	}

	/**
	 * @return RecordingCalendarBo[0] the spy, [1] the ui instance with that spy installed as ->bo
	 */
	protected function makeSpiedUi() : array
	{
		$ui = new \calendar_uilist();
		$spy = new RecordingCalendarBo();
		$ui->bo = $spy;
		return [$spy, $ui];
	}

	protected function assertNoNonScalarReadIds(RecordingCalendarBo $spy) : void
	{
		foreach($spy->read_ids as $ids)
		{
			$this->assertTrue($ids === null || is_scalar($ids),
				'action() must never hand bo->read() a non-scalar id - got: '.print_r($ids, true));
		}
	}

	/**
	 * A $checked element that is itself an array naming a real column (rather than a plain id, or
	 * an array with an 'id' sub-key) must never be processed as an id further down the call chain.
	 */
	public function testArrayElementWithColumnKeyIsIgnored()
	{
		[$spy, $ui] = $this->makeSpiedUi();

		$success = $failed = 0;
		$action_msg = $msg = null;
		$ui->action('status-T', [['cal_owner' => 1]], false, $success, $failed, $action_msg, 'calendar_list', $msg);

		$this->assertNoNonScalarReadIds($spy);
		$this->assertSame(1, $failed, 'the malformed entry must be rejected, not silently skipped');
	}

	/**
	 * Same as above, for an array with a nested (non-scalar) 'id' sub-key.
	 */
	public function testArrayElementWithNonScalarIdSubkeyIsIgnored()
	{
		[$spy, $ui] = $this->makeSpiedUi();

		$success = $failed = 0;
		$action_msg = $msg = null;
		$ui->action('status-T', [['id' => ['cal_owner' => 1]]], false, $success, $failed, $action_msg, 'calendar_list', $msg);

		$this->assertNoNonScalarReadIds($spy);
		$this->assertSame(1, $failed, 'the malformed entry must be rejected, not silently skipped');
	}

	/**
	 * Legitimate scalar ids must still work normally - the fix must not break real usage.
	 */
	public function testScalarIdStillWorks()
	{
		$me = $GLOBALS['egw_info']['user']['account_id'];
		$bo = new \calendar_boupdate();
		$event_id = $bo->save([
			'title'        => 'ActionNonScalarIdTest own '.bin2hex(random_bytes(4)),
			'start'        => new Api\DateTime(time() + 3600, Api\DateTime::$server_timezone),
			'end'          => new Api\DateTime(time() + 7200, Api\DateTime::$server_timezone),
			'owner'        => $me,
			'participants' => [$me => 'U'],
		]);
		$this->assertNotFalse($event_id, 'could not create own test event');

		try
		{
			[$spy, $ui] = $this->makeSpiedUi();

			$success = $failed = 0;
			$action_msg = $msg = null;
			$ui->action('status-T', [$event_id], false, $success, $failed, $action_msg, 'calendar_list', $msg);

			$this->assertNotEmpty($spy->read_ids, 'a plain scalar id must still reach bo->read()');
			$this->assertNoNonScalarReadIds($spy);
			$this->assertSame(1, $success, 'a plain scalar id must still be processed by action()');
		}
		finally
		{
			$bo->delete($event_id, 0, true);
		}
	}
}
