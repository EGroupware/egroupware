<?php
/**
 * EGroupware Api: Test Mail\Jmap\Imap::session()'s apiUrl/downloadUrl/uploadUrl/eventSourceUrl
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail\Jmap\Imap;

/**
 * Regression test for a real customer report: attaching a file to a new compose failed with
 * "Failed to upload attachment X" for every local-shim (Dovecot/Cyrus) account.
 *
 * Root cause: these four URLs used to be built via Api\Framework::getUrl() (absolute, but through
 * Http::host(true)'s Setup-hostname preference - see ImapSessionUrlTest's sibling fix, the
 * connect-src CSP bug), then briefly made bare relative paths to fix THAT bug (2026-09-25) - which
 * broke this instead: jmap-jam's uploadBlob()/downloadBlob() run uploadUrl/downloadUrl through
 * expandURITemplate() (node_modules/jmap-jam/src/helpers.ts), which does `return new URL(expanded)`
 * - a relative string throws "Invalid URL" there. These URLs must stay ABSOLUTE, just built from
 * the CURRENT REQUEST's own host (Api\Header\Http::host(), which was never the buggy path - only
 * Http::host(true), via Api\Framework::getUrl(), preferred a Setup-configured hostname that can
 * differ from the browser's actual origin) instead of Framework::getUrl()'s Setup-hostname-first
 * resolution.
 */
class ImapSessionUrlTest extends Api\LoggedInTest
{
	private $serverBackup;

	protected function setUp() : void
	{
		$this->serverBackup = $_SERVER;
	}

	protected function tearDown() : void
	{
		$_SERVER = $this->serverBackup;
	}

	private function assertAbsoluteUrl(string $url, string $message) : void
	{
		self::assertMatchesRegularExpression('#^https?://[^/]+/#', $url, $message);
	}

	public function testUrlsAreAbsoluteAndMatchTheCurrentRequestHost()
	{
		$_SERVER['HTTP_HOST'] = 'actual-request-host.example.invalid';
		unset($_SERVER['HTTP_X_FORWARDED_HOST']);
		$GLOBALS['egw_info']['server']['hostname'] = 'some-other-configured-hostname.invalid';

		$session = Imap::session();

		foreach (['apiUrl', 'downloadUrl', 'uploadUrl', 'eventSourceUrl'] as $key)
		{
			$this->assertAbsoluteUrl($session[$key], "$key must be an absolute URL (jmap-jam's own "
				."uploadBlob()/downloadBlob() run it through JS's new URL(), which throws on a bare path)");
			self::assertStringContainsString('actual-request-host.example.invalid', $session[$key],
				"$key must reflect the CURRENT request's own host, not a stale/mismatched Setup hostname");
		}
	}

	/**
	 * downloadUrl/uploadUrl additionally carry RFC 8620 §6-mandated "{placeholder}" template
	 * segments (accountId/blobId/type/name) - confirms building an absolute URL up front doesn't
	 * accidentally escape/break those.
	 */
	public function testDownloadAndUploadUrlsKeepTheirTemplatePlaceholders()
	{
		$session = Imap::session();

		self::assertStringContainsString('{accountId}', $session['downloadUrl']);
		self::assertStringContainsString('{blobId}', $session['downloadUrl']);
		self::assertStringContainsString('{accountId}', $session['uploadUrl']);
	}

	/**
	 * This shim's own session() now advertises a real maxSizeUpload - see Imap::maxUploadSize()'s
	 * own docblock for why that method takes the ini values as optional params purely for this
	 * test (post_max_size/upload_max_filesize are PHP_INI_PERDIR, so ini_set() can't vary them at
	 * runtime the way a test would need to - confirmed live writing this test: doing so silently
	 * has no effect at all).
	 */
	public function testSessionAdvertisesMaxSizeUpload()
	{
		$session = Imap::session();
		self::assertArrayHasKey('maxSizeUpload', $session['capabilities'][Api\Mail\Jmap\Http::JMAP_CORE]);
		self::assertGreaterThan(0, $session['capabilities'][Api\Mail\Jmap\Http::JMAP_CORE]['maxSizeUpload']);
	}

	/**
	 * upload() reads the ENTIRE request body via file_get_contents('php://input') (not $_FILES),
	 * so the binding PHP-side ceiling is post_max_size, not upload_max_filesize - must report the
	 * SMALLER of the two regardless of which one that is.
	 */
	public function testMaxUploadSizeReportsTheSmallerOfPostMaxSizeAndUploadMaxFilesize()
	{
		self::assertSame(8 * 1024 * 1024, Imap::maxUploadSize('8M', '64M'));
		self::assertSame(8 * 1024 * 1024, Imap::maxUploadSize('64M', '8M'));
		self::assertSame(2 * 1024 * 1024 * 1024, Imap::maxUploadSize('2G', '3G'));
		self::assertSame(512 * 1024, Imap::maxUploadSize('512K', '1M'));
	}

	/**
	 * PHP's own "0 = unlimited" convention for either ini setting must not report a 0-byte
	 * maxSizeUpload - the opposite meaning (JMAP's maxSizeUpload has no "unlimited" sentinel of
	 * its own, so 0 would falsely claim "nothing may ever be uploaded").
	 */
	public function testMaxUploadSizeNeverReturnsZeroForPhpsUnlimitedConvention()
	{
		self::assertGreaterThan(0, Imap::maxUploadSize('0', '0'));
		self::assertSame(16 * 1024 * 1024, Imap::maxUploadSize('0', '16M'),
			'one side at PHP\'s "0 = unlimited" must not win a min() against the other, real limit');
		self::assertSame(16 * 1024 * 1024, Imap::maxUploadSize('16M', '0'));
	}
}
