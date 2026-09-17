<?php
/**
 * EGroupware Mail: regression test for mail_acl::ajax_folders()'s account_id=0 bypass
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api\LoggedInTest;

/**
 * Regression coverage for GHSA-qchv-95c6-f8pc: mail_acl::ajax_folders() gated admin access with
 * `!empty($_GET['account_id'])`, which treats the literal string "0" the same as "not given"
 * (`!empty('0') === false`) - so a non-admin passing `account_id=0` skipped the admin check
 * entirely. `Mail\Account::read($acc_id, $called_for)` then ALSO treats `$called_for === '0'` as
 * a sentinel that disables its own ownership WHERE-clause, so the request loaded an arbitrary
 * `acc_id` - not just the caller's own.
 *
 * Fix (commit 3e0ca74384, predates the advisory by 9 days, landed for an unrelated functional
 * bug - "Make sure ACL doesn't send empty folder"): `ajax_folders()`/`ajax_setACL()`/
 * `ajax_deleteACL()` now normalize `$_GET['account_id']`/`$content['account_id']` with
 * `!empty($x) ? $x : null` *before* anything else, so the literal "0" never survives to reach
 * either the admin check or Account::read() - both see a clean `null` ("my own mailbox") instead.
 *
 * `Account::read()`'s own `$called_for === '0'` bypass sentinel is still there by design (for
 * trusted internal/CLI callers) - confirmed live that `Account::read($other_acc, '0')` still
 * bypasses ownership for an account the current user doesn't own, while
 * `Account::read($other_acc, null)` correctly throws NotFound. This test exercises the actual
 * request-facing entry point end-to-end, which is what protects against that sentinel being
 * requestable at all.
 *
 * ajax_folders()'s only exit() call is on the success path (after echoing JSON); the denial path
 * this test exercises throws before ever reaching it, so no subprocess/isolation trick is needed.
 */
class AjaxFoldersAccountIdZeroTest extends LoggedInTest
{
	public function testAccountIdZeroDoesNotBypassOwnershipForNonAdmin()
	{
		$this->assertEmpty($GLOBALS['egw_info']['user']['apps']['admin'] ?? null,
			'this test requires the default LoggedInTest session to be a non-admin');

		// an account NOT owned by the current test user (confirmed live: Account::read(42, null)
		// throws NotFound for this session, so a real ownership check is in play here)
		$_GET['acc_id'] = 42;
		$_GET['account_id'] = '0';
		$_GET['query'] = '';

		$this->expectException(\EGroupware\Api\Exception\NotFound::class);

		mail_acl::ajax_folders();
	}
}
