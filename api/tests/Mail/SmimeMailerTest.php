<?php
/**
 * EGroupware Api: Api\Mailer::smimeEncrypt()/getRaw() + Mail\Smime::resolveMessage() round-trip tests
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

namespace EGroupware\Api\Mail;

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api;

/**
 * Covers the sign/encrypt send-side chain SmimeTest.php's own docblock flags as needing a
 * database-backed integration test: building a real signed/encrypted MIME message via
 * Api\Mailer::smimeEncrypt() + getRaw(), and reading it back via Mail\Smime::resolveMessage()/
 * Horde_Crypt_Smime::decrypt() - the exact chain resolveSpecialCaseBody()/tryJmapNativeSpecialCase()
 * (view) and smimeEncryptEmailProperties() (send) drive live.
 *
 * Uses acc_id=1 (doc/phpunit.xml's own "shared/'Everyone' mail account", present on every install)
 * purely to satisfy Api\Mailer's constructor (Mail\Account::read()), same as
 * Jmap\ImapBuildMailerTest - never sends, never touches any mailbox, and never writes S/MIME
 * credentials to this shared account (the sender/recipient certificates used here are generated
 * fresh per test via Smime::generate_certificate() and passed explicitly, never stored).
 *
 * Chain-of-trust verification is deliberately NOT asserted anywhere here: a self-signed test
 * certificate has no matching trust anchor, and whether openssl's default CA bundle considers it
 * "verified" is environment-dependent - these tests only assert that a signature was found/parsed
 * and that decryption recovers the original content, not the trust outcome.
 */
class SmimeMailerTest extends Api\LoggedInTest
{
	private const ACC_ID = 1;

	private const SENDER_DN = [
		'countryName' => 'DE', 'stateOrProvinceName' => 'Berlin', 'localityName' => 'Berlin',
		'organizationName' => 'EGroupware', 'commonName' => 'PHPUnit Sender',
		'emailAddress' => 'phpunit-sender@example.org',
	];
	private const RECIPIENT_DN = [
		'countryName' => 'DE', 'stateOrProvinceName' => 'Berlin', 'localityName' => 'Berlin',
		'organizationName' => 'EGroupware', 'commonName' => 'PHPUnit Recipient',
		'emailAddress' => 'phpunit-recipient@example.org',
	];

	protected function setUp() : void
	{
		parent::setUp();
		// Api\Mailer::smimeEncrypt() unconditionally overrides a given passphrase with whatever's
		// session-cached (a known, separately-tracked bug worked around elsewhere by pre-seeding
		// the cache with the correct value first) - a leftover value from an earlier test/session
		// must not silently break these, which all use an empty passphrase throughout
		Api\Cache::unsetSession('mail', 'smime_passphrase');
	}

	private function newMailer() : Api\Mailer
	{
		$mailer = new Api\Mailer(self::ACC_ID);
		$mailer->addAddress('recipient@example.org', 'Recipient');
		$mailer->addHeader('Subject', 'PHPUnit S/MIME test');
		$mailer->setBody("plain text body\n");
		return $mailer;
	}

	/**
	 * Regression test for the Api\Mailer::getRaw() double-wrap bug (found live 2026-09-02): it only
	 * re-synced the message's top-level headers (Content-Type/Message-ID/Date/...) from the base
	 * MIME part when getBasePart() threw (no base part set yet) - but smimeEncrypt() sets
	 * $this->_base DIRECTLY, bypassing that check entirely, so a sign-only message's top-level
	 * Content-Type never got updated to multipart/signed at all, and the actually-signed content
	 * ended up nested, headers and all, inside a bare (wrongly implied text/plain) outer envelope.
	 */
	public function testSignOnlyProducesSingleNonDoubleWrappedMessage()
	{
		$sender = (new Smime())->generate_certificate(self::SENDER_DN, null, null, 30);

		$mailer = $this->newMailer();
		$this->assertTrue($mailer->smimeEncrypt(Smime::TYPE_SIGN, [
			'senderPubKey' => $sender['cert'],
			'senderPrivKey' => $sender['privkey'],
			'passphrase' => '',
			'extracerts' => null,
		]));

		$raw = $mailer->getRaw(false);

		$structure = \Horde_Mime_Part::parseMessage($raw);
		$this->assertEquals('multipart/signed', strtolower($structure->getType()),
			'top-level Content-Type must be multipart/signed, not whatever it was before signing');
		$this->assertNotEmpty($structure->getContentTypeParameter('boundary'),
			'top-level Content-Type must carry the boundary parameter');
		$this->assertEquals('application/pkcs7-signature',
			strtolower($structure->getContentTypeParameter('protocol') ?? ''));
		$this->assertCount(2, $structure->getParts(), 'multipart/signed must have exactly 2 sub-parts');

		// the double-wrap symptom: the message's own headers end up duplicated as a second, nested
		// header block inside the body (see this test's own class docblock)
		$this->assertEquals(1, substr_count($raw, 'Subject: PHPUnit S/MIME test'),
			'Subject header must appear exactly once - a duplicate means the message got wrapped inside itself');

		$resolved = Smime::resolveMessage(self::ACC_ID, $raw, $structure->getType());
		$metadata = $resolved->getMetadata('X-EGroupware-Smime');
		$this->assertTrue($metadata['signed'] ?? false, 'resolveMessage() must detect the message as signed');
	}

	/**
	 * Reproduces emailSubmissionSet()'s own multipart/signed resend step ENTIRELY in-process (no
	 * IMAP/SMTP at all) - sign a message, get its raw bytes (simulating what got stored in Drafts),
	 * parse THOSE bytes fresh and set them as a NEW mailer's base part (exactly what
	 * emailSubmissionSet() does when resending a signed draft), then re-serialize and verify.
	 *
	 * Regression test for two real bugs found live 2026-09-02 via a real shim-sent signed message
	 * that verified successfully in-process but arrived flagged "verification failed - this
	 * message may have been tampered with" once actually transmitted through emailSubmissionSet()'s
	 * resend step:
	 *
	 * 1. signMIMEPart() signs the content part while it still carries a stale STATUS_BASEPART flag
	 *    (left over from getBasePart()'s own earlier "build the initial unsigned message" step),
	 *    which bakes an extra "MIME-Version: 1.0" header into what gets signed - a header a nested
	 *    sub-part should never carry per RFC 2045. A freshly re-parsed copy of the exact same
	 *    content naturally has no such in-memory-only flag, so it correctly omits that header -
	 *    different bytes than what was actually signed. Fixed by clearing the flag before signing.
	 * 2. Horde_Mime_Mail::send()'s own flowed-text handling sets a mixed-case "DelSp" Content-Type
	 *    parameter; Horde_Mime_Part::parseMessage() lowercases parameter names on read. Same
	 *    "signed bytes vs. reparsed bytes" divergence, fixed by normalizing parameter name casing
	 *    before signing.
	 */
	public function testResendingAStoredSignedMessageKeepsAValidSignature()
	{
		$sender = (new Smime())->generate_certificate(self::SENDER_DN, null, null, 30);

		$original = $this->newMailer();
		$this->assertTrue($original->smimeEncrypt(Smime::TYPE_SIGN, [
			'senderPubKey' => $sender['cert'],
			'senderPrivKey' => $sender['privkey'],
			'passphrase' => '',
			'extracerts' => null,
		]));
		$stored = $original->getRaw(false);

		// exactly emailSubmissionSet()'s own sequence: fresh mailer, parse the stored bytes, set as
		// base part (with the metadata flag fix), send (here: to a null transport, since we only
		// care about getRaw()'s own post-send output, same as getRaw()'s own null-transport
		// fallback), then verify
		$resend = $this->newMailer();
		$parsedBase = \Horde_Mime_Part::parseMessage($stored, ['forcemime' => true]);
		$parsedBase->setMetadata('X-EGroupware-Smime-signed', true);
		$resend->setBasePart($parsedBase);
		$resend->send(new \Horde_Mail_Transport_Null(), true);
		$resent = $resend->getRaw(false);

		// the wrapper's own preamble text (before the first boundary) is harmlessly different
		// (not part of what's actually signed - see signMIMEPart()'s own hardcoded string vs.
		// toString()'s generic multipart fallback text for an empty _contents), so align there
		// before comparing - everything from the first boundary onward must be byte-identical
		$align = fn(string $s) => substr($s, strpos($s, '--=_'));
		$this->assertSame($align($stored), $align($resent),
			're-sending an already-signed stored message must reproduce its signed content byte-for-byte');

		$resentStructure = \Horde_Mime_Part::parseMessage($resent);
		$resolved = Smime::resolveMessage(self::ACC_ID, $resent, $resentStructure->getType());
		$metadata = $resolved->getMetadata('X-EGroupware-Smime');
		// NOT asserting verify===true - a self-signed test cert has no trust anchor, so
		// verifySignature() legitimately returns verify=false even for a genuinely intact
		// signature (see this class' own docblock). What actually distinguishes "structurally
		// valid signature, just untrusted CA" from "signature broken" is whether a cert was
		// extracted at all - verifySignature() THROWS (metadata['cert'] never gets set) when the
		// cryptographic check itself fails, exactly the "tampered with" live bug being reproduced
		// here.
		$this->assertNotEmpty($metadata['cert'] ?? '',
			're-sending an already-signed stored message must not invalidate its signature: '.
			($metadata['msg'] ?? '(no message)'));
	}

	/**
	 * A signed message with non-ASCII body content must stay 7-bit clean end to end (quoted-
	 * printable-encoded, not bare 8bit) - a relay/content-scanning proxy along the way is not
	 * guaranteed to be 8bit-clean, and even a single altered byte in the signed content
	 * invalidates the signature entirely. Note: investigated live 2026-09-02 as a possible cause
	 * of a real shim-sent signed message arriving flagged as "verification failed" - this specific
	 * property already held (Horde_Mime_Part's own default encoding logic already picks
	 * quoted-printable for 8-bit text regardless of the 'encode' option given to toString()), so
	 * it did NOT explain that incident; kept as a genuine defensive assertion regardless.
	 */
	public function testSignOnlyWithNonAsciiBodyStays7bitClean()
	{
		$sender = (new Smime())->generate_certificate(self::SENDER_DN, null, null, 30);

		$mailer = $this->newMailer();
		$mailer->setBody("Mit freundlichen Grüßen\n");
		$this->assertTrue($mailer->smimeEncrypt(Smime::TYPE_SIGN, [
			'senderPubKey' => $sender['cert'],
			'senderPrivKey' => $sender['privkey'],
			'passphrase' => '',
			'extracerts' => null,
		]));

		$raw = $mailer->getRaw(false);

		// quoted-printable/7bit encoding escapes non-ASCII bytes as literal "=XX" sequences - the
		// transmitted bytes themselves must all be within the 7-bit ASCII range, or a relay that
		// isn't 8bit-clean could alter them and invalidate the signature
		$this->assertMatchesRegularExpression('/^[\x00-\x7F]*$/', $raw,
			'a signed message must be 7-bit clean throughout, not contain raw non-ASCII bytes');
	}

	/**
	 * TYPE_ENCRYPT: the resulting body entity must be a single opaque application/pkcs7-mime leaf
	 * (extractSmimeBodyBlob()'s own single-leaf assumption for the {type, blobId} bodyStructure
	 * swap) that the recipient can decrypt back to the original content.
	 */
	public function testEncryptOnlyRoundTrip()
	{
		$sender = (new Smime())->generate_certificate(self::SENDER_DN, null, null, 30);
		$recipient = (new Smime())->generate_certificate(self::RECIPIENT_DN, null, null, 30);

		$mailer = $this->newMailer();
		$this->assertTrue($mailer->smimeEncrypt(Smime::TYPE_ENCRYPT, [
			'senderPubKey' => $sender['cert'],
			'recipientsCerts' => ['recipient@example.org' => $recipient['cert']],
		]));

		$base = $mailer->getBasePart();
		$this->assertEquals('application/pkcs7-mime', strtolower($base->getType()));

		$smime = new Smime();
		$decrypted = $smime->decrypt($base->toString(['headers' => true, 'canonical' => true]), [
			'type' => 'message', 'pubkey' => $recipient['cert'], 'privkey' => $recipient['privkey'], 'passphrase' => '',
		]);
		$this->assertStringContainsString('plain text body', $decrypted);
	}

	/**
	 * TYPE_SIGN_ENCRYPT: same opaque single-leaf shape as encrypt-only (Horde_Crypt_Smime wraps the
	 * detached signature INSIDE the encrypted envelope for this type, unlike sign-only's own
	 * separate multipart/signed structure) - decrypting must recover the original content.
	 */
	public function testSignAndEncryptRoundTrip()
	{
		$sender = (new Smime())->generate_certificate(self::SENDER_DN, null, null, 30);
		$recipient = (new Smime())->generate_certificate(self::RECIPIENT_DN, null, null, 30);

		$mailer = $this->newMailer();
		$this->assertTrue($mailer->smimeEncrypt(Smime::TYPE_SIGN_ENCRYPT, [
			'senderPubKey' => $sender['cert'],
			'senderPrivKey' => $sender['privkey'],
			'passphrase' => '',
			'extracerts' => null,
			'recipientsCerts' => ['recipient@example.org' => $recipient['cert']],
		]));

		$base = $mailer->getBasePart();
		$this->assertEquals('application/pkcs7-mime', strtolower($base->getType()));

		$smime = new Smime();
		$decrypted = $smime->decrypt($base->toString(['headers' => true, 'canonical' => true]), [
			'type' => 'message', 'pubkey' => $recipient['cert'], 'privkey' => $recipient['privkey'], 'passphrase' => '',
		]);
		$this->assertStringContainsString('plain text body', $decrypted);
	}
}
