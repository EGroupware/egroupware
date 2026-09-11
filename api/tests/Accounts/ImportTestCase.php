<?php
/**
 * EGroupware API - shared harness for Api\Accounts\Import tests
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests;

require_once realpath(__DIR__.'/../LoggedInTest.php');
require_once __DIR__.'/Fixtures/FakeLdapAccountsBackend.php';
require_once __DIR__.'/Fixtures/FakeAdsAccountsBackend.php';
require_once __DIR__.'/Fixtures/FakeContactsSource.php';

use EGroupware\Api;
use EGroupware\Api\Accounts\Import;
use EGroupware\Api\Accounts\Tests\Fixtures\FakeLdapAccountsBackend;
use EGroupware\Api\Accounts\Tests\Fixtures\FakeContactsSource;

/**
 * Builds a real Api\Accounts\Import instance WITHOUT running its constructor (which would
 * otherwise build real Ldap/Ads/Contacts\Ldap objects and connect to a live directory server -
 * see doc/ai/projects/accounts-import-test-coverage.md, "Why not mock the LDAP protocol").
 *
 * Uses ReflectionClass::newInstanceWithoutConstructor() + ReflectionProperty to inject:
 * - a fixture-backed FakeLdapAccountsBackend/FakeContactsSource as the *source* (what a real
 *   run would pull from LDAP/ADS)
 * - the REAL Accounts\Sql/Contacts\Sql backends (via real Api\Accounts('sql')/Api\Contacts()
 *   frontends) as the *destination* - this deliberately exercises the real create/update/
 *   delete/membership SQL logic against the test database, which is the part actually worth
 *   covering; only the network/LDAP boundary is faked.
 *
 * account_import_update_source is always forced off by setImportConfig() unless a test
 * explicitly re-enables it - Import::hookEditAccount() is wired to the same addaccount/
 * editaccount hooks that a normal run() creating an SQL account fires, so leaving it on would
 * make every pull-sync test risk also exercising the (untested, out of scope for now)
 * write-back path. See doc/ai/projects/accounts-import-test-coverage.md and
 * [[feedback_account_import_breaks_tests]] for the same hazard hitting unrelated tests.
 *
 * IMPORTANT: every call to $import->run(...) in a test MUST pass `save_state: false`. Without
 * it, a real (non-dry-run) run() call persists 'account_import_lastrun' to this install's real
 * config (and, for an initial run, (re)installs/cancels the real periodic 'AccountsImport' async
 * job) - regardless of dry_run for the config write. Phase 1/2 tests briefly did this by mistake
 * before the parameter existed and drifted this shared dev box's real account_import_lastrun
 * value to a test-run timestamp; `save_state: false` (added specifically for this) is the fix -
 * see doc/ai/projects/accounts-import-test-coverage.md's "state-persistence side effects" note.
 */
abstract class ImportTestCase extends Api\LoggedInTest
{
	/** @var array original $GLOBALS['egw_info']['server'][...] values, restored in tearDown() */
	private array $configBackup = [];

	/** @var int[] account_id's created via createdImport()'s real Sql backends, deleted in tearDown() */
	private array $accountIdsToDelete = [];

	/** @var string[] captured logger messages as "LEVEL: message" */
	protected array $loggedMessages = [];

	protected function tearDown() : void
	{
		foreach ($this->configBackup as $name => $value)
		{
			$GLOBALS['egw_info']['server'][$name] = $value;
		}
		$this->configBackup = [];

		// direct backend delete, NOT $GLOBALS['egw']->accounts->delete() - the session-cached
		// frontend may not know about an account a fresh Sql backend just created (see
		// realAccountId()'s docblock); always attempt the delete rather than gating on a
		// possibly-stale id2name() check first. Sql::delete() needs a real $frontend (used for
		// its own id2name() call, to also clean up the linked contact) - a bare `new Sql()` has
		// none and fatals.
		if ($this->accountIdsToDelete)
		{
			$accounts_sql = (new Api\Accounts('sql'))->backend;
			foreach ($this->accountIdsToDelete as $account_id)
			{
				Api\Accounts::cache_invalidate($account_id);    // so Sql::delete()'s id2name() sees it
				$GLOBALS['egw']->acl->delete_account($account_id);
				$accounts_sql->delete($account_id);
				Api\Accounts::cache_invalidate($account_id);
			}
		}
		$this->accountIdsToDelete = [];

		parent::tearDown();
	}

	/**
	 * Set account_import_* (or any other) server config for the current test, remembering the
	 * previous value so tearDown() can restore it.
	 *
	 * @param array $overrides name => value pairs for $GLOBALS['egw_info']['server']
	 */
	protected function setImportConfig(array $overrides) : void
	{
		foreach ($overrides as $name => $value)
		{
			if (!array_key_exists($name, $this->configBackup))
			{
				$this->configBackup[$name] = $GLOBALS['egw_info']['server'][$name] ?? null;
			}
			$GLOBALS['egw_info']['server'][$name] = $value;
		}
	}

	/**
	 * Register a real account_id (created by the Import instance under test, via the real Sql
	 * backend) for cleanup in tearDown().
	 */
	protected function deleteAfterTest(int $account_id) : void
	{
		$this->accountIdsToDelete[] = $account_id;
	}

	/**
	 * Look up an account_id by login-id via a direct DB query, bypassing $GLOBALS['egw']->accounts
	 * entirely - per [[feedback_accounts_singleton_broken_in_phpunit_cli]], the session-cached
	 * Api\Accounts frontend can return stale (pre-import) lookups for an account row a backend
	 * write just created/renamed in the same CLI process, since nothing invalidates ITS cache.
	 *
	 * @return int|null real account_id (never a stale/cached one), or null if not found
	 */
	protected function realAccountId(string $account_lid) : ?int
	{
		$id = $GLOBALS['egw']->db->select(Api\Accounts\Sql::TABLE, 'account_id', ['account_lid' => $account_lid],
			__LINE__, __FILE__)->fetchColumn();
		return $id !== false ? (int)$id : null;
	}

	/**
	 * Same as realAccountId(), but negated - egw_accounts stores a group's account_id as a plain
	 * positive number (like a user's); the app-layer convention of representing groups with a
	 * NEGATIVE account_id (used everywhere else, incl. this fixture data and Accounts\Sql::read())
	 * is applied only on the way out, by Accounts\Sql::read()/save() themselves.
	 *
	 * @return int|null real (negative) group account_id, or null if not found
	 */
	protected function realGroupId(string $account_lid) : ?int
	{
		$id = $this->realAccountId($account_lid);
		return $id !== null ? -$id : null;
	}

	/**
	 * Read back an account's stored data via a *fresh* Accounts\Sql backend instance (not the
	 * session-cached $GLOBALS['egw']->accounts frontend) - same staleness hazard as realAccountId().
	 */
	protected function realAccountRead(int $account_id)
	{
		return (new Api\Accounts\Sql())->read($account_id);
	}

	/**
	 * Build an Import instance wired to fixture source backends + the real (test-DB-backed) Sql
	 * destination backends, bypassing Import::__construct() entirely.
	 *
	 * WARNING: this forces account_import_dn_regexp/_aliases/_update_source to safe defaults
	 * (see body) - call setImportConfig() for any of these AFTER buildImport(), never before, or
	 * this silently clobbers it back.
	 *
	 * @param array $accounts account_id => normalized account array (see FakeLdapAccountsBackend)
	 * @param array $contacts list of contact rows (see FakeContactsSource)
	 * @param array $memberOf group_account_id => [member_account_id => account_lid] pairs
	 * @param object|null $sourceBackend override the source accounts backend - defaults to
	 *        FakeLdapAccountsBackend($accounts, $memberOf) (covers account_import_source
	 *        "ldap"/"univention"); pass a FakeAdsAccountsBackend for "ads" tests, since Import
	 *        type-checks its source against Ads::class (see that fixture's docblock)
	 * @return Import
	 */
	protected function buildImport(array $accounts = [], array $contacts = [], array $memberOf = [],
		?object $sourceBackend = null) : Import
	{
		$this->loggedMessages = [];

		// Safe-by-default config: real dev/CI boxes usually have account_import_* already
		// configured for a real LDAP/ADS install (eg. account_import_dn_regexp filtering real
		// DNs) - a test that forgets to set one of these explicitly must NOT silently pick that
		// real value up and skip/misbehave. Tests override any of these via their own
		// setImportConfig() call, AFTER buildImport(), as needed.
		// account_import_update_source is forced off (not just defaulted) - see class docblock
		// re. the hookEditAccount feedback loop; tests exercising that path re-enable it themselves.
		$this->setImportConfig([
			'account_import_dn_regexp' => '',
			'account_import_aliases' => false,
			'account_import_update_source' => false,
		]);

		$frontend_sql = new Api\Accounts('sql');
		$contacts_sql_frontend = new Api\Contacts();

		$import = (new \ReflectionClass(Import::class))->newInstanceWithoutConstructor();

		$this->setImportProperty($import, 'frontend_sql', $frontend_sql);
		$this->setImportProperty($import, 'accounts_sql', $frontend_sql->backend);
		$this->setImportProperty($import, 'contacts_sql_frontend', $contacts_sql_frontend);
		$this->setImportProperty($import, 'contacts_sql', $contacts_sql_frontend->so_accounts ?: $contacts_sql_frontend->somain);
		$this->setImportProperty($import, 'accounts', $sourceBackend ?? new FakeLdapAccountsBackend($accounts, $memberOf));
		$this->setImportProperty($import, 'contacts', new FakeContactsSource($contacts));
		$this->setImportProperty($import, '_logger', function (string $message, string $level)
		{
			$this->loggedMessages[] = "$level: $message";
		});
		// same 3-entry array Import::__construct() builds - see api/src/Accounts/Import.php
		$this->setImportProperty($import, 'files2attrs', [
			Api\Contacts::FILES_PHOTO => ['jpegphoto', Api\Contacts::FILES_BIT_PHOTO, null],
			Api\Contacts::FILES_PGP_PUBKEY => ['pubkey', Api\Contacts::FILES_BIT_PGP_PUBKEY, \addressbook_bo::$pgp_key_regexp],
			Api\Contacts::FILES_SMIME_PUBKEY => ['pubkey', Api\Contacts::FILES_BIT_SMIME_PUBKEY, Api\Mail\Smime::$certificate_regexp],
		]);

		return $import;
	}

	private function setImportProperty(Import $import, string $property, $value) : void
	{
		$rp = new \ReflectionProperty(Import::class, $property);
		$rp->setAccessible(true);
		$rp->setValue($import, $value);
	}
}
