<?php

/**
 * Test Vfs\Sqlfs\Utils::fsck() and its 4 private fsck_fix_* checks - Phase 7
 * of the vfs-test-coverage project (doc/ai/projects/vfs-test-coverage.md).
 *
 * All 4 private fsck_fix_* methods scan the WHOLE egw_sqlfs table, with no
 * way to scope them to a subtree - unlike everything else in this project,
 * they're not naturally test-isolatable. $check_only=true (the default) is
 * read-only/safe against the shared dev DB, so it's used throughout; the
 * REPAIR path ($check_only=false) is deliberately NOT exercised against
 * live data here, for the same reasoning as Phase 2's skipped
 * persistent-mount write and Phase 4's skipped quotaRecalc() call - it
 * would touch/modify whatever ELSE happens to be inconsistent in this
 * shared dev DB, not just rows this test created itself.
 *
 * DETECTION is still thoroughly testable and safe: each private method is
 * invoked directly via reflection against a SPECIFIC, isolated row this
 * test seeds itself (never touching real/pre-existing data), confirming
 * the check correctly flags exactly the corruption it's supposed to.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs\Sqlfs;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;

class FsckTest extends LoggedInTest
{
	/**
	 * @var string root of a scratch sqlfs:// area - see FacadeTest for why
	 * (doc/ai/projects/vfs-test-coverage.md Phase 1/3)
	 */
	protected $test_root;

	protected $files = [];

	protected function setUp() : void
	{
		parent::setUp();

		$this->test_root = '/fscktest_' . substr(md5(uniqid('', true)), 0, 8);
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		Vfs::mount('sqlfs://default' . $this->test_root, $this->test_root, false, false);
		Vfs::clearstatcache();
		Vfs::mkdir($this->test_root);
		Vfs::chown($this->test_root, $GLOBALS['egw_info']['user']['account_id']);
		Vfs::chmod($this->test_root, 0750);
		Vfs::$is_root = $backup;
		Vfs::clearstatcache();
	}

	protected function tearDown() : void
	{
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		foreach(array_reverse($this->files) as $file)
		{
			if(Vfs::file_exists($file) || Vfs::is_link($file))
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

	protected function callFsckFix(string $method, bool $check_only = true) : array
	{
		$m = new \ReflectionMethod(Utils::class, $method);
		$m->setAccessible(true);
		return $m->invoke(null, $check_only);
	}

	public function testFsckCheckOnlyIsReadOnlyAndRunsWithoutError() : void
	{
		$msgs = Utils::fsck(true);
		$this->assertIsArray($msgs);
	}

	/**
	 * The 3 required top-level nodes should be healthy in any working
	 * install (not simulating corruption here - deleting the real / or
	 * /home node, even temporarily, would be far too disruptive to this
	 * shared dev DB and any concurrent session using it).
	 */
	public function testRequiredNodesCurrentlyHealthy() : void
	{
		$msgs = $this->callFsckFix('fsck_fix_required_nodes', true);
		$this->assertSame([], $msgs, 'Expected /, /home, /apps to all be healthy in this environment');
	}

	/**
	 * fsck_fix_no_content() flags a row whose physical blob file is
	 * missing from files/sqlfs - Sqlfs/Utils.php:262-348.
	 */
	public function testDetectsFileWithMissingPhysicalContent() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'will lose its physical content');
		$stat = Vfs::stat($file);

		$fs_path = StreamWrapper::_fs_path($stat['ino']);
		$this->assertTrue(file_exists($fs_path), 'Sanity check: physical blob file should exist right after writing');
		unlink($fs_path);

		$msgs = $this->callFsckFix('fsck_fix_no_content', true);
		$found = array_filter($msgs, fn($m) => str_contains($m, '#' . $stat['ino']));
		$this->assertNotEmpty($found, 'fsck should have flagged the file with missing physical content: ' . json_encode($msgs));
	}

	/**
	 * fsck_fix_no_content() also flags a 0-byte file outside /templates/ or
	 * /etemplates/ (which legitimately use 0-byte files as delete markers -
	 * see the Merge wrapper's own use of the same convention, Phase 6).
	 */
	public function testDetectsUnexpectedEmptyFile() : void
	{
		$file = $this->files[] = $this->getFilename();
		Vfs::touch($file);	// 0-byte file, NOT under /templates/
		$stat = Vfs::stat($file);
		$this->assertSame(0, $stat['size']);

		$msgs = $this->callFsckFix('fsck_fix_no_content', true);
		$found = array_filter($msgs, fn($m) => str_contains($m, '#' . $stat['ino']));
		$this->assertNotEmpty($found, 'fsck should have flagged the unexpected empty file: ' . json_encode($msgs));
	}

	/**
	 * fsck_fix_unconnected() flags a row whose fs_dir points to a
	 * non-existent parent - Sqlfs/Utils.php:368-442.
	 */
	public function testDetectsUnconnectedNode() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');
		$stat = Vfs::stat($file);
		$original_dir_ino = Vfs::stat($this->test_root)['ino'];

		// point fs_dir at an id that doesn't exist - a huge, surely-unused number
		$bogus_parent = 999999999;
		$GLOBALS['egw']->db->update('egw_sqlfs', ['fs_dir' => $bogus_parent], ['fs_id' => $stat['ino']], __LINE__, __FILE__);

		try
		{
			$msgs = $this->callFsckFix('fsck_fix_unconnected', true);
			$found = array_filter($msgs, fn($m) => str_contains($m, '#' . $stat['ino']));
			$this->assertNotEmpty($found, 'fsck should have flagged the unconnected node: ' . json_encode($msgs));
		}
		finally
		{
			// restore before tearDown() tries to clean it up normally
			$GLOBALS['egw']->db->update('egw_sqlfs', ['fs_dir' => $original_dir_ino], ['fs_id' => $stat['ino']], __LINE__, __FILE__);
			Vfs::clearstatcache();
		}
	}

	/**
	 * fsck_fix_multiple_active() flags two active rows sharing the same
	 * fs_dir+fs_name - Sqlfs/Utils.php:452 onward.
	 */
	public function testDetectsMultipleActiveFilesWithSameName() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'original');
		$stat = Vfs::stat($file);

		// clone the row (same fs_dir/fs_name), as a second ACTIVE entry
		$row = $GLOBALS['egw']->db->select('egw_sqlfs', '*', ['fs_id' => $stat['ino']], __LINE__, __FILE__)->fetch();
		unset($row['fs_id']);
		$row['fs_active'] = 1;
		$GLOBALS['egw']->db->insert('egw_sqlfs', $row, false, __LINE__, __FILE__);
		$duplicate_id = $GLOBALS['egw']->db->get_last_insert_id('egw_sqlfs', 'fs_id');

		try
		{
			$msgs = $this->callFsckFix('fsck_fix_multiple_active', true);
			$found = array_filter($msgs, fn($m) => str_contains($m, Vfs::basename($file)));
			$this->assertNotEmpty($found, 'fsck should have flagged the duplicate active file: ' . json_encode($msgs));
		}
		finally
		{
			$GLOBALS['egw']->db->delete('egw_sqlfs', ['fs_id' => $duplicate_id], __LINE__, __FILE__);
			Vfs::clearstatcache();
		}
	}
}
