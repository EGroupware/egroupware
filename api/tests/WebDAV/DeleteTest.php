<?php
/**
 * Test the WebDAV server's DELETE method (webdav.php) - Phase 5 of the
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

class DeleteTest extends WebDAVTest
{
	protected static $user;
	protected static $other;

	public static function setUpBeforeClass() : void
	{
		self::$user = self::randomLid('webdavdel');
		self::createUser(self::$user);
		self::$other = self::randomLid('webdavdelother');
		self::createUser(self::$other);
	}

	public static function tearDownAfterClass() : void
	{
		self::hardDeleteHomeTree(self::$user);
		self::hardDeleteHomeTree(self::$other);
		parent::tearDownAfterClass();
	}

	public function testDeleteFileReturns204ThenGetReturns404() : void
	{
		$path = $this->homeFile(self::$user, 'to-delete.txt');
		$this->assertHttpStatus([200, 201], $this->putFile($path, 'x', 'text/plain', self::$user));

		$response = $this->getClient(self::$user)->delete($this->url($path));
		$this->assertHttpStatus(204, $response);

		$get = $this->getFileResponse($path, self::$user);
		$this->assertHttpStatus(404, $get, 'file must be gone after DELETE');
	}

	public function testDeleteEmptyDirectoryReturns204() : void
	{
		$dir = $this->homeFile(self::$user, 'empty-dir-to-delete/');
		$this->assertHttpStatus(201, $this->getClient(self::$user)->request('MKCOL', $this->url($dir)));

		$response = $this->getClient(self::$user)->delete($this->url($dir));
		$this->assertHttpStatus(204, $response);
		$this->assertHttpStatus(404, $this->getFileResponse($dir, self::$user));
	}

	/**
	 * DELETE of a directory removes it together with its children and answers 204
	 *
	 * That holds on every backend: a hard-deleting one (plain sqlfs) removes the child's row, a
	 * soft-deleting one (versioning, S3) only flags it inactive - and a directory holding nothing
	 * but inactive rows counts as empty, so it can be removed too.
	 */
	public function testDeleteDirectoryWithChildren() : void
	{
		$dir = $this->homeFile(self::$user, 'to-delete-dir/');
		$file = $this->homeFile(self::$user, 'to-delete-dir/child.txt');
		$this->assertHttpStatus(201, $this->getClient(self::$user)->request('MKCOL', $this->url($dir)));
		$this->assertHttpStatus([200, 201], $this->putFile($file, 'x', 'text/plain', self::$user));

		$response = $this->getClient(self::$user)->delete($this->url($dir));

		$this->assertHttpStatus(204, $response, 'recursive DELETE must fully succeed');
		$this->assertHttpStatus(404, $this->getFileResponse($file, self::$user), 'the child must be gone');
		$this->assertHttpStatus(404, $this->getFileResponse($dir, self::$user), 'the directory must be gone');
	}

	public function testDeleteNonExistentPathReturns404() : void
	{
		$response = $this->getClient(self::$user)->delete($this->url($this->homeFile(self::$user, 'does-not-exist.txt')));
		$this->assertHttpStatus(404, $response);
	}

	/**
	 * A second, unrelated test user has no ACL grant on the first user's
	 * /home/<user> - real behavior is 404, NOT 403: with no read access to
	 * the owner's home directory at all, `file_exists($path)` (checked
	 * FIRST in Vfs\WebDAV::DELETE(), api/src/Vfs/WebDAV.php:85) is already
	 * false from the other user's perspective, so the request never reaches
	 * a permission check - it looks exactly like the path doesn't exist.
	 * This is sound security behavior (not confirming a private path's
	 * existence to an unauthorized user), just not the 403 one might
	 * naively expect.
	 */
	public function testDeleteWithoutPermissionReturns404NotExposingExistence() : void
	{
		$path = $this->homeFile(self::$user, 'owned-by-user.txt');
		$this->assertHttpStatus([200, 201], $this->putFile($path, 'x', 'text/plain', self::$user));

		$response = $this->getClient(self::$other)->delete($this->url($path));
		$this->assertHttpStatus(404, $response, 'a different user must not be able to DELETE this file, nor learn that it exists');

		// confirm it genuinely survived the denied attempt
		$this->assertHttpStatus(200, $this->getFileResponse($path, self::$user));
	}
}
