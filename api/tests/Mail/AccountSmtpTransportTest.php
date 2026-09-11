<?php
/**
 * EGroupware Api: Test Mail\Account::smtpTransport()'s JMAP-vs-classic-SMTP wiring
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail;

/**
 * smtpTransport() picks Mail\Jmap\Transport vs the classic Horde_Mail_Transport_Smtphorde based
 * on acc_smtp_ssl's protocol bits (JMAP_HTTP/JMAP_HTTPS), the same way acc_imap_ssl/acc_sieve_ssl
 * already select JMAP for their own protocols - NOT via acc_smtp_type (that stays the unrelated
 * account-provisioning selector, see admin_mail::normalizeAccountType()).
 *
 * Only the WIRING is tested here - neither transport actually connects eagerly (Horde_Mail_
 * Transport_Smtphorde's constructor is lazy, and Mail\Jmap\Transport's constructor just stores
 * the account), so this needs no live server. Real send() behavior needs a live JMAP server, same
 * "real I/O, no injection seam" limitation already documented for sibling classes.
 */
class AccountSmtpTransportTest extends Api\LoggedInTest
{
	private function dummyAccount(int $ssl) : Mail\Account
	{
		return new Mail\Account([
			'acc_id' => 0,
			'acc_smtp_ssl' => $ssl,
			'acc_smtp_host' => 'smtp.example.org',
			'acc_smtp_port' => 587,
			'acc_smtp_username' => 'phpunit-test-user',
			'acc_smtp_password' => 'phpunit-test-password',
			'acc_imap_host' => 'imap.example.org',
			'acc_imap_username' => 'phpunit-test-user',
		]);
	}

	public function testJmapHttpsSelectsJmapTransport()
	{
		$this->assertInstanceOf(Mail\Jmap\Transport::class,
			$this->dummyAccount(Mail\Account::JMAP_HTTPS)->smtpTransport());
	}

	public function testJmapHttpSelectsJmapTransport()
	{
		$this->assertInstanceOf(Mail\Jmap\Transport::class,
			$this->dummyAccount(Mail\Account::JMAP_HTTP)->smtpTransport());
	}

	public function testClassicTlsSelectsSmtphorde()
	{
		$this->assertInstanceOf(Horde_Mail_Transport_Smtphorde::class,
			$this->dummyAccount(Mail\Account::SSL_TLS)->smtpTransport());
	}

	public function testClassicStarttlsSelectsSmtphorde()
	{
		$this->assertInstanceOf(Horde_Mail_Transport_Smtphorde::class,
			$this->dummyAccount(Mail\Account::SSL_STARTTLS)->smtpTransport());
	}

	/**
	 * A VERIFY bit (bits 3-4) set alongside the JMAP protocol bits (bits 0-2) must not confuse
	 * the PROTOCOL_MASK check - the two live in disjoint bit ranges by design.
	 */
	public function testJmapWithVerifyDisabledBitStillSelectsJmapTransport()
	{
		$this->assertInstanceOf(Mail\Jmap\Transport::class,
			$this->dummyAccount(Mail\Account::JMAP_HTTPS | Mail\Account::VERIFY_DISABLED)->smtpTransport());
	}

	/**
	 * Regression guard: acc_smtp_ssl can legitimately be the literal string 'no' (the "no
	 * encryption" sentinel, see admin_mail::sslTypes()/mergeVerifyCheckbox()) - PHP 8's bitwise &
	 * throws "Unsupported operand types: string & int" for a non-numeric string operand instead
	 * of pre-8's silent-0 coercion. Found live 2026-09-03: any account with acc_smtp_ssl='no'
	 * crashed on EVERY save (Mail\Account::write() -> saveUserData() -> smtpServer()), not just
	 * ones with S/MIME involved - dummyAccount()'s own int-typed $ssl param can't reproduce this
	 * (a string argument would fail at ITS OWN call boundary), hence a raw array here instead.
	 */
	public function testSmtpServerToleratesNoEncryptionStringSentinel()
	{
		$account = new Mail\Account([
			'acc_id' => 0,
			'acc_smtp_type' => Mail\Smtp::class,
			'acc_smtp_ssl' => 'no',
			'acc_smtp_host' => 'smtp.example.org',
			'acc_smtp_port' => 25,
			'acc_imap_host' => 'imap.example.org',
			'acc_imap_username' => 'phpunit-test-user',
		]);

		$smtpServer = $account->smtpServer();

		$this->assertInstanceOf(Mail\Smtp::class, $smtpServer);
		$this->assertSame('smtp.example.org', $smtpServer->host,
			'no encryption scheme prefix (tlsv1://, tls://) must NOT be added to the host');
	}

	/** Same regression, for smtpTransport()'s classic (non-JMAP) branch. */
	public function testSmtpTransportToleratesNoEncryptionStringSentinel()
	{
		$account = new Mail\Account([
			'acc_id' => 0,
			'acc_smtp_ssl' => 'no',
			'acc_smtp_host' => 'smtp.example.org',
			'acc_smtp_port' => 25,
			'acc_imap_host' => 'imap.example.org',
			'acc_imap_username' => 'phpunit-test-user',
		]);

		$this->assertInstanceOf(Horde_Mail_Transport_Smtphorde::class, $account->smtpTransport());
	}
}
