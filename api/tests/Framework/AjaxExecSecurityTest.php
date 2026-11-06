<?php
/**
 * EGroupware Api: Framework\Ajax::ajax_exec() security regression test
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage framework
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Framework;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api\LoggedInTest;

/**
 * Regression coverage for GHSA-q2j3-83cg-vg83 (Framework\Ajax::ajax_exec() cross-app dispatch,
 * high severity) - the dispatcher-wiring half of commit c311428a28 ("make sure we have a valid
 * menuaction").
 *
 * Before that commit, ajax_exec() checked run-rights only for the attacker-chosen $app segment
 * of the menuaction, then dispatched `new $class` unconditionally - a menuaction like
 * home.admin_account.delete ran the REAL admin_account class (which belongs to the admin app)
 * while only checking the caller has rights to the harmless 'home' app. index.php already had a
 * class-to-app consistency check (Json\Request::checkMenuAction(), see
 * api/tests/Json/RequestSecurityTest.php for that check's own regression coverage);
 * ajax_exec() simply never called it. The fix added exactly that call, right after menuaction
 * presence is confirmed and before anything else (including `new $class`) runs.
 *
 * This test is about ajax_exec()'s WIRING to checkMenuAction(), not checkMenuAction()'s own logic
 * (already covered by RequestSecurityTest.php) - a future refactor of ajax_exec() that dropped or
 * moved this call after the dispatch would reopen the hole without RequestSecurityTest.php ever
 * noticing, since that file never touches ajax_exec() at all.
 *
 * Not covered here (same underlying vulnerability's second half, fixed in the same commit, but
 * needs @runInSeparateProcess - not attempted in this pass): admin_account::delete()'s OWN
 * fail-open admin-membership check, for the specific PoC sink named in the advisory. That method's
 * denial path calls Framework::window_close(), which unconditionally exit()s - fatal to the
 * current PHPUnit process if called in-process.
 */
class AjaxExecSecurityTest extends LoggedInTest
{
	/**
	 * @return array<string, array{0:string}>
	 */
	public static function crossAppMenuActionProvider()
	{
		return [
			'home app-name + real admin_account class (PoC shape: admin_account::delete)' =>
				['home.admin_account.delete'],
			'api app-name + real admin_acl class (PoC shape: admin_acl::index)' =>
				['api.admin_acl.index'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('crossAppMenuActionProvider')]
	public function testAjaxExecRejectsCrossAppMenuAction(string $menuaction)
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionCode(997);

		Ajax::ajax_exec('/index.php?menuaction='.$menuaction);
	}

	/**
	 * Sanity check the provider's payloads actually name real, autoloadable classes belonging to
	 * a DIFFERENT app than the spoofed one - otherwise this test would pass for the wrong reason
	 * (class simply not found) rather than because the cross-app check fired.
	 */
	public function testProviderClassesActuallyExistInAdminApp()
	{
		$this->assertTrue(class_exists('\\admin_account'));
		$this->assertTrue(class_exists('\\admin_acl'));
	}
}
