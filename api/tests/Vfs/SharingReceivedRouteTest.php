<?php

/**
 * Tests for the "share received" route a logged-in recipient is sent to
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/SharingBase.php';

use EGroupware\Api\Vfs;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Cookie\CookieJar;

/**
 * filemanager's share_received route must apply the same share password/expiry check
 * share.php itself already runs before ever redirecting a logged-in recipient here.
 *
 * Real HTTP, like SharingPasswordThrottleTest/LogoutRedirectXssTest: the route is reached as a
 * second, logged-in user's own session via the normal menuaction dispatch, which has no
 * in-process equivalent worth building just for this.
 */
class SharingReceivedRouteTest extends SharingBase
{
	const SHARE_PASSWORD = 'correct-horse-battery-staple';

	/**
	 * Share a filesystem-backed directory (real files, reachable from the webserver the HTTP
	 * fetch below talks to - the default VFS backend is not) with the given extra share columns,
	 * and return its token
	 *
	 * @param array $extra eg. share_passwd / share_expires
	 * @return string share token
	 */
	protected function shareFileWith(array $extra) : string
	{
		// don't add to $this->files - mountFilesystem() tracks it via $this->mounts instead
		$dir = Vfs::get_home_dir() . '/share_received_route_test/';
		$this->mountFilesystem($dir);

		$share_extra = array();
		$this->getShareExtra($dir, Sharing::READONLY, $share_extra);
		$share = $this->createShare($dir, Sharing::READONLY, $share_extra + $extra);

		return $share['share_token'];
	}

	/**
	 * Base URL of the webserver the tests were told to use, same fallback chain as
	 * LogoutRedirectXssTest - EGW_URL wins, since webserver_url is normally a bare path.
	 */
	protected function baseUrl() : string
	{
		$url = getenv('EGW_URL') ?: ($_ENV['EGW_URL'] ?? null) ?: ($GLOBALS['EGW_URL'] ?? null) ?:
			'http://localhost/egroupware';
		return rtrim($url, '/');
	}

	/**
	 * Log in over HTTP as a real client does, and return the client/cookie-jar pair to reuse.
	 *
	 * @return array{0: Client, 1: CookieJar}|null null if login failed (caller markTestSkipped()s)
	 */
	protected function httpLogin() : ?array
	{
		$jar = new CookieJar();
		$client = new Client([
			RequestOptions::HTTP_ERRORS => false,
			RequestOptions::ALLOW_REDIRECTS => true,
			RequestOptions::CONNECT_TIMEOUT => 5,
			RequestOptions::TIMEOUT => 10,
			RequestOptions::COOKIES => $jar,
		]);
		$response = $client->post($this->baseUrl().'/login.php', [
			RequestOptions::FORM_PARAMS => [
				'login' => $GLOBALS['EGW_USER'],
				'passwd' => $GLOBALS['EGW_PASSWORD'],
				'passwd_type' => 'text',
				'submitit' => 'Login',
			],
		]);
		if ($response->getStatusCode() === 0)
		{
			$this->noWebserverResponse('No webserver response for login.php');
		}
		if (!$jar->getCookieByName('sessionid'))
		{
			return null;
		}
		return [$client, $jar];
	}

	/**
	 * Fetch the share_received route as the logged-in recipient, who has never been given the
	 * share's password
	 */
	protected function fetchShareReceived(Client $client, string $token) : \Psr\Http\Message\ResponseInterface
	{
		return $client->get($this->baseUrl().'/index.php', [
			RequestOptions::QUERY => [
				'menuaction' => 'filemanager.EGroupware\\Filemanager\\Sharing\\Ui.share_received',
				'token' => $token,
			],
			RequestOptions::ALLOW_REDIRECTS => false,
		]);
	}

	/**
	 * A password-protected share must not be served through share_received without its password
	 */
	public function testPasswordProtectedShareRefusesWithoutPassword()
	{
		$token = $this->shareFileWith(['share_passwd' => password_hash(self::SHARE_PASSWORD, PASSWORD_DEFAULT)]);

		[$client] = $this->httpLogin() ?? [null];
		if (!$client) $this->markTestSkipped('Could not log in as phpunit test user');

		$response = $this->fetchShareReceived($client, $token);
		$body = (string)$response->getBody();

		$this->assertEquals(401, $response->getStatusCode(),
			"share_received answered HTTP {$response->getStatusCode()} for a password-protected share without its password");
		$this->assertStringNotContainsString('mount_location', $body,
			'share_received filled the dialog of a password-protected share without its password');
	}

	/**
	 * A share whose expiry date has passed must not be served through share_received either
	 */
	public function testExpiredShareRefuses()
	{
		$token = $this->shareFileWith(['share_expires' => time() - 86400]);

		[$client] = $this->httpLogin() ?? [null];
		if (!$client) $this->markTestSkipped('Could not log in as phpunit test user');

		$response = $this->fetchShareReceived($client, $token);

		$this->assertEquals(404, $response->getStatusCode(),
			"share_received answered HTTP {$response->getStatusCode()} for an expired share");
	}

	/**
	 * A share without a password and without an expiry date is still served to a logged-in
	 * recipient - the check must not lock out the ordinary case
	 */
	public function testOpenShareIsStillServed()
	{
		$token = $this->shareFileWith([]);

		[$client] = $this->httpLogin() ?? [null];
		if (!$client) $this->markTestSkipped('Could not log in as phpunit test user');

		$response = $this->fetchShareReceived($client, $token);

		$this->assertEquals(200, $response->getStatusCode(),
			"share_received answered HTTP {$response->getStatusCode()} for a share without password or expiry");
	}
}
