<?php
/**
 * EGroupware Admin: regression test for non-admin Categories access
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Admin;

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api\LoggedInTest;

/**
 * admin_categories::init_static() switched from a plain acl->check(...,'admin') to
 * Acl::checkAdminDeny() in d5bea64f16 (GHSA-76q5-2jm8-x8c3 hardening) - correct for the
 * admin-only 'global_categorie' deny-mask, but init_static() also runs for regular, non-admin
 * users via preferences_categories_ui (which extends admin_categories and deliberately skips
 * its parent::__construct() to let non-admins manage their own categories) - and unconditionally
 * at file scope, via the bare `admin_categories::init_static();` at the bottom of
 * class.admin_categories.inc.php, triggered merely by autoloading the parent class. Since
 * checkAdminDeny() throws Exception\NoPermission\Admin for anyone without 'admin' app rights by
 * design, every non-admin clicking Categories (now surfaced per-app in kdots' app menu, see
 * kdots/js/EgwFrameworkApp.ts) got a hard "you need to be an administrator" error instead of
 * their own category list.
 *
 * Setup: LoggedInTest logs in as 'demo', a real non-admin account. The private static
 * $acl_search etc. caches are reset via reflection first, since they're computed once per
 * process (`is_null()` guard) and an earlier test class may have already populated them.
 *
 * Pass criteria: init_static() must not throw for a non-admin, and must leave the class
 * unrestricted (matching the pre-d5bea64f16 fail-open-for-non-admins behaviour) - the
 * 'global_categorie' admin deny-mask simply does not apply to a user who was never an admin.
 */
class CategoriesNonAdminAccessTest extends LoggedInTest
{
	private function resetStaticAclCache() : void
	{
		$class = new \ReflectionClass(\admin_categories::class);
		foreach(['acl_search', 'acl_add', 'acl_view', 'acl_edit', 'acl_delete', 'acl_add_sub'] as $prop)
		{
			$property = $class->getProperty($prop);
			$property->setAccessible(true);
			$property->setValue(null, null);
		}
	}

	public function testInitStaticDoesNotThrowForNonAdmin()
	{
		$this->assertArrayNotHasKey('admin', $GLOBALS['egw_info']['user']['apps'],
			'This test requires a non-admin session to be meaningful');

		$this->resetStaticAclCache();

		\admin_categories::init_static();

		$class = new \ReflectionClass(\admin_categories::class);
		foreach(['acl_search', 'acl_add', 'acl_view', 'acl_edit', 'acl_delete', 'acl_add_sub'] as $prop)
		{
			$property = $class->getProperty($prop);
			$property->setAccessible(true);
			$this->assertTrue($property->getValue(),
				"admin_categories::\$$prop must default to unrestricted (true) for a non-admin user");
		}
	}

	public function testPreferencesCategoriesUiIndexDoesNotThrowForNonAdmin()
	{
		$this->resetStaticAclCache();

		$_GET['cats_app'] = 'addressbook';
		$content = null;
		// Must not throw Exception\NoPermission\Admin - this is exactly the click path from
		// kdots' per-app "Categories" menu item (preferences.preferences_categories_ui.index).
		// index() renders a full etemplate response; discard it, only the absence of the
		// exception matters here.
		ob_start();
		try
		{
			(new \preferences_categories_ui())->index($content);
		}
		finally
		{
			ob_end_clean();
		}
		$this->addToAssertionCount(1);
	}

	/**
	 * The 'deny_cats' site-config restricts specific groups from managing their own categories.
	 * It was never actually enforced anywhere - only (before kdots dropped 'cats' entirely, see
	 * project history) used to hide the menu item, never to block the URL itself. This checks
	 * both halves now: the menu-feature flag computed in kdots_framework::content(), and the
	 * hard server-side gate in preferences_categories_ui::__construct().
	 */
	public function testDenyCatsBlocksMemberOfDeniedGroup()
	{
		$memberships = $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true);
		if (empty($memberships))
		{
			$this->markTestSkipped('Current test user (demo) is not a member of any group');
		}
		$denied_group = reset($memberships);

		$original = $GLOBALS['egw_info']['server']['deny_cats'] ?? null;
		$GLOBALS['egw_info']['server']['deny_cats'] = [$denied_group];
		try
		{
			$this->resetStaticAclCache();
			$this->expectException(\EGroupware\Api\Exception\NoPermission::class);
			new \preferences_categories_ui();
		}
		finally
		{
			$GLOBALS['egw_info']['server']['deny_cats'] = $original;
		}
	}

	/**
	 * Acl::deniedByGroup('deny_cats') is the exact call kdots_framework::content() uses to
	 * compute the per-app 'categories' menu-feature flag - tested directly here since content()
	 * itself needs a full framework/navbar-apps bootstrap that's impractical in a unit test.
	 */
	public function testDeniedByGroupTrueForMemberOfDeniedGroup()
	{
		$memberships = $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true);
		if (empty($memberships))
		{
			$this->markTestSkipped('Current test user (demo) is not a member of any group');
		}
		$denied_group = reset($memberships);

		$original = $GLOBALS['egw_info']['server']['deny_cats'] ?? null;
		$GLOBALS['egw_info']['server']['deny_cats'] = [$denied_group];
		try
		{
			$this->assertTrue($GLOBALS['egw']->acl->deniedByGroup('deny_cats'),
				'Acl::deniedByGroup() must find the user restricted');
		}
		finally
		{
			$GLOBALS['egw_info']['server']['deny_cats'] = $original;
		}
	}

	public function testDeniedByGroupFalseWhenNotConfigured()
	{
		$original = $GLOBALS['egw_info']['server']['deny_cats'] ?? null;
		$GLOBALS['egw_info']['server']['deny_cats'] = null;
		try
		{
			$this->assertFalse($GLOBALS['egw']->acl->deniedByGroup('deny_cats'));
		}
		finally
		{
			$GLOBALS['egw_info']['server']['deny_cats'] = $original;
		}
	}
}
