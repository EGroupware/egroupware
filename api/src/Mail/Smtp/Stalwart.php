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
	 * Capabilities of this class (pipe-separated): default, forward
	 */
	const CAPABILITIES = 'default';

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
	 * Hook called when group is added or updated
	 *
	 * @param array $data values for keys "location", "account_id", "account_lid", "account_email" and "mailbox"
	 * @return void
	 */
	function updateGroup($data)
	{
		$this->group($data['account_id'], $data['account_lid'],
			$data['account_email'] ?? Api\Accounts::id2name($data['account_id']));

		parent::updateGroup($data);
	}

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

		// we check the SQL user-data, if we have a Stalwart accountId stored in mail_type=0=TYPE_ENABLED
		if (($userData = parent::getUserData($user, $match_uid_at_domain)))
		{
			if ($userData['accountStatus'] === self::MAIL_ENABLED)
			{
				unset($userData['accountStatus']);
			}
			elseif (!empty($userData['accountStatus']))
			{
				$response = $this->jmapClient()->jmapCall([
					['x:Account/get', [
						'ids' => [$userData['accountStatus']],
					], 'c'],
				], [Jmap::JMAP_CORE, self::USING_STALWART]);

				$account = current($response['methodResponses'][0][1]['list'] ?? []);
			}
		}

		$domainId = $this->domainId($this->defaultDomain) ?? throw new \Exception("Domain '$this->defaultDomain' not found!");
		if (!isset($account))
		{
			$response = $this->jmapClient()->jmapCall([
				['x:Account/query', [
					'filter' => [
						'name' => self::name2stalwart($account_lid),
						'domainId' => $domainId,
					],
				], 'b'],
				['x:Account/get', [
					'#ids' => ['name' => 'x:Account/query', 'path' => '/ids', 'resultOf' => 'b'],
				], 'c'],
			], [Jmap::JMAP_CORE, self::USING_STALWART]);

			$account = current($response['methodResponses'][1][1]['list'] ?? []);
		}
		if ($account && $account['domainId'] === $domainId)
		{
			$aliases = [];
			foreach($account['aliases'] as $alias)
			{
				if ($alias['enabled'])
				{
					$aliases[] = $alias['name'].'@'.$this->domain($account['domainId']);
				}
			}
			// add mailing lists as aliases too
			$lists = $this->getMailingListByRecipient($account['emailAddress']);
			foreach($lists as $list)
			{
				$aliases[] = $list['emailAddress'];
			}
			// check if EGroupware's primary email is just an alias for Stalwart --> preserve it
			if (($key = array_search(strtolower($userData['mailLocalAddress']), $aliases)) !== false)
			{
				$email = strtolower($userData['mailLocalAddress']);
				$aliases[] = $account['emailAddress'];
				unset($aliases[$key]);
			}
			$userData = [
				'mailLocalAddress' =>  $email ?? $account['emailAddress'],   // preserve EGroupware's primary mail address
				'quotaLimit' => $account['quotas']['maxDiskQuota'] ?? null ? $account['quotas']['maxDiskQuota']>>20 : null, // MB
				'uid' => [$account['name']],
				'mailAlternateAddress' => array_values(array_unique($aliases)),
				'mailForwardingAddress' => [],
				//'forwardOnly' => false,
				'accountStatus' => $account['id'],
				'stalwart' => $account,
				'mailingLists' => $lists,
			];
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

		if (empty($_mailLocalAddress)) return;

		$account_lid = $this->accounts->id2name($_uidnumber) ?? throw new \Exception("Invalid user #$_uidnumber!");
		if (self::name2stalwart($_mailLocalAddress) !== self::name2stalwart($account_lid.'@'.$this->defaultDomain))
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
				'name' => self::name2stalwart($name),
				'domainId' => $domainId,
				'description' => null,  // ToDo: preserve from $userData['stalwart']['aliases']
			];
		}
		$prefs = (new Api\Preferences($_uidnumber))->read_repository();
		// ToDo: make sure combination of language and country is a valid local BEFORE sending it to Stalwart
		[$lang, $country] = explode('-', $prefs['common']['lang'])+[null, null];
		$locale = $lang.'_'.strtoupper($country ?: $prefs['common']['country']);
		$account = array_filter([
			'@type' => 'User',
			'name' => self::name2stalwart($account_lid),
			'description' => $this->accounts->id2name($_uidnumber, 'account_fullname'),
			//'emailAddress' => strtolower($account_lid.'@'.$this->defaultDomain),  // is server-set by name and domainId
			'domainId' => $this->domainId($this->defaultDomain),
			'aliases' => $aliases,
			'quotas' => ['maxDiskQuota' => $_quota ? $_quota << 20 : null],
			'locale' => $locale,
			'timeZone' => $prefs['common']['tz'] ?? 'UTC',
			'credentials' => (object)["0" => [  // otherwise PHP's json_encode encodes it as JSON array
				'@type' => 'Password',
				'secret' => $this->accounts->id2name($_uidnumber, 'account_pwd'),
			]],
			'memberGroupIds' => Jmap::boolPatch($this->groupIds(Api\Accounts::getInstance()->memberships($_uidnumber))),
		]);
		// update account in Stalwart
		if (($userData = $this->getUserData($_uidnumber)) && !empty($userData['accountStatus']) && $_accountStatus)
		{
			if (!empty($userData['stalwart']['quotas'])) $account['quotas'] = array_merge($userData['stalwart']['quotas'], $account['quotas']);
			// array_diff_assoc() does NOT work with non-scalar values!
			$diff = array_udiff_assoc($account, $userData['stalwart'], static fn($a, $b) => $a != $b);
			// trying to delete a not existing quota give a 400 Bad Request
			if (array_key_exists('quotas', $diff) && !isset($diff['quotas']['maxDiskQuota']) && !isset($userData['stalwart']['quotas']['maxDiskQuota']))
			{
				unset($diff['quotas']);
			}
			if (($diff['aliases'] = $this->aliasesPatch($account['aliases']??[], $userData['stalwart']['aliases'], $userData['mailingLists'])) == (object)[])
			{
				unset($diff['aliases']);
			}
			if (($diff['memberGroupIds'] = JMAP::boolPatch(array_keys(get_object_vars($account['memberGroupIds'])), array_keys($userData['stalwart']['memberGroupIds']))) == (object)[])
			{
				unset($diff['memberGroupIds']);
			}
			if ($diff)
			{
				while(true)
				{
					$response = $this->jmapClient()->jmapCall([
						['x:Account/set', [
							'update' => [
								($accountId=$userData['stalwart']['id']) => $diff,
							]], 'a'
						]], [Jmap::JMAP_CORE, self::USING_STALWART]);

					// check account updated
					if(array_key_exists($userData['stalwart']['id'], $response['methodResponses'][0][1]['updated'] ?? []))
					{
						break;
					}
					if ($this->checkErrorEmailTaken($response['methodResponses'][0][1]['notUpdated'][$accountId] ?? null,
						$diff['aliases'], $account['name'].'@'.$this->domain($account['domainId'])))
					{
						continue;   // --> try again updating it
					}
					// check given locale is invalid (EGroupware language and country are independent!)
					if ($response['methodResponses'][0][1]['notUpdated'][$accountId]['type'] === 'invalidPatch' &&
						in_array('locale', $response['methodResponses'][0][1]['notUpdated'][$accountId]['properties']))
					{
						// use to currently set one in Stalwart or "en_US" if nothing is set
						$diff['locale'] = $userData['stalwart']['locale'] ?? 'en_US';
						continue;
					}
					throw new \Exception("Mail account NOT updated: ".json_encode($response, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
				}
			}
			// check if a list-alias has been removed --> remove it from the list
			foreach($userData['mailingLists'] as $list)
			{
				if (!in_array($list['emailAddress'], array_map(fn($alias)=>$alias['name'].'@'.$this->domain($alias['domainId']), $account['aliases']??[])))
				{
					if (isset($list['recipients'][$email=self::name2stalwart($account_lid).'@'.$this->domain($account['domainId'])]))
					{
						unset($list['recipients'][$email]);
						$this->mailingList($list['emailAddress'], array_keys($list['recipients']));
					}
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
			if (!isset($account['quotas']['maxDiskQuota'])) unset($account['quotas']);
			if (isset($account['aliases'])) $account['aliases'] = (object)$account['aliases'];

			while(true)
			{
				$response = $this->jmapClient()->jmapCall([
					['x:Account/set', [
						'create' => [
							'new1' => $account,
						]], 'a'
					],
				], [Jmap::JMAP_CORE, self::USING_STALWART]);
				// check new account created
				if (!empty($response['methodResponses'][0][1]['created']['new1']['id']))
				{
					$accountId = $response['methodResponses'][0][1]['created']['new1']['id'];
					break;
				}
				if ($this->checkErrorEmailTaken($response['methodResponses'][0][1]['notCreated']['new1'] ?? null,
					$account['aliases'], $account['name'] . '@' . $this->domain($account['domainId'])))
				{
					continue;   // --> try again creating it
				}
				// check given locale is invalid (EGroupware language and country are independent!)
				if (in_array('locale', $response['methodResponses'][0][1]['notCreated']['new1']['properties'] ?? []))
				{
					// use "en_US" as fallback
					$account['locale'] = 'en_US';
					continue;
				}
				throw new \Exception("Mail account NOT created: ".json_encode($response, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
			}
		}
		// updating the SQL table too for now
		parent::setUserData($_uidnumber, $_mailAlternateAddress, $_mailForwardingAddress, $_deliveryMode,
			$accountId, $_mailLocalAddress, $_quota, $_forwarding_only, $_setMailbox);

		/* called by parent::setUserData(), as long as we call that
		// let interested parties know account was update
		Api\Hooks::process(array(
			'location' => 'mailaccount_userdata_updated',
			'account_id' => $_uidnumber,
		));*/

		return true;
	}

	/**
	 * Stalwart silently lowercases names and removes space (we replace space with an underscore instead)
	 *
	 * @param string|array $name
	 * @return string
	 */
	static function name2stalwart(string|array $name)
	{
		$ret = array_map(static fn($name) => strtolower(str_replace(' ', '_', $name)), (array)$name);

		return !is_array($name) ? $ret[0] : $ret;
	}

	const NON_MAILBOX_GROUP_PREFIX = 'noreply-';

	/**
	 * Sync the given groups to Stalwart, if not already exist, and return their ids
	 *
	 * @param string[] $memberships account_id => account_lid pairs
	 * @return string[]
	 */
	protected function groupIds(array $memberships)
	{
		$memberships = self::name2stalwart($memberships);
		$stalwartIds = [];
		foreach($this->db->select(self::TABLE, '*', [
			'account_id' => array_keys($memberships),
			'mail_type' => self::TYPE_ENABLED
		], __LINE__, __FILE__) as $row)
		{
			$stalwartIds[$row['account_id']] = $row['mail_value'];
		}
		$domainId = $this->domainId($this->defaultDomain) ?? throw new \Exception("Domain '$this->defaultDomain' not found!");
		$response = $this->jmapClient()->jmapCall([
			['x:Account/query', [
				'filter' => /*Jmap::filterConditions('AND', [
					Jmap::filterConditions('OR', ['name' => array_diff_key($memberships, array_flip($stalwartIds))]),*/
				[
					'domainId' => $domainId,
				],
			], 'b'],
			['x:Account/get', [
				'#ids' => ['name' => 'x:Account/query', 'path' => '/ids', 'resultOf' => 'b'],
			], 'c'],
		], [Jmap::JMAP_CORE, self::USING_STALWART]);
		foreach($response['methodResponses'][1][1]['list'] as $group)
		{
			if (($key=array_search($group['name'], $memberships)) !== false ||
				str_starts_with($group['name'], self::NON_MAILBOX_GROUP_PREFIX) &&
					($key=array_search(substr($group['name'], strlen(self::NON_MAILBOX_GROUP_PREFIX)), $memberships)) !== false)
			{
				$stalwartIds[$key] = $group['id'];
				$this->db->insert(self::TABLE, [
					'account_id' => $key,
					'mail_type' => self::TYPE_ENABLED,
					'mail_value' => $group['id'],
				], false,__LINE__, __FILE__);
			}
		}
		// create still missing groups on Stalwart side
		$methodcalls = [
			['x:Account/set', [
				'create' => []], 'a'
			],
		];
		if (($missing = array_diff_key($memberships, $stalwartIds)))
		{
			foreach ($missing as $account_id => $account_lid)
			{
				$stalwartIds[$account_id] = $this->group($account_id, $account_lid,
					Api\Accounts::id2name($account_id, 'account_email'), false);
			}
		}
		return array_values($stalwartIds);
	}

	/**
	 * Create or update group
	 *
	 * @param int $account_id
	 * @param string $account_lid
	 * @param string|null $account_email
	 * @param bool|null $exists null: check, false: does not exist, true: does already exist (but groupId not known)
	 * @param bool $is_shared_mailbox
	 * @return string group ID
	 */
	public function group(int $account_id, string $account_lid, ?string $account_email, ?bool $exists=null, bool $is_shared_mailbox=false)
	{
		$domainId = $this->domainId($this->defaultDomain);
		$name = self::name2stalwart($account_lid);
		$prefix = empty($account_email) || !$is_shared_mailbox ? self::NON_MAILBOX_GROUP_PREFIX : '';
		$account = array_filter([
			'@type' => 'Group',
			'name' => $prefix.$name,
			'description' => $account_lid,
			//'emailAddress' => strtolower($account_lid.'@'.$this->defaultDomain),  // is server-set by name and domainId
			'domainId' => $domainId,
			//'aliases' => $aliases,
			//'quotas' => ['maxDiskQuota' => $_quota ? $_quota << 20 : null],
		]);
		// check if group already exists with or without prefix
		if ($exists !== false)
		{
			$response = $this->jmapClient()->jmapCall([
				['x:Account/query', [
					'filter' => Jmap::filterConditions('AND', [
						'domainId' => $domainId,
						'name' => $name,
					]),
				], 'b'],
				['x:Account/get', [
					'#ids' => ['name' => 'x:Account/query', 'path' => '/ids', 'resultOf' => 'b'],
				], 'c'],
				['x:Account/query', [
					'filter' => Jmap::filterConditions('AND', [
						'domainId' => $domainId,
						'name' => self::NON_MAILBOX_GROUP_PREFIX.$name,
					]),
				], 'd'],
				['x:Account/get', [
					'#ids' => ['name' => 'x:Account/query', 'path' => '/ids', 'resultOf' => 'd'],
				], 'e'],
			], [Jmap::JMAP_CORE, self::USING_STALWART]);
		}
		if ($exists !== false && ($groupId = $response['methodResponses'][0][1]['ids'][0] ?? $response['methodResponses'][2][1]['ids'][0] ?? null))
		{
			$group = current($response['methodResponses'][1][1]['list']) ?: current($response['methodResponses'][3][1]['list']);
			if (($diff=array_diff_assoc($account, $group)))
			{
				// update group
				$response = $this->jmapClient()->jmapCall([
					['x:Account/set', [
						'update' => [$groupId => $diff],
					], 'a'],
				], [Jmap::JMAP_CORE, self::USING_STALWART]);
			}
		}
		else
		{
			// create group
			$response = $this->jmapClient()->jmapCall([
				['x:Account/set', [
					'create' => ['new1' => $account],
				], 'a'],
			], [Jmap::JMAP_CORE, self::USING_STALWART]);
			$groupId = $response['methodResponses'][0][1]['created'][0] ?? throw new \Exception("Could not create group '$account_lid'!");
			$this->db->insert(self::TABLE, [
				'account_id' => $account_id,
				'mail_type' => self::TYPE_ENABLED,
				'mail_value' => $groupId,
			], false,__LINE__, __FILE__);
		}
		// update mailing list, if required
		if (!empty($account_email) && !$is_shared_mailbox)
		{
			$this->mailingList($account_email, array_values(array_filter(array_map(fn($account_id) => Api\Accounts::id2name($account_id, 'account_email'),
					Api\Accounts::getInstance()->members($account_id, true)))), null, [], lang('Group').' '.$account_lid);
		}
		// remove evtl. existing mailing-list
		else
		{
			if (empty($account_email))
			{
				$account_email = $account['account_lid'].'@'.$this->defaultDomain;
			}
			foreach ($this->getMailingListByRecipient($account_email, false, true) as $list)
			{
				if (strtolower($list['emailAddress']) === strtolower($account_email))
				{
					$response = $this->jmapClient()->jmapCall([
						['x:Account/set', [
							'delete' => [$list['id']]
						], 'a'],
					], [Jmap::JMAP_CORE, self::USING_STALWART]);
					if (!array_key_exists($list['id'], $response['methodResponses'][0][1]['deleted']))
					{
						throw new \Exception("Could not delete mailing list '$account_email'!");
					}
					break;
				}
			}
		}
		return $groupId;
	}

	/**
	 * Check error caused by one email/alias already used by an other object
	 *
	 * @param ?array $error notCreated / notUpdated error for account to update or create
	 * @param array[] $aliases array of array with values for name and domainId
	 * @return bool true: shared alias was replaced with a mailing-list
	 * @throw \Exception on error
	 */
	function checkErrorEmailTaken(?array $error, array|object &$aliases, $email)
	{
		if (($error['type']??null) === 'primaryKeyViolation' &&
			in_array('email', $error['properties']??[]) &&
			!empty($error['objectId']['id']) &&
			($object = $this->get($error['objectId']['object'], $error['objectId']['id'])))
		{
			$key = array_search($object['emailAddress'], $emails = $this->aliases2emails($aliases));

			switch($error['objectId']['object'])
			{
				// alias address already hold by a mailing-list --> add the other account too
				case 'MailingList':
					if ($key !== false)
					{
						$this->mailingList($object['emailAddress'], array_merge(array_keys($object['recipients']), (array)$email));
						if (is_object($aliases))
						{
							unset($aliases->$key);
						}
						else
						{
							unset($aliases[$key]);
						}
						return true;    // $email added to MailingList
					}
					break;

				// alias address hold by another account --> remove alias and add both to new mailing-list
				case 'Account':
					// alias is primary email of another account --> nothing we want to do to allow that currently
					if ($key !== false)
					{
						throw new \Exception("Account '$object[name]' already exists!");
					}
					// alias is shared with another account --> create new mailing-list and add both
					foreach(array_intersect($aliases, $this->aliases2emails($object['aliases'])) as $key => $alias_email)
					{
						$alias_key = $this->aliasKey($object['aliases'], [
							'name' => explode('@', $alias_email)[0],
							'domainId' => $this->domainId(explode('@', $alias_email)[1]),
						]);
						// remove offending alias first
						$response = $this->jmapClient()->jmapCall([
							['x:Account/set', [
								'update' => [$object['id'] => ['aliases' => [$alias_key => null]]],
							], 'a']
						], [Jmap::JMAP_CORE, self::USING_STALWART]);
						if (!array_key_exists($object['id'], $response['methodResponses'][0][1]['update']))
						{
							throw new \Exception("Could not update account $object[email] to remove alias '$alias_email'!");
						}
						if (is_object($aliases))
						{
							unset($aliases->$key);
						}
						else
						{
							unset($aliases[$key]);
						}
						// then create new mailing list
						$this->mailingList($alias_email, [$email, $object['emailAddress']]);
					}
					return true;
			}
		}
		return false;
	}

	/**
	 * Convert aliases (array with values for key "name" and "domainId") to email-addresses
	 *
	 * @param array[]|object $aliases
	 * @return string[] email addresses
	 */
	public function aliases2emails(array|object $aliases) : array
	{
		return array_map(fn($alias) => $alias['name'].'@'.$this->domain($alias['domainId']),
			is_array($aliases) ? $aliases : get_object_vars($aliases));
	}

	/**
	 * Create or update a distribution-/mailing-list for the given email address with given members
	 *
	 * @param string $email
	 * @param array $recipients email-addresses
	 * @param string|null $emailAccount accountId currently holding the $email
	 * @param array $aliases further alias email-addresses for the distribution list
	 * @param ?string $description
	 * @throws \Exception on error
	 * @return string mailing-list ID
	 */
	public function mailingList(string $email, array $recipients, ?string $emailAccount=null, array $aliases=[], ?string $description=null)
	{
		[$name, $domain] = explode('@', $email);
		$domainId = $this->domainId($domain);
		$mailing_list = [
			'name' => self::name2stalwart($name),
			'domainId' => $domainId,
			'recipients' => (object)array_combine(array_values($recipients), array_fill(0, count($recipients), true)),
			'aliases' => $this->aliasesPatch($aliases),
			'description' => $description,
		];
		if (!$emailAccount)
		{
			if (($stalwart_mailing_list = $this->getMailingListByRecipient($email)[0] ?? null) &&
				$stalwart_mailing_list['emailAddress'] !== $email)
			{
				$stalwart_mailing_list = null;
			}
		}
		// remove $email from account currently holding it
		else
		{
			$response = $this->jmapClient()->jmapCall([
				['x:Account/get', [
					'id' => $emailAccount,
				], "a"],
			]);
			if (($aliases = $response['methodResponses'][0][1]['list']['id']['aliases'] ?? null) &&
				($key = $this->aliasKey($aliases, [
					'name' => $name,
					'domainId' => $domainId,
				])))
			{
				$aliases[$key] = null;
				$response = $this->jmapClient()->jmapCall([
					['x:Account/set', [
						'update' => [
						'id' => ['aliases' => $aliases],
					]], "a"],
				], [Jmap::JMAP_CORE, self::USING_STALWART]);
			}
		}
		// create mailing-list
		if (empty($stalwart_mailing_list))
		{
			$response = $this->jmapClient()->jmapCall([
				['x:MailingList/set', [
					'create' => [
						'new1' => $mailing_list,
					]
				], 'a']
			], [Jmap::JMAP_CORE, self::USING_STALWART]);
			$listId = $response['methodResponses'][0][1]['created']['new1']['id'] ??
				throw new \Exception("Mailing list '$email' NOT created: ".json_encode($response, JSON_UNESCAPED_SLASHES));
		}
		// update mailing list, if there is a change in recipients
		elseif (($recipient_changes = self::emailPatch($mailing_list['recipients'], $stalwart_mailing_list['recipients'])) != (object)[])
		{
			$response = $this->jmapClient()->jmapCall([
				['x:MailingList/set', [
					'update' => [
						$stalwart_mailing_list['id'] => ['recipients' => $mailing_list['recipients']],
					]
				], 'a']
			], [Jmap::JMAP_CORE, self::USING_STALWART]);
			if (!array_key_exists($stalwart_mailing_list['id'], $response['methodResponses'][0][1]['updated'] ?? []))
			{
				throw new \Exception("Mailing list '$mailing_list[emailAddress]' NOT updated: " . json_encode($response, JSON_UNESCAPED_SLASHES));
			}
		}
		return $stalwart_mailing_list['id'] ?? $listId;
	}

	/**
	 * Get all mailing-lists with the given recipient or email
	 *
	 * @param string $recipient email-address
	 * @param bool $return_just_email false: return full mailing-lists, true: return email address of mailing-list
	 * @param bool $return_groups_too false: return no groups, true: also return groups
	 * @return array
	 */
	public function getMailingListByRecipient(string $recipient, bool $return_just_email=false, bool $return_groups_too=false) : array
	{
		$response = $this->jmapClient()->jmapCall([
			['x:MailingList/query', [
				'filter' => [
					'text' => $recipient,
				]
			], 'a'],
			['x:MailingList/get', [
				'#ids' => ['name' => 'x:MailingList/query', 'path' => '/ids', 'resultOf' => 'a'],
			], 'b']
		], [Jmap::JMAP_CORE, self::USING_STALWART]);
		$lists = $response['methodResponses'][1][1]['list'] ?? [];

		// as we can only search field-unspecific, we have to make sure the returned lists really contain $recipient OR use it as emailAddress
		$lists = array_filter($lists, fn($list) => isset($list['recipients'][$recipient]) || $list['emailAddress'] === $recipient);

		// should we return groups used as distribution lists
		if (!$return_groups_too)
		{
			$lists = array_filter($lists, function($list) {
				return Api\Accounts::getInstance()->get_type($list['name']) !== 'g';
			});
		}

		if ($return_just_email)
		{
			return array_map(fn($mailing_list) => $mailing_list['emailAddress'], $lists);
		}
		return $lists;
	}

	/**
	 * JMAP query for one or more objects
	 *
	 * @param string $what name of object e.g. "Account"
	 * @param array $filter JMAP filter
	 * @param bool $multiple false (default): throw if more then one result, true: return array of matches
	 * @return array|null
	 * @throw \Exception for more the one result
	 */
	protected function query(string $what, array $filter, bool $multiple=false) : ?array
	{
		$response = $this->jmapClient()->jmapCall([
			["x:$what/query", [
				'filter' => $filter,
			], 'a']
		], [Jmap::JMAP_CORE, self::USING_STALWART]);

		if ($multiple)
		{
			return $response['methodResponses'][0][1]['list'] ?? [];
		}
		if (count($response['methodResponses'][0][1]['list'] ?? []) > 1)
		{
			throw new \Exception("Query for $what object returned more then one result, filter: ".
				json_encode($response, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
		}
		return current($response['methodResponses'][0][1]['list'] ?? []);
	}

	/**
	 * JMAP get for one object by its type and ID
	 *
	 * @param string $what name of object e.g. "Account"
	 * @param string $id id
	 * @return array|null
	 * @throw \Exception for more the one result
	 */
	protected function get(string $what, string $id) : ?array
	{
		$response = $this->jmapClient()->jmapCall([
			["x:$what/get", [
				'id' => $id,
			], 'a']
		], [Jmap::JMAP_CORE, self::USING_STALWART]);

		return $response['methodResponses'][0][1]['list'][0] ??
			throw new \Exception("Get for $what object with id='$id' returned no result: ".
			json_encode($response, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
	}

	/**
	 * Find key / attribute name of given alias
	 *
	 * @param array|object $aliases array or object of aliases
	 * @param string|array $find alias object or emailAddress to search
	 * @return string|null key of alias or null, if not find
	 */
	public function aliasKey(array|object $aliases, string|array $find) : ?string
	{
		if (is_string($find))
		{
			$find = [
				'name' => explode('@', $find)[0],
				'domainId' => $this->domainId(explode('@', $find)[1]),
			];
		}
		foreach($aliases as $id => $alias)
		{
			if ($alias['name'] === $find['name'] && $alias['domainId'] === $find['domainId'])
			{
				return $id;
			}
		}
		return null;
	}

	/**
	 * ML recipients is a map emailAddress => true
	 *
	 * @param array|object $new new email address
	 * @param array|object|null $old existing ones
	 * @return object map or patch for aliases email => true|null
	 */
	protected static function emailPatch(array|object $new, array|object|null $old=null) : object
	{
		if (is_object($new))
		{
			$new = array_keys(get_object_vars($new));
		}
		if (is_object($old))
		{
			$old = array_keys(get_object_vars($old));
		}
		elseif (is_array($old) && is_string(key($old)))
		{
			$old = array_keys($old);
		}
		$recipients = [];
		foreach($old ?? [] as $email)
		{
			if (($key = array_search($email, $new)) !== false)
			{
				unset($new[$key]);
			}
			else
			{
				$recipients[$email] = null;
			}
		}
		foreach($new as $email)
		{
			$recipients[$email] = true;
		}
		return (object)$recipients;    // aliases must be an object, never an array to be a JSON patch
	}

	/**
	 * Aliases is a map, thought Stalwart uses numerical keys "0", "1", ...
	 *
	 * @param array $new
	 * @param array|object|null $old
	 * @return object map or patch for aliases
	 */
	protected function aliasesPatch(array $new, array|object|null $old=null, ?array $lists=null) : object
	{
		$aliases = [];
		foreach($old ?? [] as $key => $alias)
		{
			if (!($found=current(array_filter($new, static function($n) use ($alias)
			{
				return $n['name'] === $alias['name'] && $n['domainId'] === $alias['domainId'];
			}))))
			{
				$aliases[$key] = null;
			}
			/* EGroupware has no NOT enabled aliases
			elseif ($alias['enabled'] != $found[0]['enabled'])
			{
				$aliases[(string)$key] = $alias;
			}*/
			// alias set/unchanged in $new --> not report in patch
			else
			{
				$aliases[$key] = $alias;
				foreach($new as $k => $n)
				{
					if ($found['name'] === $n['name'] && $found['domainId'] === $n['domainId'])
					{
						unset($new[$k]);
						break;
					}
				}
			}
		}
		// remove mailing-list aliases
		foreach($lists ?? [] as $list)
		{
			if (($key = $this->aliasKey($new, $list['emailAddress'])) !== null)
			{
				unset($new[$key]);
			}
		}
		$num_old = count(is_object($old) ? get_object_vars($old) : $old ?? []);
		foreach(array_values($new) as $k => $alias)
		{
			$aliases[$num_old+$k] = $alias;
		}
		return (object)$aliases;    // aliases must be an object, never an array to be a JSON patch
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