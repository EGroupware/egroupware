<?php
/**
 * EGroupware Admin: defense-in-depth regression tests for admin_customfields/Groups/admin_accesslog
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Admin;

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

/**
 * Regression coverage for three defense-in-depth gaps found auditing the GHSA-76q5-2jm8-x8c3
 * hardening follow-up: admin_customfields::index(), Groups::edit()'s save branch, and
 * admin_accesslog::get_rows() previously had NO authorization check of their own - they relied
 * entirely on the JSON dispatcher (Json\Request::checkMenuAction() + Egw::check_app_rights())
 * keeping non-admins from reaching them at all. That's a single point of failure: any future
 * change to the dispatcher's app-binding logic would silently reopen full read/write access to
 * custom-field definitions, group membership, and the access/session log - exactly how the
 * original admin_passwordreset CVE worked before it got its own independent check.
 *
 * Setup: each test calls the entry point directly, bypassing the dispatcher entirely (exactly
 * like a dispatcher regression would), as the 'demo' user LoggedInTest logs in as by default - a
 * real, non-admin account (no 'admin' key in $GLOBALS['egw_info']['user']['apps']). No fixtures
 * are created; every assertion is that an exception fires BEFORE any state-changing or
 * data-returning code runs.
 *
 * Pass criteria: each call throws Exception\NoPermission (or the ::Admin subclass) as its very
 * first effect. A regression that moved the new check after the state-changing code, or dropped
 * it entirely, would either not throw at all or throw only after already writing/reading data.
 *
 * Known limitation: the admin-still-works path (a real admin's request is NOT blocked by these
 * new checks) is not covered here - it requires the 'sysop' admin test account's password
 * (EGW_ADMIN_PASSWORD env var, generated per-install, see doc/ai/testing.md), which was not
 * available in this environment. The added checks were verified by code review to mirror the
 * already-admin-gated sibling methods in each class (eg. admin_customfields::ajax_delete_type(),
 * admin_accesslog::index()), but that has not been exercised live against a real admin session.
 */
class SecurityHardeningTest extends LoggedInTest
{
	/**
	 * admin_customfields::index() saves/deletes custom-field definitions (create_content_type(),
	 * delete_content_type(), update(), nm-triggered delete) with no check of its own before the
	 * fix - only the sibling ajax_delete_type() was guarded.
	 */
	public function testCustomfieldsIndexBlocksNonAdmin()
	{
		$this->expectException(Api\Exception\NoPermission::class);

		(new \admin_customfields('addressbook'))->index(['appname' => 'addressbook']);
	}

	/**
	 * Groups::edit()'s save/apply branch called admin_cmd_edit_group/admin_cmd_account_app
	 * (creating/editing the group) before the checkAdminDeny() calls further down the method,
	 * which only ever set post-render $readonlys button flags for the NEXT redraw - a crafted
	 * direct POST bypassed authorization entirely for the actual save.
	 */
	public function testGroupsEditSaveBlocksNonAdmin()
	{
		$this->expectException(Api\Exception\NoPermission\Admin::class);

		(new Groups())->edit([
			'account_id' => 0,
			'button' => ['save' => true],
		]);
	}

	/**
	 * admin_accesslog::get_rows() is called directly by the nextmatch widget's own ajax
	 * menuaction (admin.admin_accesslog.get_rows), bypassing index()'s checkAdminDeny() gate
	 * entirely - session/access-log data was readable (and, via the nm 'action' path, session
	 * data killable) with no check of its own.
	 */
	public function testAccesslogGetRowsBlocksNonAdmin()
	{
		$this->expectException(Api\Exception\NoPermission\Admin::class);

		$rows = $readonlys = [];
		(new \admin_accesslog())->get_rows(['session_list' => false], $rows, $readonlys);
	}
}
