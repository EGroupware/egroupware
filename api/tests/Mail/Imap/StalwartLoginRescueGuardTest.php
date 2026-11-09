<?php
/**
 * EGroupware Api: Stalwart::login()'s real-IMAP-login fallback must never get its port
 * silently rescued by Jmap::_connect()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Imap;

require_once realpath(__DIR__.'/../../AppTest.php');

/**
 * Jmap::_connect() (see its own docblock, [[project_jmap_imap_fallthrough_cleanup]]) rescues any
 * raw-IMAP connect made on a non-IMAP (JMAP(S)) port by retrying on 993 instead of failing
 * outright - except for Stalwart::login()'s own deliberate real-password-login fallback (used
 * only once the OAuth/JMAP token grant has already failed, eg. because MFA is required), which
 * must always use the account's actual configured port/credentials, never a guessed one - a
 * login's outcome (especially one that could bypass an MFA requirement a different, guessed
 * port might not enforce) must never depend on a guess.
 */
class StalwartLoginRescueGuardTest extends \EGroupware\Api\AppTest
{
	public function testLoginFallbackSetsAndAlwaysResetsAllowRawImapConnectFlag()
	{
		$stalwart = $this->getMockBuilder(Stalwart::class)
			->disableOriginalConstructor()
			->onlyMethods(['accessToken', '_connect'])
			->getMock();
		// forces login() into its real-IMAP-login fallback branch (as if the OAuth/JMAP token
		// grant failed, eg. MFA required)
		$stalwart->method('accessToken')->willReturn(null);
		// Horde_Imap_Client_Socket::_login() rejects a blank password before ever reaching
		// _connect() - disableOriginalConstructor() leaves params empty, so this needs setting
		// directly for the test to reach the code path under test at all
		$stalwart->setParam('password', 'irrelevant-test-value');

		$flagDuringConnect = null;
		$stalwart->method('_connect')->willReturnCallback(function() use ($stalwart, &$flagDuringConnect)
		{
			$flagDuringConnect = self::readAllowRawImapConnect($stalwart);
			// abort here deliberately - a real socket attempt has no place in a unit test
			throw new \Exception('stop before any real socket IO');
		});

		try
		{
			$stalwart->login();
			$this->fail('expected the real-IMAP-login attempt to propagate its exception, not be swallowed');
		}
		catch (\Exception $e)
		{
			$this->assertSame('stop before any real socket IO', $e->getMessage());
		}

		$this->assertTrue($flagDuringConnect,
			'allowRawImapConnect must be true while the deliberate real-IMAP-login fallback runs');
		$this->assertFalse(self::readAllowRawImapConnect($stalwart),
			'allowRawImapConnect must be reset even though _connect() threw');
	}

	private static function readAllowRawImapConnect(Stalwart $stalwart) : bool
	{
		$prop = new \ReflectionProperty(Jmap::class, 'allowRawImapConnect');
		$prop->setAccessible(true);
		return $prop->getValue($stalwart);
	}
}
