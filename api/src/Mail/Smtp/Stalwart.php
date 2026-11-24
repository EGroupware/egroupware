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
use EGroupware\Api\Mail\Jmap\Http as JmapHttp;

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
	 * true: an alias, which is the email address of an other account, is skipped (in favor of the account), instead of
	 * giving an error. Used by the REST API, eg. the migration to Stalwart must not fail because of that.
	 */
	public static bool $skipTakenAliases = false;

	/**
	 * Warnings (translated messages) of skipped aliases, see self::$skipTakenAliases
	 *
	 * @var string[]
	 */
	public static array $warnings = [];

	/**
	 * Maximum number of tries to create or update an account, while fixing the reported problems (taken email address, locale)
	 */
	const MAX_RETRIES = 10;

	/**
	 * Capabilities of this class (pipe-separated): default, forward
	 */
	const CAPABILITIES = 'default';

	/**
	 * @var JmapHttp JMAP connection
	 */
	protected JmapHttp $jmap;
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
	 * Dispatches on the group's explicit Stalwart-sync mode (groupSyncMode()) - 'none' syncs
	 * nothing (and cleans up anything from a previous mode), 'acl' and 'list' are today's
	 * pre-existing group()/mailingList() behaviour, now explicit choices instead of an implicit
	 * "always ACL, plus a list whenever there's an email" combination. 'mailbox' creates/maintains
	 * a real, unprefixed shared mailbox and ACL-grants full (but not mayShare) access to every
	 * current member, via syncMailboxShares() - confirmed live (2026-11-10) that the admin JMAP
	 * session accepts Mailbox/get+set against an arbitrary accountId, not just its own.
	 *
	 * @param array $data values for keys "location", "account_id", "account_lid", "account_email",
	 *  "stalwart_sync" (new, one of groupSyncMode()'s GROUP_SYNC_* values - only present when the
	 *  admin UI's new selector submitted one) and "mailbox"
	 * @return void
	 */
	function updateGroup($data)
	{
		$account_id = $data['account_id'];
		$account_lid = $data['account_lid'];
		$account_email = $data['account_email'] ?? Api\Accounts::id2name($account_id, 'account_email');

		$old_mode = $this->groupSyncMode($account_id, $account_email);
		$mode = $data['stalwart_sync'] ?? $old_mode;

		switch ($mode)
		{
			case self::GROUP_SYNC_NONE:
				$this->deleteGroupPrincipal($account_id, $account_lid);
				break;

			case self::GROUP_SYNC_ACL:
				$this->group($account_id, $account_lid, null);
				break;

			case self::GROUP_SYNC_LIST:
				$this->group($account_id, $account_lid, $account_email);
				break;

			case self::GROUP_SYNC_MAILBOX:
				$groupId = $this->group($account_id, $account_lid, $account_email, null, true);
				$this->syncMailboxShares($account_id, $groupId);
				break;
		}
		// leaving GROUP_SYNC_MAILBOX for acl/list: keep the mailbox itself, just revoke every
		// member's ACL share on the way out (the group principal survives, renamed/reused by the
		// group()-based branches above). Leaving it for 'none' instead: deleteGroupPrincipal() above
		// already deleted the whole mailbox (shares and all, see its own docblock for why that's now
		// fine), so revoking shares here would be a pointless no-op against an account that no
		// longer exists - skip it.
		if ($old_mode === self::GROUP_SYNC_MAILBOX && !in_array($mode, [self::GROUP_SYNC_MAILBOX, self::GROUP_SYNC_NONE], true))
		{
			$this->revokeAllMailboxShares($account_lid);
		}
		$this->saveGroupSyncMode($account_id, $mode);

		parent::updateGroup($data);
	}

	/**
	 * Hook called when an (existing) account is edited --> sync it to Stalwart
	 *
	 * Mail\Smtp::updateAccount() (the base class this extends) is a plain no-op stub - Stalwart
	 * needs to actually sync changed account data (incl. the password, via setUserData()'s own
	 * "credentials" handling - see changePassword()'s docblock for why that's the right value to
	 * send), same as it does for account creation.
	 *
	 * Deliberately does NOT just reuse addAccount()'s own mailLocalAddress computation
	 * ($data['account_email'] ?: Api\Accounts::email($firstname, $lastname, $lid, ...)) for an
	 * account that's already provisioned in Stalwart: that computation returns null whenever
	 * account_email is unset on the core account AND email_address_format is configured 'none'
	 * (confirmed live on this install, for accounts incl. real ones like "test3") - setUserData()
	 * then silently no-ops on empty($_mailLocalAddress), so nothing (not just the password) would
	 * ever sync for such an account. Reusing the address Stalwart itself already has on file
	 * avoids re-deriving it at all for the (normal) "account already exists there" case.
	 *
	 * @param array $data values for keys 'account_email', 'account_firstname', 'account_lastname', 'account_lid', 'account_id'
	 * @return bool
	 */
	function updateAccount($data)
	{
		$account_id = !empty($data['account_id']) ? $data['account_id'] :
			$this->accounts->name2id($data['account_lid'], 'account_lid', 'u');

		if (($userData = $this->getUserData($account_id)) && !empty($userData['stalwart']['id']))
		{
			// pass the existing aliases, forwards and quota, otherwise setUserData() removes the aliases, or fails to unset the quota
			return $this->setUserData($account_id, $userData['mailAlternateAddress'] ?? [], $userData['mailForwardingAddress'] ?? [], null,
				self::MAIL_ENABLED, $userData['mailLocalAddress'], $userData['quotaLimit'] ?? null);
		}
		// not (yet) provisioned in Stalwart at all - fall back to addAccount()'s own derivation
		return $this->addAccount($data);
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
	function getUserData($user, $match_uid_at_domain=false, bool $no_cache=false)
	{
		if (strpos($user, '@') !== false)
		{
			$account_id = $this->accounts->name2id($user, 'account_email', 'u') ?:
				$this->accounts->name2id($user, 'account_lid', 'u') ?:
				throw new \InvalidArgumentException("Invalid user '$user'!");
		}
		$account_lid = Api\Accounts::id2name($account_id ?? $user) ?: throw new \InvalidArgumentException("Invalid user $user!");

		// we check the SQL user-data, if we have a Stalwart accountId stored in mail_type=0=TYPE_ENABLED
		if (($userData = parent::getUserData($user, $match_uid_at_domain, $no_cache)))
		{
			if ($userData['accountStatus'] === self::MAIL_ENABLED)
			{
				unset($userData['accountStatus']);
			}
			elseif (($userData['accountStatus'] ?? '') !== '')    // Stalwart ids can be "0", which empty() treats as unset
			{
				$response = $this->jmapClient()->jmapCall([
					['x:Account/get', [
						'ids' => [$userData['accountStatus']],
					], 'c'],
				], [JmapHttp::JMAP_CORE, self::USING_STALWART]);

				$account = current($response['methodResponses'][0][1]['list'] ?? []);
			}
			$userData['mailAlternateAddress'] ??= [];
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
			], [JmapHttp::JMAP_CORE, self::USING_STALWART]);

			$account = current($response['methodResponses'][1][1]['list'] ?? []);
		}
		if ($account && $account['domainId'] === $domainId)
		{
			$aliases = [];
			foreach($account['aliases'] as $alias)
			{
				if ($alias['enable'] ?? $alias['enabled'] ?? true)    // Stalwart 1.0 renamed enabled to enable
				{
					$aliases[] = $alias['name'].'@'.$this->domain($alias['domainId'] ?? $account['domainId']);    // aliases can be in other domains
				}
			}
			// add mailing lists as aliases too
			$lists = $this->getMailingListByRecipient($account['emailAddress']);
			foreach($lists as $list)
			{
				$aliases[] = $list['emailAddress'];
			}
			// account not yet provisioned (no Stalwart id in SQL, SQL still holds the primary email and aliases of the old backend, eg.
			// during a migration): the SQL data wins, as setUserData() would otherwise delete the SQL aliases and reset the primary email
			$sqlQuota = null;
			if ($userData && !isset($userData['accountStatus']))
			{
				$sqlQuota = $userData['quotaLimit'] ?? null;    // MB, like the quota from Stalwart below
				// NOT $userData['mailLocalAddress']: mail_type=5 of the SQL backend is the storage location of the mailbox, which never changes,
				// the primary email is the one of the addressbook, see primaryAndAliases()
				$aliases = array_values(array_unique(array_map('strtolower', array_merge($userData['mailAlternateAddress'] ?? [], $aliases))));
			}
			[$email, $aliases] = $this->primaryAndAliases($this->primaryEmail($account_id ?? $user), $account, $aliases);
			// forwards are a Sieve rule "Forward mail to ..." of the account (see Sieve\Forward), other scripts are ignored
			$forward = $this->forward($account['id']);
			$userData = [
				'mailLocalAddress' =>  $email ?: $account['emailAddress'],   // the primary email of the addressbook
				'quotaLimit' => $account['quotas']['maxDiskQuota'] ?? null ? $account['quotas']['maxDiskQuota']>>20 : ($sqlQuota ?: null), // MB
				'quotaUsed' => isset($account['usedDiskQuota']) ? $account['usedDiskQuota']>>20 : null, // MB
				'uid' => [$account['name']],
				'mailAlternateAddress' => array_values(array_unique($aliases)),
				'mailForwardingAddress' => $forward['addresses'] ?? [],
				'deliveryMode' => $forward && !$forward['keep'] ? self::FORWARD_ONLY : null,
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
		// a primary email, which is the address of a mailing-list, can not be an alias: the list has it and includes the mailbox, see archiveMailbox()
		if (self::name2stalwart($_mailLocalAddress) !== self::name2stalwart($account_lid.'@'.$this->defaultDomain) &&
			!$this->isMailingListAddress($_mailLocalAddress))
		{
			array_unshift($_mailAlternateAddress, $_mailLocalAddress);
		}
		$aliases = $this->aliasObjects($_mailAlternateAddress, $_mailLocalAddress);
		// the mailbox address of the account itself is no alias (primary email on the alias-domain, or the mailbox address listed as alias)
		$own = ['name' => self::name2stalwart($account_lid), 'domainId' => $this->domainId($this->defaultDomain)];
		$aliases = array_values(array_filter($aliases, static fn($a) => $a['name'] !== $own['name'] || $a['domainId'] !== $own['domainId']));
		$prefs = (new Api\Preferences($_uidnumber))->read_repository();
		// ToDo: make sure combination of language and country is a valid local BEFORE sending it to Stalwart
		[$lang, $country] = explode('-', $prefs['common']['lang'])+[null, null];
		// Stalwart's locale is a BCP 47 tag (eg. "en-US"), NOT a POSIX one ("en_US") - a hyphen, not
		// an underscore, and Stalwart now rejects the POSIX form outright (used to tolerate it)
		$locale = $lang.'-'.strtoupper($country ?: $prefs['common']['country']);
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
			// Stalwart rejects an empty secret (account without password): do not send credentials then
			'credentials' => ($pwd = $this->accounts->id2name($_uidnumber, 'account_pwd')) ? (object)["0" => [  // otherwise PHP's json_encode encodes it as JSON array
				'@type' => 'Password',
				'secret' => $pwd,
			]] : null,
			'memberGroupIds' => JmapHttp::boolPatch($this->groupIds(Api\Accounts::getInstance()->memberships($_uidnumber))),
		], fn($val) => isset($val));    // keep empty array
		// update account in Stalwart
		if (($userData = $this->getUserData($_uidnumber)) && ($userData['accountStatus'] ?? '') !== '' && (string)$_accountStatus !== '')
		{
			if (!empty($userData['stalwart']['quotas'])) $account['quotas'] = array_merge($userData['stalwart']['quotas'], $account['quotas']);
			// array_diff_assoc() does NOT work with non-scalar values!
			$diff = array_udiff_assoc($account, $userData['stalwart'], static fn($a, $b) => $a != $b);
			// trying to delete a not existing quota give a 400 Bad Request
			if (array_key_exists('quotas', $diff) && !isset($diff['quotas']['maxDiskQuota']) && !isset($userData['stalwart']['quotas']['maxDiskQuota']))
			{
				unset($diff['quotas']);
			}
			// aliasesPatch() returns the complete new alias map, only send it if it differs from the current one
			if (($diff['aliases'] = $this->aliasesPatch($account['aliases']??[], $userData['stalwart']['aliases'], $userData['mailingLists'])) == (object)$userData['stalwart']['aliases'])
			{
				unset($diff['aliases']);
			}
			if (($diff['memberGroupIds'] = JmapHttp::boolPatch(array_keys(get_object_vars($account['memberGroupIds'])), array_keys($userData['stalwart']['memberGroupIds']))) == (object)[])
			{
				unset($diff['memberGroupIds']);
			}
			if ($diff)
			{
				$tries = 0;
				while(true)
				{
					// never retry forever: each retry changes $diff, so more than a few can only be an endless loop
					if (++$tries > self::MAX_RETRIES)
					{
						throw new \Exception("Mail account NOT updated after $tries tries: ".json_encode($response ?? null, JSON_UNESCAPED_SLASHES));
					}
					$response = $this->jmapClient()->jmapCall([
						['x:Account/set', [
							'update' => (object)[    // Stalwart ids can be "0", which would be encoded as JSON array
								($accountId=$userData['stalwart']['id']) => $diff,
							]], 'a'
						]], [JmapHttp::JMAP_CORE, self::USING_STALWART]);

					// check account updated
					if(array_key_exists($userData['stalwart']['id'], $response['methodResponses'][0][1]['updated'] ?? []))
					{
						break;
					}
					// $diff['aliases'] was deliberately unset above when there's no real alias change
					// to send - but checkErrorEmailTaken() needs a real array|object to inspect/mutate
					// regardless, so use a standalone copy instead of passing (or auto-vivifying) the
					// possibly-absent $diff['aliases'] directly, and only merge it back into $diff (for
					// the retry submission below) if it's now non-empty - otherwise we'd resubmit an
					// explicit empty aliases patch, wiping the account's real aliases in Stalwart
					$aliases_for_retry = $diff['aliases'] ?? (object)[];
					if ($this->checkErrorEmailTaken($response['methodResponses'][0][1]['notUpdated'][$accountId] ?? null,
						$aliases_for_retry, $account['name'].'@'.$this->domain($account['domainId'])))
					{
						// also use an empty map, if there was an alias patch: all its aliases were skipped (otherwise we would retry
						// the same patch forever)
						if ($aliases_for_retry != (object)[] || isset($diff['aliases']))
						{
							$diff['aliases'] = $aliases_for_retry;
						}
						continue;   // --> try again updating it
					}
					// check given locale is invalid (EGroupware language and country are independent!)
					if ($response['methodResponses'][0][1]['notUpdated'][$accountId]['type'] === 'invalidPatch' &&
						in_array('locale', $response['methodResponses'][0][1]['notUpdated'][$accountId]['properties']))
					{
						// use to currently set one in Stalwart or "en-US" if nothing is set
						$fallback = $userData['stalwart']['locale'] ?? 'en-US';
						if (($diff['locale'] ?? null) === $fallback)
						{
							unset($diff['locale']);    // fallback is rejected too --> do not change the locale at all
						}
						else
						{
							$diff['locale'] = $fallback;
						}
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
		elseif ($userData && ($userData['accountStatus'] ?? '') !== '' && (string)$_accountStatus !== '')
		{
			// ToDo
		}
		elseif (($userData['accountStatus'] ?? '') === '' && (string)$_accountStatus !== '')
		{
			$account['@type'] = 'User';
			if (!isset($account['quotas']['maxDiskQuota'])) unset($account['quotas']);
			if (isset($account['aliases'])) $account['aliases'] = (object)$account['aliases'];

			$tries = 0;
			while(true)
			{
				if (++$tries > self::MAX_RETRIES)
				{
					throw new \Exception("Mail account NOT created after $tries tries: ".json_encode($response ?? null, JSON_UNESCAPED_SLASHES));
				}
				$response = $this->jmapClient()->jmapCall([
					['x:Account/set', [
						'create' => [
							'new1' => $account,
						]], 'a'
					],
				], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
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
					// use "en-US" as fallback
					$account['locale'] = 'en-US';
					continue;
				}
				throw new \Exception("Mail account NOT created: ".json_encode($response, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
			}
		}
		// forwards are stored as Sieve rule in the account's script, throws if there is a script we do not understand
		if ($accountId ??= $userData['stalwart']['id'] ?? null)
		{
			$this->forward($accountId, $_mailForwardingAddress, $_deliveryMode !== self::FORWARD_ONLY);
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
	 * Hook called when a user's password gets changed --> sync it to Stalwart, if he already has an account there
	 *
	 * Uses the SAME already-hashed egw_accounts.account_pwd value setUserData() sends as the account's
	 * "credentials" secret on create/update (Api\Auth::change_password() writes the new password to
	 * that column and invalidates the accounts cache BEFORE firing the "changepassword" hook, so this
	 * always sees the new one) - not the hook's cleartext $data['new_passwd'], for consistency with
	 * that existing convention (and so nothing here ever needs to handle a cleartext credential itself).
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @return void
	 */
	function changePassword(array $data)
	{
		if (empty($data['account_id']) || !($userData = $this->getUserData($data['account_id'])) ||
			empty($userData['stalwart']['id']))
		{
			return;    // no (yet existing) Stalwart account to sync the new password to
		}
		$response = $this->jmapClient()->jmapCall([
			['x:Account/set', [
				'update' => (object)[    // Stalwart ids can be "0", which would be encoded as JSON array
					$userData['stalwart']['id'] => [
						'credentials' => (object)["0" => [    // otherwise PHP's json_encode encodes it as JSON array
							'@type' => 'Password',
							'secret' => $this->accounts->id2name($data['account_id'], 'account_pwd'),
						]],
					],
				]], 'a'
			],
		], [JmapHttp::JMAP_CORE, self::USING_STALWART]);

		if (!array_key_exists($userData['stalwart']['id'], $response['methodResponses'][0][1]['updated'] ?? []))
		{
			throw new \Exception("Error updating password for account #{$data['account_id']} in Stalwart: ".
				json_encode($response, JSON_UNESCAPED_SLASHES));
		}
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
	 * Explicit, admin-chosen Stalwart-sync modes for a group (edit-group UI, shown only when a
	 * managed Stalwart account exists) - stored per-group via groupSyncMode()/saveGroupSyncMode(),
	 * see doc/ai/projects/ for the full design (TYPE_GROUP_SYNC row in egw_mailaccounts).
	 */
	const GROUP_SYNC_NONE = 'none';		// a) no sync to Stalwart at all
	const GROUP_SYNC_ACL = 'acl';			// b) noreply-prefixed ACL/membership-only Group principal
	const GROUP_SYNC_LIST = 'list';		// c) same Group principal, PLUS a real MailingList
	const GROUP_SYNC_MAILBOX = 'mailbox';	// d) unprefixed Group used as a real shared mailbox (Phase B)

	/**
	 * Get this group's explicitly configured Stalwart-sync mode
	 *
	 * No stored row yet (a pre-existing group, from before this feature existed) --> compute AND
	 * PERSIST the default that reproduces exactly what that group's sync already did before this
	 * feature existed: GROUP_SYNC_ACL if it has no email, GROUP_SYNC_LIST if it does - so the admin
	 * UI shows a correct, already-materialized value from the very first time someone opens that
	 * group's edit dialog, not a value re-derived (and never actually stored) on every read.
	 *
	 * Public - the edit-group admin UI (admin/src/Groups.php) needs to read the current mode to
	 * pre-select it in the new selector; saveGroupSyncMode()/deleteGroupPrincipal() stay
	 * protected/internal-only, only ever reached through the normal group-save hook dispatch.
	 *
	 * @param int $account_id
	 * @param ?string $account_email pass when already known, to avoid a second accounts lookup
	 * @return string one of self::GROUP_SYNC_*
	 */
	public function groupSyncMode(int $account_id, ?string $account_email=null) : string
	{
		foreach ($this->db->select(self::TABLE, 'mail_value', [
			'account_id' => $account_id,
			'mail_type' => self::TYPE_GROUP_SYNC,
		], __LINE__, __FILE__, false, '', self::APP) as $row)
		{
			return $row['mail_value'];
		}
		$mode = empty($account_email ?? Api\Accounts::id2name($account_id, 'account_email')) ?
			self::GROUP_SYNC_ACL : self::GROUP_SYNC_LIST;
		$this->saveGroupSyncMode($account_id, $mode);
		return $mode;
	}

	/**
	 * Store a group's explicitly configured Stalwart-sync mode
	 *
	 * @param int $account_id
	 * @param string $mode one of self::GROUP_SYNC_*
	 * @return void
	 */
	protected function saveGroupSyncMode(int $account_id, string $mode) : void
	{
		$this->db->insert(self::TABLE, [
			'mail_value' => $mode,
		], [
			'account_id' => $account_id,
			'mail_type' => self::TYPE_GROUP_SYNC,
		], __LINE__, __FILE__, self::APP);
	}

	/**
	 * Delete this group's Stalwart Group principal (both the noreply-prefixed ACL-only variant AND
	 * the unprefixed, real shared-mailbox variant - whichever currently exists) and any mailing-
	 * list, if they exist - used for sync mode GROUP_SYNC_NONE, where group() itself is never
	 * called (so its own equivalent mailing-list cleanup branch, see group()'s "remove evtl.
	 * existing mailing-list" comment, never runs - this folds the same cleanup in here).
	 *
	 * Safe to call unconditionally, no confirmation needed: GROUP_SYNC_NONE means "nothing for this
	 * group on Stalwart at all", full stop, for either variant - including a group that was
	 * previously GROUP_SYNC_MAILBOX with a real, in-use shared mailbox. This used to be handled far
	 * more cautiously (ACL shares revoked, but the mailbox and its mail left in place, requiring a
	 * separate explicit "delete this group's mailbox" admin action that was never actually built -
	 * see the plan's "Mailbox-deletion policy" history), but ralf decided (2026-11-10) that's no
	 * longer warranted now that Stalwart's enterprise version backs up deleted accounts (so they
	 * can be un-deleted) and no real group-mailbox is in production use yet - the risk an automatic
	 * delete here was guarding against is gone.
	 *
	 * @param int $account_id
	 * @param string $account_lid
	 * @return void
	 */
	protected function deleteGroupPrincipal(int $account_id, string $account_lid) : void
	{
		$domainId = $this->domainId($this->defaultDomain);
		$name = self::name2stalwart($account_lid);

		// delete an existing mailing-list, if any - same lookup/delete shape as group()'s own
		// "remove evtl. existing mailing-list" branch, which is never reached for this mode
		$account_email = Api\Accounts::id2name($account_id, 'account_email') ?: $name.'@'.$this->defaultDomain;
		foreach ($this->getMailingListByRecipient($account_email, false, true) as $list)
		{
			if (strtolower($list['emailAddress']) === strtolower($account_email))
			{
				$response = $this->jmapClient()->jmapCall([
					['x:Account/set', [
						'destroy' => [$list['id']],
					], 'a'],
				], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
				if (!in_array($list['id'], $response['methodResponses'][0][1]['destroyed'] ?? []))
				{
					throw new \Exception("Could not delete mailing list '$account_email'!");
				}
				break;
			}
		}

		// delete whichever Group principal currently exists - the noreply-prefixed ACL-only one
		// (modes acl/list) or the unprefixed real shared-mailbox one (mode mailbox) - at most one
		// of the two can exist for a given group at a time, so trying both candidate names is safe
		foreach ([self::NON_MAILBOX_GROUP_PREFIX.$name, $name] as $candidateName)
		{
			$response = $this->jmapClient()->jmapCall([
				['x:Account/query', [
					'filter' => JmapHttp::filterConditions('AND', [
						'domainId' => $domainId,
						'name' => $candidateName,
					]),
				], 'b'],
			], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
			if (($groupId = $response['methodResponses'][0][1]['ids'][0] ?? null))
			{
				$response = $this->jmapClient()->jmapCall([
					['x:Account/set', [
						'destroy' => [$groupId],
					], 'a'],
				], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
				if (!in_array($groupId, $response['methodResponses'][0][1]['destroyed'] ?? []))
				{
					throw new \Exception("Could not delete group '$account_lid'!");
				}
			}
		}
		$this->db->delete(self::TABLE, [
			'account_id' => $account_id,
			'mail_type' => self::TYPE_ENABLED,
		], __LINE__, __FILE__, self::APP);
	}

	/**
	 * Resolve an email address to its JMAP Principal id, via the ADMIN session - same
	 * Principal/query pattern as Imap\Jmap::jmapPrincipalId(), which isn't reachable from here
	 * (different class, bound to a different, per-user session, not this admin-automation one).
	 *
	 * @param string $email
	 * @return ?string null if not found
	 */
	protected function jmapPrincipalId(string $email) : ?string
	{
		static $cache = [];
		if (!array_key_exists($email, $cache))
		{
			$response = $this->jmapClient()->jmapCall([
				['Principal/query', ['filter' => ['email' => $email]], 'a'],
			], [JmapHttp::JMAP_CORE, JmapHttp::JMAP_PRINCIPALS]);
			$cache[$email] = $response['methodResponses'][0][1]['ids'][0] ?? null;
		}
		return $cache[$email];
	}

	/**
	 * Full read/write/create/delete/submit access for a shared group-mailbox's own members -
	 * deliberately NOT mayShare, so a member can never themselves grant others access to the
	 * group's shared mailbox (that stays an admin-only action, via this group's own membership).
	 */
	const MAILBOX_RIGHTS_FULL_MEMBER = [
		'mayReadItems' => true,
		'mayAddItems' => true,
		'mayRemoveItems' => true,
		'maySetSeen' => true,
		'maySetKeywords' => true,
		'mayCreateChild' => true,
		'mayRename' => true,
		'mayDelete' => true,
		'maySubmit' => true,
		'mayShare' => false,
	];

	/**
	 * Grant/revoke a shared group-mailbox's own INBOX ACL shares to match current group
	 * membership - diffs live against the mailbox's own current shareWith every time (same
	 * "recompute, don't track prior grants ourselves" philosophy as GROUP_SYNC_LIST's mailing-
	 * list recipients, see group()) - no new storage needed. Only the group's own INBOX is
	 * shared (matching this mode's own "a real shared mailbox", not per-subfolder sharing).
	 *
	 * @param ?int $account_id null to revoke every existing share without granting anyone (used
	 *  by revokeAllMailboxShares() when leaving this mode - the group's own EGroupware membership
	 *  is irrelevant there, every current share must simply go)
	 * @param string $groupId this group's own (unprefixed) Stalwart Group/Account id - also the
	 *  JMAP accountId its own Mailbox objects live under
	 * @return void
	 */
	protected function syncMailboxShares(?int $account_id, string $groupId) : void
	{
		$memberEmails = $account_id === null ? [] : array_values(array_filter(array_map(
			fn($member_id) => Api\Accounts::id2name($member_id, 'account_email'),
			Api\Accounts::getInstance()->members($account_id, true))));

		$response = $this->jmapClient()->jmapCall([
			['Mailbox/query', ['accountId' => $groupId, 'filter' => ['role' => 'inbox']], 'a'],
			['Mailbox/get', [
				'accountId' => $groupId,
				'#ids' => ['name' => 'Mailbox/query', 'path' => '/ids', 'resultOf' => 'a'],
				'properties' => ['id', 'shareWith'],
			], 'b'],
		], [...JmapHttp::JMAP_MAIL, JmapHttp::JMAP_MAIL_SHARE]);
		if (!($inbox = $response['methodResponses'][1][1]['list'][0] ?? null))
		{
			throw new \Exception("Could not find group's own Inbox (accountId '$groupId') to share!");
		}
		$currentShares = array_keys((array)($inbox['shareWith'] ?? []));
		$wantedPrincipalIds = array_values(array_filter(array_map(
			fn($email) => $this->jmapPrincipalId($email), $memberEmails)));

		$patch = [];
		foreach (array_diff($wantedPrincipalIds, $currentShares) as $principalId)
		{
			$patch['shareWith/'.$principalId] = self::MAILBOX_RIGHTS_FULL_MEMBER;
		}
		foreach (array_diff($currentShares, $wantedPrincipalIds) as $principalId)
		{
			$patch['shareWith/'.$principalId] = null;
		}
		if (!$patch) return;

		$response = $this->jmapClient()->jmapCall([
			['Mailbox/set', [
				'accountId' => $groupId,
				'update' => [$inbox['id'] => $patch],
			], 'a'],
		], [...JmapHttp::JMAP_MAIL, JmapHttp::JMAP_MAIL_SHARE]);
		if (!empty($response['methodResponses'][0][1]['notUpdated'][$inbox['id']]))
		{
			throw new \Exception('Could not update group mailbox shares: '.
				json_encode($response['methodResponses'][0][1]['notUpdated'][$inbox['id']]));
		}
	}

	/**
	 * Revoke every member's ACL share on a group's own (unprefixed, real) shared mailbox, without
	 * touching the mailbox or its mail at all - the only automatic cleanup step when leaving
	 * GROUP_SYNC_MAILBOX for any other mode (see updateGroup()'s own comment and the plan's
	 * "Mailbox-deletion policy" note) - actually deleting the mailbox itself requires a separate,
	 * explicit admin action, never an automatic side effect of a mode change.
	 *
	 * @param string $account_lid
	 * @return void
	 */
	protected function revokeAllMailboxShares(string $account_lid) : void
	{
		$domainId = $this->domainId($this->defaultDomain);
		$name = self::name2stalwart($account_lid);
		$response = $this->jmapClient()->jmapCall([
			['x:Account/query', [
				'filter' => JmapHttp::filterConditions('AND', [
					'domainId' => $domainId,
					'name' => $name,	// UNPREFIXED - the real shared mailbox, not the noreply- one
				]),
			], 'b'],
		], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
		if (($groupId = $response['methodResponses'][0][1]['ids'][0] ?? null))
		{
			$this->syncMailboxShares(null, $groupId);
		}
	}

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
				'filter' => /*JmapHttp::filterConditions('AND', [
					JmapHttp::filterConditions('OR', ['name' => array_diff_key($memberships, array_flip($stalwartIds))]),*/
				[
					'domainId' => $domainId,
				],
			], 'b'],
			['x:Account/get', [
				'#ids' => ['name' => 'x:Account/query', 'path' => '/ids', 'resultOf' => 'b'],
			], 'c'],
		], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
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
		// create still missing groups on Stalwart side - unless that group's own explicitly
		// configured sync mode is GROUP_SYNC_NONE: a group explicitly opted out of Stalwart sync
		// must stay absent from memberGroupIds too, or simply syncing an unrelated member's own
		// account would silently recreate a group principal for it, defeating that choice.
		// GROUP_SYNC_MAILBOX resolves to the real, unprefixed, mailbox-enabled principal here too
		// (same one updateGroup()'s own dispatcher creates/maintains) - this method only ever
		// resolves/creates the principal id for memberGroupIds, it never touches ACL shares
		// itself (that stays this group's OWN save's job, via updateGroup()'s dispatcher).
		$methodcalls = [
			['x:Account/set', [
				'create' => []], 'a'
			],
		];
		if (($missing = array_diff_key($memberships, $stalwartIds)))
		{
			foreach ($missing as $account_id => $account_lid)
			{
				if (($mode = $this->groupSyncMode((int)$account_id)) === self::GROUP_SYNC_NONE)
				{
					continue;
				}
				$stalwartIds[$account_id] = $this->group($account_id, $account_lid,
					in_array($mode, [self::GROUP_SYNC_LIST, self::GROUP_SYNC_MAILBOX], true) ?
						Api\Accounts::id2name($account_id, 'account_email') : null,
					false, $mode === self::GROUP_SYNC_MAILBOX);
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
					'filter' => JmapHttp::filterConditions('AND', [
						'domainId' => $domainId,
						'name' => $name,
					]),
				], 'b'],
				['x:Account/get', [
					'#ids' => ['name' => 'x:Account/query', 'path' => '/ids', 'resultOf' => 'b'],
				], 'c'],
				['x:Account/query', [
					'filter' => JmapHttp::filterConditions('AND', [
						'domainId' => $domainId,
						'name' => self::NON_MAILBOX_GROUP_PREFIX.$name,
					]),
				], 'd'],
				['x:Account/get', [
					'#ids' => ['name' => 'x:Account/query', 'path' => '/ids', 'resultOf' => 'd'],
				], 'e'],
			], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
		}
		if ($exists !== false && ($groupId = $response['methodResponses'][0][1]['ids'][0] ?? $response['methodResponses'][2][1]['ids'][0] ?? null))
		{
			$group = current($response['methodResponses'][1][1]['list']) ?: current($response['methodResponses'][3][1]['list']);
			if (($diff=array_diff_assoc($account, $group)))
			{
				// update group
				$response = $this->jmapClient()->jmapCall([
					['x:Account/set', [
						'update' => (object)[$groupId => $diff],
					], 'a'],
				], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
			}
		}
		else
		{
			// create group
			$response = $this->jmapClient()->jmapCall([
				['x:Account/set', [
					'create' => ['new1' => $account],
				], 'a'],
			], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
			$groupId = $response['methodResponses'][0][1]['created']['new1']['id'] ?? throw new \Exception("Could not create group '$account_lid'!");
			$this->db->insert(self::TABLE, [
				'account_id' => $account_id,
				'mail_type' => self::TYPE_ENABLED,
				'mail_value' => $groupId,
			], false,__LINE__, __FILE__);
		}
		// update mailing list, if required
		if (!empty($account_email) && !$is_shared_mailbox)
		{
			$this->mailingList($account_email, self::listRecipients(array_map(fn($account_id) => Api\Accounts::id2name($account_id, 'account_email'),
					Api\Accounts::getInstance()->members($account_id, true)), $account_email, $this->archiveMailbox($account_email)),
				null, [], lang('Group').' '.$account_lid);
		}
		// remove evtl. existing mailing-list
		else
		{
			if (empty($account_email))
			{
				// $account has no 'account_lid' key (only '@type'/'name'/'description'/'domainId',
				// see above) - $name (this group's own Stalwart-sanitized bare name) is the correct
				// value here; using the undefined array key silently produced "@$domain" before,
				// so an existing mailing-list was never actually found/deleted when email was
				// cleared - found while wiring GROUP_SYNC_ACL/NONE through this same branch, which
				// now relies on it being correct
				$account_email = $name.'@'.$this->defaultDomain;
			}
			foreach ($this->getMailingListByRecipient($account_email, false, true) as $list)
			{
				if (strtolower($list['emailAddress']) === strtolower($account_email))
				{
					$response = $this->jmapClient()->jmapCall([
						['x:Account/set', [
							'destroy' => [$list['id']]
						], 'a'],
					], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
					if (!in_array($list['id'], $response['methodResponses'][0][1]['destroyed'] ?? []))
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
					// alias is the email address of another account: an account and a mailing-list or alias can NOT share an address
					// --> skip the alias in favor of the account (does NOT fail, eg. a migration), the alias stays in EGroupware
					if ($key !== false)
					{
						$msg = lang('Email address %1 is already the email address of account %2, it can NOT be used as alias or for a mailing-list!',
							$object['emailAddress'], $object['name']);
						if (!self::$skipTakenAliases)
						{
							throw new \Exception($msg, 409);    // 409 Conflict for the REST API
						}
						self::$warnings[] = $msg.' '.lang('Skipped.');
						if (is_object($aliases))
						{
							unset($aliases->$key);
						}
						else
						{
							unset($aliases[$key]);
						}
						return true;    // --> try again without that alias
					}
					// alias is shared with another account --> create new mailing-list and add both
					$changed = false;
					foreach(array_intersect($emails, $this->aliases2emails($object['aliases'])) as $key => $alias_email)
					{
						$changed = true;
						$alias_key = $this->aliasKey($object['aliases'], [
							'name' => explode('@', $alias_email)[0],
							'domainId' => $this->domainId(explode('@', $alias_email)[1]),
						]);
						// remove offending alias first (aliases is replaced as a whole, null is an invalid value in a map)
						$remaining = $object['aliases'];
						unset($remaining[$alias_key]);
						$response = $this->jmapClient()->jmapCall([
							['x:Account/set', [
								'update' => (object)[$object['id'] => ['aliases' => (object)$remaining]],
							], 'a']
						], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
						if (!array_key_exists($object['id'], $response['methodResponses'][0][1]['updated'] ?? []))
						{
							throw new \Exception("Could not update account $object[emailAddress] to remove alias '$alias_email': ".
								json_encode($response['methodResponses'][0][1]['notUpdated'][$object['id']] ?? $response['methodResponses'][0], JSON_UNESCAPED_SLASHES));
						}
						// further shared aliases of the same account must be removed from the aliases WITHOUT this one, otherwise
						// the update adds this alias (now held by the new mailing-list) again and fails
						$object['aliases'] = $remaining;
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
					return $changed;    // nothing changed --> retrying would give the same error again
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
			// the list holding $email itself: also a group's list (named like the group, eg. group "SV" --> sv@),
			// otherwise it is not found and creating it again fails with primaryKeyViolation
			// (array_filter() keeps the keys, so the first match is not necessarily [0])
			$stalwart_mailing_list = array_values(array_filter($this->getMailingListByRecipient($email, false, true),
				fn($list) => $list['emailAddress'] === $email))[0] ?? null;
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
				], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
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
			], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
			$listId = $response['methodResponses'][0][1]['created']['new1']['id'] ??
				throw new \Exception("Mailing list '$email' NOT created: ".json_encode($response, JSON_UNESCAPED_SLASHES));
		}
		// update mailing list, if there is a change in recipients
		elseif (($recipient_changes = self::emailPatch($mailing_list['recipients'], $stalwart_mailing_list['recipients'])) != (object)[])
		{
			$response = $this->jmapClient()->jmapCall([
				['x:MailingList/set', [
					'update' => (object)[
						$stalwart_mailing_list['id'] => ['recipients' => $mailing_list['recipients']],
					]
				], 'a']
			], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
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
		], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
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
		], [JmapHttp::JMAP_CORE, self::USING_STALWART]);

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
				'ids' => [$id],    // "id" is NOT a valid argument and gets ignored, returning ALL objects
			], 'a']
		], [JmapHttp::JMAP_CORE, self::USING_STALWART]);

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
				// removed alias: just leave it out, as "aliases" is replaced as a whole and null is an invalid value in a map
				continue;
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
		// keys of the existing aliases can have gaps (after removing one), so start after the highest one
		$next_key = ($old_keys = array_keys(is_object($old) ? get_object_vars($old) : $old ?? [])) ? max($old_keys)+1 : 0;
		foreach(array_values($new) as $k => $alias)
		{
			$aliases[$next_key+$k] = $alias;
		}
		return (object)$aliases;    // aliases must be an object, never an array to be a JSON patch
	}

	/**
	 * Return Jmap client
	 *
	 * @param bool $adminConnection true: return jmapClient with admin rights, false: jmapClient for the current user
	 * @return JmapHttp
	 */
	public function jmapClient(bool $adminConnection=true)
	{
		if (!isset($this->jmap) || $adminConnection !== $this->jmap_is_admin_connection)
		{
			if (($this->jmap_is_admin_connection = $adminConnection))
			{
				// admin credentials are either username and password or an API key (of a tenant admin), used as Bearer token
				$api_key = Api\Jmap::isApiKey($this->account->acc_imap_admin_password);
				if (!$this->account->acc_imap_admin_password || !$api_key && !$this->account->acc_imap_admin_username)
				{
					throw new \InvalidArgumentException("No admin username or password!");
				}
				$this->jmap = new JmapHttp($this->account->acc_smtp_host,
					$api_key ? '' : $this->account->acc_imap_admin_username,
					$this->account->acc_imap_admin_password);
			}
			else
			{
				$this->jmap = new JmapHttp($this->host, $this->acc_smtp_username, $this->acc_smtp_password, $this->jmap_accountId);
			}
		}
		return $this->jmap;
	}

	/**
	 * Read or set the forward of an account, which is a Sieve rule "Forward mail to ..." (see Sieve\Forward)
	 *
	 * Reading never fails (an unreadable or not EGroupware script gives no forward), setting does, if the requested forward
	 * differs from the current one and the account has a Sieve script we do not understand, as we would overwrite it.
	 *
	 * @param string $accountId Stalwart id of the account
	 * @param string[]|null $addresses =null read, otherwise forward addresses to set, empty to remove the forward
	 * @param bool $keep =false only to set: keep a copy of the mails in the mailbox, false: "Forward only"
	 * @return array|null|true read: array with keys "addresses" and "keep" or null, set: true if changed
	 * @throws \Exception
	 */
	protected function forward(string $accountId, ?array $addresses=null, bool $keep=false)
	{
		$sieve = new Api\Mail\Sieve\Jmap($this->jmapClient(), $accountId);
		if ($addresses === null)
		{
			return Api\Mail\Sieve\Forward::get($sieve);
		}
		$current = Api\Mail\Sieve\Forward::get($sieve);
		$wanted = array_values(array_unique(array_filter(array_map('trim', $addresses))));
		// nothing to do, also not for a not understood script, if we neither have a forward nor are asked to set one
		if (!$wanted && !$current ||
			$current && $wanted && array_map('strtolower', $current['addresses']) == array_map('strtolower', $wanted) && $current['keep'] === $keep)
		{
			return false;
		}
		return Api\Mail\Sieve\Forward::set($sieve, $wanted, $keep);
	}

	protected static $domainIds;

	/**
	 * Primary email of an account: the one of the addressbook (account_email)
	 *
	 * NOT mail_type=5 of the SQL backend (mailLocalAddress): that is the storage location of the mailbox, which never changes (and
	 * can be on an old domain), so it must be ignored for Stalwart.
	 *
	 * @param int|string $user numerical account-id or account-name
	 * @return string lower-case email address, empty if there is none
	 */
	protected function primaryEmail(int|string $user) : string
	{
		$account_id = is_numeric($user) ? (int)$user : $this->accounts->name2id($user, 'account_lid', 'u');
		return $account_id ? strtolower(trim((string)Api\Accounts::id2name($account_id, 'account_email'))) : '';
	}

	/**
	 * Primary email and aliases of an account, as EGroupware shows them, from the account in Stalwart
	 *
	 * The primary email is the one of the addressbook, EGroupware sends with it. Stalwart has the mailbox address (<account name>@<domain>)
	 * and aliases, so a primary email with an other name-part has to become an alias: setUserData() creates it from the primary email,
	 * also if it is on an alias-domain (domainIdOrAliasDomain()). The mailbox address then is listed as one of the aliases.
	 *
	 * @param string $primary primary email from primaryEmail(), can be empty
	 * @param array $account Stalwart account with values for keys name and emailAddress
	 * @param string[] $aliases lower-case aliases (and lists) of the account as email addresses
	 * @return array [string primary email or '' for the account's own address, string[] aliases]
	 */
	protected function primaryAndAliases(string $primary, array $account, array $aliases) : array
	{
		if ($primary === '' || strpos($primary, '@') === false)
		{
			return ['', $aliases];
		}
		[$name, $domain] = explode('@', $primary, 2);
		// a primary email on a domain Stalwart does not know (eg. an external email of an external account) can not be
		// an alias in Stalwart, but it must survive as primary email in EGroupware
		if (!$this->domainIdOrAliasDomain($domain))
		{
			return [$primary, array_values(array_diff($aliases, [$primary]))];
		}
		// the same name-part on the main domain is the same alias, do not list it twice
		$aliases = array_values(array_diff($aliases, [$primary, $name.'@'.strtolower($this->defaultDomain)]));
		// the mailbox address stays an alias, unless the primary email is it (on the main or an alias-domain)
		if (self::name2stalwart($name) !== $account['name'])
		{
			$aliases[] = strtolower($account['emailAddress']);
		}
		return [$primary, array_values(array_unique($aliases))];
	}

	/**
	 * Is an email address the address of a mailing-list
	 */
	protected function isMailingListAddress(string $email) : bool
	{
		foreach($this->getMailingListByRecipient($email, false, true) as $list)
		{
			if (strtolower($list['emailAddress']) === strtolower($email))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Mailbox of the user, whose primary email is the address of a mailing-list: it is a recipient of the list too (eg. an archive mailbox "pr" for the
	 * list personalrat@), as an account and a list can not have the same address
	 *
	 * @param string $list_email address of the list
	 * @return ?string address of the mailbox, null: nobody has the address as primary email, or it is his mailbox address (the user wins, no list)
	 */
	protected function archiveMailbox(string $list_email) : ?string
	{
		if (!($account_id = $this->accounts->name2id($list_email, 'account_email', 'u')) || !($lid = Api\Accounts::id2name($account_id)))
		{
			return null;
		}
		$name = self::name2stalwart($lid);
		return $name !== self::name2stalwart(explode('@', $list_email)[0]) ? $name.'@'.strtolower($this->defaultDomain) : null;
	}

	/**
	 * Recipients of the mailing-list of a group
	 *
	 * @param string[] $emails email addresses of the members
	 * @param string $list_email address of the list
	 * @param ?string $mailbox mailbox address from archiveMailbox(): replaces the list's own address, if a member has it as primary email, and is a recipient
	 * @return string[] lower-case, without duplicates
	 */
	public static function listRecipients(array $emails, string $list_email, ?string $mailbox=null) : array
	{
		$list_email = strtolower($list_email);
		$emails = array_filter(array_map(static fn($e) => strtolower(trim((string)$e)), $emails));
		// a list can not have itself as recipient
		$emails = array_values(array_diff($emails, [$list_email]));
		if ($mailbox)
		{
			$emails[] = strtolower($mailbox);
		}
		return array_values(array_unique($emails));
	}

	/**
	 * Alias objects of Stalwart for a list of email addresses
	 *
	 * An address on an alias-domain of the account's domain (full alias-domain: it has the same name-parts as the main domain) is an alias
	 * of the same name-part on the main domain, see domainIdOrAliasDomain(). That is how a primary email on an alias-domain (eg. s.hinkel@meissen-inklusiv.de
	 * for the account shinkel@lebenshilfe-meissen.de) is created as alias, EGroupware and the old Postfix maps treated it as the user's address.
	 *
	 * @param string[] $addresses email addresses (aliases and the primary email, if it differs from the account name)
	 * @param string $mailLocalAddress primary email, an external one (domain unknown to Stalwart) is skipped silently
	 * @return array[] alias objects with name, domainId, enable and description, without duplicates
	 * @throws \Exception 422 for an address on an unknown domain, unless self::$skipTakenAliases
	 */
	protected function aliasObjects(array $addresses, string $mailLocalAddress) : array
	{
		$aliases = [];
		foreach(array_unique($addresses) as $alias)
		{
			[$name, $domain] = explode('@', strtolower($alias));
			if (!($domainId = $this->domainIdOrAliasDomain($domain)))
			{
				// the primary email of an external account can be on a domain not hosted by Stalwart: it is neither created
				// nor sent to Stalwart, but stays in EGroupware (parent::setUserData() stores it in SQL)
				if (strtolower($alias) === strtolower($mailLocalAddress))
				{
					continue;
				}
				// an alias must be on an existing domain or alias-domain (created via mserver or the migration), domains are never created here
				$msg = lang('Domain %1 of alias %2 is neither a domain nor an alias-domain of the mail server, it must be created first!', $domain, $alias);
				if (!self::$skipTakenAliases)
				{
					throw new \Exception($msg, 422);    // 422 Unprocessable Content for the REST API
				}
				self::$warnings[] = $msg.' '.lang('Skipped.');
				continue;
			}
			// the same name-part on the main domain and on its alias-domain is only one alias
			$aliases[$name.'@'.$domainId] = [
				'enable' => true,    // Stalwart 0.16 used 'enabled', 1.0 rejects it with invalidPatch
				'name' => self::name2stalwart($name),
				'domainId' => $domainId,
				'description' => null,  // ToDo: preserve from $userData['stalwart']['aliases']
			];
		}
		return array_values($aliases);
	}

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
			], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
			self::$domainIds[$domain] = $response['methodResponses'][0][1]['ids'][0] ?? null;
			// only permanently cache returned id's, not that there was none yet
			if (isset(self::$domainIds[$domain]))
			{
				Api\Cache::setInstance(__CLASS__, 'domainIds-'.$this->account->acc_smtp_host, self::$domainIds);
			}
		}
		return self::$domainIds[$domain];
	}

	protected static $aliasDomains = [];

	/**
	 * Names of the alias-domains of a domain
	 *
	 * Stalwart knows an alias-domain only as key of the "aliases" map of its main domain, never as domain of its own: x:Domain/query by its name
	 * finds nothing, so domainId() returns null for it.
	 *
	 * @param ?string $domain =null main domain, default the account's domain (self::$defaultDomain)
	 * @return string[] lower-case domain names
	 */
	function aliasDomains(?string $domain=null) : array
	{
		$domain = strtolower($domain ?? $this->defaultDomain ?? '');
		$key = (isset($this->account) ? $this->account->acc_smtp_host : '').'|'.$domain;
		if (!isset(self::$aliasDomains[$key]))
		{
			self::$aliasDomains[$key] = [];
			if ($domain && ($domainId = $this->domainId($domain)))
			{
				$response = $this->jmapClient()->jmapCall([
					['x:Domain/get', [
						'ids' => [$domainId],
						'properties' => ['aliases'],
					], 'a'],
				], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
				self::$aliasDomains[$key] = array_map('strtolower', array_keys((array)($response['methodResponses'][0][1]['list'][0]['aliases'] ?? [])));
			}
		}
		return self::$aliasDomains[$key];
	}

	/**
	 * Get the domainId of a domain, which can also be an alias-domain of the account's own domain
	 *
	 * Only the alias-domains of $this->defaultDomain count, an alias-domain of an other hosted domain does not (its name-parts are not the account's).
	 *
	 * @param string $domain
	 * @return string|null domainId of the domain, or of the main domain for one of its alias-domains
	 */
	function domainIdOrAliasDomain(string $domain) : ?string
	{
		if (($domainId = $this->domainId($domain)))
		{
			return $domainId;
		}
		return in_array(strtolower($domain), $this->aliasDomains(), true) ? $this->domainId($this->defaultDomain) : null;
	}

	function domain(string $domainId) : ?string
	{
		if (self::$domainIds === null)
		{
			self::$domainIds = Api\Cache::getInstance(__CLASS__, 'domainIds-'.$this->account->acc_smtp_host) ?? [];
		}
		if (!($domain = array_search($domainId, self::$domainIds, true)))
		{
			// Domain/query's response only ever has an "ids" list (never resolved object
			// properties) - need Domain/get by id to actually read the domain's "name". Found
			// live 2026-10-30: this previously read a non-existent "name" key off a */query
			// response, always null, so every alias in a domain other than the account's own
			// cached default domain silently lost its domain on display (and on save, since
			// setUserData() then failed to find a domain named '' for the resulting bare
			// "local-part@" string).
			$response = $this->jmapClient()->jmapCall([
				['x:Domain/get', [
					'ids' => [$domainId],
				], 'a']
			], [JmapHttp::JMAP_CORE, self::USING_STALWART]);
			// only permanently cache returned id's, not that there was none yet
			if (($domain = $response['methodResponses'][0][1]['list'][0]['name'] ?? null))
			{
				self::$domainIds[$domain] = $domainId;
				Api\Cache::setInstance(__CLASS__, 'domainIds-'.$this->account->acc_smtp_host, self::$domainIds);
			}
		}
		return $domain;
	}
}