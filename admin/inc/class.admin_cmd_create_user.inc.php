<?php
/**
 * EGroupware admin - admin command: create a new user without an admin creator
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2026 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * admin command: create a new user, allowing a non-admin (or anonymous) creator
 *
 * Unlike admin_cmd_edit_user, whose creator must hold admin rights (checked via
 * admin_cmd::_check_admin()), this command may explicitly waive that check via
 * skipAdminCheck() - but only exists on this class, so no other admin_cmd subclass
 * (edit_group, delete_account, ...) can ever be waived this way just by holding a
 * reference to the object.
 *
 * To limit what such a waived command is allowed to do, this class:
 * - refuses (in exec()) to touch anything but a brand-new account
 * - refuses (in skipAdminCheck()) to skip the check if any of the target account's
 *   groups themselves carry admin rights
 *
 * Callers remain responsible for only ever populating $set with fields they are
 * willing to let an unauthenticated/non-admin creator set (eg. registration_bo
 * constrains account_primary_group/account_groups to the app's configured groups
 * before constructing this command).
 */
class admin_cmd_create_user extends admin_cmd_edit_user
{
	/**
	 * Whether skipAdminCheck() was called (and passed its own checks)
	 *
	 * @var boolean
	 */
	protected $adminCheckSkipped = false;

	/**
	 * Constructor
	 *
	 * @param array $set array with all data of the new account
	 * @param string $password =null password
	 * @param boolean $run_addaccount_hook =null default run addaccount hook
	 */
	function __construct(array $set, $password=null, $run_addaccount_hook=null)
	{
		parent::__construct(false, $set, $password, $run_addaccount_hook);
	}

	/**
	 * Call after construction, before run(), to allow a non-admin (or anonymous)
	 * creator to run this command.
	 *
	 * Refuses to skip the check if any of the account's target groups (primary or
	 * secondary) themselves carry admin rights: no legitimate use of this class
	 * (eg. self-registration) should ever land a new account in an admin group,
	 * whatever the caller's $set['account_groups'] ends up containing.
	 *
	 * @throws Api\Exception\NoPermission\Admin if a target group has admin rights
	 */
	public function skipAdminCheck()
	{
		foreach ((array)($this->set['account_groups'] ?? []) as $gid)
		{
			admin_cmd::_instanciate_acl($gid);
			if (admin_cmd::$acl->check('run', 1, 'admin'))
			{
				throw new Api\Exception\NoPermission\Admin();
			}
		}
		$this->adminCheckSkipped = true;
	}

	/**
	 * Executes the command
	 *
	 * Only ever allowed to create a brand-new account, never to edit an existing one -
	 * that keeps a waived admin-check strictly scoped to account creation.
	 *
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Api\Exception\WrongUserinput if targeting an existing account
	 */
	protected function exec($check_only=false)
	{
		if ($this->account)
		{
			throw new Api\Exception\WrongUserinput('admin_cmd_create_user must not target an existing account!');
		}
		return parent::exec($check_only);
	}

	/**
	 * @param string $extra_acl =null further admin rights to check, eg. 'account_access'
	 * @param int $extra_deny =null further admin rights to check, eg. 16 = deny edit Api\Accounts
	 * @throws Api\Exception\NoPermission\Admin
	 */
	protected function _check_admin($extra_acl=null, $extra_deny=null)
	{
		if ($this->adminCheckSkipped)
		{
			return;
		}
		parent::_check_admin($extra_acl, $extra_deny);
	}
}
