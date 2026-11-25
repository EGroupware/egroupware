<?php
/**
 * EGroupware Api: Test Mail\Imap::newSieveConnection() - the SIEVE_CLASS-specific constructor shape
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Mail;
use EGroupware\Api\Mail\Imap;
use EGroupware\Api\Mail\Sieve;
use PHPUnit\Framework\TestCase;

/**
 * Regression test (found live 2026-11-25, ralf, admin "edit vacation notice" for acc_id=1/Stalwart:
 * "whenever I open it, it says Active, I deactivate it and reopen it, and it's again Active").
 *
 * Root cause: Imap::setVacationUser()/getVacationUser() used to construct `static::SIEVE_CLASS`
 * unconditionally as `new (...)( $icServer, $euser, $scriptName )` - correct for the classic
 * Sieve\ManageSieve class (its own constructor's 2nd positional arg genuinely IS a username,
 * needed since ManageSieve speaks a wholly separate per-connection protocol with its own
 * impersonation handshake), but Sieve\Jmap's constructor only ever accepts
 * `($params, ?string $accountId=null)` - the euser positional arg silently landed in $accountId
 * instead (PHP drops extra/mismatched positional args rather than erroring), pointing EVERY
 * SieveScript JMAP call at a bogus "account" (a resolved mailbox USERNAME string, never a real
 * Stalwart accountId). Stalwart didn't error on this - it just found zero sieve scripts there and
 * silently auto-created a fresh, empty one each time, so the admin dialog always read back an
 * empty vacation array (defaulting the status dropdown to "Active") regardless of what had
 * actually been saved moments before - the REAL script (under this account's own correct,
 * already-impersonated accountId) was written correctly the whole time via whichever OTHER
 * already-constructed connection the same request's hasExtension()/__call() calls happened to
 * reuse, so server-side vacation behaviour was never actually wrong, only the admin dialog's own
 * re-display of it after a reopen.
 *
 * newSieveConnection() is the (now SIEVE_CLASS-aware) single chokepoint both setVacationUser() and
 * getVacationUser() construct their Sieve\Logic connection through - private, so exercised here via
 * Reflection (same pattern used for other single-method regression tests in this suite, eg.
 * ProfileHandlerResolveUnconfiguredTrashFolderTest).
 */
class ImapNewSieveConnectionTest extends TestCase
{
	private function call(Imap $icServer, string $euser, ?string $scriptName=null) : Sieve\Connection
	{
		$method = new ReflectionMethod(Imap::class, 'newSieveConnection');
		$method->setAccessible(true);
		return $method->invoke($icServer, $euser, $scriptName);
	}

	/** @return mixed protected/private property value, read via Reflection */
	private function readProperty(object $object, string $property)
	{
		$prop = new ReflectionProperty(get_class($object), $property);
		$prop->setAccessible(true);
		return $prop->getValue($object);
	}

	public function testJmapSieveClassIgnoresEuserAndDefaultsAccountIdFromTheAlreadyImpersonatedSession()
	{
		$fakeJmapSession = new class {
			public $accountId = 'real-stalwart-accountid';
			public $downloadUrl = '';
			public $uploadUrl = '';
		};
		$icServer = new class($fakeJmapSession) extends Mail\Imap\Jmap
		{
			public function __construct(private object $fakeJmapSession) {}
			public function jmapClient() { return $this->fakeJmapSession; }
		};

		$sieve = $this->call($icServer, 'ralf@boulder.egroupware.org');

		$this->assertInstanceOf(Sieve\Jmap::class, $sieve);
		$this->assertSame('real-stalwart-accountid', $this->readProperty($sieve, 'accountId'),
			"Sieve\\Jmap's accountId must default from the already-impersonated JMAP session, " .
			"never get overridden with the euser string (the bug this test guards against)");
	}
}
