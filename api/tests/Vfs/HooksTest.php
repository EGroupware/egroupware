<?php

/**
 * Test that Vfs\StreamWrapper's notification hooks (vfs_read/added/modified/
 * pre-write/unlink/rename/mkdir/rmdir) actually fire with the right data, and
 * that filemanager_hooks::vfs_hooks()/push() - the REAL client-notification
 * pipeline, corrected finding in doc/ai/projects/vfs-test-coverage.md's
 * Phase 5 - filters/targets pushes correctly. Phase 5 of the
 * vfs-test-coverage project.
 *
 * Two testing seams, neither requiring a live push server or DB writes:
 * - Api\Hooks::$locations (protected static) is spliced via reflection to
 *   add a spy hook alongside the real, already-registered ones for the
 *   duration of each test, restored in tearDown().
 * - Api\Json\Push::$backend (protected static) is pre-set via reflection to
 *   $this (this class implements Api\Json\PushBackend) BEFORE any push
 *   happens, so Push::checkSetBackend()'s "only set if not already set"
 *   guard skips trying to reach a real backend entirely.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/../LoggedInTest.php';
require_once __DIR__ . '/../../../admin/inc/class.admin_cmd_edit_group.inc.php';
require_once __DIR__ . '/../../../admin/inc/class.admin_cmd_delete_account.inc.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;

class HooksTest extends LoggedInTest implements Api\Json\PushBackend
{
	/**
	 * @var string root of a scratch sqlfs:// area, NOT under /home - see
	 * FacadeTest for why (doc/ai/projects/vfs-test-coverage.md Phase 1/6)
	 */
	protected $test_root;

	/**
	 * @var string[] paths to clean up in tearDown(), in creation order
	 */
	protected $files = [];

	protected $original_hook_locations;
	protected $original_push_backend;

	/**
	 * @var array location => list of Api\Hooks::process() $args arrays
	 */
	public static $captured_hooks = [];

	/**
	 * @var array list of ['account_id' => ..., 'key' => ..., 'data' => ...]
	 * from Api\Json\Push::addGeneric() calls, captured via this class
	 * acting as the push backend (see class docblock)
	 */
	public $push_calls = [];

	public static function spyHook(array $args) : bool
	{
		self::$captured_hooks[$args['location']][] = $args;
		return true;
	}

	// --- Api\Json\PushBackend ---

	public function addGeneric($account_id, $key, $data)
	{
		$this->push_calls[] = ['account_id' => $account_id, 'key' => $key, 'data' => $data];
	}

	public function online() : array
	{
		return [];
	}

	protected function setUp() : void
	{
		parent::setUp();

		if(!isset($GLOBALS['egw_info']['user']['apps']['filemanager']))
		{
			$this->markTestSkipped('Test user has no filemanager app rights - needed for vfs_pre-write, ' .
				'the one vfs_* hook whose Api\Hooks::process() call does NOT bypass the per-app permission check');
		}

		self::$captured_hooks = [];
		$this->push_calls = [];

		// force Api\Hooks::$locations to be populated before we snapshot/splice it
		Api\Hooks::process('never_registered_probe_location_xyz');

		$hooks_prop = new \ReflectionProperty(Api\Hooks::class, 'locations');
		$hooks_prop->setAccessible(true);
		$this->original_hook_locations = $hooks_prop->getValue();

		$push_prop = new \ReflectionProperty(Api\Json\Push::class, 'backend');
		$push_prop->setAccessible(true);
		$this->original_push_backend = $push_prop->getValue();

		$this->spliceHookAndPushSpies();

		$this->test_root = '/hookstest_' . substr(md5(uniqid('', true)), 0, 8);
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		Vfs::mount('sqlfs://default' . $this->test_root, $this->test_root, false, false);
		Vfs::clearstatcache();
		Vfs::mkdir($this->test_root);
		Vfs::chown($this->test_root, $GLOBALS['egw_info']['user']['account_id']);
		Vfs::chmod($this->test_root, 0750);
		Vfs::$is_root = $backup;
		Vfs::clearstatcache();

		// clear anything captured by the fixture setup itself
		self::$captured_hooks = [];
		$this->push_calls = [];
	}

	/**
	 * Splice the spy hook into Api\Hooks::$locations and swap in the fake Push
	 * backend. Extracted from setUp() purely for reuse; Api\Hooks::$locations
	 * and Api\Json\Push::$backend are both plain static class properties, NOT
	 * tied to $GLOBALS['egw'] - confirmed (via temporary debug instrumentation,
	 * not shipped) that an asAdmin()/switchUser() round-trip does NOT reset
	 * either one, so a single splice in setUp() covers the whole test.
	 */
	protected function spliceHookAndPushSpies() : void
	{
		// force Api\Hooks::$locations to be populated before we snapshot/splice it
		Api\Hooks::process('never_registered_probe_location_xyz');

		$hooks_prop = new \ReflectionProperty(Api\Hooks::class, 'locations');
		$hooks_prop->setAccessible(true);
		$locations = $hooks_prop->getValue();
		foreach(['vfs_read', 'vfs_added', 'vfs_modified', 'vfs_pre-write', 'vfs_unlink', 'vfs_rename', 'vfs_mkdir', 'vfs_rmdir'] as $loc)
		{
			if(!in_array(self::class . '::spyHook', $locations[$loc]['filemanager'] ?? [], true))
			{
				$locations[$loc]['filemanager'][] = self::class . '::spyHook';
			}
		}
		$hooks_prop->setValue(null, $locations);

		$push_prop = new \ReflectionProperty(Api\Json\Push::class, 'backend');
		$push_prop->setAccessible(true);
		$push_prop->setValue(null, $this);
	}

	protected function tearDown() : void
	{
		$hooks_prop = new \ReflectionProperty(Api\Hooks::class, 'locations');
		$hooks_prop->setAccessible(true);
		$hooks_prop->setValue(null, $this->original_hook_locations);

		$push_prop = new \ReflectionProperty(Api\Json\Push::class, 'backend');
		$push_prop->setAccessible(true);
		$push_prop->setValue(null, $this->original_push_backend);

		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		foreach(array_reverse($this->files) as $file)
		{
			if(Vfs::is_dir($file) && !Vfs::is_link($file))
			{
				Vfs::remove($file);
			}
			elseif(Vfs::file_exists($file) || Vfs::is_link($file))
			{
				Vfs::unlink($file);
			}
		}
		if($this->test_root)
		{
			Vfs::remove($this->test_root);
			Vfs::umount($this->test_root);
		}
		Vfs::$is_root = $backup;
		parent::tearDown();
	}

	protected function getFilename($suffix = '') : string
	{
		$reflect = new \ReflectionClass($this);
		return $this->test_root . '/' . $reflect->getShortName() . '_' . $this->name() . $suffix;
	}

	/**
	 * Find the captured push whose 'id' matches $path, across all
	 * addGeneric() calls in $this->push_calls (filemanager_hooks::push()
	 * calls Push::apply("egw.push", [[...single push array...]])).
	 */
	protected function findPushFor(string $path) : ?array
	{
		foreach($this->push_calls as $call)
		{
			$push = $call['data']['parms'][0] ?? null;
			if(is_array($push) && ($push['id'] ?? null) === $path)
			{
				return ['account_id' => $call['account_id'], 'data' => $push];
			}
		}
		return null;
	}

	// ---------------------------------------------------------------
	// Part A: direct vfs_* hook firing (Vfs\StreamWrapper)
	// ---------------------------------------------------------------

	public function testMkdirFiresVfsHookWithPathAndUrl() : void
	{
		$dir = $this->getFilename('_mkdir');
		Vfs::mkdir($dir);

		$this->assertCount(1, self::$captured_hooks['vfs_mkdir'] ?? []);
		$this->assertEquals($dir, self::$captured_hooks['vfs_mkdir'][0]['path']);
	}

	public function testWriteFiresAddedThenModifiedThenRead() : void
	{
		$file = $this->files[] = $this->getFilename();

		file_put_contents(Vfs::PREFIX . $file, 'first write');
		$this->assertCount(1, self::$captured_hooks['vfs_added'] ?? []);
		$this->assertArrayNotHasKey('vfs_modified', self::$captured_hooks);

		file_put_contents(Vfs::PREFIX . $file, 'second write');
		$this->assertCount(1, self::$captured_hooks['vfs_modified'] ?? []);

		file_get_contents(Vfs::PREFIX . $file);
		$this->assertCount(1, self::$captured_hooks['vfs_read'] ?? []);
	}

	public function testUnlinkFiresVfsHookWithStat() : void
	{
		$file = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');
		self::$captured_hooks = [];

		Vfs::unlink($file);

		$this->assertCount(1, self::$captured_hooks['vfs_unlink'] ?? []);
		$this->assertEquals($file, self::$captured_hooks['vfs_unlink'][0]['path']);
		$this->assertIsArray(self::$captured_hooks['vfs_unlink'][0]['stat']);
	}

	public function testRenameFiresVfsHookWithFromAndTo() : void
	{
		$from = $this->getFilename('_from');
		$to = $this->files[] = $this->getFilename('_to');
		file_put_contents(Vfs::PREFIX . $from, 'x');
		self::$captured_hooks = [];

		Vfs::rename($from, $to);

		$this->assertCount(1, self::$captured_hooks['vfs_rename'] ?? []);
		$this->assertEquals($from, self::$captured_hooks['vfs_rename'][0]['from']);
		$this->assertEquals($to, self::$captured_hooks['vfs_rename'][0]['to']);
	}

	public function testRmdirFiresVfsHook() : void
	{
		$dir = $this->getFilename('_rmdir');
		Vfs::mkdir($dir);
		self::$captured_hooks = [];

		Vfs::rmdir($dir);

		$this->assertCount(1, self::$captured_hooks['vfs_rmdir'] ?? []);
		$this->assertEquals($dir, self::$captured_hooks['vfs_rmdir'][0]['path']);
	}

	/**
	 * Real finding: vfs_pre-write is the ONLY vfs_* hook whose
	 * Api\Hooks::process() call does NOT pass no_permission_check=true
	 * (StreamWrapper.php:288, vs :246,452,518,579,628 for all the others) -
	 * so unlike every other vfs hook, it only fires for an app the current
	 * user actually has run-rights to. No consumer is registered for it
	 * anywhere in the codebase today, so this has zero observable effect
	 * currently, but it's a real inconsistency worth knowing about if
	 * anyone ever wires a vfs_pre-write consumer up.
	 */
	public function testPreWriteFiresVfsHook() : void
	{
		$file = $this->files[] = $this->getFilename();

		file_put_contents(Vfs::PREFIX . $file, 'x');

		$this->assertNotEmpty(self::$captured_hooks['vfs_pre-write'] ?? []);
		$this->assertEquals($file, self::$captured_hooks['vfs_pre-write'][0]['path']);
	}

	// ---------------------------------------------------------------
	// Part B: filemanager_hooks::vfs_hooks()/push() - the real
	// client-notification pipeline
	// ---------------------------------------------------------------

	public function testZeroSizeFileWriteDoesNotPush() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, '');

		$this->assertEmpty($this->push_calls, 'A zero-size file write should not trigger a client push');
	}

	public function testNonEmptyFileWriteBroadcastsForNonHomePath() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'not empty');

		$push = $this->findPushFor($file);
		$this->assertNotNull($push, 'Expected a push for ' . $file);
		$this->assertSame(Api\Json\Push::ALL, $push['account_id']);
		$this->assertEquals('add', $push['data']['type']);
	}

	public function testTempAndLockFilesAreFiltered() : void
	{
		foreach(['~$locked.docx', '.~lock.foo#', 'something.tmp'] as $name)
		{
			$file = $this->files[] = $this->test_root . '/' . $name;
			file_put_contents(Vfs::PREFIX . $file, 'not empty');
		}

		$this->assertEmpty($this->push_calls, 'Temp/lock files should never trigger a client push');
	}

	public function testRenameTriggersDeleteThenAddPush() : void
	{
		$from = $this->getFilename('_from');
		$to = $this->files[] = $this->getFilename('_to');
		file_put_contents(Vfs::PREFIX . $from, 'x');
		$this->push_calls = [];

		Vfs::rename($from, $to);

		$delete = $this->findPushFor($from);
		$this->assertNotNull($delete, 'Expected an extra delete-push for the old path');
		$this->assertEquals('delete', $delete['data']['type']);

		$add = $this->findPushFor($to);
		$this->assertNotNull($add, 'Expected an add-push for the new path');
		$this->assertEquals('add', $add['data']['type']);
	}

	public function testHomeDirWriteTargetsOwnerNotBroadcast() : void
	{
		$home_root = '/home/hookstest_' . substr(md5(uniqid('', true)), 0, 8);
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		Vfs::mount('sqlfs://default' . $home_root, $home_root, false, false);
		Vfs::clearstatcache();
		Vfs::mkdir($home_root);
		Vfs::chown($home_root, $GLOBALS['egw_info']['user']['account_id']);
		Vfs::chmod($home_root, 0750);
		Vfs::$is_root = $backup;
		Vfs::clearstatcache();

		try
		{
			$file = $home_root . '/homefile.txt';
			file_put_contents(Vfs::PREFIX . $file, 'not empty');

			$push = $this->findPushFor($file);
			$this->assertNotNull($push, 'Expected a push for ' . $file);
			$this->assertNotSame(Api\Json\Push::ALL, $push['account_id'],
				'A /home/ path should target owner(+group), not broadcast to everyone');
			$this->assertContains($GLOBALS['egw_info']['user']['account_id'], (array)$push['account_id']);
		}
		finally
		{
			Vfs::$is_root = true;
			Vfs::remove($home_root);
			Vfs::umount($home_root);
			Vfs::$is_root = $backup;
		}
	}

	/**
	 * Real bug found+fixed while investigating this: filemanager_hooks::push()
	 * built the group-home-dir recipient list via
	 * `$account_id[] = $stat['uid']; ... $account_id += $accounts->members(...)`
	 * - `+=` is array UNION, which keeps the LEFT array's value at any
	 * colliding key. Since `members(..., $just_id=true)` returns a plain
	 * 0-indexed array (array_keys() of the membership map) and the owner was
	 * placed at index 0 first, that union silently dropped whichever member
	 * happened to occupy index 0 of members()'s result - NOT "no push to a
	 * group" (there never was one; membership is correctly expanded to
	 * individual account_ids), but a real, empirically-reproducible dropped
	 * recipient. Fixed via array_merge()+array_unique() instead of +=.
	 *
	 * This test creates a real, disposable group (with the current test user
	 * plus 2 throwaway member accounts - created via bare Api\Accounts::save()
	 * with NO password, so account creation never touches
	 * Auth::change_password()'s mail/Stalwart hook chain at all) via the same
	 * admin_cmd_edit_group path a real "create group" admin action uses, writes
	 * into that group's home dir, and asserts every member - not just most of
	 * them - appears in the push recipient list. See the write step below for
	 * why it's done as root rather than as the member test user.
	 */
	public function testHomeDirGroupWriteTargetsAllMembersNotJustOwner() : void
	{
		$suffix = substr(md5(uniqid('', true)), 0, 8);
		$accounts = $GLOBALS['egw']->accounts;
		$current_id = $GLOBALS['egw_info']['user']['account_id'];

		$group_lid = 'hookstestgroup' . $suffix;
		$group_dir = '/home/' . $group_lid;
		$is_root_backup = Vfs::$is_root;

		$group_id = null;
		$member_ids = [];
		$this->asAdmin(function() use (&$group_id, &$member_ids, $suffix, $group_lid, $accounts, $current_id)
		{
			foreach(['a', 'b'] as $letter)
			{
				$data = [
					'account_type'      => 'u',
					'account_lid'       => 'hookstestmember' . $letter . $suffix,
					'account_firstname' => 'Member' . $letter,
					'account_lastname'  => 'Test',
					'account_status'    => 'A',
					// -1 = never expires; omitting this defaults to 0, which
					// Accounts::is_expired() treats as ALREADY expired, silently
					// excluding the account from members(..., $active=true)
					'account_expires'   => -1,
				];
				$member_ids[] = $accounts->save($data);
			}

			$cmd = new \admin_cmd_edit_group(false, [
				'account_lid'     => $group_lid,
				'account_members' => array_merge([$current_id], $member_ids),
			]);
			$cmd->run();
			$group_id = $cmd->account;
		});
		$this->assertNotEmpty($group_id, 'Failed to create the test group');

		// Mount a scratch sqlfs:// backend over the group's home dir ONLY NOW, as the very
		// last step before writing: Vfs::mount(..., $persistent_mount=false) is transient
		// and gets silently wiped by any Vfs\StreamWrapper::init_static() call that happens
		// afterward (a known gotcha - see project_vfs_test_coverage.md) - and
		// admin_cmd_edit_group()/asAdmin()'s account-creation cascade above does trigger
		// one (confirmed: mounting BEFORE that block left Vfs::resolve_url($group_dir)
		// back on the default stylite.s3:// "/" mount by the time of the write, not the
		// scratch mount at all). Re-creating the dir here mirrors Vfs\Hooks::addGroup()'s
		// own mkdir()/chown()/chgrp()/chmod() convention, just against sqlfs instead of the
		// Versioning-backed default, to avoid that backend's own write-visibility timing.
		Vfs::$is_root = true;
		Vfs::mount('sqlfs://default' . $group_dir, $group_dir, false, false);
		Vfs::clearstatcache();
		Vfs::mkdir($group_dir);
		Vfs::chown($group_dir, 0);
		Vfs::chgrp($group_dir, abs($group_id));
		Vfs::chmod($group_dir, 0070);
		Vfs::$is_root = $is_root_backup;
		Vfs::clearstatcache();

		try
		{
			// Group directories get mode 0070 (group-only, NO owner bits - matching
			// Vfs\Hooks::addGroup()'s own convention, api/src/Vfs/Hooks.php:139), so a
			// freshly-created file inherits mode 0060: only the GROUP gets read/write,
			// the creator's OWNER bits are zero. Standard Unix permission semantics check
			// OWNER bits exclusively once uid matches - never falling through to the group
			// bits even though the creator (demo) is ALSO a group member - so demo can't
			// Vfs::stat() their own file immediately after creating it. That's exactly what
			// filemanager_hooks::vfs_hooks()'s own internal Vfs::stat($path) call hits too,
			// making it treat a perfectly real write as indistinguishable from a genuine
			// zero-size one (see testZeroSizeFileWriteDoesNotPush() above) and skip the
			// push entirely - a second, real, separate finding, NOT the array-union bug
			// this test targets. Do the write as root to bypass that mode check outright;
			// push()'s group-membership expansion (the actual thing under test) still
			// correctly includes demo via their group membership regardless of whose uid
			// ends up on the file.
			$file = $group_dir . '/groupfile.txt';
			Vfs::$is_root = true;
			file_put_contents(Vfs::PREFIX . $file, 'group content');
			Vfs::$is_root = $is_root_backup;

			$push = $this->findPushFor($file);
			$this->assertNotNull($push, 'Expected a push for ' . $file);
			$this->assertNotSame(Api\Json\Push::ALL, $push['account_id'],
				'A group /home/ path should target members, not broadcast to everyone');

			$expected = array_unique(array_merge([$current_id], $member_ids));
			sort($expected);
			$actual = array_unique((array)$push['account_id']);
			sort($actual);
			$this->assertEquals($expected, $actual,
				'Every group member must be notified - none silently dropped by the array-union bug');
		}
		finally
		{
			// Hard-delete the scratch mount's own rows FIRST, directly, while it's
			// confirmed still mounted: asAdmin() below does its own switchUser() round-trip,
			// which (same as the mount-timing issue above) can wipe this transient mount
			// before Vfs\Hooks::deleteGroup()'s own Vfs::remove('/home/'.$lid) call gets a
			// chance to use it - leaving these rows permanently orphaned under a backend
			// nothing points at anymore (confirmed empirically: they survived every earlier
			// attempt that relied on that hook alone for cleanup).
			Vfs::$is_root = true;
			$dir_stat = Vfs::stat($group_dir);
			if($dir_stat)
			{
				$rs = $GLOBALS['egw']->db->select('egw_sqlfs', 'fs_id', ['fs_dir' => $dir_stat['ino']], __LINE__, __FILE__);
				while($row = $rs->fetch())
				{
					$GLOBALS['egw']->db->delete('egw_sqlfs', ['fs_id' => $row['fs_id']], __LINE__, __FILE__);
				}
				$GLOBALS['egw']->db->delete('egw_sqlfs', ['fs_id' => $dir_stat['ino']], __LINE__, __FILE__);
			}
			Vfs::umount($group_dir);
			Vfs::$is_root = $is_root_backup;
			Vfs::clearstatcache();

			$this->asAdmin(function() use ($group_id, $member_ids)
			{
				if($group_id)
				{
					(new \admin_cmd_delete_account($group_id, null, false))->run();
				}
				foreach($member_ids as $id)
				{
					(new \admin_cmd_delete_account($id, null, true))->run();
				}
			});
		}
	}
}
