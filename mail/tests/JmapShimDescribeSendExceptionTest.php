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

	/**
	 * The exception ACTUALLY reaching emailSubmissionSet()'s catch (see ticket #125201's own real
	 * error log) is never a bare Horde_Smtp_Exception - Horde_Mime_Part::send() wraps it into
	 * Horde_Mail_Exception, then Horde_Mime_Exception. Both wrappers extend Horde_Exception_Wrapped,
	 * which copies ->details forward from whatever it wraps at construction time - found live: the
	 * server error log already showed the specific reason (via that automatic copying +
	 * _egw_log_exception()'s own ->details check) even before this fix, but describeSendException()
	 * itself still only ever checked `instanceof Horde_Smtp_Exception` directly, which a
	 * Horde_Mime_Exception never satisfies - so the CLIENT-facing description stayed the generic
	 * "Mailbox unavailable." regardless. This is what actually reproduces (and fixes) that gap.
	 */
	public function testUnwrapsDetailsThroughHordeMimeExceptionAndHordeMailException()
	{
		$smtp = $this->hordeSmtpException(450, '<info@example.invalid>: Recipient address rejected: Domain not found');
		// Horde_Smtp::_getResponse()'s own convention (api/thumbnail.php's own sibling finding,
		// ticket #125201) - sets this directly, not through any wrapping mechanism
		$smtp->details = '<info@example.invalid>: Recipient address rejected: Domain not found';
		$mime = new \Horde_Mime_Exception(new \Horde_Mail_Exception($smtp));

		$this->assertSame('Mailbox unavailable.', $mime->getMessage(),
			'confirms the fixture really is the generic-message wrapper the real bug hit');

		$description = $this->describeSendException($mime);

		$this->assertSame('<info@example.invalid>: Recipient address rejected: Domain not found', $description);
	}

	private function isMessageTooLargeSmtpError(string $description) : bool
	{
		$reflection = new \ReflectionMethod(Imap::class, 'isMessageTooLargeSmtpError');
		$reflection->setAccessible(true);
		return $reflection->invoke(null, $description);
	}

	/**
	 * ralf, 2026-11-04: a message rejected by the outbound relay (eg. Postfix's own
	 * message_size_limit) used to land as an indistinguishable generic 'serverFail' - this
	 * reclassifies it as 'tooLarge' (RFC 8621 S7.4, same shape a real Stalwart account's own
	 * EmailSubmission/set already uses), so the client can show ONE translated message for both
	 * backends. Enhanced status code 5.3.4 (RFC 3463) is the one standardised, reliable signal.
	 */
	public function testDetectsEnhancedStatusCode534RegardlessOfSurroundingText()
	{
		$this->assertTrue($this->isMessageTooLargeSmtpError(
			'552 5.3.4 Message size exceeds fixed maximum message size'));
		$this->assertTrue($this->isMessageTooLargeSmtpError('5.3.4 some other MTA-specific wording entirely'));
	}

	/** Free-text fallback for an MTA that omits the enhanced status code - best-effort, not exhaustive. */
	public function testDetectsCommonFreeTextWordingAsAFallback()
	{
		$this->assertTrue($this->isMessageTooLargeSmtpError('552 Message size exceeds maximum permitted'));
		$this->assertTrue($this->isMessageTooLargeSmtpError('Error: message too big for this server'));
		$this->assertTrue($this->isMessageTooLargeSmtpError('REJECTED: message too large'));
	}

	public function testDoesNotMatchAnUnrelatedSmtpRejection()
	{
		$this->assertFalse($this->isMessageTooLargeSmtpError(
			'450 4.2.1 <someone@example.org>: Recipient address rejected: over quota'));
		$this->assertFalse($this->isMessageTooLargeSmtpError('Mailbox unavailable.'));
		// "size" alone, without any exceed/too-big/too-large wording, must not false-positive
		$this->assertFalse($this->isMessageTooLargeSmtpError('550 5.1.1 unknown recipient size restriction policy'));
	}
}
