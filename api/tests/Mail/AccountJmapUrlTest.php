<?php
/**
 * EGroupware Api: Test Mail\Account::jmapUrl()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail;

/**
 * jmapUrl() is a pure static helper (no DB/network access), but still extends LoggedInTest
 * rather than a bare TestCase - merely autoloading Mail\Account from a bare TestCase can poison
 * its static $db for the rest of the PHPUnit process, see [[feedback_bare_testcase_poisons_account_db]].
 *
 * Shared between Mail\Imap\Jmap::jmapUrl() (real IMAP-side usage) and Mail\Jmap\Transport
 * (JMAP submission) - both need the exact same sentinel/scheme handling, moved here 2026-09-02
 * instead of duplicated.
 */
class AccountJmapUrlTest extends Api\LoggedInTest
{
	public function testHttpsSchemeAndDefaultPortOmitted()
	{
		$this->assertSame('https://mail.example.org',
			Mail\Account::jmapUrl('mail.example.org', 443, Mail\Account::JMAP_HTTPS));
	}

	public function testHttpSchemeAndDefaultPortOmitted()
	{
		$this->assertSame('http://mail.example.org',
			Mail\Account::jmapUrl('mail.example.org', 80, Mail\Account::JMAP_HTTP));
	}

	public function testNonDefaultPortIncluded()
	{
		$this->assertSame('https://mail.example.org:8443',
			Mail\Account::jmapUrl('mail.example.org', 8443, Mail\Account::JMAP_HTTPS));
	}

	public function testZeroPortUsesSchemeDefaultOnly()
	{
		$this->assertSame('https://mail.example.org',
			Mail\Account::jmapUrl('mail.example.org', 0, Mail\Account::JMAP_HTTPS));
	}

	/**
	 * Non-JMAP protocol bits (eg. a stale/irrelevant SSL_TLS value passed in by a caller that
	 * doesn't actually care) must still resolve to https, not error - JMAP_HTTP is the only bit
	 * pattern that selects http, everything else defaults to https.
	 */
	public function testNonJmapProtocolBitsDefaultToHttps()
	{
		$this->assertSame('https://mail.example.org',
			Mail\Account::jmapUrl('mail.example.org', 443, Mail\Account::SSL_TLS));
	}

	/**
	 * EGroupware's own sentinel service-names (the "mail" docker-compose service, and the
	 * "stalwart"/hosting-internal shortcuts) must be passed through UNCHANGED, never turned into
	 * eg. "https://mail" - not a real, resolvable host, broke a production account when this
	 * guard was missing (found live 2026-08-24, see Mail\Imap\Jmap::jmapUrl()'s own history).
	 */
	public function testSentinelHostsPassThroughUnchanged()
	{
		foreach (['mail', 'stalwart', 'internal.k8s.farm.egroupware.org'] as $sentinel)
		{
			$this->assertSame($sentinel, Mail\Account::jmapUrl($sentinel, 443, Mail\Account::JMAP_HTTPS));
		}
	}

	/**
	 * An already-schemed URL (eg. stored verbatim from an earlier resolution) must also pass
	 * through unchanged, not get a second scheme prefixed.
	 */
	public function testAlreadySchemedUrlPassesThroughUnchanged()
	{
		$this->assertSame('https://custom.example.org:8443/jmap/',
			Mail\Account::jmapUrl('https://custom.example.org:8443/jmap/', 443, Mail\Account::JMAP_HTTPS));
	}
}
