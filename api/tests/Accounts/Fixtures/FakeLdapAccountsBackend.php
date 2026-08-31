<?php
/**
 * EGroupware API - fake LDAP/ADS-shaped accounts source backend for Import tests
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests\Fixtures;

/**
 * Stands in for Api\Accounts\Ldap/Ads/Univention as the *source* backend fed to
 * Api\Accounts\Import - see doc/ai/projects/accounts-import-test-coverage.md.
 *
 * Deliberately does NOT extend Ldap/Ads/Univention: Import only type-checks its source backend
 * against Ads::class (for the getMembers() vs members() choice in Import::groups()), and every
 * other method it calls (read/search/memberships/members) is called through the plain
 * duck-typed contract, not a real LDAP connection - extending the real classes would require
 * neutralizing their live-connecting constructors for no benefit. Also stands in for a
 * `univention` source (Univention extends Ldap and Import never type-checks against it either).
 * Use FakeAdsAccountsBackend for the ADS-specific getMembers() code path.
 *
 * Fixture data is already in EGroupware's *normalized* account-array shape (the same shape
 * Accounts\Sql::read() returns), not raw LDAP attribute names - Import works exclusively off
 * that normalized shape (see Ldap::_read_user()/_read_group()'s attributes2egw mapping, which
 * this fake replaces entirely rather than re-implements).
 */
class FakeLdapAccountsBackend
{
	/** @var array<int, array> account_id => normalized account array (users and groups, groups keyed negative) */
	private array $accounts;

	/** @var array<int, array<int, string>> group account_id => [(member account_id) => account_lid] */
	private array $memberOf;

	/** @var int next auto-assigned account_id for save()'s "LDAP assigns a new uidNumber" simulation - Phase 5 (write-back) only */
	private int $nextId = 800000;

	/**
	 * @param array $accounts account_id => normalized account array, as Accounts\Sql::read() would return
	 * @param array $memberOf group_account_id => [member_account_id => account_lid] pairs, for members()/memberships()
	 */
	public function __construct(array $accounts = [], array $memberOf = [])
	{
		$this->accounts = $accounts;
		$this->memberOf = $memberOf;
	}

	/**
	 * @param int $account_id
	 * @return array|false
	 */
	function read($account_id)
	{
		return $this->accounts[$account_id] ?? false;
	}

	/**
	 * Import::groups() is the ONLY caller, always with $param = ['type' => 'groups'] optionally
	 * plus ['modified' => $timestamp] for an incremental run - no other $param shape is ever
	 * passed, so that's the only contract implemented here.
	 *
	 * @param array $param
	 * @return array<int, array> account_id => normalized group array (account_type === 'g')
	 */
	function search($param)
	{
		if (($param['type'] ?? null) !== 'groups')
		{
			throw new \Exception(__METHOD__.'('.json_encode($param).') fixture only supports type=groups - the only shape Import::groups() ever passes');
		}
		$modified_since = $param['modified'] ?? null;
		$result = [];
		foreach ($this->accounts as $account_id => $account)
		{
			if (($account['account_type'] ?? null) !== 'g')
			{
				continue;
			}
			if (isset($modified_since) && (int)($account['account_modified'] ?? 0) < (int)$modified_since)
			{
				continue;
			}
			$result[$account_id] = $account;
		}
		return $result;
	}

	function memberships($account_id)
	{
		$memberships = [];
		foreach ($this->memberOf as $group_id => $members)
		{
			if (isset($members[$account_id]))
			{
				$memberships[$group_id] = $this->accounts[$group_id]['account_lid'] ?? null;
			}
		}
		return $memberships;
	}

	function members($account_id)
	{
		return $this->memberOf[$account_id] ?? [];
	}

	/**
	 * Used by Import::run() pull-sync only for the "1 or 2 comma/semicolon-separated
	 * default_group_lid names" resolution against $this->accounts_sql (the destination), never on
	 * this (source) backend - but IS used on this backend by Import::hookEditAccount() (the
	 * account_import_update_source write-back path, Phase 5).
	 */
	function name2id($name, $which = 'account_lid', $account_type = null)
	{
		foreach ($this->accounts as $account_id => $account)
		{
			if (($account[$which] ?? null) === $name &&
				(!isset($account_type) || ($account_type === 'g') === ($account_id < 0)))
			{
				return $account_id;
			}
		}
		return false;
	}

	function id2name($account_id, $which = 'account_lid')
	{
		return $this->accounts[$account_id][$which] ?? false;
	}

	/**
	 * Simulates "LDAP/AD assigns a new uidNumber/entryUUID/DN on create" - if $data carries no
	 * account_id (Import::hookEditAccount()'s 'addaccount' case deliberately unsets it before
	 * calling save(), exactly to trigger this), one is auto-assigned, along with a fixture
	 * account_uuid/account_dn if not already set. Mutates $data by reference, same contract as the
	 * real Ldap/Ads/Sql backends' save().
	 *
	 * @param array $data
	 * @return int|false the (possibly newly-assigned) account_id, or false - never fails here
	 */
	function save(&$data)
	{
		if (empty($data['account_id']))
		{
			$is_group = ($data['account_type'] ?? 'u') === 'g';
			$id = $this->nextId++;
			$data['account_id'] = $is_group ? -$id : $id;
		}
		$data['account_uuid'] ??= 'fixture-uuid-'.$data['account_id'];
		$data['account_dn'] ??= 'uid='.($data['account_lid'] ?? $data['account_id']).',ou=people,dc=example,dc=org';
		$this->accounts[$data['account_id']] = $data;
		return $data['account_id'];
	}

	function delete($account_id)
	{
		if (!isset($this->accounts[$account_id]))
		{
			return false;
		}
		unset($this->accounts[$account_id]);
		return true;
	}

	function set_memberships($groups, $account_id)
	{
		throw new \Exception(__METHOD__.'() not (yet) implemented in fixture - Import never calls this on the source backend');
	}

	function update_lastlogin($account_id, $ip)
	{
		throw new \Exception(__METHOD__.'() not (yet) implemented in fixture - Import never calls this on the source backend');
	}
}
