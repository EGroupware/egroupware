<?php

/**
 * Tests for admin_cmd_create_user
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// test base providing common stuff
require_once __DIR__.'/CommandBase.php';

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * admin_cmd_create_user is what registration_bo now uses to create self-registered accounts
 * while the current session has no admin rights (the default LoggedInTest session is exactly
 * such a non-admin account, same as registration's real 'anonymous' session).
 *
 * Coverage:
 * - skipAdminCheck() lets a non-admin creator through (the actual registration use-case)
 * - without skipAdminCheck(), a non-admin creator is still rejected - regression guard for the
 *   admin_cmd::_check_admin() fail-open bug (the `&&`/`||` fix)
 * - skipAdminCheck() itself refuses if any target group carries admin rights
 * - exec() refuses to ever touch an existing account, even if ->account gets set post-construction
 */
class CreateUserCommandTest extends CommandBase {

	protected $account_id;
	protected $group_id;

	protected $account = array(
		'account_lid' => 'create_user_cmd_test',
		'account_firstname' => 'CreateUserCommand',
		'account_lastname' => 'Test',
		'account_passwd' => 'Some$trongTestPassw0rd!',
		'account_passwd2' => 'Some$trongTestPassw0rd!',
	);

	protected function setUp() : void
	{
		if(($account_id = $GLOBALS['egw']->accounts->name2id($this->account['account_lid'])))
		{
			// Delete if there in case something went wrong
			$GLOBALS['egw']->accounts->delete($account_id);
		}
	}

	protected function tearDown() : void
	{
		if($this->account_id && $GLOBALS['egw']->accounts->id2name($this->account_id))
		{
			$GLOBALS['egw']->accounts->delete($this->account_id);
		}
		if($this->group_id)
		{
			(new Acl())->delete_repository('admin', 'run', $this->group_id);
			$GLOBALS['egw']->accounts->delete($this->group_id);
		}
		parent::tearDown();
	}

	/**
	 * The default LoggedInTest session (EGW_USER) is a normal, non-admin account - exactly what
	 * registration_bo's real (anonymous) session is. skipAdminCheck() must let creation through.
	 */
	public function testCreateAsNonAdminWithSkip()
	{
		$log_count = $this->get_log_count();

		$command = new admin_cmd_create_user($this->account);
		$command->comment = 'Needed for unit test '.$this->name();
		$command->skipAdminCheck();
		$command->run();
		$this->account_id = $command->account;

		$this->assertNotEmpty($this->account_id, 'Did not create test user account');
		$this->assertGreaterThan($log_count, $this->get_log_count(), "Command ($command) did not log");
	}

	/**
	 * Without skipAdminCheck(), the current non-admin session must NOT be able to create an
	 * account via this command either.
	 */
	public function testCreateWithoutSkipFailsAsNonAdmin()
	{
		$this->expectException(Api\Exception\NoPermission\Admin::class);

		$command = new admin_cmd_create_user($this->account);
		$command->run();
	}

	/**
	 * skipAdminCheck() must refuse if any of the account's target groups carry admin rights -
	 * defense in depth, independent of whatever populated account_groups/primary_group.
	 */
	public function testSkipAdminCheckRefusesAdminGroup()
	{
		$this->asAdmin(function()
		{
			$group_cmd = new admin_cmd_edit_group(false, array(
				'account_lid' => 'create_user_cmd_test_admin_group',
				'account_members' => array($GLOBALS['egw_info']['user']['account_id']),
			));
			$group_cmd->run();
			$this->group_id = $group_cmd->account;
			(new Acl())->add_repository('admin', 'run', $this->group_id, 1);
		});
		$this->assertNotEmpty($this->group_id, 'Did not create test group');

		$this->expectException(Api\Exception\NoPermission\Admin::class);

		$account = $this->account;
		$account['account_groups'] = array($this->group_id);

		(new admin_cmd_create_user($account))->skipAdminCheck();
	}

	/**
	 * A plain group with NO acl rows at all (the normal state of a real self-registration
	 * group, and of any freshly created group) must NOT be mistaken for an admin group.
	 *
	 * Regression guard: Acl::check()/get_rights() return an unconditional "allowed" for an
	 * account/group with zero acl rows whenever server config acl_default != 'deny' - which is
	 * the default on a fresh install (unlike this dev instance, which is why this only failed
	 * in CI). skipAdminCheck() must not be fooled by that into refusing a harmless group.
	 */
	public function testSkipAdminCheckAllowsPlainUnconfiguredGroup()
	{
		$this->asAdmin(function()
		{
			$group_cmd = new admin_cmd_edit_group(false, array(
				'account_lid' => 'create_user_cmd_test_plain_group',
				'account_members' => array($GLOBALS['egw_info']['user']['account_id']),
			));
			$group_cmd->run();
			$this->group_id = $group_cmd->account;
		});
		$this->assertNotEmpty($this->group_id, 'Did not create test group');

		// force the fail-open Acl::get_rights() fallback path, whatever this instance's
		// real acl_default config is, so the test actually exercises the CI failure mode
		$orig_acl_default = $GLOBALS['egw_info']['server']['acl_default'] ?? null;
		$GLOBALS['egw_info']['server']['acl_default'] = 'allow';
		try
		{
			$account = $this->account;
			$account['account_groups'] = array($this->group_id);

			// must not throw
			(new admin_cmd_create_user($account))->skipAdminCheck();
			$this->assertTrue(true);
		}
		finally
		{
			$GLOBALS['egw_info']['server']['acl_default'] = $orig_acl_default;
		}
	}

	/**
	 * exec() must refuse to touch an existing account, even if something sets ->account after
	 * construction - keeps a waived admin-check strictly scoped to creating NEW accounts.
	 */
	public function testExecRefusesExistingAccount()
	{
		$this->asAdmin(function()
		{
			$user_cmd = new admin_cmd_edit_user(false, $this->account);
			$user_cmd->run();
			$this->account_id = $user_cmd->account;
		});
		$this->assertNotEmpty($this->account_id, 'Did not create pre-existing test account');

		$this->expectException(Api\Exception\WrongUserinput::class);

		$command = new admin_cmd_create_user($this->account);
		$command->skipAdminCheck();
		$command->account = $this->account_id;
		$command->run();
	}
}
