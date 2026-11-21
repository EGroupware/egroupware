<?php
/**
 * EGroupware API: tests for Header\Authenticate::hasWebServerIdentity()
 *
 * remote.php, webdav.php, files/webdav.php and xerox-scan.php apply their ?auth= query parameter
 * fallback only when the request carries no web-server-authenticated identity already.
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Header;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuthenticateHasWebServerIdentityTest extends TestCase
{
	public static function providerIdentity() : array
	{
		return [
			'nothing' => [false, []],
			'PHP_AUTH_USER' => [true, ['PHP_AUTH_USER' => 'alice']],
			'REMOTE_USER' => [true, ['REMOTE_USER' => 'alice']],
			'REDIRECT_REMOTE_USER' => [true, ['REDIRECT_REMOTE_USER' => 'alice']],
			'PHP_AUTH_DIGEST' => [true, ['PHP_AUTH_DIGEST' => 'username="alice", realm="x"']],
			'SSL_CLIENT_S_DN' => [true, ['SSL_CLIENT_S_DN' => '/CN=alice/O=Example']],
			'empty PHP_AUTH_USER' => [false, ['PHP_AUTH_USER' => '']],
			'empty REMOTE_USER' => [false, ['REMOTE_USER' => '']],
			'empty REDIRECT_REMOTE_USER' => [false, ['REDIRECT_REMOTE_USER' => '']],
			'empty PHP_AUTH_DIGEST' => [false, ['PHP_AUTH_DIGEST' => '']],
			'empty SSL_CLIENT_S_DN' => [false, ['SSL_CLIENT_S_DN' => '']],
			'unrelated server vars' => [false, ['HTTP_USER_AGENT' => 'curl/8']],
		];
	}

	/**
	 * Pass criteria: only PHP_AUTH_USER, REMOTE_USER, REDIRECT_REMOTE_USER, PHP_AUTH_DIGEST or
	 * SSL_CLIENT_S_DN count, and only non-empty
	 */
	#[DataProvider('providerIdentity')]
	public function testHasWebServerIdentity(bool $expected, array $server)
	{
		$this->assertSame($expected, Authenticate::hasWebServerIdentity($server));
	}

	/**
	 * Pass criteria: defaults to the current request ($_SERVER)
	 */
	public function testDefaultsToRequest()
	{
		$backup = $_SERVER;
		try
		{
			unset($_SERVER['PHP_AUTH_USER'], $_SERVER['REMOTE_USER'], $_SERVER['PHP_AUTH_DIGEST']);
			$this->assertFalse(Authenticate::hasWebServerIdentity());
			$_SERVER['PHP_AUTH_USER'] = 'alice';
			$this->assertTrue(Authenticate::hasWebServerIdentity());
		}
		finally
		{
			$_SERVER = $backup;
		}
	}
}
