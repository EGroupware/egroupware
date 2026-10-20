<?php
/**
 * EGroupware calendar: regression test for calendar_ui::ajax_get() input handling
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
 * ajax_get($event_ids) is documented to take a list of plain event ids. This pins that each
 * element is actually treated as such, and that an unexpected (non-scalar) element is simply
 * ignored instead of being passed further down the call chain.
 */
class AjaxGetNonScalarIdTest extends LoggedInTest
{
	/** @var \calendar_boupdate */
	protected $bo;
	protected $other_account_id;
	protected $other_event_id;

	protected function setUp() : void
	{
		parent::setUp();
		Api\Json\Response::get()->initResponseArray();
		$this->bo = new \calendar_boupdate();
		$this->other_account_id = $this->makeOtherUser();

		$this->other_event_id = $this->bo->save([
			'title'        => 'AjaxGetNonScalarIdTest other '.bin2hex(random_bytes(4)),
			'start'        => new Api\DateTime(time() + 3600, Api\DateTime::$server_timezone),
			'end'          => new Api\DateTime(time() + 7200, Api\DateTime::$server_timezone),
			'owner'        => $this->other_account_id,
			'public'       => 0,
			'participants' => [$this->other_account_id => 'A'],
		], true);
		$this->assertNotFalse($this->other_event_id, 'could not create the other test event');
	}

	protected function tearDown() : void
	{
		if ($this->other_event_id)
		{
			$this->bo->delete($this->other_event_id, 0, true);
		}
		if ($this->other_account_id)
		{
			$id = $this->other_account_id;
			$this->asAdmin(function() use ($id)
			{
				$GLOBALS['egw']->accounts->delete($id);
			});
		}
		parent::tearDown();
	}

	protected function makeOtherUser() : int
	{
		return $this->asAdmin(function()
		{
			$command = new \admin_cmd_edit_user(false, [
				'account_lid'       => 'ajax_get_nonscalar_other'.bin2hex(random_bytes(4)),
				'account_firstname' => 'AjaxGetNonScalarId',
				'account_lastname'  => 'Other',
			]);
			$command->comment = 'Needed for unit test '.$this->getName();
			$command->run();
			return $command->account;
		});
	}

	/**
	 * Pulls whatever event data (if any) ajax_get() sent back to the client for assertion.
	 */
	protected function pushedEvents() : array
	{
		$response = Api\Json\Response::get();
		$prop = (new \ReflectionClass($response))->getProperty('responseArray');
		$prop->setAccessible(true);
		$events = [];
		foreach((array)$prop->getValue($response) as $chunk)
		{
			$chunk = (array)$chunk;
			if (($chunk['type'] ?? null) === 'data' && is_array($chunk['data']['data'] ?? null))
			{
				$events[] = $chunk['data']['data'];
			}
		}
		return $events;
	}

	/**
	 * An $event_ids element that is itself an array (rather than a plain id) must be ignored.
	 */
	public function testArrayElementWithColumnKeyIsIgnored()
	{
		(new \calendar_ui())->ajax_get([['cal_owner' => $this->other_account_id]]);

		foreach($this->pushedEvents() as $event)
		{
			$this->assertNotEquals($this->other_event_id, $event['id'] ?? null,
				'a non-scalar $event_ids element must never be processed as an id');
		}
	}

	/**
	 * Same as above, with an integer-keyed array element.
	 */
	public function testArrayElementWithIntegerKeyIsIgnored()
	{
		(new \calendar_ui())->ajax_get([[1 => 'something']]);

		$this->assertSame([], $this->pushedEvents(),
			'a non-scalar $event_ids element must be dropped, not processed');
	}

	/**
	 * Legitimate scalar ids must still work normally - the fix must not break real usage.
	 */
	public function testScalarIdStillWorks()
	{
		$me = $GLOBALS['egw_info']['user']['account_id'];
		$event_id = $this->bo->save([
			'title'        => 'AjaxGetNonScalarIdTest own '.bin2hex(random_bytes(4)),
			'start'        => new Api\DateTime(time() + 3600, Api\DateTime::$server_timezone),
			'end'          => new Api\DateTime(time() + 7200, Api\DateTime::$server_timezone),
			'owner'        => $me,
			'participants' => [$me => 'A'],
		]);
		$this->assertNotFalse($event_id, 'could not create own test event');

		try
		{
			(new \calendar_ui())->ajax_get([$event_id]);

			$found = false;
			foreach($this->pushedEvents() as $event)
			{
				$found = $found || ($event['id'] ?? null) == $event_id;
			}
			$this->assertTrue($found, 'a plain scalar id must still be processed by ajax_get()');
		}
		finally
		{
			$this->bo->delete($event_id, 0, true);
		}
	}
}
