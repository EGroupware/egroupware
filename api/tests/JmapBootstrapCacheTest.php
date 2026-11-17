<?php
/**
 * EGroupware Api: Jmap::__construct() must cache bootstrap()'s discovery result across requests
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;

/**
 * Ticket #127741 (rbz-schuetzenpark.de, Dovecot->Stalwart migration): PDF attachments opened with
 * "considerable delay" - root cause: Jmap::__construct() called bootstrap() unconditionally, which
 * does a full authenticated HTTP round-trip to /.well-known/jmap EVERY time a Jmap/JmapHttp object
 * is constructed, even though EGroupware is stateless per PHP request and this happens fresh on
 * every single request touching a real Stalwart account (eg. every attachment download pays this
 * PLUS the actual download, doubling its round trips). Fixed by caching the discovery result
 * one level up, in bootstrapCached() (used by __construct() instead of calling bootstrap()
 * directly) - bootstrap() itself stays untouched, see JmapBootstrapSharedAccountsTest for why.
 */
class JmapBootstrapCacheTest extends TestCase
{
	private array $keysToClear = [];

	protected function tearDown() : void
	{
		foreach ($this->keysToClear as $key)
		{
			Cache::unsetInstance(Jmap::class, $key);
		}
		parent::tearDown();
	}

	private function wellKnownResponse(string $host) : array
	{
		return [
			'accounts' => [
				'ca' => ['name' => 'ralf@example.invalid', 'isPersonal' => true,
					'accountCapabilities' => ['urn:ietf:params:jmap:mail' => []]],
			],
			'capabilities' => ['urn:ietf:params:jmap:core' => []],
			'apiUrl' => "https://$host/jmap/",
			'downloadUrl' => "https://$host/jmap/download/",
			'uploadUrl' => "https://$host/jmap/upload/",
			'eventSourceUrl' => "https://$host/jmap/eventsource/",
		];
	}

	/**
	 * Real constructor (so bootstrapCached() runs), only api() mocked - no network.
	 *
	 * api()'s stub must be configured BEFORE the constructor runs (bootstrap() calls it from
	 * inside __construct()), so the constructor is disabled on getMock() and run manually via
	 * Reflection afterwards, once the stub is in place - a plain setConstructorArgs() would invoke
	 * the real constructor too early, while api() still returns PHPUnit's unconfigured default
	 * (null), which fails type-checks deeper in bootstrap().
	 */
	private function constructJmap(string $host, string $user, string $secret, ?array $apiResponse, int $expectedApiCalls) : Jmap
	{
		$mock = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods(['api'])
			->getMock();
		$mock->expects($this->exactly($expectedApiCalls))->method('api')->willReturn($apiResponse);

		(new \ReflectionClass(Jmap::class))->getConstructor()
			->invoke($mock, "https://$host", $user, $secret, ['urn:ietf:params:jmap:core']);

		return $mock;
	}

	public function testSecondConstructionWithSameCredentialsSkipsDiscoveryCall()
	{
		$host = 'cache-test-1.example.invalid';
		$this->keysToClear[] = md5("https://$host".'|ralf|secret1');

		// first construction: cache miss, api() called once
		$jmap1 = $this->constructJmap($host, 'ralf', 'secret1', $this->wellKnownResponse($host), 1);
		$this->assertSame('ca', $jmap1->accountId);
		$this->assertArrayHasKey('urn:ietf:params:jmap:mail', $jmap1->accountCapabilities);

		// second construction, identical url+user+secret: cache hit, api() must NOT be called
		$jmap2 = $this->constructJmap($host, 'ralf', 'secret1', null, 0);
		$this->assertSame('ca', $jmap2->accountId);
		$this->assertArrayHasKey('urn:ietf:params:jmap:mail', $jmap2->accountCapabilities);
		$this->assertSame($jmap1->downloadUrl, $jmap2->downloadUrl);
	}

	public function testDifferentSecretAgainstSameHostTriggersFreshFetch()
	{
		$host = 'cache-test-2.example.invalid';
		$this->keysToClear[] = md5("https://$host".'|ralf|secret1');
		$this->keysToClear[] = md5("https://$host".'|ralf|secret2');

		$jmap1 = $this->constructJmap($host, 'ralf', 'secret1', $this->wellKnownResponse($host), 1);
		$this->assertSame('ca', $jmap1->accountId);

		// different secret, same host+user: must NOT reuse the first secret's cache entry
		$jmap2 = $this->constructJmap($host, 'ralf', 'secret2', $this->wellKnownResponse($host), 1);
		$this->assertSame('ca', $jmap2->accountId);
	}

	public function testDifferentUsernameWithEmptyUserApiKeyDoesNotLeakAcrossAdmins()
	{
		$host = 'cache-test-3.example.invalid';
		// two different tenant-admin API keys, both with an empty username (Bearer-token path) -
		// must NOT share a cache entry just because the username ('') is identical
		$this->keysToClear[] = md5("https://$host".'||API_adminkey1');
		$this->keysToClear[] = md5("https://$host".'||API_adminkey2');

		// both mocks require exactly 1 api() call each - if the cache key collided (eg. keyed on
		// url+user alone), the second construction would hit the first's cache entry and its
		// mock's api() would never fire, failing this test's mock expectation on tearDown
		$jmap1 = $this->constructJmap($host, '', 'API_adminkey1', $this->wellKnownResponse($host), 1);
		$this->assertSame('ca', $jmap1->accountId);

		$jmap2 = $this->constructJmap($host, '', 'API_adminkey2', $this->wellKnownResponse($host), 1);
		$this->assertSame('ca', $jmap2->accountId);
	}
}
