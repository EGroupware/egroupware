<?php
/**
 * EGroupware Api: Mail account credentials tests
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb-AT-stylite.de>
 * @copyright (c) 2016 by Ralf Becker <rb-AT-stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Mail;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use EGroupware\Api\Mail\Credentials;

/**
 * Mail account credentials tests
 *
 * Only testing en&decryption of mail passwords so far.
 * Further tests would need database.
 */
class CredentialsTest extends TestCase
{
	/**
	 * Test new 16.1 AES password encryption with OpenSSL
	 */
	public function testAes()
	{
		$mail_password = 'RälfÜber12345678sdddfd';
		$account_id = $GLOBALS['egw_info']['user']['account_id'] = 1;
		$key = 'HMqUHxzMBjjvXppV';
		$this->setSessionPassword($key);

		// test encryption with fixed salt
		$pw_encrypted = 'IaaBeu6LiIa+iFBnHYroXA==4lp30Z4B20OdUYnFrxM3lo4b+bsf5wQITdyM1eMP6PM=';
		$pw_enc = Credentials::USER_AES;
		$this->assertEquals($pw_encrypted, self::callProtectedMethod('encrypt_openssl_aes', __NAMESPACE__.'\\Credentials',
			array($mail_password, $account_id, &$pw_enc, $key, base64_decode(substr($pw_encrypted, 0, Credentials::SALT_LEN64)))),
			'AES encrypt with fixed salt');

		// test encryption&descryption with random salt
		$pw_enc = Credentials::USER_AES;
		$pw_encrypted_rs = self::callProtectedMethod('encrypt_openssl_aes', __NAMESPACE__.'\\Credentials',
			array($mail_password, $account_id, &$pw_enc, $key));
		$row = array(
			'account_id' => $account_id,
			'cred_password' => $pw_encrypted_rs,
			'cred_pw_enc' => $pw_enc,
		);
		$this->assertEquals($mail_password, self::callProtectedMethod('decrypt', __NAMESPACE__.'\\Credentials',
			array($row, $key)), 'AES decrypt with random salt');
	}

	/**
	 * Test old 14.x tripledes password encryption with mcrypt (if available) and openssl
	 */
	public function testTripledes()
	{
		$mail_password = 'RälfÜber12345678sdddfd';
		$account_id = $GLOBALS['egw_info']['user']['account_id'] = 1;
		$this->setSessionPassword('HMqUHxzMBjjvXppV');
		$pw_encrypted = 'Y7QwLIqS6MP61hS8/e4i0wCdtpQP6kZ2';

		// if mycrypt is available check encrypting too
		if (check_load_extension('mcrypt'))
		{
			$pw_enc = null;
			$this->assertEquals($pw_encrypted, self::callProtectedMethod('encrypt_mcrypt_3des', __NAMESPACE__.'\\Credentials',
				array($mail_password, $account_id, &$pw_enc)), 'tripledes encryption with mcrypt');
		}
		else
		{
			$pw_enc = Credentials::USER;
		}
		// otherwise only check decrypting with openssl
		$row = array(
			'account_id' => $account_id,
			'cred_password' => $pw_encrypted,
			'cred_pw_enc' => $pw_enc,
		);
		try
		{
			$password = self::callProtectedMethod('decrypt', __NAMESPACE__ . '\\Credentials', array($row));
		}
		catch (\EGroupware\Api\Exception\WrongParameter|\EGroupware\Api\Exception\AssertionFailed $e)
		{
			if(!check_load_extension('mcrypt'))
			{
				$this->markTestSkipped('tripledes decryption fallback needs mcrypt when OpenSSL 3DES is unavailable');
			}
			throw $e;
		}
		$this->assertEquals($mail_password, $password, 'tripledes decryption with openssl');

		if (check_load_extension('mcrypt'))
		{
			$this->assertEquals($mail_password, self::callProtectedMethod('decrypt_mcrypt_3des', __NAMESPACE__.'\\Credentials',
				array($row)), 'tripledes decryption with mcrypt');
		}
	}

	/**
	 * Regression test for a real bug found while building the S/MIME feature: decrypt_openssl_aes()
	 * does trim($decrypted, "\0"), which silently corrupts binary data that genuinely starts or
	 * ends with a null byte (common in DER-encoded certs, eg. an RSA modulus needing a leading \0
	 * sign-padding byte). This demonstrates the underlying bug in isolation, without the SMIME-only
	 * 'x'-wrap workaround (see testSmimeXWrapProtectsBoundaryNullBytes) - proves WHY that workaround
	 * is needed, and guards against someone "simplifying" decrypt_openssl_aes() by dropping trim()
	 * without also removing the (then unnecessary) 'x'-wrap in Credentials::write()/read().
	 */
	public function testAesRoundTripCorruptsBoundaryNullBytes()
	{
		$key = 'HMqUHxzMBjjvXppV';
		$pw_enc = null;

		foreach ([
			'leading null' => "\x00".str_repeat('A', 99),
			'trailing null' => str_repeat('A', 99)."\x00",
		] as $label => $plain)
		{
			$encrypted = self::callProtectedMethod('encrypt_openssl_aes', __NAMESPACE__.'\\Credentials',
				array($plain, 0, &$pw_enc, $key, null, true));
			$row = array('account_id' => 0, 'cred_password' => $encrypted, 'cred_pw_enc' => $pw_enc);
			$decrypted = self::callProtectedMethod('decrypt_openssl_aes', __NAMESPACE__.'\\Credentials', array($row, $key));

			$this->assertNotEquals($plain, $decrypted,
				"demonstrates the bug: decrypt_openssl_aes() must NOT round-trip boundary null bytes ($label) - ".
				'if this assertion starts failing, trim() was removed/fixed upstream and the SMIME x-wrap workaround can go too');
		}
	}

	/**
	 * The actual fix: Credentials::write()/read()'s SMIME-only 'x' wrap (on BOTH ends, not just the
	 * end as it originally was) must survive the same boundary-null-byte corruption demonstrated in
	 * testAesRoundTripCorruptsBoundaryNullBytes(). Replicates write()/read()'s wrap/strip inline
	 * (both are on the private, DB-writing code path) so this stays a pure, no-DB test.
	 */
	public function testSmimeXWrapProtectsBoundaryNullBytes()
	{
		$key = 'HMqUHxzMBjjvXppV';
		$pw_enc = null;

		foreach ([
			'leading null' => "\x00".str_repeat('A', 99),
			'trailing null' => str_repeat('A', 99)."\x00",
			'leading+trailing null' => "\x00".str_repeat('A', 98)."\x00",
		] as $label => $plain)
		{
			// mirrors Credentials::write()'s "if (type==SMIME) password = 'x'.password.'x'"
			$wrapped = 'x'.$plain.'x';
			$encrypted = self::callProtectedMethod('encrypt_openssl_aes', __NAMESPACE__.'\\Credentials',
				array($wrapped, 0, &$pw_enc, $key, null, true));
			$row = array('account_id' => 0, 'cred_password' => $encrypted, 'cred_pw_enc' => $pw_enc);
			$decrypted = self::callProtectedMethod('decrypt_openssl_aes', __NAMESPACE__.'\\Credentials', array($row, $key));

			// mirrors Credentials::read()'s matching strip of both markers
			if (substr($decrypted, 0, 1) === 'x') $decrypted = substr($decrypted, 1);
			if (substr($decrypted, -1) === 'x') $decrypted = substr($decrypted, 0, -1);

			$this->assertEquals($plain, $decrypted, "x-wrap must fully protect boundary null bytes ($label)");
		}
	}

	protected static function callProtectedMethod($name, $classname, $params)
	{
		$class = new ReflectionClass($classname);
		$method = $class->getMethod($name);
		$method->setAccessible(true);
		$obj = new $classname();
		return $method->invokeArgs($obj, $params);
	}

	protected function setSessionPassword(string $password): void
	{
		$GLOBALS['egw'] ??= new \stdClass();
		$GLOBALS['egw']->session ??= new \stdClass();
		$GLOBALS['egw']->session->passwd = $password;
	}
}
