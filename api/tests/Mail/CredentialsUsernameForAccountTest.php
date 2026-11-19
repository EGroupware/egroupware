<?php
/**
 * EGroupware Api: regression test for Credentials::usernameForAccount()
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

namespace EGroupware\Api\Mail;

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api;

/**
 * Mail\Account::__construct() needs to resolve WHO to impersonate for a given $called_for
 * account_id even when there is no live/matching session for it (eg. Jmap::pushCallback()'s
 * cleanup path acting on a specific account from a server-to-server webhook request, not an
 * interactive login) - found live 2026-11-18/19, ticket #128191: acc_imap_username was left
 * unset in exactly that case, which then failed Horde_Imap_Client_Base's "requires a username"
 * check further down with no $GLOBALS['egw_info']['user'] involved at all.
 *
 * Credentials::usernameForAccount() is the fix: it resolves the same acc_imap_logintype-driven
 * username Credentials::from_session() builds from the CURRENT session, but from an explicit
 * account_id instead, independent of any live/matching $GLOBALS['egw_info']['user'].
 */
class CredentialsUsernameForAccountTest extends Api\LoggedInTest
{
	public function testStandardLogintypeReturnsAccountLid()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$account_lid = $GLOBALS['egw_info']['user']['account_lid'];

		$this->assertSame($account_lid,
			Credentials::usernameForAccount($account_id, 'standard', 'example.org'));
	}

	public function testEmailLogintypeReturnsAccountEmail()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$account_email = $GLOBALS['egw_info']['user']['account_email'];

		$this->assertSame($account_email,
			Credentials::usernameForAccount($account_id, 'email', 'example.org'));
	}

	public function testVmailmgrLogintypeAppendsDomain()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$account_lid = $GLOBALS['egw_info']['user']['account_lid'];

		$this->assertSame($account_lid.'@example.org',
			Credentials::usernameForAccount($account_id, 'vmailmgr', 'example.org'));
	}

	public function testUnknownAccountIdReturnsNull()
	{
		// an account_id that does not exist (eg. a stale/orphaned one left over after a
		// migration) must resolve to null, never a half-built/empty-ish username string
		$this->assertNull(Credentials::usernameForAccount(999999, 'standard', 'example.org'));
	}

	/**
	 * The actual bug: Mail\Account::__construct() used to leave acc_imap_username unset
	 * whenever $called_for didn't match the (possibly entirely absent) current global user -
	 * now it resolves it via Credentials::usernameForAccount() instead, so the admin-
	 * impersonation master-login string still gets built.
	 */
	public function testAccountResolvesUsernameForCalledForWithoutMatchingSession()
	{
		$called_for = $GLOBALS['egw_info']['user']['account_id'];
		$account_lid = $GLOBALS['egw_info']['user']['account_lid'];

		// simulate a server-to-server webhook call: no current/matching global user at all
		$saved = $GLOBALS['egw_info']['user']['account_id'];
		$GLOBALS['egw_info']['user']['account_id'] = null;
		try
		{
			$account = new Account([
				// a non-existent acc_id: Credentials::read()/Notifications::read() still run (acc_id
				// > 0) but safely return empty, so only the explicit acc_* params below take effect
				'acc_id' => 999999999,
				'acc_imap_logintype' => 'standard',
				'acc_domain' => 'example.org',
				'acc_admin_use_without_pw' => true,
				'acc_imap_admin_username' => 'admin-at-egroupware.org',
				'acc_imap_admin_password' => 'secret',
				'acc_imap_type' => Imap\Jmap::class,
			], $called_for);

			$this->assertSame($account_lid.Imap\Jmap::MASTER_SEPARATOR.'admin-at-egroupware.org',
				$account->acc_imap_username,
				'must still build the master-login string from $called_for, not leave it unset');
			$this->assertSame('secret', $account->acc_imap_password);
		}
		finally
		{
			$GLOBALS['egw_info']['user']['account_id'] = $saved;
		}
	}
}
