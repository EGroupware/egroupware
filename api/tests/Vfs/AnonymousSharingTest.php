<?php

/**
 * Tests for sharing files and directories
 *
 * We check the access and permissions, making sure we only have access to what is supposed to be there.
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2020  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/SharingBase.php';
require_once __DIR__ . '/../../../admin/inc/class.admin_cmd_delete_account.inc.php';
require_once __DIR__ . '/../../../admin/inc/class.admin_cmd_edit_user.inc.php';

use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;


class AnonymousSharingTest extends SharingBase
{

	// File that should not be available over the share
	protected $no_access;

	protected function setUp() : void
	{

	}

	protected function tearDown() : void
	{
		LoggedInTest::setUpBeforeClass();
		parent::tearDown();
	}

	public function setupShare(&$dir, $extra = array(), $create = 'createShare')
	{
		// First, create the files to be shared
		$this->files[] = $dir;
		Vfs::mkdir($dir);
		$this->files += $this->addFiles($dir);


		// Create and use link
		$this->getShareExtra($dir, Sharing::READONLY, $extra);

		$share = call_user_func([$this, $create], $dir, Sharing::READONLY, $extra);
		$link = Vfs\Sharing::share2link($share);


		return $link;
	}

	/**
	 * Create a hidden upload share
	 *
	 * @param $path
	 * @param $mode
	 * @param array $extra
	 * @return array
	 * @throws \EGroupware\Api\Exception\AssertionFailed
	 */
	protected function createHiddenUploadShare($path, $mode, $extra = array())
	{
		// Make sure the path is there
		if(!Vfs::is_readable($path))
		{
			$this->assertTrue(
				Vfs::is_dir($path) ? Vfs::mkdir($path, 0750, true) : Vfs::touch($path),
				"Share path $path does not exist"
			);
		}

		// Create share
		$this->shares[] = $share = TestHiddenSharing::create('', $path, $mode, $name, $recipients, $extra);

		return $share;
	}

	/**
	 * Test a single anonymous user accessing two shares at the same time, in different browser windows
	 *
	 * @return void
	 * @see SharingACLTest::testShareNewSession() for a single share
	 *
	 */
	public function testTwoShares()
	{
		// TEST SETUP
		// Create shares
		$dir1 = Vfs::get_home_dir() . '/share1/';
		$link1 = $this->setupShare($dir1);

		$dir2 = Vfs::get_home_dir() . '/share2/';
		$link2 = $this->setupShare($dir2);

		// Add some files so we can tell the shares apart apart
		$FIRST_CONTENT = "This is the first share\n";
		$SECOND_CONTENT = "This is the second share\n";
		$dir_1_files = $this->addFiles($dir1, $FIRST_CONTENT);
		$dir_2_files = $this->addFiles($dir2, $SECOND_CONTENT);
		$this->files += $dir_1_files;
		$this->files += $dir_2_files;

		// Now log out
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();
		LoggedInTest::tearDownAfterClass();


		// ACTUAL TEST
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_COOKIEJAR, "/tmp/cookieFileName");
		curl_setopt($curl, CURLOPT_COOKIEFILE, "/tmp/cookieFileName");
		$this->checkShare($link1, $FIRST_CONTENT, $curl);
		$this->checkShare($link2, $SECOND_CONTENT, $curl);

		// Check first file again
		$this->checkShare($link1, $FIRST_CONTENT, $curl);
	}

	protected function checkShare($link, $content, &$curl)
	{
		// Read the  etemplate
		$data = array();
		$form = $this->getShare($link, $data, false, $curl);
		$this->assertNotNull($form, "Could not read the share link");
		$rows = $data['data']['content']['nm']['rows'];

		// Check content
		$result = array_filter($rows, function ($v)
		{
			return $v['name'] == 'test_file.txt';
		});
		$this->assertIsArray($result, "Could not find test file");
		$result = array_pop($result);

		$content_url = preg_replace('/\/share.php(.+)/', $result['download_url'], $link);

		curl_setopt($curl, CURLOPT_URL, $content_url);
		$fetched = curl_exec($curl);

		$this->assertEquals($content, $fetched, "Wrong file contents");
	}
}
