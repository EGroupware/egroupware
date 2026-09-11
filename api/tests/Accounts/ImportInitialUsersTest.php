<?php
/**
 * EGroupware API - Api\Accounts\Import: config validation + initial users-only import
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests;

require_once __DIR__.'/ImportTestCase.php';

use EGroupware\Api;

/**
 * Covers the first slice of doc/ai/projects/accounts-import-test-coverage.md's test-case plan
 * (item 1: config-validation error paths; item 2: initial import, users only).
 *
 * Setup strategy: Api\Accounts\Import::run() is called against a real Import instance built by
 * ImportTestCase::buildImport() - fixture-backed FakeLdapAccountsBackend/FakeContactsSource
 * stand in for the LDAP source, real Accounts\Sql/Contacts\Sql (against the test database) are
 * the destination. No live LDAP/ADS server involved - see ImportTestCase's docblock.
 *
 * Pass criteria: for the create case, the account/contact end up in the real (test) database
 * with the expected fields, and run()'s returned counters match; for the validation cases,
 * run() throws \InvalidArgumentException with no database change at all.
 */
class ImportInitialUsersTest extends ImportTestCase
{
	/**
	 * account_import_source must be one of ldap/ads/univention - anything else must fail fast,
	 * before touching any backend, and must not depend on the fixtures at all.
	 */
	public function testInvalidSourceThrows() : void
	{
		$this->setImportConfig([
			'account_import_source' => 'not-a-real-source',
			'account_import_type' => 'users',
			'account_import_delete' => 'no',
		]);
		$import = $this->buildImport();

		$this->expectException(\InvalidArgumentException::class);
		$import->run(true, save_state: false);
	}

	/**
	 * account_import_type must be one of users/users+groups/users+local+groups.
	 */
	public function testInvalidTypeThrows() : void
	{
		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'not-a-real-type',
			'account_import_delete' => 'no',
		]);
		$import = $this->buildImport();

		$this->expectException(\InvalidArgumentException::class);
		$import->run(true, save_state: false);
	}

	/**
	 * An incremental run (initial_import=false) requires account_import_lastrun to already be
	 * set from a prior initial run - Import::run() throws before touching any backend otherwise
	 * (api/src/Accounts/Import.php line ~205-208).
	 */
	public function testIncrementalWithoutLastrunThrows() : void
	{
		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'no',
			'account_import_lastrun' => null,
		]);
		$import = $this->buildImport();

		$this->expectException(\InvalidArgumentException::class);
		$import->run(false, save_state: false);
	}

	/**
	 * A brand-new account present in the (fake) LDAP source and absent from SQL must be created
	 * for-real in the test database, with its primary group set to the configured default group
	 * ("Default", per default_group_lid's own default) since account_import_type is "users" only.
	 */
	public function testCreatesNewUserFromSource() : void
	{
		$account_lid = 'import_test_'.substr(md5(random_bytes(8)), 0, 8);
		$account_id = random_int(950000, 999999);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'no',
			'account_import_aliases' => false,
			'account_import_dn_regexp' => '',
		]);

		$import = $this->buildImport(
			accounts: [
				$account_id => [
					'account_id' => $account_id,
					'account_lid' => $account_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Import',
					'account_lastname' => 'Test',
					'account_fullname' => 'Import Test',
					'account_email' => $account_lid.'@example.org',
					'account_uuid' => 'fixture-uuid-'.$account_id,
					'account_dn' => 'uid='.$account_lid.',ou=people,dc=example,dc=org',
				],
			],
			contacts: [
				[
					'account_id' => $account_id,
					'modified' => time(),
					'jpegphoto' => null,
					'dn' => 'uid='.$account_lid.',ou=people,dc=example,dc=org',
					'n_given' => 'Import',
					'n_family' => 'Test',
					'n_fn' => 'Import Test',
					// 'email' is Contacts\Sql's app-facing field name for this contact row (also
					// what Import::aliasImport() reads) - Accounts\Sql::read()'s "contact_email
					// AS account_email" is a raw-SQL column alias in a hand-written JOIN, a
					// different, lower-level name for the same underlying DB column; using
					// 'contact_email' as a fixture key here was a bug (a first attempt at this
					// test did exactly that) - Contacts\Sql::save() silently ignores unknown
					// field names, and Import's own diff logic then saw it as a permanent phantom
					// difference (present in the fixture, never in the saved row) on every rerun.
					'email' => $account_lid.'@example.org',
				],
			],
		);

		$result = $import->run(true, save_state: false);

		// find the real, DB-assigned account_id by login-id via a direct DB query (the fixture's
		// numeric id may collide with an existing local account and get offset by Import itself -
		// see CONFLICT_OFFSET; and $GLOBALS['egw']->accounts's session cache can't be trusted to
		// know about a row a backend-level write just created - see realAccountId()'s docblock)
		$created_account_id = $this->realAccountId($account_lid);
		if ($created_account_id)
		{
			$this->deleteAfterTest($created_account_id);
		}

		$this->assertNotEmpty($created_account_id, 'New account was not created in SQL: '.implode("\n", $this->loggedMessages));
		$this->assertSame(1, $result['created'], 'run() should report exactly 1 created account');
		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));

		$saved = $this->realAccountRead($created_account_id);
		$this->assertSame($account_lid, $saved['account_lid']);
		$this->assertSame('Import', $saved['account_firstname']);
		$this->assertSame('Test', $saved['account_lastname']);
		$this->assertSame($account_lid.'@example.org', $saved['account_email']);

		$default_group_id = (new Api\Accounts\Sql())->name2id('Default', 'account_lid', 'g');
		$memberships = (new Api\Accounts\Sql())->memberships($created_account_id);
		$this->assertArrayHasKey($default_group_id, $memberships,
			'New user should have been added to the configured default_group_lid ("Default")');
	}

	/**
	 * A second run against an unchanged source must not write anything and must report the
	 * account as up-to-date, not created/updated.
	 */
	public function testUnchangedAccountIsUpToDateOnRerun() : void
	{
		$account_lid = 'import_test_'.substr(md5(random_bytes(8)), 0, 8);
		$account_id = random_int(950000, 999999);
		$fixture_account = [
			'account_id' => $account_id,
			'account_lid' => $account_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Import',
			'account_lastname' => 'Rerun',
			'account_fullname' => 'Import Rerun',
			'account_email' => $account_lid.'@example.org',
			'account_uuid' => 'fixture-uuid-'.$account_id,
		];
		$fixture_contact = [
			'account_id' => $account_id,
			'modified' => time(),
			'jpegphoto' => null,
			'n_given' => 'Import',
			'n_family' => 'Rerun',
			'n_fn' => 'Import Rerun',
			'email' => $account_lid.'@example.org',    // see the fixture-shape note in testCreatesNewUserFromSource()
		];

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'no',
			// deliberately cleared, not left at whatever this dev box's real LDAP/ADS config
			// happens to have configured - our fixture contact carries no "dn" at all, so a
			// real (non-empty) regexp would silently preg_match(..., null) and skip it
			'account_import_dn_regexp' => '',
		]);

		// first run: creates the account
		$import1 = $this->buildImport([$account_id => $fixture_account], [$fixture_contact]);
		$import1->run(true, save_state: false);
		$created_account_id = $this->realAccountId($account_lid);
		$this->assertNotEmpty($created_account_id, 'Pre-condition: first run must create the account');
		$this->deleteAfterTest($created_account_id);

		// second run against the SAME unchanged fixture data: must be a no-op
		$import2 = $this->buildImport([$account_id => $fixture_account], [$fixture_contact]);
		$result = $import2->run(true, save_state: false);

		$this->assertSame(0, $result['created'], 'Rerun should not create anything: '.implode("\n", $this->loggedMessages));
		$this->assertSame(0, $result['updated'], 'Rerun should not update anything: '.implode("\n", $this->loggedMessages));
		$this->assertSame(1, $result['uptodate'], 'Rerun should report the account as up-to-date');
		$this->assertSame(0, $result['errors']);
	}
}
