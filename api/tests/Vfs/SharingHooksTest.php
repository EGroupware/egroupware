<?php

/**
 * Tests for updating / deleting shares if underlying file is renamed or deleted
 *
 * We create files and delete them through the VFS, then check to see if the share is
 * correctly deleted too.
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2020  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/SharingBase.php';

use EGroupware\Api\Vfs;


class SharingHooksTest extends SharingBase
{

	/**
	 * Test that deleting a file deletes the share
	 */
	public function testDeleteFileDeletesShare()
	{
		$target = Vfs::get_home_dir();

		$this->files = $this->addFiles($target);
		$test_file = $this->files[0];

		// Make sure there are no leftover shares
		Sharing::delete(array('share_path' => $test_file));

		// Create share
		$this->shares[] = $created_share = Sharing::create('', $test_file, Sharing::READONLY, '', '');

		$this->assertEquals(Vfs::PREFIX . $test_file, $created_share['share_path']);

		// Now delete the file
		Vfs::remove($test_file);

		// Check for share for that file
		$read_share = $this->readShare($created_share['share_id']);

		$this->assertEquals(array(), $read_share, "Expected not to find the share, but something was found");
	}


	/**
	 * Test that deleting a directory deletes shares on any file in that directory
	 */
	public function testDeleteDirectoryDeletesShare()
	{
		$target = Vfs::get_home_dir();

		$this->files = $this->addFiles($target);
		$test_file = $target . '/sub_dir/subdir_test_file.txt';

		// Make sure there are no leftover shares
		Sharing::delete(array('share_path' => $test_file));

		// Create share
		$this->shares[] = $created_share = Sharing::create('', $test_file, Sharing::READONLY, '', '');

		$this->assertEquals(Vfs::PREFIX . $test_file, $created_share['share_path']);

		// Now delete the parent directory
		Vfs::remove($target . '/sub_dir');

		// Check for share for that file
		$read_share = $this->readShare($created_share['share_id']);

		$this->assertEquals(array(), $read_share, "Expected not to find the share, but something was found");
	}

	/**
	 * Test renaming a file updates the share
	 */
	public function testRenameFileUpdatesShare()
	{
		$target = Vfs::get_home_dir();

		$this->files = $this->addFiles($target);
		$test_file = $this->files[0];

		// Make sure there are no leftover shares
		Sharing::delete(array('share_path' => $test_file));

		// Create share
		$this->shares[] = $created_share = Sharing::create('', $test_file, Sharing::READONLY, '', '');

		$this->assertEquals(Vfs::PREFIX . $test_file, $created_share['share_path']);

		// Now rename the file
		$this->files[] = $moved = $target . '/moved.txt';
		Vfs::rename($test_file, $moved);

		// Check for share for that file
		$read_share = $this->readShare($created_share['share_id']);

		$this->assertEquals(Vfs::PREFIX . $moved, $read_share['share_path'], "Expected find the share with a different path");
		$this->assertNotEquals(Vfs::PREFIX . $moved, $created_share['share_path'], "Expected find the share with a different path");
	}
}
