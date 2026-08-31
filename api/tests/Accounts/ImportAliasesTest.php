<?php
/**
 * EGroupware API - Api\Accounts\Import: alias sync (Phase 4)
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests;

require_once __DIR__.'/ImportTestCase.php';

use EGroupware\Api;
use EGroupware\Api\Accounts\Tests\Fixtures\FakeAdsAccountsBackend;

/**
 * Covers doc/ai/projects/accounts-import-test-coverage.md's Phase 4 test-case plan item 7:
 * account_import_aliases add/remove diffing, dry_run, and the $export_ldif mode.
 *
 * Import::aliasImport() (api/src/Accounts/Import.php ~line 729) writes through a real, plain
 * `Api\Mail\Smtp\Sql` instance (hardcoded, NOT the site's configured Mail\Smtp\Stalwart backend -
 * Import never goes through the account-specific factory for this) against the real `egw_mailaccounts`
 * table, scoped by this test's own random account_id - unlike the account-table-wide risks
 * elsewhere in this project, there's no shared-state blast radius here to worry about.
 *
 * The per-source attribute-name switch ($alias_attribute/$primary_attribute: 'mail' for ldap,
 * 'proxyaddresses'/'mail' for ads, 'mailalternativeaddress'/'mailprimaryaddress' for univention)
 * is used ONLY inside the $export_ldif branch - the normal (non-LDIF) add/remove-via-SQL path
 * only ever looks at $contact['aliases']/$contact['email'], identically regardless of source. So
 * the add/remove/dry-run tests below don't need to be repeated per source; only the LDIF tests do.
 */
class ImportAliasesTest extends ImportTestCase
{
	/**
	 * A brand-new account (no prior mail_accounts rows at all) whose source contact carries
	 * "aliases" must get those written as mailAlternateAddress via Api\Mail\Smtp\Sql::setUserData().
	 */
	public function testAddsNewAliasesForNewAccount() : void
	{
		$account_lid = 'import_test_alias_'.substr(md5(random_bytes(8)), 0, 8);
		$account_id = random_int(950000, 999999);
		$alias1 = $account_lid.'.alias1@example.org';
		$alias2 = $account_lid.'.alias2@example.org';

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'no',
		]);

		$import = $this->buildImport(
			accounts: [
				$account_id => [
					'account_id' => $account_id,
					'account_lid' => $account_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Alias',
					'account_lastname' => 'Test',
				],
			],
			contacts: [
				[
					'account_id' => $account_id,
					'modified' => time(),
					'jpegphoto' => null,
					'n_given' => 'Alias',
					'n_family' => 'Test',
					'email' => $account_lid.'@example.org',
					'aliases' => [$alias1, $alias2],
				],
			],
		);
		$this->setImportConfig(['account_import_aliases' => true]);    // AFTER buildImport() - see its docblock

		$result = $import->run(true, save_state: false);

		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));
		$created_id = $this->realAccountId($account_lid);
		$this->assertNotEmpty($created_id, 'Account was not created: '.implode("\n", $this->loggedMessages));
		$this->deleteAfterTest($created_id);

		$mail_data = (new Api\Mail\Smtp\Sql())->getUserData($created_id);
		$this->assertEqualsCanonicalizing([$alias1, $alias2], $mail_data['mailAlternateAddress'] ?? [],
			'Both source aliases should have been written: '.implode("\n", $this->loggedMessages));
	}

	/**
	 * An account that already has an alias in SQL, but whose source contact no longer lists it (a
	 * different alias now instead), must have the old one removed and the new one added.
	 */
	public function testAddRemoveDiffOnExistingAccount() : void
	{
		$account_lid = 'import_test_alias_'.substr(md5(random_bytes(8)), 0, 8);
		$old_alias = $account_lid.'.old@example.org';
		$new_alias = $account_lid.'.new@example.org';
		$local_address = $account_lid.'@example.org';

		$accounts_sql = (new Api\Accounts('sql'))->backend;
		$existing = [
			'account_lid' => $account_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Alias',
			'account_lastname' => 'Existing',
			'account_email' => $local_address,
		];
		$existing_id = $accounts_sql->save($existing, true);
		$this->assertNotEmpty($existing_id, 'Pre-condition: could not create the existing account');
		$this->deleteAfterTest($existing_id);
		Api\Accounts::cache_invalidate($existing_id);

		$mail_accounts = new Api\Mail\Smtp\Sql();
		$this->assertTrue($mail_accounts->setUserData($existing_id, [$old_alias], [], null, 'active', $local_address, null),
			'Pre-condition: could not set the pre-existing alias');
		$pre = $mail_accounts->getUserData($existing_id);
		$this->assertSame([$old_alias], $pre['mailAlternateAddress'] ?? [], 'Pre-condition: old alias not actually stored');

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'no',
		]);

		$import = $this->buildImport(
			accounts: [
				$existing_id => [
					'account_id' => $existing_id,
					'account_lid' => $account_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Alias',
					'account_lastname' => 'Existing',
				],
			],
			contacts: [
				[
					'account_id' => $existing_id,
					'modified' => time(),
					'jpegphoto' => null,
					'n_given' => 'Alias',
					'n_family' => 'Existing',
					'email' => $local_address,
					'aliases' => [$new_alias],    // old_alias no longer present
				],
			],
		);
		$this->setImportConfig(['account_import_aliases' => true]);

		$result = $import->run(true, save_state: false);

		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));
		$post = (new Api\Mail\Smtp\Sql())->getUserData($existing_id);
		$this->assertSame([$new_alias], $post['mailAlternateAddress'] ?? [],
			'The old alias should be gone and the new one present: '.implode("\n", $this->loggedMessages));
	}

	/**
	 * dry_run must not write any alias change, only log what it would do.
	 */
	public function testDryRunDoesNotWriteAliases() : void
	{
		$account_lid = 'import_test_alias_'.substr(md5(random_bytes(8)), 0, 8);
		$account_id = random_int(950000, 999999);
		$alias = $account_lid.'.alias@example.org';

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'no',
		]);

		$import = $this->buildImport(
			accounts: [
				$account_id => [
					'account_id' => $account_id,
					'account_lid' => $account_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Alias',
					'account_lastname' => 'DryRun',
				],
			],
			contacts: [
				[
					'account_id' => $account_id,
					'modified' => time(),
					'jpegphoto' => null,
					'n_given' => 'Alias',
					'n_family' => 'DryRun',
					'email' => $account_lid.'@example.org',
					'aliases' => [$alias],
				],
			],
		);
		$this->setImportConfig(['account_import_aliases' => true]);

		$import->run(true, dry_run: true, save_state: false);

		$log = implode("\n", $this->loggedMessages);
		$this->assertStringContainsString('Dry-run: would add aliases', $log);
		$this->assertStringContainsString($alias, $log);

		// the account itself was also only a dry-run create - nothing to clean up, and nothing to
		// look up mail data for (no real account_id exists) - the log content above is the proof
	}

	/**
	 * $export_ldif does NOT preview what the import would write - it diffs SQL's CURRENT alias
	 * state against what the source shows and emits an LDIF block to push SQL's state back to
	 * LDAP/AD (Import::run()'s own docblock: "changes between aliases defined in SQL database and
	 * AD"). It's driven entirely by aliasImport()'s $removed_aliases (SQL has an alias the source
	 * no longer shows) - found the hard way: an LDIF test against a brand-new account with nothing
	 * in SQL yet produces an empty diff (`mail: ` with no value), since there's nothing in SQL to
	 * push. So this pre-populates real SQL alias state first, then makes the source point to an
	 * empty alias list (as if AD/LDAP is missing the alias) to trigger a real, non-empty diff.
	 *
	 * For account_import_source=ldap the alias attribute IS "mail" (same as the primary-email
	 * attribute) - so the 'mail' attribute in the LDIF ends up multi-valued: the primary address,
	 * then each alternate address (aliasImport()'s `case 'mail': ... // fall through` into
	 * `case 'mailalternateaddress'`).
	 */
	public function testLdifExportForLdapSource() : void
	{
		$account_lid = 'import_test_alias_'.substr(md5(random_bytes(8)), 0, 8);
		$dn = 'uid='.$account_lid.',ou=people,dc=example,dc=org';
		$alias = $account_lid.'.alias@example.org';
		$local_address = $account_lid.'@example.org';

		$accounts_sql = (new Api\Accounts('sql'))->backend;
		$existing = [
			'account_lid' => $account_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Alias',
			'account_lastname' => 'Ldif',
			'account_email' => $local_address,
		];
		$existing_id = $accounts_sql->save($existing, true);
		$this->assertNotEmpty($existing_id, 'Pre-condition: could not create the existing account');
		$this->deleteAfterTest($existing_id);
		Api\Accounts::cache_invalidate($existing_id);

		$mail_accounts = new Api\Mail\Smtp\Sql();
		$this->assertTrue($mail_accounts->setUserData($existing_id, [$alias], [], null, 'active', $local_address, null),
			'Pre-condition: could not set the pre-existing alias in SQL');

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'no',
		]);

		$import = $this->buildImport(
			accounts: [
				$existing_id => [
					'account_id' => $existing_id,
					'account_lid' => $account_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Alias',
					'account_lastname' => 'Ldif',
				],
			],
			contacts: [
				[
					'account_id' => $existing_id,
					'modified' => time(),
					'jpegphoto' => null,
					'dn' => $dn,
					'n_given' => 'Alias',
					'n_family' => 'Ldif',
					'email' => $local_address,
					// no 'aliases' - as if LDAP/AD doesn't have $alias yet, unlike SQL
				],
			],
		);

		ob_start();
		try
		{
			$import->run(true, dry_run: true, export_ldif: 'aliases', save_state: false);
		}
		finally
		{
			$ldif = ob_get_clean();
		}

		$this->assertStringContainsString("dn: $dn", $ldif, "Expected the LDIF diff to reference the contact's dn: $ldif");
		// "add" not "replace": the fixture contact carries no 'aliases' key at all (empty()), which
		// aliasImport() treats as "LDAP has none yet" rather than "LDAP has different ones"
		$this->assertStringContainsString('add: mail', $ldif,
			"ldap source's alias attribute is \"mail\" - expected an 'add: mail' block: $ldif");
		$this->assertStringContainsString("mail: $local_address", $ldif, 'Primary address must be included');
		$this->assertStringContainsString("mail: $alias", $ldif, "SQL's alias must be pushed into the diff");
	}

	/**
	 * Same idea as above, for account_import_source=ads - the alias attribute is "proxyaddresses"
	 * (SMTP:/smtp:-prefixed values), distinct from the "mail" primary-address attribute, so unlike
	 * the ldap case the primary address and the aliases end up under different attribute names.
	 */
	public function testLdifExportForAdsSource() : void
	{
		$account_lid = 'import_test_alias_'.substr(md5(random_bytes(8)), 0, 8);
		$dn = 'cn='.$account_lid.',ou=people,dc=example,dc=org';
		$alias = $account_lid.'.alias@example.org';
		$local_address = $account_lid.'@example.org';

		$accounts_sql = (new Api\Accounts('sql'))->backend;
		$existing = [
			'account_lid' => $account_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Alias',
			'account_lastname' => 'LdifAds',
			'account_email' => $local_address,
		];
		$existing_id = $accounts_sql->save($existing, true);
		$this->assertNotEmpty($existing_id, 'Pre-condition: could not create the existing account');
		$this->deleteAfterTest($existing_id);
		Api\Accounts::cache_invalidate($existing_id);

		$mail_accounts = new Api\Mail\Smtp\Sql();
		$this->assertTrue($mail_accounts->setUserData($existing_id, [$alias], [], null, 'active', $local_address, null),
			'Pre-condition: could not set the pre-existing alias in SQL');

		$this->setImportConfig([
			'account_import_source' => 'ads',
			'account_import_type' => 'users',
			'account_import_delete' => 'no',
		]);

		$fixture_accounts = [
			$existing_id => [
				'account_id' => $existing_id,
				'account_lid' => $account_lid,
				'account_type' => 'u',
				'account_status' => 'A',
				'account_firstname' => 'Alias',
				'account_lastname' => 'LdifAds',
			],
		];
		$import = $this->buildImport(
			accounts: $fixture_accounts,
			contacts: [
				[
					'account_id' => $existing_id,
					'modified' => time(),
					'jpegphoto' => null,
					'dn' => $dn,
					'n_given' => 'Alias',
					'n_family' => 'LdifAds',
					'email' => $local_address,
					// no 'aliases' - as if AD doesn't have $alias yet, unlike SQL
				],
			],
			sourceBackend: new FakeAdsAccountsBackend($fixture_accounts),
		);

		ob_start();
		try
		{
			$import->run(true, dry_run: true, export_ldif: 'aliases', save_state: false);
		}
		finally
		{
			$ldif = ob_get_clean();
		}

		$this->assertStringContainsString("dn: $dn", $ldif);
		// "add" not "replace" - see the ldap test's comment on the same point
		$this->assertStringContainsString('add: proxyaddresses', $ldif,
			"ads source's alias attribute is \"proxyaddresses\": $ldif");
		$this->assertStringContainsString("proxyaddresses: SMTP:$local_address", $ldif,
			'The primary address must be SMTP-prefixed (uppercase) in a proxyAddresses value');
		$this->assertStringContainsString("proxyaddresses: smtp:$alias", $ldif,
			'The alias must be smtp-prefixed (lowercase) in a proxyAddresses value');
		$this->assertDoesNotMatchRegularExpression('/^mail:/m', $ldif,
			'ads must not use the ldap-style "mail" alias attribute name for this diff - the primary '.
			'address already matches between SQL and the source, so no separate primary-attribute block is needed');
	}
}
