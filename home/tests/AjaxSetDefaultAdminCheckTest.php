<?php
/**
 * EGroupware home: regression test for ajax_set_default()'s admin check
 *
 * @link http://www.egroupware.org
 * @package home
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

/**
 * Regression coverage for GHSA-fv5g-m3g7-cjw5: home_ui::ajax_set_default() gated its "admins
 * only" path on $GLOBALS['egw_info']['apps']['admin'] - the INSTALLED-apps list
 * (Applications.php::read_installed_apps(), instance-wide, truthy for every user on any install
 * with the admin app installed), not the GRANTED-rights flag
 * $GLOBALS['egw_info']['user']['apps']['admin'] (intersected per-user at Session.php). The guard
 * therefore never fired for a non-admin, letting a request-controlled $group write forced/
 * default/group home-dashboard preferences for all users or any group.
 *
 * Fix (commit c0b18a11e0, "fix admin check", predates the advisory by over a month): check the
 * per-user granted-rights flag instead, and throw NoPermission\Admin rather than silently
 * returning.
 *
 * The default LoggedInTest session (EGW_USER) is a normal, non-admin account - exactly the
 * attacker's perspective in the advisory. This test asserts against the real, currently-shipped
 * code (not a reverted copy) that a non-admin is rejected; it's a regression guard, not a fresh
 * discovery.
 */
class AjaxSetDefaultAdminCheckTest extends \EGroupware\Api\AppTest
{
	public function testNonAdminIsRejected()
	{
		$this->assertEmpty($GLOBALS['egw_info']['user']['apps']['admin'] ?? null,
			'this test requires the default LoggedInTest session to be a non-admin');
		// egw_info['apps']['admin'] (the INSTALLED-apps list, not per-user rights) being truthy
		// is the actual pre-fix vulnerable condition - assert it's truthy here so a regression
		// back to checking that list instead of the per-user one would be caught
		$this->assertNotEmpty($GLOBALS['egw_info']['apps']['admin'] ?? null,
			'the admin app must be installed on this test instance for this test to be meaningful');

		$this->expectException(Api\Exception\NoPermission\Admin::class);

		home_ui::ajax_set_default('add', ['some_portlet_id'], 'forced');
	}
}
