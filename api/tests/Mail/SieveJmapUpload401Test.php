<?php
/**
 * EGroupware Api: Test Mail\Sieve\Jmap retrying a script upload, which Stalwart answers with 401 for a just issued token
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Exception\Http as HttpException;
use EGroupware\Api\Jmap as ApiJmap;
use EGroupware\Api\Mail\Jmap\Http;
use EGroupware\Api\Mail\Sieve\Jmap as SieveJmap;
use PHPUnit\Framework\TestCase;

/**
 * Stand-in JMAP client, whose upload fails a given number of times with the given HTTP status
 */
class SieveJmapUpload401Http extends Http
{
	public int $uploads = 0;
	public int $fail_times = 0;
	public int $fail_status = 401;

	public static function create(int $fail_times, int $status=401) : self
	{
		$http = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(ApiJmap::class, 'uploadUrl'))->setValue($http, 'https://example.org/upload/{accountId}/');
		$http->fail_times = $fail_times;
		$http->fail_status = $status;
		return $http;
	}

	public function api(string $url, string $method='GET', $body='', array $header=['Content-Type: application/json'], ?array &$response_header=null, int $follow=3)
	{
		if (++$this->uploads <= $this->fail_times)
		{
			throw new HttpException('Unexpected HTTP status code '.$this->fail_status, $this->fail_status, $method, $url, $body);
		}
		return ['blobId' => 'NEW'];
	}

	public function jmapCall(array $methodCalls, string|array|null $using=null, bool $emulate=false)
	{
		[$method] = $methodCalls[0];
		if ($method === 'SieveScript/get')
		{
			return ['methodResponses' => [[$method, ['list' => []], '0']]];
		}
		return ['methodResponses' => [[$method, ['created' => ['A' => ['name' => 'x', 'isActive' => true]]], '0']]];
	}
}

class SieveJmapUpload401Test extends TestCase
{
	protected function setUp() : void
	{
		SieveJmap::$upload_401_delay_us = 0;
	}

	public function testUploadIsRetriedAfter401()
	{
		$http = SieveJmapUpload401Http::create(2);
		(new SieveJmap($http, 'byl'))->installScript('x', '# script', true);

		$this->assertSame(3, $http->uploads);
	}

	public function testGivesUpAfterTheLastTry()
	{
		$http = SieveJmapUpload401Http::create(100);
		$this->expectException(HttpException::class);
		try {
			(new SieveJmap($http, 'byl'))->installScript('x', '# script', true);
		}
		finally {
			$this->assertSame(SieveJmap::UPLOAD_401_TRIES, $http->uploads);
		}
	}

	public function testOtherStatusIsNotRetried()
	{
		$http = SieveJmapUpload401Http::create(1, 500);
		try {
			(new SieveJmap($http, 'byl'))->installScript('x', '# script', true);
			$this->fail('exception expected');
		}
		catch (HttpException $e) {
			$this->assertSame(500, $e->getCode());
		}
		$this->assertSame(1, $http->uploads);
	}
}
