<?php
/**
 * EGroupware Api: Test Mail\Sieve\Jmap finding an existing script, which is not the first one of the account
 *
 * installScript() created a script "EGW18" instead of updating it, if the account had other scripts before it (Stalwart answered
 * alreadyExists and the user could not change the vacation message)
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Jmap as ApiJmap;
use EGroupware\Api\Mail\Jmap\Http;
use EGroupware\Api\Mail\Sieve\Jmap as SieveJmap;
use PHPUnit\Framework\TestCase;

/**
 * Stand-in JMAP client recording the SieveScript/set calls
 */
class SieveJmapScriptLookupHttp extends Http
{
	public array $sets = [];
	public array $scripts = [
		['id' => 'b', 'name' => 'sieve', 'isActive' => false, 'blobId' => 'B1'],
		['id' => 'c', 'name' => 'EGW18', 'isActive' => true, 'blobId' => 'B2'],
		['id' => 'd', 'name' => 'felamimail', 'isActive' => false, 'blobId' => 'B3'],
		['id' => 'e', 'name' => 'mail', 'isActive' => false, 'blobId' => 'B4'],
	];

	public static function create() : self
	{
		$http = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
		$prop = new ReflectionProperty(ApiJmap::class, 'uploadUrl');
		$prop->setValue($http, 'https://example.org/upload/{accountId}/');
		return $http;
	}

	public function api(string $url, string $method='GET', $body='', array $header=['Content-Type: application/json'], ?array &$response_header=null, int $follow=3)
	{
		return ['blobId' => 'NEW'];
	}

	public function jmapCall(array $methodCalls, string|array|null $using=null, bool $emulate=false)
	{
		[$method, $args] = $methodCalls[0];
		if ($method === 'SieveScript/get')
		{
			return ['methodResponses' => [[$method, ['list' => $this->scripts], '0']]];
		}
		$this->sets[] = $args;
		$id = array_key_first($args['update'] ?? []);
		return ['methodResponses' => [[$method, [
			'updated' => $id ? [$id => ['blobId' => 'NEW']] : [],
			'created' => ['A' => ['name' => $args['create']['A']['name'] ?? '', 'isActive' => true]],
		], '0']]];
	}
}

class SieveJmapScriptLookupTest extends TestCase
{
	public function testInstallUpdatesExistingScriptWhichIsNotTheFirst()
	{
		$http = SieveJmapScriptLookupHttp::create();
		$sieve = new SieveJmap($http, 'byl');

		$sieve->installScript('EGW18', '# script', true);

		$this->assertCount(1, $http->sets);
		$this->assertArrayNotHasKey('create', $http->sets[0], 'script EGW18 exists and must be updated, not created');
		$this->assertSame(['blobId' => 'NEW'], $http->sets[0]['update']['c']);
	}

	public function testInstallCreatesMissingScript()
	{
		$http = SieveJmapScriptLookupHttp::create();
		$sieve = new SieveJmap($http, 'byl');

		$sieve->installScript('other', '# script', true);

		$this->assertSame('other', $http->sets[0]['create']['A']['name']);
	}

	public function testRemoveDestroysTheScriptById()
	{
		$http = SieveJmapScriptLookupHttp::create();
		$sieve = new SieveJmap($http, 'byl');

		$sieve->removeScript('EGW18');

		$this->assertCount(1, $http->sets);
		$this->assertSame(['c'], $http->sets[0]['destroy']);
		$this->assertTrue($http->sets[0]['onSuccessDeactivateScript'], 'the active script has to be deactivated in the same call');

		$http->sets = [];
		$sieve->removeScript('felamimail');
		$this->assertSame(['d'], $http->sets[0]['destroy']);
		$this->assertArrayNotHasKey('onSuccessDeactivateScript', $http->sets[0]);

		$http->sets = [];
		$sieve->removeScript('does-not-exist');
		$this->assertSame([], $http->sets);
	}
}
