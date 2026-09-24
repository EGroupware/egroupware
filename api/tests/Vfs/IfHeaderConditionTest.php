<?php

/**
 * Tests for HTTP_WebDAV_Server::_check_uri_condition() (via EGroupware\Api\Vfs\WebDAV)
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Vfs;

/**
 * Regression/feature coverage for making the WebDAV "If:" header's per-resource ETag/lock-token
 * conditions (RFC 4918 §10.4) actually work, instead of _check_uri_condition() unconditionally
 * returning true (found while auditing GHSA-ghvm-932h-8969's sibling header-handling code -
 * NOT itself a security fix: the actual lock-bypass protection already runs through the separate
 * _check_lock_status() substring check, unaffected by any of this).
 *
 * Tested at the _check_if_header_conditions() level (real wire-format "If:" header strings, same
 * as what ServeRequest() feeds it), not by hand-constructing _if_header_parser()'s internal
 * condition-string shape directly - this exercises parser and checker together, the way they are
 * actually used, and would catch a mismatch between the two (like the stray trailing ">" bug on
 * the ETag branches this same change fixed).
 *
 * Two groups of tests:
 * - StubbedWebDAV-based: checkLock()/currentEtag() overridden with known, controlled return
 *   values, so the parsing/comparison/negation logic is tested deterministically without needing
 *   any real VFS I/O.
 * - Real-file-based: exercises checkLock()/currentEtag()'s actual implementation against a real
 *   VFS file. Skipped (not failed) if the environment cannot create one - this dev instance's
 *   default "/" mount is backed by a real S3 service that has, independent of anything here,
 *   started refusing new file creates (confirmed via a raw file_put_contents()/Vfs::touch()
 *   probe returning false with a StreamWrapper "file does not exist or can not be created"
 *   error) - a pre-existing environment issue, not something these tests can work around.
 */
class IfHeaderConditionTest extends LoggedInTest
{
	protected function makeWebdav(string $uri='http://example.org/egroupware/groupdav.php/testfile.txt') : WebDAV
	{
		$webdav = new WebDAV();
		// override-first: PHP CLI's own $_SERVER['SCRIPT_NAME'] (eg. "Standard input code" under
		// `php -r`) would otherwise win over these via array + array's left-side-wins semantics
		$webdav->_SERVER = [
			'HTTP_HOST' => 'example.org',
			'SCRIPT_NAME' => '/egroupware/groupdav.php',
			'REQUEST_URI' => '/egroupware/groupdav.php/testfile.txt',
		] + $_SERVER;
		$webdav->uri = $uri;
		return $webdav;
	}

	protected function checkIf(WebDAV $webdav, string $ifHeader) : bool
	{
		$webdav->_SERVER['HTTP_IF'] = $ifHeader;
		return $webdav->_check_if_header_conditions();
	}

	// ------------------------------------------------------------------
	// Deterministic tests via StubbedWebDAV - no real VFS I/O needed
	// ------------------------------------------------------------------

	protected function makeStub($lock, ?string $etag) : StubbedWebDAV
	{
		$webdav = new StubbedWebDAV($lock, $etag);
		$webdav->_SERVER = [
			'HTTP_HOST' => 'example.org',
			'SCRIPT_NAME' => '/egroupware/groupdav.php',
			'REQUEST_URI' => '/egroupware/groupdav.php/testfile.txt',
		] + $_SERVER;
		$webdav->uri = 'http://example.org/egroupware/groupdav.php/testfile.txt';
		return $webdav;
	}

	public function testMatchingEtagConditionIsSatisfied()
	{
		$webdav = $this->makeStub(null, '"stub-etag-123"');
		$this->assertTrue($this->checkIf($webdav, '(["stub-etag-123"])'),
			'a condition asserting the resource\'s actual current ETag must be satisfied');
	}

	public function testNonMatchingEtagConditionIsNotSatisfied()
	{
		$webdav = $this->makeStub(null, '"stub-etag-123"');
		$this->assertFalse($this->checkIf($webdav, '(["not-the-real-etag"])'),
			'a condition asserting an ETag the resource does not have must fail');
	}

	public function testWeakEtagConditionUsesSameComparison()
	{
		$webdav = $this->makeStub(null, '"stub-etag-123"');
		$this->assertTrue($this->checkIf($webdav, '([W/"stub-etag-123"])'),
			'a weak ETag condition must compare the same way a strong one does (documented simplification)');
	}

	public function testNegatedMatchingEtagConditionFails()
	{
		$webdav = $this->makeStub(null, '"stub-etag-123"');
		$this->assertFalse($this->checkIf($webdav, '(Not ["stub-etag-123"])'),
			'negating a condition that would otherwise match must flip the result to not-satisfied');
	}

	public function testNegatedNonMatchingEtagConditionSucceeds()
	{
		$webdav = $this->makeStub(null, '"stub-etag-123"');
		$this->assertTrue($this->checkIf($webdav, '(Not ["not-the-real-etag"])'),
			'negating a condition that would otherwise fail must flip the result to satisfied');
	}

	public function testMatchingLockTokenConditionIsSatisfied()
	{
		// _check_if_header_conditions() enforces the RFC2518 6.4 opaquelocktoken UUID shape
		// (litmus tests this) before it ever reaches _check_uri_condition(), so the stub token
		// must look like a real one or every case here would 423 before the actual comparison
		$webdav = $this->makeStub(['token' => 'opaquelocktoken:12345678-1234-1234-1234-123456789012'], null);
		$this->assertTrue($this->checkIf($webdav, '(<opaquelocktoken:12345678-1234-1234-1234-123456789012>)'),
			'a condition asserting the resource\'s real, current lock token must be satisfied');
	}

	public function testWrongLockTokenConditionIsNotSatisfied()
	{
		$webdav = $this->makeStub(['token' => 'opaquelocktoken:12345678-1234-1234-1234-123456789012'], null);
		$this->assertFalse($this->checkIf($webdav, '(<opaquelocktoken:87654321-4321-4321-4321-210987654321>)'),
			'a condition asserting a lock token the resource does not actually have must fail');
	}

	public function testLockTokenConditionOnUnlockedResourceIsNotSatisfied()
	{
		$webdav = $this->makeStub(false, null);
		$this->assertFalse($this->checkIf($webdav, '(<opaquelocktoken:12345678-1234-1234-1234-123456789012>)'),
			'a lock-token condition on a resource that is not locked at all must fail');
	}

	/**
	 * Tagged-list: the condition is explicitly scoped to a DIFFERENT resource-URI than the
	 * current request, via a leading "<...>" before the "(...)" - this is the whole feature
	 * RFC 4918's If-header Tagged-list syntax exists for (eg. Depth:infinity operations, or
	 * COPY/MOVE needing to check both source and destination), and the part that was previously
	 * discarded entirely ("unset($uri); // not used").
	 */
	public function testTaggedListScopesConditionToExplicitResource()
	{
		$webdav = $this->makeStub(null, '"stub-etag-123"');
		$taggedUri = 'http://example.org/egroupware/groupdav.php/some/other/file.txt';

		$this->assertTrue($this->checkIf($webdav, '<'.$taggedUri.'> (["stub-etag-123"])'),
			'a Tagged-list condition explicitly scoped to a resource\'s URI must be checked '.
			'against that resource, not silently ignored');
		$this->assertFalse($this->checkIf($webdav, '<'.$taggedUri.'> (["wrong-etag"])'),
			'a Tagged-list condition with the wrong ETag for the explicitly-named resource must fail');
	}

	/**
	 * A syntactically well-formed but semantically empty condition list, referring to the
	 * CURRENT request's own resource (no resource-tag at all), must still be checked for real,
	 * not just always pass.
	 */
	public function testUntaggedConditionAppliesToCurrentResource()
	{
		$webdav = $this->makeStub(null, '"stub-etag-123"');
		$this->assertFalse($this->checkIf($webdav, '(["definitely-not-the-etag"])'),
			'an untagged condition list must be checked against the current request\'s own '.
			'resource, not silently accepted');
	}

	/**
	 * Regression guard: a resource-URI that cannot be resolved to a local path at all (different
	 * host here) must never be treated as satisfying an ETag/lock-token condition - the previous
	 * "not really implemented" stub returned true for anything not literally "<DAV:...>", which
	 * would have made this pass silently. Uses the real (non-stubbed) WebDAV since resolution
	 * failure happens before checkLock()/currentEtag() would ever be called.
	 */
	public function testConditionOnUnresolvableForeignHostUriFails()
	{
		$webdav = $this->makeWebdav();
		$this->assertFalse($this->checkIf($webdav, '<http://attacker.example/whatever> (["irrelevant"])'),
			'a condition scoped to a URI on a different host must never resolve to local state');
	}

	/**
	 * Regression guard for the same class of bug fixed in GHSA-ghvm-932h-8969's Destination
	 * header: a resource-URI containing ".." must never be resolved to a path outside this
	 * server's own root, even for this comparatively low-severity read-only ETag/lock probe.
	 */
	public function testConditionOnTraversalUriFails()
	{
		$webdav = $this->makeWebdav();
		$this->assertFalse(
			$this->checkIf($webdav, '<http://example.org/egroupware/groupdav.php/../../../etc/passwd> (["irrelevant"])'),
			'a resource-URI containing ".." must never be resolved at all, not even for a read-only check'
		);
	}

	// ------------------------------------------------------------------
	// Real-file-based tests - skipped if this environment cannot write one
	// ------------------------------------------------------------------

	protected $testFile;
	protected $lockToken;

	protected function tearDown() : void
	{
		if ($this->lockToken)
		{
			Vfs::unlock($this->testFile, $this->lockToken, false);
			$this->lockToken = null;
		}
		if ($this->testFile)
		{
			@Vfs::unlink($this->testFile);
			$this->testFile = null;
		}
		parent::tearDown();
	}

	/**
	 * @return string|null vfs path of a freshly created, empty test file, or null if this
	 *  environment would not let us create one (caller must markTestSkipped() in that case)
	 */
	protected function createRealTestFile() : ?string
	{
		$path = '/home/'.$GLOBALS['egw_info']['user']['account_lid'].
			'/if_header_test_'.bin2hex(random_bytes(4)).'.txt';
		if (@file_put_contents(Vfs::PREFIX.$path, 'If-header condition test content') === false)
		{
			return null;
		}
		$this->testFile = $path;
		return $path;
	}

	public function testRealFileMatchingEtagConditionIsSatisfied()
	{
		if (!($path = $this->createRealTestFile()))
		{
			$this->markTestSkipped('this environment could not create a real VFS test file (see class docblock)');
		}
		$stat = stat(Vfs::PREFIX.$path);
		$etagValue = $stat['ino'].':'.$stat['mtime'].':'.$stat['size'];

		$webdav = $this->makeWebdav('http://example.org/egroupware/groupdav.php'.$path);
		$webdav->_SERVER['REQUEST_URI'] = '/egroupware/groupdav.php'.$path;
		$this->assertTrue($this->checkIf($webdav, '(["'.$etagValue.'"])'),
			'a condition asserting the real resource\'s actual current ETag must be satisfied');
	}

	public function testRealFileMatchingLockTokenConditionIsSatisfied()
	{
		if (!($path = $this->createRealTestFile()))
		{
			$this->markTestSkipped('this environment could not create a real VFS test file (see class docblock)');
		}
		// _check_if_header_conditions() enforces the RFC2518 6.4 opaquelocktoken UUID shape,
		// so this can't just be an arbitrary random string like the other real-file tokens
		$token = sprintf('opaquelocktoken:%08x-%04x-%04x-%04x-%012x',
			random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0, 0xffff),
			random_int(0, 0xffff), random_int(0, 0xffffffffffff));
		$timeout = 300;
		$owner = 'mailto:test@example.org';
		$scope = 'exclusive';
		$type = 'write';
		if (!Vfs::lock($path, $token, $timeout, $owner, $scope, $type, false, false))
		{
			$this->markTestSkipped('could not create a real lock on the test file');
		}
		$this->lockToken = $token;

		$webdav = $this->makeWebdav('http://example.org/egroupware/groupdav.php'.$path);
		$webdav->_SERVER['REQUEST_URI'] = '/egroupware/groupdav.php'.$path;
		$this->assertTrue($this->checkIf($webdav, '(<'.$token.'>)'),
			'a condition asserting the real resource\'s actual current lock token must be satisfied');
	}
}

/**
 * Test double: checkLock()/currentEtag() return fixed, known values instead of touching the VFS
 * at all, so _check_uri_condition()'s parsing/comparison/negation logic can be tested
 * deterministically.
 */
class StubbedWebDAV extends WebDAV
{
	private $lock;
	private $etag;

	public function __construct($lock, $etag)
	{
		$this->lock = $lock;
		$this->etag = $etag;
	}

	function checkLock($path)
	{
		return $this->lock;
	}

	function currentEtag($path, $stat=null)
	{
		return $this->etag;
	}
}
