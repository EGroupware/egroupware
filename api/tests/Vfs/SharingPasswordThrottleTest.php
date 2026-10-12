<?php

/**
 * Regression test for Sharing::checkPasswordThrottled()'s per-token failed-password lockout.
 *
 * Before this fix, Sharing::check_password() had no attempt tracking at all - an attacker with a
 * valid share token (the token itself is 24 CSPRNG bytes, impractical to guess, but the share
 * PASSWORD is often much weaker) could guess the password at unlimited speed. The throttle now
 * applies a growing delay per wrong guess and a hard temporary lockout (reject WITHOUT even
 * checking the password) once too many wrong guesses happen for the SAME share token.
 *
 * Real HTTP, same reasoning as SharingPasswordExpiryTest: the password arrives as PHP_AUTH_PW from
 * a Basic auth header, there is no in-process path that exercises the same thing.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/SharingBase.php';

use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;

class SharingPasswordThrottleTest extends SharingBase
{
	const SHARE_PASSWORD = 'correct-horse-battery-staple';

	/**
	 * Write a file, share it password-protected, and log out so the request that follows is made
	 * by a stranger - same pattern as SharingPasswordExpiryTest::shareFileWith().
	 */
	protected function shareFileWith(array $extra, string $content) : string
	{
		$file = Vfs::get_home_dir() . '/share_throttle_test.txt';
		$this->assertNotFalse(
			file_put_contents(Vfs::PREFIX . $file, $content),
			'Unable to write test file "' . Vfs::PREFIX . $file . '" - check file permissions for CLI user'
		);
		$this->files[] = $file;

		$this->getShareExtra($file, Sharing::READONLY, $extra);
		$share = $this->createShare($file, Sharing::READONLY, $extra);
		$link = Vfs\Sharing::share2link($share);

		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();

		LoggedInTest::tearDownAfterClass();

		return $link;
	}

	protected function fetchShare(string $link, ?string $password = null) : array
	{
		$curl = curl_init($link);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
		if($password !== null)
		{
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($curl, CURLOPT_USERPWD, 'anonymous:' . $password);
		}
		$body = curl_exec($curl);
		$code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$errno = curl_errno($curl);
		$error = curl_error($curl);
		curl_close($curl);

		if($code === 0)
		{
			$this->noWebserverResponse("No webserver response for share link '$link' (curl errno $errno: $error)");
		}

		return ['code' => $code, 'body' => (string)$body];
	}

	/**
	 * A couple of mistyped passwords must not lock a legitimate user out of their own share - the
	 * throttle only needs to stop UNLIMITED guessing, not a normal typo or two.
	 */
	public function testCorrectPasswordAfterFewWrongGuessesStillWorks()
	{
		$content = 'Reachable after a couple of wrong guesses, not yet at the lockout threshold.';
		$link = $this->shareFileWith(
			['share_passwd' => password_hash(self::SHARE_PASSWORD, PASSWORD_DEFAULT)],
			$content
		);

		$this->fetchShare($link, 'wrong-1');
		$this->fetchShare($link, 'wrong-2');
		$response = $this->fetchShare($link, self::SHARE_PASSWORD);

		$this->assertEquals(200, $response['code'],
			"Correct password after 2 wrong guesses got HTTP {$response['code']}, the throttle must not lock out a normal typo or two");
		$this->assertStringContainsString($content, $response['body']);
	}

	/**
	 * The actual fix: enough wrong guesses in a row locks the share token out even for the
	 * CORRECT password - proving a real lockout happened, not just "still rejects wrong guesses"
	 * (which was already true before this fix and proves nothing new).
	 */
	public function testTooManyWrongGuessesLocksOutEvenTheCorrectPassword()
	{
		$content = 'Must become unreachable, even with the right password, after too many wrong guesses.';
		$link = $this->shareFileWith(
			['share_passwd' => password_hash(self::SHARE_PASSWORD, PASSWORD_DEFAULT)],
			$content
		);

		for ($i = 0; $i < Sharing::MAX_FAILED_PASSWORDS; $i++)
		{
			$response = $this->fetchShare($link, 'wrong-guess-'.$i);
			$this->assertEquals(401, $response['code'], "Wrong guess #$i got HTTP {$response['code']}");
		}

		// now locked out - even the CORRECT password must be rejected
		$response = $this->fetchShare($link, self::SHARE_PASSWORD);

		$this->assertEquals(401, $response['code'],
			"The correct password was accepted (HTTP {$response['code']}) right after ".Sharing::MAX_FAILED_PASSWORDS.
			" wrong guesses - the share token must be locked out for a while instead");
		$this->assertStringNotContainsString($content, $response['body']);
	}
}
