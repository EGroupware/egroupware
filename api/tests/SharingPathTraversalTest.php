<?php
/**
 * EGroupware API: regression test for Sharing::ServeRequest()'s path-traversal 400-bail
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Namespace-scoped shim for the global http_response_code(): Sharing::ServeRequest() lives in
 * namespace EGroupware\Api and calls the bare (unqualified) http_response_code(...) - PHP
 * resolves that against the CURRENT namespace first, falling back to the global function only if
 * none is declared here. Declaring one in this file (loaded fresh in the isolated child process
 * spawned for the test below, before ServeRequest() ever runs) lets us observe the exact response
 * code synchronously, before the real `exit;` two lines later in the fixed code terminates the
 * process - no reliance on shutdown functions, which do not run to completion in this PHPUnit
 * version's process-isolation child (confirmed empirically; not investigated further since this
 * technique sidesteps the problem entirely).
 */
function http_response_code($code = null)
{
	file_put_contents(SharingPathTraversalTest::RESPONSE_CODE_FILE, (string)$code);

	return \http_response_code($code);
}

/**
 * Regression coverage for GHSA-5f5x-v83g-7f45 (share.php/<token>/../<path> scope escape), the
 * defense-in-depth fix in commit 5b46835dbb: Sharing::ServeRequest() now bails with a 400
 * response the instant REQUEST_URI (urldecoded) contains ".." - before anything else, including
 * share-token validation, ever runs.
 *
 * ServeRequest() itself calls `exit;` right after the 400 - fatal to whatever PHP process runs
 * it. The test that triggers it is wrapped in #[RunInSeparateProcess] purely to contain that
 * blast radius to one isolated child (which PHPUnit will report as "ended unexpectedly" - that
 * error status is expected and harmless, not a false negative: the actual pass/fail signal comes
 * from the FOLLOW-UP test reading the file the shim above wrote, which survives the child's death
 * since it's a real file on disk, not in-process state).
 *
 * Constructed via ReflectionClass::newInstanceWithoutConstructor() rather than a real share
 * session (Sharing::create_session()) - the traversal check is the literal first statement in
 * ServeRequest(), before $this->share (or anything else on the instance) is ever touched, so no
 * real share fixture is needed to reach it.
 */
class SharingPathTraversalTest extends TestCase
{
	const RESPONSE_CODE_FILE = '/tmp/egw_sharing_path_traversal_test_response_code';

	#[RunInSeparateProcess]
	public function testServeRequestBailsOnTraversalInRequestUri()
	{
		@unlink(self::RESPONSE_CODE_FILE);
		$_SERVER['REQUEST_URI'] = '/egroupware/share.php/sometoken/../../../etc/passwd';

		$instance = (new \ReflectionClass(Sharing::class))->newInstanceWithoutConstructor();
		$instance->ServeRequest();
	}

	public function testServeRequestBailedWith400()
	{
		$this->assertFileExists(self::RESPONSE_CODE_FILE,
			'ServeRequest() must call http_response_code() before exiting on a traversal attempt '.
			'- if this file is missing, the traversal check never ran at all');
		$this->assertSame('400', file_get_contents(self::RESPONSE_CODE_FILE));

		@unlink(self::RESPONSE_CODE_FILE);
	}
}
