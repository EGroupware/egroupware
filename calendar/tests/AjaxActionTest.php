<?php
/**
 * Test the ajax endpoint the calendar list's context-menu actions now call
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
 * calendar_uilist::ajax_action() is what "Change your status" in the list view calls now; it used
 * to submit the whole eTemplate, rebuilding the list and losing its scroll position and selection.
 *
 * WHY THIS EXISTS
 * Calendar is the one converted app whose list endpoint is NOT on "<app>_ui": it lives on
 * calendar_uilist. EgwApp.ajax_action() falls back to the "<app>.<app>_ui.ajax_action" convention
 * when the action does not name a menuaction, so every one of these actions answered
 * "calendar.calendar_ui.ajax_action is not a valid menuaction" (400) and did nothing at all. That
 * only shows up by clicking the entry - the endpoint tested here was always fine - so this pins
 * the endpoint while calendar_uilist::get_actions() pins the menuaction it has to be reached by.
 *
 * SETUP
 * Each test creates its own event and deletes it again in tearDown.
 *
 * PASS CRITERIA
 * The participant status really changed (read back through calendar_bo), and the response carries
 * an egw.refresh call - without which a converted action updates no rows.
 */
class AjaxActionTest extends LoggedInTest
{
	/** @var \calendar_boupdate */
	protected $bo;
	protected $event_id;

	protected function setUp() : void
	{
		Api\Json\Response::get()->initResponseArray();
		$this->bo = new \calendar_boupdate();
	}

	protected function tearDown() : void
	{
		if ($this->event_id)
		{
			$this->bo->delete($this->event_id, 0, true);
			$this->event_id = null;
		}
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
	 * A real eTemplate request id, the way the browser sends one along.
	 *
	 * These endpoints refuse without it: json.php has no CSRF token of its own, so the exec id is
	 * what says the caller had one of our pages open (Nextmatch::validateExecId()).  Writing to
	 * the request is what persists it - a brand-new one with nothing set is never saved.
	 */
	protected function execId() : string
	{
		$request = \EGroupware\Api\Etemplate\Request::read();
		$id = $request->id();
		$request->content = ['nm' => []];
		unset($request);
		return $id;
	}

	protected function makeEvent() : int
	{
		$me = $GLOBALS['egw_info']['user']['account_id'];
		$this->event_id = $this->bo->save([
			'title' => 'AjaxActionTest ' . bin2hex(random_bytes(4)),
			'start' => new Api\DateTime(time() + 3600, Api\DateTime::$server_timezone),
			'end'   => new Api\DateTime(time() + 7200, Api\DateTime::$server_timezone),
			'owner' => $me,
			'participants' => [$me => 'A'],
		]);
		$this->assertNotFalse($this->event_id, 'could not create the test event');
		return $this->event_id;
	}

	protected function myStatus() : string
	{
		$event = $this->bo->read($this->event_id);
		return substr((string)$event['participants'][$GLOBALS['egw_info']['user']['account_id']], 0, 1);
	}

	/**
	 * The regression shape: status-<X> has to reach action()'s body and persist. action() splits
	 * the id on '-', so the status letter travels in the action id itself.
	 */
	public function testStatusChangeThroughTheEndpoint()
	{
		$this->makeEvent();
		$this->assertSame('A', $this->myStatus());

		(new \calendar_uilist())->ajax_action($this->execId(), 'status-T', [$this->event_id], false, []);

		$this->assertSame('T', $this->myStatus(), 'status-T must set the status to tentative');
		$this->assertNotNull($this->refreshCall(),
			'the endpoint must answer with egw.refresh, or the list updates no rows');
	}

	/**
	 * _targetapp must be a real app name: egw.refresh() resolves it before its msg-only
	 * early-return, and a name that is not an app throws in the kdots framework, before the
	 * result message is ever shown.
	 */
	public function testRefreshTargetappIsARealApp()
	{
		$this->makeEvent();

		(new \calendar_uilist())->ajax_action($this->execId(), 'status-R', [$this->event_id], false, []);

		$parms = $this->refreshCall();
		$this->assertNotNull($parms);
		$this->assertSame('calendar', $parms[4], 'never the msg-only-push-refresh sentinel');
	}

	/**
	 * "Select all" re-runs the list query to expand the selection; with nothing cached that used
	 * to run unfiltered, ie. every event the user can see.
	 */
	public function testSelectAllWithoutACachedQueryTouchesNothing()
	{
		$this->makeEvent();
		Api\Cache::unsetSession('calendar', 'calendar_list');

		(new \calendar_uilist())->ajax_action($this->execId(), 'status-T', [], true, []);

		$this->assertSame('A', $this->myStatus(),
			'select-all with no cached query must NOT fall back to acting on everything');
	}

	/**
	 * The actions have to name calendar_uilist explicitly, since they are reached through
	 * EgwApp.ajax_action() whose fallback ("calendar.calendar_ui.ajax_action") is not a class that
	 * exists.  Declared on the container, so every generated child inherits it.
	 */
	public function testStatusActionNamesTheRightMenuaction()
	{
		$ui = new \calendar_uilist();
		$rm = new \ReflectionMethod($ui, 'get_actions');
		$rm->setAccessible(true);
		$actions = $rm->invoke($ui);

		$this->assertSame('calendar.calendar_uilist.ajax_action',
			$actions['status']['data']['menuaction'] ?? null,
			'"Change your status" must name calendar_uilist, or every child 400s');
	}
}
