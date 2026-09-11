<?php
/**
 * Test the WebDAV server's POST method (webdav.php) for multipart/form-data
 * (plain HTML upload form) requests.
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

use Psr\Http\Message\ResponseInterface;

class PostTest extends WebDAVTest
{
	protected static $user;

	/**
	 * Assert a response's Location header points at $path - the server only sends a path-absolute
	 * Location (no scheme/host, see HTTP_WebDAV_Server::ServeRequest()'s $base_uri construction),
	 * so compare just the path component rather than the full url().
	 */
	protected function assertLocationIs(string $path, ResponseInterface $response) : void
	{
		$this->assertSame(parse_url($this->url($path), PHP_URL_PATH),
			parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
	}

	public static function setUpBeforeClass() : void
	{
		self::$user = self::randomLid('webdavpost');
		self::createUser(self::$user);
	}

	public static function tearDownAfterClass() : void
	{
		self::hardDeleteHomeTree(self::$user);
		parent::tearDownAfterClass();
	}

	public function testPostSingleFileToDirectoryCreatesFile() : void
	{
		$response = $this->postMultipart($this->homeCollection(self::$user), [
			['field' => 'file', 'filename' => 'upload.txt', 'contents' => 'hello world'],
		], self::$user);

		$this->assertHttpStatus(204, $response);
		$this->assertLocationIs($this->homeFile(self::$user, 'upload.txt'), $response);

		$get = $this->getFileResponse($this->homeFile(self::$user, 'upload.txt'), self::$user);
		$this->assertSame('hello world', (string)$get->getBody());
	}

	public function testPostMultipleFilesToDirectoryCreatesAllFiles() : void
	{
		$response = $this->postMultipart($this->homeCollection(self::$user), [
			['field' => 'files[]', 'filename' => 'multi-a.txt', 'contents' => 'A'],
			['field' => 'files[]', 'filename' => 'multi-b.txt', 'contents' => 'B'],
		], self::$user);

		$this->assertHttpStatus(204, $response);
		// Location points at the FIRST file, there's no standard way to report multiple
		$this->assertLocationIs($this->homeFile(self::$user, 'multi-a.txt'), $response);

		$this->assertSame('A', (string)$this->getFileResponse($this->homeFile(self::$user, 'multi-a.txt'), self::$user)->getBody());
		$this->assertSame('B', (string)$this->getFileResponse($this->homeFile(self::$user, 'multi-b.txt'), self::$user)->getBody());
	}

	/**
	 * A client-supplied filename trying to escape the target directory must be reduced to just
	 * its basename - "../../etc/evil.txt" must land as "evil.txt" INSIDE the target directory,
	 * never above it.
	 */
	public function testPostSanitizesPathTraversalFilename() : void
	{
		$response = $this->postMultipart($this->homeCollection(self::$user), [
			['field' => 'file', 'filename' => '../../etc/evil.txt', 'contents' => 'traversal'],
		], self::$user);

		$this->assertHttpStatus(204, $response);
		$this->assertLocationIs($this->homeFile(self::$user, 'evil.txt'), $response);

		$get = $this->getFileResponse($this->homeFile(self::$user, 'evil.txt'), self::$user);
		$this->assertSame('traversal', (string)$get->getBody());
	}

	public function testPostSingleFileToExistingFileReplacesContent() : void
	{
		$path = $this->homeFile(self::$user, 'replace-me.txt');
		$this->assertHttpStatus(201, $this->putFile($path, 'first version', 'text/plain', self::$user));

		$response = $this->postMultipart($path, [
			['field' => 'file', 'filename' => 'ignored-name.txt', 'contents' => 'second version'],
		], self::$user);
		$this->assertHttpStatus(204, $response);

		$get = $this->getFileResponse($path, self::$user);
		$this->assertSame('second version', (string)$get->getBody());
	}

	public function testPostSingleFileToNonExistentFileCreatesIt() : void
	{
		$path = $this->homeFile(self::$user, 'created-via-post.txt');

		$response = $this->postMultipart($path, [
			['field' => 'file', 'filename' => 'ignored-name.txt', 'contents' => 'created content'],
		], self::$user);
		$this->assertHttpStatus(204, $response);

		$get = $this->getFileResponse($path, self::$user);
		$this->assertSame('created content', (string)$get->getBody());
	}

	public function testPostMultipleFilesToExistingFileReturns400() : void
	{
		$path = $this->homeFile(self::$user, 'single-only.txt');
		$this->assertHttpStatus(201, $this->putFile($path, 'original', 'text/plain', self::$user));

		$response = $this->postMultipart($path, [
			['field' => 'files[]', 'filename' => 'a.txt', 'contents' => 'A'],
			['field' => 'files[]', 'filename' => 'b.txt', 'contents' => 'B'],
		], self::$user);
		$this->assertHttpStatus(400, $response);

		// original content must be untouched (all-or-nothing)
		$get = $this->getFileResponse($path, self::$user);
		$this->assertSame('original', (string)$get->getBody());
	}

	public function testPostWithMissingParentDirectoryReturns409() : void
	{
		$response = $this->postMultipart($this->homeFile(self::$user, 'no-such-dir/file.txt'), [
			['field' => 'file', 'filename' => 'x.txt', 'contents' => 'x'],
		], self::$user);
		$this->assertHttpStatus(409, $response);
	}
}
