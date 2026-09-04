<?php
/**
 * Test the WebDAV server's GET method (webdav.php), including byte-range
 * requests - Phase 3 of the webdav-test-coverage project
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

class GetTest extends WebDAVTest
{
	protected static $user;

	/**
	 * 260 bytes, 26 letters x 10, so byte offsets are easy to reason about
	 * (offset N is always ('A' + (N/10) % 26)).
	 */
	const FILE_CONTENT = "AAAAAAAAAABBBBBBBBBBCCCCCCCCCCDDDDDDDDDDEEEEEEEEEEFFFFFFFFFFGGGGGGGGGGHHHHHHHHHHIIIIIIIIIIJJJJJJJJJJKKKKKKKKKKLLLLLLLLLLMMMMMMMMMM";

	public static function setUpBeforeClass() : void
	{
		self::$user = self::randomLid('webdavget');
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

	/**
	 * No Content-Length assertion here: Server.php's http_GET() only sends an
	 * explicit Content-Length "if (!self::use_compression())", and
	 * use_compression() stays true for text/* mimetypes (only turned off for
	 * everything else, per its own comment about double-compressing zip
	 * files) - so a text/plain GET legitimately has no Content-Length header
	 * at all (relies on chunked transfer-encoding / connection close instead).
	 */
	public function testFullFileGet() : void
	{
		$response = $this->getFileResponse($this->homeFile(self::$user, 'file.txt'), self::$user);
		$this->assertHttpStatus(200, $response);
		$this->assertSame(self::FILE_CONTENT, (string)$response->getBody());
		$this->assertStringStartsWith('text/plain', $response->getHeaderLine('Content-Type'));
	}

	public function testMissingFileGetReturns404() : void
	{
		$response = $this->getFileResponse($this->homeFile(self::$user, 'does-not-exist.txt'), self::$user);
		$this->assertHttpStatus(404, $response);
	}

	public function testDirectoryGetReturnsAutoindexHtml() : void
	{
		$response = $this->getFileResponse($this->homeCollection(self::$user), self::$user);
		$this->assertHttpStatus(200, $response);
		$this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
		$this->assertStringContainsString('file.txt', (string)$response->getBody());
	}

	public function testSingleByteRangeFromStart() : void
	{
		$response = $this->getFileResponse($this->homeFile(self::$user, 'file.txt'), self::$user, [
			'Range' => 'bytes=0-9',
		]);
		$this->assertHttpStatus(206, $response, 'single byte-range GET');
		$this->assertSame('AAAAAAAAAA', (string)$response->getBody());
		$this->assertSame('bytes 0-9/'.strlen(self::FILE_CONTENT), $response->getHeaderLine('Content-Range'));
	}

	public function testSingleByteRangeMidFile() : void
	{
		$response = $this->getFileResponse($this->homeFile(self::$user, 'file.txt'), self::$user, [
			'Range' => 'bytes=20-29',
		]);
		$this->assertHttpStatus(206, $response);
		$this->assertSame('CCCCCCCCCC', (string)$response->getBody());
	}

	public function testByteRangeWithoutEndGoesToEndOfFile() : void
	{
		// "bytes=120-" - from offset 120 to end (last 10 bytes: "MMMMMMMMMM")
		$response = $this->getFileResponse($this->homeFile(self::$user, 'file.txt'), self::$user, [
			'Range' => 'bytes=120-',
		]);
		$this->assertHttpStatus(206, $response);
		$this->assertSame('MMMMMMMMMM', (string)$response->getBody());
	}

	/**
	 * Real, documented server bug (not exercised as a "should be 206" test):
	 * Server.php::http_GET()'s suffix-range branch (`else` at the bottom of
	 * the single-range if/elseif/else, reached when $range has no 'start' key)
	 * does fseek()+fpassthru() but - unlike BOTH other branches just above it -
	 * never calls `$this->http_status("206 Partial content")`, so the response
	 * keeps whatever status was set earlier ("200 OK") despite correctly
	 * returning only the partial body. A client relying on the status code
	 * to detect a partial response (rather than just trusting the body) would
	 * be misled. Documented in doc/ai/projects/webdav-test-coverage.md.
	 */
	public function testSuffixByteRangeReturnsLastNBytesButWrongStatus() : void
	{
		$response = $this->getFileResponse($this->homeFile(self::$user, 'file.txt'), self::$user, [
			'Range' => 'bytes=-10',
		]);
		$this->assertSame('MMMMMMMMMM', (string)$response->getBody(), 'body content is correct despite the status bug below');
		$this->assertHttpStatus(200, $response, 'BUG: should be 206 Partial content, see docblock');
	}

	/**
	 * Real, documented server bug: the range-start-past-EOF check
	 * (`fseek($stream, $range['start'], SEEK_SET); if (feof($stream)) { 416 }`)
	 * never actually triggers - PHP's feof() only becomes true after a FAILED
	 * READ, not merely from seeking past the physical end of a (seekable)
	 * stream, so this immediately-after-fseek() check is dead code. The
	 * request instead falls through to a normal 206 response with an EMPTY
	 * body (the read loop's `$size > 0` guard prevents reading anything, but
	 * nothing catches the resulting empty/invalid range either). Documented
	 * in doc/ai/projects/webdav-test-coverage.md.
	 */
	public function testRangeStartBeyondFileSizeDoesNotActuallyReturn416() : void
	{
		$response = $this->getFileResponse($this->homeFile(self::$user, 'file.txt'), self::$user, [
			'Range' => 'bytes=999-1999',
		]);
		$this->assertHttpStatus(206, $response, 'BUG: should be 416 Requested range not satisfiable, see docblock');
		$this->assertSame('', (string)$response->getBody());
	}
}
