<?php
/**
 * EGroupware Api: the ajax_action() endpoints nextmatch context-menu actions now call
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../LoggedInTest.php');

/**
 * Converting a context-menu action from submit to ajax moves it onto an ajax_action() endpoint.
 * Most of those endpoints delegate to an action() that already existed, but the endpoints
 * themselves are new or rewritten code: argument order, the checkbox array, the "select all"
 * expansion and the egw.refresh() response are all new, and none of it runs at all unless a real
 * row is passed.
 *
 * WHY THIS EXISTS
 * The conversion work was verified by driving actions with a deliberately non-existent row id, so
 * as not to write to real data. Every one of these handlers opens with
 * `if (($Ok = !!($contact = $this->read($id)) && ...))`, so read() fails, the && short-circuits,
 * and the handler body never executes. That proved routing and payload and nothing else - it let
 * a PHP 8 fatal through in addressbook's cat_set (see addressbook/tests/CategoryActionTest.php).
 * These tests call ajax_action() the way the browser does, with a row that exists.
 *
 * SETUP
 * Each test creates its own entry and removes it in tearDown, so nothing pre-existing is touched
 * and a failed assertion still cleans up. Api\Json\Response is inspected rather than mocked, so
 * the assertions cover what actually gets sent back to the client.
 *
 * PASS CRITERIA
 * The entry really changed (read back from the storage layer), and the response carries an
 * egw.refresh call - without which a converted action updates no rows at all.
 */
class NextmatchAjaxActionTest extends LoggedInTest
{
	protected $contact_id;
	protected $info_id;
	/** @var \addressbook_bo */
	protected $contacts;
	/** @var \infolog_bo */
	protected $infolog;

	protected function setUp() : void
	{
		$this->contacts = new \addressbook_bo();
		$this->infolog = new \infolog_bo();
		// a fresh response per test, so assertions only see this test's calls
		Api\Json\Response::get()->initResponseArray();
	}

	protected function tearDown() : void
	{
		if ($this->contact_id)
		{
			// Contacts::delete() only marks tid='D' first time round; a second call really removes it
			$this->contacts->delete($this->contact_id);
			$this->contacts->delete($this->contact_id);
			$this->contact_id = null;
		}
		if ($this->info_id)
		{
			$this->infolog->delete($this->info_id, false, false, true);
			$this->info_id = null;
		}
	}

	/**
	 * The egw.refresh call the response should carry, or null
	 */
	protected function refreshCall() : ?array
	{
		$response = Api\Json\Response::get();
		$rc = new \ReflectionClass($response);
		$prop = $rc->getProperty('responseArray');
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

	protected function makeContact(array $extra = []) : int
	{
		$contact = ['n_family' => 'AjaxActionTest', 'n_given' => 'Temporary',
			'owner' => $GLOBALS['egw_info']['user']['account_id']] + $extra;
		return $this->contact_id = $this->contacts->save($contact);
	}

	protected function makeInfolog(array $extra = []) : int
	{
		$info = ['info_subject' => 'AjaxActionTest', 'info_type' => 'task',
			'info_status' => 'ongoing', 'info_owner' => $GLOBALS['egw_info']['user']['account_id']] + $extra;
		return $this->info_id = $this->infolog->write($info, true, true, true, true);
	}

	/**
	 * addressbook_ui::ajax_action() used to declare its 4th parameter $skip_notification and pass
	 * it straight into action()'s $checkboxes slot. move_to_* reads $checkboxes['move_to_copy'],
	 * so getting that wrong turns a requested copy into a move - ie. it loses the original.
	 */
	public function testAddressbookMoveToCopyKeepsTheOriginal()
	{
		$personal = $GLOBALS['egw_info']['user']['account_id'];
		$this->makeContact(['owner' => $personal]);

		$ui = new \addressbook_ui();
		$ui->ajax_action('move_to_' . $personal, [$this->contact_id], false, ['move_to_copy' => true]);

		$this->assertNotEmpty($this->contacts->read($this->contact_id),
			'with "Copy instead of move" ticked the original contact must still exist');
		$this->assertNotNull($this->refreshCall(),
			'ajax_action() must answer with egw.refresh, or the list updates no rows');
	}

	/**
	 * The same endpoint with the checkbox unticked: a move to the SAME addressbook is a no-op,
	 * which is what makes this safe to assert without a second addressbook to move into.
	 */
	public function testAddressbookMoveToReportsThroughTheResponse()
	{
		$personal = $GLOBALS['egw_info']['user']['account_id'];
		$this->makeContact(['owner' => $personal]);

		$ui = new \addressbook_ui();
		$ui->ajax_action('move_to_' . $personal, [$this->contact_id], false, []);

		$parms = $this->refreshCall();
		$this->assertNotNull($parms);
		$this->assertNotEmpty($parms[0], 'the user gets a result message');
		// the sentinel belongs in the 2nd argument only - resolving it as _targetapp (5th) throws
		// in the kdots framework, before refresh() ever shows the message
		$this->assertSame('addressbook', $parms[4],
			'_targetapp must be a real app, never the msg-only-push-refresh sentinel');
	}

	public function testAddressbookCategoryAddThroughTheEndpoint()
	{
		$this->makeContact(['cat_id' => '1']);

		$ui = new \addressbook_ui();
		$ui->ajax_action('cat_add_9', [$this->contact_id], false, []);

		$this->assertSame('1,9', $this->contacts->read($this->contact_id)['cat_id']);
	}

	/**
	 * infolog's close action, end to end through the endpoint the context menu now calls.
	 */
	public function testInfologCloseSetsStatusDone()
	{
		$this->makeInfolog();
		$this->assertSame('ongoing', $this->infolog->read($this->info_id)['info_status']);

		$ui = new \infolog_ui();
		$ui->ajax_action('close', [$this->info_id], false, []);

		$info = $this->infolog->read($this->info_id);
		$this->assertSame('done', $info['info_status'], 'close must set the status to done');
		$this->assertEquals(100, $info['info_percent']);
		$this->assertNotNull($this->refreshCall());
	}

	public function testInfologStatusChangeThroughTheEndpoint()
	{
		$this->makeInfolog();

		$ui = new \infolog_ui();
		$ui->ajax_action('status_billed', [$this->info_id], false, []);

		$this->assertSame('billed', $this->infolog->read($this->info_id)['info_status']);
	}

	/**
	 * The category dialog's "Remove" sends the bare prefix. infolog's handler has always had a
	 * lang('removed category') branch for it, but category_action() never emitted a "None" entry,
	 * so nothing could reach it - this is the first thing that does.
	 */
	public function testInfologCategoryRemoveClearsIt()
	{
		$this->makeInfolog(['info_cat' => 1]);
		$this->assertEquals(1, $this->infolog->read($this->info_id)['info_cat']);

		$ui = new \infolog_ui();
		$ui->ajax_action('cat_', [$this->info_id], false, []);

		$this->assertEmpty($this->infolog->read($this->info_id)['info_cat'],
			'the bare cat_ prefix must clear the category');
	}

	/**
	 * Same hole in addressbook: action() fetches the cached query itself and, finding none,
	 * ran get_rows() unfiltered - ie. every contact the user can see. Pre-existing on the submit
	 * path; the ajax endpoint refuses instead.
	 */
	public function testAddressbookSelectAllWithoutACachedQueryTouchesNothing()
	{
		$this->makeContact(['cat_id' => '1']);
		Api\Cache::unsetSession('addressbook', 'index');

		$ui = new \addressbook_ui();
		$ui->ajax_action('cat_add_9', [], true, []);

		$this->assertSame('1', $this->contacts->read($this->contact_id)['cat_id'],
			'select-all with no cached query must NOT fall back to acting on everything');
	}

	/**
	 * A crafted request must not be able to point action() at an arbitrary session key.
	 */
	public function testAddressbookRejectsAnUnknownSessionName()
	{
		$this->makeContact(['cat_id' => '1']);
		Api\Cache::unsetSession('addressbook', 'index');

		$ui = new \addressbook_ui();
		$ui->ajax_action('cat_add_9', [], true, [], '../../evil');

		// falls back to 'index', which has no cached query, so the guard above stops it
		$this->assertSame('1', $this->contacts->read($this->contact_id)['cat_id']);
	}

	/**
	 * infolog's ajax_action() used to pass an empty query to action(), so "select all" re-ran
	 * get_rows() with NO filters - ie. every InfoLog the user can see, not the filtered selection.
	 * It now passes the query get_rows() cached in the session. With no cached query at all the
	 * safe outcome is to touch nothing, which is what this pins.
	 */
	public function testInfologSelectAllWithoutACachedQueryTouchesNothing()
	{
		$this->makeInfolog();
		Api\Cache::unsetSession('infolog', 'session_data');

		$ui = new \infolog_ui();
		$ui->ajax_action('close', [], true, []);

		$this->assertSame('ongoing', $this->infolog->read($this->info_id)['info_status'],
			'select-all with no cached query must NOT fall back to acting on everything');
	}
}
