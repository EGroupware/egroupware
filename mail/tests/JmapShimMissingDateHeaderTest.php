<?php
/**
 * Test EGroupware\Api\Mail\Jmap\Imap::emailFromFetch()'s sentAt when the message has no Date: header
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

/**
 * Ticket #126291 (live, 2026-10): a device (a "Gude Expert Power Control" power-strip notifier)
 * sends messages with NO Date: header at all. Horde_Imap_Client_Data_Envelope::__get('date')
 * (vendor/egroupware/imap-client/lib/Horde/Imap/Client/Data/Envelope.php) has a documented defect
 * ("@todo $date should return null if it doesn't exist") - when the ENVELOPE has no Date:, it
 * returns a FRESH `Horde_Imap_Client_DateTime("now")` on every single access, not null. Trusting
 * that unconditionally (as emailFromFetch() used to) made every message from this sender show the
 * SAME timestamp, re-advancing to "now" on every reload - exactly the reported symptom.
 *
 * __isset('date') (not a truthy check on the value itself, which is always a valid non-null
 * DateTime either way) is the only way to tell a real Date: from Horde's silent placeholder - it
 * reports whether the underlying raw header was actually present. Same mocking pattern as
 * JmapShimSendTimeHeadersRegressionTest.php.
 */
class JmapShimMissingDateHeaderTest extends \PHPUnit\Framework\TestCase
{
	private function emailFromFetch(\Horde_Imap_Client_Data_Fetch $data) : array
	{
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		return JmapShim::emailFromFetch($imap, 'INBOX', '42', $data);
	}

	public function testSentAtUsesTheRealDateHeaderWhenPresent()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setEnvelope(['date' => 'Mon, 12 Oct 2026 08:32:46 +0200']);
		$data->setImapDate('Mon, 12 Oct 2026 09:00:00 +0200');

		$email = $this->emailFromFetch($data);

		$this->assertSame('2026-10-12T06:32:46Z', $email['sentAt'], "real Date: header, converted to UTC");
		$this->assertSame('2026-10-12T07:00:00Z', $email['receivedAt'], "receivedAt stays INTERNALDATE-based, independent of sentAt");
	}

	public function testSentAtFallsBackToInternalDateWhenDateHeaderIsAbsent()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		// no setEnvelope() call at all - getEnvelope() returns a bare Envelope with no 'date' key,
		// the exact same shape Horde parses from a real ENVELOPE response with NIL for the date
		$data->setImapDate('Mon, 12 Oct 2026 09:00:00 +0200');

		$email = $this->emailFromFetch($data);

		$this->assertSame($email['receivedAt'], $email['sentAt'],
			"must fall back to the already INTERNALDATE-based receivedAt, not Horde's silent \"now\" placeholder");
		$this->assertSame('2026-10-12T07:00:00Z', $email['sentAt']);
	}

	public function testSentAtDoesNotAdvanceToNowOnRepeatedCallsWhenDateHeaderIsAbsent()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setImapDate('Mon, 12 Oct 2026 09:00:00 +0200');

		$first = $this->emailFromFetch($data)['sentAt'];
		usleep(1100000);
		$second = $this->emailFromFetch($data)['sentAt'];

		$this->assertSame($first, $second, "a stable, stored value - not Horde's envelope->date re-evaluating to \"now\" on every call");
	}
}
