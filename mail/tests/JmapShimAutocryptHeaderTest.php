<?php
/**
 * Test EGroupware\Api\Mail\Jmap\Imap's Autocrypt header re-fetch (AUTOCRYPT_HEADER_PROPERTY) - the
 * second half of a live-reported regression, same bug class as JmapShimSendTimeHeadersRegressionTest.php
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

/**
 * Live regression (2026-09-09, ralf, testing Autocrypt Phase 5 item 3's just-added sending half):
 * "It sends now, but no Autocrypt header" - the CREATE-time write path (MailJmap.
 * draftEmailProperties()/buildMailerFromEmailProperties() reading the bare `header:Autocrypt`
 * property) was already correct (confirmed live: the freshly-built draft's raw MIME bytes DO
 * contain "Autocrypt:"), but emailSubmissionSet()'s actual send path does NOT reuse that draft
 * as-is - it re-fetches the already-stored Draft via emailGet() and rebuilds a FRESH Mailer from
 * THAT (see its own docblock), and emailGet()/emailFromFetch() had NO Autocrypt handling
 * whatsoever - unlike Stalwart's own native JMAP-over-HTTP (which genuinely supports ANY
 * "header:X:form" property per RFC 8621 §4.1.3 with no server-side code needed here at all,
 * confirmed live against a real Stalwart account BEFORE this fix), this shim's emailGet() has NO
 * generic header mechanism, only an explicit per-header allowlist (same class of gap
 * JmapShimSendTimeHeadersRegressionTest.php already covers for replyTo/X-Priority/
 * Disposition-Notification-To) - so the Autocrypt header was silently dropped again at actual
 * send time even though the draft itself had it correctly.
 *
 * emailFromFetch() is public static and takes an already-constructed Horde_Imap_Client_Socket/
 * Horde_Imap_Client_Data_Fetch as plain parameters (same as JmapShimSendTimeHeadersRegressionTest.php),
 * so this is directly testable with a stub socket and a real Horde_Imap_Client_Data_Fetch
 * fixture. No DB/IMAP/session needed.
 */
class JmapShimAutocryptHeaderTest extends \PHPUnit\Framework\TestCase
{
	private function emailFromFetch(\Horde_Imap_Client_Data_Fetch $data, bool $wantAutocrypt) : array
	{
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		return JmapShim::emailFromFetch($imap, 'INBOX', '42', $data,
			false, false, false, false, false, false, false, $wantAutocrypt);
	}

	public function testAutocryptHeaderIsPopulatedAsAnArrayWhenRequestedAndPresent()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setHeaders('autocrypt', "Autocrypt: addr=rb@egroupware.org; keydata=AAAABASE64KEYDATA\r\n\r\n");

		$email = $this->emailFromFetch($data, true);

		$this->assertSame(['addr=rb@egroupware.org; keydata=AAAABASE64KEYDATA'], $email[JmapShim::AUTOCRYPT_HEADER_PROPERTY]);
	}

	public function testAutocryptHeaderIsAnEmptyArrayNotNullWhenRequestedButAbsent()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();

		$email = $this->emailFromFetch($data, true);

		$this->assertSame([], $email[JmapShim::AUTOCRYPT_HEADER_PROPERTY],
			"RFC 8621 :all suffix - absent means empty array, never null");
	}

	/** RFC 8621 §4.1.3 ":all" suffix - every instance, in order, as a plain array. */
	public function testMultipleAutocryptHeaderInstancesAreAllReturned()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setHeaders('autocrypt',
			"Autocrypt: addr=a@example.invalid; keydata=FIRST\r\n".
			"Autocrypt: addr=b@example.invalid; keydata=SECOND\r\n\r\n");

		$email = $this->emailFromFetch($data, true);

		$this->assertSame(
			['addr=a@example.invalid; keydata=FIRST', 'addr=b@example.invalid; keydata=SECOND'],
			$email[JmapShim::AUTOCRYPT_HEADER_PROPERTY]);
	}

	/**
	 * allHeaderValues() is RFC 2047 MIME-decoded, same as firstHeaderValue()'s header properties -
	 * this is unavoidable, not a deliberate choice: Horde_Imap_Client_Data_Fetch::getHeaders()'s
	 * HEADER_PARSE mode (what emailFromFetch() uses) decodes internally while parsing, before this
	 * code ever sees the value, so there's no separate decode step here to skip. Verified harmless
	 * for real Autocrypt data even so: `keydata=` is pure base64 (alphabet A-Za-z0-9+/=), which can
	 * never contain the "?" an RFC 2047 encoded word requires, so genuine keydata can never be
	 * mistaken for one and altered by this decoding.
	 */
	public function testAutocryptHeaderValueIsMimeDecodedLikeOtherHeaders()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		// "=?UTF-8?B?wqk=?=" is a complete, valid RFC 2047 encoded word for "\xC2\xA9" (a copyright
		// sign) - included here only to document the actual (decoding) behaviour; it can't occur in
		// real base64 keydata, so this has no practical effect on genuine Autocrypt headers.
		$data->setHeaders('autocrypt', "Autocrypt: addr=rb@egroupware.org; keydata=AAAA=?UTF-8?B?wqk=?=BBBB\r\n\r\n");

		$email = $this->emailFromFetch($data, true);

		$this->assertSame(["addr=rb@egroupware.org; keydata=AAAA\xC2\xA9BBBB"], $email[JmapShim::AUTOCRYPT_HEADER_PROPERTY]);
	}

	/** Only present at all when actually requested - same "don't pay for it unless asked" contract as every other header property here. */
	public function testAutocryptHeaderIsOmittedEntirelyWhenNotRequested()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setHeaders('autocrypt', "Autocrypt: addr=rb@egroupware.org; keydata=X\r\n\r\n");

		$email = $this->emailFromFetch($data, false);

		$this->assertArrayNotHasKey(JmapShim::AUTOCRYPT_HEADER_PROPERTY, $email);
	}
}
