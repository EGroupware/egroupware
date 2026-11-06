<?php
/**
 * EGroupware Addressbook: regression test for Api\Contacts\Sql under Db::requireSqlFragmentObjects()
 *
 * @link https://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

/**
 * Api\Contacts\Sql (addressbook's SQL backend) opted into Api\Db::requireSqlFragmentObjects(true)
 * on its own Db clone (doc/ai/projects/sql-fragment-type-safety.md). Several of its own filter
 * conditions are raw SQL fragments it builds itself (cat_id, shared_by/shared_with, the ACL OR'ed
 * owner/private/shared condition, the negated account_id filter, list_etag update) - this proves
 * they're wrapped in Db\SqlFragment, and that Api\Contacts\Storage's own 'tid != deleted' filters
 * (added one layer above the backend) are wrapped too, so none of this throws once opted in.
 */
class ContactsSqlFragmentTest extends LoggedInTest
{
	private array $contact_ids = [];
	private ?int $list_id = null;

	protected function tearDown() : void
	{
		$bo = new Api\Contacts();
		foreach ($this->contact_ids as $id)
		{
			$bo->delete($id);
		}
		$this->contact_ids = [];
		if ($this->list_id)
		{
			$bo->delete_list($this->list_id);
			$this->list_id = null;
		}
		parent::tearDown();
	}

	private function saveContact(array $data = []) : int
	{
		$bo = new Api\Contacts();
		$id = $bo->save(array_merge([
			'n_family' => 'ContactsSqlFragmentTest-'.uniqid(),
		], $data));
		$this->assertNotFalse($id, 'saving the fixture contact failed');
		$this->contact_ids[] = $id;
		return $id;
	}

	public function testSearchWithCatIdFilterDoesNotThrow()
	{
		$cat_id = $GLOBALS['egw']->categories->add(['name' => 'ContactsSqlFragmentTest-'.uniqid(), 'appname' => 'addressbook']);
		$id = $this->saveContact(['cat_id' => $cat_id]);

		$bo = new Api\Contacts();
		$rows = $bo->search('', false, '', '', '', false, 'AND', false, ['cat_id' => $cat_id]);

		$this->assertIsArray($rows);

		$GLOBALS['egw']->categories->delete($cat_id);
	}

	public function testSearchWithOwnAddressbookOwnerFilterDoesNotThrow()
	{
		$id = $this->saveContact();

		$bo = new Api\Contacts();
		$rows = $bo->search('', false, '', '', '', false, 'AND', false,
			['owner' => $GLOBALS['egw_info']['user']['account_id']]);

		$this->assertIsArray($rows);
	}

	public function testSearchAllAddressbooksDoesNotThrow()
	{
		$id = $this->saveContact();

		$bo = new Api\Contacts();
		// no 'owner' filter --> "search all addressbooks" ACL branch
		$rows = $bo->search('', false, '', '', '', false, 'AND', false, []);

		$this->assertIsArray($rows);
	}

	public function testSearchWithSharedWithFilterDoesNotThrow()
	{
		$this->saveContact();

		$bo = new Api\Contacts();
		$rows = $bo->search('', false, '', '', '', false, 'AND', false, ['shared_with' => 'not']);

		$this->assertIsArray($rows);
	}

	public function testSearchWithNegatedAccountIdFilterDoesNotThrow()
	{
		$id = $this->saveContact();

		$bo = new Api\Contacts();
		$rows = $bo->search('', false, '', '', '', false, 'AND', false,
			['account_id' => ['!', $GLOBALS['egw_info']['user']['account_id']]]);

		$this->assertIsArray($rows);
	}

	public function testGroupSearchWithAccountDnOrderDoesNotThrow()
	{
		// exercises Accounts\Sql's 'groups' filter (wraps "account_type='g'" in Db\SqlFragment) and
		// Contacts\Sql's in_array() check for that literal when sorting by account_dn
		$rows = $GLOBALS['egw']->accounts->search(['type' => 'groups', 'order' => 'account_dn']);

		$this->assertIsArray($rows);
	}

	public function testAddListUpdatesEtagWithoutThrowing()
	{
		$bo = new Api\Contacts();
		$name = 'ContactsSqlFragmentTest-'.uniqid();
		$this->list_id = $bo->add_list(['list_name' => $name], $GLOBALS['egw_info']['user']['account_id']);

		$this->assertIsInt($this->list_id, 'could not create the test list');

		// add_list() on an existing list hits the 'list_etag=list_etag+1' SqlFragment-wrapped update
		// (insert()'s REPLACE semantics need the full row back, same as addressbook_ui's own caller)
		$list = ['list_name' => $name];
		$updated = $bo->add_list(['list_id' => $this->list_id], $GLOBALS['egw_info']['user']['account_id'], [], $list);
		$this->assertEquals($this->list_id, $updated);
	}
}
