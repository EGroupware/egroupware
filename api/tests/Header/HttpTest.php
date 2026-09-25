<?php
/**
 * EGroupware API: tests for Header\Http::fullUrl()'s $use_setup_hostname passthrough
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Header;

use PHPUnit\Framework\TestCase;

/**
 * fullUrl()/Api\Framework::getUrl() used to hardcode host(true) - always preferring a possibly
 * stale/mismatched Setup-configured hostname over the current request's own. That broke a real
 * customer's local-shim mail accounts (a same-origin JMAP endpoint built with the wrong host got
 * silently blocked by the page's own connect-src 'self' CSP). Both now take an explicit
 * $use_setup_hostname parameter, defaulting to false (prefer the live request) - a caller with no
 * live request at all (a CLI/cron job) can still opt into the old behavior by passing true.
 */
class HttpTest extends TestCase
{
	private $serverBackup;
	private $hostnameBackup;

	protected function setUp() : void
	{
		$this->serverBackup = $_SERVER;
		$this->hostnameBackup = $GLOBALS['egw_info']['server']['hostname'] ?? null;
		$_SERVER['HTTP_HOST'] = 'actual-request-host.example.invalid';
		unset($_SERVER['HTTP_X_FORWARDED_HOST']);
		$_SERVER['HTTPS'] = 'on';
		$GLOBALS['egw_info']['server']['hostname'] = 'configured.example.invalid';
	}

	protected function tearDown() : void
	{
		$_SERVER = $this->serverBackup;
		$GLOBALS['egw_info']['server']['hostname'] = $this->hostnameBackup;
	}

	public function testDefaultsToTheCurrentRequestHost()
	{
		self::assertSame('https://actual-request-host.example.invalid/mail/jmap.php',
			Http::fullUrl('/mail/jmap.php'));
	}

	public function testExplicitFalseAlsoUsesTheCurrentRequestHost()
	{
		self::assertSame('https://actual-request-host.example.invalid/mail/jmap.php',
			Http::fullUrl('/mail/jmap.php', false));
	}

	public function testExplicitTrueFallsBackToTheOldSetupHostnamePreference()
	{
		self::assertSame('https://configured.example.invalid/mail/jmap.php',
			Http::fullUrl('/mail/jmap.php', true));
	}

	/**
	 * Non-absolute (no leading slash) input must pass through unchanged regardless of
	 * $use_setup_hostname - fullUrl()'s own documented contract ("only used if $link is only a
	 * path").
	 */
	public function testAlreadyAbsoluteLinkPassesThroughUnchanged()
	{
		self::assertSame('https://elsewhere.example.invalid/foo',
			Http::fullUrl('https://elsewhere.example.invalid/foo'));
	}
}
