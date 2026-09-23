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
		if ($this->tmp_path && Vfs::file_exists($this->tmp_path))
		{
			Vfs::unlink($this->tmp_path);
			$this->tmp_path = null;
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
		$ui->ajax_delete('delete', [$this->share_id], false);

		$this->assertEmpty($this->readShare($this->share_id), 'the share must be gone');
		$this->assertNotNull($this->refreshCall(),
			'the endpoint must answer with egw.refresh, or the list updates no rows');
		$this->share_id = null;		// already deleted
	}

	/**
	 * Only 'delete' is supported - anything else must be refused, not silently treated as one.
	 */
	public function testSharesRejectsAnUnknownAction()
	{
		$this->expectException(\EGroupware\Api\Exception\WrongParameter::class);
		(new \filemanager_shares())->ajax_delete('something_else', [1], false);
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

		(new Jobs())->ajax_action('delete', [$this->job_id], false);

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
		(new Jobs())->ajax_action('not_an_action', ['whatever'], false);

		$parms = $this->refreshCall();
		$this->assertNotNull($parms, 'even a failure has to answer the client');
		$this->assertSame('error', end($parms), 'and it has to say it failed');
	}
}
