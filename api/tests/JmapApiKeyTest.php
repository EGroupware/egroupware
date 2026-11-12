<?php
/**
 * EGroupware Api: Jmap uses a Stalwart API key as Bearer token, but only if explicitly asked to
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;

/**
 * Hosted domains use the API key of their tenant admin instead of the system admin's password.
 *
 * A key must ONLY become a Bearer token if the caller passes an empty username: code logging in a user with
 * "user*admin" and the admin credentials as password would otherwise authenticate as the owner of the key, and
 * not as that user.
 */
class JmapApiKeyTest extends TestCase
{
	const KEY = 'API_AAABogAAAAKDijFfC2KUJwKf1aeWcsfsSIsTzQ';

	/**
	 * Jmap without the network bootstrap, exposing the Authorization header it would send
	 */
	private function authorization(string $user, string $secret) : array
	{
		$jmap = new class('stalwart.example.org', $user, $secret, ['urn:ietf:params:jmap:core']) extends Jmap
		{
			public function bootstrap(bool $use_well_known=true, ?string &$accountId=null) : string
			{
				$accountId = 'test';
				return $this->url;
			}
			public function headers() : array
			{
				return $this->authorization;
			}
		};
		return array_values($jmap->headers());
	}

	public function testIsApiKey()
	{
		$this->assertTrue(Jmap::isApiKey(self::KEY));
		$this->assertFalse(Jmap::isApiKey('secret'));
		$this->assertFalse(Jmap::isApiKey(''));
		$this->assertFalse(Jmap::isApiKey(null));
		$this->assertFalse(Jmap::isApiKey('xAPI_key'));
	}

	public function testEmptyUsernameUsesKeyAsBearerToken()
	{
		$this->assertSame(['Authorization: Bearer '.self::KEY], $this->authorization('', self::KEY));
	}

	public function testKeyWithUsernameStaysBasicAuth()
	{
		$this->assertSame(['Authorization: Basic '.base64_encode('user%admin:'.self::KEY)],
			$this->authorization('user%admin', self::KEY));
	}

	public function testPasswordWithoutUsernameStaysBasicAuth()
	{
		$this->assertSame(['Authorization: Basic '.base64_encode(':secret')], $this->authorization('', 'secret'));
	}
}
