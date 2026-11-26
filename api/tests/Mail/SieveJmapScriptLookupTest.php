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
	public ?array $scripts = [
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
		// getScript()'s own download GET - distinguish from installScript()'s upload POST, which
		// this stand-in always answers with a fresh blobId below
		if ($method === 'GET')
		{
			return '# downloaded '.$url;
		}
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

	/**
	 * Regression test (found live 2026-11-25, admin "edit vacation notice" for a fresh account with
	 * no sieve script yet): RFC 8620 §5.1 Get's "list" is supposed to always be an array, even
	 * empty, but a real JMAP server can still send back a JSON `null` instead - a bare
	 * `foreach (null as ...)` only ever emits a PHP WARNING, not a Throwable, so
	 * Jmap::listScripts()'s own "ignore not existing script" catch block never fired, and `null`
	 * reached installScript()'s/getScript()'s own array_filter() calls, which don't accept null and
	 * fatal with "array_filter(): Argument #1 ($array) must be of type array, null given".
	 */
	public function testInstallCreatesMissingScriptWhenServerSendsNullInsteadOfAnEmptyList()
	{
		$http = SieveJmapScriptLookupHttp::create();
		$http->scripts = null;
		$sieve = new SieveJmap($http, 'byl');

		$sieve->installScript('other', '# script', true);

		$this->assertSame('other', $http->sets[0]['create']['A']['name']);
	}

	/**
	 * Regression test (found live 2026-11-26, Nordenham): getScript() had the EXACT SAME
	 * `[$script] = array_filter(...)` bug this file's own af05acf667 fix already addressed in
	 * installScript()/removeScript() - this third, identical call site was missed. Since
	 * array_filter() preserves original keys, `[$script] = ...` only ever looks at key 0, so any
	 * script NOT first in the account's list (here "EGW18", at index 1) silently threw "not
	 * found" - caught by retrieveRules()'s own "ignore not found script" catch, so getVacation()
	 * always came back with an EMPTY vacation array. The admin vacation dialog then showed status
	 * "Active" (the select's own default when no value binds) and a generic site-wide default
	 * text, regardless of what was actually saved - the save itself (installScript(), already
	 * fixed) was always correct.
	 */
	public function testGetScriptFindsAnExistingScriptWhichIsNotTheFirst()
	{
		$http = SieveJmapScriptLookupHttp::create();
		$prop = new ReflectionProperty(ApiJmap::class, 'downloadUrl');
		$prop->setValue($http, 'https://example.org/download/{accountId}/{name}/{blobId}/{type}');
		$sieve = new SieveJmap($http, 'byl');

		$content = $sieve->getScript('EGW18');

		// EGW18's own blobId is 'B2' (see SieveJmapScriptLookupHttp::$scripts) - finding it at all
		// (not throwing "not found") proves the non-first script was correctly located
		$this->assertStringContainsString('/B2/', $content);
	}

	public function testGetScriptThrowsForATrulyMissingScript()
	{
		$http = SieveJmapScriptLookupHttp::create();
		$sieve = new SieveJmap($http, 'byl');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('does-not-exist');
		$sieve->getScript('does-not-exist');
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
