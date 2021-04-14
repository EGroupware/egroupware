<?php

/**
 * Tests for sharing files and directories
 *
 * We systematically test the various Vfs backends with files, subdirectories
 * and symlinks.  A backend is mounted (if needed) and test files are created.
 * Then we create the share (readable, writable) log out and check what the share
 * gives.  This is compared against what should be available, and we check the
 * access on each.
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2018  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/SharingBase.php';

use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;


class SharingBackendTest extends SharingBase
{
	/**
	 * Test to make sure a readonly link to home gives just readonly access,
	 * and just to user's home
	 */
	public function testHomeReadonly()
	{
		$dir = Vfs::get_home_dir().'/'.$this->getName(false).'/';

		$this->checkDirectory($dir, Sharing::READONLY);
	}

	/**
	 * Test to make sure a writable link to home gives write access, but just
	 * to user's home
	 */
	public function testHomeWritable()
	{
		$dir = Vfs::get_home_dir().'/'.$this->getName(false).'/';

		$this->checkDirectory($dir, Sharing::WRITABLE);
	}

	/**
	 * Test for a readonly share of a path with versioning turned on
	 */
	public function testVersioningReadonly()
	{
		$this->files[] = $dir = Vfs::get_home_dir().'/versioned/';

		// Create versioned directory
		if(Vfs::is_dir($dir)) Vfs::remove($dir);
		Vfs::mkdir($dir);
		$this->assertTrue(Vfs::is_writable($dir), "Unable to write to '$dir' as expected");
		$this->mountVersioned($dir);

		$this->checkDirectory($dir, Sharing::READONLY);
	}

	/**
	 * Test for a writable share of a path with versioning turned on
	 */
	public function testVersioningWritable()
	{
		$this->files[] = $dir = Vfs::get_home_dir().'/versioned/';

		// Create versioned directory
		if(Vfs::is_dir($dir)) Vfs::remove($dir);
		Vfs::mkdir($dir);
		$this->assertTrue(Vfs::is_writable($dir), "Unable to write to '$dir' as expected");
		$this->mountVersioned($dir);

		$this->checkDirectory($dir, Sharing::WRITABLE);
	}

	/**
	 * Test for a readonly share of a path from the filesystem
	 */
	public function testFilesystemReadonly()
	{
		// Don't add to files list or it deletes the folder from filesystem
		$dir = '/filesystem/';

		// Mount filesystem directory
		if(Vfs::is_dir($dir)) Vfs::remove($dir);
		$this->mountFilesystem($dir);
		$this->assertTrue(Vfs::is_writable($dir), "Unable to write to '$dir' as expected");

		$this->checkDirectory($dir, Sharing::READONLY);

		// Test folder in filesystem already has this file in it
		// It should be picked up normally, but an explicit check can't hurt
		$this->checkOneFile('/filesystem_test.txt', Sharing::READONLY);
	}

	/**
	 * Test for a readonly share of a path from the filesystem
	 */
	public function testFilesystemWritable()
	{
		// Don't add to files list or it deletes the folder from filesystem
		$dir = '/filesystem/';

		// Mount filesystem directory
		if(Vfs::is_dir($dir)) Vfs::remove($dir);
		$this->mountFilesystem($dir);
		$this->assertTrue(Vfs::is_writable($dir), "Unable to write to '$dir' as expected");

		$this->checkDirectory($dir, Sharing::WRITABLE);

		// Test folder in filesystem already has this file in it
		// It should be picked up normally, but an explicit check can't hurt
		$this->checkOneFile('/filesystem_test.txt', Sharing::WRITABLE);
	}

	/**
	 * Test for a readonly share of an application entry's filesystem (/apps)
	 */
	public function testLinksReadonly()
	{
		// Need to mount apps
		$this->mountLinks("/apps");

		// Create an infolog entry for testing purposes
		$info_id = $this->make_infolog();
		$bo = new \infolog_bo();
		$dir = "/apps/infolog/$info_id/";

		$this->assertTrue(Vfs::is_writable($dir), "Unable to write to '$dir' as expected");

		$this->checkDirectory($dir, Sharing::READONLY);

		// Can't delete it here, we're still the anonymous user until after
		$this->entries[] = Array(Array($bo, 'delete'), Array($info_id, false, false, true));
	}

	/**
	 * Test for a writable share of an application entry's filesystem (/apps)
	 */
	public function testLinksWritable()
	{
		// Need to mount apps
		$this->mountLinks("/apps");

		// Create an infolog entry for testing purposes
		$bo = new \infolog_bo();
		$info_id = $this->make_infolog();
		$dir = "/apps/infolog/$info_id/";

		$this->assertTrue(Vfs::is_writable($dir), "Unable to write to '$dir' as expected");

		$this->checkDirectory($dir, Sharing::WRITABLE);

		// Can't delete it here, we're still the anonymous user until after
		$this->entries[] = Array(Array($bo, 'delete'), Array($info_id, false, false, true));
	}

	/**
	 * Test merge stream wrapper
	 */
	public function testMergeReadonly()
	{
		if(!class_exists("\EGroupware\Stylite\Vfs\Merge\StreamWrapper"))
		{
			$this->markTestSkipped();
			return;
		}
		// Don't add to files list or it deletes the folder from filesystem
		$dir = '/merged/';

		// Mount filesystem directory
		if(Vfs::is_dir($dir)) Vfs::remove($dir);
		$this->mountMerge($dir);

		$this->assertTrue(
				Vfs::is_writable($dir),
				"Unable to write to '$dir' as expected, check {$GLOBALS['egw_info']['user']['account_lid']} is in Admin group (Merge requirement)"
		);
		$this->checkDirectory($dir, Sharing::READONLY);

		// Test folder in filesystem already has this file in it
		// It should be picked up normally, but an explicit check can't hurt
		$this->checkOneFile('/filesystem_test.txt', Sharing::READONLY);
	}

	/**
	 * Test merge stream wrapper
	 */
	public function testMergeWritable()
	{
		if(!class_exists("\EGroupware\Stylite\Vfs\Merge\StreamWrapper"))
		{
			$this->markTestSkipped();
			return;
		}
		// Don't add to files list or it deletes the folder from filesystem
		$dir = '/merged/';

		// Mount filesystem directory
		if(Vfs::is_dir($dir)) Vfs::remove($dir);
		$this->mountMerge($dir);

		$this->assertTrue(
				Vfs::is_writable($dir),
				"Unable to write to '$dir' as expected, check {$GLOBALS['egw_info']['user']['account_lid']} is in Admin group (Merge requirement)"
		);
		$this->checkDirectory($dir, Sharing::WRITABLE);

		// Test folder in filesystem already has this file in it
		// It should be picked up normally, but an explicit check can't hurt
		$this->checkOneFile('/filesystem_test.txt', Sharing::WRITABLE);
	}

	/**
	 * Test that creating a share on a symlink actually creates the share on the
	 * link target instead.
	 */
	public function testSharingSymlinkActuallySharesTargetFile()
	{
		$target = Vfs::get_home_dir();

		$this->files = $this->addFiles($target);

		// Make symlink
		$this->files[] = $symlink = $target.'/symlink.txt';
		$file = $target.'/test_file.txt';
		if(Vfs::is_link($symlink)) Vfs::unlink($symlink);
		$this->assertTrue(
			Vfs::symlink($file, $symlink),
			"Unable to create symlink $symlink => $file"
		);

		// Create share
		$this->shares[] = $created_share = Sharing::create('', $symlink, Sharing::READONLY, '', '');

		$this->assertEquals($file, $created_share['share_path']);
	}


	/**
	 * Test that creating a share on a symlink actually creates the share on the
	 * link target instead.
	 */
	public function testSharingSymlinkActuallySharesTargetDirectory()
	{
		$target = Vfs::get_home_dir();

		$this->files = $this->addFiles($target);

		// Make symlink
		$this->files[] = $symlink = $target.'/symlinked_dir';
		$file = $target.'/sub_dir';
		if(Vfs::is_link($symlink)) Vfs::unlink($symlink);
		$this->assertTrue(
			Vfs::symlink($file, $symlink),
			"Unable to create symlink $symlink => $file"
		);

		// Create share
		$this->shares[] = $created_share = Sharing::create('', $symlink, Sharing::READONLY, '', '');

		$this->assertEquals($file, $created_share['share_path']);
	}

	public function testShareFileInsideSymlinkDirectory()
	{
		$target = Vfs::get_home_dir();

		$this->files = $this->addFiles($target);
		$target_file = $target.'/sub_dir/'.'subdir_test_file.txt';

		// Make symlink
		$this->files[] = $symlink = $target.'/symlinked_dir/';
		$file = $symlink.'subdir_test_file.txt';
		if(Vfs::is_link($symlink)) Vfs::unlink($symlink);
		$this->assertTrue(
			Vfs::symlink($target.'/sub_dir/', $symlink),
			"Unable to create symlink $symlink => $target/sub_dir/"
		);

		// Create share
		$this->shares[] = $created_share = Sharing::create('', $file, Sharing::READONLY, '', '');

		$this->assertEquals(Vfs::PREFIX . $target_file, $created_share['share_path']);
	}
}
