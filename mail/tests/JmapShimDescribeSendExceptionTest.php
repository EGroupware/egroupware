<?php
/**
 * Test EGroupware\Api\Mail\Jmap\Imap::describeSendException()
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap;

/**
 * Ticket #125201 follow-up (a real customer, forwarding to a large distribution list): a send
 * failure showed only a generic, untranslated "Mailbox unavailable." with nothing more specific
 * in the error log either. Root cause: Horde_Smtp_Exception (Postfix's own SMTP transport)
 * replaces the SERVER's own specific response text with a generic one keyed purely off the
 * numeric SMTP code - ANY 450 becomes that same bare phrase, regardless of what Postfix actually
 * said about which recipient/why. The real, specific response survives on the exception's own
 * `raw_msg` property, which nothing in this codebase used before this fix.
 */
class JmapShimDescribeSendExceptionTest extends \PHPUnit\Framework\TestCase
{
	private function describeSendException(\Throwable $e) : string
	{
		$reflection = new \ReflectionMethod(Imap::class, 'describeSendException');
		$reflection->setAccessible(true);
		return $reflection->invoke(null, $e);
	}

	/** Not a fixture we control the message text of - Horde_Smtp_Exception::__construct() always translates it. */
	private function hordeSmtpException(int $smtpCode, string $rawMessage) : \Horde_Smtp_Exception
	{
		$e = new \Horde_Smtp_Exception($rawMessage);
		$e->setSmtpCode($smtpCode);
		return $e;
	}

	public function testUsesTheRawServerResponseNotHordesGenericTranslation()
	{
		$e = $this->hordeSmtpException(450,
			'450 4.2.1 <someone@example.org>: Recipient address rejected: over quota');
		// Horde's own generic 450 mapping - confirms the fixture is really hitting that branch
		$this->assertSame('Mailbox unavailable.', $e->getMessage());

		$description = $this->describeSendException($e);

		$this->assertSame('450 4.2.1 <someone@example.org>: Recipient address rejected: over quota', $description);
	}

	public function testSetsDetailsSoEgwLogExceptionAlsoLogsTheRawResponse()
	{
		$e = $this->hordeSmtpException(450, '450 4.2.1 <someone@example.org>: over quota');

		$this->describeSendException($e);

		$this->assertSame('450 4.2.1 <someone@example.org>: over quota', $e->details);
	}

	public function testFallsBackToGetMessageForAnythingThatIsNotAHordeSmtpException()
	{
		$e = new \RuntimeException('some other failure');

		$description = $this->describeSendException($e);

		$this->assertSame('some other failure', $description);
		$this->assertFalse(isset($e->details), 'must not invent a details property for an exception type with no raw_msg at all');
	}

	public function testActuallyLogsTheException()
	{
		$log = tempnam(sys_get_temp_dir(), 'egw-jmap-send-exception-');
		$error_log = ini_get('error_log') ?: '';
		ini_set('error_log', $log);
		// see JmapShimDispatchLogsUncaughtExceptionsTest's own comment for why this is pinned
		$no_exception_handler = $GLOBALS['egw_info']['flags']['no_exception_handler'] ?? null;
		$GLOBALS['egw_info']['flags']['no_exception_handler'] = false;

		try
		{
			$this->describeSendException($this->hordeSmtpException(450, '450 4.2.1 <someone@example.org>: over quota'));
		}
		finally
		{
			$logged = file_get_contents($log);
			ini_set('error_log', $error_log);
			unlink($log);
			$GLOBALS['egw_info']['flags']['no_exception_handler'] = $no_exception_handler;
		}

		$this->assertStringContainsString('450 4.2.1 <someone@example.org>: over quota', $logged,
			'the raw, specific server response must reach the log, not just the generic message');
	}
}
