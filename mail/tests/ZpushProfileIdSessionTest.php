<?php
/**
 * EGroupware Mail: Test mail_zpush's $profileID session-cache persistence
 *
 * @link https://www.egroupware.org
 * @package mail
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\mail;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;

/**
 * mail_zpush::$profileID was previously bound BY REFERENCE to Api\Cache::getSession(),
 * so its 5 mutation sites (constructor's preference-override/fallback-to-default-account,
 * _connect()'s two resolved-IMAP-server-id promotions) auto-persisted to the session.
 * Migrated to a plain read + explicit Api\Cache::setSession() calls at each site - this
 * proves a mutation actually lands in the session, not just in the in-process static.
 *
 * mail_zpush requires the (optional, separately packaged) z-push ActiveSync library's
 * activesync_backend to construct a real instance, so this calls the now-private persist
 * helper directly via Reflection - a pure "does the write happen" check, not a test of
 * ActiveSync profile resolution itself.
 */
class ZpushProfileIdSessionTest extends \EGroupware\Api\LoggedInTest
{
	public function testProfileIdMutationPersistsToSession()
	{
		$test_profile_id = 424242;
		\mail_zpush::$profileID = $test_profile_id;

		$persist = new \ReflectionMethod(\mail_zpush::class, 'persist_profile_id');
		$persist->setAccessible(true);
		$persist->invoke(null);

		$this->assertSame($test_profile_id, Api\Cache::getSession('mail', 'activeSyncProfileID'),
			'profileID mutation was not persisted to the session');
	}
}
