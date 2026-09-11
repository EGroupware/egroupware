<?php
/**
 * Investigation for the bug report (2026-09-09, ralf, relaying a tester report): "our exporting
 * and importing of .eml files is not byte exact and breaks s/mime or pgp signatures" - narrowed
 * by ralf to the SIGNED body specifically, not the (unsigned) envelope headers.
 *
 * This is the shim (local Dovecot-backed IMAP) counterpart to mail/js/test/
 * MailRawSourceByteFidelity.test.ts, which found the real bug on the JMAP-client side:
 * MailJmap.fetchRawSourceByBlobId() calls response.text(), which always UTF-8-decodes the bytes
 * (per the WHATWG Fetch spec) regardless of the message's own declared charset, silently
 * replacing any non-UTF-8-valid byte with U+FFFD - a real corruption risk for a genuinely 8-bit
 * (Content-Transfer-Encoding: 8bit) signed body, e.g. legacy windows-1252/ISO-8859-1 content
 * still common from older/corporate (notably Outlook) S/MIME senders.
 *
 * These tests exercise the shim's OWN core byte-transport primitives directly -
 * Api\Mail\Jmap\Imap::appendRawMessage() (the core of Email/import) and ::fetchRawMessage() (the
 * core of the raw-message download endpoint), plus ::uploadBytes()/readUploadedBlob() (the
 * upload-then-resolve half of Email/import) - against a stubbed Horde_Imap_Client_Socket, no live
 * IMAP/DB connection needed (both methods take an already-constructed $imap as a parameter, they
 * never call self::imapServer() internally). Pass criteria: byte-for-byte equality, proving
 * (or disproving) that THIS half of the pipeline is safe - i.e. isolating the bug to the
 * client-side response.text() step, not the shim's own IMAP append/fetch.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

class JmapShimRawMessageByteFidelityTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Imap::uploadPath() builds its temp-file path from $GLOBALS['egw_info']['server']['temp_dir']
	 * - normally set by doc/phpunit_bootstrap.php, not guaranteed here since this test may run
	 * under a bare bootstrap (see this class's own docblock on why: a concurrent session's WIP
	 * changes to that shared file can break the framework autoloader it also sets this up in).
	 */
	protected function setUp() : void
	{
		parent::setUp();
		if (empty($GLOBALS['egw_info']['server']['temp_dir']))
		{
			$GLOBALS['egw_info']['server']['temp_dir'] = sys_get_temp_dir();
		}
	}

	/**
	 * A fully ASCII/CRLF multipart/signed message (base64 signature, plain-ASCII signed body) -
	 * every byte here is already 7-bit-safe, so this is the "healthy" control case.
	 */
	private function asciiSafeSignedMessage() : string
	{
		return
			"Content-Type: multipart/signed; protocol=\"application/pkcs7-signature\"; ".
				"micalg=sha-256; boundary=\"sig-boundary\"\r\n".
			"\r\n".
			"--sig-boundary\r\n".
			"Content-Type: text/plain; charset=us-ascii\r\n".
			"Content-Transfer-Encoding: 7bit\r\n".
			"\r\n".
			"Hello, this is the signed body.\r\n".
			"--sig-boundary\r\n".
			"Content-Type: application/pkcs7-signature; name=\"smime.p7s\"\r\n".
			"Content-Transfer-Encoding: base64\r\n".
			"\r\n".
			"MIIBogYJKoZIhvcNAQcCoIIBkzCCAY8CAQExDzANBglghkgBZQMEAgEFADALBgkq\r\n".
			"--sig-boundary--\r\n";
	}

	/**
	 * Same structure, but the signed body is `Content-Transfer-Encoding: 8bit` with a genuine
	 * raw ISO-8859-1 byte (0xE9, "e" with an acute accent) sitting directly in the stream - a
	 * real, legitimate MIME shape (8BITMIME is a standard SMTP extension), and exactly the kind
	 * of content a real S/MIME signature would cover byte-for-byte. Written as a raw PHP string
	 * byte (double-quoted "\xE9"), NOT any UTF-8 encoding of "é" - PHP strings are plain byte
	 * arrays, so this is the actual single invalid-as-UTF-8 byte, same as the JS test's
	 * eightBitSignedBodyMessage().
	 */
	private function eightBitSignedBodyMessage() : string
	{
		return
			"Content-Type: multipart/signed; protocol=\"application/pkcs7-signature\"; ".
				"micalg=sha-256; boundary=\"sig-boundary\"\r\n".
			"\r\n".
			"--sig-boundary\r\n".
			"Content-Type: text/plain; charset=iso-8859-1\r\n".
			"Content-Transfer-Encoding: 8bit\r\n".
			"\r\n".
			"caf\xe9\r\n".
			"--sig-boundary\r\n".
			"Content-Type: application/pkcs7-signature; name=\"smime.p7s\"\r\n".
			"Content-Transfer-Encoding: base64\r\n".
			"\r\n".
			"MIIBogYJKoZIhvcNAQcCoIIBkzCCAY8CAQExDzANBglghkgBZQMEAgEFADALBgkq\r\n".
			"--sig-boundary--\r\n";
	}

	/**
	 * @return \Horde_Imap_Client_Socket&\PHPUnit\Framework\MockObject\Stub
	 *
	 * Also stubs search(), not just append() - found while writing this test: real Horde
	 * responses aside, appendRawMessage()'s own `isset($ret->ids)` check (Imap.php:2143) can
	 * never be true for a REAL Horde_Imap_Client_Ids object either, since that class defines a
	 * magic __get('ids') but no __isset() - PHP's isset() on an object property with __get but
	 * no __isset always returns false, confirmed directly against the real class (`php -r`:
	 * isset($ids->ids) === false even though $ids->ids itself correctly returns the array).
	 * appendRawMessage() therefore always falls through to its own documented "server didn't
	 * report UIDPLUS-style ids" search() fallback in practice, today, for every call - a real,
	 * separate bug worth flagging back (not in scope for this byte-fidelity investigation), but
	 * this stub has to replicate that actual current behaviour to exercise appendRawMessage() at
	 * all.
	 */
	private function stubImapCapturingAppend(string $expectedRaw, int $assignedUid, ?array &$capturedData = null)
	{
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		$imap->method('append')->willReturnCallback(function($mailbox, $data) use (&$capturedData)
		{
			$capturedData = $data;
			return new \Horde_Imap_Client_Ids([]);
		});
		$imap->method('search')->willReturn(['match' => new \Horde_Imap_Client_Ids([$assignedUid])]);
		$fetchFixture = new \Horde_Imap_Client_Data_Fetch();
		$fetchFixture->setFullMsg($expectedRaw);
		$imap->method('fetch')->willReturn([$assignedUid => $fetchFixture]);
		return $imap;
	}

	private function invokeAppendRawMessage(\Horde_Imap_Client_Socket $imap, string $mailbox, string $raw, array $flags=[]) : array
	{
		$method = new \ReflectionMethod(JmapShim::class, 'appendRawMessage');
		$method->setAccessible(true);
		return $method->invoke(null, $imap, $mailbox, $raw, $flags);
	}

	/**
	 * Control case: appendRawMessage() must pass the exact bytes it's given straight to
	 * append(), and fetchRawMessage() must return exactly what fetch() reports back - for an
	 * already-7-bit-safe message, proving the harness/round-trip itself is sound before testing
	 * the case that actually matters.
	 */
	public function testAppendThenFetchRoundTripsAsciiSafeMessageByteForByte()
	{
		$original = $this->asciiSafeSignedMessage();
		$captured = null;
		$imap = $this->stubImapCapturingAppend($original, 42, $captured);

		$appended = $this->invokeAppendRawMessage($imap, 'INBOX', $original);

		$this->assertSame($original, $captured[0]['data'], "append() must receive the exact original bytes");
		$this->assertSame(strlen($original), $appended['size']);

		$fetched = JmapShim::fetchRawMessage($imap, 'INBOX', $appended['id']);

		$this->assertSame($original, $fetched, "fetchRawMessage() must return byte-identical content");
	}

	/**
	 * The actual regression check: a genuinely 8-bit signed body (the shape the JS-side bug was
	 * found against) must ALSO survive appendRawMessage()+fetchRawMessage() byte-for-byte. If
	 * this test passes (as expected - neither method decodes/re-encodes anything, see their own
	 * docblocks), it rules the shim's own IMAP append/fetch layer OUT as a source of the reported
	 * corruption, isolating the bug to the client-side response.text() step instead.
	 */
	public function testAppendThenFetchRoundTripsEightBitSignedBodyByteForByte()
	{
		$original = $this->eightBitSignedBodyMessage();
		$this->assertStringContainsString("\xe9", $original, "sanity check: the fixture really does contain the raw non-UTF-8 byte");
		$captured = null;
		$imap = $this->stubImapCapturingAppend($original, 43, $captured);

		$appended = $this->invokeAppendRawMessage($imap, 'INBOX', $original);

		$this->assertSame($original, $captured[0]['data']);

		$fetched = JmapShim::fetchRawMessage($imap, 'INBOX', $appended['id']);

		$this->assertSame($original, $fetched,
			"the shim's own append/fetch must be byte-exact even for a genuinely 8-bit signed body - ".
			"unlike the JS client's fetchRawSourceByBlobId(), see this file's own docblock");
		$this->assertSame(strlen($original), strlen($fetched), "no byte may be added, dropped, or substituted");
	}

	/**
	 * The upload-then-resolve half of Email/import (Imap::upload()'s Imap::uploadBytes(), and
	 * emailImport()'s own Imap::readUploadedBlob() "upload:<token>" branch) - a real temp-file
	 * write+read hop, must also be byte-exact for 8-bit content. Exercised directly (not via the
	 * public emailImport()/upload() HTTP entry points, which need a DB-backed account for
	 * imapServer() - see this file's own docblock) since both are plain, DB-free static helpers.
	 */
	public function testUploadBytesThenReadUploadedBlobRoundTripsEightBitContentByteForByte()
	{
		$original = $this->eightBitSignedBodyMessage();

		$uploaded = JmapShim::uploadBytes($original, 'message/rfc822');
		$this->assertStringStartsWith('upload:', $uploaded['blobId']);
		$this->assertSame(strlen($original), $uploaded['size']);

		$readMethod = new \ReflectionMethod(JmapShim::class, 'readUploadedBlob');
		$readMethod->setAccessible(true);
		$resolved = $readMethod->invoke(null, '0', $uploaded['blobId']);

		$this->assertSame($original, $resolved, "uploadBytes()+readUploadedBlob() must round-trip 8-bit content byte-for-byte");

		// clean up the temp file the same way emailImport() itself does after a successful import
		$pathMethod = new \ReflectionMethod(JmapShim::class, 'uploadPath');
		$pathMethod->setAccessible(true);
		@unlink($pathMethod->invoke(null, substr($uploaded['blobId'], strlen('upload:'))));
	}

	/**
	 * Explicit CRLF-preservation check - implied by the byte-for-byte assertions above, but
	 * called out on its own since CRLF-vs-LF drift is THE textbook cause of a MIME/S-MIME
	 * signature failing to verify after any kind of transport (RFC 1847's canonicalization is
	 * defined over CRLF line endings specifically).
	 */
	public function testAppendThenFetchPreservesCrlfLineEndingsExactly()
	{
		$original = $this->eightBitSignedBodyMessage();
		$captured = null;
		$imap = $this->stubImapCapturingAppend($original, 44, $captured);
		$appended = $this->invokeAppendRawMessage($imap, 'INBOX', $original);
		$fetched = JmapShim::fetchRawMessage($imap, 'INBOX', $appended['id']);

		$this->assertSame(substr_count($original, "\r\n"), substr_count($fetched, "\r\n"));
		// no lone "\n" without a preceding "\r" anywhere - i.e. every line ending is still CRLF
		$this->assertSame(0, preg_match('/(?<!\r)\n/', $fetched), "every line ending must still be CRLF, never a bare LF");
	}
}
