<?php
/**
 * Tests for admin_acl::check_access()
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// test base providing common stuff
require_once __DIR__.'/CommandBase.php';

use EGroupware\Api;

/**
 * admin_acl::check_access($account_id, $location, $throw) gates who may add/edit/delete an ACL
 * row via the self-service (non-admin) "Access rights" editor.
 *
 * Every app reachable through that editor (calendar, infolog, addressbook, timesheet, ...) stores
 * grants via Api\Acl::get_grants()'s convention: acl_account = the entry's OWNER, acl_location =
 * the account granted access (the reverse of Acl::check()'s account=grantee/location=owner). So a
 * non-admin's own_access must be based on $account_id (owner) matching the current user, not
 * $location - which is the account they are choosing to grant access TO, never themselves.
 *
 * Regression coverage for two opposite bugs seen on this one comparison:
 * - pre-fix (2013-2026-08-10): correct - $account_id compared, self-service sharing worked.
 * - 2026-08-10 regression (commit 53172789b9): flipped to compare $location instead, believing
 *   $account_id was the grantee (Acl::check()'s convention) - broke every non-admin "share my
 *   calendar/infolog/etc with a colleague" grant (ticket #124621), while simultaneously *opening*
 *   a cross-account IDOR in the other direction: a non-admin could pass $account_id=<victim>,
 *   $location=<self> and have it accepted, since only $location was checked against self.
 * - This test locks in the corrected behaviour (back to comparing $account_id) and guards against
 *   re-introducing either direction's bug.
 */
class AdminAclCheckAccessTest extends CommandBase
{
	/** Some other, unrelated account id - must not be the current test session's account */
	const OTHER_ACCOUNT = 44; // birgit, a real active account. Not modified by this test.

	/**
	 * The legitimate self-service case: current (non-admin) user is the resource owner
	 * (acl_account), granting access to somebody else (acl_location) - must be allowed.
	 */
	public function testOwnerGrantsToOtherIsAllowed()
	{
		$me = $GLOBALS['egw_info']['user']['account_id'];

		$this->assertTrue(
			admin_acl::check_access($me, self::OTHER_ACCOUNT, false),
			'Non-admin user must be able to grant access to their OWN data (account_id=self, location=other)'
		);
	}

	/**
	 * The cross-account IDOR: a non-admin claims somebody ELSE's account as the resource owner
	 * (acl_account) while naming themselves as the one granted access (acl_location) - must be
	 * denied, since a non-admin has no authority over another account's data.
	 */
	public function testOtherAsOwnerIsDenied()
	{
		$me = $GLOBALS['egw_info']['user']['account_id'];

		$this->assertFalse(
			admin_acl::check_access(self::OTHER_ACCOUNT, $me, false),
			'Non-admin user must NOT be able to act as if a DIFFERENT account (account_id) is the resource owner'
		);
	}

	/**
	 * Neither id is the current user - clearly not the non-admin's own data either way.
	 */
	public function testNeitherAccountNorLocationIsSelfIsDenied()
	{
		$this->assertFalse(
			admin_acl::check_access(self::OTHER_ACCOUNT, self::OTHER_ACCOUNT + 1, false),
			'Non-admin user must not get access when neither account_id nor location is themselves'
		);
	}

	/**
	 * 'run' (application run-rights) is explicitly excluded from own_access, even when the
	 * current user is the account_id - only a real admin may grant run-rights.
	 */
	public function testRunLocationAlwaysRequiresAdmin()
	{
		$me = $GLOBALS['egw_info']['user']['account_id'];

		$this->assertFalse(
			admin_acl::check_access($me, 'run', false),
			'Non-admin user must NOT be able to grant themselves (or anyone) run-rights via own_access'
		);
	}

	/**
	 * Default $throw=true must actually throw, not just return false, when access is denied.
	 */
	public function testThrowsOnDenied()
	{
		$this->expectException(Api\Exception\NoPermission::class);

		$me = $GLOBALS['egw_info']['user']['account_id'];
		admin_acl::check_access(self::OTHER_ACCOUNT, $me);
	}
}
