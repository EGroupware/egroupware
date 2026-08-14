<?php
/**
 * EGroupware Api: S/MIME certificate/key generation tests
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

namespace EGroupware\Api\Mail;

use PHPUnit\Framework\TestCase;

/**
 * Pure openssl-backed tests for Smime's key/certificate generation helpers
 * used by the admin_mail S/MIME dialog (self-signed certificate, CSR
 * export/generation, PKCS12 (re-)assembly for importing a CA-signed cert).
 *
 * These do not touch the database or a live IMAP/mail account - they only
 * exercise openssl_* through Smime's (static) methods. Storage
 * (Mail\Credentials::write()/read(), addressbook_bo::set_smime_keys()) is
 * NOT covered here and needs a database-backed integration test; see
 * admin/tests/SmimeGenerateTest.php for the validation branches of the
 * admin_mail methods that call into storage.
 */
class SmimeTest extends TestCase
{
	private const DN = array(
		'countryName' => 'DE',
		'stateOrProvinceName' => 'Berlin',
		'localityName' => 'Berlin',
		'organizationName' => 'EGroupware',
		'organizationalUnitName' => 'QA',
		'commonName' => 'PHPUnit Test',
		'emailAddress' => 'phpunit@example.org',
	);

	/**
	 * generate_certificate() must return a private key, CSR and self-signed
	 * certificate that all belong together, and must honour the requested
	 * validity period.
	 *
	 * Regression test: validity used to be read from a non-existent
	 * $_dn['validation'] array key (typo for 'validity'), so any requested
	 * validity was silently ignored and the 365 day default always used.
	 */
	public function testGenerateCertificateSelfSigned()
	{
		$smime = new Smime();
		$result = $smime->generate_certificate(self::DN, null, null, 30);

		$this->assertStringContainsString('BEGIN PRIVATE KEY', $result['privkey'] ?? '', 'private key missing/unexpectedly encrypted');
		$this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $result['csr'] ?? '', 'CSR missing from result');
		$this->assertStringContainsString('BEGIN CERTIFICATE', $result['cert'] ?? '', 'self-signed certificate missing from result');

		// certificate must actually validate against the returned private key
		$pkey = openssl_pkey_get_private($result['privkey']);
		$this->assertNotFalse($pkey, 'returned private key must be usable without a passphrase');
		$this->assertTrue(openssl_x509_check_private_key($result['cert'], $pkey),
			'self-signed certificate must match the returned private key');

		// requested 30 days validity must be honoured (see regression note above)
		$parsed = openssl_x509_parse($result['cert']);
		$days = round(($parsed['validTo_time_t'] - $parsed['validFrom_time_t']) / 86400);
		$this->assertEquals(30, $days, 'certificate validity must match the requested value, not the 365 day default');
	}

	/**
	 * A passphrase given to generate_certificate() must actually protect the
	 * returned private key.
	 *
	 * Regression test: the only caller (mail_ui::ajax_smimeGenCertificate(),
	 * now removed) used to invoke generate_certificate() with a wrong
	 * argument order/count, so the passphrase silently landed nowhere and was
	 * never applied to the generated key.
	 */
	public function testGenerateCertificateAppliesPassphrase()
	{
		$smime = new Smime();
		$result = $smime->generate_certificate(self::DN, null, 'correct horse battery staple', 30);

		$this->assertStringContainsString('ENCRYPTED PRIVATE KEY', $result['privkey'],
			'private key must be PEM-encrypted when a passphrase is given');
		$this->assertFalse(@openssl_pkey_get_private($result['privkey'], 'wrong passphrase'),
			'wrong passphrase must not unlock the private key');
		$this->assertNotFalse(openssl_pkey_get_private($result['privkey'], 'correct horse battery staple'),
			'correct passphrase must unlock the private key');
	}

	/**
	 * generate_csr() must build a CSR carrying the given DN for an already
	 * existing (passphrase protected) private key - this is what
	 * mail_ui::smimeExportCsr() and admin_mail's "Export CSR"/"Create CSR for
	 * new private key" buttons rely on.
	 */
	public function testGenerateCsrForExistingKey()
	{
		$pkey = openssl_pkey_new(array(
			'digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA,
		));
		openssl_pkey_export($pkey, $privkey, 'secret');

		$csr = Smime::generate_csr($privkey, self::DN, 'secret');

		$this->assertNotFalse($csr, 'CSR generation must succeed with the correct passphrase');
		$this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $csr);
		$subject = openssl_csr_get_subject($csr);
		$this->assertEquals(self::DN['commonName'], $subject['CN'], 'CSR must carry the given commonName');
		$this->assertEquals(self::DN['emailAddress'], $subject['emailAddress'], 'CSR must carry the given emailAddress');
	}

	/**
	 * generate_csr() must fail cleanly (return false) rather than emit a
	 * fatal error, when given the wrong passphrase for the private key.
	 */
	public function testGenerateCsrFailsWithWrongPassphrase()
	{
		$pkey = openssl_pkey_new(array(
			'digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA,
		));
		openssl_pkey_export($pkey, $privkey, 'secret');

		$this->assertFalse(@Smime::generate_csr($privkey, self::DN, 'wrong'),
			'CSR generation must fail for a private key that cannot be unlocked');
	}

	/**
	 * build_pkcs12() must combine a private key and its matching certificate
	 * into a p12 that reads back both - this is how "Create self-signed
	 * certificate"/"Create CSR for new private key" store a freshly generated
	 * key, and how "Import certificate" re-combines a CA-signed certificate
	 * with the already stored private key.
	 */
	public function testBuildPkcs12RoundTrip()
	{
		$smime = new Smime();
		$generated = $smime->generate_certificate(self::DN, null, null, 365);

		$p12 = Smime::build_pkcs12($generated['privkey'], $generated['cert'], '', 'p12pass');
		$this->assertNotFalse($p12, 'build_pkcs12() must succeed for a matching key+cert pair');

		$certs = array();
		$this->assertTrue(openssl_pkcs12_read($p12, $certs, 'p12pass'), 'resulting p12 must be readable with its export password');
		$subject = openssl_x509_parse($certs['cert'] ?? '')['subject'] ?? array();
		$this->assertEquals(self::DN['commonName'], $subject['CN'] ?? null, 'p12 must contain the original certificate');
	}

	/**
	 * build_pkcs12() must reject a certificate that does not belong to the
	 * given private key, rather than silently building a broken p12.
	 *
	 * This backs admin_mail::import_smime_cert()'s "Certificate does not
	 * match the stored private key!" validation error - protects against a
	 * user uploading a certificate for the wrong key/account.
	 */
	public function testBuildPkcs12FailsOnMismatchedKey()
	{
		$smime = new Smime();
		$certA = $smime->generate_certificate(self::DN, null, null, 365);
		$certB = $smime->generate_certificate(array('commonName' => 'Other', 'emailAddress' => 'other@example.org'), null, null, 365);

		$this->assertFalse(@Smime::build_pkcs12($certA['privkey'], $certB['cert']),
			'combining a private key with a non-matching certificate must fail, not silently succeed');
	}

	/**
	 * dn_from_cert() must map openssl_x509_parse()'s short subject keys (C,
	 * ST, ...) back to the long-form DN keys openssl_csr_new()/
	 * generate_certificate() expect - used to prefill "Export CSR" with the
	 * currently stored certificate's DN.
	 */
	public function testDnFromCert()
	{
		$smime = new Smime();
		$generated = $smime->generate_certificate(self::DN, null, null, 365);

		$dn = Smime::dn_from_cert($generated['cert']);

		foreach (self::DN as $key => $value)
		{
			$this->assertEquals($value, $dn[$key] ?? null, "dn_from_cert() must round-trip the '$key' field");
		}
	}

	/**
	 * build_pkcs12()'s $extracerts must end up in the resulting p12 - this is how an intermediate
	 * CA certificate uploaded alongside a CA-signed certificate reaches outgoing signed mail:
	 * mail_compose::_encrypt() reads get_acc_smime()['extracerts'] back out of exactly this and
	 * passes it into Horde_Crypt_Smime/openssl_pkcs7_sign(), which embeds it in the signed
	 * message so recipients can validate the certificate chain.
	 */
	public function testBuildPkcs12WithExtracertsRoundTrip()
	{
		$smime = new Smime();
		$leaf = $smime->generate_certificate(self::DN, null, null, 365);
		$intermediate = $smime->generate_certificate(
			array('commonName' => 'Intermediate CA', 'emailAddress' => 'ca@example.org'), null, null, 365);

		$p12 = Smime::build_pkcs12($leaf['privkey'], $leaf['cert'], '', 'p12pass', array($intermediate['cert']));
		$this->assertNotFalse($p12, 'build_pkcs12() must succeed with extracerts given');

		$certs = array();
		$this->assertTrue(openssl_pkcs12_read($p12, $certs, 'p12pass'));
		$this->assertCount(1, $certs['extracerts'] ?? array(), 'p12 must contain exactly the one given extra certificate');
		$subject = openssl_x509_parse($certs['extracerts'][0] ?? '')['subject'] ?? array();
		$this->assertEquals('Intermediate CA', $subject['CN'] ?? null);
	}

	/**
	 * Strips PEM armor and returns the raw DER bytes - used to build DER test fixtures without
	 * shelling out to the openssl CLI (openssl_x509_export() only ever produces PEM; PEM is
	 * simply base64(DER) wrapped in "-----BEGIN...-----" armor, so reversing that is exact).
	 */
	private function pemToDer(string $pem) : string
	{
		$body = preg_replace('/-----(BEGIN|END) [^-]+-----|\r|\n/', '', $pem);
		return base64_decode($body);
	}

	/**
	 * normalize_cert_pem() must pass a single, already-PEM certificate through unchanged (as the
	 * sole array element) - the common case, eg. re-processing what generate_certificate() itself
	 * produced.
	 */
	public function testNormalizeCertPemSinglePem()
	{
		$smime = new Smime();
		$generated = $smime->generate_certificate(self::DN, null, null, 365);

		$certs = Smime::normalize_cert_pem($generated['cert']);

		$this->assertCount(1, $certs);
		$this->assertSame(trim($generated['cert']), trim($certs[0]));
	}

	/**
	 * normalize_cert_pem() must split several PEM certificates concatenated in one file into
	 * separate array entries - a common way CAs deliver "your certificate + the chain" as a
	 * single file. Regression-relevant: a naive greedy regex (.*  instead of .*?) would match
	 * from the FIRST "BEGIN" to the LAST "END", merging both certificates into one bogus entry.
	 */
	public function testNormalizeCertPemMultipleConcatenated()
	{
		$smime = new Smime();
		$leaf = $smime->generate_certificate(self::DN, null, null, 365);
		$intermediate = $smime->generate_certificate(
			array('commonName' => 'Intermediate CA', 'emailAddress' => 'ca@example.org'), null, null, 365);

		$certs = Smime::normalize_cert_pem($leaf['cert'].$intermediate['cert']);

		$this->assertCount(2, $certs, 'must split into two separate certificates, not merge them');
		$this->assertEquals(self::DN['commonName'], openssl_x509_parse($certs[0])['subject']['CN'] ?? null);
		$this->assertEquals('Intermediate CA', openssl_x509_parse($certs[1])['subject']['CN'] ?? null);
	}

	/**
	 * normalize_cert_pem() must convert a raw/binary DER-encoded certificate (eg. a Windows-style
	 * .cer file, as a CA is likely to hand back after signing a CSR) to PEM - regression test for
	 * a real bug where uploading such a file was rejected as "does not match the stored private
	 * key" because the raw DER bytes were passed straight to openssl_pkcs12_export(), which
	 * requires PEM.
	 */
	public function testNormalizeCertPemDerSingleCertificate()
	{
		$smime = new Smime();
		$generated = $smime->generate_certificate(self::DN, null, null, 365);
		$der = $this->pemToDer($generated['cert']);

		$certs = Smime::normalize_cert_pem($der);

		$this->assertCount(1, $certs);
		$this->assertStringContainsString('BEGIN CERTIFICATE', $certs[0]);
		$this->assertEquals(self::DN['commonName'], openssl_x509_parse($certs[0])['subject']['CN'] ?? null);
	}

	/**
	 * normalize_cert_pem() must return an empty array (not throw/warn into a fatal) for data that
	 * is neither a certificate nor a PKCS#7 bundle in any encoding.
	 */
	public function testNormalizeCertPemInvalidDataReturnsEmpty()
	{
		$this->assertSame(array(), @Smime::normalize_cert_pem('this is not a certificate'));
	}

	/**
	 * normalize_cert_pem() must extract every certificate from a PEM-armored PKCS#7 (.p7b)
	 * bundle - the other common way CAs deliver "your certificate + the chain" as one file.
	 * Uses the openssl CLI (via crl2pkcs7) only to build the test fixture, since ext-openssl has
	 * no PKCS#7-bundle-creation function - skips gracefully if the CLI isn't available.
	 */
	public function testNormalizeCertPemPkcs7Bundle()
	{
		exec('openssl version 2>&1', $out, $rc);
		if ($rc !== 0)
		{
			$this->markTestSkipped('openssl CLI not available to build the PKCS#7 test fixture');
		}

		$smime = new Smime();
		$leaf = $smime->generate_certificate(self::DN, null, null, 365);
		$intermediate = $smime->generate_certificate(
			array('commonName' => 'Intermediate CA', 'emailAddress' => 'ca@example.org'), null, null, 365);

		$chain_file = tempnam(sys_get_temp_dir(), 'smime_test_');
		$p7b_file = tempnam(sys_get_temp_dir(), 'smime_test_');
		try
		{
			file_put_contents($chain_file, $leaf['cert'].$intermediate['cert']);
			exec('openssl crl2pkcs7 -nocrl -certfile '.escapeshellarg($chain_file).
				' -out '.escapeshellarg($p7b_file).' 2>&1', $out, $rc);
			$this->assertSame(0, $rc, 'test setup: building the PKCS#7 fixture must succeed: '.implode("\n", $out));

			$certs = Smime::normalize_cert_pem(file_get_contents($p7b_file));

			$this->assertCount(2, $certs);
			$cns = array_map(fn($c) => openssl_x509_parse($c)['subject']['CN'] ?? null, $certs);
			$this->assertContains(self::DN['commonName'], $cns);
			$this->assertContains('Intermediate CA', $cns);
		}
		finally
		{
			@unlink($chain_file);
			@unlink($p7b_file);
		}
	}

	/**
	 * isPassphraseProtected() must recognise a p12 exported WITHOUT a passphrase as not
	 * protected - backs admin_mail::edit()'s "smime_needs_passphrase" flag, which decides
	 * whether to show the passphrase-hint placeholder at all.
	 */
	public function testIsPassphraseProtectedFalseForUnprotectedKey()
	{
		$smime = new Smime();
		$generated = $smime->generate_certificate(self::DN, null, null, 365);
		$p12 = Smime::build_pkcs12($generated['privkey'], $generated['cert'], '', '');

		$this->assertFalse(Smime::isPassphraseProtected($p12));
	}

	/**
	 * isPassphraseProtected() must recognise a p12 exported WITH a passphrase as protected -
	 * this is the case that makes admin_mail::edit() show the placeholder hint, and makes
	 * smimeExportFile()/import_smime_cert() reject an empty submitted passphrase up front
	 * with a "please enter the passphrase" error rather than a generic decrypt failure.
	 */
	public function testIsPassphraseProtectedTrueForProtectedKey()
	{
		$smime = new Smime();
		$generated = $smime->generate_certificate(self::DN, null, null, 365);
		$p12 = Smime::build_pkcs12($generated['privkey'], $generated['cert'], '', 'p12pass');

		$this->assertTrue(Smime::isPassphraseProtected($p12));
	}

	/**
	 * isPassphraseProtected() must not throw/warn-crash on garbage input (eg. a corrupted
	 * credential) - it deliberately uses @openssl_pkcs12_read(). openssl can't distinguish
	 * "not a valid p12 at all" from "valid p12, wrong/missing password", so unparseable data
	 * is conservatively reported as protected (true) - harmless, since callers only use this
	 * to decide whether to show a passphrase-hint placeholder, never to validate the blob.
	 */
	public function testIsPassphraseProtectedTrueForGarbage()
	{
		$this->assertTrue(Smime::isPassphraseProtected('not a valid pkcs12 blob'));
	}

	/**
	 * Regression test for the "certificate renewed via CSR can no longer decrypt old mail" bug:
	 * openssl_pkcs7_decrypt() selects the RecipientInfo by the given certificate's issuer+serial,
	 * not merely by whether the private key fits - so decrypt() with the CURRENT (renewed)
	 * certificate must fail for a message encrypted under the PREVIOUS certificate, even though
	 * both certificates share the same key pair.
	 */
	public function testDecryptFailsWithRenewedCertificateAlone()
	{
		$smime = new Smime();
		$old = $smime->generate_certificate(self::DN, null, null, 365);
		$renewed = $this->renewCertificate($old['privkey']);

		$out = tempnam(sys_get_temp_dir(), 'smime_test_');
		openssl_pkcs7_encrypt($this->tempFile("secret\n"), $out, $old['cert'], []);
		$encrypted = file_get_contents($out);
		@unlink($out);

		$this->expectException(\Horde_Crypt_Exception::class);
		$smime->decrypt($encrypted, [
			'type' => 'message', 'pubkey' => $renewed, 'privkey' => $old['privkey'], 'passphrase' => '',
		]);
	}

	/**
	 * decryptWithCandidates() must fall through to a later candidate certificate when an earlier
	 * one doesn't match the message's RecipientInfo - this is the actual fix: try the renewed
	 * certificate first, then fall back to the retained previous one(s).
	 */
	public function testDecryptWithCandidatesFallsBackToMatchingCertificate()
	{
		$smime = new Smime();
		$old = $smime->generate_certificate(self::DN, null, null, 365);
		$renewed = $this->renewCertificate($old['privkey']);

		$in = $this->tempFile("secret payload\n");
		$out = tempnam(sys_get_temp_dir(), 'smime_test_');
		openssl_pkcs7_encrypt($in, $out, $old['cert'], []);
		$encrypted = file_get_contents($out);
		@unlink($out);
		@unlink($in);

		$result = Smime::decryptWithCandidates($smime, $encrypted,
			[$renewed, $old['cert']], $old['privkey'], '');

		$this->assertStringContainsString('secret payload', $result);
	}

	/**
	 * decryptWithCandidates() must throw (not silently return something useless) when none of the
	 * given candidate certificates match the message.
	 */
	public function testDecryptWithCandidatesThrowsWhenNoneMatch()
	{
		$smime = new Smime();
		$old = $smime->generate_certificate(self::DN, null, null, 365);
		$unrelated = $smime->generate_certificate(
			array('commonName' => 'Unrelated', 'emailAddress' => 'unrelated@example.org'), null, null, 365);

		$in = $this->tempFile("secret payload\n");
		$out = tempnam(sys_get_temp_dir(), 'smime_test_');
		openssl_pkcs7_encrypt($in, $out, $old['cert'], []);
		$encrypted = file_get_contents($out);
		@unlink($out);
		@unlink($in);

		$this->expectException(\Horde_Crypt_Exception::class);
		Smime::decryptWithCandidates($smime, $encrypted, [$unrelated['cert']], $unrelated['privkey'], '');
	}

	/**
	 * isOwnCertificate() must recognise a retired own certificate (same key pair, different
	 * certificate) as belonging to the given private key, so mail_compose::_encrypt() can filter
	 * it out of the chain sent with outgoing signed mail.
	 */
	public function testIsOwnCertificateTrueForRetiredOwnCertificate()
	{
		$smime = new Smime();
		$old = $smime->generate_certificate(self::DN, null, null, 365);
		$renewed = $this->renewCertificate($old['privkey'], 42);

		$this->assertTrue(Smime::isOwnCertificate($old['cert'], $old['privkey']));
		$this->assertTrue(Smime::isOwnCertificate($renewed, $old['privkey']));
	}

	/**
	 * isOwnCertificate() must recognise a genuine intermediate/CA certificate (belonging to a
	 * DIFFERENT key pair, eg. the CA's) as NOT belonging to our own private key, so it's kept in
	 * the chain sent with outgoing signed mail.
	 */
	public function testIsOwnCertificateFalseForUnrelatedCertificate()
	{
		$smime = new Smime();
		$own = $smime->generate_certificate(self::DN, null, null, 365);
		$intermediate = $smime->generate_certificate(
			array('commonName' => 'Intermediate CA', 'emailAddress' => 'ca@example.org'), null, null, 365);

		$this->assertFalse(Smime::isOwnCertificate($intermediate['cert'], $own['privkey']));
	}

	/**
	 * Writes $content to a fresh temp file and returns its path (caller must clean up).
	 */
	private function tempFile(string $content) : string
	{
		$path = tempnam(sys_get_temp_dir(), 'smime_test_');
		file_put_contents($path, $content);
		return $path;
	}

	/**
	 * Builds a new certificate for an EXISTING private key, mirroring what a CA does when it
	 * (re-)signs a CSR generated via Smime::generate_csr() for an already stored key - same key
	 * pair as $privkey, but a genuinely different certificate (own DN/issuer, but a distinct
	 * serial number, as a real CA would assign - openssl_csr_sign()'s default serial is always 0,
	 * which would make two self-signed certs with the same subject indistinguishable to
	 * openssl_pkcs7_decrypt()'s issuer+serial matching and defeat the point of this test fixture).
	 */
	private function renewCertificate(string $privkey, int $serial=1) : string
	{
		$key = openssl_pkey_get_private($privkey);
		$csr = openssl_csr_new(self::DN, $key, array('digest_alg' => 'sha256'));
		$x509 = openssl_csr_sign($csr, null, $key, 365, array('digest_alg' => 'sha256'), $serial);
		openssl_x509_export($x509, $cert);
		return $cert;
	}
}
