<?php
/**
 * EGroupware Api: Test Mail\Imap\Jmap::checkAdminConnection()'s pure guard logic
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Imap;

require_once realpath(__DIR__.'/../../LoggedInTest.php');

use EGroupware\Api;

/**
 * checkAdminConnection() ultimately authenticates via a real JMAP HTTP request (JmapHttp's
 * constructor), which needs a live server - not tested here, same "real I/O, no injection seam"
 * limitation JmapSessionPersistenceTest.php's docblock already documents for this class. This
 * only covers the two guard branches that return/throw BEFORE any network call is attempted:
 * "nothing to check" (no admin credentials configured) and "nothing to check it AGAINST" (no
 * username to impersonate) - both real regressions the override could reintroduce without ever
 * touching the network.
 *
 * See [[project_jmap_imap_fallthrough_cleanup]]: this override exists because the inherited
 * classic-IMAP implementation unconditionally builds a raw Horde_Imap_Client_Socket against
 * acc_imap_host/acc_imap_port, which for a JMAP account is the JMAP(S) endpoint, not a real IMAP
 * port - found live 2026-09-01.
 */
class JmapCheckAdminConnectionTest extends Api\LoggedInTest
{
	private function dummyParams(int $acc_id, array $overrides=[]) : array
	{
		return array_merge([
			'acc_id' => $acc_id,
			'acc_sieve_enabled' => false,
			'acc_imap_logintype' => null,
			'acc_domain' => null,
			'acc_imap_timeout' => 1,
			'acc_imap_host' => '127.0.0.1',
			'acc_imap_port' => 143,
			'acc_imap_ssl' => 0,
			'acc_imap_username' => 'phpunit-test-user',
			'acc_imap_password' => 'phpunit-test-password',
		], $overrides);
	}

	/**
	 * No acc_imap_admin_username configured at all - acc_imap_administration is false, so the
	 * check must be a no-op (no exception, no network call attempted).
	 */
	public function testNoOpWhenAdministrationNotConfigured()
	{
		$jmap = new Jmap($this->dummyParams(999999997));

		$jmap->checkAdminConnection();
		$this->addToAssertionCount(1);	// reaching here without an exception IS the assertion
	}

	/**
	 * Administration IS configured, but there's no username to impersonate (called with the
	 * default true, and acc_imap_username itself is empty - eg. a multi-user "everyone" account
	 * with no single configured login) - must fail fast with a clear message, not attempt a
	 * network call with an incomplete "%admin" master-login string.
	 */
	public function testThrowsWhenNoUsernameToImpersonate()
	{
		$jmap = new Jmap($this->dummyParams(999999996, [
			'acc_imap_username' => '',
			'acc_imap_admin_username' => 'master',
			'acc_imap_admin_password' => 'master-password',
		]));

		$this->expectException(Api\Exception::class);
		$jmap->checkAdminConnection();
	}
}
