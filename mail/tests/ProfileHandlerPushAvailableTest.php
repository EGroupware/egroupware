<?php
/**
 * EGroupware Mail: Mail\Ui\ProfileHandler::enablePush()'s return value tests
 *
 * Ticket-driven regression (2026-09-23, real forum report "26.9.20260922 Ständiger reload vom
 * Posteingang"): mail_ui::get_rows()'s classic dynamic "disable the row list's periodic
 * autorefresh once we know push works for this account" mechanism (commits 9a005ab7c0/6bd87cafb5,
 * 2020) was silently dropped when get_rows() itself was removed for the full client-side JMAP
 * migration. jmapBootstrap() now forwards enablePush()'s own account-specific push-capability
 * check to the client (MailJmap.syncAutorefresh()) instead - this covers that return value, the
 * one new piece of pure, DB-free logic (jmapBootstrap() itself is too entangled with a real
 * account/IMAP connection to usefully mock, same reasoning as resolveSmime()).
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

use EGroupware\Api\Mail\Imap\PushIface;
use EGroupware\Mail\Ui\ProfileHandler;
use PHPUnit\Framework\TestCase;

class ProfileHandlerPushAvailableTest extends TestCase
{
	private function callEnablePush($imapServer, $icServerID)
	{
		$method = new ReflectionMethod(ProfileHandler::class, 'enablePush');
		$method->setAccessible(true);
		return $method->invoke(null, $imapServer, $icServerID);
	}

	public function testReturnsTrueAndRegistersPushWhenTheServerSupportsIt()
	{
		$registered = null;
		$imapServer = new class($registered) implements PushIface
		{
			private $registeredRef;
			public function __construct(&$registeredRef) { $this->registeredRef = &$registeredRef; }
			public function pushAvailable() { return true; }
			public function enablePush(?int $account_id = null, ?string $acc_id_folder = null)
			{
				$this->registeredRef = $acc_id_folder;
				return true;
			}
		};

		$this->assertTrue($this->callEnablePush($imapServer, 42));
		$this->assertStringContainsString('42', $registered, 'must register push for the given icServerID');
		$this->assertStringContainsString('INBOX', $registered);
	}

	public function testReturnsFalseAndNeverRegistersWhenTheServerDoesNotSupportPush()
	{
		$called = false;
		$imapServer = new class($called) implements PushIface
		{
			private $calledRef;
			public function __construct(&$calledRef) { $this->calledRef = &$calledRef; }
			public function pushAvailable() { return false; }
			public function enablePush(?int $account_id = null, ?string $acc_id_folder = null)
			{
				$this->calledRef = true;
				return true;
			}
		};

		$this->assertFalse($this->callEnablePush($imapServer, 42));
		$this->assertFalse($called, 'enablePush() must never be called once pushAvailable() said no');
	}

	public function testReturnsFalseForAServerClassThatDoesNotImplementPushIfaceAtAll()
	{
		// eg. a plain IMAP class without PushIface support at all - same "no push" outcome,
		// no crash, no instanceof-check bypass
		$imapServer = new stdClass();

		$this->assertFalse($this->callEnablePush($imapServer, 42));
	}

	public function testReturnsFalseInsteadOfThrowingWhenPushAvailableItselfThrows()
	{
		$imapServer = new class implements PushIface
		{
			public function pushAvailable() { throw new \Exception('IMAP connection lost'); }
			public function enablePush(?int $account_id = null, ?string $acc_id_folder = null) { return true; }
		};

		$this->assertFalse($this->callEnablePush($imapServer, 42));
	}
}
