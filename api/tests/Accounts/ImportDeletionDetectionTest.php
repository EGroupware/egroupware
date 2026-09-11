<?php
/**
 * EGroupware API - Api\Accounts\Import: deletion-candidate detection (Phase 2)
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests;

require_once __DIR__.'/ImportTestCase.php';

use EGroupware\Api;

/**
 * Covers doc/ai/projects/accounts-import-test-coverage.md's Phase 2 deletion test-case item -
 * but deliberately does NOT exercise deletion/deactivation actually *happening*.
 *
 * Api\Accounts\Import::run()'s "no longer existing user(s)" query
 * (`$where = ['account_type' => 'u']` [+ 'account_status' => 'A' for deactivate]) is completely
 * unscoped - it reads EVERY real account_type='u' row in the shared egw_accounts table, with no
 * filter tying it to this test's fixtures. On this shared, live dev database, actually letting
 * account_import_delete='yes'/'deactivate' run for real would delete/deactivate every genuine
 * account this test's tiny fake source doesn't happen to mention - unacceptable.
 *
 * Every test here therefore uses dry_run=true, which the same code path only ever *logs* under
 * (`if ($dry_run) { $this->logger("Dry-run: would ..."); ... }` - no admin_cmd_delete_account
 * call, no `account_status` UPDATE) - see api/src/Accounts/Import.php's deletion block. What's
 * verified is narrower and safer than "deletion works": that the *candidate-detection* logic
 * correctly separates "seen in the source this run" from "not seen" - i.e. that accounts genuinely
 * present in the source do NOT show up as delete candidates, and accounts genuinely absent do,
 * incl. the "anonymous" account's dedicated carve-out (Import.php line ~625).
 *
 * Setup strategy: pre-create real SQL-only accounts directly via Accounts\Sql (simulating
 * accounts a prior import run already created) - one whose account_lid IS mirrored in the fake
 * source's contacts/accounts fixtures ("still there"), one that is NOT ("no longer there"). Read
 * the resulting dry-run log message for the deletion block and assert on which login-ids it does
 * and doesn't mention.
 */
class ImportDeletionDetectionTest extends ImportTestCase
{
	public function testSeenAccountExcludedNotSeenAccountIncludedAsDryRunDeleteCandidate() : void
	{
		$accounts_sql = (new Api\Accounts('sql'))->backend;

		// "still there": pre-created in SQL AND present in the fake source this run
		$seen_lid = 'import_test_seen_'.substr(md5(random_bytes(8)), 0, 8);
		$seen_data = [
			'account_lid' => $seen_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Seen',
			'account_lastname' => 'Account',
		];
		$seen_id = $accounts_sql->save($seen_data, true);
		$this->assertNotEmpty($seen_id, 'Pre-condition: could not create the "seen" account');
		$this->deleteAfterTest($seen_id);

		// "no longer there": pre-created in SQL, absent from the fake source this run
		$gone_lid = 'import_test_gone_'.substr(md5(random_bytes(8)), 0, 8);
		$gone_data = [
			'account_lid' => $gone_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Gone',
			'account_lastname' => 'Account',
		];
		$gone_id = $accounts_sql->save($gone_data, true);
		$this->assertNotEmpty($gone_id, 'Pre-condition: could not create the "gone" account');
		$this->deleteAfterTest($gone_id);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'yes',
		]);

		$import = $this->buildImport(
			accounts: [
				$seen_id => [
					'account_id' => $seen_id,
					'account_lid' => $seen_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Seen',
					'account_lastname' => 'Account',
					'account_fullname' => 'Seen Account',
				],
			],
			contacts: [
				[
					'account_id' => $seen_id,
					'modified' => time(),
					'jpegphoto' => null,
					'n_given' => 'Seen',
					'n_family' => 'Account',
					'n_fn' => 'Seen Account',
				],
			],
		);

		$result = $import->run(true, dry_run: true, save_state: false);

		$log = implode("\n", $this->loggedMessages);
		$this->assertMatchesRegularExpression('/Dry-run: would delete \d+ no longer existing user\(s\)/', $log,
			"Expected a dry-run delete-candidates log line: $log");
		$this->assertStringContainsString("$gone_lid (#$gone_id)", $log,
			'The account absent from the source this run must be listed as a delete candidate');
		$this->assertStringNotContainsString("$seen_lid (#$seen_id)", $log,
			'The account present in the source this run must NOT be listed as a delete candidate');
		$this->assertGreaterThanOrEqual(1, $result['deleted'],
			'run() should report at least the "gone" account among the (dry-run) deleted count');

		// dry_run must be a pure no-op: both accounts must still be exactly as they were
		$this->assertNotNull($this->realAccountId($seen_lid), 'dry_run must not actually delete the "seen" account');
		$this->assertNotNull($this->realAccountId($gone_lid), 'dry_run must not actually delete the "gone" account');
		$this->assertSame('A', $this->realAccountRead($gone_id)['account_status'],
			'dry_run must not actually deactivate the "gone" account either');
	}

	/**
	 * The anonymous account is structurally excluded from deletion candidates
	 * (Import::run() line ~625: `$sql_users = array_diff($sql_users, ['anonymous']);`) regardless
	 * of account_import_delete - it's required for EGroupware to function. It will naturally be
	 * "not seen" by an empty-ish fake source (no real install's LDAP export would even carry it),
	 * so this is exactly the scenario that carve-out exists for.
	 */
	public function testAnonymousAccountNeverListedAsDeleteCandidate() : void
	{
		$this->assertNotNull($this->realAccountId('anonymous'),
			'Pre-condition: this install is expected to have the standard "anonymous" account');

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'deactivate',
		]);

		$import = $this->buildImport();    // empty fake source - every real user "looks" gone

		$result = $import->run(true, dry_run: true, save_state: false);

		$log = implode("\n", $this->loggedMessages);
		$this->assertStringNotContainsString('anonymous (#', $log,
			"\"anonymous\" must never appear in the dry-run deactivate-candidates log: $log");
	}

	/**
	 * Same idea as the user-level test above, but for GROUPS - Import::groups()'s own deletion
	 * block, only reached when account_import_type does NOT include "local" (which forces group
	 * deletion off unconditionally, regardless of account_import_delete - see the config matrix in
	 * doc/ai/projects/accounts-import-test-coverage.md).
	 *
	 * This is also a regression test for a real bug found+fixed in this same session: the dry-run
	 * branch of that deletion block logged `$group`/`$sql_id` - stale variables left over from the
	 * PRECEDING (unrelated) foreach loop, not the group actually being considered for deletion -
	 * and incremented `$delete` (the string config value 'yes'/'no'/'deactivate') instead of the
	 * `$deleted` counter. Asserting on the exact log content here would have failed against the
	 * old code (wrong name/id, and 'deleted' staying 0).
	 */
	public function testSeenGroupExcludedNotSeenGroupIncludedAsDryRunDeleteCandidate() : void
	{
		$accounts_sql = (new Api\Accounts('sql'))->backend;

		// "still there": pre-created in SQL AND present in the fake source this run
		$seen_lid = 'import_test_grp_seen_'.substr(md5(random_bytes(8)), 0, 8);
		$seen_data = ['account_lid' => $seen_lid, 'account_type' => 'g'];
		$seen_id = $accounts_sql->save($seen_data, true);
		$this->assertNotEmpty($seen_id, 'Pre-condition: could not create the "seen" group');
		$this->deleteAfterTest($seen_id);

		// "no longer there": pre-created in SQL, absent from the fake source this run
		$gone_lid = 'import_test_grp_gone_'.substr(md5(random_bytes(8)), 0, 8);
		$gone_data = ['account_lid' => $gone_lid, 'account_type' => 'g'];
		$gone_id = $accounts_sql->save($gone_data, true);
		$this->assertNotEmpty($gone_id, 'Pre-condition: could not create the "gone" group');
		$this->deleteAfterTest($gone_id);

		$source_seen_id = -random_int(700000, 749999);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users+groups',    // NOT "local" - group deletion stays enabled
			'account_import_delete' => 'yes',
		]);

		$import = $this->buildImport(
			accounts: [
				$source_seen_id => [
					'account_id' => $source_seen_id,
					'account_lid' => $seen_lid,
					'account_type' => 'g',
				],
			],
		);

		$result = $import->run(true, dry_run: true, save_state: false);

		$log = implode("\n", $this->loggedMessages);
		$this->assertStringContainsString("Dry-run: would delete group '$gone_lid' (#$gone_id)", $log,
			"The group absent from the source this run must be listed as a delete candidate: $log");
		$this->assertStringNotContainsString("would delete group '$seen_lid'", $log,
			'The group present in the source this run must NOT be listed as a delete candidate');
		$this->assertGreaterThanOrEqual(1, $result['deleted'],
			'run() should report at least the "gone" group among the (dry-run) deleted count - '.
			'regression check for the $delete++ (string, not counter) bug');

		// dry_run must be a pure no-op
		$this->assertNotFalse($accounts_sql->read($seen_id), 'dry_run must not actually delete the "seen" group');
		$this->assertNotFalse($accounts_sql->read($gone_id), 'dry_run must not actually delete the "gone" group');
	}
}
