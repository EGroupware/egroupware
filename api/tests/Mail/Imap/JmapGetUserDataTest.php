<?php
/**
 * EGroupware Api: regression guard for Mail\Imap\Jmap::getUserData()'s JMAP-native quota fetch
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
 * getUserData() previously called getStorageQuotaRoot('INBOX'), the raw-IMAP QUOTA extension,
 * against acc_imap_host/acc_imap_port - for a JMAP account those are the JMAP(S) endpoint, not a
 * real IMAP port, so it hung for the full connect/read timeout (~20-30s) instead of failing fast
 * (found live 2026-09-01: opening the account-edit wizard for a JMAP account took ~30s for
 * exactly this reason, since admin_mail::edit() unconditionally calls
 * Mail\Account::getUserData() on every open).
 *
 * A real JMAP quota FETCH still needs a live server with the quota capability to test the
 * success path (same "real I/O, no injection seam" limitation JmapSessionPersistenceTest.php's
 * docblock already documents for this class) - this only guards the regression itself: an
 * unreachable JMAP host must fail well within the old hang's timeframe, and never throw.
 */
class JmapGetUserDataTest extends Api\LoggedInTest
{
	/**
	 * Port 1 is reserved (tcpmux) and essentially never has anything listening - same convention
	 * AdminMailCheckCertTest.php already uses for a reliably-unreachable host.
	 */
	public function testFailsFastInsteadOfHangingOnUnreachableHost()
	{
		$jmap = new Jmap([
			'acc_id' => 999999995,
			'acc_sieve_enabled' => false,
			'acc_imap_logintype' => null,
			'acc_domain' => null,
			'acc_imap_timeout' => 1,
			'acc_imap_host' => '127.0.0.1',
			'acc_imap_port' => 1,
			'acc_imap_ssl' => 6, // JMAP_HTTPS
			'acc_imap_username' => 'phpunit-test-user',
			'acc_imap_password' => 'phpunit-test-password',
		]);

		$start = microtime(true);
		$result = $jmap->getUserData('phpunit-test-user');
		$elapsed = microtime(true) - $start;

		$this->assertSame([], $result);
		// generous margin over Mail\Jmap::CONNECT_TIMEOUT (5s) - the old raw-IMAP hang took
		// 20-30s, so anything comfortably under that proves the regression is gone
		$this->assertLessThan(10, $elapsed,
			'getUserData() took too long for an unreachable host - the raw-IMAP hang may have regressed');
	}
}
