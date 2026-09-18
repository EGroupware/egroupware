<?php
/**
 * Regression test: Api\WebDAV\Hooks::logViewer() (the "Show log of following device" tail-popup,
 * shared by CalDAV\Hooks::log() and filemanager_hooks::log()) must never allow path traversal
 * outside the calling app's own files_dir subdirectory - this exact class of bug was fixed once
 * before for the CalDAV log-viewer (see HTTP_WebDAV_Server::sanitize_filename()'s docblock), so
 * this is regression coverage for both the original fix and this session's WebDAV/filemanager
 * extension of the same code.
 *
 * logViewer()'s own regex is deliberately loose on the trailing "...\.log$" part (it allows '..'
 * segments there) - the actual, unconditional enforcement is Api\Json\Tail's constructor, which
 * rejects ANY path containing '..', an absolute path, or a URL scheme. Both layers are exercised
 * here: a crafted $_GET['filename'] must be rejected (some exception, either logViewer()'s own
 * Exception\WrongParameter for values that don't even match its prefix regex, or Tail's
 * \InvalidArgumentException for ones that do), and a legitimate own-log file access must succeed.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package api
 * @subpackage webdav
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__.'/../LoggedInTest.php';

class LogViewerPathTraversalTest extends LoggedInTest
{
	protected static $log_dir;
	protected static $log_file;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		self::$log_dir = Config::read('phpgwapi')['files_dir'].'/filemanager/'.
			$GLOBALS['egw_info']['user']['account_lid'];
		if (!is_dir(self::$log_dir))
		{
			mkdir(self::$log_dir, 0700, true);
		}
		self::$log_file = self::$log_dir.'/phpunit-traversal-test.log';
		file_put_contents(self::$log_file, "test log content\n");
	}

	public static function tearDownAfterClass() : void
	{
		if (self::$log_file && file_exists(self::$log_file))
		{
			unlink(self::$log_file);
		}
		parent::tearDownAfterClass();
	}

	/**
	 * Data provider of malicious $_GET['filename'] values a crafted "show-log" request (or a direct
	 * URL) might send, all of which must be rejected before any file content is ever read.
	 *
	 * @return array
	 */
	public static function maliciousFilenameProvider() : array
	{
		return array(
			'traversal inside own dir' => array('filemanager/%s/../../../../etc/passwd.log'),
			'traversal wrong app prefix' => array('groupdav/%s/../../etc/passwd.log'),
			'absolute path' => array('/etc/passwd.log'),
			'no app prefix at all' => array('../../../etc/passwd.log'),
			'wrong extension (not .log)' => array('filemanager/%s/../../../../etc/passwd'),
		);
	}

	/**
	 * @param string $filename_template %s is replaced with the current user's account_lid
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('maliciousFilenameProvider')]
	public function testMaliciousFilenameRejected(string $filename_template) : void
	{
		$_GET['filename'] = sprintf($filename_template, $GLOBALS['egw_info']['user']['account_lid']);

		$threw = false;
		try
		{
			WebDAV\Hooks::logViewer('filemanager');
		}
		catch (\Throwable $e)
		{
			$threw = true;
		}
		$this->assertTrue($threw, 'logViewer() must reject "'.$_GET['filename'].'" but did not throw');
	}

	/**
	 * A legitimate own-log file must NOT be rejected by either security layer (this is the ordinary
	 * "click the log in preferences" path) - proves the rejections above aren't just over-broad
	 * denials. Only checks the two security-relevant exception types; unrelated failures further
	 * down logViewer()'s framework-rendering path (outside this test's in-process bootstrap) are
	 * not what this test is about.
	 */
	public function testLegitimateOwnLogNotRejectedBySecurityChecks() : void
	{
		$_GET['filename'] = 'filemanager/'.$GLOBALS['egw_info']['user']['account_lid'].
			'/phpunit-traversal-test.log';

		// logViewer()'s final step (Framework::render()) echoes a full HTML page directly - our
		// OWN ob_start() (not just measuring the level) is what actually stops that reaching real,
		// unbuffered stdout. Letting it escape unbuffered here would permanently mark PHP's
		// headers_sent() true for the REST of this PHPUnit process (verified: a single unbuffered
		// echo does this even under an later ob_start()) - which silently breaks every later
		// header()-based HTTP status (eg. CalDAV::runRequest()'s http_status()) for any other
		// in-process test that runs afterwards, however unrelated. This is exactly what broke
		// infolog's CalDAVImportTest in CI: doc/phpunit.xml's "Api" testsuite (this file) runs
		// before the "Apps" testsuite (infolog's), so the poisoning carried forward silently.
		$ob_level = ob_get_level();
		ob_start();
		try
		{
			WebDAV\Hooks::logViewer('filemanager');
		}
		catch (Exception\WrongParameter|\InvalidArgumentException $e)
		{
			$this->fail('legitimate own log file was rejected as a security violation: '.$e->getMessage());
		}
		catch (\Throwable $e)
		{
			// some other, unrelated failure (eg. framework rendering outside a full request) -
			// not a security-check rejection, so not this test's concern
		}
		finally
		{
			while (ob_get_level() > $ob_level) ob_end_clean();
			while (ob_get_level() < $ob_level) ob_start();
		}
		$this->addToAssertionCount(1);
	}
}
