<?php
/**
 * Test xerox-scan.php's Xerox "Scan to HTTP" CGI-script bridge (Vfs\XeroxScan).
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package api
 * @subpackage vfs
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__.'/WebDAVTest.php';

use GuzzleHttp\RequestOptions;

class XeroxScanTest extends WebDAVTest
{
	protected static $user;
	protected static $log_dir;

	public static function setUpBeforeClass() : void
	{
		self::$user = self::randomLid('xeroxscan');
		self::createUser(self::$user);

		$account_id = Accounts::getInstance()->name2id(self::$user);
		$prefs = new Preferences($account_id);
		$prefs->read_repository();
		$prefs->add('filemanager', 'debug_level', 'f');
		$prefs->save_repository(true);

		self::$log_dir = Config::read('phpgwapi')['files_dir'].'/filemanager/'.self::$user;
	}

	public static function tearDownAfterClass() : void
	{
		self::hardDeleteHomeTree(self::$user);
		if (self::$log_dir && is_dir(self::$log_dir))
		{
			array_map('unlink', glob(self::$log_dir.'/*'));
			rmdir(self::$log_dir);
		}
		parent::tearDownAfterClass();
	}

	/**
	 * Points at xerox-scan.php instead of webdav.php, otherwise identical to WebDAVTest::url()
	 */
	protected function url($path='/')
	{
		return str_replace('/webdav.php', '/xerox-scan.php', parent::url($path));
	}

	protected function xeroxPost(array $fields) : \Psr\Http\Message\ResponseInterface
	{
		return $this->getClient(self::$user)->post($this->url($this->homeCollection(self::$user)), [
			RequestOptions::MULTIPART => $fields,
		]);
	}

	public function testListDirOfOwnHomeReturns200() : void
	{
		$response = $this->xeroxPost([
			['name' => 'theOperation', 'contents' => 'ListDir'],
			['name' => 'destDir', 'contents' => ''],
		]);
		$this->assertHttpStatus(200, $response);
	}

	public function testListDirOfMissingDirReturns404() : void
	{
		$response = $this->xeroxPost([
			['name' => 'theOperation', 'contents' => 'ListDir'],
			['name' => 'destDir', 'contents' => 'no-such-subdir'],
		]);
		$this->assertHttpStatus(404, $response);
	}

	public function testMakeDirThenListDirSeesIt() : void
	{
		$response = $this->xeroxPost([
			['name' => 'theOperation', 'contents' => 'MakeDir'],
			['name' => 'destDir', 'contents' => 'incoming'],
		]);
		$this->assertHttpStatus(201, $response);

		// idempotent: calling again on an existing dir must NOT fail
		$response = $this->xeroxPost([
			['name' => 'theOperation', 'contents' => 'MakeDir'],
			['name' => 'destDir', 'contents' => 'incoming'],
		]);
		$this->assertHttpStatus(200, $response);

		$response = $this->xeroxPost([
			['name' => 'theOperation', 'contents' => 'ListDir'],
			['name' => 'destDir', 'contents' => ''],
		]);
		$this->assertHttpStatus(200, $response);
		$this->assertStringContainsString('incoming', (string)$response->getBody());
	}

	/**
	 * PutFile with the file sent as a real upload part (filename= present, lands in $_FILES) -
	 * the common case for an actual scanned document.
	 */
	public function testPutFileAsRealUploadWritesContent() : void
	{
		$response = $this->xeroxPost([
			['name' => 'theOperation', 'contents' => 'PutFile'],
			['name' => 'destDir', 'contents' => ''],
			['name' => 'destName', 'contents' => 'scan1.pdf'],
			['name' => 'sendfile', 'contents' => '%PDF-1.4 fake scan', 'filename' => 'scan1.pdf'],
		]);
		$this->assertHttpStatus(200, $response);

		$get = $this->getClient(self::$user)->get(
			str_replace('/xerox-scan.php', '/webdav.php', $this->url($this->homeCollection(self::$user))).'scan1.pdf');
		$this->assertSame('%PDF-1.4 fake scan', (string)$get->getBody());
	}

	/**
	 * PutFile with the file sent as a plain field (no filename=, lands in $_POST) - some devices
	 * encode it this way instead.
	 */
	public function testPutFileAsPlainFieldWritesContent() : void
	{
		$response = $this->xeroxPost([
			['name' => 'theOperation', 'contents' => 'PutFile'],
			['name' => 'destDir', 'contents' => ''],
			['name' => 'destName', 'contents' => 'scan2.pdf'],
			['name' => 'sendfile', 'contents' => '%PDF-1.4 plain field scan'],
		]);
		$this->assertHttpStatus(200, $response);

		$get = $this->getClient(self::$user)->get(
			str_replace('/xerox-scan.php', '/webdav.php', $this->url($this->homeCollection(self::$user))).'scan2.pdf');
		$this->assertSame('%PDF-1.4 plain field scan', (string)$get->getBody());
	}

	public function testUnknownOperationReturns400() : void
	{
		$response = $this->xeroxPost([
			['name' => 'theOperation', 'contents' => 'Frobnicate'],
		]);
		$this->assertHttpStatus(400, $response);
	}

	public function testDeleteFileNotImplemented() : void
	{
		$response = $this->xeroxPost([
			['name' => 'theOperation', 'contents' => 'DeleteFile'],
			['name' => 'destDir', 'contents' => ''],
			['name' => 'destName', 'contents' => 'scan1.pdf'],
		]);
		$this->assertHttpStatus(501, $response);
	}

	/**
	 * The whole point of this session's work: a failed/odd request must be self-explanatory in the
	 * shared "WebDAV logging" log, without needing another live round-trip to diagnose.
	 */
	public function testRequestIsLogged() : void
	{
		$this->xeroxPost([
			['name' => 'theOperation', 'contents' => 'ListDir'],
			['name' => 'destDir', 'contents' => ''],
		]);

		$log_file = self::$log_dir.'/xerox-scan.log';
		$this->assertFileExists($log_file);
		$content = file_get_contents($log_file);
		$this->assertStringContainsString('operation=ListDir status=200 OK', $content);
	}
}
