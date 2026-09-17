<?php

/**
 * Tests for admin_account::delete()'s admin-membership check
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// test base providing common stuff
require_once __DIR__.'/CommandBase.php';

use EGroupware\Api;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Regression coverage for the sink half of GHSA-q2j3-83cg-vg83 (Framework\Ajax::ajax_exec
 * cross-app dispatch), fixed in the same commit (c311428a28) as the dispatcher-wiring covered by
 * api/tests/Framework/AjaxExecSecurityTest.php.
 *
 * admin_account::delete() itself had a fail-open admin-membership check: only
 * `$GLOBALS['egw']->acl->check('account_access',32,'admin')`, with no
 * `empty($GLOBALS['egw_info']['user']['apps']['admin'])` guard in front of it - a non-admin has
 * no admin ACL rows at all, so that check alone returns falsy ("not denied") for them, same
 * fail-open shape as the admin_passwordreset/GHSA-76q5 family. A non-admin who reached this
 * method (eg. via the ajax_exec cross-app dispatch bug that same commit also fixed) could delete
 * ANY account.
 *
 * admin_account::delete()'s denial path calls Framework::window_close(), which unconditionally
 * exit()s - fatal to whatever process runs it, and a class-method call (unlike a bare function)
 * can't be intercepted via a namespace shim the way api/tests/SharingPathTraversalTest.php
 * handles its own exit()-adjacent check. Rather than trying to survive past the exit() in-process
 * (PHPUnit's own process-isolation child does not run registered shutdown functions to completion
 * in this environment - confirmed empirically, not investigated further), this test checks the
 * DURABLE, EXTERNAL side effect instead: does the target account still exist in the database
 * afterward? The trigger test's "ended unexpectedly" error status is expected and harmless; the
 * actual pass/fail signal comes from the separate, normal follow-up test, and both tests re-derive
 * the target account_id fresh from the database by account_lid rather than relying on any
 * in-memory state, since the isolated test runs in a genuinely separate PHP process that does not
 * share this class's static properties with the one setUpBeforeClass() ran in.
 */
class AdminAccountDeleteAclTest extends CommandBase
{
	const ACCOUNT_LID = 'admin_account_delete_acl_test';

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		static::asAdminStatic(function()
		{
			if (($existing = $GLOBALS['egw']->accounts->name2id(self::ACCOUNT_LID)))
			{
				$GLOBALS['egw']->accounts->delete($existing);
			}
			$cmd = new admin_cmd_edit_user(false, [
				'account_lid' => self::ACCOUNT_LID,
				'account_firstname' => 'AdminAccountDelete',
				'account_lastname' => 'AclTest',
			]);
			$cmd->run();
		});
	}

	public static function tearDownAfterClass() : void
	{
		static::asAdminStatic(function()
		{
			if (($account_id = $GLOBALS['egw']->accounts->name2id(self::ACCOUNT_LID)))
			{
				$GLOBALS['egw']->accounts->delete($account_id);
			}
		});
		parent::tearDownAfterClass();
	}

	#[RunInSeparateProcess]
	public function testDeleteAsNonAdminTriggersDenial()
	{
		$account_id = $GLOBALS['egw']->accounts->name2id(self::ACCOUNT_LID);

		admin_account::delete([
			'account_id' => [$account_id],
			'delete' => 1,
		]);
	}

	public function testAccountSurvivedNonAdminDeleteAttempt()
	{
		$this->assertNotFalse($GLOBALS['egw']->accounts->name2id(self::ACCOUNT_LID),
			'admin_account::delete() must deny a non-admin caller BEFORE reaching _deferred_delete() - '.
			'if this fails, the target account was actually deleted by a non-admin session');
	}
}
