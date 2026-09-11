<?php
/**
 * EGroupware Api: Test Mail\Imap's $supports_keywords session-cache persistence
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail;

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api;

/**
 * Imap::$supports_keywords was previously bound BY REFERENCE to Api\Cache::getSession(),
 * so examineMailbox()/hasCapability() mutating it auto-persisted to the session. Migrated
 * to a plain read (init_static()) + explicit Api\Cache::setSession() calls at the 2 actual
 * mutation sites - this proves a mutation actually lands in the session, not just in the
 * in-process static property.
 *
 * Exercising this through a real IMAP connection would need live server infrastructure, so
 * this calls the now-private persist helper directly via Reflection - it's a pure "does the
 * write happen" check, not a test of IMAP capability detection itself.
 */
class ImapSupportsKeywordsSessionTest extends Api\LoggedInTest
{
	public function testSupportsKeywordsMutationPersistsToSession()
	{
		Imap::init_static();

		$server_id = 'phpunit-test-server';
		Imap::$supports_keywords[$server_id] = true;

		$persist = new \ReflectionMethod(Imap::class, 'persist_supports_keywords');
		$persist->setAccessible(true);
		$persist->invoke(null);

		$session_value = Api\Cache::getSession(Imap::class, 'supports_keywords');
		$this->assertSame(true, $session_value[$server_id] ?? null,
			'supports_keywords mutation was not persisted to the session');
	}
}
