<?php
/**
 * EGroupware API - Api\Accounts\Import: account_import_dn_regexp edge case (Phase 3)
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests;

require_once __DIR__.'/ImportTestCase.php';

use EGroupware\Api;

/**
 * Covers doc/ai/projects/accounts-import-test-coverage.md's Phase 3 test-case plan item 8:
 * confirms the actual (not assumed) behavior of account_import_dn_regexp interacting with
 * account_import_delete, flagged in the config matrix as a suspected sharp edge.
 *
 * Import::run()'s per-contact loop does `if (!preg_match($regexp, $contact['dn'])) continue;`
 * as the VERY FIRST thing in the loop body (api/src/Accounts/Import.php line ~298-302) - before
 * `unset($sql_users[$account_id])` (which only happens near the end of that same loop body, after
 * successful processing). A contact that fails the regexp match is therefore treated EXACTLY like
 * one absent from the source entirely: its account_id is never removed from the deletion-candidate
 * list. This test confirms that concretely: an account genuinely still present in the source, but
 * excluded by this run's dn_regexp, gets counted as a delete candidate - dry_run=true only, for
 * the same shared-DB-safety reason as ImportDeletionDetectionTest (see that class's docblock).
 *
 * Whether this is a bug or intended ("dn_regexp defines what this run manages; anything outside
 * it is nobody's concern including for deletion purposes") isn't settled by this test - it just
 * pins down the current, real behavior so it can't regress silently and so the question is
 * answerable later without re-deriving it from the code.
 */
class ImportDnRegexpTest extends ImportTestCase
{
	public function testRegexpExcludedButStillPresentAccountIsTreatedAsDeleteCandidate() : void
	{
		$accounts_sql = (new Api\Accounts('sql'))->backend;

		$excluded_lid = 'import_test_dnregexp_'.substr(md5(random_bytes(8)), 0, 8);
		$excluded_data = ['account_lid' => $excluded_lid, 'account_type' => 'u', 'account_status' => 'A'];
		$excluded_id = $accounts_sql->save($excluded_data, true);
		$this->assertNotEmpty($excluded_id, 'Pre-condition: could not create the pre-existing account');
		$this->deleteAfterTest($excluded_id);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users',
			'account_import_delete' => 'yes',
		]);

		$import = $this->buildImport(
			accounts: [
				$excluded_id => [
					'account_id' => $excluded_id,
					'account_lid' => $excluded_lid,
					'account_type' => 'u',
					'account_status' => 'A',
				],
			],
			contacts: [
				[
					'account_id' => $excluded_id,
					'modified' => time(),
					'jpegphoto' => null,
					'dn' => 'uid='.$excluded_lid.',ou=other,dc=example,dc=org',    // does NOT match the regexp below
					'n_given' => 'Excluded',
					'n_family' => 'ByRegexp',
				],
			],
		);
		// MUST be set AFTER buildImport() - it forces account_import_dn_regexp back to '' as a
		// safe default (see ImportTestCase::buildImport()'s docblock) and would otherwise clobber
		// this test's whole point
		$this->setImportConfig([
			// matches nothing under ou=people - the fixture contact's dn is under ou=other above
			'account_import_dn_regexp' => '/ou=people,/',
		]);

		$result = $import->run(true, dry_run: true, save_state: false);

		$log = implode("\n", $this->loggedMessages);
		$this->assertStringContainsString("$excluded_lid (#$excluded_id)", $log,
			"Current behavior: a dn_regexp-excluded-but-still-present account IS listed as a delete ".
			"candidate (Import.php's regexp `continue` happens before unset(\$sql_users[...])). If this ".
			"assertion ever fails because the account is instead correctly excluded from the delete ".
			"list, that's a deliberate behavior change worth calling out, not a bug to \"fix\" back.");
		$this->assertGreaterThanOrEqual(1, $result['deleted']);

		// dry_run must be a pure no-op regardless
		$this->assertNotNull($this->realAccountId($excluded_lid));
	}
}
