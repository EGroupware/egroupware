<?php
/**
 * Test the bulk distribution-list actions the context-menu list dialog sends
 *
 * @link https://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Addressbook;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

/**
 * addressbook_ui::action() applies the distribution-list changes the "Add to or remove from list"
 * dialog sends: to_list_<id> adds the selected contacts to a list, remove_from_list_<id> removes
 * them from one.
 *
 * WHY remove_from_list_<id> EXISTS
 * The sub-menu it replaces had a bare "Remove from distribution list" carrying no list id at all.
 * The server fell back to `$query['filter2']` - whichever list the filter dropdown happened to be
 * showing - so the entry had to be disabled unless one was selected there, and it was impossible
 * to remove a contact from a list you were not already filtered to. The dialog names the list.
 *
 * SETUP
 * Each test creates its own list and contact and removes both again in tearDown, so nothing
 * pre-existing is touched and a failed assertion still cleans up.
 *
 * PASS CRITERIA
 * The list membership read back from the storage layer matches what the action should have done.
 * The filter2-independence test deliberately leaves the session's cached query EMPTY, which is
 * what proves the id is coming from the action and not from the filter.
 */
class DistributionListActionTest extends LoggedInTest
{
	/** @var \addressbook_bo */
	protected $bo;
	/** @var \addressbook_ui */
	protected $ui;
	protected $contact_id;
	protected $list_id;

	protected function setUp() : void
	{
		$this->bo = new \addressbook_bo();
		$this->ui = new \addressbook_ui();
		Api\Json\Response::get()->initResponseArray();
	}

	protected function tearDown() : void
	{
		if ($this->list_id)
		{
			$this->bo->delete_list($this->list_id);
			$this->list_id = null;
		}
		if ($this->contact_id)
		{
			// Contacts::delete() only marks tid='D' first time round
			$this->bo->delete($this->contact_id);
			$this->bo->delete($this->contact_id);
			$this->contact_id = null;
		}
	}

	protected function makeContact() : int
	{
		$contact = ['n_family' => 'DistributionListActionTest', 'n_given' => 'Temporary',
			'owner' => $GLOBALS['egw_info']['user']['account_id']];
		return $this->contact_id = $this->bo->save($contact);
	}

	protected function makeList() : int
	{
		$this->list_id = $this->bo->add_list(
			['list_name' => 'DistributionListActionTest ' . microtime(true)],
			$GLOBALS['egw_info']['user']['account_id']);
		$this->assertIsInt($this->list_id, 'could not create the test list');
		return $this->list_id;
	}

	/**
	 * Membership lives in egw_addressbook2list; read it directly rather than through search(),
	 * whose list filtering goes via a view and does not accept a plain 'list' column.
	 */
	protected function isOnList() : bool
	{
		$rs = $GLOBALS['egw']->db->select('egw_addressbook2list', 'contact_id',
			['list_id' => $this->list_id, 'contact_id' => $this->contact_id],
			__LINE__, __FILE__, false, '', 'api');
		return (bool)$rs->fetchColumn();
	}

	protected function runAction(string $action, array $query = []) : array
	{
		$success = $failed = 0;
		$action_msg = $msg = $error_msg = null;
		$ok = $this->ui->action($action, [$this->contact_id], false, $success, $failed, $action_msg,
			$query, $msg, [], $error_msg);
		return ['ok' => $ok, 'success' => $success, 'failed' => $failed, 'msg' => $msg];
	}

	public function testAddToListPutsTheContactOnIt()
	{
		$this->makeContact();
		$this->makeList();
		$this->assertFalse($this->isOnList(), 'the contact should not be on the list yet');

		$result = $this->runAction('to_list_' . $this->list_id);

		$this->assertTrue($result['ok'], 'to_list reported failure: ' . $result['msg']);
		$this->assertTrue($this->isOnList(), 'the contact must be on the list');
	}

	/**
	 * THE point of the new action id: the list comes from the action, not from whatever the filter
	 * happens to be. The cached query passed in is deliberately EMPTY - under the old bare
	 * remove_from_list that alone made the removal impossible.
	 */
	public function testRemoveFromListWorksWithoutAFilter()
	{
		$this->makeContact();
		$this->makeList();
		$this->bo->add2list($this->contact_id, $this->list_id);
		$this->assertTrue($this->isOnList(), 'setup failed: contact not on the list');

		$result = $this->runAction('remove_from_list_' . $this->list_id, []);

		$this->assertTrue($result['ok'], 'remove_from_list reported failure: ' . $result['msg']);
		$this->assertFalse($this->isOnList(),
			'the contact must be off the list, with no filter2 involved');
	}

	/**
	 * The old bare action id still has to behave as it did, for anything not yet converted.
	 */
	public function testBareRemoveFromListStillUsesTheFilter()
	{
		$this->makeContact();
		$this->makeList();
		$this->bo->add2list($this->contact_id, $this->list_id);

		$result = $this->runAction('remove_from_list', ['filter2' => $this->list_id]);

		$this->assertTrue($result['ok'], 'the filter2 fallback must keep working');
		$this->assertFalse($this->isOnList());
	}

	/**
	 * ...and with neither an id nor a filter it must refuse rather than guess.
	 */
	public function testRemoveFromListWithNoListAtAllIsRefused()
	{
		$this->makeContact();
		$this->makeList();
		$this->bo->add2list($this->contact_id, $this->list_id);

		$result = $this->runAction('remove_from_list', []);

		$this->assertFalse($result['ok']);
		$this->assertNotEmpty($result['msg'], 'it has to say why');
		$this->assertTrue($this->isOnList(), 'nothing may be removed when no list was named');
	}

	/**
	 * The dialog fetches its options from here rather than them travelling with every get_rows().
	 */
	public function testTheOptionsEndpointReturnsTheUsersLists()
	{
		$this->makeList();

		$this->ui->ajax_distribution_lists(true);

		$response = Api\Json\Response::get();
		$prop = (new \ReflectionClass($response))->getProperty('responseArray');
		$prop->setAccessible(true);
		$data = null;
		foreach((array)$prop->getValue($response) as $chunk)
		{
			$chunk = (array)$chunk;
			if (($chunk['type'] ?? null) === 'data') $data = $chunk['data'];
		}
		$this->assertIsArray($data, 'the endpoint must answer with the option list');
		$this->assertContains((string)$this->list_id, array_column($data, 'value'),
			'the list just created must be offered');
	}
}
