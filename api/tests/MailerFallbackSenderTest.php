<?php
/**
 * EGroupware API: tests for Mailer::setFallbackSender()
 *
 * Mails not sent in a user's context, eg. the "login blocked" notification to the admins, set a synthetic sender
 * (eGroupWare@<mail_suffix>). Combined with a SMTP-only profile having no identity email, the envelope sender
 * (Return-Path) was empty ("<>"), which SMTP servers checking sender against the authenticated account now reject.
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once realpath(__DIR__.'/LoggedInTest.php');

class MailerFallbackSenderTest extends LoggedInTest
{
	const FALLBACK = 'eGroupWare@example.org';

	protected function mailer(?string $return_path, ?string $from) : Mailer
	{
		$mailer = new Mailer('initbasic');
		if (isset($return_path)) $mailer->addHeader('Return-Path', $return_path, true);
		if (isset($from)) $mailer->addHeader('From', $from);
		return $mailer;
	}

	protected function bare(Mailer $mailer, string $header) : string
	{
		return trim((string)preg_replace('/^.*<(.*)>.*$/', '$1', (string)$mailer->getHeader($header)), " <>");
	}

	/**
	 * Pass criteria: without any sender, the fallback is used for From AND the envelope sender
	 */
	public function testNoSenderUsesFallbackForBoth()
	{
		$mailer = $this->mailer(null, null);
		$mailer->setFallbackSender(self::FALLBACK, 'eGroupWare');

		$this->assertSame(self::FALLBACK, $this->bare($mailer, 'From'));
		$this->assertSame(self::FALLBACK, $this->bare($mailer, 'Return-Path'));
		$this->assertStringContainsString('eGroupWare', $mailer->getHeader('From'), 'name is used with the fallback');
	}

	/**
	 * Pass criteria: an SMTP-only profile without identity email (empty "<>" envelope and no From) gets the fallback
	 */
	public function testSmtpOnlyAccountWithoutEmailGetsNonEmptyEnvelope()
	{
		$mailer = $this->mailer('<>', '');
		$mailer->setFallbackSender(self::FALLBACK);

		$this->assertSame(self::FALLBACK, $this->bare($mailer, 'Return-Path'), 'envelope sender must not stay empty');
		$this->assertSame(self::FALLBACK, $this->bare($mailer, 'From'));
	}

	/**
	 * Pass criteria: the sender of the mail-account is kept, the fallback is NOT used
	 */
	public function testAccountSenderIsKept()
	{
		$mailer = $this->mailer('<smtp@account.example>', 'Account <ident@account.example>');
		$mailer->setFallbackSender(self::FALLBACK);

		$this->assertSame('smtp@account.example', $this->bare($mailer, 'Return-Path'));
		$this->assertSame('ident@account.example', $this->bare($mailer, 'From'));
	}

	/**
	 * Pass criteria: if only one of both is missing, it takes the other's address and NOT the fallback, so
	 * header and envelope sender match
	 */
	public function testMissingPartTakesTheOtherOne()
	{
		$mailer = $this->mailer('<smtp@account.example>', '');
		$mailer->setFallbackSender(self::FALLBACK);
		$this->assertSame('smtp@account.example', $this->bare($mailer, 'From'));
		$this->assertSame('smtp@account.example', $this->bare($mailer, 'Return-Path'));

		$mailer = $this->mailer('<>', 'Account <ident@account.example>');
		$mailer->setFallbackSender(self::FALLBACK);
		$this->assertSame('ident@account.example', $this->bare($mailer, 'Return-Path'));
		$this->assertSame('ident@account.example', $this->bare($mailer, 'From'));
	}
}
