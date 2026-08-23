<?php
/**
 * EGroupware API: tests for CalDAV\RestClientTrait
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage caldav/rest
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api\Exception\Http as HttpException;

/**
 * Tests EGroupware\Api\CalDAV\RestClientTrait - the reusable REST client (api(), apiIterator(),
 * checkPublicIP()) used by Api\Mail\Jmap, CalDAV\Sync and news_admin_import, moved here from
 * doc/REST-CalDAV-CardDAV/api-client.php's global functions.
 *
 * A tiny local `php -S` server (loopback only, no external network/DNS needed) is started once
 * for the whole class to exercise real HTTP round-trips: JSON decoding, non-2xx status mapping to
 * HttpException, redirect handling, and the base_url-prepending convenience. This is a genuine
 * integration test of the curl plumbing, not a mock - deliberately, since that plumbing (redirect
 * following, header parsing, the SSRF guard on redirect targets) is exactly what regressed
 * silently before (it lived in an unnamespaced, untested doc/ script for years).
 *
 * NOT covered here: checkPublicIP()'s DNS-resolution branch for a hostname that isn't already a
 * literal IP (dns_get_record() has no injection seam in this trait, unlike admin_mail's
 * dnsQuery() wrapper) - only the two literal-IP branches are tested, which is where the actual
 * security-relevant filter_var() logic lives; the DNS branch is a thin pass-through to the same
 * filter_var() check per resolved record.
 */
class RestClientTraitTest extends \PHPUnit\Framework\TestCase
{
	private static $server_process;
	private static string $server_url;
	private static string $fixture_dir;

	public static function setUpBeforeClass() : void
	{
		self::$fixture_dir = sys_get_temp_dir().'/RestClientTraitTest-'.bin2hex(random_bytes(4));
		mkdir(self::$fixture_dir);
		file_put_contents(self::$fixture_dir.'/router.php', self::routerSource());

		// find a free loopback port, then immediately hand it to `php -S`
		$socket = stream_socket_server('tcp://127.0.0.1:0');
		[, $port] = explode(':', stream_socket_get_name($socket, false));
		fclose($socket);

		self::$server_url = "http://127.0.0.1:$port";
		$cmd = escapeshellarg(PHP_BINARY).' -S 127.0.0.1:'.$port.' '.escapeshellarg(self::$fixture_dir.'/router.php');
		self::$server_process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::$fixture_dir);
		self::assertIsResource(self::$server_process, 'failed to start php -S test server');

		// wait for the server to accept connections (max ~2s)
		for ($i = 0; $i < 40; $i++)
		{
			if (($conn = @fsockopen('127.0.0.1', (int)$port, $errno, $errstr, 0.1)))
			{
				fclose($conn);
				return;
			}
			usleep(50000);
		}
		self::fail('test php -S server did not start listening in time');
	}

	public static function tearDownAfterClass() : void
	{
		if (self::$server_process)
		{
			proc_terminate(self::$server_process);
			proc_close(self::$server_process);
		}
		array_map('unlink', glob(self::$fixture_dir.'/*'));
		@rmdir(self::$fixture_dir);
	}

	/**
	 * Minimal router for the `php -S` test server: inspects $_SERVER['REQUEST_URI']/METHOD and
	 * returns canned responses used by the tests below.
	 */
	private static function routerSource() : string
	{
		return <<<'EOT'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
switch ($path)
{
	case '/json':
		header('Content-Type: application/json');
		echo json_encode(['hello' => 'world', 'method' => $_SERVER['REQUEST_METHOD']]);
		break;

	case '/plain':
		header('Content-Type: text/plain');
		echo 'just text';
		break;

	case '/echo-path':
		header('Content-Type: application/json');
		echo json_encode(['path' => $path, 'query' => $_SERVER['QUERY_STRING'] ?? '']);
		break;

	case '/echo-body':
		header('Content-Type: application/json');
		echo json_encode(['body' => file_get_contents('php://input'), 'method' => $_SERVER['REQUEST_METHOD']]);
		break;

	case '/not-found':
		http_response_code(404);
		header('Content-Type: application/json');
		echo json_encode(['error' => 'not found here']);
		break;

	case '/redirect-here':
		header('Location: /json');
		http_response_code(302);
		break;

	case '/redirect-private':
		// a private/reserved IP literal - checkPublicIP() must reject this WITHOUT any DNS lookup
		header('Location: http://169.254.169.254/secret');
		http_response_code(302);
		break;

	case '/sync':
		parse_str($_SERVER['QUERY_STRING'] ?? '', $q);
		header('Content-Type: application/json');
		if (($q['sync-token'] ?? '') === '')
		{
			echo json_encode(['responses' => ['/a' => ['id' => 'a']], 'sync-token' => 'page2', 'more-results' => true]);
		}
		else
		{
			echo json_encode(['responses' => ['/b' => ['id' => 'b']], 'sync-token' => 'done']);
		}
		break;

	default:
		http_response_code(500);
		echo 'unexpected path: '.$path;
}
EOT;
	}

	private function client() : object
	{
		return new class { use RestClientTrait; };
	}

	// --- checkPublicIP() ---

	public function testCheckPublicIpRejectsPrivateIpLiteral()
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->client()->checkPublicIP('http://192.168.1.1/');
	}

	public function testCheckPublicIpRejectsLoopbackIpLiteral()
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->client()->checkPublicIP('http://127.0.0.1/');
	}

	public function testCheckPublicIpAllowsPublicIpLiteral()
	{
		// must NOT throw
		$this->client()->checkPublicIP('http://8.8.8.8/');
		$this->addToAssertionCount(1);
	}

	// --- api(): SSRF guard integration ---

	public function testApiRejectsPrivateIpBeforeConnectingWhenOnlyPublic()
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->client()->api('http://127.0.0.1:1/', 'GET', '', [], $dummy, 3, true);
	}

	public function testApiAllowsLoopbackWhenOnlyPublicIsFalse()
	{
		// only_public=false must skip the SSRF guard entirely and attempt a real connection -
		// nothing listens on our test server's /json path with a WRONG port, but the point here
		// is just that it does NOT throw InvalidArgumentException for the private IP
		try {
			$this->client()->api(self::$server_url.'/json', 'GET', '', [], $dummy, 3, false);
			$this->addToAssertionCount(1);
		}
		catch (\InvalidArgumentException $e) {
			$this->fail('only_public=false must not run the private-IP guard: '.$e->getMessage());
		}
	}

	// --- api(): connection failure ---

	public function testApiThrowsHttpExceptionWithCodeZeroOnConnectionFailure()
	{
		// nothing listens on port 1 - connection must fail fast
		try {
			$this->client()->api('http://127.0.0.1:1/', 'GET', '', [], $dummy, 3, false);
			$this->fail('expected HttpException for a refused connection');
		}
		catch (HttpException $e) {
			$this->assertSame(0, $e->getCode());
			$this->assertSame('http://127.0.0.1:1/', $e->request_uri);
		}
	}

	// --- api(): base_url prepending ---

	public function testApiPrependsBaseUrlForPathOnlyUrls()
	{
		$client = $this->client();
		$client->base_url = self::$server_url;

		$result = $client->api('/echo-path', 'GET', '', [], $dummy, 3, false);

		$this->assertSame('/echo-path', $result['path']);
	}

	public function testApiLeavesFullUrlUnchanged()
	{
		$client = $this->client();
		$client->base_url = 'http://this-must-not-be-used.invalid';

		$result = $client->api(self::$server_url.'/echo-path', 'GET', '', [], $dummy, 3, false);

		$this->assertSame('/echo-path', $result['path']);
	}

	// --- api(): response handling ---

	public function testApiDecodesJsonResponse()
	{
		$result = $this->client()->api(self::$server_url.'/json', 'GET', '', [], $dummy, 3, false);

		$this->assertSame(['hello' => 'world', 'method' => 'GET'], $result);
	}

	public function testApiReturnsRawStringForNonJsonResponse()
	{
		$result = $this->client()->api(self::$server_url.'/plain', 'GET', '', [], $dummy, 3, false);

		$this->assertSame('just text', $result);
	}

	public function testApiSendsExplicitAuthorizationHeader()
	{
		// the trait itself has no credential storage - the caller passes the header explicitly
		$result = $this->client()->api(self::$server_url.'/echo-body', 'POST', 'payload',
			['Authorization: Basic Zm9vOmJhcg==', 'Content-Type: text/plain'], $dummy, 3, false);

		$this->assertSame('payload', $result['body']);
		$this->assertSame('POST', $result['method']);
	}

	public function testApiThrowsHttpExceptionWithResponseBodyOnNon2xxStatus()
	{
		try {
			$this->client()->api(self::$server_url.'/not-found', 'GET', '', [], $dummy, 3, false);
			$this->fail('expected HttpException for a 404 response');
		}
		catch (HttpException $e) {
			$this->assertSame(404, $e->getCode());
			$this->assertSame(['error' => 'not found here'], json_decode($e->response, true));
		}
	}

	public function testApiFollowsRedirectToSameServerPath()
	{
		$result = $this->client()->api(self::$server_url.'/redirect-here', 'GET', '', [], $dummy, 3, false);

		$this->assertSame(['hello' => 'world', 'method' => 'GET'], $result);
	}

	public function testApiRejectsRedirectToPrivateIpEvenWithoutDns()
	{
		$this->expectException(\InvalidArgumentException::class);
		// only_public defaults to true here - the redirect target is a private/link-local IP literal
		$this->client()->api(self::$server_url.'/redirect-private');
	}

	// --- apiIterator() ---

	public function testApiIteratorFollowsSyncTokenAcrossPages()
	{
		$client = $this->client();
		$client->base_url = self::$server_url;
		$params = [];

		$results = iterator_to_array($client->apiIterator('/sync', $params, false));

		$this->assertCount(2, $results);
		$this->assertSame('a', $results[0]['id']);
		$this->assertSame('/a', $results[0]['@self']);
		$this->assertSame('b', $results[1]['id']);
		$this->assertSame('done', $params['sync-token']);
	}
}
