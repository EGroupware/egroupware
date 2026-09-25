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
}
