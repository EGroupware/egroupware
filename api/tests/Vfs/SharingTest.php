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

	public function setUp()
	{

	}
	public function tearDown()
	{
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

		// Log out
		//LoggedInTest::tearDownAfterClass();

		// Load share
		$_SERVER['REQUEST_URI'] = $link;
		$this->setup_info();
		Sharing::create_session();

		// Try to read
		echo __METHOD__ . ' PATH: ' . $path. "\n";
		if(Vfs::is_dir($path))
		{
			$this->checkDirectoryLink($link, $share);
		}

		LoggedInTest::setupBeforeClass();
	}

	public function testHomeReadonly()
	{
		$this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
		$this->readableLink('/home/'.$GLOBALS['egw_info']['user']['account_lid'], Sharing::READONLY);
	}

	public function checkDirectoryLink($link, $share)
	{
		$curl = curl_init($link);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		//curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.10 (KHTML, like Gecko) Chrome/8.0.552.224 Safari/534.10');
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


	}
}
