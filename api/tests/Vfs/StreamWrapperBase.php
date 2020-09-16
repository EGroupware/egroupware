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


class StreamWrapperBase extends LoggedInTest
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
	}

	protected function tearDown() : void
	{
		LoggedInTest::tearDownAfterClass();
		LoggedInTest::setupBeforeClass();

		// Re-init, since they look at user, fstab, etc.
		// Also, further tests that access the filesystem fail if we don't
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();

		// Need to ask about mounts, or other tests fail
		Vfs::mount();

		$backup = Vfs::$is_root;
		Vfs::$is_root = true;

		if(static::LOG_LEVEL > 1)
		{
			error_log($this->getName() . ' files for removal:');
			error_log(implode("\n",$this->files));
			error_log($this->getName() . ' mounts for removal:');
			error_log(implode("\n",$this->mounts));
		}

		// Remove any added files (as root to limit versioning issues)
		if(in_array('/',$this->files))
		{
			$this->fail('Tried to remove root');
		}
		foreach($this->files as $file)
		{
			Vfs::unlink($file);
		}

		// Remove any mounts
		foreach($this->mounts as $mount)
		{
			Vfs::umount($mount);
		}

		Vfs::$is_root = $backup;
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
	 * Simple test that we can write something and it's there
	 * By putting it in the base class, this test gets run for every backend
	 */
	public function testSimpleReadWrite() : void
	{
		if(!$this->test_file)
		{
			$this->markTestSkipped("No test file set - set it in setUp() or overriding test");
		}
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
	}
	/**
	 * Simple delete of a file
	 * By putting it in the base class, this test gets run for every backend
	 *
	 */
	public function testDelete() : void
	{
		if(!$this->test_file)
		{
			$this->markTestSkipped("No test file set - set it in setUp() or overriding test");
		}

		// Write
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

		Vfs::unlink($this->test_file);

		$post = Vfs::stat($this->test_file);
		$this->assertEquals(null,$post,
				"File '$this->test_file' was there after deleting"
		);
	}

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