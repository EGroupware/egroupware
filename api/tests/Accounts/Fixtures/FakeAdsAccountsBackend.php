<?php
/**
 * EGroupware API - fake ADS-shaped accounts source backend for Import tests
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests\Fixtures;

use EGroupware\Api\Accounts\Ads;

/**
 * Stands in for Api\Accounts\Ads as Import's *source* backend, for the account_import_source=ads
 * path specifically - see doc/ai/projects/accounts-import-test-coverage.md.
 *
 * Unlike FakeLdapAccountsBackend, this one MUST `extends Ads`: Import::groups() does
 * `is_a($this->accounts, Ads::class)` to decide between calling members() (Ldap/Univention shape)
 * or getMembers() (Ads shape, keyed by the group's account_dn instead of a plain account_id) -
 * that's the one place Import type-checks its source backend at all. The constructor is
 * overridden to never call Ads::__construct() (which live-connects via Api\Ldap::factory()) -
 * every other Ads method Import might reach is likewise overridden with fixture-backed logic
 * rather than inherited (the inherited ones would touch $this->ds, which is never initialized
 * here).
 */
class FakeAdsAccountsBackend extends Ads
{
	/** @var array<int, array> account_id => normalized account array (users and groups, groups keyed negative) */
	private array $accounts;

	/** @var array<int, array<int, string>> group account_id (the RID, i.e. $group['account_id'] passed to getMembers()) => [(member account_id) => account_lid] */
	private array $memberOf;

	/**
	 * @param array $accounts account_id => normalized account array, as Accounts\Sql::read() would return
	 * @param array $memberOf group_account_id => [member_account_id => account_lid] pairs
	 */
	public function __construct(array $accounts = [], array $memberOf = [])
	{
		$this->accounts = $accounts;
		$this->memberOf = $memberOf;
	}

	function read($account_id)
	{
		return $this->accounts[$account_id] ?? false;
	}

	/**
	 * Same contract as FakeLdapAccountsBackend::search() - see its docblock.
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

	/**
	 * Import::groups() calls this instead of members() for an Ads source (see class docblock),
	 * with $group['account_id'] set to the RID (the same key this fixture's $memberOf is keyed
	 * by) - matches the real Ads::getMembers()'s own required-argument validation.
	 *
	 * @param array $group with values for keys account_id and account_dn
	 * @return array<int, string> member_account_id => account_lid pairs
	 */
	public function getMembers(array $group)
	{
		if (empty($group['account_dn']) || empty($group['account_id']))
		{
			throw new \InvalidArgumentException(__METHOD__.'('.json_encode($group).') missing account_id and/or account_dn attribute');
		}
		return $this->memberOf[$group['account_id']] ?? [];
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

	function members($gid)
	{
		throw new \Exception(__METHOD__.'() not (yet) implemented in fixture - Import::groups() always calls getMembers() for an Ads source instead, see class docblock');
	}

	function name2id($name, $which = 'account_lid', $account_type = null)
	{
		throw new \Exception(__METHOD__.'() not (yet) implemented in fixture - Import never calls this on the source backend');
	}

	function id2name($account_id, $which = 'account_lid')
	{
		throw new \Exception(__METHOD__.'() not (yet) implemented in fixture - Import never calls this on the source backend');
	}

	function save(&$data)
	{
		throw new \Exception(__METHOD__.'() not (yet) implemented in fixture - only needed for the account_import_update_source write-back path');
	}

	function delete($account_id)
	{
		throw new \Exception(__METHOD__.'() not (yet) implemented in fixture - only needed for the account_import_update_source write-back path');
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
