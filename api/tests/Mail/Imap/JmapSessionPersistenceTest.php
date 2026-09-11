<?php
/**
 * EGroupware Api: Test Mail\Imap\Jmap's session-cache persistence
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Imap;

require_once realpath(__DIR__.'/../../LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail;

/**
 * Jmap::$jmap_accountId/$jmap_states/$current_folder were previously bound BY REFERENCE to
 * Api\Cache::getSession(). $jmap_accountId is additionally passed into Mail\Jmap::__construct()'s
 * own by-reference $accountId parameter, so Mail\Jmap::bootstrap() mutating it still reaches
 * Jmap::$jmap_accountId after this migration (PHP reference-parameter passing doesn't care
 * whether the passed variable is itself a reference) - only the LAST hop, back into the
 * session, needed to become an explicit setSession() call (persist_jmap_state()).
 *
 * Constructs a Jmap instance with dummy (non-network-touching) params - the constructor itself
 * never connects, so this proves the persistence mechanism without needing a live JMAP server.
 */
class JmapSessionPersistenceTest extends Api\LoggedInTest
{
	private function dummyParams(int $acc_id) : array
	{
		return [
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
		];
	}

	public function testPersistJmapStateWritesAllThreeKeysToSession()
	{
		$acc_id = 999999999; // arbitrary, won't collide with a real configured account
		$jmap = new Jmap($this->dummyParams($acc_id));

		$prop = function(string $name)
		{
			$prop = new \ReflectionProperty(Jmap::class, $name);
			$prop->setAccessible(true);
			return $prop;
		};
		$prop('jmap_accountId')->setValue($jmap, 'test-account-id');
		$prop('jmap_states')->setValue($jmap, ['test-account-id' => ['state' => 42]]);
		$prop('current_folder')->setValue($jmap, 'INBOX/Sub');

		$persist = new \ReflectionMethod(Jmap::class, 'persist_jmap_state');
		$persist->setAccessible(true);
		$persist->invoke($jmap);

		$this->assertSame('test-account-id', Api\Cache::getSession(Jmap::class, 'accountId:'.$acc_id));
		$this->assertSame(['test-account-id' => ['state' => 42]], Api\Cache::getSession(Jmap::class, 'states:'.$acc_id));
		$this->assertSame('INBOX/Sub', Api\Cache::getSession(Jmap::class, 'currentFolder:'.$acc_id));
	}

	public function testConstructorReadsPreviouslyPersistedState()
	{
		$acc_id = 999999998; // arbitrary, distinct from the other test's id
		Api\Cache::setSession(Jmap::class, 'accountId:'.$acc_id, 'previously-cached-id');
		Api\Cache::setSession(Jmap::class, 'states:'.$acc_id, ['previously-cached-id' => ['state' => 7]]);
		Api\Cache::setSession(Jmap::class, 'currentFolder:'.$acc_id, 'INBOX');

		$jmap = new Jmap($this->dummyParams($acc_id));

		$get = function(string $name) use ($jmap)
		{
			$prop = new \ReflectionProperty(Jmap::class, $name);
			$prop->setAccessible(true);
			return $prop->getValue($jmap);
		};
		$this->assertSame('previously-cached-id', $get('jmap_accountId'));
		$this->assertSame(['previously-cached-id' => ['state' => 7]], $get('jmap_states'));
		$this->assertSame('INBOX', $get('current_folder'));
	}
}
