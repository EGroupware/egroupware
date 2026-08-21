<?php
/**
 * EGroupware Api: regression test for Link\Sharing::create()'s ownership guard
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Acl;
use EGroupware\Api\Link\Sharing;
use EGroupware\Api\Storage\Base;

require_once realpath(__DIR__.'/../AppTest.php');

/**
 * Before the fix, Link\Sharing::create() (used whenever no app-specific Sharing class or
 * EPL Stylite\Link\Sharing exists) inserted a share row + token for an arbitrary "app::id"
 * entry path with zero read/ownership verification - unlike its sibling Vfs\Sharing::create(),
 * which validates via Vfs::check_access() before persisting a share.
 *
 * This uses timesheet entries as the target app, since timesheet_bo registers a Link
 * file_access hook and its ownership semantics are well understood from
 * TimesheetBoTest.
 */
class LinkSharingTest extends \EGroupware\Api\AppTest
{
	/** @var int|null ts_id created by the current test, cleaned up in tearDown */
	private $ts_id;

	protected function tearDown(): void
	{
		if ($this->ts_id)
		{
			(new Base('timesheet', 'egw_timesheet'))->delete(array('ts_id' => $this->ts_id));

			// clean up any share row created for it, in case a test unexpectedly succeeded
			(new Base('api', 'egw_sharing'))->delete(array('share_path' => 'timesheet::'.$this->ts_id));
			(new Base('api', 'egw_sharing'))->delete(array('share_path' => '/apps/timesheet/'.$this->ts_id));

			$this->ts_id = null;
		}
	}

	private function createTimesheet(int $owner): int
	{
		$so = new Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_sharing_'.bin2hex(random_bytes(6)),
			'ts_start'    => time(),
			'ts_duration' => 60,
			'ts_quantity' => 1.0,
			'ts_owner'    => $owner,
			'ts_created'  => time(),
			'ts_modified' => time(),
			'ts_modifier' => $owner,
		);
		$so->save();

		return (int)$so->data['ts_id'];
	}

	/**
	 * Pass criteria: create() throws (NoPermission) instead of inserting a share row, for
	 * an entry the caller has no read access to.
	 */
	public function testCreateRejectsEntryWithoutReadAccess()
	{
		// an account id the current test-user has no ACL grant for
		$this->ts_id = $this->createTimesheet(999999);
		$path = 'timesheet::'.$this->ts_id;

		try
		{
			Sharing::create('shareReadonlyLink', $path, Sharing::READONLY, $path, array());
			$this->fail('create() must reject sharing an entry the caller can not read');
		}
		catch (\Exception $e)
		{
			// expected
		}

		$row = (new Base('api', 'egw_sharing'))->read(array('share_path' => $path));
		$this->assertEmpty($row, 'no share row must be created for an unreadable entry');
	}

	/**
	 * Pass criteria: create() succeeds and returns a share token for an entry the caller
	 * does have read access to (their own timesheet entry).
	 */
	public function testCreateAllowsOwnEntry()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$this->ts_id = $this->createTimesheet($account_id);
		$path = 'timesheet::'.$this->ts_id;

		$share = Sharing::create('shareReadonlyLink', $path, Sharing::READONLY, $path, array());

		$this->assertNotEmpty($share['share_token'] ?? null, 'a share token must be created for a readable entry');

		(new Base('api', 'egw_sharing'))->delete(array('share_path' => $path));
	}

	/**
	 * Storage\Merge::create_share() uses a "/apps/$app/$id" path (not "app::id") for its
	 * "-files_only" share variant (see Merge.php's $$share-files_only$$ placeholder handling).
	 * Regression: create()'s path parsing must recognize this format too, not just reject it.
	 */
	public function testCreateAllowsOwnEntryViaAppsPathFormat()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$this->ts_id = $this->createTimesheet($account_id);
		$path = '/apps/timesheet/'.$this->ts_id;

		$share = Sharing::create('', $path, Sharing::READONLY, null, array());

		$this->assertNotEmpty($share['share_token'] ?? null, 'a share token must be created for a readable entry');

		(new Base('api', 'egw_sharing'))->delete(array('share_path' => $path));
	}

	/**
	 * Same "/apps/$app/$id" path format, but for an entry the caller can not read - must
	 * still be rejected, not silently treated as unparsable-and-allowed.
	 */
	public function testCreateRejectsEntryWithoutReadAccessViaAppsPathFormat()
	{
		$this->ts_id = $this->createTimesheet(999999);
		$path = '/apps/timesheet/'.$this->ts_id;

		try
		{
			Sharing::create('', $path, Sharing::READONLY, null, array());
			$this->fail('create() must reject sharing an entry the caller can not read');
		}
		catch (\Exception $e)
		{
			// expected
		}

		$row = (new Base('api', 'egw_sharing'))->read(array('share_path' => $path));
		$this->assertEmpty($row, 'no share row must be created for an unreadable entry');
	}
}
