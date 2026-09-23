<?php
/**
 * Test the bulk category actions the context-menu category dialog sends
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
 * addressbook_ui::action() applies the bulk category changes the "Categories" dialog sends:
 * cat_add_<id> and cat_del_<id> add to / remove from a contact's comma-separated cat_id, and
 * cat_set_<csv> replaces the whole list in a single write.
 *
 * WHY THIS EXISTS
 * cat_set was added with the dialog and shipped broken: it called $this->save() with an inline
 * array expression, and save() takes &$contact by reference, which is a fatal in PHP 8
 * ("could not be passed by reference"). It was not caught because the action was only exercised
 * with a non-existent contact id - read() returns false, the && short-circuits, and save() is
 * never reached. A bogus id proves the request routing and NOTHING about the handler body.
 *
 * SETUP
 * Each test creates its own contact in the user's personal addressbook and deletes it again in
 * tearDown, so nothing pre-existing is touched and a failed assertion still cleans up.
 *
 * PASS CRITERIA
 * The contact's cat_id after action() equals the expected comma-separated list. Category ids are
 * used as opaque integers - action() does not validate them against existing categories, and this
 * is testing the list arithmetic, not the category system.
 */
class CategoryActionTest extends LoggedInTest
{
	/** @var \addressbook_ui */
	protected $ui;
	/** @var \addressbook_bo */
	protected $bo;
	protected $contact_id;

	protected function setUp() : void
	{
		$this->ui = new \addressbook_ui();
		$this->bo = new \addressbook_bo();
	}

	protected function tearDown() : void
	{
		if ($this->contact_id)
		{
			// Contacts::delete() only marks tid='D' the first time round (the "deleted" bin);
			// only a second call on an already-deleted contact really removes the row. One call
			// would leave every test's fixture behind in the address book.
			$this->bo->delete($this->contact_id);
			$this->bo->delete($this->contact_id);
			$this->contact_id = null;
		}
	}

	/**
	 * Nothing this file created may survive it - a fixture left in the "deleted" bin is still
	 * visible to the user, and these are indistinguishable junk contacts.
	 */
	public function testFixturesAreReallyRemoved()
	{
		$id = $this->makeContact('1');
		$this->bo->delete($id);
		$this->bo->delete($id);
		$this->contact_id = null;
		// read() answers null (not false) for a row that is gone
		$this->assertEmpty($this->bo->read($id), 'the test fixture was not actually removed');
	}

	/**
	 * A throw-away contact owned by the test user, with the given categories
	 */
	protected function makeContact(?string $cat_id) : int
	{
		$contact = [
			'n_family' => 'CategoryActionTest',
			'n_given'  => 'Temporary',
			'owner'    => $GLOBALS['egw_info']['user']['account_id'],
			'cat_id'   => $cat_id,
		];
		$this->contact_id = $this->bo->save($contact);
		$this->assertNotFalse($this->contact_id, 'could not create the test contact');
		return $this->contact_id;
	}

	protected function runAction(string $action) : array
	{
		$success = $failed = 0;
		$action_msg = $msg = $error_msg = null;
		$ok = $this->ui->action($action, [$this->contact_id], false, $success, $failed, $action_msg,
			'index', $msg, [], $error_msg);
		return ['ok' => $ok, 'success' => $success, 'failed' => $failed, 'msg' => $msg];
	}

	protected function catId() : ?string
	{
		return $this->bo->read($this->contact_id)['cat_id'] ?: null;
	}

	/**
	 * The regression this file was written for: cat_set must actually reach save().
	 */
	public function testSetReplacesTheWholeList()
	{
		$this->makeContact('1,2');
		$result = $this->runAction('cat_set_3,4');

		$this->assertTrue($result['ok'], 'cat_set reported failure: ' . $result['msg']);
		$this->assertSame(1, $result['success']);
		$this->assertSame('3,4', $this->catId(),
			'cat_set must replace the whole list, not merge with it');
	}

	public function testSetWithASingleCategory()
	{
		$this->makeContact('1,2,3');
		$this->runAction('cat_set_7');
		$this->assertSame('7', $this->catId());
	}

	/**
	 * "Replace" with nothing picked is not reachable from the dialog (it requires a selection),
	 * but the action id is public, so clearing must at least be safe rather than fatal.
	 */
	public function testSetWithNoCategoriesClearsThem()
	{
		$this->makeContact('1,2');
		$this->runAction('cat_set_');
		$this->assertNull($this->catId());
	}

	/**
	 * Nothing to write must not report a spurious failure.
	 */
	public function testSetToTheSameListIsANoOp()
	{
		$this->makeContact('5,6');
		$result = $this->runAction('cat_set_5,6');
		$this->assertTrue($result['ok']);
		$this->assertSame('5,6', $this->catId());
	}

	/**
	 * The two verbs cat_set was added alongside, so the dialog's three buttons are all covered.
	 */
	public function testAddAppendsToTheList()
	{
		$this->makeContact('1');
		$this->runAction('cat_add_9');
		$this->assertSame('1,9', $this->catId());
	}

	public function testDeleteRemovesFromTheList()
	{
		$this->makeContact('1,9');
		$this->runAction('cat_del_9');
		$this->assertSame('1', $this->catId());
	}
}
