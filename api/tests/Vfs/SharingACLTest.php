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


class SharingACLTest extends SharingBase
{
	// User for testing - we share with this user & log in as them for checking
	protected $account_id;

	// File that should not be available over the share
	protected $no_access;

	// Use a completely new user, so we know it's there and "clean"
	protected $account = array(
			'account_lid' => 'user_test',
			'account_firstname' => 'ShareAccess',
			'account_lastname' => 'Test',
			'account_passwd' => 'passw0rd',
			'account_passwd_2' => 'passw0rd'
	);

	protected function setUp() : void
	{
		if(($account_id = $GLOBALS['egw']->accounts->name2id($this->account['account_lid'])))
		{
			// Delete if there in case something went wrong
			$GLOBALS['egw']->accounts->delete($account_id);
		}

		// admin_cmd_edit_user requires the CURRENT session to be a real admin
		$this->asAdmin(function()
		{
			$command = new \admin_cmd_edit_user(false, $this->account);
			$command->comment = 'Needed for unit test ' . $this->name();
			$command->run();
			$this->account_id = $command->account;
		});
	}

	protected function tearDown() : void
	{
		LoggedInTest::setUpBeforeClass();
		parent::tearDown();
		if($this->account_id)
		{
			$GLOBALS['egw']->accounts->delete($this->account_id);
		}
	}

	public function setupShare(&$dir, $extra = array(), $create = 'createShare')
	{
		// First, create the files to be shared
		$this->files[] = $dir = Vfs::get_home_dir() . '/share/';
		Vfs::mkdir($dir);
		$this->files = $this->addFiles($dir);

		// Also create one that should not be accessed
		$this->files[] = $this->no_access = Vfs::get_home_dir() . '/not_shared_file.txt';
		$this->assertTrue(
				file_put_contents(Vfs::PREFIX . $this->no_access, "This file is not shared") !== FALSE,
				'Unable to write test file "' . Vfs::PREFIX . $this->no_access . '" - check file permissions for CLI user'
		);

		// Create and use link
		$this->getShareExtra($dir, Sharing::READONLY, $extra);

		$share = call_user_func([$this,$create],$dir, Sharing::READONLY, $extra);
		$link = Vfs\Sharing::share2link($share);

		// Now log out and log in as someone else
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();
		LoggedInTest::tearDownAfterClass();

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
					Vfs::is_dir($path) ? Vfs::mkdir($path,0750,true) : Vfs::touch($path),
					"Share path $path does not exist"
			);
		}

		// Create share
		$this->shares[] = $share = TestHiddenSharing::create('', $path, $mode, $name, $recipients, $extra);

		return $share;
	}

	/**
	 * Test that a share of a directory only gives access to that directory, and any other
	 * directories that the sharer has are unavailable
	 *
	 * This checks an existing user that is logged in when they follow the share link.
	 *
	 * ** CURRENTLY we make a new session anyway, so no changes should be visible on VFS **
	 */
	public function testShareKeepSession()
	{
		// This test hands its own session to the webserver so the share is opened as a known
		// user.  Session::verify() rejects a session whose stored IP is empty when the instance
		// has sessions_checkip on, and a session created from the command line has no IP, so
		// there the server can only ever fall back to an anonymous one and the share is served
		// as if nobody was logged in.  That is a property of the install, decided before the
		// request is made - not something inferred from how the response came back.
		if(!empty($GLOBALS['egw_info']['server']['sessions_checkip']))
		{
			$this->markTestSkipped('Instance has sessions_checkip enabled: a session created on the command line has no IP and can never be verified by the webserver');
		}

		$dir = '';
		$link = $this->setupShare($dir);

		LoggedInTest::load_egw($this->account['account_lid'],$this->account['account_passwd']);
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();

		// Check that we can't access the no_access file
		$this->assertFalse(Vfs::is_readable($this->no_access), "Could access the not-readable file even before we started.");

		// What's our VFS like?
		$pre_fstab = Vfs::mount();
		$vfs_options = array(
				'maxdepth' => 3,
			// Exclude a lot of the stuff we're not interested in
				'path_preg' => '#^' . Vfs::PREFIX . '\/(?!apps|templates|etemplates|apps-backup).*#'
		);

		//$pre_files = Vfs::find('/', $vfs_options);

		// A share opened by a logged-in user does NOT give the filemanager listing an anonymous
		// visitor gets: Sharing\Ui::share_received() offers to mount the share into the user's
		// own VFS, so this answers filemanager.file_share_received.  This test asked for a
		// nextmatch and got away with it only because it never reached a webserver.
		$data = array();
		$curl = null;
		$form = $this->getShare($link, $data, true, $curl, false);
		$this->assertNotNull($form, "Could not read the share link '$link'");
		$this->assertEquals('filemanager.file_share_received', $data['name'],
			"A logged-in user following the share link did not get the mount dialog");

		// Nothing is mounted until that dialog is submitted, so the share must not have given
		// this user anything yet - which is what the rest of this test checks.
		$post_mount_vfs = Vfs::mount();
		//$post_files = Vfs::find('/', $vfs_options);

		// Check that our fstab was not changed
		$this->assertEquals(count($pre_fstab), count($post_mount_vfs), "fstab mounts changed");

		// Check we can't find the non-shared file in VFS
		$this->assertFalse(Vfs::is_readable($this->no_access),
				"Could access the not-readable file '$this->no_access' after accessing the share."
		);

		// The dialog names the share it is offering, and only that share - the share we made,
		// not the file we deliberately left out of it
		$this->assertEquals($this->share_token($link), $data['data']['content']['share_token'] ?? null,
			"The mount dialog was for a different share");
		$this->assertStringNotContainsString(Vfs::basename($this->no_access), $data['data']['content']['share_path'] ?? '',
			"The mount dialog offered the file we should not have access to ({$this->no_access})");
	}

	/**
	 * Token out of a share link
	 *
	 * @param string $link
	 * @return string
	 */
	protected function share_token(string $link) : string
	{
		return substr($link, strrpos($link, '/') + 1);
	}


	/**
	 * Test that a share of a directory only gives access to that directory, and any other
	 * directories that the sharer has are unavailable
	 *
	 * This checks from one logged in user to anonymous with a new session
	 */
	public function testShareNewSession()
	{
		$dir = '';
		$link = $this->setupShare($dir);

		// Now follow the link - this _should_ be enough to get it added
		//$mimetype = Vfs::mime_content_type($dir);
		//$this->checkSharedFile($link, $mimetype);

		// Read the etemplate
		$data = array();
		$form = $this->getShare($link, $data, false);
		$this->assertNotNull($form, "Could not read the share link");
		$rows = $data['data']['content']['nm']['rows'];


		// Check we can't find the non-shared file
		$result = array_filter($rows, function($v) {
			return $v['name'] == $this->no_access;
		});
		$this->assertEmpty($result, "Found the file we shouldn't have access to ({$this->no_access})");

		// Check that we can find the shared file(s) in the form / nm list
		// Don't test the no-access one (done above), and no good way to get the sub-dir file either,
		// since nm only has top-level files and we can't switch the filter
		$this->checkNextmatch($dir, array_diff($this->files, [$this->no_access, $dir."sub_dir/subdir_test_file.txt"]), $rows);
	}


	/**
	 * Test that a share of a directory with hidden upload subdirectory only gives access to that directory,
	 * and the upload directory as well as any other directories that the sharer has are unavailable
	 *
	 * This checks from one logged in user to anonymous with a new session
	 */
	public function testShareHiddenUploadNewSession()
	{
		$dir = '';
		$link = $this->setupShare($dir, [], 'createHiddenUploadShare');

		// Now follow the link - this _should_ be enough to get it added
		//$mimetype = Vfs::mime_content_type($dir);
		//$this->checkSharedFile($link, $mimetype);

		// Read the etemplate
		$data = array();
		$form = $this->getShare($link, $data, false);
		$this->assertNotNull($form, "Could not read the share link");
		$rows = array_values($data['data']['content']['nm']['rows']);

		// Check we can't find the non-shared file
		$result = array_filter($rows, function($v) {
			return $v['name'] == $this->no_access;
		});
		$this->assertEmpty($result, "Found the file we shouldn't have access to ({$this->no_access})");

		// Test that we can't see the hidden upload directory
		$result = array_filter($rows, function($v) {
			return $v['name'] == 'Upload';
		});
		$this->assertEmpty($result, "Hidden upload directory is visible");


		// Check that we can find the shared file(s) in the form / nm list
		// Don't test the no-access one (done above), and no good way to get the sub-dir file either,
		// since nm only has top-level files and we can't switch the filter
		$this->checkNextmatch($dir, array_diff($this->files, [$this->no_access, $dir."sub_dir/subdir_test_file.txt"]), $rows);
	}

	/**
	 * Check the nextmatch rows to see if all the expected files (in the given directory) are present
	 *
	 * @param $dir Current working directory, share target
	 * @param $check_files List of files that should be there
	 * @param $rows Nextmatch rows
	 */
	protected function checkNextmatch($dir, $check_files, $rows)
	{
		foreach($check_files as $file)
		{
			$relative_file = str_replace($dir,'',$file);

			if($relative_file[strlen($relative_file)-1] == '/')
			{
				$relative_file = substr($relative_file, 0, -1);
			}
			$result = array_filter($rows, function($v) use ($relative_file) {
				return $v['name'] == $relative_file;
			});
			$this->assertNotEmpty($result, "Couldn't find shared file '$file'");
		}

	}

	/**
	 * Test that a share of a single file gives the file (uses WebDAV)
	 */
	public function testSingleFile()
	{
		$dir = Vfs::get_home_dir().'/';

		// Plain text file
		$file = $dir.'test_file.txt';
		$content = 'Testing that sharing a single (non-editable) file gives us the file.';
		$this->assertTrue(
				file_put_contents(Vfs::PREFIX.$file, $content) !== FALSE,
				'Unable to write test file "' . Vfs::PREFIX . $file .'" - check file permissions for CLI user'
		);
		$this->files[] = $file;

		$mimetype = Vfs::mime_content_type($file);

		// Create and use link
		$extra = array();
		$this->getShareExtra($file, Sharing::READONLY, $extra);

		$share = $this->createShare($file, Sharing::READONLY, $extra);
		$link = Vfs\Sharing::share2link($share);

		// Re-init, since they look at user, fstab, etc.
		// Also, further tests that access the filesystem fail if we don't
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();

		// Log out & clear cache
		LoggedInTest::tearDownAfterClass();

		$this->checkSharedFile($link, $mimetype, $share);
	}

	/**
	 * Test that a single-file share served to a DIFFERENT logged-in user
	 * (keep_session branch) actually delivers the file, not an empty body.
	 *
	 * This is the regression test for the bug where the keep_session branch
	 * did not set resolve_url pointing at the share OWNER's file, so a
	 * logged-in non-owner got an empty response (HTTP 200, 0 bytes).
	 */
	public function testSingleFileKeepSession()
	{
		$dir = Vfs::get_home_dir().'/';

		// Plain text file
		$file = $dir.'test_file.txt';
		$content = 'Testing that sharing a single file to another logged-in user gives us the file.';
		$this->assertTrue(
			file_put_contents(Vfs::PREFIX.$file, $content) !== FALSE,
			'Unable to write test file "' . Vfs::PREFIX . $file .'" - check file permissions for CLI user'
		);
		$this->files[] = $file;

		$mimetype = Vfs::mime_content_type($file);

		// Create and use link (as the original user)
		$extra = array();
		$this->getShareExtra($file, Sharing::READONLY, $extra);

		$share = $this->createShare($file, Sharing::READONLY, $extra);
		$link = Vfs\Sharing::share2link($share);

		// Re-init, since they look at user, fstab, etc.
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();

		// Now follow the link as a DIFFERENT logged-in user (keep_session branch)
		LoggedInTest::load_egw($this->account['account_lid'],$this->account['account_passwd']);
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();

		// The single file is served via WebDAV (ServeRequest), not the filemanager UI, so
		// check the actual download delivers the real content, not an empty body.  Asking
		// getShare() for a filemanager template here contradicted that: it parses the
		// nextmatch out of the response, and a raw file has none.  That mismatch was hidden
		// while an unserved share counted as "skipped" - with the file served, it failed.
		$this->checkSharedFile($link, $mimetype, $share, $content);
	}
}
