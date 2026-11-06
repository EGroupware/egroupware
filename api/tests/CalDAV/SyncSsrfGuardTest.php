<?php
/**
 * EGroupware API: regression test that CalDAV\Sync (calendar subscribe) keeps its SSRF guard
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage caldav
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

/**
 * Regression coverage for GHSA-qp3q-cgqv-3674 / CVE-2026-77241 (SSRF via calendar subscribe,
 * CVSS 9.6 critical) - specifically the "wiring" half of the fix, not the guard logic itself.
 *
 * The actual SSRF guard (RestClientTrait::checkPublicIP(), api()'s only_public parameter) is
 * already thoroughly covered by RestClientTraitTest.php, using a synthetic class that just `use`s
 * the trait. That leaves one thing unverified: does CalDAV\Sync - the class calendar_uiforms::
 * subscribe() instantiates with an attacker-controlled URL - really call api() with the guard
 * active (its default), rather than passing only_public=false or bypassing api() with a raw curl
 * call somewhere? A future edit adding an explicit only_public=false ("to support an internal test
 * CalDAV server", a very plausible-sounding, well-intentioned change) would silently reopen this
 * exact SSRF, and RestClientTraitTest.php would keep passing throughout since it never touches
 * Sync at all.
 *
 * No network mocking needed: checkPublicIP() rejects a private/loopback/link-local IP LITERAL via
 * a pure filter_var() check, before any curl connection is attempted - confirmed by grepping every
 * $this->api(...) call site in Sync.php: none passes an explicit only_public argument, so all of
 * them (test(), propfind(), the calendar-fetch paths) rely on api()'s only_public=true default.
 */
class SyncSsrfGuardTest extends \PHPUnit\Framework\TestCase
{
	public function testTestRejectsLoopbackUrl()
	{
		$this->expectException(\InvalidArgumentException::class);

		(new Sync('http://127.0.0.1:1/'))->test();
	}

	public function testTestRejectsLinkLocalCloudMetadataUrl()
	{
		$this->expectException(\InvalidArgumentException::class);

		(new Sync('http://169.254.169.254/latest/meta-data/'))->test();
	}

	public function testTestRejectsPrivateRfc1918Url()
	{
		$this->expectException(\InvalidArgumentException::class);

		(new Sync('http://10.0.0.5/'))->test();
	}
}
