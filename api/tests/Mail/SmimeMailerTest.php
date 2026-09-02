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
