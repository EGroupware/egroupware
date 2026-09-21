<?php

/**
 * Tests for the two share options that decide whether a recipient gets in at all:
 * a password, and an expiry date.
 *
 * Both are checked in Sharing::check_token() before anything is served, and both fail
 * closed - a wrong or missing password answers 401 with a Basic realm, an expired share
 * is simply not found by the token query and answers 404.  Neither had any test, so a
 * regression in either would have shipped silently, and the first report would have come
 * from someone's client being unable to open a link they were sent.
 *
 * These go over real HTTP, because the password arrives as PHP_AUTH_PW from a Basic auth
 * header - there is no in-process path that exercises the same thing.
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/SharingBase.php';

use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;

class SharingPasswordExpiryTest extends SharingBase
{
	const SHARE_PASSWORD = 'correct-horse-battery-staple';

	/**
	 * Write a file, share it with the given extra share columns, and log out so the
	 * request that follows is made by a stranger.
	 *
	 * @param array $extra extra egw_sharing columns, eg. share_passwd / share_expires
	 * @param string $content file content, so a successful fetch can be told from an error page
	 * @return string share link
	 */
	protected function shareFileWith(array $extra, string $content) : string
	{
		$file = Vfs::get_home_dir() . '/share_option_test.txt';
		$this->assertNotFalse(
			file_put_contents(Vfs::PREFIX . $file, $content),
			'Unable to write test file "' . Vfs::PREFIX . $file . '" - check file permissions for CLI user'
		);
		$this->files[] = $file;

		$this->getShareExtra($file, Sharing::READONLY, $extra);
		$share = $this->createShare($file, Sharing::READONLY, $extra);
		$link = Vfs\Sharing::share2link($share);

		// Re-init, since they look at user, fstab, etc.
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();

		// Log out & clear cache - from here on we are just someone with a link
		LoggedInTest::tearDownAfterClass();

		return $link;
	}

	/**
	 * Fetch a share link the way a recipient's browser would, optionally answering the
	 * Basic auth challenge.
	 *
	 * @param string $link
	 * @param string|null $password null: send no credentials at all
	 * @return array ['code' => int, 'body' => string]
	 */
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
	 * A password-protected share must not hand the file to someone who sends no password
	 */
	public function testPasswordShareRefusesWithoutPassword()
	{
		$content = 'This file is behind a share password and must not be served without it.';
		$link = $this->shareFileWith(
			['share_passwd' => password_hash(self::SHARE_PASSWORD, PASSWORD_DEFAULT)],
			$content
		);

		$response = $this->fetchShare($link);

		$this->assertEquals(401, $response['code'], "Password-protected share answered HTTP {$response['code']} to a request with no password");
		$this->assertStringNotContainsString($content, $response['body'], 'Password-protected share served the file content without a password');
	}

	/**
	 * ... nor to someone who guesses wrong
	 */
	public function testPasswordShareRefusesWrongPassword()
	{
		$content = 'This file is behind a share password and must not be served to a wrong one.';
		$link = $this->shareFileWith(
			['share_passwd' => password_hash(self::SHARE_PASSWORD, PASSWORD_DEFAULT)],
			$content
		);

		$response = $this->fetchShare($link, 'not-the-password');

		$this->assertEquals(401, $response['code'], "Password-protected share answered HTTP {$response['code']} to a wrong password");
		$this->assertStringNotContainsString($content, $response['body'], 'Password-protected share served the file content to a wrong password');
	}

	/**
	 * ... and must hand it over to someone with the right one, which is the half that
	 * breaks quietly: a share nobody can open looks exactly like a share nobody tried.
	 */
	public function testPasswordShareServesWithCorrectPassword()
	{
		$content = 'This file is behind a share password and must be served with it.';
		$link = $this->shareFileWith(
			['share_passwd' => password_hash(self::SHARE_PASSWORD, PASSWORD_DEFAULT)],
			$content
		);

		$response = $this->fetchShare($link, self::SHARE_PASSWORD);

		$this->assertEquals(200, $response['code'], "Share did not open with the correct password, got HTTP {$response['code']}");
		$this->assertStringContainsString($content, $response['body'], 'Share opened with the correct password but did not contain the file');
	}

	/**
	 * A share whose expiry date has passed must be gone.  check_token()'s token query
	 * filters on share_expires, so this answers 404 rather than 401.
	 */
	public function testExpiredShareIsRefused()
	{
		$content = 'This share expired yesterday and must not be served.';
		// share_expires is a date column, so "yesterday" is unambiguously in the past
		$link = $this->shareFileWith(['share_expires' => time() - 86400], $content);

		$response = $this->fetchShare($link);

		$this->assertEquals(404, $response['code'], "Expired share answered HTTP {$response['code']} instead of 404");
		$this->assertStringNotContainsString($content, $response['body'], 'Expired share still served the file content');
	}

	/**
	 * The other direction, which is the one that embarrasses you in front of a client:
	 * an expiry date that has NOT passed must not lock them out early.
	 */
	public function testUnexpiredShareIsServed()
	{
		$content = 'This share expires next week and must still be served today.';
		$link = $this->shareFileWith(['share_expires' => time() + 7 * 86400], $content);

		$response = $this->fetchShare($link);

		$this->assertEquals(200, $response['code'], "Share expiring next week answered HTTP {$response['code']}");
		$this->assertStringContainsString($content, $response['body'], 'Share expiring next week did not serve the file');
	}
}
