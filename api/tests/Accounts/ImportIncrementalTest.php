<?php
/**
 * EGroupware API - Api\Accounts\Import: incremental sync (Phase 3)
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests;

require_once __DIR__.'/ImportTestCase.php';

use EGroupware\Api;
use EGroupware\Api\Accounts\Tests\Fixtures\FakeAdsAccountsBackend;

/**
 * Covers doc/ai/projects/accounts-import-test-coverage.md's Phase 3 test-case plan item 5
 * (incremental: only-changed entries processed, delete forced off, AD's always-full-groups
 * override). testIncrementalWithoutLastrunThrows (the "first incremental run ever" error path)
 * is already covered by ImportInitialUsersTest, not repeated here.
 *
 * Setup strategy: same ImportTestCase::buildImport() harness as Phase 1/2. account_import_lastrun
 * is set explicitly per test (via setImportConfig(), which only touches
 * $GLOBALS['egw_info']['server'] - never the real DB config row, since save_state: false keeps
 * run() from persisting anything there). Contact/group "modified" timestamps are set relative to
 * that lastrun value to control which fixture rows the incremental filter should and shouldn't
 * pick up - see FakeContactsSource::search()/FakeLdapAccountsBackend::search()'s modified-filter
 * handling.
 */
class ImportIncrementalTest extends ImportTestCase
{
	/**
	 * A contact whose "modified" predates account_import_lastrun must be excluded by the
	 * incremental filter (never even reach the per-account loop); one whose "modified" is at or
	 * after lastrun must be processed - Import::run() line ~272's
	 * `$filter[] = 'modified>='.$lastrun`, applied by FakeContactsSource::search().
	 */
	public function testIncrementalOnlySyncsChangedContacts() : void
	{
		$lastrun = time() - 3600;

		$unchanged_lid = 'import_test_unchanged_'.substr(md5(random_bytes(8)), 0, 8);
		$unchanged_id = random_int(950000, 999999);
		$changed_lid = 'import_test_changed_'.substr(md5(random_bytes(8)), 0, 8);
		$changed_id = random_int(950000, 999999);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'no',
			'account_import_lastrun' => $lastrun,
		]);

		$import = $this->buildImport(
			accounts: [
				$unchanged_id => [
					'account_id' => $unchanged_id,
					'account_lid' => $unchanged_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Unchanged',
					'account_lastname' => 'Account',
				],
				$changed_id => [
					'account_id' => $changed_id,
					'account_lid' => $changed_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Changed',
					'account_lastname' => 'Account',
				],
			],
			contacts: [
				[
					'account_id' => $unchanged_id,
					'modified' => $lastrun - 100,    // BEFORE lastrun - must be filtered out
					'jpegphoto' => null,
					'n_given' => 'Unchanged',
					'n_family' => 'Account',
				],
				[
					'account_id' => $changed_id,
					'modified' => $lastrun + 100,    // AFTER lastrun - must be processed
					'jpegphoto' => null,
					'n_given' => 'Changed',
					'n_family' => 'Account',
				],
			],
		);

		$result = $import->run(false, save_state: false);

		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));
		$this->assertSame(1, $result['created'], 'Only the "changed" account should have been processed/created');

		$created_id = $this->realAccountId($changed_lid);
		$this->assertNotEmpty($created_id, 'The "changed" account should have been created');
		$this->deleteAfterTest($created_id);

		$this->assertNull($this->realAccountId($unchanged_lid),
			'The "unchanged" account (modified before lastrun) must not have been created - it was never even seen this run');
	}

	/**
	 * Incremental runs (initial_import=false) can NEVER delete/deactivate, regardless of
	 * account_import_delete - Import::run() line ~218-221 forces $delete = 'no' whenever
	 * !$initial_import. Since deletion is structurally disabled here (not just dry-run-suppressed),
	 * this is safe to run for real (non-dry-run) against the shared DB - the deletion query block
	 * itself never executes.
	 */
	public function testIncrementalNeverDeletesEvenWhenConfigured() : void
	{
		$accounts_sql = (new Api\Accounts('sql'))->backend;

		$gone_lid = 'import_test_incr_gone_'.substr(md5(random_bytes(8)), 0, 8);
		$gone_data = ['account_lid' => $gone_lid, 'account_type' => 'u', 'account_status' => 'A'];
		$gone_id = $accounts_sql->save($gone_data, true);
		$this->assertNotEmpty($gone_id, 'Pre-condition: could not create the "gone" account');
		$this->deleteAfterTest($gone_id);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'yes',    // configured on, but must be structurally ignored
			'account_import_lastrun' => time() - 3600,
		]);

		$import = $this->buildImport();    // empty fake source - "gone" is absent this run too

		$result = $import->run(false, save_state: false);

		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));
		$this->assertSame(0, $result['deleted'], 'Incremental runs must never report a deletion candidate');
		foreach ($this->loggedMessages as $message)
		{
			$this->assertStringNotContainsString('no longer existing user', $message,
				'Incremental runs must never even compute/log deletion candidates: '.$message);
		}
		$this->assertNotNull($this->realAccountId($gone_lid), 'The account must genuinely still exist - untouched');
	}

	/**
	 * account_import_source=ads always does a FULL group sync, even on an incremental run -
	 * Import::run() line ~227's `$initial_import || $source === 'ads' ? null : lastrun` - because
	 * (per the code comment there) AD doesn't reliably update a group's modification timestamp
	 * when only membership changes. A group whose fixture "modified" predates lastrun (which a
	 * plain modified-filter would exclude) must still be picked up and updated for an Ads source.
	 */
	public function testAdsGroupsAlwaysFullSyncOnIncremental() : void
	{
		$accounts_sql = (new Api\Accounts('sql'))->backend;
		$lastrun = time() - 3600;

		$group_lid = 'import_test_grp_stale_'.substr(md5(random_bytes(8)), 0, 8);
		$existing_group = ['account_lid' => $group_lid, 'account_type' => 'g', 'account_description' => 'Old description'];
		$existing_group_id = $accounts_sql->save($existing_group, true);
		$this->assertNotEmpty($existing_group_id, 'Pre-condition: could not create the existing group');
		$this->deleteAfterTest($existing_group_id);

		$source_group_id = -random_int(700000, 749999);
		$fixture_accounts = [
			$source_group_id => [
				'account_id' => $source_group_id,
				'account_lid' => $group_lid,
				'account_type' => 'g',
				'account_description' => 'New description',
				'account_modified' => $lastrun - 1000,    // STALE - would be filtered out by a plain modified check
				'account_dn' => 'cn='.$group_lid.',ou=groups,dc=example,dc=org',
			],
		];

		$this->setImportConfig([
			'account_import_source' => 'ads',
			'account_import_type' => 'users+groups',
			'account_import_delete' => 'no',
			'account_import_lastrun' => $lastrun,
		]);

		$import = $this->buildImport(
			accounts: $fixture_accounts,
			sourceBackend: new FakeAdsAccountsBackend($fixture_accounts),
		);

		$result = $import->run(false, save_state: false);

		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));
		$this->assertSame(1, $result['updated'],
			'The stale-modified group must still be updated for an Ads source on an incremental run: '.implode("\n", $this->loggedMessages));
		$this->assertSame('New description', $accounts_sql->read($existing_group_id)['account_description']);
	}

	/**
	 * Contrast case for the above: a non-Ads source DOES respect the per-group modified filter on
	 * an incremental run - the same stale-"modified" group must NOT be picked up/updated when
	 * account_import_source=ldap.
	 */
	public function testLdapGroupsRespectModifiedFilterOnIncremental() : void
	{
		$accounts_sql = (new Api\Accounts('sql'))->backend;
		$lastrun = time() - 3600;

		$group_lid = 'import_test_grp_stale_'.substr(md5(random_bytes(8)), 0, 8);
		$existing_group = ['account_lid' => $group_lid, 'account_type' => 'g', 'account_description' => 'Old description'];
		$existing_group_id = $accounts_sql->save($existing_group, true);
		$this->assertNotEmpty($existing_group_id, 'Pre-condition: could not create the existing group');
		$this->deleteAfterTest($existing_group_id);

		$source_group_id = -random_int(700000, 749999);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users+groups',
			'account_import_delete' => 'no',
			'account_import_lastrun' => $lastrun,
		]);

		$import = $this->buildImport(
			accounts: [
				$source_group_id => [
					'account_id' => $source_group_id,
					'account_lid' => $group_lid,
					'account_type' => 'g',
					'account_description' => 'New description',
					'account_modified' => $lastrun - 1000,    // STALE - filtered out by FakeLdapAccountsBackend::search()
				],
			],
		);

		$result = $import->run(false, save_state: false);

		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));
		$this->assertSame(0, $result['updated'],
			'A stale-modified group must NOT be picked up for a non-Ads source on an incremental run: '.implode("\n", $this->loggedMessages));
		$this->assertSame('Old description', $accounts_sql->read($existing_group_id)['account_description'],
			'The group must remain untouched');
	}
}
