<?php

/**
 * Tests for sharing files and directories
 *
 * This is a bit of a mess, but I think we probably want to automatically test
 * this to make sure we don't expose more than desired.
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package
 * @copyright (c) 2018  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

use EGroupware\Api;
use EGroupware\Api\Vfs;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Stylite\Vfs\Versioning;


class SharingTest extends LoggedInTest
{

	/**
	 * Keep track of shares to remove after
	 */
	protected $shares = Array();

	/**
	 * Keep track of files to remove after
	 * @var Array
	 */
	protected $files = Array();

	/**
	 * Options for searching the Vfs (Vfs::find())
	 */
	const VFS_OPTIONS = array(
		'maxdepth' => 5
	);

	public function setUp()
	{

	}

	public function tearDown()
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

		// Remove any added files as root to avoid versioning issues
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		Vfs::remove($this->files);
		Vfs::$is_root = $backup;

		// Remove any added shares
		foreach($this->shares as $share)
		{
			Sharing::delete($share);
		}
	}


	/**
	 * Test to make sure a readonly link to home gives just readonly access,
	 * and just to user's home
	 */
	public function testHomeReadonly()
	{
		$dir = Vfs::get_home_dir().'/';

		$this->checkDirectory($dir, Sharing::WRITABLE);
	}

	/**
	 * Test to make sure a writable link to home gives write access, but just
	 * to user's home
	 */
	public function testHomeWritable()
	{
		$dir = Vfs::get_home_dir().'/';

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
	 * Check a given directory to see that a link to it works.
	 *
	 * We check
	 * - Files/directories available to original user are available through share
	 * - Permissions match share (Read / Write)
	 * - Files are not empty
	 *
	 * @param string $dir
	 * @param string $mode
	 */
	protected function checkDirectory($dir, $mode)
	{
		$this->files += $this->addFiles($dir);

		$logged_in_files = array_map(
				function($path) use ($dir) {return str_replace($dir, '/', $path);},
				Vfs::find($dir, static::VFS_OPTIONS)
		);

		//echo "\n".$this->getName();
		//echo "\nLogged in files:\n".implode("\n", $logged_in_files)."\n";

		// Create and use link
		$extra = array();
		switch($mode)
		{
			case Sharing::WRITABLE:
				$extra['share_writable'] = TRUE;
				break;
		}
		$this->shareLink($dir, $mode, $extra);

		$files = Vfs::find('/', static::VFS_OPTIONS);

		//echo "\nLinked files:\n".implode("\n", $files)."\n";

		// Make sure files are the same
		$this->assertEquals($logged_in_files, $files);

		// Make sure all are readonly
		foreach($files as $file)
		{
			$this->checkOneFile($file, $mode);
		}
	}

	/**
	 * Check the access permissions for one file/directory
	 *
	 * @param string $file
	 * @param string $mode
	 */
	protected function checkOneFile($file, $mode)
	{
		// All the test files have something in them
		if(!Vfs::is_dir($file))
		{
			$this->assertNotEmpty(file_get_contents(Vfs::PREFIX.$file), "$file was empty");
		}

		// Check permissions
		switch($mode)
		{
			case Sharing::READONLY:
				$this->assertFalse(Vfs::is_writable($file));
				if(!Vfs::is_dir($file))
				{
					$this->assertFalse(file_put_contents(Vfs::PREFIX.$file, 'Writable check'));
				}
				break;
			case Sharing::WRITABLE:
				// Root is not writable
				if($file == '/') continue;

				$this->assertTrue(Vfs::is_writable($file), $file . ' was not writable');
				if(!Vfs::is_dir($file))
				{
					$this->assertNotFalse(file_put_contents(Vfs::PREFIX.$file, 'Writable check'));
				}
				break;
		}

	}

	protected function mountVersioned($path)
	{
		if (!class_exists('EGroupware\Stylite\Vfs\Versioning\StreamWrapper'))
		{
			$this->markTestSkipped("No versioning available");
		}
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		$url = Versioning\StreamWrapper::PREFIX.$path;
		$this->assertTrue(Vfs::mount($url,$path), "Unable to mount $path as versioned");
		Vfs::$is_root = $backup;
	}

	/**
	 * Add some files to the given path so there's something to find.
	 *
	 * @param string $path
	 *
	 * @return array of paths
	 */
	protected function addFiles($path)
	{
		$files = array();

		// Plain file
		$files[] = $file = $path.'test_file.txt';
		$this->assertTrue(
			file_put_contents(Vfs::PREFIX.$file, 'Test for ' . $this->getName() ."\n". Api\DateTime::to()) !== FALSE,
			'Unable to write test file - check file permissions for CLI user'
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
			file_put_contents(Vfs::PREFIX.$file, 'Test for ' . $this->getName() ."\n". Api\DateTime::to()) !== FALSE,
			'Unable to write test file - check file permissions for CLI user'
		);
		return $files;
	}

	/**
	 * Test that readable shares are actually readable
	 *
	 * @param string $path
	 */
	public function createShare($path, $mode, $extra = array())
	{
		// Make sure the path is there
		if(!Vfs::is_readable($path))
		{
			$this->assertTrue(Vfs::is_dir($path) ? Vfs::mkdir($path,0750,true) : Vfs::touch($path));
		}

		// Create share
		$this->shares[] = $share = Sharing::create($path, $mode, $name, $recipients, $extra);

		return $share;
	}

	/**
	 * Test that a share link can be made, and that only that path is available
	 *
	 * @param string $path
	 */
	public function shareLink($path, $mode, $extra = array())
	{
		//echo __METHOD__ . "('$path',$mode)\n";
		// Setup - create path and share
		$share = $this->createShare($path, $mode, $extra);
		$link = Vfs\Sharing::share2link($share);
		//echo __METHOD__ . " link: $link\n";
		//echo __METHOD__ . " share: " . array2string($share)."\n";

		// Setup for share to load
		$_SERVER['REQUEST_URI'] = $link;
		preg_match('|^https?://[^/]+(/.*)share.php/'.$share['share_token'].'$|', $path_info=$_SERVER['REQUEST_URI'], $matches);
        $_SERVER['SCRIPT_NAME'] = $matches[1];

		// Log out & clear cache
		LoggedInTest::tearDownAfterClass();

		// Re-init, since they look at user, fstab, etc.
		// Also, further tests that access the filesystem fail if we don't
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();


		// If it's a directory, check to make sure it gives the filemanager UI
		if(Vfs::is_dir($path))
		{
			$this->checkDirectoryLink($link, $share);
		}

		// Load share
		$this->setup_info();

		// Our path should be mounted to root
		$this->assertTrue(Vfs::is_readable('/'));

		// Check other paths
		$this->assertFalse(Vfs::is_readable($path));
		$this->assertFalse(Vfs::is_readable($path . '../'));
	}

	/**
	 * Test to make sure that a directory link leads to a limited filemanager
	 * interface (not a file or 404).
	 *
	 * @param type $link
	 * @param type $share
	 */
	public function checkDirectoryLink($link, $share)
	{
		// Set up curl
		$curl = curl_init($link);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		$html = curl_exec($curl);
		curl_close($curl);

		if(!$html)
		{
			// No response - could mean something is terribly wrong, or it could
			// mean we're running on Travis with no webserver to answer the
			// request
			return;
		}

		// Parse & check for nextmatch
		$dom = new \DOMDocument();
		@$dom->loadHTML($html);
		$xpath = new \DOMXPath($dom);
		$form = $xpath->query ('//form')->item(0);
		$data = json_decode($form->getAttribute('data-etemplate'));

		$this->assertEquals('filemanager.index', $data->name);

		// Make sure we start at root, not somewhere else like the token mounted
		// as a sub-directory
		$this->assertEquals('/', $data->data->content->nm->path);

		unset($data->data->content->nm->actions);
		//var_dump($data->data->content->nm);
	}
	protected function setup_info()
	{
		// Copied from share.php
		$GLOBALS['egw_info'] = array(
			'flags' => array(
				'disable_Template_class' => true,
				'noheader'  => true,
				'nonavbar' => 'always',	// true would cause eTemplate to reset it to false for non-popups!
				'currentapp' => 'filemanager',
				'autocreate_session_callback' => 'EGroupware\\Api\\Vfs\\Sharing::create_session',
				'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
			)
		);

		ob_start();
		static::load_egw('anonymous','','',$GLOBALS['egw_info']);
		ob_end_clean();
	}
}
