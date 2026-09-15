<?php
/**
 * EGroupware Api: tests for Api\Contacts / Api\Contacts\Storage
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__.'/../LoggedInTest.php';

class ContactsTest extends LoggedInTest
{
	/**
	 * @var int account_id marked 'hidden' for the duration of this test class
	 */
	private static $hidden_account_id;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		self::$hidden_account_id = $GLOBALS['egw']->accounts->name2id($GLOBALS['EGW_ADMIN_USER']);
		self::asAdminStatic(function()
		{
			$GLOBALS['egw']->acl->add_repository('phpgwapi', 'hidden', self::$hidden_account_id, 1);
		});
	}

	public static function tearDownAfterClass() : void
	{
		// Remove the 'hidden' ACL fixture before the base class tears the session down - it
		// mutates instance-wide ACL data that must not leak into later test classes sharing this
		// PHPUnit process.
		$hidden_account_id = self::$hidden_account_id;
		self::asAdminStatic(function() use ($hidden_account_id)
		{
			$GLOBALS['egw']->acl->delete_repository('phpgwapi', 'hidden', $hidden_account_id);
		});

		parent::tearDownAfterClass();
	}

	/**
	 * Regression test for a bug in Contacts\Storage::search() (api/src/Contacts/Storage.php): a
	 * caller-supplied filter=>['account_id'=>null] means "only contacts NOT linked to any account" -
	 * used eg. by calendar_owner_etemplate_widget::ajax_search() to avoid listing the same person
	 * twice, once via their account_id and once via their addressbook contact_id. The "hide hidden
	 * accounts" step for non-admins used `$filter['account_id'] ?? null`, which cannot distinguish "no
	 * account_id filter given at all" from "account_id explicitly filtered to null" - both read as
	 * null. So whenever ANY account anywhere on the instance was marked 'hidden' (phpgwapi ACL),
	 * Accounts::hidden2account_id() silently replaced the caller's null filter with "exclude only the
	 * hidden accounts", letting every OTHER account-linked contact back into results.
	 *
	 * Setup: LoggedInTest logs in as the non-admin 'demo' user by default (required - the buggy branch
	 * only runs for non-admins). setUpBeforeClass() marks the admin test account (sysop, a DIFFERENT
	 * account from demo) 'hidden' via direct Acl::add_repository() calls, solely to make
	 * Accounts::hidden2account_id(false, null) return a non-empty array - the override is a no-op when
	 * no account anywhere is hidden, so without this fixture the bug cannot reproduce. sysop is
	 * unmarked again in tearDownAfterClass() so the fixture doesn't leak into later test classes
	 * sharing this PHPUnit process.
	 *
	 * Pass criteria: searching contacts for demo's own account-linked contact_id (found via
	 * Accounts::id2name(..., 'person_id')) with filter=>['account_id'=>null] must return zero rows -
	 * demo's own contact must never resurface via this filter, regardless of some unrelated account
	 * (sysop) being hidden. Before the fix this returned demo's contact (SQL effectively became
	 * "account_id IS NULL OR account_id NOT IN (sysop_id)", which demo's real account_id satisfies).
	 */
	public function testAccountIdNullFilterExcludesAccountContactEvenWithUnrelatedHiddenAccount()
	{
		$demo_account_id = $GLOBALS['egw_info']['user']['account_id'];
		$demo_contact_id = $GLOBALS['egw']->accounts->id2name($demo_account_id, 'person_id');
		$this->assertNotEmpty($demo_contact_id,
			'Test precondition failed: demo has no linked addressbook contact_id to search for');

		$contacts = new Contacts();
		$rows = $contacts->search(['contact_id' => $demo_contact_id], false, '', '', '', false, 'AND', false,
			['account_id' => null]);

		$this->assertEmpty($rows,
			"filter=>['account_id'=>null] must exclude demo's own account-linked contact ".
			"(contact_id=$demo_contact_id), even though an unrelated account (sysop) is marked 'hidden'");
	}

	/**
	 * Regression test: Contacts\Sql::organisations() with $param['org_view']=='org_name,adr_one_locality'
	 * (or 'org_name,org_unit') issues TWO consecutive parent::search() calls with a server-generated
	 * "GROUP BY ... ORDER BY ..." fragment, guarded by setting $this->sanitize_order_by=false once
	 * before both calls. But Storage\Base::search() resets sanitize_order_by back to true again right
	 * after consuming it on EACH call (by design, so a false set by one caller never leaks into an
	 * unrelated later call) - so only the FIRST of the two calls was actually bypassing
	 * sanitizeOrderBy(), and the second one hit its "contains untrusted GROUP BY/HAVING"
	 * \InvalidArgumentException, breaking the addressbook's "Organisations by location"/"by
	 * departments" grouped views entirely.
	 *
	 * Pass criteria: organisations() with org_view='org_name,adr_one_locality' must not throw and must
	 * return an array (possibly empty).
	 */
	public function testOrganisationsGroupedByLocationDoesNotThrow()
	{
		$sql = new Contacts\Sql();
		$rows = $sql->organisations(['org_view' => 'org_name,adr_one_locality', 'sort' => 'ASC',
			'start' => 0, 'num_rows' => 10]);

		$this->assertIsArray($rows === false ? [] : $rows,
			"organisations() with org_view='org_name,adr_one_locality' threw or returned a non-array");
	}

	/**
	 * Regression test that the earlier fix ("harden $by parameter", commit 23099b7d12) for
	 * Contacts\Sql::organisations() is still effective: $param['org_view']'s part after the comma
	 * (client-controlled, eg. via the addressbook nextmatch "grouped_view" state) is used unquoted to
	 * build GROUP BY/ORDER BY SQL fragments, so it is restricted to a hard-coded whitelist
	 * (['org_unit', 'adr_one_locality']) - anything else must be silently ignored (treated as no $by at
	 * all), never reach the database. $param['sort'] is likewise restricted to exactly 'ASC'/'DESC'.
	 *
	 * This guards against the fix in this class for the sanitize_order_by bug (see
	 * testOrganisationsGroupedByLocationDoesNotThrow() above) having reopened that hole - eg. by
	 * bypassing sanitizeOrderBy() more broadly than the narrow, already-whitelisted $by/$sort fragments
	 * it was meant for.
	 *
	 * Pass criteria: organisations() with an injection attempt in org_view/sort must not throw, and the
	 * SQL it actually executes (captured via debug=1 and PHP's error_log) must contain none of the
	 * injected fragments.
	 */
	public function testOrganisationsRejectsInjectedOrgViewAndSort()
	{
		$log_file = tempnam(sys_get_temp_dir(), 'sqllog');
		$prev_error_log = ini_set('error_log', $log_file);

		$sql = new Contacts\Sql();
		$sql->debug = 1;
		try
		{
			$rows = $sql->organisations([
				'org_view' => "org_name,org_unit) UNION SELECT account_lid,account_pw,3,4,5 FROM egw_accounts -- ",
				'sort' => "ASC; DROP TABLE egw_addressbook -- ",
				'start' => 0, 'num_rows' => 10,
			]);
		}
		finally
		{
			ini_set('error_log', $prev_error_log);
		}
		$this->assertIsArray($rows === false ? [] : $rows,
			'organisations() with an injection attempt in org_view/sort threw instead of ignoring it');

		$logged_sql = file_get_contents($log_file);
		unlink($log_file);

		foreach (['UNION', 'account_pw', 'DROP TABLE'] as $needle)
		{
			$this->assertStringNotContainsStringIgnoringCase($needle, $logged_sql,
				"malicious org_view/sort content leaked into the executed SQL: $logged_sql");
		}
	}
}
