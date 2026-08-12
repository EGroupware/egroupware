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
}
