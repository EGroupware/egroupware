<?php
/**
 * Test the WebDAV server's PUT method (webdav.php), including Content-Range
 * chunked uploads - Phase 4 of the webdav-test-coverage project
 * (doc/ai/projects/webdav-test-coverage.md).
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

class PutTest extends WebDAVTest
{
	protected static $user;

	public static function setUpBeforeClass() : void
	{
		self::$user = self::randomLid('webdavput');
		self::createUser(self::$user);
	}

	public static function tearDownAfterClass() : void
	{
		self::hardDeleteHomeTree(self::$user);
		parent::tearDownAfterClass();
	}

	public function testPutNewFileReturns201() : void
	{
		$response = $this->putFile($this->homeFile(self::$user, 'new.txt'), 'content', 'text/plain', self::$user);
		$this->assertHttpStatus(201, $response);
	}

	public function testPutOverExistingFileReturns204() : void
	{
		$path = $this->homeFile(self::$user, 'overwrite.txt');
		$this->assertHttpStatus(201, $this->putFile($path, 'first version', 'text/plain', self::$user));

		$response = $this->putFile($path, 'second, longer version', 'text/plain', self::$user);
		$this->assertHttpStatus(204, $response);

		$get = $this->getFileResponse($path, self::$user);
		$this->assertSame('second, longer version', (string)$get->getBody());
	}

	public function testPutWithMissingParentDirectoryReturns409() : void
	{
		$response = $this->putFile($this->homeFile(self::$user, 'no-such-dir/file.txt'), 'x', 'text/plain', self::$user);
		$this->assertHttpStatus(409, $response);
	}

	public function testPutToExistingDirectoryReturns403() : void
	{
		$response = $this->putFile($this->homeCollection(self::$user), 'x', 'text/plain', self::$user);
		$this->assertHttpStatus(403, $response);
	}

	/**
	 * Chunked upload via sequential Content-Range PUTs to the SAME resource,
	 * the mechanism some WebDAV clients (and davfs2, and this session's own
	 * ask - "used via WebDAV range-requests by some clients to upload huge
	 * files") use instead of one huge request body. Server/Filesystem.php's
	 * PUT() deliberately opens with "c" mode (not "w") whenever
	 * $options['ranges'] is set, specifically so a later chunk doesn't
	 * truncate an earlier one.
	 */
	public function testChunkedUploadAssemblesFullFile() : void
	{
		$path = $this->homeFile(self::$user, 'chunked.bin');
		$total = 30;

		// first chunk creates the resource -> 201
		$response = $this->putFileRange($path, str_repeat('A', 10), 0, 9, $total, self::$user);
		$this->assertHttpStatus(201, $response, 'first chunk creates the resource');

		// subsequent chunks write to the existing resource -> 204
		$response = $this->putFileRange($path, str_repeat('B', 10), 10, 19, $total, self::$user);
		$this->assertHttpStatus(204, $response, 'second chunk');

		$response = $this->putFileRange($path, str_repeat('C', 10), 20, 29, $total, self::$user);
		$this->assertHttpStatus(204, $response, 'third chunk');

		$get = $this->getFileResponse($path, self::$user);
		$this->assertHttpStatus(200, $get);
		$this->assertSame(str_repeat('A', 10).str_repeat('B', 10).str_repeat('C', 10), (string)$get->getBody());
	}

	/**
	 * Chunks arriving out of order must still assemble correctly - each PUT
	 * carries its own absolute Content-Range offset, so arrival order must
	 * not matter (unlike a naive append).
	 */
	public function testOutOfOrderChunkedUploadStillAssemblesCorrectly() : void
	{
		$path = $this->homeFile(self::$user, 'chunked-reordered.bin');
		$total = 30;

		$this->assertHttpStatus(201, $this->putFileRange($path, str_repeat('C', 10), 20, 29, $total, self::$user), 'first chunk (last third)');
		$this->assertHttpStatus(204, $this->putFileRange($path, str_repeat('A', 10), 0, 9, $total, self::$user), 'second chunk (first third)');
		$this->assertHttpStatus(204, $this->putFileRange($path, str_repeat('B', 10), 10, 19, $total, self::$user), 'third chunk (middle third)');

		$get = $this->getFileResponse($path, self::$user);
		$this->assertSame(str_repeat('A', 10).str_repeat('B', 10).str_repeat('C', 10), (string)$get->getBody());
	}
}
