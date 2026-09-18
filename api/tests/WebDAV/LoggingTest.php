<?php
/**
 * Test webdav.php's opt-in request/response logging (HTTP_WebDAV_Server::logApp()/log_request()),
 * shared with CalDAV/CardDAV's groupdav logging - added to diagnose a customer's WebDAV scanner
 * upload failure (multipart/form-data POST, see WebDAV/PostTest.php).
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

use GuzzleHttp\RequestOptions;

class LoggingTest extends WebDAVTest
{
	protected static $user;
	protected static $log_dir;

	public static function setUpBeforeClass() : void
	{
		self::$user = self::randomLid('webdavlog');
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
	 * A request with NO User-Agent header must log to a clearly-named "no-user-agent.log" file,
	 * not something derived from sanitize_filename('') that's easy to mistake for a stray/hidden
	 * file - some devices (eg. scanners) send no User-Agent at all.
	 *
	 * Also verifies Vfs\WebDAV::POST()'s per-file upload metadata (field/filename/size/error) is
	 * present in the log - that's the actual diagnostic payload this feature exists for, since the
	 * upload's raw binary body is deliberately never captured (isFileUpload() gate).
	 */
	public function testNoUserAgentLogsToOwnFileWithUploadMetadata() : void
	{
		$response = $this->getClient(self::$user)->post($this->url($this->homeCollection(self::$user)), [
			RequestOptions::MULTIPART => [
				['name' => 'file', 'filename' => 'scan.pdf', 'contents' => 'PDFDATA'],
			],
			RequestOptions::HEADERS => ['User-Agent' => ''],
		]);
		$this->assertHttpStatus(204, $response);

		$log_file = self::$log_dir.'/no-user-agent.log';
		$this->assertFileExists($log_file, 'no-user-agent.log was not created');

		$content = file_get_contents($log_file);
		$this->assertStringContainsString('upload: field=file filename=scan.pdf size=7 error=0', $content);
		$this->assertStringContainsString('204 No Content', $content);
		// the raw multipart body must NOT be captured, only its metadata (see isFileUpload())
		$this->assertStringNotContainsString('PDFDATA', $content);
	}

	/**
	 * A multipart part with a "name" but no "filename" attribute is NOT a file upload as far as
	 * PHP is concerned - it lands in $_POST, not $_FILES (a common real-world scanner/client
	 * mistake). POST() must reject it with 400, and the log must say exactly why - naming the
	 * $_POST field(s) it actually received - rather than leaving a bare 400 with no clue, which is
	 * what a customer-reported "everything I can see looks fine but I get 400" ticket looks like.
	 */
	public function testMissingFilenameLandsInPostAndLogsWhy() : void
	{
		$response = $this->getClient(self::$user)->post($this->url($this->homeCollection(self::$user)), [
			RequestOptions::MULTIPART => [
				// no 'filename' => Guzzle omits filename="..." from Content-Disposition, so PHP
				// treats this as a plain $_POST field instead of a $_FILES upload
				['name' => 'file', 'contents' => 'PDFDATA'],
			],
			RequestOptions::HEADERS => ['User-Agent' => 'scanner-no-filename-test'],
		]);
		$this->assertHttpStatus(400, $response);

		$log_file = self::$log_dir.'/scanner-no-filename-test.log';
		$this->assertFileExists($log_file, 'scanner-no-filename-test.log was not created');

		$content = file_get_contents($log_file);
		$this->assertStringContainsString('no files in request', $content);
		$this->assertStringContainsString('fields received as $_POST instead of $_FILES', $content);
		$this->assertStringContainsString('file (7 bytes)', $content);
	}
}
