<?php
/**
 * Test the ajax endpoint the custom-field list's Delete action now calls
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
 * admin_customfields::ajax_action() is a new endpoint: Delete used to submit the whole eTemplate.
 *
 * It is the first converted action carrying a 'confirm_handler', which is what makes it
 * interesting: the policy app shows its own dialog and writes what it collected ("requested by",
 * comment) into action.data, which a submit carried along as nm[admin_cmd]. EgwApp.ajax_action()
 * now forwards that as a 6th argument - without it the policy app would ask for a comment and
 * then throw it away, which is worse than not asking.
 *
 * The delete also no longer depends on $this->appname: the endpoint has no $_GET['appname'] to
 * construct the class with, so each field's app and name are read back from the field itself.
 *
 * PASS CRITERIA
 * The field really went away (read back from egw_customfields), the admin command recorded the
 * comment, and the response carries an egw.refresh whose _targetapp is null.
 */
class CustomfieldsAjaxActionTest extends LoggedInTest
{
	const APP = 'admin';
	/** @var int[] cf_ids created by this test */
	protected $cf_ids = [];

	protected function setUp() : void
	{
		Api\Json\Response::get()->initResponseArray();
	}

	protected function tearDown() : void
	{
		foreach($this->cf_ids as $cf_id)
		{
			$GLOBALS['egw']->db->delete('egw_customfields', ['cf_id' => $cf_id], __LINE__, __FILE__, 'phpgwapi');
		}
		$this->cf_ids = [];
	}

	/**
	 * A real eTemplate request id, the way the browser sends one along - the endpoint refuses
	 * without it, see Nextmatch::validateExecId().  Writing to the request is what persists it.
	 */
	protected function execId() : string
	{
		$request = \EGroupware\Api\Etemplate\Request::read();
		$id = $request->id();
		$request->content = ['nm' => []];
		unset($request);
		return $id;
	}

	/**
	 * The egw.refresh call the response should carry, or null
	 */
	protected function refreshCall() : ?array
	{
		$response = Api\Json\Response::get();
		$prop = (new \ReflectionClass($response))->getProperty('responseArray');
		$prop->setAccessible(true);
		foreach((array)$prop->getValue($response) as $chunk)
		{
			$chunk = (array)$chunk;
			if (($chunk['type'] ?? null) === 'apply' && (($chunk['data']['func'] ?? null) === 'egw.refresh'))
			{
				return (array)$chunk['data']['parms'];
			}
		}
		return null;
	}

	protected function makeCustomfield(string $name) : int
	{
		$GLOBALS['egw']->db->insert('egw_customfields', [
			'cf_app'   => self::APP,
			'cf_name'  => $name,
			'cf_label' => $name,
			'cf_type'  => 'text',
			'cf_order' => 100,
		], false, __LINE__, __FILE__, 'phpgwapi');

		return $this->cf_ids[] = $GLOBALS['egw']->db->get_last_insert_id('egw_customfields', 'cf_id');
	}

	protected function exists($cf_id) : bool
	{
		return (bool)$GLOBALS['egw']->db->select('egw_customfields', 'cf_id', ['cf_id' => $cf_id],
			__LINE__, __FILE__, false, '', 'phpgwapi')->fetchColumn();
	}

	/**
	 * The regression shape: the endpoint has to delete without ever being told which app the
	 * fields belong to.
	 */
	public function testDeleteRemovesTheCustomfieldWithoutAnAppname()
	{
		$cf_id = $this->makeCustomfield('ajaxactiontest_'.bin2hex(random_bytes(4)));
		$this->assertTrue($this->exists($cf_id), 'fixture was not created');

		// constructed with NO appname, the way json.php builds it; as admin, because deleting a
		// custom field runs an admin command and the endpoint checks for that right
		$this->asAdmin(fn() => (new \admin_customfields())->ajax_action($this->execId(), 'delete', [$cf_id]));

		$this->assertFalse($this->exists($cf_id), 'delete must remove the custom field');
		$parms = $this->refreshCall();
		$this->assertNotNull($parms, 'the endpoint must answer with egw.refresh');
		$this->assertEquals($cf_id, $parms[2]);
		$this->assertSame('delete', $parms[3]);
	}

	/**
	 * The policy app's comment has to reach the admin command, or asking for it was pointless.
	 */
	public function testThePolicyCommentReachesTheAdminCommand()
	{
		$cf_id = $this->makeCustomfield('ajaxactiontest_policy_'.bin2hex(random_bytes(4)));
		$comment = 'AjaxActionTest policy comment '.bin2hex(random_bytes(4));

		$this->asAdmin(fn() => (new \admin_customfields())->ajax_action($this->execId(), 'delete',
			[$cf_id], false, null, ['comment' => $comment]));

		$this->assertFalse($this->exists($cf_id), 'the field still has to go');
		$this->assertNotEmpty($GLOBALS['egw']->db->select('egw_admin_queue', 'cmd_id',
			['cmd_comment' => $comment], __LINE__, __FILE__, false, '', 'admin')->fetchColumn(),
			'the comment the policy dialog collected must be recorded with the command');
	}

	/**
	 * Without a valid exec id the endpoint must do nothing at all.
	 */
	public function testABogusExecIdDeletesNothing()
	{
		$cf_id = $this->makeCustomfield('ajaxactiontest_bogus_'.bin2hex(random_bytes(4)));

		$this->asAdmin(fn() => (new \admin_customfields())
			->ajax_action('admin_nobody_not-a-real-request-id', 'delete', [$cf_id]));

		$this->assertTrue($this->exists($cf_id), 'a rejected request must not run the action');
		$this->assertNull($this->refreshCall(), 'and must not answer with egw.refresh either');
	}

	/**
	 * _targetapp stays null: this list is opened from the admin tree, so naming an app would send
	 * egw.refresh() looking for a window that is not the one showing the list.
	 */
	public function testRefreshDoesNotNameAWindow()
	{
		$cf_id = $this->makeCustomfield('ajaxactiontest_args_'.bin2hex(random_bytes(4)));

		$this->asAdmin(fn() => (new \admin_customfields())->ajax_action($this->execId(), 'delete', [$cf_id]));

		$parms = $this->refreshCall();
		$this->assertSame('admin', $parms[1], 'the app whose lists to refresh');
		$this->assertNull($parms[4] ?? null, 'but never a window');
	}

	/**
	 * More than one row changed means no id at all: egw.refresh() takes a single id, and
	 * Et2Nextmatch.refresh(id, null) only defaults its type when that type is undefined.
	 */
	public function testAMultiRowDeleteAsksForAFullReload()
	{
		$first = $this->makeCustomfield('ajaxactiontest_m1_'.bin2hex(random_bytes(4)));
		$second = $this->makeCustomfield('ajaxactiontest_m2_'.bin2hex(random_bytes(4)));

		$this->asAdmin(fn() => (new \admin_customfields())->ajax_action($this->execId(), 'delete', [$first, $second]));

		$parms = $this->refreshCall();
		$this->assertNull($parms[2], 'no single id for a multi-row action');
		$this->assertNull($parms[3], 'and no type, so egw.refresh reloads the list');
	}
}
