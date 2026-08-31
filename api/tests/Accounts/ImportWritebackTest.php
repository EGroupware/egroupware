<?php
/**
 * EGroupware API - Api\Accounts\Import: write-back to source (Phase 5)
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests;

require_once __DIR__.'/ImportTestCase.php';
require_once __DIR__.'/Fixtures/FakeContactsSoAccounts.php';

use EGroupware\Api;
use EGroupware\Api\Accounts\Import;
use EGroupware\Api\Accounts\Tests\Fixtures\FakeLdapAccountsBackend;
use EGroupware\Api\Accounts\Tests\Fixtures\FakeContactsSoAccounts;

/**
 * Covers doc/ai/projects/accounts-import-test-coverage.md's Phase 5: Import::hookEditAccount(),
 * the account_import_update_source write-back path (local SQL changes pushed back to the
 * LDAP/ADS source), deferred as a separate follow-up from Phases 1-4 (the pull-sync path).
 *
 * Testability: hookEditAccount() is a plain `public static function` - no Import instance
 * involved at all, so the ImportTestCase::buildImport() harness isn't used here. Instead it calls
 * `static::accountsFactory()`/`static::contactsFactory()` (note `static::`, not `self::` - the
 * tweak made early in this project specifically to make this kind of override possible), which
 * `TestableWritebackImport` (this file) overrides to return fixture-backed frontends instead of
 * building real, live-connecting Ldap/Ads objects. No ReflectionClass tricks needed here - just a
 * subclass.
 *
 * FakeLdapAccountsBackend's save()/delete()/name2id()/id2name() (stubbed "not implemented" through
 * Phase 1-4, since the pull-sync path never calls them on the *source* backend) are properly
 * implemented for real here, incl. save()'s "LDAP/AD assigns a new uidNumber/UUID/DN on create"
 * simulation - exactly the shape hookEditAccount()'s 'addaccount' case exercises.
 *
 * 'addaccount'/'editaccount'/'deleteaccount'/'editaccountcontact' are covered, including
 * 'editaccountcontact''s GUID-validation-failure recovery branch (its nested
 * `self::hookEditAccount([...])` calls used to be non-forwarding `self::` - which resets late
 * static binding back to the literal `Import` class mid-call, so `TestableWritebackImport`'s
 * factory overrides would silently not apply there - changed to `static::` specifically to make
 * this branch testable, same reasoning as the original `accountsFactory`/`contactsFactory` tweak).
 */
class ImportWritebackTest extends ImportTestCase
{
	/** @var array original Api\Config::$configs['phpgwapi'][...] values, restored in tearDown() */
	private array $realConfigBackup = [];

	protected function tearDown() : void
	{
		TestableWritebackImport::$accountsOverride = null;
		TestableWritebackImport::$contactsOverride = null;

		if ($this->realConfigBackup)
		{
			$rp = new \ReflectionProperty(Api\Config::class, 'configs');
			$rp->setAccessible(true);
			$configs = $rp->getValue();
			foreach ($this->realConfigBackup as $name => $value)
			{
				if ($value === null)
				{
					unset($configs['phpgwapi'][$name]);
				}
				else
				{
					$configs['phpgwapi'][$name] = $value;
				}
			}
			$rp->setValue(null, $configs);
			$this->realConfigBackup = [];
		}

		parent::tearDown();
	}

	/**
	 * hookEditAccount() reads config via Api\Config::read('phpgwapi') directly - NOT
	 * $GLOBALS['egw_info']['server'] like Import::run() does - so ImportTestCase::setImportConfig()
	 * has no effect on it at all. Api\Config's static cache (self::$configs) is private with no
	 * public setter for arbitrary overrides (save_value() would write to the real shared DB config
	 * row - not acceptable here, same class of risk as elsewhere in this project), so this reaches
	 * it via reflection instead, backing up/restoring exactly like setImportConfig() does for the
	 * other config path.
	 */
	private function setRealImportConfig(array $overrides) : void
	{
		$rp = new \ReflectionProperty(Api\Config::class, 'configs');
		$rp->setAccessible(true);
		$configs = $rp->getValue();
		foreach ($overrides as $name => $value)
		{
			if (!array_key_exists($name, $this->realConfigBackup))
			{
				$this->realConfigBackup[$name] = $configs['phpgwapi'][$name] ?? null;
			}
			$configs['phpgwapi'][$name] = $value;
		}
		$rp->setValue(null, $configs);
	}

	/**
	 * Fixture helper: build the Api\Accounts frontend object hookEditAccount() expects back from
	 * accountsFactory() ($accounts->backend->save()/name2id()/read()/delete()), wrapping the given
	 * fake backend, without running Api\Accounts::__construct() (which would try to instantiate a
	 * REAL backend class matching the string $account_repository - moot here since we're injecting
	 * one directly, and its is_a($backend_object, $backend_class) check would reject our fake
	 * anyway, since it deliberately doesn't extend Ldap/Ads - see FakeLdapAccountsBackend's docblock).
	 */
	private function fakeAccountsFrontend(FakeLdapAccountsBackend $backend) : Api\Accounts
	{
		$frontend = (new \ReflectionClass(Api\Accounts::class))->newInstanceWithoutConstructor();
		$frontend->backend = $backend;    // public property
		return $frontend;
	}

	public function testNoopWhenUpdateSourceDisabled() : void
	{
		$backend = new FakeLdapAccountsBackend();
		TestableWritebackImport::$accountsOverride = $this->fakeAccountsFrontend($backend);

		$this->setRealImportConfig([
			'account_import_source' => 'ldap',
			'account_import_update_source' => false,
		]);

		TestableWritebackImport::hookEditAccount([
			'location' => 'addaccount',
			'account_id' => 12345,
			'account_lid' => 'should_not_be_pushed',
			'account_type' => 'u',
		]);

		$this->assertFalse($backend->name2id('should_not_be_pushed'),
			'Nothing should have been pushed to the source while account_import_update_source is off');
	}

	/**
	 * Import::run() itself creates/updates SQL accounts and fires the addaccount/editaccount hooks
	 * that hookEditAccount() listens on - the 'caller_method' guard is what stops that from
	 * looping back into a write-back push during a normal pull-sync run (see
	 * [[feedback_account_import_breaks_tests]] and this project's own "hookEditAccount feedback
	 * loop" hazard, forced off by default in ImportTestCase::buildImport() for exactly this reason).
	 */
	public function testNoopWhenCalledByImportItself() : void
	{
		$backend = new FakeLdapAccountsBackend();
		TestableWritebackImport::$accountsOverride = $this->fakeAccountsFrontend($backend);

		$this->setRealImportConfig([
			'account_import_source' => 'ldap',
			'account_import_update_source' => true,
		]);

		TestableWritebackImport::hookEditAccount([
			'location' => 'addaccount',
			'account_id' => 12345,
			'account_lid' => 'should_not_be_pushed',
			'account_type' => 'u',
			'caller_method' => Import::class.'::run',
		]);

		$this->assertFalse($backend->name2id('should_not_be_pushed'),
			'A hook call flagged as coming from Import itself must be ignored, to avoid a pull/push sync loop');
	}

	/**
	 * A new local account pushed to the (fake) source must land there with an auto-assigned
	 * uidNumber/UUID/DN (FakeLdapAccountsBackend::save()'s simulation of what a real directory
	 * would assign), and that DN/UUID must be written back into the REAL SQL account row (matched
	 * by account_id, scoped to this test's own throwaway account - no shared-state risk) plus
	 * egw_addressbook.contact_uid.
	 */
	public function testAddAccountPushesNewAccountAndStoresUuidDn() : void
	{
		$account_lid = 'import_test_wb_'.substr(md5(random_bytes(8)), 0, 8);

		// via the FRONTEND, not the backend directly - Api\Accounts::save() also creates the
		// linked addressbook contact (Accounts.php ~line 852), which the real
		// hookEditAccount()'s egw_addressbook.contact_uid UPDATE below needs to find a row to
		// affect - a bare backend->save() (as elsewhere in this project, where the linked contact
		// isn't relevant) would leave that UPDATE a silent no-op
		$local_account = [
			'account_lid' => $account_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Writeback',
			'account_lastname' => 'Add',
		];
		$local_id = (new Api\Accounts('sql'))->save($local_account);
		$this->assertNotEmpty($local_id, 'Pre-condition: could not create the local account');
		$this->deleteAfterTest($local_id);
		Api\Accounts::cache_invalidate($local_id);

		$backend = new FakeLdapAccountsBackend();
		TestableWritebackImport::$accountsOverride = $this->fakeAccountsFrontend($backend);

		$this->setRealImportConfig([
			'account_import_source' => 'ldap',
			'account_import_update_source' => true,
		]);

		TestableWritebackImport::hookEditAccount([
			'location' => 'addaccount',
			'account_id' => $local_id,
			'account_lid' => $account_lid,
			'account_type' => 'u',
			'account_firstname' => 'Writeback',
			'account_lastname' => 'Add',
		]);

		$fake_id = $backend->name2id($account_lid);
		$this->assertNotFalse($fake_id, 'The account should have been created on the (fake) source');
		$fake_account = $backend->read($fake_id);
		$this->assertNotEmpty($fake_account['account_uuid'] ?? null, 'The fake source should have assigned a uuid');
		$this->assertNotEmpty($fake_account['account_dn'] ?? null, 'The fake source should have assigned a dn');

		$saved = $this->realAccountRead($local_id);
		$this->assertSame($fake_account['account_uuid'], $saved['account_uuid'],
			"The source's assigned uuid must be written back into the real SQL account row");
		$this->assertSame($fake_account['account_dn'], $saved['account_dn'],
			"The source's assigned dn must be written back into the real SQL account row");

		$contact_uid = $GLOBALS['egw']->db->select('egw_addressbook', 'contact_uid',
			['account_id' => $local_id], __LINE__, __FILE__)->fetchColumn();
		$this->assertSame($fake_account['account_uuid'], $contact_uid,
			"The contact's contact_uid must be set to the source's assigned uuid too");
	}

	/**
	 * An account that's already synced (has a real account_uuid on file) being edited locally must
	 * push the FULL updated field set to the existing source entry - matched by account_id, which
	 * for an already-synced account is the SAME numeric id on both sides (the SQL account_id IS
	 * the source's own uidNumber for a synced account - confirmed the hard way: an earlier version
	 * of this test used two different ids and silently exercised the "treat as new" branch
	 * instead, since Api\Accounts::getInstance()->id2name($account['account_id'], 'account_uuid')
	 * looks the caller-supplied id up in the REAL SQL table, not some independent source-side id).
	 */
	public function testEditAccountUpdatesExistingSourceEntry() : void
	{
		$account_lid = 'import_test_wb_'.substr(md5(random_bytes(8)), 0, 8);
		$fixture_uuid = 'fixture-uuid-preexisting-'.$account_lid;

		// real SQL account first, to get its id - the fake source entry below is keyed by that
		// SAME id, matching the "already synced" convention
		$local_account = [
			'account_lid' => $account_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Before',
			'account_uuid' => $fixture_uuid,
		];
		$local_id = (new Api\Accounts('sql'))->save($local_account);
		$this->assertNotEmpty($local_id, 'Pre-condition: could not create the local account');
		$this->deleteAfterTest($local_id);
		Api\Accounts::cache_invalidate($local_id);

		$backend = new FakeLdapAccountsBackend([
			$local_id => [
				'account_id' => $local_id,
				'account_lid' => $account_lid,
				'account_type' => 'u',
				'account_uuid' => $fixture_uuid,
				'account_dn' => 'uid='.$account_lid.',ou=people,dc=example,dc=org',
				'account_firstname' => 'Before',
			],
		]);
		TestableWritebackImport::$accountsOverride = $this->fakeAccountsFrontend($backend);

		$this->setRealImportConfig([
			'account_import_source' => 'ldap',
			'account_import_update_source' => true,
		]);

		TestableWritebackImport::hookEditAccount([
			'location' => 'editaccount',
			'account_id' => $local_id,
			'account_lid' => $account_lid,
			'account_type' => 'u',
			'account_firstname' => 'After',
		]);

		$updated = $backend->read($local_id);
		$this->assertSame('After', $updated['account_firstname'],
			'The existing source entry should reflect the locally-edited field');
	}

	/**
	 * A locally-deleted account must be removed from the source too, looked up by account_lid.
	 */
	public function testDeleteAccountRemovesFromSource() : void
	{
		$account_lid = 'import_test_wb_'.substr(md5(random_bytes(8)), 0, 8);
		$fixture_id = 820001 + random_int(0, 8999);

		$backend = new FakeLdapAccountsBackend([
			$fixture_id => [
				'account_id' => $fixture_id,
				'account_lid' => $account_lid,
				'account_type' => 'u',
			],
		]);
		TestableWritebackImport::$accountsOverride = $this->fakeAccountsFrontend($backend);

		$this->setRealImportConfig([
			'account_import_source' => 'ldap',
			'account_import_update_source' => true,
		]);

		TestableWritebackImport::hookEditAccount([
			'location' => 'deleteaccount',
			'account_id' => $fixture_id,
			'account_lid' => $account_lid,
		]);

		$this->assertFalse($backend->name2id($account_lid), 'The account should have been removed from the (fake) source');
	}

	/**
	 * Fixture helper: build the Api\Contacts frontend object hookEditAccount() expects back from
	 * contactsFactory() ($contacts->backendSave()), wrapping the given fake so_accounts backend -
	 * without running Api\Contacts::__construct() (which would build a REAL, live-connecting
	 * Contacts\Ldap so_accounts object - see FakeContactsSoAccounts's docblock). contact_repository
	 * defaults to 'sql' (its own class-declared default, still applied by
	 * newInstanceWithoutConstructor() even though __construct() never runs) - only
	 * account_repository needs setting explicitly, to make Contacts\Storage::save()'s "contact_repository
	 * != account_repository" routing condition pick the so_accounts branch.
	 */
	private function fakeContactsFrontend(FakeContactsSoAccounts $soAccounts, string $accountRepository = 'ldap') : Api\Contacts
	{
		$frontend = (new \ReflectionClass(Api\Contacts::class))->newInstanceWithoutConstructor();
		$frontend->account_repository = $accountRepository;
		$frontend->so_accounts = $soAccounts;
		return $frontend;
	}

	/**
	 * Locally-edited contact data (n_given/n_family/etc.) for an account already synced to the
	 * source must be pushed to the source, keyed by the source's own uid (NOT the SQL contact id -
	 * "id is the uid for LDAP or ADS!", per hookEditAccount()'s own comment).
	 */
	public function testEditAccountContactPushesContactDataToSource() : void
	{
		$so_accounts = new FakeContactsSoAccounts();
		TestableWritebackImport::$contactsOverride = $this->fakeContactsFrontend($so_accounts);

		$this->setRealImportConfig([
			'account_import_source' => 'ldap',
			'account_import_update_source' => true,
		]);

		$uid = 'uid=import_test_wb_contact,ou=people,dc=example,dc=org';
		TestableWritebackImport::hookEditAccount([
			'location' => 'editaccountcontact',
			'uid' => $uid,
			'account_id' => 123456,    // just needs to be non-empty for the top-level guard
			'n_given' => 'Changed',
			'n_family' => 'Contact',
		]);

		$this->assertArrayHasKey($uid, $so_accounts->saved, 'The contact data should have been pushed to the (fake) source, keyed by its uid');
		$this->assertSame('Changed', $so_accounts->saved[$uid]['n_given']);
		$this->assertSame('Contact', $so_accounts->saved[$uid]['n_family']);
	}

	/**
	 * A backend save() failure must surface as a thrown Exception, not be silently swallowed.
	 */
	public function testEditAccountContactThrowsOnBackendError() : void
	{
		$so_accounts = new FakeContactsSoAccounts();
		$so_accounts->nextSaveResult = 'simulated backend failure';
		TestableWritebackImport::$contactsOverride = $this->fakeContactsFrontend($so_accounts);

		$this->setRealImportConfig([
			'account_import_source' => 'ldap',
			'account_import_update_source' => true,
		]);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('simulated backend failure');

		TestableWritebackImport::hookEditAccount([
			'location' => 'editaccountcontact',
			'uid' => 'uid=import_test_wb_contact_err,ou=people,dc=example,dc=org',
			'account_id' => 123456,
			'n_given' => 'Changed',
		]);
	}

	/**
	 * The GUID-recovery branch: a contact save() rejected because its id isn't a valid GUID (per
	 * the source backend - simulated here) means the account behind it is still local, not yet
	 * synced. hookEditAccount() reacts by pushing a brand-new 'addaccount' for it first, reading
	 * back the uuid the source assigned, then retrying 'editaccountcontact' with that uuid as the
	 * id - which must succeed this time. Exercises the SAME nested-call path this project's
	 * self::->static:: fix (see class docblock) was made specifically to make testable.
	 */
	public function testEditAccountContactRecoversFromInvalidGuidByCreatingNewAccount() : void
	{
		$account_lid = 'import_test_wb_'.substr(md5(random_bytes(8)), 0, 8);
		$placeholder_uid = 'local-placeholder-'.$account_lid;    // not a "real" GUID

		// a genuinely local account: no account_uuid on file yet
		$local_account = [
			'account_lid' => $account_lid,
			'account_type' => 'u',
			'account_status' => 'A',
			'account_firstname' => 'Writeback',
			'account_lastname' => 'Recover',
		];
		$local_id = (new Api\Accounts('sql'))->save($local_account);
		$this->assertNotEmpty($local_id, 'Pre-condition: could not create the local account');
		$this->deleteAfterTest($local_id);
		Api\Accounts::cache_invalidate($local_id);

		$accounts_backend = new FakeLdapAccountsBackend();
		TestableWritebackImport::$accountsOverride = $this->fakeAccountsFrontend($accounts_backend);

		$so_accounts = new FakeContactsSoAccounts();
		$so_accounts->invalidGuidId = $placeholder_uid;
		TestableWritebackImport::$contactsOverride = $this->fakeContactsFrontend($so_accounts);

		$this->setRealImportConfig([
			'account_import_source' => 'ldap',
			'account_import_update_source' => true,
		]);

		TestableWritebackImport::hookEditAccount([
			'location' => 'editaccountcontact',
			'uid' => $placeholder_uid,
			'account_id' => $local_id,
			'account_lid' => $account_lid,
			'account_type' => 'u',
			'n_given' => 'Recovered',
		]);

		// the nested 'addaccount' must have created a new entry on the (fake) source ...
		$new_fake_id = $accounts_backend->name2id($account_lid);
		$this->assertNotFalse($new_fake_id, 'The account should have been pushed to the source as new');
		$new_uuid = $accounts_backend->read($new_fake_id)['account_uuid'];
		$this->assertNotEmpty($new_uuid);

		// ... and written that uuid back into the real SQL account ...
		$this->assertSame($new_uuid, $this->realAccountRead($local_id)['account_uuid'],
			"The newly-assigned uuid must be written back into the real SQL account row");

		// ... and the RETRIED editaccountcontact call must have succeeded, keyed by the new uuid
		// (not the original placeholder, which would have kept throwing)
		$this->assertArrayNotHasKey($placeholder_uid, $so_accounts->saved);
		$this->assertArrayHasKey($new_uuid, $so_accounts->saved,
			'The retried contact save should have landed under the new uuid');
		$this->assertSame('Recovered', $so_accounts->saved[$new_uuid]['n_given']);
	}
}

/**
 * Overrides Import's 2 factory methods (called via `static::` inside hookEditAccount(), see this
 * file's class docblock) to hand back fixture-backed frontends instead of building real ones.
 */
class TestableWritebackImport extends Import
{
	public static ?Api\Accounts $accountsOverride = null;
	public static ?Api\Contacts $contactsOverride = null;

	protected static function accountsFactory(string $account_repository)
	{
		return static::$accountsOverride;
	}

	protected static function contactsFactory(string $account_repository)
	{
		return static::$contactsOverride;
	}
}
