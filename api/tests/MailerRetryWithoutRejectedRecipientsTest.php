<?php
/**
 * Test EGroupware\Api\Mailer::send() retrying without rejected recipients
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once realpath(__DIR__.'/LoggedInTest.php');

/**
 * Ticket #125201 (a real customer: a distribution-list send to ~300 addresses failed entirely,
 * nobody got the message, because ONE address's domain no longer exists): Horde_Smtp's own
 * RCPT-TO handling deliberately refuses to even attempt DATA if ANY recipient was rejected (its
 * own code comment: "Can't pipeline DATA since we want to throw an exception if ANY of the
 * recipients are bad") - a real behaviour change from whatever the classic code path relied on
 * (a bad recipient bouncing back asynchronously via the MTA's own NDR, never blocking delivery to
 * everyone else), even though Api\Mailer itself was already using Horde_Smtp before any of this
 * session's JMAP work.
 *
 * send() now retries once, excluding whichever recipients Horde_Smtp itself rejected, and reports
 * them via $this->failedRecipients (address => reason) instead of throwing - callers can then
 * tell the user "sent to everyone, except: ..." instead of the whole send silently failing.
 *
 * A fake Horde_Mail_Transport (not a real SMTP connection) reproduces the EXACT wrapped exception
 * shape production actually throws (Horde_Smtp_Exception_Recipients, wrapped by
 * Horde_Mail_Transport_Smtphorde into Horde_Mail_Exception, then by Horde_Mime_Part::send() into
 * Horde_Mime_Exception - see Mailer::send()'s own docblock) - confirmed against a real customer's
 * own error log (ticket #125201).
 */
class MailerRetryWithoutRejectedRecipientsTest extends LoggedInTest
{
	private function mailer() : Mailer
	{
		$mailer = new Mailer('initbasic');
		$mailer->addHeader('Subject', 'Test');
		$mailer->addHeader('From', 'sender@example.org');
		return $mailer;
	}

	public static function wrappedRecipientsException(array $recipients, string $detail) : \Horde_Mime_Exception
	{
		$smtp = new \Horde_Smtp_Exception_Recipients($detail);
		$smtp->setSmtpCode(450);
		$smtp->details = $detail;
		$smtp->recipients = $recipients;

		return new \Horde_Mime_Exception(new \Horde_Mail_Exception($smtp));
	}

	public function testRetriesWithoutTheRejectedRecipientAndReportsIt()
	{
		$mailer = $this->mailer();
		$mailer->addAddress('good@example.org');
		$mailer->addAddress('info@khami-fitness.de');

		$transport = new class extends \Horde_Mail_Transport
		{
			public array $attempts = [];
			public function send($recipients, array $headers, $body)
			{
				$this->attempts[] = $recipients;
				if (count($this->attempts) === 1)
				{
					throw MailerRetryWithoutRejectedRecipientsTest::wrappedRecipientsException(
						['info@khami-fitness.de'],
						'<info@khami-fitness.de>: Recipient address rejected: Domain not found');
				}
			}
		};

		$mailer->send($transport);

		$this->assertCount(2, $transport->attempts, 'must have retried exactly once');
		$this->assertStringNotContainsString('khami-fitness.de', $transport->attempts[1],
			'the retry must exclude the rejected recipient from the envelope');
		$this->assertStringContainsString('good@example.org', $transport->attempts[1],
			'the retry must still include the good recipient');
		$this->assertSame(
			['info@khami-fitness.de' => 'Recipient address rejected: Domain not found'],
			$mailer->failedRecipients);
	}

	public function testDoesNotRetryOrReportAnythingForAnOrdinarySuccessfulSend()
	{
		$mailer = $this->mailer();
		$mailer->addAddress('good@example.org');

		$transport = new class extends \Horde_Mail_Transport
		{
			public array $attempts = [];
			public function send($recipients, array $headers, $body)
			{
				$this->attempts[] = $recipients;
			}
		};

		$mailer->send($transport);

		$this->assertCount(1, $transport->attempts);
		$this->assertSame([], $mailer->failedRecipients);
	}

	public function testRealFailureStillPropagatesWhenTheRetryAlsoFails()
	{
		$mailer = $this->mailer();
		$mailer->addAddress('good@example.org');
		$mailer->addAddress('info@khami-fitness.de');

		$transport = new class extends \Horde_Mail_Transport
		{
			public array $attempts = [];
			public function send($recipients, array $headers, $body)
			{
				$this->attempts[] = $recipients;
				if (count($this->attempts) === 1)
				{
					throw MailerRetryWithoutRejectedRecipientsTest::wrappedRecipientsException(
						['info@khami-fitness.de'], '<info@khami-fitness.de>: Domain not found');
				}
				// the retry itself hits an unrelated, genuine connection failure
				throw new \Horde_Mime_Exception(new \Horde_Mail_Exception(
					new \Horde_Smtp_Exception('Server is not accepting SMTP connections.')));
			}
		};

		$this->expectException(\Horde_Mime_Exception::class);
		try
		{
			$mailer->send($transport);
		}
		finally
		{
			$this->assertCount(2, $transport->attempts, 'must still have attempted the retry');
			$this->assertSame([], $mailer->failedRecipients,
				'must not report a false partial success when the retry itself failed too');
		}
	}

	/**
	 * A single-recipient send whose one-and-only recipient is the rejected one: excluding it
	 * would leave NOTHING to send to, so retrying is pointless - and, per the user's own
	 * requirement, would risk replacing the ORIGINAL, already-detailed rejection reason with a
	 * likely much less clear "no recipients at all" failure from the pointless retry attempt
	 * instead. The very same original exception must propagate unchanged, on the FIRST attempt
	 * only - not a second, different one.
	 */
	public function testSkipsTheRetryEntirelyWhenNoRecipientsWouldBeLeft()
	{
		$mailer = $this->mailer();
		$mailer->addAddress('info@khami-fitness.de');

		$originalException = self::wrappedRecipientsException(
			['info@khami-fitness.de'], '<info@khami-fitness.de>: Recipient address rejected: Domain not found');
		$transport = new class($originalException) extends \Horde_Mail_Transport
		{
			public array $attempts = [];
			private $exception;
			function __construct($exception) { $this->exception = $exception; }
			public function send($recipients, array $headers, $body)
			{
				$this->attempts[] = $recipients;
				throw $this->exception;
			}
		};

		try
		{
			$mailer->send($transport);
			$this->fail('Expected the original exception to propagate');
		}
		catch (\Horde_Mime_Exception $e)
		{
			$this->assertSame($originalException, $e, 'must be the SAME exception, not a second one from a pointless retry');
		}

		$this->assertCount(1, $transport->attempts, 'must never have attempted a retry with nothing left to send to');
		$this->assertSame([], $mailer->failedRecipients,
			'a hard failure, not a partial success - the caller must fall back to the exception itself');
	}
}
