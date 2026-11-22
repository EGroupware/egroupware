<?php

/**
 * Test that accepting a share persists a usable vfs_fstab mount
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/SharingBase.php';
require_once __DIR__ . '/../../../admin/inc/class.admin_cmd_edit_user.inc.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Vfs;

/**
 * Accepting a share (the "Accept" button on the share_received dialog, filemanager.Sharing.Ui::
 * handle_share_received()) persists a vfs_fstab entry via Vfs::mount()'s own $persistent_mount
 * handling, pointing at the share's own share2url(), so the share stays mounted across later
 * sessions, not just the one that accepted it.
 *
 * This is a safety net for that legitimate path, ahead of any change to how vfs_fstab entries
 * are trusted - it must keep passing once such a change lands. It stops short of reading the
 * shared file's content through the mount on a later session: that end-to-end read hits a
 * separate, pre-existing issue unrelated to vfs_fstab trust, reproducible on unmodified code
 * with either a filesystem:// or the default backend - out of scope here.
 */
class ShareReceivedMountTest extends SharingBase
{
	protected $recipient_lid = 'share_mount_test_recipient';
	protected $recipient_passwd = 'passw0rd-fixture';
	protected $recipient_id;

	protected function setUp() : void
	{
		parent::setUp();
		if (($id = $GLOBALS['egw']->accounts->name2id($this->recipient_lid)))
		{
			$GLOBALS['egw']->accounts->delete($id);
		}
		$this->asAdmin(function()
		{
			$command = new \admin_cmd_edit_user(false, array(
				'account_lid' => $this->recipient_lid,
				'account_firstname' => 'Share',
				'account_lastname' => 'Recipient',
				'account_passwd' => $this->recipient_passwd,
				'account_passwd_2' => $this->recipient_passwd,
			));
			$command->comment = 'Needed for unit test '.$this->name();
			$command->run();
			$this->recipient_id = $command->account;
		});
	}

	protected function tearDown() : void
	{
		LoggedInTest::setUpBeforeClass();
		parent::tearDown();
		if ($this->recipient_id)
		{
			$GLOBALS['egw']->accounts->delete($this->recipient_id);
		}
	}

	/**
	 * Accepting a share must persist a vfs_fstab mount that is still there, unchanged, on a
	 * later, fresh session - not just within the same request that accepted it
	 */
	public function testAcceptedShareMountSurvivesFreshSession()
	{
		// Share a filesystem-backed directory (real files, reachable the same way a share's own
		// backend is) as the currently logged-in (sharer) test user
		$dir = Vfs::get_home_dir().'/share_mount_test/';
		$this->mountFilesystem($dir);
		$marker = 'EGW-SHARE-MOUNT-'.uniqid();
		$this->assertNotFalse(file_put_contents(Vfs::PREFIX.$dir.'secret.txt', $marker),
			'could not write the fixture file to share');

		$extra = array();
		$this->getShareExtra($dir, Sharing::READONLY, $extra);
		$share = $this->createShare($dir, Sharing::READONLY, $extra);
		$token = $share['share_token'];

		// Switch to the recipient and accept the share - reproducing exactly what
		// share_received()'s own GET path computes for the dialog (share_root, mount_location, url)
		$this->switchUser($this->recipient_lid, $this->recipient_passwd);

		$share_row = Sharing::so()->read(array('share_token' => $token));
		$this->assertNotEmpty($share_row, 'share not found by token');
		Sharing::setup_share(true, $share_row);
		$mount_location = Vfs::basename($share_row['share_root']);
		$url = Sharing\StreamWrapper::share2url($share_row);
		$mount_path = Vfs::dirname($share_row['share_root']).'/'.$mount_location;

		// the actual mounting+persisting Ui::handle_share_received() does for 'permanent' => true -
		// not called through that method itself, since it ends in Api\Framework::window_close(),
		// which terminates the process (fine for a real ajax popup, fatal for a test run)
		Vfs::$is_root = true;
		Vfs::umount($share_row['share_root']);
		$this->assertTrue(Vfs::mount($url, $mount_path, false, $this->recipient_id),
			'could not mount the accepted share');
		Vfs::$is_root = false;

		// Confirm the preference was actually persisted to the DB, not just held for this request
		$prefs = new Api\Preferences($this->recipient_id);
		$prefs->read_repository();
		$this->assertArrayHasKey($mount_path, $prefs->data['common']['vfs_fstab'] ?? array(),
			'accepting the share did not persist a vfs_fstab entry');

		// A later request re-reads preferences from the DB on its own - switchUser() forces
		// exactly that by tearing the session down and re-bootstrapping it, going through
		// StreamWrapper::init_static()'s own validatedFstab() trust check along the way
		$this->switchUser($this->recipient_lid, $this->recipient_passwd);

		$this->assertSame($url, $GLOBALS['egw_info']['user']['preferences']['common']['vfs_fstab'][$mount_path] ?? null,
			'the accepted share mount did not survive a fresh session unchanged');
	}

	/**
	 * GHSA-gq47-f3vp-h8p4: a vfs_fstab entry asserting a DIFFERENT user, with no backing
	 * egw_sharing row, must be dropped - not merged into the trusted fstab used for VFS ACL
	 * checks. Without this, any logged in user could set such an entry (eg. via the now-blocked
	 * ajax_set_preference()) to be treated as someone else, including an admin, for ACL purposes.
	 *
	 * Persists the preference directly, the same way Vfs::mount()'s own $persistent_mount
	 * handling does (not through ajax_set_preference(), which this advisory also closes off
	 * separately) - this test is specifically about the reverse-validation in StreamWrapper.
	 */
	public function testInjectedCrossUserMountWithNoShareIsDropped()
	{
		$admin_lid = $GLOBALS['EGW_ADMIN_USER'];
		$mount_path = '/home/'.$this->recipient_lid.'/not_a_real_share';
		$malicious_url = 'vfs://'.$admin_lid.'@default/home/'.$admin_lid;

		$this->switchUser($this->recipient_lid, $this->recipient_passwd);

		$prefs = new Api\Preferences($this->recipient_id);
		$prefs->read_repository();
		$prefs->user['common']['vfs_fstab'][$mount_path] = $malicious_url;
		$prefs->save_repository();

		// A later request re-reads preferences from the DB on its own
		$this->switchUser($this->recipient_lid, $this->recipient_passwd);

		$this->assertArrayNotHasKey($mount_path, Vfs::mount(),
			'an injected vfs_fstab entry asserting another user, with no backing share, was trusted');
	}
}
