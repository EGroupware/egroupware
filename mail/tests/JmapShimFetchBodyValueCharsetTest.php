<?php
/**
 * Ticket-driven regression (2026-09-18, ralf): forwarding a real auto-generated German invoice
 * mail ("BSAS Mailservice") failed outright client-side with "Laden der ursprünglichen
 * Nachricht(en) fehlgeschlagen" / "Unexpected end of JSON input" - traced to
 * Api\Mail\Jmap\Imap::fetchBodyValue() blindly assuming utf-8 for a text/html part with no
 * declared charset, when the part was actually raw 8-bit Windows-1252/ISO-8859-1 content. That
 * left genuinely invalid utf-8 bytes in the JMAP response, and json_encode() then fails OUTRIGHT
 * for the entire response (not just this field) - surfacing as an empty body client-side.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

class JmapShimFetchBodyValueCharsetTest extends \PHPUnit\Framework\TestCase
{
	private function invokeFetchBodyValue(\Horde_Imap_Client_Socket $imap, string $mailbox, string $uid,
		\Horde_Mime_Part $structure, string $partId) : array
	{
		return JmapShim::fetchBodyValue($imap, $mailbox, $uid, $structure, $partId);
	}

	/**
	 * @param string $rawBytes the part's raw (already transfer-decoded) content
	 * @param ?string $charset Content-Type charset parameter, null = not declared at all (this
	 *  ticket's exact real-world shape)
	 */
	private function stubImapForPart(string $partId, string $rawBytes) : \Horde_Imap_Client_Socket
	{
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		$fetchFixture = new \Horde_Imap_Client_Data_Fetch();
		$fetchFixture->setBodyPart($partId, $rawBytes, '8bit');
		$imap->method('fetch')->willReturn([1 => $fetchFixture]);
		return $imap;
	}

	private function htmlPart(?string $charset) : \Horde_Mime_Part
	{
		$part = new \Horde_Mime_Part();
		$part->setType('text/html');
		$part->setMimeId('1');
		if ($charset !== null)
		{
			$part->setContentTypeParameter('charset', $charset);
		}
		return $part;
	}

	/**
	 * The real repro shape: no charset declared at all, raw Windows-1252 bytes (a German invoice
	 * mailer, "BSAS Mailservice") - must NOT be blindly treated as utf-8 (that produces invalid
	 * utf-8, breaking json_encode() for the whole response), and must decode to the correct text.
	 */
	public function testUndeclaredCharsetWithNonUtf8BytesDecodesViaDetection()
	{
		// "Gesamtsumme beträgt" with "ä" as the raw Windows-1252 byte 0xE4 (not any utf-8
		// encoding of "ä") - exactly the shape a real 8-bit, undeclared-charset German mail part
		// has on the wire.
		$raw = "Gesamtsumme betr\xe4gt: 63,98 EUR";
		$imap = $this->stubImapForPart('1', $raw);
		$structure = $this->htmlPart(null);

		$result = $this->invokeFetchBodyValue($imap, 'INBOX', '1', $structure, '1');

		$this->assertTrue(mb_check_encoding($result['value'], 'UTF-8'),
			'the decoded value must be valid utf-8, or json_encode() fails for the whole response');
		$this->assertSame("Gesamtsumme beträgt: 63,98 EUR", $result['value']);
		$this->assertFalse(json_encode(['v' => $result['value']]) === false,
			'json_encode() must not fail on the decoded value');
	}

	/**
	 * The ORIGINAL 2026-09-03 fix this must not regress: undeclared charset, but the content is
	 * genuinely already utf-8 (eg. a modern MUA that just omits the charset param for a pure-
	 * ASCII/utf-8 body) - must still decode correctly as utf-8, not get mangled by a windows-1252
	 * reinterpretation of already-correct utf-8 bytes.
	 */
	public function testUndeclaredCharsetWithGenuineUtf8BytesStaysUtf8()
	{
		// "café" with "é" as a genuine 2-byte utf-8 sequence (0xC3 0xA9), not a single 8-bit byte
		$raw = "caf\xc3\xa9";
		$imap = $this->stubImapForPart('1', $raw);
		$structure = $this->htmlPart(null);

		$result = $this->invokeFetchBodyValue($imap, 'INBOX', '1', $structure, '1');

		$this->assertSame('café', $result['value']);
	}

	/**
	 * An explicitly declared charset must still be honoured exactly as before - this fix only
	 * changes behaviour for the "nothing declared at all" case.
	 */
	public function testDeclaredCharsetIsStillHonoured()
	{
		$raw = "caf\xe9"; // iso-8859-1 "é"
		$imap = $this->stubImapForPart('1', $raw);
		$structure = $this->htmlPart('iso-8859-1');

		$result = $this->invokeFetchBodyValue($imap, 'INBOX', '1', $structure, '1');

		$this->assertSame('café', $result['value']);
	}
}
