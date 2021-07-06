<?php

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Vfs;

/**
 * Class VfsTest
 *
 * Various tests of VFS to prevent regression
 *
 * @package EGroupware\Api\Vfs
 */
class VfsTest extends LoggedInTest
{

    /**
     * Keep track of files to remove after
     * @var string[]
     */
    protected $files = Array();

	protected function tearDown() : void
    {
        foreach ($this->files as $file)
        {
        	if(Vfs::is_dir($file) && !Vfs::is_link($file))
			{
				Vfs::rmdir($file);
			}
        	else
        	{
            	Vfs::unlink($file);
			}
        }
    }

	/**
	 * Test that if we create a symlink to a folder, we can actually access
	 * that folder and its contents through the symlink.
	 *
	 * @throws \EGroupware\Api\Exception\AssertionFailed
	 */
    public function testSymlinkFromFolder()
	{
		// Setup
		$test_base_dir = Vfs::get_home_dir();
		$source_dir = $test_base_dir . "/link_test";
		$link_dir = $test_base_dir . "/link_target";

		Vfs::mkdir($source_dir);

		// Add something into the directory
		$test_file_name = '/test.txt';
		$this->files[] = $test_file = $source_dir.$test_file_name;
		$contents = $this->getName() . "\nJust a test ;)\n";
		$this->assertNotFalse(
			file_put_contents(Vfs::PREFIX . $test_file, $contents),
			"Could not write file $test_file"
		);

		// Add into files list after test file, to make sure test file is removed
		// first during cleanup.  Order matters: link first, dir last
		$this->files[] = $link_dir;
		$this->files[] = $source_dir;

		// Create the link
		$this->assertTrue(
			Vfs::symlink($source_dir, $link_dir),
			"Could not create symlink to test ('$link_dir')"
		);

		Vfs::clearstatcache();

		// Test - is a link
		$this->assertTrue(Vfs::is_link($link_dir), "Link directory was not a link");

		// Test - directory is a directory
		$this->assertTrue(Vfs::is_dir($link_dir), "Link directory was not a directory");

		// Test - Folder is what we expect
		$stat = Vfs::stat($link_dir);
		$this->assertEquals(2,$stat['nlink'], "Link target is not a folder");
		$this->assertStringEndsWith($source_dir,$stat['url'], "Looks like link is wrong");

		// Test - File is where we expect
		$files = Vfs::find($link_dir,['type'=>'F']);
		$this->assertEquals(1,Vfs::$find_total, "Unexpected file count");
		$this->assertEquals($link_dir.$test_file_name, $files[0], "File name mismatch");


		// Test - File is what we expect
		Vfs::stat($files[0]);
		$this->assertEquals($contents, file_get_contents(Vfs::PREFIX . $files[0]), "File contents are wrong");
	}
}
