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


class SharingTest extends LoggedInTest
{

	// Keep track of shares to remove after
	protected $shares = Array();

	// Keep track of files to remove after
	protected $files = Array();

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

		// Remove any added files
		foreach($this->files as $path)
		{
			//echo "Unlinking $path: " . (Vfs::unlink($path) ? 'success' : 'failed');
			Vfs::unlink($path);
		}

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

		//var_dump(Vfs::find('/',array('maxdepth' => 1)));
		$logged_in_files = array_map(
				function($path) use ($dir) {return str_replace($dir, '/', $path);},
				Vfs::find($dir)
		);
		$this->shareLink($dir, Sharing::READONLY);

		$files = Vfs::find(Vfs::get_home_dir());

		//var_dump(Vfs::find('/test_subdir',array('maxdepth' => 1)));
		// Make sure files are the same
		$this->assertEquals($logged_in_files, $files);

		// Make sure all are readonly
		foreach($files as $file)
		{
			$this->assertFalse(Vfs::is_writable($file));
		}
	}

	/**
	 * Test to make sure a writable link to home gives write access, but just
	 * to user's home
	 */
	public function testHomeWritable()
	{
		$dir = Vfs::get_home_dir().'/';

		if(!Vfs::is_writable($dir))
		{
			$this->markTestSkipped("Unable to write to '$dir' as expected");
		}

		// Add some things for us to find, and make sure the dir is actually writable
		$file = $dir.'test_file.txt';
		$this->files[] = $file;
		$this->assertTrue(
			file_put_contents(Vfs::PREFIX.$file, 'Test for ' . $this->getName() ."\n". Api\DateTime::to()) !== FALSE,
			'Unable to write test file - check file permissions for CLI user'
		);

		$logged_in_files = array_map(
				function($path) use ($dir) {return str_replace($dir, '/', $path);},
				Vfs::find($dir)
		);

		// Make sure the file's there
		$this->assertTrue(in_array('/test_file.txt', $logged_in_files), 'Test file did not get created');

		// Now we go to the link...
		$this->shareLink($dir, Sharing::WRITABLE, array('share_writable' => TRUE));
		$files = Vfs::find(Vfs::get_home_dir());

		// Make sure files are the same
		$this->assertEquals($logged_in_files, $files);

		// Make sure all are writable
		foreach($files as $file)
		{
			// Root is not writable
			if($file == '/') continue;

			$this->assertTrue(Vfs::is_writable($file), $file . ' was not writable');
		}
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
		Vfs::clearstatcache();

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
