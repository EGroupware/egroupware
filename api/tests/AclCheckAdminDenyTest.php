<?php
/**
 * EGroupware Api: Acl::checkAdminDeny() tests
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage acl
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__.'/LoggedInTest.php';

/**
 * Acl::checkAdminDeny() (api/src/Acl.php:315) is the shared primitive introduced in d5bea64f16 to
 * replace the fail-open deny-mask pattern at the root of GHSA-76q5-2jm8-x8c3 / CVE-2026-73854
 * (plain `acl->check($location, $mask, 'admin')` returns falsy - "not denied" - for a user who was
 * NEVER an admin, not just a restricted one, so a bare `if (check(...)) redirect()` never fires for
 * a genuine non-admin). It's now the single gate several admin_* classes rely on, so it's tested
 * directly rather than only indirectly through each caller.
 *
 * Setup: LoggedInTest boots the non-admin 'demo' session; individual tests switch to the real
 * 'sysop' admin account via asAdmin() where needed. No fixtures are created or mutated.
 *
 * Pass criteria:
 * - A non-admin ('demo') must always get Exception\NoPermission\Admin, regardless of the mask
 *   checked - this is what makes the primitive fail CLOSED instead of open.
 * - A genuine, unrestricted admin ('sysop') must NOT get that exception, and checkAdminDeny()'s
 *   return value must match a direct acl->check(...,'admin') call for the same arguments (proving
 *   it's a transparent passthrough for real admins, not an additional restriction).
 */
class AclCheckAdminDenyTest extends LoggedInTest
{
	public function testNonAdminAlwaysThrows()
	{
		$this->expectException(Exception\NoPermission\Admin::class);

		$GLOBALS['egw']->acl->checkAdminDeny('current_sessions', 1);
	}

	public function testAdminGetsPassthroughResult()
	{
		$this->asAdmin(function()
		{
			$expected = $GLOBALS['egw']->acl->check('current_sessions', 1, 'admin');
			$actual = $GLOBALS['egw']->acl->checkAdminDeny('current_sessions', 1);

			$this->assertSame((bool)$expected, (bool)$actual,
				'checkAdminDeny() must return the same deny-state as a direct check() call for a real admin');
		});
	}
}
