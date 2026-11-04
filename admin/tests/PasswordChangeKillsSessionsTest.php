<?php
/**
 * EGroupware Admin: password change / account disable must end existing sessions
 *
 * Regression tests: previously neither admin_cmd_edit_user's direct SQL password save,
 * nor Api\Auth::change_password(), nor disabling an account, ended any session issued
 * before the change - a stolen session cookie kept working after the victim (or an
 * admin) changed the password, or after an admin disabled the account.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once __DIR__.'/CommandBase.php';

use EGroupware\Api;

class PasswordChangeKillsSessionsTest extends CommandBase
{
	protected $account_id;

	protected $account = array(
		'account_lid' => 'sesskill_test',
		'account_firstname' => 'SessKill',
		'account_lastname' => 'Test',
	);

	protected function tearDown() : void
	{
		if ($this->account_id)
		{
			$GLOBALS['egw']->accounts->delete($this->account_id);
			$this->account_id = null;
		}
		parent::tearDown();
	}

	/**
	 * Insert a row into egw_access_log simulating an other, currently active session
	 *
	 * @param int $account_id
	 * @return int access-log row id, as used by Session::destroy_all()/session_list()
	 */
	protected function fakeActiveSession($account_id)
	{
		$GLOBALS['egw']->db->insert('egw_access_log', array(
			'session_php'    => 'phpunit-fake-'.$account_id.'-'.mt_rand(),
			'loginid'        => 'phpunit',
			'ip'             => '127.0.0.1',
			'li'             => time(),
			'account_id'     => $account_id,
			'session_dla'    => time(),
		), false, __LINE__, __FILE__);

		return (int)$GLOBALS['egw']->db->get_last_insert_id('egw_access_log', 'sessionid');
	}

	protected function activeSessionCount($account_id)
	{
		return (int)Api\Session::session_count(array('account_id' => $account_id));
	}

	/**
	 * Seed a fake active session for $account_id whose stored (encrypted) password is
	 * $password - mimics what a real login stores via Session::__set('passwd', ...), so
	 * Session::get_password_from_session() has something real to decrypt.
	 */
	protected function seedFakeSessionPassword($account_id, $password)
	{
		$session_php = 'phpunit-pwseed-'.$account_id.'-'.mt_rand();
		$GLOBALS['egw']->db->insert('egw_access_log', array(
			'session_php'    => $session_php,
			'loginid'        => 'phpunit',
			'ip'             => '127.0.0.1',
			'li'             => time(),
			'account_id'     => $account_id,
			'session_dla'    => time(),
		), false, __LINE__, __FILE__);

		$orig_sessionid = session_id();
		$orig_active = session_status() === PHP_SESSION_ACTIVE;
		if ($orig_active)
		{
			session_write_close();
		}

		session_id($session_php);
		session_start();
		$pw_enc = null;
		$_SESSION[Api\Session::EGW_SESSION_VAR]['session_password'] = Api\Mail\Credentials::encrypt(
			$password, $account_id, $pw_enc, true
		);
		session_write_close();

		if ($orig_sessionid)
		{
			session_id($orig_sessionid);
			if ($orig_active)
			{
				session_start();
			}
		}
	}

	/**
	 * Admin changing another user's password (admin_cmd_edit_user) must end all
	 * of that user's existing sessions - covers both the direct SQL-backend save
	 * path and the Auth::change_password() path for other backends.
	 */
	public function testAdminPasswordChangeKillsOtherSessions()
	{
		$this->asAdmin(function()
		{
			$command = new admin_cmd_edit_user(false, $this->account + array(
				'account_passwd'   => 'Initial-Pw-234',
				'account_passwd_2' => 'Initial-Pw-234',
			));
			$command->comment = 'Setup for unit test '.$this->name();
			$command->run();
			$this->account_id = $command->account;

			$this->fakeActiveSession($this->account_id);
			$this->fakeActiveSession($this->account_id);
			$this->assertSame(2, $this->activeSessionCount($this->account_id),
				'Fixture sessions not visible via Session::session_count()');

			$command = new admin_cmd_edit_user($this->account_id, array(
				'account_passwd'   => 'Changed-Pw-234',
				'account_passwd_2' => 'Changed-Pw-234',
			));
			$command->comment = 'Password change for unit test '.$this->name();
			$command->run();

			$this->assertSame(0, $this->activeSessionCount($this->account_id),
				'Admin changing a users password did not end their existing sessions');
		});
	}

	/**
	 * Disabling an account must make Accounts::is_active() false immediately -
	 * that's what Session::verify()/create() now check on every request, so an
	 * existing session for a just-disabled account stops working on its very
	 * next request without needing an explicit kill-all call.
	 */
	public function testDisablingAccountMakesItInactive()
	{
		$this->asAdmin(function()
		{
			$command = new admin_cmd_edit_user(false, $this->account);
			$command->comment = 'Setup for unit test '.$this->name();
			$command->run();
			$this->account_id = $command->account;

			$this->assertTrue(Api\Accounts::is_active($this->account_id), 'Freshly created account should be active');

			// account_expires=0 --> admin_cmd_edit_user sets account_status='' (disabled)
			$command = new admin_cmd_edit_user($this->account_id, array('account_expires' => 0));
			$command->comment = 'Disable for unit test '.$this->name();
			$command->run();

			Api\Accounts::cache_invalidate($this->account_id);
			$this->assertFalse(Api\Accounts::is_active($this->account_id),
				'Disabled account must no longer be considered active');
		});
	}

	/**
	 * A user changing their OWN password (Auth::change_password(), the self-service
	 * path) must end every other session of theirs, while leaving the session making
	 * the change itself logged in.
	 */
	public function testSelfPasswordChangeKeepsCurrentSessionButKillsOthers()
	{
		$password = 'Initial-Pw-234';
		$this->asAdmin(function() use ($password)
		{
			$command = new admin_cmd_edit_user(false, $this->account + array(
				'account_passwd'   => $password,
				'account_passwd_2' => $password,
			));
			$command->comment = 'Setup for unit test '.$this->name();
			$command->run();
			$this->account_id = $command->account;
		});

		// log in as the test account itself, so session->account_id matches it
		$this->switchUser($this->account['account_lid'], $password);
		try
		{
			$current = $GLOBALS['egw']->session->sessionid_access_log;
			$other = $this->fakeActiveSession($this->account_id);
			$this->assertSame(2, $this->activeSessionCount($this->account_id));

			$auth = new Api\Auth();
			$this->assertTrue($auth->change_password($password, 'Changed-Pw-234', $this->account_id));

			$this->assertSame(1, $this->activeSessionCount($this->account_id),
				'change_password() should end the other session but keep the current one');
			$remaining = Api\Session::session_list(0, 'DESC', 'session_dla', true, array('account_id' => $this->account_id));
			$this->assertArrayHasKey($current, $remaining, 'The current session must survive its own password change');
			$this->assertArrayNotHasKey($other, $remaining, 'The other session must not survive the password change');
		}
		finally
		{
			$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		}
	}

	/**
	 * Session::get_password_from_session() must recover the exact plaintext password stored
	 * in an account's existing session, and must leave the CALLING session (ours) attached to
	 * its own, original session afterwards - not the one it just read.
	 */
	public function testGetPasswordFromSessionRecoversPasswordAndRestoresOwnSession()
	{
		$this->asAdmin(function()
		{
			$command = new admin_cmd_edit_user(false, $this->account);
			$command->comment = 'Setup for unit test '.$this->name();
			$command->run();
			$this->account_id = $command->account;

			// no session exists yet for this fresh account
			$this->assertNull(Api\Session::get_password_from_session($this->account_id));

			$own_sessionid = session_id();
			$this->seedFakeSessionPassword($this->account_id, 'Recoverable-Pw-1');

			$this->assertSame('Recoverable-Pw-1', Api\Session::get_password_from_session($this->account_id));
			$this->assertSame($own_sessionid, session_id(),
				'get_password_from_session() must reattach to the callers own session afterwards');
		});
	}

	/**
	 * An admin resetting someone's password (who does not know their old one) must still end
	 * up invalidating the cached Auth::authenticate() result for the OLD password, by
	 * recovering it from an existing session - otherwise the old (now invalid) password would
	 * keep authenticating for the remainder of Auth::AUTH_CACHE_TIME.
	 */
	public function testAdminPasswordResetInvalidatesOldAuthenticateCache()
	{
		// Auth::authenticate()'s cache is Api\Cache::INSTANCE level (currently the Files provider in
		// this environment) - skip rather than fail if that provider can't actually persist here (eg.
		// an unwritable cache dir), since that's an unrelated environment issue, not a regression.
		$probe = 'phpunit-cache-probe-'.mt_rand();
		if (Api\Cache::getCache($GLOBALS['egw_info']['server']['install_id'], __CLASS__, $probe,
			function() { return 'works'; }, [], 60) !== 'works' ||
			Api\Cache::getCache($GLOBALS['egw_info']['server']['install_id'], __CLASS__, $probe,
			function() { return 'miss'; }, [], 60) !== 'works')
		{
			$this->markTestSkipped('Api\Cache::INSTANCE provider is not actually persisting in this environment');
		}

		$this->asAdmin(function()
		{
			$command = new admin_cmd_edit_user(false, $this->account);
			$command->comment = 'Setup for unit test '.$this->name();
			$command->run();
			$this->account_id = $command->account;
			$lid = $this->account['account_lid'];

			$old_password = 'Old-Recoverable-Pw-1';
			$this->seedFakeSessionPassword($this->account_id, $old_password);

			// simulate a previously cached SUCCESSFUL Auth::authenticate() result for the old password,
			// using the exact same cache key Auth::authenticate() itself would use
			$backend_class = get_class(Api\Auth::backend());
			$cache_key = sha1($lid.':'.$old_password.':text:'.$backend_class);
			Api\Cache::getCache($GLOBALS['egw_info']['server']['install_id'], Api\Auth::class, $cache_key,
				function() { return true; }, [], Api\Auth::AUTH_CACHE_TIME);
			$this->assertTrue(Api\Cache::getCache($GLOBALS['egw_info']['server']['install_id'], Api\Auth::class, $cache_key,
				function() { return 'should-not-be-called'; }, [], Api\Auth::AUTH_CACHE_TIME),
				'Fixture did not actually populate the Auth::authenticate() cache');

			// admin resets the password via the SQL-direct Accounts::save() path, without knowing the old one
			$command = new admin_cmd_edit_user($this->account_id, array(
				'account_passwd'   => 'New-Pw-1',
				'account_passwd_2' => 'New-Pw-1',
			));
			$command->comment = 'Reset for unit test '.$this->name();
			$command->run();

			$still_cached = Api\Cache::getCache($GLOBALS['egw_info']['server']['install_id'], Api\Auth::class, $cache_key,
				function() { return 'cache-was-cleared'; }, [], Api\Auth::AUTH_CACHE_TIME);
			$this->assertSame('cache-was-cleared', $still_cached,
				'Admin password reset did not invalidate the cached Auth::authenticate() result for the old password');
		});
	}
}
