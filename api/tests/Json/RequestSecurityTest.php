<?php
/**
 * EGroupware Api: JSON dispatcher security regression tests
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage json
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Json;

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api\LoggedInTest;

/**
 * Regression coverage for GHSA-76q5-2jm8-x8c3 / CVE-2026-73854 (CVSS 9.6 critical).
 *
 * Egw::check_app_rights() exempts currentapp 'api'/'about' from the normal app-membership
 * check ("give everyone implicit api rights"). Request::checkMenuAction() is the only thing
 * stopping an attacker from exploiting that exemption by spoofing menuaction=api.<AnyClass>.<method>
 * to run a class that actually belongs to a privileged app (eg. admin) - the original PoC was
 * menuaction=api.admin_passwordreset.ajax_reset, which reaches Accounts::set_memberships() with
 * no further ACL check and lets a non-admin add themselves to the Admins group.
 *
 * checkMenuAction() itself is a pure static function (string in, throws or returns), but this
 * extends LoggedInTest rather than plain TestCase: Request.php has a load-time side effect
 * (`Widget::scanForWidgets()` runs at the bottom of the file, outside any class/method) that
 * requires a live DB connection just to autoload the Request class at all - a bare TestCase run
 * fails with "Call to a member function select() on null" before checkMenuAction() ever runs.
 *
 * Setup strategy: LoggedInTest boots a real session (demo user, non-admin) via doc/phpunit.xml.
 * No fixtures are created or mutated - checkMenuAction() takes a raw string and either throws or
 * returns, so the demo session only exists to make the class loadable.
 *
 * Pass criteria: every payload that names a class belonging to a DIFFERENT app than the
 * menuaction's app-name component must throw \InvalidArgumentException (error code 997). A
 * regression (eg. someone "simplifying" the appName-vs-classApp comparison) would make one of
 * these payloads fall through silently, which is exactly how the original vulnerability worked.
 */
class RequestSecurityTest extends LoggedInTest
{
	/**
	 * @return array<string, array{0:string}>
	 */
	public static function crossAppMenuActionProvider()
	{
		return [
			'original PoC: api. prefix + admin class' => ['api.admin_passwordreset.ajax_reset'],
			'about. prefix + admin class' => ['about.admin_passwordreset.ajax_reset'],
			'api. prefix + different admin class' => ['api.admin_account.ajax_delete_group'],
			'api. prefix + admin_acl class' => ['api.admin_acl.ajax_change_acl'],
			'mixed-case appName does not evade the check' => ['Api.admin_passwordreset.ajax_reset'],
			'leading-backslash EGroupware class still resolves the real owning app' =>
				['api.\\EGroupware\\CalDAV\\OpenAPI.configuration'],
		];
	}

	/**
	 * A menuaction whose class does not belong to the declared app must be rejected.
	 *
	 * @param string $menuaction
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('crossAppMenuActionProvider')]
	public function testCrossAppMenuActionIsRejected($menuaction)
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionCode(997);

		Request::checkMenuAction($menuaction);
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function legitimateMenuActionProvider()
	{
		return [
			'class genuinely belongs to declared app' => ['admin.admin_ui.index'],
			'api-owned class called via api.' => ['api.EGroupware\\Api\\Framework\\About.index'],
			'admin appName is always allowed (highest privilege, used a lot in Admin app)' =>
				['admin.addressbook_contacts.index'],
		];
	}

	/**
	 * Sanity check the same guard does NOT reject legitimate same-app (or admin-prefixed)
	 * menuactions - otherwise the security fix would just be breaking the app instead of
	 * actually distinguishing attacker payloads from real ones.
	 *
	 * @param string $menuaction
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('legitimateMenuActionProvider')]
	public function testSameAppMenuActionIsAllowed($menuaction)
	{
		Request::checkMenuAction($menuaction);
		$this->addToAssertionCount(1);	// no exception thrown == pass
	}
}
