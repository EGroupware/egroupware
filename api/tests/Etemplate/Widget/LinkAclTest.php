<?php
/**
 * EGroupware - Test ACL enforcement of the link-widget's ajax_* methods
 *
 * Regression tests for GHSA-hgrx-j8rv-2m36: Etemplate\Widget\Link::ajax_delete (and the sibling
 * ajax_link / ajax_link_comment methods) used to perform NO ACL check at all, allowing any
 * authenticated user to create, comment on or delete arbitrary entry-links.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once __DIR__ . '/../../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate\Request;

class LinkAclTest extends LoggedInTest
{
	/**
	 * @var int[] infolog info_id's to clean up
	 */
	protected $entries = [];

	/**
	 * @var int|null id of 2nd (unprivileged) test-user
	 */
	protected $account_id;

	/**
	 * @var int|null resources category created for the file_access hook tests
	 */
	protected $cat_id;

	/**
	 * @var int|null resource created for the file_access hook tests
	 */
	protected $resource_id;

	/**
	 * @var int|null bookmark created for the file_access hook tests
	 */
	protected $bookmark_id;

	protected $account = [
		'account_lid' => 'link_acl_test_user',
		'account_firstname' => 'Link',
		'account_lastname' => 'AclTest',
		'account_passwd' => 'passw0rd',
		'account_passwd_2' => 'passw0rd',
		// Don't let them in Default, any set ACLs there would interfere with the tests
		'account_primary_group' => 'Testers',
		'account_groups' => ['Testers'],
	];

	protected function tearDown() : void
	{
		// Make sure we're back to the original user, a failure could leave us logged in as someone else
		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);

		// resources_acl_bo::$permissions/$resource_acl are process-wide statics computed lazily for
		// whoever is "current user" and never re-checked once set - if a resources sub-test below left
		// them populated for the unprivileged test-user, they'd silently apply to every OTHER app/test
		// still to run in this phpunit process (eg. calendar\ResetParticipantStatusTest, which also
		// calls resources_bo::checkUseable() and got a stale "denied" cat_id result from this class
		// leaking into it). Always invalidate after switching back, not just in the two tests that
		// touch resources directly.
		\resources_acl_bo::invalidate_cache();

		// ajax_delete/ajax_link/ajax_link_comment each call Response::get()->data(), but it's a singleton
		// that only accepts one data-response - reset it, or the next test's call throws (same pattern
		// as api/tests/Etemplate/WidgetBaseTest.php)
		$ref = new \ReflectionProperty('\\EGroupware\\Api\\Json\\Response', 'response');
		$ref->setAccessible(true);
		$ref->setValue(null, null);

		$bo = new \infolog_bo();
		foreach($this->entries as $info_id)
		{
			$bo->delete($info_id, false, true);
		}
		$this->entries = [];

		if ($this->resource_id)
		{
			(new \resources_so())->delete(['res_id' => $this->resource_id]);
			$this->resource_id = null;
		}
		if ($this->bookmark_id)
		{
			(new \bookmarks_so())->delete($this->bookmark_id);
			$this->bookmark_id = null;
		}
		if ($this->cat_id)
		{
			(new Api\Categories($GLOBALS['egw_info']['user']['account_id'], 'resources'))->delete($this->cat_id);
			$this->cat_id = null;
		}
		if ($this->account_id)
		{
			// admin_cmd_delete_account requires the CURRENT session to be a real admin
			$this->switchUser($GLOBALS['EGW_ADMIN_USER'], $GLOBALS['EGW_ADMIN_PASSWORD']);
			$command = new \admin_cmd_delete_account($this->account_id, null, true);
			$command->comment = 'Removing in tearDown for unit test ' . $this->getName();
			$command->run();
			$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
			$this->account_id = null;
		}

		parent::tearDown();
	}

	/**
	 * Create the 2nd, unprivileged test-user (a fresh, "clean" user without any grants)
	 */
	protected function makeUser() : int
	{
		if (($account_id = $GLOBALS['egw']->accounts->name2id($this->account['account_lid'])))
		{
			$GLOBALS['egw']->accounts->delete($account_id);
		}

		// the group should contain the ORIGINAL (non-admin) session's account, not the admin's
		$original_account_id = $GLOBALS['egw_info']['user']['account_id'];

		// admin_cmd_edit_group/_edit_user require the CURRENT session to be a real admin
		$this->switchUser($GLOBALS['EGW_ADMIN_USER'], $GLOBALS['EGW_ADMIN_PASSWORD']);

		if (!$GLOBALS['egw']->accounts->exists($this->account['account_primary_group']))
		{
			$group = new \admin_cmd_edit_group(false, [
				'account_lid' => 'Testers',
				'account_members' => [$original_account_id],
			]);
			$group->run();
		}
		$command = new \admin_cmd_edit_user(false, $this->account);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		$this->account_id = $command->account;

		// don't leave the current (admin) user in the Testers group, it could interfere with other tests
		$remove_group = new \admin_cmd_edit_group('Testers', [
			'account_lid' => 'Testers',
			'account_members' => [$this->account_id],
		]);
		$remove_group->run();

		// restore the base test session (same fallback identity used everywhere else)
		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);

		return $this->account_id;
	}

	protected function makeInfolog(array $extra=[]) : int
	{
		$bo = new \infolog_bo();
		$info_id = $bo->write(array_merge([
			'info_subject' => 'LinkAclTest ' . $this->getName(),
			'info_des' => 'Test entry for ' . $this->getName(),
			'info_status' => 'open',
		], $extra), true, true, true, true);
		$this->entries[] = $info_id;
		return $info_id;
	}

	protected function makeLink(int $id1, int $id2) : int
	{
		$link_id = Api\Link::link('infolog', $id1, 'infolog', $id2);
		$this->assertIsInt($link_id, 'Failed to set up test link');
		return $link_id;
	}

	/**
	 * Grant the 2nd test-user real Acl::EDIT rights on infolog entries owned by the primary
	 * test-user (which is who makeInfolog() creates entries as).
	 *
	 * infolog_bo::check_access() grants EDIT via 'responsible' only if the app is configured
	 * with implicit_rights=edit (default is 'read'), so that's not reliable here - grant it
	 * via the actual ACL grants system instead, which check_access() always honors
	 * ($grants[$owner] & $required_rights).
	 *
	 * Note the account/location order: Acl::get_grants() (what check_access() reads its
	 * $grants from) only picks up rows stored as acl_account=OWNER, acl_location=GRANTEE -
	 * the opposite of Acl::check()/admin_cmd_acl's usual "account=grantee, location=owner"
	 * convention used for e.g. 'run' rights below. Verified empirically, not just from docs.
	 */
	protected function grantInfologEdit() : void
	{
		$command = new \admin_cmd_acl(true, $this->account_id, 'infolog', 'run', Acl::READ);
		$command->run();

		$grant = new \admin_cmd_acl(true, $GLOBALS['egw_info']['user']['account_id'], 'infolog', $this->account_id, Acl::EDIT);
		$grant->run();
	}

	/**
	 * Simulate an etemplate-request that recorded $app/$id as edit-allowed (what
	 * Link::beforeSendToClient() does for a widget rendered non-readonly), without needing
	 * to actually render a template.
	 */
	protected function whitelistExecId(string $app, $id) : string
	{
		$request = Request::read();
		$request->allowLinkEdit($app, $id);
		$exec_id = $request->id();
		unset($request);	// force __destruct() to persist the request data
		return $exec_id;
	}

	public function testAjaxDeleteDeniedWithoutAccess()
	{
		$id1 = $this->makeInfolog();
		$id2 = $this->makeInfolog();
		$link_id = $this->makeLink($id1, $id2);

		$this->makeUser();
		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		try
		{
			Link::ajax_delete($link_id);
			$this->fail('ajax_delete() should have thrown Api\Exception\NoPermission');
		}
		catch (Api\Exception\NoPermission $e)
		{
			// expected
		}

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		$this->assertNotFalse(Api\Link::get_link($link_id), 'Link got deleted despite denied access');
	}

	public function testAjaxDeleteDeniedWithForeignExecId()
	{
		$id1 = $this->makeInfolog();
		$id2 = $this->makeInfolog();
		$other_id = $this->makeInfolog();
		$link_id = $this->makeLink($id1, $id2);

		$this->makeUser();
		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		// exec_id whitelists a DIFFERENT, unrelated entry - must NOT grant access to our link
		$exec_id = $this->whitelistExecId('infolog', $other_id);

		try
		{
			Link::ajax_delete($link_id, $exec_id);
			$this->fail('ajax_delete() should have thrown Api\Exception\NoPermission');
		}
		catch (Api\Exception\NoPermission $e)
		{
			// expected
		}

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		$this->assertNotFalse(Api\Link::get_link($link_id), 'Link got deleted despite a foreign exec_id');
	}

	public function testAjaxDeleteAllowedViaRealEditRights()
	{
		$id1 = $this->makeInfolog();
		$id2 = $this->makeInfolog();
		$link_id = $this->makeLink($id1, $id2);

		$this->makeUser();
		$this->grantInfologEdit();

		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		// no exec_id at all: must fall back to a real Api\Link::file_access() check
		Link::ajax_delete($link_id);

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		$this->assertFalse(Api\Link::get_link($link_id), 'Link was NOT deleted despite real edit-rights');
	}

	public function testAjaxDeleteAllowedViaWhitelistedExecId()
	{
		$id1 = $this->makeInfolog();
		$id2 = $this->makeInfolog();
		$link_id = $this->makeLink($id1, $id2);

		$this->makeUser();
		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		// test-user has NO real edit-rights on either entry, only the whitelist from a
		// (simulated) non-readonly render of the link-widget for id1
		$exec_id = $this->whitelistExecId('infolog', $id1);

		Link::ajax_delete($link_id, $exec_id);

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		$this->assertFalse(Api\Link::get_link($link_id), 'Link was NOT deleted despite whitelisted exec_id');
	}

	public function testAjaxLinkDeniedWithoutAccess()
	{
		$id1 = $this->makeInfolog();
		$id2 = $this->makeInfolog();

		$this->makeUser();
		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		try
		{
			Link::ajax_link('infolog', $id1, [['app' => 'infolog', 'id' => $id2]]);
			$this->fail('ajax_link() should have thrown Api\Exception\NoPermission');
		}
		catch (Api\Exception\NoPermission $e)
		{
			// expected
		}

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		$this->assertEmpty(Api\Link::get_links('infolog', $id1, 'infolog'), 'Link got created despite denied access');
	}

	public function testAjaxLinkAllowedViaWhitelistedExecId()
	{
		$id1 = $this->makeInfolog();
		$id2 = $this->makeInfolog();

		$this->makeUser();
		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		$exec_id = $this->whitelistExecId('infolog', $id1);

		Link::ajax_link('infolog', $id1, [['app' => 'infolog', 'id' => $id2]], $exec_id);

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		$this->assertNotEmpty(Api\Link::get_links('infolog', $id1, 'infolog'), 'Link was NOT created despite whitelisted exec_id');
	}

	/**
	 * Attaching a file / linking an entry to a NOT YET SAVED entry (eg. dropping a file on the
	 * link-widget while filling in an "add" form) must NOT require edit-rights: there's no real
	 * id yet to check rights on, and Api\Link::link() itself only ever accumulates the pending
	 * link into the (by-reference) $id array in that case - nothing gets written to the DB until
	 * the entry is actually saved and the app processes the accumulated links afterwards.
	 *
	 * Regression test for a fix breaking exactly this: the app widget typically just omits
	 * 'to_id' from a new entry's initial content rather than pre-seeding an empty array, so the
	 * client can send id=null/''/0 just as often as id=[] - ajax_link() must treat all of those
	 * the same (matching Api\Link::link()'s own "is_array($id1) || !$id1" check, api/src/Link.php).
	 */
	public function testAjaxLinkAllowedForNotYetSavedEntry()
	{
		$id2 = $this->makeInfolog();

		$this->makeUser();
		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		// No exec_id, no grant at all - must still be allowed for every "not yet saved" shape
		foreach ([null, '', 0, []] as $id1)
		{
			Link::ajax_link('infolog', $id1, [['app' => 'infolog', 'id' => $id2]]);

			$response = Api\Json\Response::get()->initResponseArray();
			$data = null;
			foreach ($response as $item)
			{
				if ($item['type'] === 'data') $data = $item['data'];
			}
			$this->assertIsArray($data,
				'ajax_link() should return the accumulated pending-links array for id=' . var_export($id1, true));
		}

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);

		// None of the above may actually have written a link to the DB
		$this->assertEmpty(Api\Link::get_links('infolog', $id2, 'infolog'),
			'Link got created despite $id being for a not-yet-saved entry');
	}

	public function testAjaxLinkCommentDeniedWithoutAccess()
	{
		$id1 = $this->makeInfolog();
		$id2 = $this->makeInfolog();
		$link_id = $this->makeLink($id1, $id2);

		$this->makeUser();
		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		try
		{
			Link::ajax_link_comment($link_id, 'hacked comment');
			$this->fail('ajax_link_comment() should have thrown Api\Exception\NoPermission');
		}
		catch (Api\Exception\NoPermission $e)
		{
			// expected
		}

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		$link = Api\Link::get_link($link_id);
		$this->assertNotSame('hacked comment', $link['link_remark'] ?? null, 'Comment got changed despite denied access');
	}

	public function testAjaxLinkCommentAllowedViaWhitelistedExecId()
	{
		$id1 = $this->makeInfolog();
		$id2 = $this->makeInfolog();
		$link_id = $this->makeLink($id1, $id2);

		$this->makeUser();
		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		$exec_id = $this->whitelistExecId('infolog', $id1);

		Link::ajax_link_comment($link_id, 'legit comment', $exec_id);

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		$link = Api\Link::get_link($link_id);
		$this->assertSame('legit comment', $link['link_remark'] ?? null, 'Comment was NOT changed despite whitelisted exec_id');
	}

	/**
	 * Api\Link::file_access() must only grant Acl::READ, never Acl::EDIT, from its title()-based
	 * fallback for apps not implementing a 'file_access' hook.
	 */
	public function testFileAccessFallbackGrantsReadOnly()
	{
		Api\Link::$app_register['linkacltest_fallback'] = [
			'title' => static function($id) { return $id ? "Test $id" : null; },
		];
		try
		{
			$this->assertTrue(Api\Link::file_access('linkacltest_fallback', 1, Acl::READ),
				'title() succeeds, Acl::READ should be granted');
			$this->assertFalse(Api\Link::file_access('linkacltest_fallback', 1, Acl::EDIT),
				'no file_access hook: Acl::EDIT must NOT be granted just because title() succeeded');
		}
		finally
		{
			unset(Api\Link::$app_register['linkacltest_fallback']);
		}
	}

	public function testResourcesFileAccessHookDeniesWithoutGrant()
	{
		$cats = new Api\Categories($GLOBALS['egw_info']['user']['account_id'], 'resources');
		$this->cat_id = $cats->add(['name' => 'LinkAclTest ' . $this->getName()]);
		$this->resource_id = (new \resources_so())->save(['name' => 'Test resource', 'cat_id' => $this->cat_id]);

		$this->makeUser();
		(new \admin_cmd_acl(true, $this->account_id, 'resources', 'run', Acl::READ))->run();
		// deliberately NOT granting any category-ACL

		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);
		// resources_acl_bo caches ALL of the current user's category rights on first use and never
		// refreshes them - must be cleared explicitly after switching user within the same process
		\resources_acl_bo::invalidate_cache();

		$bo = new \resources_bo();
		$this->assertFalse($bo->file_access($this->resource_id, Acl::EDIT),
			'Without any category grant, resources file_access() must deny Acl::EDIT');

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
	}

	public function testResourcesFileAccessHookAllowsWithGrant()
	{
		$cats = new Api\Categories($GLOBALS['egw_info']['user']['account_id'], 'resources');
		$this->cat_id = $cats->add(['name' => 'LinkAclTest ' . $this->getName()]);
		$this->resource_id = (new \resources_so())->save(['name' => 'Test resource', 'cat_id' => $this->cat_id]);

		$this->makeUser();
		(new \admin_cmd_acl(true, $this->account_id, 'resources', 'run', Acl::READ))->run();
		(new \admin_cmd_acl(true, $this->account_id, 'resources', 'L' . $this->cat_id, Acl::EDIT))->run();

		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);
		// resources_acl_bo caches ALL of the current user's category rights on first use and never
		// refreshes them - must be cleared explicitly after switching user within the same process
		\resources_acl_bo::invalidate_cache();

		$bo = new \resources_bo();
		$this->assertTrue($bo->file_access($this->resource_id, Acl::EDIT),
			'Category Acl::EDIT grant should give resources file_access() Acl::EDIT');

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
	}

	/**
	 * bookmarks now has a 'file_access' hook (bookmarks_bo::check_perms) - part of the same fix
	 * as GHSA-hgrx-j8rv-2m36, since bookmarks is the only hookless app that uses <et2-link-to>
	 * on itself (to_app=bookmarks), so without a real hook it fell back to the tightened
	 * READ-only Api\Link::file_access() fallback and could never grant Acl::EDIT.
	 */
	public function testBookmarksFileAccessHookDeniesWithoutGrant()
	{
		$this->bookmark_id = (new \bookmarks_so())->add([
			'name' => 'LinkAclTest ' . $this->getName(),
			'url' => 'http://example.com/test',
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'access' => 'private',
		]);

		$this->makeUser();
		(new \admin_cmd_acl(true, $this->account_id, 'bookmarks', 'run', Acl::READ))->run();
		// deliberately NOT granting any Acl::EDIT

		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		$this->assertFalse(Api\Link::file_access('bookmarks', $this->bookmark_id, Acl::EDIT),
			'Without any grant, bookmarks file_access() must deny Acl::EDIT on a private bookmark owned by someone else');

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
	}

	public function testBookmarksFileAccessHookAllowsWithGrant()
	{
		$this->bookmark_id = (new \bookmarks_so())->add([
			'name' => 'LinkAclTest ' . $this->getName(),
			'url' => 'http://example.com/test',
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'access' => 'private',
		]);

		$this->makeUser();
		(new \admin_cmd_acl(true, $this->account_id, 'bookmarks', 'run', Acl::READ))->run();
		(new \admin_cmd_acl(true, $GLOBALS['egw_info']['user']['account_id'], 'bookmarks', $this->account_id, Acl::EDIT))->run();

		$this->switchUser($this->account['account_lid'], $this->account['account_passwd']);

		$this->assertTrue(Api\Link::file_access('bookmarks', $this->bookmark_id, Acl::EDIT),
			'Acl::EDIT grant from the owner should give bookmarks file_access() Acl::EDIT');

		$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
	}
}
