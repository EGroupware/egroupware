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
		$leaked = $this->purgeInfolog();
		if ($leaked)
		{
			throw new \RuntimeException($leaked);
		}
	}

	/**
	 * Purge the entry makeInfolog() created, and say so when it survives.
	 *
	 * infolog_bo::delete() soft-deletes, the same way Contacts::delete() above does: the first
	 * call sets info_status to 'deleted', unlinks everything but file attachments and keeps the
	 * row, and only purges once the entry already reads 'deleted'. One call therefore returns
	 * true with the row still in egw_infolog, which on a shared instance means a growing pile of
	 * 'AjaxActionTest' fixtures. Hence two calls, and a read of egw_infolog afterwards rather
	 * than trusting either return value - a delete refused on rights also only returns false.
	 *
	 * @return string empty when the row is gone, otherwise what is left behind and why
	 */
	protected function purgeInfolog() : string
	{
		if (!$this->info_id)
		{
			return '';
		}
		$info_id = $this->info_id;
		$this->info_id = null;

		$problem = '';
		try
		{
			$this->infolog->delete($info_id, false, false, true);	// -> info_status 'deleted'
			$this->infolog->delete($info_id, false, false, true);	// -> row gone
		}
		catch (\Exception $e)
		{
			$problem = ': ' . get_class($e) . ': ' . $e->getMessage();
		}
		$left = $GLOBALS['egw']->db->select('egw_infolog', 'info_status', array('info_id' => $info_id),
			__LINE__, __FILE__, false, '', 'infolog')->fetchColumn();
		if ($left === false && !$problem)
		{
			return '';
		}
		return sprintf('could not clean up the fixture infolog #%d, it is still in egw_infolog as "%s"%s',
			$info_id, (string)$left, $problem);
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

	/**
	 * A real eTemplate request id, the way the browser sends one along.
	 *
	 * These endpoints refuse without it: json.php has no CSRF token of its own, so the exec id is
	 * what says the caller had one of our pages open (Nextmatch::validateExecId()).  Writing to
	 * the request is what persists it - a brand-new one with nothing set is never saved.
	 */
	protected function execId(array $nm = []) : string
	{
		$request = \EGroupware\Api\Etemplate\Request::read();
		$id = $request->id();
		$request->content = ['nm' => $nm];
		unset($request);
		return $id;
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
		$ui->ajax_action($this->execId(), 'move_to_' . $personal, [$this->contact_id], false, ['move_to_copy' => true]);

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
		$ui->ajax_action($this->execId(), 'move_to_' . $personal, [$this->contact_id], false, []);

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
		$ui->ajax_action($this->execId(), 'cat_add_9', [$this->contact_id], false, []);

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
		$ui->ajax_action($this->execId(), 'close', [$this->info_id], false, []);

		$info = $this->infolog->read($this->info_id);
		$this->assertSame('done', $info['info_status'], 'close must set the status to done');
		$this->assertEquals(100, $info['info_percent']);
		$this->assertNotNull($this->refreshCall());
	}

	public function testInfologStatusChangeThroughTheEndpoint()
	{
		$this->makeInfolog();

		$ui = new \infolog_ui();
		$ui->ajax_action($this->execId(), 'status_billed', [$this->info_id], false, []);

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
		$ui->ajax_action($this->execId(), 'cat_', [$this->info_id], false, []);

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
		$ui->ajax_action($this->execId(), 'cat_add_9', [], true, []);

		$this->assertSame('1', $this->contacts->read($this->contact_id)['cat_id'],
			'select-all with no cached query must NOT fall back to acting on everything');
	}

	/**
	 * json.php authenticates by session cookie and checks app rights and the ajax_* naming rule -
	 * it has no CSRF token, and the eTemplate exec id is what fills that gap for an action the
	 * context menu now sends directly instead of submitting. Without a live one the endpoint has
	 * to do nothing at all, or every converted action is reachable by anything that can make the
	 * browser send its cookie.
	 */
	public function testAnActionWithoutALiveExecIdDoesNothing()
	{
		$this->makeContact(['cat_id' => '1']);
		$ui = new \addressbook_ui();

		$ui->ajax_action('', 'cat_add_9', [$this->contact_id], false, []);
		$this->assertSame('1', $this->contacts->read($this->contact_id)['cat_id'],
			'an action with no exec id at all must not be carried out');

		$ui->ajax_action('addressbook_nobody_'.base64_encode(random_bytes(32)), 'cat_add_9',
			[$this->contact_id], false, []);
		$this->assertSame('1', $this->contacts->read($this->contact_id)['cat_id'],
			'a made-up exec id must not be carried out either');

		// ...and the same call with a real one still works, so the guard is not just refusing
		// everything
		$ui->ajax_action($this->execId(), 'cat_add_9', [$this->contact_id], false, []);
		$this->assertSame('1,9', $this->contacts->read($this->contact_id)['cat_id']);
	}

	/**
	 * A crafted request must not be able to point action() at an arbitrary session key.
	 */
	public function testAddressbookRejectsAnUnknownSessionName()
	{
		$this->makeContact(['cat_id' => '1']);
		Api\Cache::unsetSession('addressbook', 'index');

		$ui = new \addressbook_ui();
		$ui->ajax_action($this->execId(), 'cat_add_9', [], true, [], '../../evil');

		// falls back to 'index', which has no cached query, so the guard above stops it
		$this->assertSame('1', $this->contacts->read($this->contact_id)['cat_id']);
	}

	/**
	 * The open_popup actions (Delegation, Start date, Due date, Links) show a small form and used
	 * to submit the WHOLE eTemplate just so the server could read it. They now build the same
	 * composite action id index() built out of the submitted popup values - <action>_<verb>_<value>
	 * - and send that to ajax_action(). These pin that the server really does understand it, since
	 * the whole conversion rests on that and needed no server change.
	 */
	public function testPopupActionCompositeIdSetsResponsible()
	{
		$this->makeInfolog();
		$me = $GLOBALS['egw_info']['user']['account_id'];

		$ui = new \infolog_ui();
		$ui->ajax_action($this->execId(), 'responsible_ok_' . $me, [$this->info_id], false, []);

		$this->assertEquals([$me], array_values((array)$this->infolog->read($this->info_id)['info_responsible']),
			'responsible_ok_<ids> must set the responsible users');
	}

	public function testPopupActionCompositeIdAddsAndRemovesResponsible()
	{
		$this->makeInfolog();
		$me = $GLOBALS['egw_info']['user']['account_id'];
		$ui = new \infolog_ui();

		$ui->ajax_action($this->execId(), 'responsible_add_' . $me, [$this->info_id], false, []);
		$this->assertContains((string)$me, array_map('strval',
			(array)$this->infolog->read($this->info_id)['info_responsible']));

		$ui->ajax_action($this->execId(), 'responsible_delete_' . $me, [$this->info_id], false, []);
		$this->assertNotContains((string)$me, array_map('strval',
			(array)$this->infolog->read($this->info_id)['info_responsible']));
	}

	public function testPopupActionCompositeIdSetsStartdate()
	{
		$this->makeInfolog();
		$when = mktime(12, 0, 0, 6, 15, 2027);

		$ui = new \infolog_ui();
		$ui->ajax_action($this->execId(), 'startdate_ok_' . $when, [$this->info_id], false, []);

		$this->assertEquals($when, $this->infolog->read($this->info_id)['info_startdate'],
			'startdate_ok_<ts> must set the start date');
	}

	/**
	 * ...and the empty value the popup sends when its field was cleared must clear the date,
	 * rather than being read as a date of 0.
	 */
	public function testPopupActionCompositeIdClearsStartdate()
	{
		$this->makeInfolog(['info_startdate' => mktime(12, 0, 0, 6, 15, 2027)]);
		$this->assertNotEmpty($this->infolog->read($this->info_id)['info_startdate']);

		$ui = new \infolog_ui();
		$ui->ajax_action($this->execId(), 'startdate_ok_', [$this->info_id], false, []);

		$this->assertEmpty($this->infolog->read($this->info_id)['info_startdate']);
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
		$ui->ajax_action($this->execId(), 'close', [], true, []);

		$this->assertSame('ongoing', $this->infolog->read($this->info_id)['info_status'],
			'select-all with no cached query must NOT fall back to acting on everything');
	}

	/**
	 * "Select all" sends no ids, the server re-runs the list's cached query to find them. The
	 * client says how many entries its list shows, and the action must be refused - before
	 * anything is changed - when the server's own expansion comes out at a different number
	 * (Nextmatch::checkSelectAllCount()), because the user is then about to change entries they
	 * never saw selected.
	 *
	 * The cached query is narrowed to the one fixture entry, so the expansion finds exactly 1:
	 * claiming the list showed 40 must throw and leave the entry open, claiming 1 must close it.
	 */
	public function testInfologSelectAllIsRefusedWhenTheCountDoesNotMatchTheList()
	{
		$this->makeInfolog();
		Api\Cache::setSession('infolog', 'session_data', [
			'col_filter' => ['info_id' => $this->info_id], 'filter' => '', 'order' => 'info_id', 'sort' => 'ASC',
		]);
		$ui = new \infolog_ui();

		try
		{
			$ui->ajax_action($this->execId(), 'close', [], 40, []);
			$this->fail('a select-all expanding to 1 entry where the list showed 40 must be refused');
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			$this->assertStringContainsString('40', $e->getMessage());
		}
		$this->assertSame('ongoing', $this->infolog->read($this->info_id)['info_status'],
			'a refused select-all must not have changed anything');

		$ui->ajax_action($this->execId(), 'close', [], 1, []);
		$this->assertSame('done', $this->infolog->read($this->info_id)['info_status'],
			'a select-all whose count matches the list must still be carried out');
	}

	/**
	 * A view of the InfoLogs linked to something - a contact's CRM view - sends its actions to
	 * the same endpoint as the main list, but shows a different selection. "Select all" in it
	 * has to re-run the view's own query, stored per linked entry, which the endpoint can only
	 * find from the settings the page was rendered with.
	 *
	 * The main list's query here is narrowed to an id that is no InfoLog, so re-running it would
	 * close nothing. The count sent along (1) is a safety net: should a query ever not be
	 * narrowed to this test's entry, the server refuses to act instead of closing others.
	 */
	public function testInfologSelectAllInALinkedViewUsesThatViewsQuery()
	{
		$this->makeInfolog();
		$narrowed = static function(int $info_id) : array
		{
			return ['col_filter' => ['info_id' => $info_id], 'filter' => '', 'order' => 'info_id', 'sort' => 'ASC'];
		};
		Api\Cache::setSession('infolog', 'session_data', $narrowed(2147483600));
		Api\Cache::setSession('infolog', 'session_data-addressbook:2147483601', $narrowed($this->info_id));

		(new \infolog_ui())->ajax_action(
			$this->execId(['action' => 'addressbook', 'action_id' => 2147483601]), 'close', [], 1, []);

		$this->assertSame('done', $this->infolog->read($this->info_id)['info_status'],
			'select all must act on the entry the linked view showed');
		Api\Cache::unsetSession('infolog', 'session_data-addressbook:2147483601');
		Api\Cache::unsetSession('infolog', 'session_data');
	}

	/**
	 * A linked view stores its query under a name of its own, one per linked entry, and leaves
	 * alone what the main list stored: that is what the main list restores on the next visit.
	 */
	public function testInfologLinkedViewStoresItsQueryWithoutReplacingTheMainLists()
	{
		$main = ['col_filter' => [], 'filter' => 'own', 'order' => 'info_id', 'sort' => 'ASC'];
		Api\Cache::setSession('infolog', 'session_data', $main);

		$rows = $readonlys = null;
		$query = ['start' => 0, 'num_rows' => 1, 'col_filter' => [], 'filter' => '', 'search' => '',
			'order' => 'info_id', 'sort' => 'ASC', 'action' => 'addressbook', 'action_id' => 2147483601];
		(new \infolog_ui())->get_rows($query, $rows, $readonlys);

		$this->assertSame($main, Api\Cache::getSession('infolog', 'session_data'),
			'the main list\'s query must be left alone');
		$stored = Api\Cache::getSession('infolog', 'session_data-addressbook:2147483601');
		$this->assertIsArray($stored, 'the view\'s query is stored under its own name');
		$this->assertEquals(2147483601, $stored['action_id']);
		Api\Cache::unsetSession('infolog', 'session_data-addressbook:2147483601');
		Api\Cache::unsetSession('infolog', 'session_data');
	}

	/**
	 * The same refusal in addressbook, whose action() re-runs the query get_rows() cached under
	 * 'index'. The cached query is narrowed to the fixture contact (expansion finds 1): a client
	 * that saw 40 contacts must be refused with the category untouched, one that saw 1 must get
	 * its category added.
	 */
	public function testAddressbookSelectAllIsRefusedWhenTheCountDoesNotMatchTheList()
	{
		$this->makeContact(['cat_id' => '1']);
		Api\Cache::setSession('addressbook', 'index', [
			'col_filter' => ['contact_id' => $this->contact_id], 'order' => 'contact_id', 'sort' => 'ASC',
		]);
		$ui = new \addressbook_ui();

		try
		{
			$ui->ajax_action($this->execId(), 'cat_add_9', [], 40, []);
			$this->fail('a select-all expanding to 1 contact where the list showed 40 must be refused');
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			$this->assertStringContainsString('40', $e->getMessage());
		}
		$this->assertSame('1', $this->contacts->read($this->contact_id)['cat_id'],
			'a refused select-all must not have changed anything');

		$ui->ajax_action($this->execId(), 'cat_add_9', [], 1, []);
		$this->assertEqualsCanonicalizing(['1', '9'],
			explode(',', $this->contacts->read($this->contact_id)['cat_id']),
			'a select-all whose count matches the list must still be carried out');
	}

	/**
	 * An endpoint is told which list an action came from by the settings the page was rendered
	 * with, read through the exec id: listSetting() returns one of them, listQueryName() the name
	 * that list stores its query under - its session_for if it has one, else the app's default.
	 * Neither may invent a name for a request that is not known.
	 */
	public function testTheListAnActionCameFromIsReadFromThePagesSettings()
	{
		$portlet = $this->execId(['session_for' => 'home-phpunit', 'action_id' => 42]);
		$main = $this->execId();

		$this->assertSame('home-phpunit', Nextmatch::listSetting($portlet, 'session_for'));
		$this->assertSame(42, Nextmatch::listSetting($portlet, 'action_id'));
		$this->assertNull(Nextmatch::listSetting($portlet, 'not_a_setting'));
		$this->assertNull(Nextmatch::listSetting($main, 'session_for'));
		$this->assertNull(Nextmatch::listSetting('', 'session_for'), 'no exec id, no setting');

		$this->assertSame('home-phpunit', Nextmatch::listQueryName($portlet, 'index'));
		$this->assertSame('index', Nextmatch::listQueryName($main, 'index'));
		$this->assertSame('index', Nextmatch::listQueryName('', 'index'));
	}

	/**
	 * A contact list in a Home portlet stores its query under the portlet's own name and sends
	 * its actions to the same endpoint as the main list. "Select all" in it has to re-run the
	 * portlet's query, not the main list's.
	 *
	 * The main list's query here is narrowed to an id that is no contact, so re-running it would
	 * change nothing. The count sent along (1) is a safety net: should a query ever not be
	 * narrowed to this test's contact, the server refuses to act instead of changing others.
	 */
	public function testAddressbookSelectAllInAPortletUsesThePortletsQuery()
	{
		$this->makeContact(['cat_id' => '1']);
		$narrowed = static function(int $contact_id) : array
		{
			return ['col_filter' => ['contact_id' => $contact_id], 'order' => 'contact_id', 'sort' => 'ASC'];
		};
		Api\Cache::setSession('addressbook', 'index', $narrowed(2147483600));
		Api\Cache::setSession('addressbook', 'home-phpunit',
			$narrowed($this->contact_id) + ['session_for' => 'home-phpunit']);

		(new \addressbook_ui())->ajax_action($this->execId(['session_for' => 'home-phpunit']), 'cat_add_9', [], 1, []);

		$this->assertEqualsCanonicalizing(['1', '9'],
			explode(',', $this->contacts->read($this->contact_id)['cat_id']),
			'select all must act on the contact the portlet showed');
		Api\Cache::unsetSession('addressbook', 'home-phpunit');
		Api\Cache::unsetSession('addressbook', 'index');
	}

	/**
	 * A contact list shown in a portlet stores its query under the portlet's name and leaves
	 * alone what the main list stored: that is what the main list restores on the next visit.
	 */
	public function testAddressbookPortletStoresItsQueryWithoutReplacingTheMainLists()
	{
		$main = ['col_filter' => [], 'filter' => '', 'order' => 'n_family', 'sort' => 'ASC'];
		Api\Cache::setSession('addressbook', 'index', $main);

		$rows = $readonlys = null;
		$query = ['start' => 0, 'num_rows' => 1, 'col_filter' => [], 'filter' => '', 'search' => '',
			'order' => 'n_family', 'sort' => 'ASC', 'session_for' => 'home-phpunit'];
		(new \addressbook_ui())->get_rows($query, $rows, $readonlys);

		$this->assertSame($main, Api\Cache::getSession('addressbook', 'index'),
			'the main list\'s query must be left alone');
		$this->assertIsArray(Api\Cache::getSession('addressbook', 'home-phpunit'),
			'the portlet\'s query is stored under its own name');
		Api\Cache::unsetSession('addressbook', 'home-phpunit');
		Api\Cache::unsetSession('addressbook', 'index');
	}
}
