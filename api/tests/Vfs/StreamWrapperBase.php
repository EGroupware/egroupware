<?php

/**
 * Base for testing various stream wrappers
 *
 * This holds some common things so we can re-use them for the various places
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2020  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;
use EGroupware\Stylite\Vfs\Versioning;


abstract class StreamWrapperBase extends LoggedInTest
{
	/**
	 * How much should be logged to the console (stdout)
	 *
	 * 0 = Nothing
	 * 1 = info
	 * 2 = debug
	 */
	const LOG_LEVEL = 0;

	/**
	 * @var string If we're just doing a simple test with one file, use this file
	 */
	protected $test_file = '';

	/**
	 * Keep track of files to remove after
	 * @var Array
	 */
	protected $files = Array();

	/**
	 * Keep track of mounts to remove after
	 */
	protected $mounts = Array();

	/**
	 * Options for searching the Vfs (Vfs::find())
	 */
	const VFS_OPTIONS = array(
		'maxdepth' => 5
	);

	// User for testing - we share with this user & log in as them for checking
	protected $account_id;

	// File that should not be available due to permissions
	protected $no_access;

	// Use a completely new user, so we know it's there and "clean"
	protected $account = array(
		'account_lid' => 'user_test',
		'account_firstname' => 'Access',
		'account_lastname' => 'Test',
		'account_passwd' => 'passw0rd',
		'account_passwd_2' => 'passw0rd',
		// Don't let them in Default, any set ACLs will interfere with tests
		'account_primary_group' => 'Testers',
		'account_groups' => ['Testers']
	);

	protected function setUp() : void
	{
		// Check we have basic access
		if(!is_readable($GLOBALS['egw_info']['server']['files_dir']))
		{
			$this->markTestSkipped('No read access to files dir "' .$GLOBALS['egw_info']['server']['files_dir'].'"' );
		}
		if(!is_writable($GLOBALS['egw_info']['server']['files_dir']))
		{
			$this->markTestSkipped('No write access to files dir "' .$GLOBALS['egw_info']['server']['files_dir'].'"' );
		}
		$this->mount();
	}

	protected function tearDown() : void
	{
		// Make sure we're on the original user.  Failures could cause us to be logged in as someone else
		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		$this->mount();
		// Need to ask about mounts, or other tests fail
		Vfs::mount();

		$backup = Vfs::$is_root;
		Vfs::$is_root = true;

		if(static::LOG_LEVEL > 1)
		{
			if($this->account_id) error_log($this->getName() . ' user to be removed: ' . $this->account_id);
			error_log($this->getName() . ' files for removal:');
			error_log(implode("\n",$this->files));
			error_log($this->getName() . ' mounts for removal:');
			error_log(implode("\n",$this->mounts));
		}

		// Remove our other test user
		if($this->account_id)
		{
			$command = new \admin_cmd_delete_account( $this->account_id, null, true);
			$command->comment = 'Removing in tearDown for unit test ' . $this->getName();
			$command->run();
		}

		// Remove any added files (as root to limit versioning issues)
		if(in_array('/',$this->files))
		{
			$this->fail('Tried to remove root');
		}
		foreach($this->files as $file)
		{
			if(Vfs::is_dir($file) && !Vfs::is_link(($file)))
			{
				Vfs::rmdir($file);
			}
			else
			{
				Vfs::unlink($file);
			}
		}

		// Remove any mounts
		foreach($this->mounts as $mount)
		{
			// Do not remove /apps
			if($mount == '/apps') continue;

			Vfs::umount($mount);
		}

		Vfs::$is_root = $backup;
	}

	/////
	/// These tests will be run by every extending class, with
	/// the extending class's setUp().  They can be overridden, but
	/// we get free tests this way with no copy/paste
	/////

	/**
	 * Simple test that we can write something and it's there
	 * By putting it in the base class, this test gets run for every backend
	 */
	public function testSimpleReadWrite() : string
	{
		if(!$this->test_file)
		{
			$this->markTestSkipped("No test file set - set it in setUp() or overriding test");
		}

		// Check that the file is not there
		$pre_start = Vfs::stat($this->test_file);
		$this->assertEquals(null,$pre_start,
				"File '$this->test_file' was there before we started, check clean up"
		);

		// Write
		$contents = $this->getName() . "\nJust a test ;)\n";
		$this->assertNotFalse(
				file_put_contents(Vfs::PREFIX . $this->test_file, $contents),
				"Could not write file $this->test_file"
		);

		// Check contents are unchanged
		$this->assertEquals(
				$contents, file_get_contents(Vfs::PREFIX . $this->test_file),
				"Read file contents do not match what was written"
		);

		return $this->test_file;
	}

	/**
	 * Simple delete of a file
	 * By putting it in the base class, this test gets run for every backend
	 *
	 * @depends testSimpleReadWrite
	 */
	public function testDelete($file) : void
	{
		if(!$this->test_file && !$file)
		{
			$this->markTestSkipped("No test file set - set it in setUp() or overriding test");
		}

		// Write
		if(!$file)
		{
			$contents = $this->getName() . "\nJust a test ;)\n";
			$this->assertNotFalse(
					file_put_contents(Vfs::PREFIX . $this->test_file, $contents),
					"Could not write file $this->test_file"
			);

			$start = Vfs::stat($this->test_file);
			$this->assertNotNull(
					$start,
					"File '$this->test_file' was not what we expected to find after writing"
			);
		}
		else
		{
			$this->test_file = $file;
		}

		Vfs::unlink($this->test_file);

		$post = Vfs::stat($this->test_file);
		$this->assertEquals(null,$post,
				"File '$this->test_file' was there after deleting"
		);
	}

	/**
	 * Check that a user with no permission to a file cannot access the file
	 *
	 * @depends testSimpleReadWrite
	 * @throws Api\Exception\AssertionFailed
	 */
	public function testNoReadAccess() : void
	{
		if(!$this->test_file)
		{
			$this->markTestSkipped("No test file set - set it in setUp() or overriding test");
		}

		// Check that the file is not there
		$pre_start = Vfs::stat($this->test_file);
		$this->assertEquals(null,$pre_start,
				"File '$this->test_file' was there before we started, check clean up"
		);

		// Write
		$file = $this->test_file;
		$contents = $this->getName() . "\nJust a test ;)\n";
		$this->assertNotFalse(
				file_put_contents(Vfs::PREFIX . $file, $contents),
				"Could not write file $file"
		);

		// Create another user who has no access to our file
		$user_b = $this->makeUser();

		// Log in as them
		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		$this->mount();

		// Check the file
		$this->assertFalse(
				Vfs::is_readable($file),
				"File '$file' was accessible by another user who had no permission"
		);
		$this->assertFalse(
				file_get_contents(Vfs::PREFIX . $file),
				"Read someone else's file with no permission. " . Vfs::PREFIX . $file
		);

	}


	/**
	 * Check that a user with permission to a file can access the file
	 *
	 * @depends testSimpleReadWrite
	 * @throws Api\Exception\AssertionFailed
	 */
	public function testWithAccess() : void
	{
		if(!$this->test_file)
		{
			$this->markTestSkipped("No test file set - set it in setUp() or overriding test");
		}

		// Check that the file is not there
		$pre_start = Vfs::stat($this->test_file);
		$this->assertEquals(null,$pre_start,
				"File '$this->test_file' was there before we started, check clean up"
		);

		// Write
		$file = $this->test_file;
		$contents = $this->getName() . "\nJust a test ;)\n";
		$this->assertNotFalse(
				file_put_contents(Vfs::PREFIX . $file, $contents),
				"Could not write file $file"
		);
		$pre = Vfs::stat($this->test_file);
		// Check that it's actually there
		$this->assertEquals($contents,file_get_contents(Vfs::PREFIX . $file), "Did not write test file");

		// Create another user who has no access to our file
		$user_b = $this->makeUser();

		// Allow access
		$this->allowAccess(
				$this->getName(false),
				$file,
				$user_b,
				'r'
		);

		// Log in as them
		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		$this->mount();

		// Check the file
		$post = Vfs::stat($file);
		$this->assertIsArray($post,
				"File '$file' was not accessible by another user who had permission"
		);
		$this->assertEquals(
				$contents,
				file_get_contents(Vfs::PREFIX . $file),
				"Problem reading contents of someone else's file (".Vfs::PREFIX . "$file) with permission"
		);
		$this->assertTrue(
				Vfs::is_readable($file),
				"Vfs says $file is not readable.  It should be."
		);

	}


	/**
	 * Test that we can work through/with a symlink
	 *
	 * @throws Api\Exception\AssertionFailed
	 */
	public function testSymlinkFromFolder($test_file = '') : void
	{
		// Setup
		if($test_file == '')
		{
			$test_file = Vfs::get_home_dir();
		}
		else
		{
			$test_file = Vfs::dirname($test_file);
		}
		$ns = explode('\\', __NAMESPACE__);
		$test_base_dir = $test_file . '/'.array_pop($ns).'/'.$this->getName();
		$source_dir = $test_base_dir . "/link_target";
		$link_dir = $test_base_dir . "/im_a_symlink";

		// Check if backend supports it
		$url = Vfs::resolve_url_symlinks($test_base_dir,false,false);
		$scheme = (string)Vfs::parse_url($url,PHP_URL_SCHEME);
		if (!class_exists($class = Vfs\StreamWrapper::scheme2class($scheme)) || !method_exists($class,'symlink'))
		{
			$this->markTestIncomplete($scheme . " StreamWrapper ($class) does not support symlink");
		}

		// Try to remove if it's there already
		if(Vfs::is_dir($test_base_dir))
		{
			Vfs::rmdir($test_base_dir);
		}
		$this->assertTrue(
			Vfs::mkdir($test_base_dir),
			"Could not create base test directory '$test_base_dir', delete it if it's there already."
		);
		$this->assertTrue(
			Vfs::mkdir($source_dir),
			"Could not create source directory '$source_dir'"
		);

		// Add something into the directory
		$test_file_name = '/test.txt';
		$this->files[] = $test_file = $source_dir.$test_file_name;
		$contents = $this->getName() . "\n".__CLASS__."\nJust a test ;)\n";
		$this->assertNotFalse(
			file_put_contents(Vfs::PREFIX . $test_file, $contents),
			"Could not write file $test_file"
		);

		// Add into files list after test file, to make sure test file is removed
		// first during cleanup.  Order matters: link first, dir last
		$this->files[] = $link_dir;
		$this->files[] = $source_dir;
		$this->files[] = $test_base_dir;

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
		$readlink = Vfs::readlink($link_dir);
		$this->assertStringEndsWith("/link_target", $readlink, "Looks like link is wrong");

		// Test - File is where we expect
		$files = Vfs::find($link_dir,['type'=>'F']);
		$this->assertEquals(1,Vfs::$find_total, "Unexpected file count");
		$this->assertEquals($link_dir.$test_file_name, $files[0], "File name mismatch");


		// Test - File is what we expect
		Vfs::stat($files[0]);
		$this->assertEquals($contents, file_get_contents(Vfs::PREFIX . $files[0]), "File contents are wrong");
	}


	////// Handy functions ///////

	/**
	 * Create a test user, returns the account ID
	 *
	 * @return int
	 */
	protected function makeUser(Array $account = []) : int
	{
		if(count($account) == 0)
		{
			$account = $this->account;
		}
		if(($account_id = $GLOBALS['egw']->accounts->name2id($account['account_lid'])))
		{
			// Delete if there in case something went wrong
			$GLOBALS['egw']->accounts->delete($account_id);
		}

		// It needs its own group too, Default will mess with any ACL tests
		if(!$GLOBALS['egw']->accounts->exists($account['account_primary_group']))
		{
			$group = $this->makeTestGroup();
		}

		// Execute
		$command = new \admin_cmd_edit_user(false, $account);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		$this->account_id = $command->account;

		if($group)
		{
			// Had to create the group, but we don't want current user in it
			$remove_group = new \admin_cmd_edit_group('Testers',['account_lid' => 'Testers', 'account_members' => [$this->account_id]]);
			$remove_group->run();
		}
		return $this->account_id;
	}

	/**
	 * Make a test group we can put our users in to avoid any ACLs on Default group
	 */
	protected function makeTestGroup()
	{
		// Execute
		$command = new \admin_cmd_edit_group(false, ['account_lid' => 'Testers', 'account_members' => $GLOBALS['egw_info']['user']['account_id']]);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		return $command->account;
	}

	/**
	 * Make a filename that reflects the current test
	 */
	protected function getFilename($path = null)
	{
		if(is_null($path)) $path = Vfs::get_home_dir().'/';
		if(substr($path,-1,1) !== '/') $path = $path . '/';

		$reflect = new \ReflectionClass($this);
		return $path . $reflect->getShortName() . '_' . $this->getName() . '.txt';
	}

	/**
	 * Mount the needed filesystem
	 *
	 * This may be called multiple times for each test as we change users, logout, etc.
	 */
	abstract protected function mount() : void;

	/**
	 * Allow access to the given file for the given user ID
	 *
	 * Using whatever way works best for the mount/streamwrapper being tested, allow the user access
	 *
	 * @param string $test_name
	 * @param string $test_file
	 * @param int $test_user
	 * @param string $needed r, w, rw
	 * @return mixed
	 */
	abstract protected function allowAccess(string $test_name, string &$test_file, int $test_user, string $needed) : void;

	/**
	 * Mount the app entries into the filesystem
	 *
	 * @param string $path
	 */
	protected function mountLinks($path)
	{
		Vfs::$is_root = true;
		$url = Links\StreamWrapper::PREFIX . '/apps';
		$this->assertTrue(
			Vfs::mount($url, $path, false, false),
			"Unabe to mount $url => $path"
		);
		Vfs::$is_root = false;

		$this->mounts[] = $path;
		Vfs::clearstatcache();
		Vfs::init_static();
	}

	/**
	 * Start versioning for the given path
	 *
	 * @param string $path
	 */
	protected function mountVersioned($path)
	{
		if (!class_exists('EGroupware\Stylite\Vfs\Versioning\StreamWrapper'))
		{
			$this->markTestSkipped("No versioning available");
		}
		if(substr($path, -1) == '/') $path = substr($path, 0, -1);
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		$url = Versioning\StreamWrapper::PREFIX.$path;
		$this->assertTrue(Vfs::mount($url,$path), "Unable to mount $path as versioned");
		Vfs::$is_root = $backup;

		$this->mounts[] = $path;
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();
	}

	/**
	 * Mount a test filesystem path (api/tests/fixtures/Vfs/filesystem_mount)
	 * at the given VFS path
	 *
	 * @param string $path
	 */
	protected function mountFilesystem($path)
	{
		// Vfs breaks if path has trailing /
		if(substr($path, -1) == '/') $path = substr($path, 0, -1);

		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		$fs_path = realpath(__DIR__ . '/../fixtures/Vfs/filesystem_mount');
		if(!file_exists($fs_path))
		{
			$this->fail("Missing filesystem test directory 'api/tests/fixtures/Vfs/filesystem_mount'");
		}
		$url = Filesystem\StreamWrapper::SCHEME.'://default'. $fs_path.
				'?user='.$GLOBALS['egw_info']['user']['account_id'].'&group=Default&mode=775';
		$this->assertTrue(Vfs::mount($url,$path), "Unable to mount $url to $path");
		Vfs::$is_root = $backup;

		$this->mounts[] = $path;
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();
	}

	/**
	 * Merge a test filesystem path (api/tests/fixtures/Vfs/filesystem_mount)
	 * with the given VFS path
	 *
	 * @param string $path
	 */
	protected function mountMerge($path)
	{
		// Vfs breaks if path has trailing /
		if(substr($path, -1) == '/') $path = substr($path, 0, -1);


		$backup = Vfs::$is_root;
		Vfs::$is_root = true;

		// I guess merge needs the dir in SQLFS first
		if(!Vfs::is_dir($path)) Vfs::mkdir($path);
		Vfs::chmod($path, 0750);
		Vfs::chown($path, $GLOBALS['egw_info']['user']['account_id']);

		$url = \EGroupware\Stylite\Vfs\Merge\StreamWrapper::SCHEME.'://default'.$path.'?merge=' . realpath(__DIR__ . '/../fixtures/Vfs/filesystem_mount');
		$this->assertTrue(Vfs::mount($url,$path), "Unable to mount $url to $path");
		Vfs::$is_root = $backup;

		$this->mounts[] = $path;
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();
	}

	/**
	 * Add some files to the given path so there's something to find.
	 *
	 * @param string $path
	 *
	 * @return array of paths
	 */
	protected function addFiles($path, $content = false)
	{
		if(substr($path, -1) != '/')
		{
			$path .= '/';
		}
		if(!$content)
		{
			$content = 'Test for ' . $this->getName() ."\n". Api\DateTime::to();
		}
		$files = array();

		// Plain file
		$files[] = $file = $path.'test_file.txt';
		$this->assertTrue(
			file_put_contents(Vfs::PREFIX.$file, $content) !== FALSE,
			'Unable to write test file "' . Vfs::PREFIX . $file .'" - check file permissions for CLI user'
		);

		// Subdirectory
		$files[] = $dir = $path.'sub_dir/';
		if(Vfs::is_dir($dir))
		{
			Vfs::remove($dir);
		}
		$this->assertTrue(
			Vfs::mkdir($dir),
			'Unable to create subdirectory ' . $dir
		);

		// File in a subdirectory
		$files[] = $file = $dir.'subdir_test_file.txt';
		$this->assertTrue(
			file_put_contents(Vfs::PREFIX.$file, $content) !== FALSE,
			'Unable to write test file "' . Vfs::PREFIX . $file .'" - check file permissions for CLI user'
		);

		// Symlinked file
		/* We don't test these because they don't work - the target will always
		 * be outside the share root
		// Always says its empty
		$files[] = $symlink = $path.'symlink.txt';
		if(Vfs::file_exists($symlink)) Vfs::remove($symlink);
		$this->assertTrue(
			Vfs::symlink($file, $symlink),
			"Unable to create symlink $symlink => $file"
		);

		// Symlinked dir
		$files[] = $symlinked_dir = $path.'sym_dir/';
		$this->assertTrue(
			Vfs::symlink($dir, $symlinked_dir),
			'Unable to create symlinked directory ' . $symlinked_dir
		);
*/
		return $files;
	}
}