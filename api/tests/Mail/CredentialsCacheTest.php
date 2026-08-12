<?php
/**
 * EGroupware Api: regression test for Mail\Credentials::read()'s per-process cache going stale
 * across separate requests (eg. a S/MIME key written by one PHP-FPM worker, then read by another)
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

namespace EGroupware\Api\Mail;

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api;

/**
 * Reproduces and verifies the fix for a real bug found while building the S/MIME feature:
 * Credentials::$cache is a per-process (PHP-FPM worker) static cache. write()/delete() only
 * unset() their OWN process's cache entry for the written acc_id/account_id. A DIFFERENT worker
 * that has ALREADY cached (eg. from an earlier, unrelated page load before the credential
 * existed) an entry for that same acc_id/account_id never sees the new credential, because
 * read()'s cache branch only re-queries the DB when NO cache entry exists at all for that
 * acc_id/account_id - not when the specifically requested TYPE isn't represented in what's
 * cached.
 *
 * A single PHPUnit process reproduces this faithfully: what matters is "cache already populated
 * before the write, never invalidated for this reader" - which caller populated the cache (this
 * same test, or truly a different worker) doesn't change Credentials::$cache's behaviour.
 */
class CredentialsCacheTest extends Api\LoggedInTest
{
	private $acc_id = 1; // per project memory: acc_id=1 Stalwart/JMAP test account
	private $account_id;

	protected function setUp() : void
	{
		$this->account_id = $GLOBALS['egw_info']['user']['account_id'];
		Credentials::delete($this->acc_id, $this->account_id, Credentials::SMIME);
	}

	protected function tearDown() : void
	{
		Credentials::delete($this->acc_id, $this->account_id, Credentials::SMIME);
	}

	/**
	 * The actual fix: Smime::get_acc_smime() must find a credential written AFTER an earlier,
	 * unrelated ALL-types read already cached "no S/MIME" for this acc_id/account_id - simulating
	 * a stale cache left behind by a different PHP-FPM worker.
	 */
	public function testGetAccSmimeSeesCredentialWrittenAfterStaleAllTypesCache()
	{
		// simulate an earlier, unrelated page load caching ALL credential types for this account,
		// before any S/MIME credential exists - this is what Mail\Account::read() does on every
		// mail account edit page load
		Credentials::read($this->acc_id, null, array(0, $this->account_id));

		// a fresh S/MIME credential is then written (in a real deployment: by a different worker)
		$cred_id = Credentials::write($this->acc_id, 'test@example.org', 'dummy-p12-content',
			Credentials::SMIME, $this->account_id);
		$this->assertNotNull($cred_id, 'test setup: writing the credential must succeed');

		$acc_smime = Smime::get_acc_smime($this->acc_id, '', $this->account_id);

		$this->assertNotFalse($acc_smime,
			'get_acc_smime() must see a credential written after an earlier stale all-types cache read');
		$this->assertArrayHasKey('acc_smime_password', $acc_smime);
	}

	/**
	 * Without the use_cache bypass, Credentials::read() itself exhibits the stale-cache bug
	 * directly - documents the underlying mechanism get_acc_smime() works around, and guards
	 * the use_cache parameter itself against being silently removed/broken later.
	 *
	 * write()/delete() correctly unset() the cache entry for the process that performs the
	 * write, so a plain write()-then-read() within the SAME PHPUnit process/request would NOT
	 * reproduce the bug (and isn't the scenario that matters: the whole point is a DIFFERENT
	 * process/worker never having its cache invalidated). A stale cache entry is injected
	 * directly via reflection instead, to faithfully simulate what a different, unaware PHP-FPM
	 * worker's leftover cache looks like - not what write()'s own invalidation would produce.
	 */
	public function testReadUseCacheParameterBypassesStaleAllTypesCache()
	{
		$cred_id = Credentials::write($this->acc_id, 'test@example.org', 'dummy-p12-content',
			Credentials::SMIME, $this->account_id);
		$this->assertNotNull($cred_id, 'test setup: writing the credential must succeed');

		// simulate a different worker's stale cache: it looked up ALL types for this
		// acc_id/account_id before this credential existed, and found nothing at all
		$cache = new \ReflectionProperty(Credentials::class, 'cache');
		$cache->setAccessible(true);
		$original = $cache->getValue();
		// array_merge() renumbers integer keys (acc_id) instead of overwriting them - use direct
		// key assignment so the injected stale entry actually replaces any real cached data
		$stale = $original;
		$stale[$this->acc_id] = array($this->account_id => array());
		$cache->setValue(null, $stale);

		try
		{
			// default use_cache=true: reproduces the underlying bug, must NOT find it (stale cache)
			$stale = Credentials::read($this->acc_id, Credentials::SMIME, $this->account_id);
			$this->assertArrayNotHasKey('acc_smime_password', $stale,
				'demonstrates the underlying bug: a cached read() misses a credential the (simulated) stale cache predates');

			// use_cache=false: must find it despite the same stale cache being present
			$fresh = Credentials::read($this->acc_id, Credentials::SMIME, $this->account_id, $on_login, null, false);
			$this->assertArrayHasKey('acc_smime_password', $fresh,
				'use_cache=false must bypass the stale cache and see the credential');
		}
		finally
		{
			$cache->setValue(null, $original);
		}
	}
}
