<?php

/**
 * Tests for sharing files and directories
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

	protected $shares = Array();

	// Keep some server stuff to reset when done
	protected $original_server;
	protected $original_user;

	public function setUp()
	{
		$this->original_server = array(
			'REQUEST_URI' => $_SERVER['REQUEST_URI'],
			'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME']
		);
		$this->original_user = $GLOBALS['egw_info']['user'];
	}
	public function tearDown()
	{
		//echo "\n\nEnding " . $this->getName() . "\n";
		$_SERVER += $this->original_server;
		$GLOBALS['egw_info']['user'] = $this->original_user;

		$GLOBALS['egw']->session->destroy($GLOBALS['egw']->session->sessionid, $GLOBALS['egw']->session->kp3);

		// This resets the VFS, but logs in anonymous
		LoggedInTest::setupBeforeClass();
		$GLOBALS['egw_info']['user'] = $this->original_user;
		Vfs::$user = $GLOBALS['egw_info']['user']['account_id'];

		// Need to ask about mounts, or other tests fail
		Vfs::mount();

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
		$this->markTestIncomplete(
          'This test has not been implemented yet.'
		);
		return;
		$dir = Vfs::get_home_dir().'/test_subdir/';
		Vfs::mkdir($dir);

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
		$this->markTestIncomplete(
          'This test has not been implemented yet.'
		);
		return;
		// Still have problems finding the files

		var_dump(Vfs::find('/',array('maxdepth' => 1)));
		$dir = Vfs::get_home_dir().'/test_subdir/';

		$logged_in_files = array_map(
				function($path) use ($dir) {return str_replace($dir, '/', $path);},
				Vfs::find($dir)
		);
		var_dump($logged_in_files);
		$this->shareLink($dir, Sharing::WRITABLE);
		var_dump(Vfs::find('/',array('maxdepth' => 1)));
		$files = Vfs::find(Vfs::get_home_dir().'/test_subdir');

		// Make sure files are the same
		$this->assertEquals($logged_in_files, $files);

		// Make sure all are writable
		foreach($files as $file)
		{
			echo "\t".$file . "\n";
			$this->assertTrue(Vfs::is_writable($file), $file . ' was not writable');
		}
	}

	/**
	 * Test that readable shares are actually readable
	 *
	 * @param string $path
	 */
	public function createShare($path, $mode)
	{
		// Make sure the path is there
		if(!Vfs::is_readable($path))
		{
			$this->assertTrue(Vfs::is_dir($path) ? Vfs::mkdir($path,0750,true) : Vfs::touch($path));
		}

		// Create share
		$this->shares[] = $share = Sharing::create($path, $mode, $name, $recipients, $extra=array());

		return $share;
	}

	/**
	 * Test that a share link can be made, and that only that path is available
	 *
	 * @param string $path
	 */
	public function shareLink($path, $mode)
	{
		echo __METHOD__ . "('$path',$mode)\n";
		// Setup - create path and share
		$share = $this->createShare($path, $mode);
		$link = Vfs\Sharing::share2link($share);

		// Setup for share to load
		$_SERVER['REQUEST_URI'] = $link;
		preg_match('|^https?://[^/]+(/.*)share.php/'.$share['share_token'].'$|', $path_info=$_SERVER['REQUEST_URI'], $matches);
        $_SERVER['SCRIPT_NAME'] = $matches[1];

		// Log out
		LoggedInTest::tearDownAfterClass();

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

		// Parse & check for nextmatch
		$dom = new \DOMDocument();
		@$dom->loadHTML($html);
		$xpath = new \DOMXPath($dom);
		$form = $xpath->query ('//form')->item(0);
		$data = json_decode($form->getAttribute('data-etemplate'));

		$this->assertEquals('filemanager.index', $data->name);
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
