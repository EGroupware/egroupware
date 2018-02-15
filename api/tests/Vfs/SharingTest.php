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

	public function setUp()
	{
		$this->original_server = array(
			'REQUEST_URI' => $_SERVER['REQUEST_URI'],
			'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME']
		);
	}
	public function tearDown()
	{
		$_SERVER += $this->original_server;
		LoggedInTest::setupBeforeClass();
		foreach($this->shares as $share)
		{
			Sharing::delete($share);
		}
	}


	/**
	 * Test that readable shares are actually readable
	 *
	 * @param string $path
	 */
	public function createShare($path, $mode)
	{
		$this->assertTrue(Vfs::touch($path));

		$this->shares[] = $share = Sharing::create($path, $mode, $name, $recipients, $extra=array());

		return $share;
	}

	/**
	 * Test that a readable link can be made, and that only that path is available
	 *
	 * @param string $path
	 */
	public function readableLink($path, $mode)
	{
		// Setup - create path and share
		$share = $this->createShare($path, $mode);
		$link = Vfs\Sharing::share2link($share);

		echo __METHOD__ . " LINK: $link\n";
		var_dump($share);
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
		$dir = '/home/'.$GLOBALS['egw_info']['user']['account_lid'];

		$logged_in_files = array_map(
				function($path) use ($dir) {return str_replace($dir, '/', $path);},
				Vfs::find($dir)
		);
		$this->readableLink($dir, Sharing::READONLY);
		$files = Vfs::find('/');

		// Make sure files are the same
		$this->assertEquals($logged_in_files, $files);

		// Make sure all are readonly
		foreach($files as $file)
		{
			$this->assertFalse(Vfs::is_writable($file));
		}
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
		$curl = curl_init($link);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		$html = curl_exec($curl);
		curl_close($curl);
		$dom = new \DOMDocument();
		@$dom->loadHTML($html);  //convert character asing
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

		static::load_egw('anonymous','','',$GLOBALS['egw_info']);
	}
}
