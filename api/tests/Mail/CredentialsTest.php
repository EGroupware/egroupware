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
