<?php
/**
 * EGroupware Api: Test Auth::check_password_change() persists its per-session state
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__ . '/LoggedInTest.php';

/**
 * Auth::check_password_change() memoizes auth_alpwchange_val/auth_UserKnowsAboutPwdChange
 * in function-static locals, previously bound BY REFERENCE to Cache::getSession() so any
 * mutation auto-persisted to the session. Migrated to explicit Cache::setSession() calls
 * (dropping the deprecated =& pattern) - this proves the explicit writes actually land in
 * the session with the same value the function itself is using, instead of silently only
 * updating the local static and never reaching storage.
 *
 * check_password_change()'s statics persist for the whole PHP process (not just this test),
 * so by the time this test runs the values may already have been populated by framework
 * code during login - that's fine, the invariant under test (session mirrors the function's
 * current static state) holds regardless of who triggered the first call.
 */
class AuthPasswordChangeSessionTest extends LoggedInTest
{
	public function testAlpwchangeValAndUserKnowsPersistToSession()
	{
		Auth::check_password_change();

		$statics = (new \ReflectionMethod(Auth::class, 'check_password_change'))->getStaticVariables();

		$this->assertSame($statics['alpwchange_val'], Cache::getSession('phpgwapi', 'auth_alpwchange_val'),
			'auth_alpwchange_val in the session does not match what check_password_change() computed');
		$this->assertSame($statics['UserKnowsAboutPwdChange'], Cache::getSession('phpgwapi', 'auth_UserKnowsAboutPwdChange'),
			'auth_UserKnowsAboutPwdChange in the session does not match what check_password_change() computed');
	}
}
