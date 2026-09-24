<?php
/**
 * EGroupware API: regression test for Sharing::ServeRequest()'s Destination-header traversal guard
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__.'/LoggedInTest.php';

/**
 * Regression coverage for GHSA-ghvm-932h-8969: a writable share's WebDAV COPY/MOVE names its
 * target in the separate Destination request header, not the request URI. Sharing::ServeRequest()
 * only ever inspected REQUEST_URI for "..", so a request with a perfectly clean URI (passing that
 * guard) but a Destination header containing "../" reached _copymove() unchecked. Downstream,
 * Vfs::concat() normalizes "/../" segments but performs no containment check, so the destination
 * resolved outside the share root - into the share OWNER's real VFS, where the owner (not the
 * share holder) legitimately has write access - letting a writable-share holder create or
 * overwrite an arbitrary file anywhere the owner can write, with content the holder first
 * uploaded into the share via PUT.
 *
 * Fix: ServeRequest()'s existing "..." in REQUEST_URI" 400-bail now also inspects
 * $_SERVER['HTTP_DESTINATION'] the same way (urldecoded, substring check for "..").
 *
 * Same subprocess technique as SharingPathTraversalTest.php (see that file's docblock for the
 * full rationale): ServeRequest() calls exit() right after the 400, so this spawns a genuinely
 * independent PHP CLI subprocess with a namespace-scoped http_response_code() shim to observe the
 * status code synchronously before the real exit() terminates it.
 *
 * Constructed via ReflectionClass::newInstanceWithoutConstructor(), like the sibling test - the
 * traversal check is the first statement in ServeRequest(), before $this->share is ever touched,
 * so no real share fixture is needed to reach it. Both cases here deliberately keep REQUEST_URI
 * clean (matching the advisory's own PoC exactly - the whole point of the bug is that the URI
 * guard alone is not enough), so a pass here that regresses back to a URI-only check would show
 * up as this test failing, not as it accidentally passing for the wrong reason.
 */
class SharingDestinationTraversalTest extends LoggedInTest
{
	/**
	 * @return array<string, array{0:string}>
	 */
	public static function traversalDestinationProvider()
	{
		return [
			'advisory PoC shape: Destination host/../sibling' =>
				['http://example.com/egroupware/share.php/../escaped-poc-test/poc.txt'],
			'plain relative ../ in destination path' =>
				['/egroupware/share.php/sometoken/../../../etc/passwd'],
			'percent-encoded %2e%2e, must be caught after urldecode' =>
				['/egroupware/share.php/sometoken/%2e%2e/%2e%2e/escaped.txt'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('traversalDestinationProvider')]
	public function testServeRequestBailsWith400OnTraversalInDestinationHeader(string $destination)
	{
		$resultFile = tempnam(sys_get_temp_dir(), 'egw_sharing_dest_traversal_');
		@unlink($resultFile);
		$serverRoot = EGW_SERVER_ROOT;
		$user = var_export($GLOBALS['EGW_USER'], true);
		$password = var_export($GLOBALS['EGW_PASSWORD'], true);
		$destinationExport = var_export($destination, true);

		$script = <<<PHP
<?php
namespace EGroupware\\Api;

function http_response_code(\$code = null)
{
	file_put_contents('$resultFile', (string)\$code);
	return \\http_response_code(\$code);
}

require_once '$serverRoot/doc/phpunit_bootstrap.php';
require_once '$serverRoot/api/tests/LoggedInTest.php';
\\EGroupware\\Api\\LoggedInTest::load_egw($user, $password);

// clean URI - matches the advisory's own PoC: the traversal lives ONLY in the header
\$_SERVER['REQUEST_URI'] = '/egroupware/share.php/sometoken';
\$_SERVER['HTTP_DESTINATION'] = $destinationExport;
\$instance = (new \\ReflectionClass(Sharing::class))->newInstanceWithoutConstructor();
\$instance->ServeRequest();
PHP;
		$scriptFile = tempnam(sys_get_temp_dir(), 'egw_sharing_dest_traversal_script_');
		file_put_contents($scriptFile, $script);

		$process = proc_open([PHP_BINARY, '-f', $scriptFile], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$this->assertIsResource($process, 'failed to spawn the subprocess');
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		proc_close($process);
		@unlink($scriptFile);

		$this->assertFileExists($resultFile,
			'ServeRequest() must call http_response_code() before exiting on a traversal attempt in '.
			"Destination - if this file is missing, the header was never checked at all. ".
			"subprocess stderr: $stderr");
		$this->assertSame('400', file_get_contents($resultFile));

		@unlink($resultFile);
	}

	/**
	 * A Destination header with no ".." at all (and a clean URI) must NOT be rejected by this
	 * guard - proves the fix didn't turn into an overly-broad "no Destination header allowed"
	 * check. ServeRequest() proceeds well past this point for a legitimate request (share
	 * validation, WebDAV dispatch, ...), so this only asserts the traversal-specific 400 never
	 * fires - not that the whole request succeeds, which needs a real share fixture.
	 */
	public function testServeRequestDoesNotBailOnCleanDestinationHeader()
	{
		$resultFile = tempnam(sys_get_temp_dir(), 'egw_sharing_dest_clean_');
		@unlink($resultFile);
		$serverRoot = EGW_SERVER_ROOT;
		$user = var_export($GLOBALS['EGW_USER'], true);
		$password = var_export($GLOBALS['EGW_PASSWORD'], true);

		$script = <<<PHP
<?php
namespace EGroupware\\Api;

function http_response_code(\$code = null)
{
	if (\$code !== null) file_put_contents('$resultFile', (string)\$code, FILE_APPEND);
	return \\http_response_code(\$code);
}

require_once '$serverRoot/doc/phpunit_bootstrap.php';
require_once '$serverRoot/api/tests/LoggedInTest.php';
\\EGroupware\\Api\\LoggedInTest::load_egw($user, $password);

\$_SERVER['REQUEST_URI'] = '/egroupware/share.php/sometoken';
\$_SERVER['HTTP_DESTINATION'] = 'http://example.com/egroupware/share.php/sometoken/renamed.txt';
\$instance = (new \\ReflectionClass(Sharing::class))->newInstanceWithoutConstructor();
try {
	\$instance->ServeRequest();
} catch (\\Throwable \$e) {
	// share_token/DB lookups fail past the traversal guard with a fake token/no real db row -
	// that is fine and expected here, this test only cares whether 400 fired before that
}
PHP;
		$scriptFile = tempnam(sys_get_temp_dir(), 'egw_sharing_dest_clean_script_');
		file_put_contents($scriptFile, $script);

		$process = proc_open([PHP_BINARY, '-f', $scriptFile], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$this->assertIsResource($process, 'failed to spawn the subprocess');
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);
		@unlink($scriptFile);

		$codes = file_exists($resultFile) ? file_get_contents($resultFile) : '';
		@unlink($resultFile);
		$this->assertStringNotContainsString('400', $codes,
			'a clean Destination header (no "..") must not trigger the traversal 400-bail');
	}
}
