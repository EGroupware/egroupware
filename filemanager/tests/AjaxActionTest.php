<?php
/**
 * Test filemanager's ajax endpoints for the shares and jobs list context-menus
 *
 * @link https://www.egroupware.org
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Filemanager;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Vfs;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

/**
 * The Delete actions in filemanager's Shares and Jobs lists used to submit the whole eTemplate;
 * they now call an ajax endpoint that did not exist before (filemanager_shares::ajax_delete() and
 * Filemanager\Jobs::ajax_action()).
 *
 * WHY THIS EXISTS
 * Both endpoints were written and shipped without ever being executed: the conversion was checked
 * by driving actions with a non-existent row id, which short-circuits before the handler body
 * runs. That let a PHP 8 fatal through elsewhere in the same work (addressbook's cat_set), so
 * these get a real row.
 *
 * SETUP
 * Each test creates its own share/job and removes it again. The jobs list lives in the filemanager
 * app config rather than its own table, so the whole 'jobs' config value is snapshotted and put
 * back in tearDown - a failed assertion must not leave a stray job behind, nor lose real ones.
 *
 * PASS CRITERIA
 * The row is really gone afterwards (read back from its own storage), and the response carries an
 * egw.refresh call - without which the list would not update.
 */
class AjaxActionTest extends LoggedInTest
{
	protected $share_id;
	protected $job_id;
	protected $jobs_before;
	protected $tmp_path;
	/** @var string[] share_id => path of shares made by makeShare() */
	protected $extra_shares = [];

	protected function setUp() : void
	{
		Api\Json\Response::get()->initResponseArray();
		// the jobs list is a config value, not a table - keep the real one to put back
		$this->jobs_before = Api\Config::read('filemanager')['jobs'] ?? null;
	}

	protected function tearDown() : void
	{
		if ($this->share_id)
		{
			Api\Vfs\Sharing::delete(['share_id' => $this->share_id]);
			$this->share_id = null;
		}
		if ($this->job_id !== null)
		{
			// put the real jobs config back exactly as it was
			Api\Config::save_value('jobs', $this->jobs_before, 'filemanager');
			$this->job_id = null;
		}
		foreach($this->extra_shares as $share_id => $path)
		{
			Api\Vfs\Sharing::delete(['share_id' => $share_id]);
			if (Vfs::file_exists($path)) Vfs::unlink($path);
		}
		$this->extra_shares = [];
		Api\Cache::unsetSession('filemanager', \filemanager_shares::SELECT_ALL_QUERY);
		if ($this->tmp_path && Vfs::file_exists($this->tmp_path))
		{
			Vfs::unlink($this->tmp_path);
			$this->tmp_path = null;
		}
	}

	/**
	 * A real eTemplate request id, the way the browser sends one along - the endpoints refuse
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
	 * filemanager_shares::ajax_delete() - deliberately NOT called ajax_action(), because this class
	 * extends filemanager_ui whose ajax_action() is a STATIC VFS endpoint; redeclaring it
	 * non-static there is an instant PHP fatal. This pins that it stayed separate and works.
	 */
	public function testSharesDeleteRemovesTheShare()
	{
		$this->tmp_path = '/home/' . $GLOBALS['egw_info']['user']['account_lid'] . '/ajaxactiontest.txt';
		// Vfs has no file_put_contents() of its own - its stream wrapper is used through the
		// normal PHP function with the vfs:// prefix
		$this->assertNotFalse(file_put_contents(Vfs::PREFIX . $this->tmp_path, 'AjaxActionTest'),
			'could not create the file to share');

		$this->share_id = Api\Vfs\Sharing::create('', $this->tmp_path, Api\Vfs\Sharing::READONLY, '', '')['share_id']
			?? null;
		$this->assertNotNull($this->share_id, 'could not create the test share');
		$this->assertNotEmpty($this->readShare($this->share_id), 'share was not created');

		$ui = new \filemanager_shares();
		$ui->ajax_delete($this->execId(), 'delete', [$this->share_id], false);

		$this->assertEmpty($this->readShare($this->share_id), 'the share must be gone');
		$this->assertNotNull($this->refreshCall(),
			'the endpoint must answer with egw.refresh, or the list updates no rows');
		$this->share_id = null;		// already deleted
	}

	/**
	 * The refusal is the half that silently does nothing when it regresses: an endpoint that
	 * stops validating still passes every test about what it does on success.
	 */
	public function testSharesABogusExecIdDeletesNothing()
	{
		$this->tmp_path = '/home/' . $GLOBALS['egw_info']['user']['account_lid'] . '/ajaxactiontest_bogus.txt';
		$this->assertNotFalse(file_put_contents(Vfs::PREFIX . $this->tmp_path, 'AjaxActionTest'),
			'could not create the file to share');
		$this->share_id = Api\Vfs\Sharing::create('', $this->tmp_path, Api\Vfs\Sharing::READONLY, '', '')['share_id']
			?? null;
		$this->assertNotNull($this->share_id, 'could not create the test share');

		(new \filemanager_shares())->ajax_delete('filemanager_nobody_not-a-real-request-id',
			'delete', [$this->share_id], false);

		$this->assertNotEmpty($this->readShare($this->share_id),
			'a rejected request must not run the action');
		$this->assertNull($this->refreshCall(), 'and must not answer with egw.refresh either');
	}

	/**
	 * Only 'delete' is supported - anything else must be refused, not silently treated as one.
	 */
	public function testSharesRejectsAnUnknownAction()
	{
		$this->expectException(\EGroupware\Api\Exception\WrongParameter::class);
		(new \filemanager_shares())->ajax_delete($this->execId(), 'something_else', [1], false);
	}

	protected function readShare($share_id)
	{
		$rs = $GLOBALS['egw']->db->select(Api\Vfs\Sharing::TABLE, 'share_id',
			['share_id' => $share_id], __LINE__, __FILE__, false, '', Api\Db::API_APPNAME);
		return $rs->fetchColumn();
	}

	/**
	 * Filemanager\Jobs::ajax_action() - a new endpoint, and its action() throws for an unknown
	 * action, so the endpoint has to catch that rather than let it escape as a 500.
	 */
	public function testJobsDeleteRemovesTheJob()
	{
		$jobs = Api\Config::read('filemanager')['jobs'] ?? [];
		$this->job_id = 'ajaxactiontest-' . microtime(true);
		$jobs[$this->job_id] = [
			'id' => $this->job_id,
			'name' => 'AjaxActionTest',
			'creator' => $GLOBALS['egw_info']['user']['account_id'],
		];
		Api\Config::save_value('jobs', $jobs, 'filemanager');
		$this->assertArrayHasKey($this->job_id, Api\Config::read('filemanager')['jobs'],
			'could not create the test job');

		(new Jobs())->ajax_action($this->execId(), 'delete', [$this->job_id], false);

		$this->assertArrayNotHasKey($this->job_id, Api\Config::read('filemanager')['jobs'] ?? [],
			'the job must be gone');
		$this->assertNotNull($this->refreshCall());
	}

	/**
	 * action() throws for an unknown action; ajax_action() must turn that into an error message
	 * rather than an uncaught exception.
	 */
	public function testJobsUnknownActionIsReportedNotThrown()
	{
		$this->job_id = null;
		(new Jobs())->ajax_action($this->execId(), 'not_an_action', ['whatever'], false);

		$parms = $this->refreshCall();
		$this->assertNotNull($parms, 'even a failure has to answer the client');
		$this->assertSame('error', end($parms), 'and it has to say it failed');
	}

	/**
	 * A share on a file of its own, removed again in tearDown via $extra_shares
	 */
	protected function makeShare(string $name) : int
	{
		$path = '/home/' . $GLOBALS['egw_info']['user']['account_lid'] . '/' . $name;
		$this->assertNotFalse(file_put_contents(Vfs::PREFIX . $path, 'AjaxActionTest'),
			'could not create the file to share');
		$share_id = Api\Vfs\Sharing::create('', $path, Api\Vfs\Sharing::READONLY, '', '')['share_id'] ?? null;
		$this->assertNotNull($share_id, 'could not create the test share');
		$this->extra_shares[(int)$share_id] = $path;
		return (int)$share_id;
	}

	/**
	 * "Select all" deletes the shares the list is showing - not every share of the user, which
	 * is what deleting by owner alone did, whatever the list was filtered to.
	 *
	 * The count the client sends along (1) is there as a safety net: should the expansion ever
	 * ignore the filter, it would find more than the one share and refuse to act.
	 */
	public function testSharesSelectAllDeletesOnlyWhatTheListShows()
	{
		$match = $this->makeShare('ajaxactiontest_all.txt');
		$control = $this->makeShare('ajaxactiontest_control.txt');

		$ui = new \filemanager_shares();
		$rows = null;
		$query = ['start' => 0, 'num_rows' => 25, 'search' => '', 'col_filter' => ['share_id' => $match]];
		$ui->get_rows($query, $rows);
		$this->assertSame([$match], array_map('intval', array_column($rows, 'share_id')),
			'the filter must find exactly the one share');

		$ui->ajax_delete($this->execId(), 'delete', [], 1);

		$this->assertEmpty($this->readShare($match), 'select all must delete the share the list showed');
		$this->assertNotEmpty($this->readShare($control), 'and must not touch one it did not show');
	}

	/**
	 * The nextmatch re-queries single rows on its own (a refresh after a delete, a check of the
	 * rows it knows). Those queries are narrowed to a few ids; were they stored as "what the list
	 * shows", the next "select all" would act on just those rows.
	 */
	public function testSharesARowRefreshDoesNotReplaceTheCachedQuery()
	{
		$ui = new \filemanager_shares();
		$rows = null;
		$query = ['start' => 0, 'num_rows' => 25, 'search' => 'ajaxactiontest', 'col_filter' => []];
		$ui->get_rows($query, $rows);
		$cached = Api\Cache::getSession('filemanager', \filemanager_shares::SELECT_ALL_QUERY);
		$this->assertSame('ajaxactiontest', $cached['search'] ?? null, 'the list query is stored');

		$refresh = ['start' => 0, 'num_rows' => 25, 'search' => '', 'col_filter' => ['share_id' => 1],
			'csv_export' => 'refresh'];
		$ui->get_rows($refresh, $rows);

		$this->assertSame($cached, Api\Cache::getSession('filemanager', \filemanager_shares::SELECT_ALL_QUERY),
			'a single-row refresh must not replace it');
	}

	/**
	 * With nothing cached there is no way to know what the user was looking at - so "select all"
	 * has to refuse, where it used to delete every share the user owns.
	 */
	public function testSharesSelectAllRefusesWithoutACachedQuery()
	{
		$share_id = $this->makeShare('ajaxactiontest_nocache.txt');
		Api\Cache::unsetSession('filemanager', \filemanager_shares::SELECT_ALL_QUERY);

		try
		{
			(new \filemanager_shares())->ajax_delete($this->execId(), 'delete', [], true);
			$this->fail('select all without a cached query must be refused');
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			$this->assertNotEmpty($this->readShare($share_id), 'nothing may be deleted without a cached query');
		}
	}
}
