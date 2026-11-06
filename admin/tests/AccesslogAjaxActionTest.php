<?php
/**
 * Test the ajax endpoint the access-log / sessions list context-menu actions now call
 *
 * @link https://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Admin;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

/**
 * admin_accesslog::ajax_action() is what Delete (access log) and Kill (sessions) call from the
 * list's context menu.
 *
 * WHY "SELECT ALL" NEEDS A SESSION CACHE
 * Expanding it means re-running get_rows() with the list's own search and col_filter, and an ajax
 * request carries nothing to read those from - so get_rows() caches what it was asked for and the
 * endpoint reads that.  With nothing cached it refuses, because an empty query means every row
 * the user can see.
 *
 * SETUP
 * Each test writes its own access-log row and removes it again, so no real log entry is at risk.
 */
class AccesslogAjaxActionTest extends LoggedInTest
{
	protected $sessionid;
	/** @var \admin_accesslog */
	protected $ui;

	protected function setUp() : void
	{
		Api\Json\Response::get()->initResponseArray();
		$this->ui = new \admin_accesslog();
	}

	protected function tearDown() : void
	{
		if ($this->sessionid)
		{
			$GLOBALS['egw']->db->delete(\admin_accesslog::TABLE, ['sessionid' => $this->sessionid],
				__LINE__, __FILE__);
			$this->sessionid = null;
		}
		Api\Cache::unsetSession('admin', 'accesslog-log');
	}

	protected function makeLogRow() : int
	{
		$GLOBALS['egw']->db->insert(\admin_accesslog::TABLE, [
			'loginid'     => 'accesslogajaxactiontest',
			'ip'          => '192.0.2.1',          // TEST-NET-1, never a real address
			'li'          => time(),
			'lo'          => time(),               // logged out, so it is a log entry not a session
			'account_id'  => $GLOBALS['egw_info']['user']['account_id'],
			'session_php' => 'AccesslogAjaxActionTest-'.bin2hex(random_bytes(6)),
		], false, __LINE__, __FILE__);
		$this->sessionid = (int)$GLOBALS['egw']->db->get_last_insert_id(\admin_accesslog::TABLE, 'sessionid');
		$this->assertNotEmpty($this->sessionid, 'could not create the test log row');
		return $this->sessionid;
	}

	protected function rowExists(?int $sessionid = null) : bool
	{
		return (bool)$GLOBALS['egw']->db->select(\admin_accesslog::TABLE, 'sessionid',
			['sessionid' => $sessionid ?? $this->sessionid], __LINE__, __FILE__)->fetchColumn();
	}

	/**
	 * A real eTemplate request id, the way the browser sends one along - the endpoint refuses
	 * without it, see Nextmatch::validateExecId().
	 */
	protected function execId() : string
	{
		$request = \EGroupware\Api\Etemplate\Request::read();
		$id = $request->id();
		$request->content = ['nm' => []];
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

	public function testDeleteRemovesTheLogRow()
	{
		$this->makeLogRow();
		$this->assertTrue($this->rowExists());

		$this->ui->ajax_action($this->execId(), 'delete', [$this->sessionid]);

		$this->assertFalse($this->rowExists(), 'delete must really remove the row');
		$this->assertNotNull($this->refreshCall(),
			'the endpoint must answer with egw.refresh, or the list updates no rows');
		$this->sessionid = null;    // already gone
	}

	public function testWithoutALiveExecIdDoesNothing()
	{
		$this->makeLogRow();

		$this->ui->ajax_action('', 'delete', [$this->sessionid]);
		$this->assertTrue($this->rowExists(), 'no exec id must not delete anything');

		$this->ui->ajax_action('admin_nobody_'.base64_encode(random_bytes(32)), 'delete', [$this->sessionid]);
		$this->assertTrue($this->rowExists(), 'a made-up exec id must not delete anything either');
	}

	/**
	 * "Select all" re-runs the list query to expand the selection.  With nothing cached that would
	 * be an unfiltered query - ie. the whole access log - so it has to refuse instead.
	 */
	public function testSelectAllWithoutACachedQueryTouchesNothing()
	{
		$this->makeLogRow();
		Api\Cache::unsetSession('admin', 'accesslog-log');

		$this->ui->ajax_action($this->execId(), 'delete', [], true);

		$this->assertTrue($this->rowExists(),
			'select-all with no cached query must NOT fall back to deleting everything');
	}

	/**
	 * ...and with a cached query it expands to exactly what that query returns.  The query here is
	 * narrowed to this test's own row, so a regression that ignored the filters would show up as
	 * other rows disappearing rather than as a passing test.
	 */
	public function testSelectAllUsesTheCachedQuery()
	{
		$this->makeLogRow();
		$control = $this->makeControlRow();

		// get_rows() gates on admin rights, which the ordinary test user does not have - expanding
		// a "select all" is exactly the path that calls it, so this one has to run as an admin
		$this->asAdmin(function()
		{
			Api\Cache::setSession('admin', 'accesslog-log', [
				'search'       => '',
				'col_filter'   => ['sessionid' => $this->sessionid],
				'session_list' => false,
			]);
			(new \admin_accesslog())->ajax_action($this->execId(), 'delete', [], true);
		});

		$this->assertFalse($this->rowExists(), 'the row the cached query selects must be deleted');
		// NOT a row count: asAdmin() logs in and back out, and every login writes its own
		// access-log row, so the table grows while this test runs.  A second row that the cached
		// query does not select is what proves the expansion respected the filter.
		$this->assertTrue($this->rowExists($control),
			'a row outside the cached query must be left alone');

		$GLOBALS['egw']->db->delete(\admin_accesslog::TABLE, ['sessionid' => $control], __LINE__, __FILE__);
		$this->sessionid = null;
	}

	/**
	 * A second log row, which no test's cached query selects - the control for "nothing else was
	 * deleted".  Returned rather than tracked, so tearDown's single id stays the one under test.
	 */
	protected function makeControlRow() : int
	{
		$keep = $this->sessionid;
		$control = $this->makeLogRow();
		$this->sessionid = $keep;
		return $control;
	}
}
