<?php
/**
 * Test EGroupware\Api\Mail\Jmap\Imap's send-time header re-fetch (replyTo/header:X-Priority/
 * header:Disposition-Notification-To) - the second half of a live-reported regression
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

/**
 * Live regression, found in two parts: first "the selected ReplyTo is NOT send with the mail"
 * (fixed 2026-09-09, commit de4945125a - the CREATE-time write path: MailJmap.draftEmailProperties()
 * / buildMailerFromEmailProperties()). Then reported AGAIN afterward: "I set a ReplyTo in the UI,
 * but neither the mail in Sent folder nor the received mail contains the Reply-To header" - because
 * that first fix only covered the CREATE-time path. The shim's actual send path,
 * emailSubmissionSet(), does NOT reuse the client's original create-time properties - it
 * re-fetches the already-stored Draft via emailGet() and rebuilds a FRESH Mailer from THAT (see
 * its own docblock), and emailGet()/emailFromFetch() never had any code to read replyTo/
 * header:X-Priority/header:Disposition-Notification-To back from a stored message at all, so all
 * three (found via code audit alongside the live-reported one: Priority and read-receipt-request
 * would have the identical bug) were silently dropped again at actual send time.
 *
 * emailFromFetch() is public static and takes an already-constructed Horde_Imap_Client_Socket/
 * Horde_Imap_Client_Data_Fetch as plain parameters (same as JmapShimReplyThreadHeadersTest.php),
 * so this is directly testable with a stub socket and a real Horde_Imap_Client_Data_Fetch
 * fixture. No DB/IMAP/session needed.
 */
class JmapShimSendTimeHeadersRegressionTest extends \PHPUnit\Framework\TestCase
{
	private function emailFromFetch(\Horde_Imap_Client_Data_Fetch $data, bool $wantSendHeaders) : array
	{
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		return JmapShim::emailFromFetch($imap, 'INBOX', '42', $data,
			false, false, false, false, false, false, $wantSendHeaders);
	}

	public function testReplyToIsPopulatedFromTheReplyToHeaderUnconditionally()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setHeaders('addresses', "Reply-To: Jane Doe <jane@example.org>\r\n\r\n");

		// unconditional, same as to/cc/bcc/from - never gated behind a $want* flag, since it costs
		// nothing extra once 'Reply-To' was added to the same always-fetched header group
		$email = $this->emailFromFetch($data, false);

		$this->assertSame([['email' => 'jane@example.org', 'name' => 'Jane Doe']], $email['replyTo']);
	}

	public function testReplyToIsAnEmptyArrayNotNullWhenTheHeaderIsAbsent()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();

		$email = $this->emailFromFetch($data, false);

		$this->assertSame([], $email['replyTo'], "same convention as to/cc/bcc - absent means empty array, never null");
	}

	public function testPriorityAndDispositionRequestHeadersArePopulatedWhenRequestedAndPresent()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setHeaders('sendheaders',
			"X-Priority: 1\r\n".
			"Disposition-Notification-To: sender@example.org\r\n\r\n");

		$email = $this->emailFromFetch($data, true);

		$this->assertSame('1', $email[JmapShim::PRIORITY_HEADER_PROPERTY]);
		$this->assertSame('sender@example.org', $email[JmapShim::DISPOSITION_REQUEST_HEADER_PROPERTY]);
	}

	public function testPriorityAndDispositionRequestHeadersAreNullWhenRequestedButAbsent()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();

		$email = $this->emailFromFetch($data, true);

		$this->assertNull($email[JmapShim::PRIORITY_HEADER_PROPERTY]);
		$this->assertNull($email[JmapShim::DISPOSITION_REQUEST_HEADER_PROPERTY]);
	}

	/** Only Priority present - Disposition-Notification-To must independently stay null, not fall back to some other value. */
	public function testEachSendHeaderIsResolvedIndependently()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setHeaders('sendheaders', "X-Priority: 1\r\n\r\n");

		$email = $this->emailFromFetch($data, true);

		$this->assertSame('1', $email[JmapShim::PRIORITY_HEADER_PROPERTY]);
		$this->assertNull($email[JmapShim::DISPOSITION_REQUEST_HEADER_PROPERTY]);
	}

	/** Only present at all when actually requested - same "don't pay for it unless asked" contract as MDN/content-type/thread headers. */
	public function testPriorityAndDispositionRequestHeadersAreOmittedEntirelyWhenNotRequested()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setHeaders('sendheaders', "X-Priority: 1\r\n\r\n");

		$email = $this->emailFromFetch($data, false);

		$this->assertArrayNotHasKey(JmapShim::PRIORITY_HEADER_PROPERTY, $email);
		$this->assertArrayNotHasKey(JmapShim::DISPOSITION_REQUEST_HEADER_PROPERTY, $email);
	}
}
