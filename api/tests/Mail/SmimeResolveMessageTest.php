<?php
/**
 * EGroupware Api: Mail\Smime::resolveMessage() view-side tests - PassphraseMissing + TNEF
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
 * Covers resolveMessage()'s PassphraseMissing error path - the exact signal
 * resolveSpecialCaseBody()/tryJmapNativeSpecialCase() turn into the client-side passphrase
 * dialog (see doc/ai/projects/mail-compose-jmap-migration.md) - and Api\Mail::tnef_decoder(), the
 * pure decoding half of resolveTnef() (the IMAP-fetch half needs a live/mocked
 * Horde_Imap_Client_Socket, out of scope here, same principle as Jmap/ImapBuildMailerTest.php's
 * own docblock).
 *
 * Unlike SmimeMailerTest.php (which deliberately never writes S/MIME credentials to the shared
 * acc_id=1 test account - it only ever generates certificates and passes them explicitly),
 * resolveMessage()'s PassphraseMissing path genuinely needs a REAL stored account credential to
 * exercise at all (get_acc_smime()'s own Credentials::read() call resolves one FOR an account,
 * not from anything the caller passes in) - written here scoped to the CURRENT test user's own
 * account_id (Mail\Credentials::write()'s $account_id param, matching admin_mail's own
 * "acting on behalf of a specific user" pattern for a shared account), and always removed again
 * in tearDown(), so this never affects any other (real or concurrent-test) user's own view of
 * acc_id=1's credentials.
 */
class SmimeResolveMessageTest extends Api\LoggedInTest
{
	private const ACC_ID = 1;
	private const DN = [
		'countryName' => 'DE', 'stateOrProvinceName' => 'Berlin', 'localityName' => 'Berlin',
		'organizationName' => 'EGroupware', 'commonName' => 'PHPUnit ResolveMessage',
		'emailAddress' => 'phpunit-resolvemessage@example.org',
	];
	private const PASSPHRASE = 'correct horse battery staple';

	protected function tearDown() : void
	{
		Credentials::delete(self::ACC_ID, $GLOBALS['egw_info']['user']['account_id'], Credentials::SMIME);
		Api\Cache::unsetSession('mail', 'smime_passphrase');
		unset($_SESSION[Api\Session::EGW_APPSESSION_VAR]['mail']['smime_passphrase']);
		parent::tearDown();
	}

	/**
	 * Stores a real S/MIME credential (self-signed cert + passphrase-protected private key) for
	 * acc_id=1, scoped to the current test user's own account_id - see this class' own docblock.
	 *
	 * @return array{cert: string} the certificate (for building a test message against)
	 */
	private function storeCredential() : array
	{
		$generated = (new Smime())->generate_certificate(self::DN, null, self::PASSPHRASE, 30);
		$p12 = Smime::build_pkcs12($generated['privkey'], $generated['cert'], self::PASSPHRASE, self::PASSPHRASE);
		Credentials::write(self::ACC_ID, self::DN['emailAddress'], $p12, Credentials::SMIME,
			$GLOBALS['egw_info']['user']['account_id']);
		return ['cert' => $generated['cert']];
	}

	/** Builds+returns the raw bytes of an encrypted (TYPE_ENCRYPT) message to the given cert. */
	private function buildEncryptedMessage(string $cert) : string
	{
		$mailer = new Api\Mailer(self::ACC_ID);
		$mailer->addAddress(self::DN['emailAddress'], 'Recipient');
		$mailer->addHeader('Subject', 'PHPUnit resolveMessage test');
		$mailer->setBody("secret payload\n");
		$this->assertTrue($mailer->smimeEncrypt(Smime::TYPE_ENCRYPT, [
			'senderPubKey' => $cert,
			'recipientsCerts' => [self::DN['emailAddress'] => $cert],
		]));
		return $mailer->getRaw(false);
	}

	/**
	 * No passphrase given, none session-cached - get_acc_smime() can't unlock the stored private
	 * key at all, so resolveMessage() must throw PassphraseMissing rather than a generic error or
	 * silently returning unusable content.
	 */
	public function testPassphraseMissingWhenNoPassphraseGiven()
	{
		$cert = $this->storeCredential()['cert'];
		$raw = $this->buildEncryptedMessage($cert);

		$this->expectException(Smime\PassphraseMissing::class);
		Smime::resolveMessage(self::ACC_ID, $raw, 'application/pkcs7-mime', '');
	}

	/** A given but WRONG passphrase must also throw PassphraseMissing, not a generic failure. */
	public function testPassphraseMissingWhenWrongPassphraseGiven()
	{
		$cert = $this->storeCredential()['cert'];
		$raw = $this->buildEncryptedMessage($cert);

		$this->expectException(Smime\PassphraseMissing::class);
		Smime::resolveMessage(self::ACC_ID, $raw, 'application/pkcs7-mime', 'definitely wrong');
	}

	/**
	 * The correct passphrase must NOT throw, and must actually decrypt the original content -
	 * the positive-path counterpart to the two tests above, confirming they fail for the right
	 * reason (a genuinely wrong/missing passphrase) and not because resolveMessage() is broken
	 * outright.
	 */
	public function testResolveMessageDecryptsWithCorrectPassphrase()
	{
		$cert = $this->storeCredential()['cert'];
		$raw = $this->buildEncryptedMessage($cert);

		$structure = Smime::resolveMessage(self::ACC_ID, $raw, 'application/pkcs7-mime', self::PASSPHRASE);

		$this->assertStringContainsString('secret payload', $structure->getContents());
	}

	/**
	 * A still-needed passphrase falls back to the session-cached one (Api\Cache 'mail'/
	 * 'smime_passphrase') when none is explicitly given - the exact mechanism
	 * resolveSpecialCaseBody()'s own retry loop relies on (see its docblock: "falls back to the
	 * cached session passphrase, same as Mail::_decryptSmimeBody() already does").
	 */
	public function testResolveMessageFallsBackToSessionCachedPassphrase()
	{
		$cert = $this->storeCredential()['cert'];
		$raw = $this->buildEncryptedMessage($cert);
		// Api\Cache::setSession() defers writes to an in-memory buffer getSession() never
		// consults when session_status() isn't PHP_SESSION_ACTIVE (true for a PHPUnit CLI run,
		// a separately-tracked issue unrelated to resolveMessage() itself) - writing $_SESSION
		// directly, the exact shape Api\Cache itself uses, isolates testing resolveMessage()'s
		// own fallback logic from that.
		$_SESSION[Api\Session::EGW_APPSESSION_VAR]['mail']['smime_passphrase'] = self::PASSPHRASE;

		$structure = Smime::resolveMessage(self::ACC_ID, $raw, 'application/pkcs7-mime', '');

		$this->assertStringContainsString('secret payload', $structure->getContents());
	}

	/**
	 * Api\Mail::tnef_decoder() - the pure decoding half of resolveTnef() (RFC822/IMAP-fetch is the
	 * other half, needing a live/mocked IMAP connection, out of scope here). Uses the forked
	 * Horde Compress package's OWN bundled test fixture (vendor/egroupware/compress/test/...,
	 * a real winmail.dat sample already vendored for Horde's own TnefTest.php - not fetched from
	 * anywhere new) rather than trying to construct a synthetic TNEF blob by hand.
	 */
	public function testTnefDecoderDecodesRealWinmailFixture()
	{
		$fixture = realpath(__DIR__.'/../../../vendor/egroupware/compress/test/Horde/Compress/fixtures/winmail2.dat');
		$this->assertNotFalse($fixture, 'vendored winmail2.dat TNEF fixture not found');

		$decoded = Api\Mail::tnef_decoder(file_get_contents($fixture));

		$this->assertNotFalse($decoded, 'tnef_decoder() must successfully decode a real TNEF sample');
		$this->assertEquals('multipart/mixed', strtolower($decoded->getType()));
		$part = $decoded->getParts()[array_key_first($decoded->getParts())] ?? null;
		$this->assertNotNull($part, 'decoded structure must have at least one part');
		$this->assertEquals('text/calendar', strtolower($part->getType()));
		$this->assertSame('Test Meeting', $part->getName());
	}

	/** tnef_decoder() must fail cleanly (false, not a fatal) for data that isn't valid TNEF at all. */
	public function testTnefDecoderReturnsFalseForInvalidData()
	{
		$this->assertFalse(@Api\Mail::tnef_decoder('this is not a TNEF blob'));
	}
}
