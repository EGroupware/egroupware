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

/**
 * Regression coverage for the PoC scenario of the sink half of GHSA-q2j3-83cg-vg83
 * (Framework\Ajax::ajax_exec cross-app dispatch): a non-admin caller must not be able to delete
 * an arbitrary account via admin_account::delete().
 *
 * commit c311428a28 (the dispatcher-wiring half is covered by
 * api/tests/Framework/AjaxExecSecurityTest.php) also added a fail-closed admin-membership check
 * to admin_account::delete() itself, which previously relied only on
 * `$GLOBALS['egw']->acl->check('account_access',32,'admin')` - falsy ("not denied") for a
 * non-admin with no admin ACL rows at all, same fail-open shape as the admin_passwordreset/
 * GHSA-76q5 family.
 *
 * IMPORTANT finding from building this test: that check turns out to be REDUNDANT with a deeper,
 * independent layer - admin_cmd_delete_account::exec() (reached via _deferred_delete() further
 * down the same method) calls admin_cmd::_check_admin(), which was separately hardened this same
 * session (commit 19bb2b0ea6, the `&&`->`||` fix). Reverting ONLY admin_account::delete()'s own
 * check does NOT let a non-admin actually delete an account - _check_admin() still blocks it.
 * Reverting BOTH together does let the deletion through (verified: the target account was
 * genuinely deleted). So this test validates the AGGREGATE PoC-scenario property ("can a
 * non-admin delete an account this way at all"), not specifically admin_account::delete()'s own
 * check in isolation - a regression confined to just that one check, with _check_admin() staying
 * fixed, would NOT be caught here. Defense in depth is a good thing to have; it just means this
 * particular test can't be pinned to one line the way most others in this project are.
 *
 * admin_account::delete()'s denial path calls Framework::window_close(), which unconditionally
 * exit()s - fatal to whatever process runs it. PHPUnit's own #[RunInSeparateProcess] isolation
 * reports a test as "ended unexpectedly" whenever the isolated child calls exit() before handing
 * back its serialized result, which CI (correctly) treats as a failed build - an "expected" error
 * is still an error there. Registered shutdown functions were tried as a way to capture evidence
 * before that exit and don't reliably run to completion in PHPUnit 12's isolated child in this
 * environment either (confirmed empirically). A first version of this test used
 * #[RunInSeparateProcess] and appeared to demonstrate the vulnerability when only
 * admin_account.inc.php was reverted - that was almost certainly a false positive from that
 * attribute's default global-state-export leaking the parent test's transient admin identity
 * (from setUpBeforeClass()'s asAdminStatic() closure) into the isolated child, not a real
 * demonstration of a non-admin succeeding.
 *
 * So this spawns a genuinely independent PHP CLI subprocess itself (proc_open, not PHPUnit's
 * isolation), logged in FRESH as the ordinary, non-admin phpunit test user via
 * LoggedInTest::load_egw() - a real, unrelated OS process with no shared state, so its exit()
 * cannot affect the PHPUnit process running this test at all, and this test method itself never
 * errors: it makes an ordinary assertion about the DURABLE, EXTERNAL side effect the subprocess
 * left behind - does the target account still exist in the database afterward? - and returns
 * normally, like any other test.
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

	public function testDeleteAsNonAdminIsDeniedAndAccountSurvives()
	{
		$script = <<<'PHP'
<?php
require_once '{{EGW_SERVER_ROOT}}/doc/phpunit_bootstrap.php';
require_once '{{EGW_SERVER_ROOT}}/api/tests/LoggedInTest.php';
EGroupware\Api\LoggedInTest::load_egw({{EGW_USER}}, {{EGW_PASSWORD}});

$account_id = $GLOBALS['egw']->accounts->name2id('admin_account_delete_acl_test');
admin_account::delete([
	'account_id' => [$account_id],
	'delete' => 1,
]);
PHP;
		// pass the PARENT process's already-resolved, already-proven-good credentials literally,
		// rather than letting the subprocess re-derive them from its own environment - whatever
		// doc/phpunit_bootstrap.php falls back to there (env vars/defaults) is not guaranteed to
		// match what THIS LoggedInTest suite actually authenticated with (caught in CI: the
		// subprocess got "bad login or password" while the parent process's login worked fine)
		$script = str_replace(
			['{{EGW_SERVER_ROOT}}', '{{EGW_USER}}', '{{EGW_PASSWORD}}'],
			[EGW_SERVER_ROOT, var_export($GLOBALS['EGW_USER'], true), var_export($GLOBALS['EGW_PASSWORD'], true)],
			$script
		);
		$scriptFile = tempnam(sys_get_temp_dir(), 'egw_admin_account_delete_script_');
		file_put_contents($scriptFile, $script);

		$process = proc_open([PHP_BINARY, '-f', $scriptFile], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$this->assertIsResource($process, 'failed to spawn the subprocess');
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);
		@unlink($scriptFile);

		$this->assertNotFalse($GLOBALS['egw']->accounts->name2id(self::ACCOUNT_LID),
			'admin_account::delete() must deny a non-admin caller - '.
			'if this fails, the target account was actually deleted by a non-admin session');
	}
}
