<?php
/**
 * The timesheet list's context-menu actions, run the way the browser runs them.
 *
 * @package timesheet
 * @subpackage tests
 */

namespace EGroupware\Timesheet;

use EGroupware\Api;

require_once realpath(__DIR__ . '/../../api/tests/AppTest.php');

/**
 * timesheet_ui::ajax_action() is what the converted context-menu actions call. These pin the
 * round trip - the action reaches action()'s body, persists, and answers with an egw.refresh()
 * the list can act on - and that it refuses a request with no eTemplate exec id.
 *
 * The removal case is a regression guard. action()'s 'cat' branch used to read
 *
 *     if (($entry = $this->read($id)) && ($entry['cat_id'] = $settings) && $this->save($entry) == 0)
 *
 * where the middle term is an *assignment*, so it evaluates to $settings. Removing a category
 * passes an empty one, the chain short-circuited before save(), and the user was told
 * "insufficient rights" for an entry they owned. The picker dialog offers Remove as its own
 * button, so this was reachable with two clicks.
 */
class AjaxActionTest extends \EGroupware\Api\AppTest
{
	protected $ts_id;

	protected function setUp() : void
	{
		$this->ts_id = $this->makeTimesheet();
		Api\Json\Response::get()->initResponseArray();
	}

	protected function tearDown() : void
	{
		if ($this->ts_id)
		{
			$GLOBALS['egw']->db->delete('egw_timesheet', array('ts_id' => $this->ts_id),
				__LINE__, __FILE__, 'timesheet');
		}
		$this->ts_id = null;
	}

	protected function makeTimesheet() : int
	{
		$so = new Api\Storage\Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_ajaxaction_' . bin2hex(random_bytes(6)),
			'ts_start'    => time(),
			'ts_duration' => 60,
			'ts_quantity' => 1.0,
			'ts_owner'    => $GLOBALS['egw_info']['user']['account_id'],
			'ts_created'  => time(),
			'ts_modified' => time(),
			'ts_modifier' => $GLOBALS['egw_info']['user']['account_id'],
		);
		$this->assertSame(0, $so->save(), 'could not create the test timesheet');
		return (int)$so->data['ts_id'];
	}

	protected function readCat()
	{
		return $GLOBALS['egw']->db->select('egw_timesheet', 'cat_id',
			array('ts_id' => $this->ts_id), __LINE__, __FILE__, false, '', 'timesheet')->fetchColumn();
	}

	/**
	 * A real eTemplate request id, the way the browser sends one along.
	 */
	protected function execId() : string
	{
		$request = Api\Etemplate\Request::read();
		$id = $request->id();
		$request->content = array('nm' => array());
		unset($request);
		return $id;
	}

	protected function refreshCall() : ?array
	{
		$response = Api\Json\Response::get();
		$prop = (new \ReflectionClass($response))->getProperty('responseArray');
		$prop->setAccessible(true);
		foreach((array)$prop->getValue($response) as $chunk)
		{
			$chunk = (array)$chunk;
			if (($chunk['type'] ?? null) === 'apply' && ($chunk['data']['func'] ?? null) === 'egw.refresh')
			{
				return (array)$chunk['data']['parms'];
			}
		}
		return null;
	}

	/**
	 * A category id that exists, so the "set" direction is testing the action rather than a
	 * rejected id. Falls back to creating one.
	 */
	protected function aCategory() : int
	{
		$cats = new Api\Categories($GLOBALS['egw_info']['user']['account_id'], 'timesheet');
		foreach((array)$cats->return_array('all', 0, false) as $cat)
		{
			if (!empty($cat['id'])) return (int)$cat['id'];
		}
		return (int)$cats->add(array('name' => 'phpunit_cat_' . bin2hex(random_bytes(4)), 'descr' => 'test'));
	}

	public function testCategorySetThroughTheEndpoint()
	{
		$cat_id = $this->aCategory();

		(new \timesheet_ui())->ajax_action($this->execId(), 'cat_' . $cat_id, array($this->ts_id), false);

		$this->assertSame((string)$cat_id, (string)$this->readCat(), 'the category has to persist');
		$this->assertNotNull($this->refreshCall(),
			'the endpoint must answer with egw.refresh, or the list updates no rows');
	}

	/**
	 * The regression: removing a category is a success, not "insufficient rights".
	 */
	public function testCategoryRemoveThroughTheEndpoint()
	{
		$cat_id = $this->aCategory();
		(new \timesheet_ui())->ajax_action($this->execId(), 'cat_' . $cat_id, array($this->ts_id), false);
		$this->assertSame((string)$cat_id, (string)$this->readCat(), 'precondition: category set');

		Api\Json\Response::get()->initResponseArray();
		(new \timesheet_ui())->ajax_action($this->execId(), 'cat_', array($this->ts_id), false);

		$this->assertEmpty($this->readCat(), 'removing the category has to persist');

		$parms = $this->refreshCall();
		$this->assertNotNull($parms, 'and it has to answer with egw.refresh');
		$this->assertSame('success', end($parms),
			'a removal is a success - it used to be reported as a failure');
		// 'removed category' lives in api/lang as a common phrase, shared with infolog rather
		// than copied per app - this also pins that the common fallback resolves it here
		$this->assertStringContainsString('removed category', $parms[0],
			'the message has to be translated, not left as a raw phrase lookup');
	}

	/**
	 * _targetapp has to be a real app name, never the msg-only sentinel: egw.refresh() resolves
	 * it before its msg-only early-return.
	 */
	public function testRefreshNamesARealApp()
	{
		(new \timesheet_ui())->ajax_action($this->execId(), 'cat_' . $this->aCategory(),
			array($this->ts_id), false);

		$parms = $this->refreshCall();
		$this->assertNotNull($parms);
		$this->assertSame('timesheet', $parms[4], 'the 5th argument names the window to refresh');
	}

	public function testRefusesWithoutAnExecId()
	{
		$before = $this->readCat();

		(new \timesheet_ui())->ajax_action('', 'cat_' . $this->aCategory(), array($this->ts_id), false);

		$this->assertSame((string)$before, (string)$this->readCat(),
			'nothing may be written without an exec id');
		$this->assertNull($this->refreshCall(), 'and it must not answer as though it had acted');
	}

	public function testRefusesAnExecIdThatIsNotOurs()
	{
		$before = $this->readCat();

		(new \timesheet_ui())->ajax_action('timesheet_nobody_madeThisUp', 'cat_' . $this->aCategory(),
			array($this->ts_id), false);

		$this->assertSame((string)$before, (string)$this->readCat(),
			'an id that resolves to no request is no better than none');
	}
}
