<?php
/**
 * EGroupware Api: SMTP configuration for Stalwart
 *
 * Creates, updates and deletes mail accounts in Stalwart.
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Smtp;

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\Jmap;

/**
 * This class trys reading mail-accounts first from Stalwart,
 * and if not found there from the SQL DB / parent.
 *
 * When storing account-date it will be stored in Stalwart and the DB.
 */
class Stalwart extends Sql
{
	/**
	 * Label shown in EMailAdmin
	 */
	const DESCRIPTION = 'Stalwart';

	/**
	 * @var Jmap JMAP connection
	 */
	protected Jmap $jmap;
	/**
	 * @var bool JMAP connection is with admin rights
	 */
	protected bool $jmap_is_admin_connection;
	/**
	 * @var string JMAP accountId of the connection
	 */
	protected $jmap_accountId;

	const USING_STALWART = "urn:stalwart:jmap";

	/**
	 * Get the data of a given user
	 *
	 * Multiple accounts may match, if an email address is specified.
	 * In that case only mail routing fields "uid", "mailbox" and "forward" contain values
	 * from all accounts!
	 *
	 * @param int|string $user numerical account-id, account-name or email address
	 * @param boolean $match_uid_at_domain =true true: uid@domain matches, false only an email or alias address matches
	 * @return array with values for keys 'mailLocalAddress', 'mailAlternateAddress' (array), 'mailForwardingAddress' (array),
	 * 	'accountStatus' ("active"), 'quotaLimit' and 'deliveryMode' ("forwardOnly")
	 * @link https://stalw.art/docs/ref/object/domain/#xdomainquery
	 * @link
	 */
	function getUserData($user, $match_uid_at_domain=false)
	{
		if (strpos($user, '@') !== false)
		{
			$account_id = $this->accounts->name2id($user, 'account_email', 'u') ?:
				$this->accounts->name2id($user, 'account_lid', 'u') ?:
				throw new \InvalidArgumentException("Invalid user '$user'!");
		}
		$account_lid = Api\Accounts::id2name($account_id ?? $user) ?: throw new \InvalidArgumentException("Invalid user $user!");

		$domainId = $this->domainId($this->defaultDomain) ?? throw new \Exception("Domain '$this->defaultDomain' not found!");
		$response = $this->jmapClient()->jmapCall([
			['x:Account/query', [
				'filter' => [
					'name' => $account_lid,
					'domainId' => $domainId,
				],
			], 'b'],
			['x:Account/get', [
				'#ids' => ['name' => 'x:Account/query', 'path' => '/ids', 'resultOf' => 'b'],
			], 'c'],
		], [Jmap::JMAP_CORE, self::USING_STALWART]);

		// we have to manually filter the returned accounts by their domainId, as Stalwart does not support filtering by domainId
		foreach($response['methodResponses'][1][1]['list'] as $account)
		{
			if ($account['domainId'] === $domainId) break;
		}
		if ($account['domainId'] === $domainId)
		{
			$aliases = [];
			foreach($account['aliases'] as $alias)
			{
				if ($alias['enabled'])
				{
					$aliases[] = $alias['name'].'@'.$this->domain($account['domainId']);
				}
			}
			$userData = [
				'mailLocalAddress' => $account['emailAddress'],
				'quotaLimit' => $account['quotas']['maxDiskQuota'] ?? null ? $account['quotas']['maxDiskQuota']>>20 : null, // MB
				'uid' => [$account['name']],
				'mailAlternateAddress' => array_unique($aliases),
				'mailForwardingAddress' => [],
				//'forwardOnly' => false,
				'accountStatus' => self::MAIL_ENABLED,
				'stalwart' => $account,
			];
		}
		// we return the SQL user-data, but not as active, as not in Stalwart yet
		elseif (($userData = parent::getUserData($user, $match_uid_at_domain)))
		{
			unset($userData['accountStatus']);
		}
		if (!empty($this->debug)) error_log(__METHOD__."('$user') returning ".array2string($userData));
		return $userData;
	}

	/**
	 * Set the data of a given user
	 *
	 * @param int $_uidnumber numerical user-id
	 * @param array $_mailAlternateAddress
	 * @param array $_mailForwardingAddress
	 * @param string $_deliveryMode
	 * @param string $_accountStatus
	 * @param string $_mailLocalAddress
	 * @param int $_quota in MB
	 * @param boolean $_forwarding_only =false true: store only forwarding info, used internally by saveSMTPForwarding
	 * @param string $_setMailbox =null used only for account migration
	 * @return boolean true on success, false on error writing to ldap
	 */
	function setUserData($_uidnumber, array $_mailAlternateAddress, array $_mailForwardingAddress, $_deliveryMode,
	                     $_accountStatus, $_mailLocalAddress, $_quota, $_forwarding_only=false, $_setMailbox=null)
	{
		if (!empty($this->debug)) error_log(__METHOD__ . "($_uidnumber, " . array2string($_mailAlternateAddress) . ', ' . array2string($_mailForwardingAddress) . ", '$_deliveryMode', '$_accountStatus', '$_mailLocalAddress', $_quota, forwarding_only=" . array2string($_forwarding_only) . ') ' . function_backtrace());

		$account_lid = $this->accounts->id2name($_uidnumber) ?? throw new \Exception("Invalid user #$_uidnumber!");
		if (strtolower($_mailLocalAddress) !== strtolower($account_lid.'@'.$this->defaultDomain))
		{
			array_unshift($_mailAlternateAddress, $_mailLocalAddress);
		}
		$aliases = [];
		foreach(array_unique($_mailAlternateAddress) as $alias)
		{
			[$name, $domain] = explode('@', strtolower($alias));
			if (!($domainId = $this->domainId($domain)))
			{
				// ToDo: create domain
				throw new \Exception("Domain '$domain' not found --> create it!");
			}
			$aliases[] = [
				'enabled' => true,
				'name' => $name,
				'domainId' => $domainId,
				'description' => null,  // ToDo: preserve from $userData['stalwart']['aliases']
			];
		}
		$account = array_filter([
			'@type' => 'User',
			'name' => $account_lid,
			'description' => $this->accounts->id2name($_uidnumber, 'account_fullname'),
			//'emailAddress' => strtolower($account_lid.'@'.$this->defaultDomain),  // is server-set by name and domainId
			'domainId' => $this->domainId($this->defaultDomain),
			'aliases' => self::aliasesPatch($aliases),
			'quotas' => ['maxDiskQuota' => $_quota ? $_quota << 20 : null],
			/* ToDo: seems like credentials can not be updated with pre-hashed passwords :(
			'credentials' => [[
				'@type' => 'Password',
				'secret' => $this->accounts->id2name($_uidnumber, 'account_pwd'),
			]]*/
		]);
		// update account in Stalwart
		if (($userData = $this->getUserData($_uidnumber)) && !empty($userData['accountStatus']) && $_accountStatus)
		{
			if (!empty($userData['stalwart']['quotas'])) $account['quotas'] = array_merge($userData['stalwart']['quotas'], $account['quotas']);
			// array_diff_assoc() does NOT work with non-scalar values!
			$diff = array_udiff_assoc($account, $userData['stalwart'], static fn($a, $b) => $a != $b);
			// trying to delete a not existing quota give a 400 Bad Request
			if (array_key_exists('quotas', $diff) && !isset($diff['quotas']) && !isset($userData['stalwart']['quotas']['maxDiskQuota']))
			{
				unset($diff['quotas']);
			}
			if (!($diff['aliases'] = self::aliasesPatch($account['aliases'], $userData['stalwart']['aliases'])))
			{
				unset($diff['aliases']);
			}
			if ($diff)
			{
				$response = $this->jmapClient()->jmapCall([
					['x:Account/set', [
						'update' => [
							$userData['stalwart']['id'] => $diff,
						]], 'a'
					]], [Jmap::JMAP_CORE, self::USING_STALWART]);

				if (array_key_exists($userData['stalwart']['id'], $response['methodResponses'][0][1]['updated'] ?? []))
				{
					throw new \Exception("Mail account NOT updated: ".json_encode($response, JSON_UNESCAPED_SLASHES));
				}
			}
		}
		// deactivate account in Stalwart
		elseif ($userData && !empty($userData['accountStatus']) && $_accountStatus)
		{
			// ToDo
		}
		elseif (empty($userData['accountStatus']) && $_accountStatus)
		{
			$account['@type'] = 'User';
			$response = $this->jmapClient()->jmapCall([
				['x:Account/set', [
					'create' => [
						'new1' => $account,
					]], 'a'
				],
				/* no sure how to specify the accountId
				['x:AccountPassword/set', [
					'update' => [
						'singleton' => [
							'secret' => $this->accounts->id2name($_uidnumber, 'account_pwd'),
						]
					]
				], 'b']*/
			], [Jmap::JMAP_CORE, self::USING_STALWART]);
			// no new account created
			if (empty($response['methodResponses'][0]['x:Account/set']['created']['new1']['id']))
			{
				throw new \Exception("Mail account NOT created: ".json_encode($response, JSON_UNESCAPED_SLASHES));
			}
		}
		// updating the SQL table too for now
		parent::setUserData($_uidnumber, $_mailAlternateAddress, $_mailForwardingAddress, $_deliveryMode,
			$_accountStatus, $_mailLocalAddress, $_quota, $_forwarding_only, $_setMailbox);

		/* called by parent::setUserData(), as long as we call that
		// let interested parties know account was update
		Api\Hooks::process(array(
			'location' => 'mailaccount_userdata_updated',
			'account_id' => $_uidnumber,
		));*/

		return true;
	}

	/**
	 * Aliases is a map, thought Stalwart uses numerical keys "0", "1", ...
	 *
	 * @param array $new
	 * @param array $old
	 * @return array map or patch for aliases
	 */
	protected function aliasesPatch(?array $new, array $old=[])
	{
		$aliases = [];
		foreach($old as $key => $alias)
		{
			if (!($found=array_filter($new, function($n, $k) use ($alias)
			{
				return $n['name'] === $alias['name'] && $n['domainId'] === $alias['domainId'];
			})))
			{
				$aliases[(string)$key] = null;
			}
			/* EGroupware has no NOT enabled aliases
			elseif ($alias['enabled'] != $found[0]['enabled'])
			{
				$aliases[(string)$key] = $alias;
			}*/
			// alias set/unchanged in $new --> not report in patch
			else
			{
				foreach($new as $k => $n)
				{
					if ($found[0]['name'] === $alias['name'] && $found[0]['domainId'] === $alias['domainId'])
					{
						unset($new[$k]);
					}
				}
			}
		}
		// for new aliases we make up some string keys, to php creates a JSON map/object not an array
		foreach($new as $k => $alias)
		{
			unset($alias['description']);
			$aliases["new$k"] = $alias;
		}
		return $aliases;
	}

	/**
	 * Return Jmap client
	 *
	 * @param bool $adminConnection true: return jmapClient with admin rights, false: jmapClient for the current user
	 * @return Mail\Jmap
	 */
	public function jmapClient(bool $adminConnection=true)
	{
		if (!isset($this->jmap) || $adminConnection !== $this->jmap_is_admin_connection)
		{
			if (($this->jmap_is_admin_connection = $adminConnection))
			{
				if (!$this->account->acc_imap_admin_username || !$this->account->acc_imap_admin_password)
				{
					throw new \InvalidArgumentException("No admin username or password!");
				}
				$this->jmap = new Mail\Jmap($this->account->acc_smtp_host,
					$this->account->acc_imap_admin_username,
					$this->account->acc_imap_admin_password);
			}
			else
			{
				$this->jmap = new Mail\Jmap($this->host, $this->acc_smtp_username, $this->acc_smtp_password, $this->jmap_accountId);
			}
		}
		return $this->jmap;
	}

	protected static $domainIds;

	/**
	 * Get the domainId of the given domain
	 *
	 * @param string $domain
	 * @return string|null
	 */
	function domainId(string $domain) : ?string
	{
		if (self::$domainIds === null)
		{
			self::$domainIds = Api\Cache::getInstance(__CLASS__, 'domainIds-'.$this->account->acc_smtp_host) ?? [];
		}
		if (!array_key_exists($domain, self::$domainIds))
		{
			$response = $this->jmapClient()->jmapCall([
				['x:Domain/query', [
					'filter' => ['name' => $domain],
				], 'a']
			], [Jmap::JMAP_CORE, self::USING_STALWART]);
			self::$domainIds[$domain] = $response['methodResponses'][0][1]['ids'][0] ?? null;
			// only permanently cache returned id's, not that there was none yet
			if (isset(self::$domainIds[$domain]))
			{
				Api\Cache::setInstance(__CLASS__, 'domainIds-'.$this->account->acc_smtp_host, self::$domainIds);
			}
		}
		return self::$domainIds[$domain];
	}

	function domain(string $domainId) : ?string
	{
		if (self::$domainIds === null)
		{
			self::$domainIds = Api\Cache::getInstance(__CLASS__, 'domainIds-'.$this->account->acc_smtp_host) ?? [];
		}
		if (!($domain = array_search($domainId, self::$domainIds, true)))
		{
			$response = $this->jmapClient()->jmapCall([
				['x:Domain/query', [
					'filter' => ['id' => $domainId],
				], 'a']
			], [Jmap::JMAP_CORE, self::USING_STALWART]);
			// only permanently cache returned id's, not that there was none yet
			if (($domain = $response['methodResponses'][0][1]['name'][0] ?? null))
			{
				self::$domainIds[$domain] = $domainId;
				Api\Cache::setInstance(__CLASS__, 'domainIds-'.$this->account->acc_smtp_host, self::$domainIds);
			}
		}
		return $domain;
	}
}