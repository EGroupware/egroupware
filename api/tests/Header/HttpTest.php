<?php
/**
 * EGroupware API: tests for Header\Http::host()'s request-vs-Setup-hostname precedence
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Header;

use PHPUnit\Framework\TestCase;

/**
 * Regression test for the real customer bug this precedence change fixes: local-shim mail
 * accounts' JMAP endpoint URL (built via Api\Framework::getUrl(), which calls Http::host(true))
 * used to prefer a Setup-configured hostname over the browser's own actual request host - if
 * those two ever differ (a different reverse-proxy path, a multi-homed box, a stale Setup value),
 * every URL built that way becomes a genuinely different origin than the page itself, and the
 * page's own connect-src 'self' CSP silently blocks it as a browser-level NetworkError, never
 * even reaching the server. host(true) must now prefer a live request's own host in every case,
 * falling back to the Setup hostname only when there is no live request to derive one from at all
 * (a genuine CLI/cron context, eg. building a notification-email link).
 */
class HttpTest extends TestCase
{
	private $serverBackup;
	private $hostnameBackup;

	protected function setUp() : void
	{
		$this->serverBackup = $_SERVER;
		$this->hostnameBackup = $GLOBALS['egw_info']['server']['hostname'] ?? null;
	}

	protected function tearDown() : void
	{
		$_SERVER = $this->serverBackup;
		$GLOBALS['egw_info']['server']['hostname'] = $this->hostnameBackup;
	}

	public function testLiveRequestHostWinsOverNonLocalhostSetupHostname()
	{
		// the exact real-world mismatch that broke local-shim mail accounts: an install's Setup
		// hostname is a real, non-'localhost' value, but the browser reached the page via a
		// DIFFERENT host (eg. a different reverse-proxy path) - the live request must win
		$GLOBALS['egw_info']['server']['hostname'] = 'configured.example.invalid';
		$_SERVER['HTTP_HOST'] = 'actual-request-host.example.invalid';
		unset($_SERVER['HTTP_X_FORWARDED_HOST']);

		self::assertSame('actual-request-host.example.invalid', Http::host(true));
	}

	public function testForwardedHostWinsOverHttpHostAndSetupHostname()
	{
		$GLOBALS['egw_info']['server']['hostname'] = 'configured.example.invalid';
		$_SERVER['HTTP_HOST'] = 'direct-host.example.invalid';
		$_SERVER['HTTP_X_FORWARDED_HOST'] = 'proxied-host.example.invalid, some-other-hop.invalid';

		self::assertSame('proxied-host.example.invalid', Http::host(true));
	}

	public function testFallsBackToSetupHostnameOnlyWithoutAnyLiveRequest()
	{
		// a genuine CLI/cron context - no HTTP_HOST/X-Forwarded-Host at all
		unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_FORWARDED_HOST']);
		$GLOBALS['egw_info']['server']['hostname'] = 'configured.example.invalid';

		self::assertSame('configured.example.invalid', Http::host(true));
	}

	public function testFallsBackToLocalhostWithoutAnyLiveRequestOrSetupHostname()
	{
		unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_FORWARDED_HOST']);
		unset($GLOBALS['egw_info']['server']['hostname']);

		self::assertSame('localhost', Http::host(true));
	}

	public function testUseSetupHostnameFalseIgnoresSetupHostnameEvenWithoutLiveRequest()
	{
		unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_FORWARDED_HOST']);
		$GLOBALS['egw_info']['server']['hostname'] = 'configured.example.invalid';

		self::assertSame('localhost', Http::host(false));
	}

	/**
	 * fullUrl() (Api\Framework::getUrl()'s implementation) is the ONLY caller anywhere in this
	 * codebase passing $use_setup_hostname=true - confirmed via grep before making this change -
	 * so this is the exact real call site the mail bug went through.
	 */
	public function testFullUrlUsesLiveRequestHostNotStaleSetupHostname()
	{
		$GLOBALS['egw_info']['server']['hostname'] = 'configured.example.invalid';
		$_SERVER['HTTP_HOST'] = 'actual-request-host.example.invalid';
		unset($_SERVER['HTTP_X_FORWARDED_HOST']);
		$_SERVER['HTTPS'] = 'on';

		self::assertSame('https://actual-request-host.example.invalid/mail/jmap.php',
			Http::fullUrl('/mail/jmap.php'));
	}
}
