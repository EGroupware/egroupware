<?php
/**
 * EGroupware API - Api\Accounts\Import: initial import with groups + membership (Phase 2)
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests;

require_once __DIR__.'/ImportTestCase.php';

use EGroupware\Api;
use EGroupware\Api\Accounts\Tests\Fixtures\FakeAdsAccountsBackend;

/**
 * Covers doc/ai/projects/accounts-import-test-coverage.md's Phase 2 test-case plan item 3
 * (initial import, users+groups: group create/update, primary-group remap, membership) for the
 * "ldap" and "ads" sources - "univention" reuses the same FakeLdapAccountsBackend shape as
 * "ldap" (see that fixture's docblock) so isn't repeated here.
 *
 * Setup strategy: same ImportTestCase::buildImport() harness as Phase 1 - fixture-backed source
 * accounts (incl. a group + its member via the $memberOf map) and real Accounts\Sql/Contacts\Sql
 * against the test database as the destination. account_import_type is "users+groups" for every
 * test here.
 *
 * Pass criteria: the group and its member user both land in the real (test) database, the
 * member's primary group is remapped from the source's group id to SQL's own id for that group,
 * and Api\Accounts\Sql::members()/memberships() agree on the resulting membership both ways.
 */
class ImportGroupsTest extends ImportTestCase
{
	/**
	 * A brand-new group present in the (fake) LDAP source, with one brand-new member user whose
	 * account_primary_group points at that same group (by the *source's* id, not SQL's) - this
	 * exercises Import::run()'s primary-group remap (source id -> real SQL id) as well as the
	 * plain membership sync, since both are driven off the same fixture data.
	 */
	public function testCreatesGroupWithMember() : void
	{
		$group_lid = 'import_test_grp_'.substr(md5(random_bytes(8)), 0, 8);
		$group_id = -random_int(700000, 749999);
		$user_lid = 'import_test_'.substr(md5(random_bytes(8)), 0, 8);
		$user_id = random_int(750000, 799999);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users+groups',
			'account_import_delete' => 'no',
		]);

		$import = $this->buildImport(
			accounts: [
				$group_id => [
					'account_id' => $group_id,
					'account_lid' => $group_lid,
					'account_type' => 'g',
					'account_description' => 'Import test group',
				],
				$user_id => [
					'account_id' => $user_id,
					'account_lid' => $user_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Import',
					'account_lastname' => 'Groupmember',
					'account_fullname' => 'Import Groupmember',
					'account_email' => $user_lid.'@example.org',
					'account_primary_group' => $group_id,    // source-side id, must get remapped to SQL's
				],
			],
			contacts: [
				[
					'account_id' => $user_id,
					'modified' => time(),
					'jpegphoto' => null,
					'n_given' => 'Import',
					'n_family' => 'Groupmember',
					'n_fn' => 'Import Groupmember',
					'email' => $user_lid.'@example.org',
				],
			],
			memberOf: [
				$group_id => [$user_id => $user_lid],
			],
		);

		$result = $import->run(true, save_state: false);

		$created_group_id = $this->realGroupId($group_lid);
		$this->assertNotEmpty($created_group_id, 'Group was not created in SQL: '.implode("\n", $this->loggedMessages));
		$this->deleteAfterTest($created_group_id);

		$created_user_id = $this->realAccountId($user_lid);
		$this->assertNotEmpty($created_user_id, 'Member user was not created in SQL: '.implode("\n", $this->loggedMessages));
		$this->deleteAfterTest($created_user_id);

		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));

		$saved_user = $this->realAccountRead($created_user_id);
		$this->assertSame($created_group_id, (int)$saved_user['account_primary_group'],
			"Member's primary group should have been remapped from the source's group id ($group_id) to SQL's own ($created_group_id)");

		$accounts_sql = (new Api\Accounts('sql'))->backend;
		$this->assertArrayHasKey($created_group_id, $accounts_sql->memberships($created_user_id),
			'Member should be a member of the newly-created group per Accounts\Sql::memberships()');
		$this->assertArrayHasKey($created_user_id, $accounts_sql->members($created_group_id),
			'Group should list the member per Accounts\Sql::members()');
	}

	/**
	 * A group whose account_lid already exists as a plain user in SQL must be skipped (not
	 * created, not silently merged) and counted as an error - Import::groups() line ~887-892.
	 */
	public function testGroupNameCollidingWithExistingUserIsSkipped() : void
	{
		$colliding_lid = 'import_test_'.substr(md5(random_bytes(8)), 0, 8);

		// pre-create a real SQL user with this login-id, via the real backend (not through Import)
		$accounts_sql = (new Api\Accounts('sql'))->backend;
		$existing_user = [
			'account_lid' => $colliding_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Existing',
			'account_lastname' => 'User',
		];
		$existing_user_id = $accounts_sql->save($existing_user, true);
		$this->assertNotEmpty($existing_user_id, 'Pre-condition: could not create the colliding user');
		$this->deleteAfterTest($existing_user_id);

		$group_id = -random_int(700000, 749999);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users+groups',
			'account_import_delete' => 'no',
		]);

		$import = $this->buildImport(
			accounts: [
				$group_id => [
					'account_id' => $group_id,
					'account_lid' => $colliding_lid,
					'account_type' => 'g',
				],
			],
		);

		$result = $import->run(true, save_state: false);

		$this->assertSame(1, $result['errors'], 'The name collision should be reported as exactly 1 error: '.implode("\n", $this->loggedMessages));
		// account_lid is unique in the schema, so a real conflicting INSERT could never happen
		// either way - what matters is that the EXISTING row is still the plain user, untouched
		$still_a_user = $GLOBALS['egw']->db->select(Api\Accounts\Sql::TABLE, 'account_type',
			['account_lid' => $colliding_lid], __LINE__, __FILE__)->fetchColumn();
		$this->assertSame('u', $still_a_user, 'The existing user row must be untouched, not turned into (or shadowed by) a group');
	}

	/**
	 * Same as testCreatesGroupWithMember(), but for account_import_source=ads - exercises
	 * Import::groups()'s is_a($this->accounts, Ads::class) branch, which calls getMembers()
	 * (keyed by account_dn) instead of members().
	 */
	public function testCreatesGroupWithMemberFromAds() : void
	{
		$group_lid = 'import_test_grp_'.substr(md5(random_bytes(8)), 0, 8);
		$group_id = -random_int(700000, 749999);
		$group_dn = 'cn='.$group_lid.',ou=groups,dc=example,dc=org';
		$user_lid = 'import_test_'.substr(md5(random_bytes(8)), 0, 8);
		$user_id = random_int(750000, 799999);

		$this->setImportConfig([
			'account_import_source' => 'ads',
			'account_import_type' => 'users+groups',
			'account_import_delete' => 'no',
		]);

		$fixture_accounts = [
			$group_id => [
				'account_id' => $group_id,
				'account_lid' => $group_lid,
				'account_type' => 'g',
				'account_dn' => $group_dn,
			],
			$user_id => [
				'account_id' => $user_id,
				'account_lid' => $user_lid,
				'account_type' => 'u',
				'account_status' => 'A',
				'account_firstname' => 'Import',
				'account_lastname' => 'AdsMember',
				'account_fullname' => 'Import AdsMember',
				'account_email' => $user_lid.'@example.org',
			],
		];
		$import = $this->buildImport(
			accounts: $fixture_accounts,
			contacts: [
				[
					'account_id' => $user_id,
					'modified' => time(),
					'jpegphoto' => null,
					'n_given' => 'Import',
					'n_family' => 'AdsMember',
					'n_fn' => 'Import AdsMember',
					'email' => $user_lid.'@example.org',
				],
			],
			memberOf: [
				$group_id => [$user_id => $user_lid],
			],
			sourceBackend: new FakeAdsAccountsBackend($fixture_accounts, [$group_id => [$user_id => $user_lid]]),
		);

		$result = $import->run(true, save_state: false);

		$created_group_id = $this->realGroupId($group_lid);
		$this->assertNotEmpty($created_group_id, 'Group was not created in SQL: '.implode("\n", $this->loggedMessages));
		$this->deleteAfterTest($created_group_id);

		$created_user_id = $this->realAccountId($user_lid);
		$this->assertNotEmpty($created_user_id, 'Member user was not created in SQL: '.implode("\n", $this->loggedMessages));
		$this->deleteAfterTest($created_user_id);

		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));

		$accounts_sql = (new Api\Accounts('sql'))->backend;
		$this->assertArrayHasKey($created_user_id, $accounts_sql->members($created_group_id),
			'Group should list the member (via the Ads-specific getMembers() path) per Accounts\Sql::members()');
	}

	/**
	 * A group already present in SQL (matched by account_lid) whose account_description differs
	 * from the source must be updated, not skipped/recreated - Import::groups()'s "elseif
	 * (!($sql_group = ...))" update branch.
	 */
	public function testUpdatesExistingGroup() : void
	{
		$group_lid = 'import_test_grp_'.substr(md5(random_bytes(8)), 0, 8);

		$accounts_sql = (new Api\Accounts('sql'))->backend;
		$existing_group = [
			'account_lid' => $group_lid,
			'account_type' => 'g',
			'account_description' => 'Old description',
		];
		$existing_group_id = $accounts_sql->save($existing_group, true);
		$this->assertNotEmpty($existing_group_id, 'Pre-condition: could not create the existing group');
		$this->deleteAfterTest($existing_group_id);

		// source's own numeric id for this group is irrelevant to matching (done by account_lid,
		// since we don't set an account_uuid here) - use an arbitrary distinct fixture id
		$source_group_id = -random_int(700000, 749999);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users+groups',
			'account_import_delete' => 'no',
		]);

		$import = $this->buildImport(
			accounts: [
				$source_group_id => [
					'account_id' => $source_group_id,
					'account_lid' => $group_lid,
					'account_type' => 'g',
					'account_description' => 'New description',
				],
			],
		);

		$result = $import->run(true, save_state: false);

		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));
		$this->assertSame(1, $result['updated'], 'run() should report exactly 1 updated group: '.implode("\n", $this->loggedMessages));

		$saved_group = $accounts_sql->read($existing_group_id);
		$this->assertSame('New description', $saved_group['account_description']);
	}

	/**
	 * account_import_type=users+local+groups must preserve membership in groups that exist only
	 * in SQL (never returned by the source at all) - Import::run()'s "local_memberships" branch,
	 * fed by Import::groups()'s $sql_groups out-param (groups NOT matched from the source this
	 * run). The member here is ALSO being synced this run (present in the fake source, matched by
	 * account_lid to the pre-existing SQL row) with a DIFFERENT, source-side group membership -
	 * both memberships (the source-driven one and the preserved local one) must end up set.
	 */
	public function testPreservesLocalGroupMembership() : void
	{
		$accounts_sql = (new Api\Accounts('sql'))->backend;

		// pre-existing user, as if from a prior import run
		$user_lid = 'import_test_'.substr(md5(random_bytes(8)), 0, 8);
		$existing_user = [
			'account_lid' => $user_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Import',
			'account_lastname' => 'LocalMember',
		];
		$existing_user_id = $accounts_sql->save($existing_user, true);
		$this->assertNotEmpty($existing_user_id, 'Pre-condition: could not create the existing user');
		$this->deleteAfterTest($existing_user_id);

		// pre-existing LOCAL group (never mentioned by the source at all) with that user as a member
		$local_group_lid = 'import_test_local_'.substr(md5(random_bytes(8)), 0, 8);
		$local_group = ['account_lid' => $local_group_lid, 'account_type' => 'g'];
		$local_group_id = $accounts_sql->save($local_group, true);
		$this->assertNotEmpty($local_group_id, 'Pre-condition: could not create the local group');
		$this->deleteAfterTest($local_group_id);
		$accounts_sql->set_memberships([$local_group_id], $existing_user_id);
		$this->assertArrayHasKey($local_group_id, $accounts_sql->memberships($existing_user_id),
			'Pre-condition: user must already be a member of the local group before the run');

		// source-side group the same user is ALSO a member of, per the source this run
		$source_group_lid = 'import_test_grp_'.substr(md5(random_bytes(8)), 0, 8);
		$source_group_id = -random_int(700000, 749999);

		$this->setImportConfig([
			'account_import_source' => 'ldap',
			'account_import_type' => 'users+local+groups',
			'account_import_delete' => 'no',
		]);

		$import = $this->buildImport(
			accounts: [
				$source_group_id => [
					'account_id' => $source_group_id,
					'account_lid' => $source_group_lid,
					'account_type' => 'g',
				],
				$existing_user_id => [
					'account_id' => $existing_user_id,
					'account_lid' => $user_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Import',
					'account_lastname' => 'LocalMember',
					'account_fullname' => 'Import LocalMember',
				],
			],
			contacts: [
				[
					'account_id' => $existing_user_id,
					'modified' => time(),
					'jpegphoto' => null,
					'n_given' => 'Import',
					'n_family' => 'LocalMember',
					'n_fn' => 'Import LocalMember',
				],
			],
			memberOf: [
				$source_group_id => [$existing_user_id => $user_lid],
			],
		);

		$result = $import->run(true, save_state: false);
		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));

		$created_source_group_id = $this->realGroupId($source_group_lid);
		$this->assertNotEmpty($created_source_group_id, 'Source-side group was not created: '.implode("\n", $this->loggedMessages));
		$this->deleteAfterTest($created_source_group_id);

		$memberships = $accounts_sql->memberships($existing_user_id);
		$this->assertArrayHasKey($local_group_id, $memberships,
			'Membership in the LOCAL group (never mentioned by the source) must be preserved');
		$this->assertArrayHasKey($created_source_group_id, $memberships,
			'Membership in the source-driven group must also be set');

		// the local group itself must still exist - local mode forces group deletion off
		$this->assertNotFalse($accounts_sql->read($local_group_id), 'The local group must not have been deleted');
	}

	/**
	 * account_import_source=univention reuses FakeLdapAccountsBackend as-is (Univention extends
	 * Ldap and Import never type-checks against it - only against Ads::class) - this just confirms
	 * that axis of the config matrix actually works for a users+groups sync, without repeating
	 * every scenario already covered for "ldap".
	 */
	public function testCreatesGroupWithMemberFromUnivention() : void
	{
		$group_lid = 'import_test_grp_'.substr(md5(random_bytes(8)), 0, 8);
		$group_id = -random_int(700000, 749999);
		$user_lid = 'import_test_'.substr(md5(random_bytes(8)), 0, 8);
		$user_id = random_int(750000, 799999);

		$this->setImportConfig([
			'account_import_source' => 'univention',
			'account_import_type' => 'users+groups',
			'account_import_delete' => 'no',
		]);

		$import = $this->buildImport(
			accounts: [
				$group_id => [
					'account_id' => $group_id,
					'account_lid' => $group_lid,
					'account_type' => 'g',
				],
				$user_id => [
					'account_id' => $user_id,
					'account_lid' => $user_lid,
					'account_type' => 'u',
					'account_status' => 'A',
					'account_firstname' => 'Import',
					'account_lastname' => 'UniventionMember',
					'account_fullname' => 'Import UniventionMember',
					'account_email' => $user_lid.'@example.org',
				],
			],
			contacts: [
				[
					'account_id' => $user_id,
					'modified' => time(),
					'jpegphoto' => null,
					'n_given' => 'Import',
					'n_family' => 'UniventionMember',
					'n_fn' => 'Import UniventionMember',
					'email' => $user_lid.'@example.org',
				],
			],
			memberOf: [
				$group_id => [$user_id => $user_lid],
			],
		);

		$result = $import->run(true, save_state: false);

		$created_group_id = $this->realGroupId($group_lid);
		$this->assertNotEmpty($created_group_id, 'Group was not created in SQL: '.implode("\n", $this->loggedMessages));
		$this->deleteAfterTest($created_group_id);

		$created_user_id = $this->realAccountId($user_lid);
		$this->assertNotEmpty($created_user_id, 'Member user was not created in SQL: '.implode("\n", $this->loggedMessages));
		$this->deleteAfterTest($created_user_id);

		$this->assertSame(0, $result['errors'], 'run() reported error(s): '.implode("\n", $this->loggedMessages));

		$accounts_sql = (new Api\Accounts('sql'))->backend;
		$this->assertArrayHasKey($created_user_id, $accounts_sql->members($created_group_id));
	}
}
