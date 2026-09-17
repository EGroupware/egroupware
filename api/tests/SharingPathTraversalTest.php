<?php
/**
 * EGroupware API: regression test for Sharing::ServeRequest()'s path-traversal 400-bail
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__.'/LoggedInTest.php';

/**
 * Regression coverage for GHSA-5f5x-v83g-7f45 (share.php/<token>/../<path> scope escape), the
 * defense-in-depth fix in commit 5b46835dbb: Sharing::ServeRequest() now bails with a 400
 * response the instant REQUEST_URI (urldecoded) contains ".." - before anything else, including
 * share-token validation, ever runs.
 *
 * ServeRequest() itself calls `exit;` right after the 400 - fatal to whatever PHP process runs
 * it. Running it in-process (even under PHPUnit's own #[RunInSeparateProcess]) is not viable:
 * that isolation mechanism reports the test as "ended unexpectedly" whenever the child calls
 * exit() before handing its serialized result back, which CI (correctly) treats as a failed
 * build - an "expected" error is still an error. Registered shutdown functions were tried as a
 * way to capture evidence before that exit and don't reliably run to completion in PHPUnit 12's
 * isolated child in this environment either (confirmed empirically).
 *
 * So this spawns a genuinely independent PHP CLI subprocess itself (proc_open, not PHPUnit's
 * isolation), bootstrapped the same way any real request is (doc/phpunit_bootstrap.php +
 * LoggedInTest::load_egw()) - a real, unrelated OS process, so its exit() cannot affect the
 * PHPUnit process running this test at all, and this test method itself never errors: it makes
 * ordinary assertions about the subprocess's outcome and returns normally, like any other test.
 *
 * The subprocess declares a namespace-scoped shim for the global http_response_code():
 * ServeRequest() lives in namespace EGroupware\Api and calls the bare (unqualified)
 * http_response_code(...) - PHP resolves that against the CURRENT namespace first, falling back
 * to the global function only if none is declared there. The shim records the code to a file
 * synchronously, before the real `exit;` two lines later terminates the subprocess.
 *
 * Constructed via ReflectionClass::newInstanceWithoutConstructor() rather than a real share
 * session (Sharing::create_session()) - the traversal check is the literal first statement in
 * ServeRequest(), before $this->share (or anything else on the instance) is ever touched, so no
 * real share fixture is needed to reach it.
 */
class SharingPathTraversalTest extends LoggedInTest
{
	public function testServeRequestBailsWith400OnTraversalInRequestUri()
	{
		$resultFile = tempnam(sys_get_temp_dir(), 'egw_sharing_traversal_');
		@unlink($resultFile);

		$script = <<<PHP
<?php
namespace EGroupware\\Api;

function http_response_code(\$code = null)
{
	file_put_contents('$resultFile', (string)\$code);
	return \\http_response_code(\$code);
}

require_once '/var/www/egroupware/doc/phpunit_bootstrap.php';
require_once '/var/www/egroupware/api/tests/LoggedInTest.php';
\\EGroupware\\Api\\LoggedInTest::load_egw(\$GLOBALS['EGW_USER'], \$GLOBALS['EGW_PASSWORD']);

\$_SERVER['REQUEST_URI'] = '/egroupware/share.php/sometoken/../../../etc/passwd';
\$instance = (new \\ReflectionClass(Sharing::class))->newInstanceWithoutConstructor();
\$instance->ServeRequest();
PHP;
		$scriptFile = tempnam(sys_get_temp_dir(), 'egw_sharing_traversal_script_');
		file_put_contents($scriptFile, $script);

		$process = proc_open([PHP_BINARY, '-f', $scriptFile], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$this->assertIsResource($process, 'failed to spawn the subprocess');
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		proc_close($process);
		@unlink($scriptFile);

		$this->assertFileExists($resultFile,
			'ServeRequest() must call http_response_code() before exiting on a traversal attempt - '.
			"if this file is missing, the traversal check never ran at all. subprocess stderr: $stderr");
		$this->assertSame('400', file_get_contents($resultFile));

		@unlink($resultFile);
	}
}
