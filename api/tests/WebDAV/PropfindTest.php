<?php
/**
 * Test the WebDAV server's PROPFIND method (webdav.php) - Phase 2 of the
 * webdav-test-coverage project (doc/ai/projects/webdav-test-coverage.md).
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package api
 * @subpackage webdav
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__.'/../WebDAVTest.php';

class PropfindTest extends WebDAVTest
{
	protected static $user;

	/**
	 * Fixed content of the shared file.txt fixture below, checked by
	 * testFilePropertiesReflectContent()'s getcontentlength assertion.
	 */
	const FILE_CONTENT = 'Hello, WebDAV!';

	/**
	 * One file + one sub-directory shared read-only across all test methods
	 * (created ONCE, never overwritten) - NOT per-test: this dev environment's
	 * /home/<user> routes through Versioning\StreamWrapper (default "/" mount
	 * is stylite.s3://), which creates a new version row on every write to an
	 * EXISTING non-empty file; repeated per-test overwrites of the same path
	 * would just pile up soft-deleted version rows for no reason, since these
	 * tests only ever READ file.txt/subdir/, never modify them.
	 */
	public static function setUpBeforeClass() : void
	{
		self::$user = self::randomLid('webdavpropfind');
		self::createUser(self::$user);

		$test = new static('setUpBeforeClass');
		$test->assertHttpStatus([200, 201],
			$test->putFile($test->homeFile(self::$user, 'file.txt'), self::FILE_CONTENT, 'text/plain', self::$user));
		$response = $test->getClient(self::$user)->request('MKCOL', $test->url($test->homeFile(self::$user, 'subdir/')));
		$test->assertHttpStatus(201, $response, 'MKCOL setup');
	}

	public static function tearDownAfterClass() : void
	{
		self::hardDeleteHomeTree(self::$user);
		parent::tearDownAfterClass();
	}

	public function testDepth0OnFileReturnsOnlyThatResource() : void
	{
		$response = $this->propfind($this->homeFile(self::$user, 'file.txt'), 0, self::$user);
		$this->assertHttpStatus(207, $response, 'PROPFIND depth 0 on file');

		$entries = $this->multistatusResponses($response);
		$this->assertCount(1, $entries, 'Depth 0 on a file should report exactly the file itself');
		$this->assertSame($this->homeFile(self::$user, 'file.txt'), $entries[0]['href']);
	}

	public function testFilePropertiesReflectContent() : void
	{
		$response = $this->propfind($this->homeFile(self::$user, 'file.txt'), 0, self::$user);
		$entries = $this->multistatusResponses($response);
		$props = $entries[0]['props'];

		$this->assertSame('', $props['resourcetype'] ?? null, 'A plain file must have an EMPTY resourcetype (no <collection/> child)');
		$this->assertSame((string)strlen(self::FILE_CONTENT), $props['getcontentlength'] ?? null);
		$this->assertNotEmpty($props['getetag'] ?? '');
		$this->assertNotEmpty($props['getlastmodified'] ?? '');
	}

	public function testDepth1OnCollectionListsChildren() : void
	{
		$response = $this->propfind($this->homeCollection(self::$user), 1, self::$user);
		$this->assertHttpStatus(207, $response, 'PROPFIND depth 1 on collection');

		$entries = $this->multistatusResponses($response);
		$hrefs = array_map(fn($e) => rtrim($e['href'], '/'), $entries);

		$this->assertContains(rtrim($this->homeCollection(self::$user), '/'), $hrefs, 'Depth 1 must include the collection itself');
		$this->assertContains(rtrim($this->homeFile(self::$user, 'file.txt'), '/'), $hrefs);
		$this->assertContains(rtrim($this->homeFile(self::$user, 'subdir'), '/'), $hrefs);
	}

	public function testDirectoryResourcetypeIsCollection() : void
	{
		$response = $this->propfind($this->homeFile(self::$user, 'subdir/'), 0, self::$user);
		$entries = $this->multistatusResponses($response);
		$props = $entries[0]['props'];

		$this->assertArrayHasKey('resourcetype', $props);
		// SimpleXMLElement's text-content trim() on a <resourcetype><collection/></resourcetype>
		// element with no text content yields an empty string too - assert via the raw XML instead.
		$this->assertMatchesRegularExpression('#<(?:D:)?collection\s*/>#', (string)$response->getBody());
	}

	public function testDepth0OnMissingPathReturns404() : void
	{
		$response = $this->propfind($this->homeFile(self::$user, 'does-not-exist.txt'), 0, self::$user);
		$this->assertHttpStatus(404, $response);
	}

	/**
	 * Real, documented server limitation (not exercised as a "should recurse"
	 * test): Server/Filesystem.php::PROPFIND() has a literal
	 * "// TODO recursion needed if 'Depth: infinite'" - depth "infinity" is
	 * treated the same as depth 1 (`!empty($options["depth"])` is true for
	 * BOTH), so a nested file is NOT reported despite RFC 4918 requiring
	 * full-tree recursion for Depth: infinity. Documented in
	 * doc/ai/projects/webdav-test-coverage.md.
	 *
	 * Uses its own throwaway sub-sub-directory (not the shared "subdir/"), so
	 * it doesn't perturb the read-only fixtures the other tests rely on.
	 */
	public function testDepthInfinityDoesNotActuallyRecurse() : void
	{
		$response = $this->getClient(self::$user)->request('MKCOL', $this->url($this->homeFile(self::$user, 'depthtest/')));
		$this->assertHttpStatus(201, $response, 'MKCOL depthtest/');
		$this->assertHttpStatus([200, 201],
			$this->putFile($this->homeFile(self::$user, 'depthtest/nested.txt'), 'nested', 'text/plain', self::$user));

		$response = $this->propfind($this->homeCollection(self::$user), 'infinity', self::$user);
		$this->assertHttpStatus(207, $response, 'PROPFIND depth infinity');

		$entries = $this->multistatusResponses($response);
		$hrefs = array_map(fn($e) => rtrim($e['href'], '/'), $entries);
		$this->assertContains(rtrim($this->homeFile(self::$user, 'depthtest'), '/'), $hrefs,
			'Depth infinity should still list the immediate depthtest entry itself');
		$this->assertNotContains(rtrim($this->homeFile(self::$user, 'depthtest/nested.txt'), '/'), $hrefs,
			'Depth infinity does NOT recurse into it - known server limitation, not a test bug');
	}
}
