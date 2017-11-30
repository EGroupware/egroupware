<?php
/**
 * EGroupware setup - test or create the ldap connection and hierarchy
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * setup command: test or create the ldap connection and hierarchy
 *
 * All commands can be run via setup-cli eg:
 *
 * setup/setup-cli.php [--dry-run] --setup_cmd_ldap <domain>,<config-user>,<config-pw> sub_command=set_mailbox \
 * 	ldap_base=dc=local ldap_root_dn=cn=admin,dc=local ldap_root_pw=secret ldap_host=localhost
 *
 * Changing uid/gidNumber to match SID in preparation to Samba4 migration:
 *
 * setup/setup-cli.php [--dry-run] --setup_cmd_ldap <domain>,<config-user>,<config-pw> sub_command=sid2uidnumber \
 * 	ldap_base=dc=local ldap_root_dn=cn=admin,dc=local ldap_root_pw=secret ldap_host=localhost
 *
 * - First run it with --dry-run to get ids to change / admin-cli command to change ids in EGroupware.
 * - Then run admin/admin-cli.php --change-account-id and after this command again without --dry-run.
 * - After that you can run the given setup/doc/chown.php command to change filesystem uid/gid in samba share.
 *   This is usually not needed as samba-tool clasicupgrade takes care of existing filesystem uid/gid by installing
 *   rfc2307 schema with uidNumber attributes.
 *
 * setup/setup-cli.php [--dry-run] --setup-cmd-ldap <domain>,<config-user>,<config-pw> sub_command=copy2ad \
 * 	ldap_base=dc=local ldap_root_dn=cn=admin,dc=local ldap_root_pw=secret ldap_host=localhost \
 * 	ads_domain=samba4.intern [ads_admin_user=Administrator] ads_admin_pw=secret ads_host=ad.samba4.intern [ads_connection=(ssl|tls)] \
 * 	attributes=@inetOrgPerson,accountExpires=shadowExpire
 *
 * - copies from samba-tool clasicupgrade not copied inetOrgPerson attributes and mail attributes to AD
 *
 * setup/setup-cli.php [--dry-run] --setup-cmd-ldap <domain>,<config-user>,<config-pw> sub_command=copy2ad \
 * 	ldap_base=dc=local ldap_root_dn=cn=admin,dc=local ldap_root_pw=secret ldap_host=localhost \
 * 	ads_domain=samba4.intern [ads_admin_user=Administrator] ads_admin_pw=secret \
 * 	ads_host=ad.samba4.intern [ads_connection=(ssl|tls)] [no_sid_check=1] \
 * 	attributes={smtp:}proxyAddresses=mail,{smtp:}proxyAddresses=mailalias,{quota:}proxyAddresses=mailuserquota,{forward:}proxyaddresses=maildrop
 *
 * - copies mail-attributes from ldap to AD (example is from Mandriva mailAccount schema, need to adapt to other schema!)
 *   (no_sid_check=1 uses all objectClass=posixAccount, not checking for having a SID and uid not ending in $ for computer Api\Accounts)
 *
 * setup/setup-cli.php [--dry-run] --setup-cmd-ldap <domain>,<config-user>,<config-pw> sub_command=passwords_to_sql \
 * 	ldap_context=ou=accounts,dc=local ldap_root_dn=cn=admin,dc=local ldap_root_pw=secret ldap_host=localhost
 *
 * - updating passwords for existing users in SQL from LDAP, eg. to switch off authentication to LDAP on a SQL install.
 */
class setup_cmd_ldap extends setup_cmd
{
	/**
	 * Allow to run this command via setup-cli
	 */
	const SETUP_CLI_CALLABLE = true;

	/**
	 * Instance of ldap object
	 *
	 * @var ldap
	 */
	private $test_ldap;

	/**
	 * Constructor
	 *
	 * @param string/array $domain domain-name to customize the defaults or array with all parameters
	 * @param string $ldap_host =null
	 * @param string $ldap_suffix =null base of the whole ldap install, default "dc=local"
	 * @param string $ldap_admin =null root-dn needed to create new entries in the suffix
	 * @param string $ldap_admin_pw =null
	 * @param string $ldap_base =null base of the instance, default "o=$domain,$suffix"
	 * @param string $ldap_root_dn =null root-dn used for the instance, default "cn=admin,$base"
	 * @param string $ldap_root_pw =null
	 * @param string $ldap_context =null ou for accounts, default "ou=accounts,$base"
	 * @param string $ldap_search_filter =null search-filter for accounts, default "(uid=%user)"
	 * @param string $ldap_group_context =null ou for groups, default "ou=groups,$base"
	 * @param string $sub_command ='create_ldap' 'create_ldap', 'test_ldap', 'test_ldap_root', see exec method
	 * @param string $ldap_encryption_type ='des'
	 * @param boolean $truncate_egw_accounts =false truncate accounts table before migration to SQL
	 */
	function __construct($domain,$ldap_host=null,$ldap_suffix=null,$ldap_admin=null,$ldap_admin_pw=null,
		$ldap_base=null,$ldap_root_dn=null,$ldap_root_pw=null,$ldap_context=null,$ldap_search_filter=null,
		$ldap_group_context=null,$sub_command='create_ldap',$ldap_encryption_type='des',$truncate_egw_accounts=false)
	{
		if (!is_array($domain))
		{
			$domain = array(
				'domain'        => $domain,
				'ldap_host'     => $ldap_host,
				'ldap_suffix'   => $ldap_suffix,
				'ldap_admin'    => $ldap_admin,
				'ldap_admin_pw' => $ldap_admin_pw,
				'ldap_base'     => $ldap_base,
				'ldap_root_dn'  => $ldap_root_dn,
				'ldap_root_pw'  => $ldap_root_pw,
				'ldap_context'  => $ldap_context,
				'ldap_search_filter' => $ldap_search_filter,
				'ldap_group_context' => $ldap_group_context,
				'sub_command'   => $sub_command,
				'ldap_encryption_type' => $ldap_encryption_type,
				'truncate_egw_accounts' => $truncate_egw_accounts,
			);
		}
		//echo __CLASS__.'::__construct()'; _debug_array($domain);
		admin_cmd::__construct($domain);
	}

	/**
	 * run the command: test or create the ldap connection and hierarchy
	 *
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Exception(lang('Wrong credentials to access the header.inc.php file!'),2);
	 * @throws Exception('header.inc.php not found!');
	 */
	protected function exec($check_only=false)
	{
		if (!empty($this->domain) && !preg_match('/^([a-z0-9_-]+\.)*[a-z0-9]+/i',$this->domain))
		{
			throw new Api\Exception\WrongUserinput(lang("'%1' is no valid domain name!",$this->domain));
		}
		if ($this->remote_id && $check_only && !in_array($this->sub_command, array('set_mailbox', 'sid2uidnumber', 'copy2ad')))
		{
			return true;	// further checks can only done locally
		}
		$this->_merge_defaults();
		//_debug_array($this->as_array());

		switch($this->sub_command)
		{
			case 'test_ldap_root':
				$msg = $this->connect($this->ldap_admin,$this->ldap_admin_pw);
				break;
			case 'test_ldap':
				$msg = $this->connect();
				break;
			case 'delete_ldap':
				$msg = $this->delete_base();
				break;
			case 'users_ldap':
				$msg = $this->users();
				break;
			case 'migrate_to_ldap':
			case 'migrate_to_sql':
			case 'migrate_to_univention':
			case 'passwords_to_sql':
				$msg = $this->migrate($this->sub_command);
				break;
			case 'set_mailbox':
				$msg = $this->set_mailbox($check_only);
				break;
			case 'sid2uidnumber':
				$msg = $this->sid2uidnumber($check_only);
				break;
			case 'copy2ad':
				$msg = $this->copy2ad($check_only);
				break;
			case 'create_ldap':
			default:
				$msg = $this->create();
				break;
		}
		return $msg;
	}

	const sambaSID = 'sambasid';

	/**
	 * Change uidNumber and gidNumber to match rid (last part of sambaSID)
	 *
	 * First run it with --dry-run to get ids to change / admin-cli command to change ids in EGroupware.
	 * Then run admin/admin-cli.php --change-account-id and after this command again without --dry-run.
	 * After that you need to run the given chown.php command to change filesystem uid/gid in samba share.
	 *
	 * @param boolean $check_only =false true: only connect and output necessary commands
	 */
	private function sid2uidnumber($check_only=false)
	{
		$msg = array();
		$this->connect();

		// check if base does exist
		if (!@ldap_read($this->test_ldap->ds,$this->ldap_base,'objectClass=*'))
		{
			throw new Api\Exception\WrongUserinput(lang('Base dn "%1" NOT found!',$this->ldap_base));
		}

		if (!($sr = ldap_search($this->test_ldap->ds,$this->ldap_base,
			$search='(&(|(objectClass=posixAccount)(objectClass=posixGroup))('.self::sambaSID.'=*)(!(uid=*$)))',
			array('uidNumber','gidNumber','uid','cn', 'objectClass',self::sambaSID))) ||
			!($entries = ldap_get_entries($this->test_ldap->ds, $sr)))
		{
			throw new Api\Exception(lang('Error searching "dn=%1" for "%2"!',$this->ldap_base, $search));
		}
		$change = $accounts = array();
		$cmd_change_account_id = 'admin/admin-cli.php --change-account-id <admin>@<domain>,<adminpw>';
		$change_account_id = '';
		foreach($entries as $key => $entry)
		{
			if ($key === 'count') continue;

			$entry = Api\Ldap::result2array($entry);
			$accounts[$entry['dn']] = $entry;
			//print_r($entry);

			$parts = explode('-', $entry[self::sambaSID]);
			$rid = array_pop($parts);

			if (in_array('posixAccount', $entry['objectclass']))
			{
				$id = $entry['uidnumber'];
			}
			else
			{
				$id = -$entry['gidnumber'];
				$rid *= -1;
			}
			if ($id != $rid)
			{
				$change[$id] = $rid;
				$change_account_id .= ','.$id.','.$rid;
			}
		}
		//print_r($change); die('Stop');

		// change account-ids inside EGroupware
		if ($check_only) $msg[] = "You need to run now:\n$cmd_change_account_id $change_account_id";
		//$cmd = new admin_cmd_change_account_id($change);
		//$msg[] = $cmd->run($time=null, $set_modifier=false, $skip_checks=false, $check_only);

		// now change them in LDAP
		$changed = 0;
		foreach($accounts as $dn => $account)
		{
			$modify = array();
			if (!empty($account['uidnumber']) && isset($change[$account['uidnumber']]))
			{
				$modify['uidnumber'] = $change[$account['uidnumber']];
			}
			if (isset($change[-$account['gidnumber']]))
			{
				$modify['gidnumber'] = -$change[-$account['gidnumber']];
			}
			if (!$check_only && $modify && !ldap_modify($this->test_ldap->ds, $dn, $modify))
			{
				throw new Api\Exception("Failed to modify ldap: !ldap_modify({$this->test_ldap->ds}, '$dn', ".array2string($modify).") ".ldap_error($this->test_ldap->ds).
					"\n- ".implode("\n- ", $msg));	// EGroupware change already run successful
			}
			if ($modify) ++$changed;
		}
		$msg[] = "You need to run now on your samba share(s):\nsetup/doc/chown.php -R $change_account_id <share>";

		return ($check_only ? 'Need to update' : 'Updated')." $changed entries with new uid/gidNumber in LDAP".
			"\n- ".implode("\n- ", $msg);
	}

	/**
	 * Copy given attributes of accounts of one ldap to active directory
	 *
	 * @param boolean $check_only =false true: only connect and output necessary commands
	 */
	private function copy2ad($check_only=false)
	{
		$msg = array();
		$attrs = $rename = array();
		foreach(explode(',', $this->attributes) as $attr)
		{
			if ($attr[0] == '@' ||	// copy whole objectclass without renaming, eg. @inetOrgPerson
				strpos($attr, '=') === false)
			{
				$attrs[] = $attr;
			}
			else
			{
				list($to, $from) = explode('=', $attr);
				if ($from) $attrs[] = $from;
				$rename[strtolower($from)] = $to;
			}
		}
		$ignore_attr = array_flip(array('dn', 'objectclass', 'cn', 'userpassword'));
		if (!in_array('uid', $attrs))
		{
			$attrs[] = 'uid';	// need to match account
			$ignore_attr['uid'] = true;
		}
		// connect to destination ads
		if (empty($this->ads_context))
		{
			$this->ads_context = 'CN=Users,DC='.implode(',DC=', explode('.', $this->ads_domain));
		}
		if (empty($this->ads_admin_user)) $this->ads_admin_user = 'Administrator';
		$admin_dn = strpos($this->ads_admin_user, '=') !== false ? $this->ads_admin_user :
			'CN='.$this->ads_admin_user.','.$this->ads_context;
		switch($this->ads_connection)
		{
			case 'ssl':
				$url = 'ldaps://'.$this->ads_host.'/';
				break;
			case 'tls':
				$url = 'tls://'.$this->ads_host.'/';
				break;
			default:
				$url = 'ldap://'.$this->ads_host.'/';
				break;
		}
		$this->connect($admin_dn, $this->ads_admin_pw, $url);
		$ads = $this->test_ldap; unset($this->test_ldap);

		// check if ads base does exist
		if (!@ldap_read($ads->ds, $this->ads_context, 'objectClass=*'))
		{
			throw new Api\Exception\WrongUserinput(lang('Ads dn "%1" NOT found!',$this->ads_context));
		}

		// connect to source ldap
		$this->connect();

		// check if ldap base does exist
		if (!@ldap_read($this->test_ldap->ds,$this->ldap_base,'objectClass=*'))
		{
			throw new Api\Exception\WrongUserinput(lang('Base dn "%1" NOT found!',$this->ldap_base));
		}

		if (!($sr = ldap_search($this->test_ldap->ds,$this->ldap_base,
			$search = $this->no_sid_check ? '(objectClass=posixAccount)' :
				'(&(objectClass=posixAccount)('.self::sambaSID.'=*)(!(uid=*$)))', $attrs)) ||
			!($entries = ldap_get_entries($this->test_ldap->ds, $sr)))
		{
			throw new Api\Exception(lang('Error searching "dn=%1" for "%2"!',$this->ldap_base, $search));
		}
		$changed = 0;
		$utc_diff = null;
		foreach($entries as $key => $entry)
		{
			if ($key === 'count') continue;

			$entry_arr = Api\Ldap::result2array($entry);
			$uid = $entry_arr['uid'];
			$entry = array_diff_key($entry_arr, $ignore_attr);

			if (!($sr = ldap_search($ads->ds, $this->ads_context,
				$search='(&(objectClass=user)(sAMAccountName='.Api\Ldap::quote($uid).'))', array('dn'))) ||
				!($dest = ldap_get_entries($ads->ds, $sr)))
			{
				$msg[] = lang('User "%1" not found!', $uid);
				continue;
			}
			$dn = $dest[0]['dn'];
			if (isset($rename[''])) $entry[''] = '';
			// special handling for copying shadowExpires to accountExpires
			if (strtolower($rename['shadowexpire']) === 'accountexpires')
			{
				// need to write accountExpires for never expiring account, as samba-tool classicupgrade sets it to 2038-01-19
				if (!isset($entry['shadowexpire']) || !$entry['shadowexpire'])
				{
					$entry['shadowexpire'] = accounts_ads::EXPIRES_NEVER;
				}
				else
				{
					if (is_null($utc_diff)) $utc_diff = date('Z');
					$entry['shadowexpire'] = accounts_ads::convertUnixTimeToWindowsTime(
						$entry['shadowexpire']*24*3600+$utc_diff);	// ldap time to unixTime
				}
			}
			$update = array();
			foreach($entry as $attr => $value)
			{
				if ($value || $attr === '')
				{
					$to = isset($rename[$attr]) ? $rename[$attr] : $attr;
					$prefix = null;
					if ($to[0] == '{')	// eg. {smtp:}proxyAddresses=forwardTo
					{
						list($prefix, $to) = explode('}', substr($to, 1));
					}
					foreach((array)$value as $val)
					{
						if (isset($update[$to]))
						{
							if (!is_array($update[$to])) $update[$to] = array($update[$to]);
							// we need to check (caseinsensitive) if value already exists in set
							// as AD chokes on doublicate values "Type or value exists"
							foreach($update[$to] as $v)
							{
								if (!strcasecmp($v, $prefix.$val)) continue 2;
							}
							$update[$to][] = $prefix.$val;
						}
						else
						{
							$update[$to] = $prefix.$val;
						}
					}
				}
			}
			if ($check_only)
			{
				print_r($dn);
				print_r($update);
				continue;
			}
			if ($update && !ldap_modify($ads->ds, $dn, $update))
			{
				error_log(lang('Failed updating user "%1" dn="%2"!', $uid, $dn).' '.ldap_error($ads->ds));
			}
			else
			{
				print_r(lang('User "%1" dn="%2" successful updated.', $uid, $dn)."\n");
				$changed++;
			}
		}
		if ($check_only) return lang("%1 accounts to copy found.", count($entries));

		return "Copied data of $changed accounts from LDAP to AD ".
			(count($msg) > $changed ? ' ('.(count($msg)-$changed).' errors!)' : '');
	}

	/**
	 * Migrate to other account storage
	 *
	 * @param string $mode "passwords_to_sql", "migrate_to_(sql|ldap|univention)"
	 * @return string with success message
	 * @throws Exception on error
	 */
	private function migrate($mode)
	{
		// support old boolean mode
		if (is_bool($mode)) $mode = $mode ? 'migrate_to_ldap' : 'migrate_to_sql';

		$passwords2sql = $mode === "passwords_to_sql";
		list(,$to) = explode('_to_', $mode);

		$msg = array();
		// if migrating to ldap, check ldap and create context if not yet exiting
		if ($to == 'ldap' && !empty($this->ldap_admin_pw))
		{
			$msg[] = $this->create();
		}
		elseif ($this->account_repository !== 'ads')
		{
			$msg[] = $this->connect();
		}
		// read accounts from old store
		$accounts = $this->accounts($to == 'sql' ? $this->account_repository : 'sql', $passwords2sql ? 'accounts' : 'both');

		// clean up SQL before migration
		if ($to == 'sql' && $this->truncate_egw_accounts)
		{
			$GLOBALS['egw']->db->query('TRUNCATE TABLE egw_accounts', __LINE__, __FILE__);
			$GLOBALS['egw']->db->query('DELETE FROM egw_addressbook WHERE account_id IS NOT NULL', __LINE__, __FILE__);
		}
		// instanciate accounts obj for new store
		$accounts_obj = $this->accounts_obj($to);

		$accounts_created = $groups_created = $errors = $egw_info_set = 0;
		$emailadmin_src = $ldap_class = null;
		$target = strtoupper($to);
		foreach($accounts as $account_id => $account)
		{
			if (isset($this->only) && !in_array($account_id,$this->only))
			{
				continue;
			}
			$what = ($account['account_type'] == 'u' ? lang('User') : lang('Group')).' '.
				$account_id.' ('.$account['account_lid'].')';

			// if we migrate passwords from an authentication source, we need to use account_lid, not numerical id
			if ($passwords2sql && ($id = $accounts_obj->name2id($account['account_lid'], 'account_lid', 'u')))
			{
				$account_id = $id;
			}

			// invalidate cache: otherwise no migration takes place, if cached results says account already exists
			Api\Accounts::cache_invalidate($account_id);

			if ($passwords2sql)
			{
				if (!($sql_account = $accounts_obj->read($account_id)))
				{
					$msg[] = lang('%1 does NOT exist in %2.',$what,$target);
					$errors++;
				}
				elseif(empty($account['account_pwd']))
				{
					$msg[] = lang('%1 does NOT have a password (userPassword attribute) or we are not allowed to read it!',$what);
					$errors++;
				}
				else
				{
					$sql_account['account_passwd'] = self::hash_ldap2sql($account['account_pwd']);

					if (!$accounts_obj->save($sql_account))
					{
						$msg[] = lang('Update of %1 in %2 failed !!!',$what,$target);
						$errors++;
					}
					else
					{
						$msg[] = lang('%1 password set in %2.',$what,$target);
						$accounts_created++;
					}
				}
				continue;
			}

			if ($account['account_type'] == 'u')
			{
				if ($accounts_obj->exists($account_id))
				{
					$msg[] = lang('%1 already exists in %2.',$what,$target);
					$errors++;
					continue;
				}
				if ($to != 'sql')
				{
					if ($GLOBALS['egw_info']['server']['ldap_extra_attributes'])
					{
						$account['homedirectory'] = $GLOBALS['egw_info']['server']['ldap_account_home'] . '/' . $account['account_lid'];
						$account['loginshell'] = $GLOBALS['egw_info']['server']['ldap_account_shell'];
					}
					$account['account_passwd'] = self::hash_sql2ldap($account['account_pwd']);
				}
				else
				{
					$account['account_passwd'] = self::hash_ldap2sql($account['account_pwd']);
				}
				unset($account['person_id']);

				if (!$accounts_obj->save($account))
				{
					$msg[] = lang('Creation of %1 in %2 failed !!!',$what,$target);
					$errors++;
					continue;
				}
				$accounts_obj->set_memberships($account['memberships'],$account_id);
				$msg[] = lang('%1 created in %2.',$what,$target);
				$accounts_created++;

				// check if we need to migrate mail-account
				if (!isset($ldap_class) && $this->account_repository !== 'ads')
				{
					$ldap_class = false;
					$ldap = Api\Ldap::factory(false);
					foreach(array(	// todo: have these enumerated by emailadmin ...
						'qmailUser' => 'EGroupware\\Api\\Mail\\Smtp\\Oldqmailuser',
						'dbMailUser' => 'EGroupware\\Api\\Mail\\Smtp\\Dbmailuser',
						// nothing to migrate for inetOrgPerson ...
					) as $object_class => $class)
					{
						if ($ldap->getLDAPServerInfo()->supportsObjectClass($object_class))
						{
							$ldap_class = $class;
							break;
						}
					}
				}
				if ($ldap_class)
				{
					if (!isset($emailadmin_src))
					{
						if ($to != 'sql')
						{
							$emailadmin_src = new Api\Mail\Smtp\Sql();
							$emailadmin_dst = new $ldap_class();
						}
						else
						{
							$emailadmin_src = new $ldap_class();
							$emailadmin_dst = new Api\Mail\Smtp\Sql();
						}
					}
					if (($mailaccount = $emailadmin_src->getUserData($account_id)))
					{
						//echo "<p>".array2string($mailaccount).': ';
						$emailadmin_dst->setUserData($account_id, (array)$mailaccount['mailAlternateAddress'],
							(array)$mailaccount['mailForwardingAddress'], $mailaccount['deliveryMode'],
							$mailaccount['accountStatus'], $mailaccount['mailLocalAddress'],
							$mailaccount['quotaLimit'], false, $mailaccount['mailMessageStore']);

						$msg[] = lang("Mail account of %1 migraged", $account['account_lid']);
					}
					//else echo "<p>No mail account data found for #$account_id $account[account_lid]!</p>\n";
				}

				// should we run any or some addAccount hooks
				if ($this->add_account_hook)
				{
					// setting up egw_info array with new ldap information, so hook can use Api\Ldap::ldapConnect()
					if (!$egw_info_set++)
					{
						foreach(array('ldap_host','ldap_root_dn','ldap_root_pw','ldap_context','ldap_group_context','ldap_search_filter','ldap_encryptin_type','mail_suffix','mail_login_type') as $name)
						{
							 if (!empty($this->$name)) $GLOBALS['egw_info']['server'][$name] = $this->$name;
						}
						//error_log(__METHOD__."() setup up egw_info[server]: ldap_host='{$GLOBALS['egw_info']['server']['ldap_host']}', ldap_root_dn='{$GLOBALS['egw_info']['server']['ldap_root_dn']}', ldap_root_pw='{$GLOBALS['egw_info']['server']['ldap_root_pw']}', ldap_context='{$GLOBALS['egw_info']['server']['ldap_context']}', mail_suffix='{$GLOBALS['egw_info']['server']['mail_suffix']}', mail_logig_type='{$GLOBALS['egw_info']['server']['mail_login-type']}'");
					}
					try
					{
						$account['location'] = 'addAccount';
						// running all addAccount hooks (currently NOT working, as not all work in setup)
						if ($this->add_account_hook === true)
						{
							Api\Hooks::process($account, array(), true);
						}
						elseif(is_callable($this->add_account_hook))
						{
							call_user_func($this->add_account_hook,$account);
						}
					}
					catch(Exception $e)
					{
						$msg[] = $e->getMessage();
						$errors++;
					}
				}
			}
			else
			{
				// check if group already exists
				if (!$accounts_obj->exists($account_id))
				{
					if (!$accounts_obj->save($account))
					{
						$msg[] = lang('Creation of %1 in %2 failed !!!',$what,$target);
						++$errors;
						continue;
					}
					$msg[] = lang('%1 created in %2.',$what,$target);
					$groups_created++;
				}
				else
				{
					$msg[] = lang('%1 already exists in %2.',$what,$target);
					$errors++;

					if ($accounts_obj->id2name($account_id) != $account['account_lid'])
					{
						$msg[] = lang("==> different group '%1' under that gidNumber %2, NOT setting memberships!",$account['account_lid'],$account_id);
						++$errors;
						continue;	// different group under that gidnumber!
					}
				}
				// now saving / updating the memberships
				$accounts_obj->set_members($account['members'],$account_id);
			}
		}
		if ($passwords2sql)
		{
			return lang('%1 passwords updated, %3 errors',$accounts_created,$groups_created,$errors).
				($errors || $this->verbose ? "\n- ".implode("\n- ",$msg) : '');
		}
		// migrate addressbook data
		$GLOBALS['egw_info']['user']['apps']['admin'] = true;	// otherwise migration will not run in setup!
		$addressbook = new Api\Contacts\Storage();
		foreach($this->as_array() as $name => $value)
		{
			if (substr($name, 5) == 'ldap_')
			{
				$GLOBALS['egw_info']['server'][$name] = $value;
			}
		}
		ob_start();
		$addressbook->migrate2ldap($to != 'sql' ? 'accounts' : 'accounts-back'.
			($this->account_repository == 'ads' ? '-ads' : ''));
		$msgs = array_merge($msg, explode("\n", strip_tags(ob_get_clean())));

		$this->restore_db();

		return lang('%1 users and %2 groups created, %3 errors',$accounts_created,$groups_created,$errors).
			($errors || $this->verbose ? "\n- ".implode("\n- ",$msgs) : '');
	}

	/**
	 * Convert SQL hash to LDAP hash
	 *
	 * @param string $hash
	 * @return string
	 */
	public static function hash_sql2ldap($hash)
	{
		if (!($type = $GLOBALS['egw_info']['server']['sql_encryption_type'])) $type = 'md5';

		$matches = null;
		if (preg_match('/^\\{(.*)\\}(.*)$/',$hash,$matches))
		{
			list(,$type,$hash) = $matches;
		}
		elseif (preg_match('/^[0-9a-f]{32}$/',$hash))
		{
			$type = 'md5';
		}
		switch(strtolower($type))
		{
			case 'plain':
				// ldap stores plaintext passwords without {plain} prefix
				break;

			case 'md5':
				$hash = base64_encode(pack("H*",$hash));
				// fall through
			default:
				$hash = '{'.strtoupper($type).'}'.$hash;
		}
		return $hash;
	}

	/**
	 * Convert LDAP hash to SQL hash
	 *
	 * @param string $hash
	 * @return string
	 */
	public static function hash_ldap2sql($hash)
	{
		if ($hash[0] != '{')	// plain has to be explicitly specified for sql, in ldap it's the default
		{
			$hash = '{PLAIN}'.$hash;
		}
		return $hash;
	}

	/**
	 * Read all accounts from sql or ldap
	 *
	 * @param string $from ='ldap', 'ldap', 'sql', 'univention'
	 * @param string $type ='both'
	 * @return array
	 */
	public function accounts($from='ldap', $type='both')
	{
		$accounts_obj = $this->accounts_obj($from);
		//error_log(__METHOD__."(from_ldap=".array2string($from_ldap).') get_class(accounts_obj->backend)='.get_class($accounts_obj->backend));

		$accounts = $accounts_obj->search(array('type' => $type, 'objectclass' => true, 'active' => false));

		foreach($accounts as $account_id => &$account)
		{
			if ($account_id != $account['account_id'])	// not all backends have as key the account_id
			{
				unset($account);
				$account_id = $account['account_id'];
			}
			$account += $accounts_obj->read($account_id);

			if ($account['account_type'] == 'g')
			{
				$account['members'] = $accounts_obj->members($account_id,true);
			}
			else
			{
				$account['memberships'] = $accounts_obj->memberships($account_id,true);
			}
		}
		Api\Accounts::cache_invalidate();

		return $accounts;
	}

	/**
	 * Instanciate accounts object from either sql of ldap
	 *
	 * @param string $type 'ldap', 'sql', 'univention'
	 * @return Api\Accounts
	 */
	private function accounts_obj($type)
	{
		static $enviroment_setup=null;
		if (!$enviroment_setup)
		{
			parent::_setup_enviroment($this->domain);
			$enviroment_setup = true;
		}
		if ($type != 'sql' && $type != 'ads') $this->connect();	// throws exception, if it can NOT connect

		// otherwise search does NOT work, as accounts_sql uses addressbook_bo for it
		$GLOBALS['egw_info']['server']['account_repository'] = $type;

		if (!self::$egw_setup->setup_account_object(
			array(
				'account_repository' => $GLOBALS['egw_info']['server']['account_repository'],
			) + $this->as_array()) ||
			!is_a(self::$egw_setup->accounts, 'EGroupware\\Api\\Accounts') ||
			!is_a(self::$egw_setup->accounts->backend, 'EGroupware\\Api\\Accounts\\'.ucfirst($type)))
		{
			throw new Exception(lang("Can NOT instancate accounts object for %1", strtoupper($type)));
		}
		return self::$egw_setup->accounts;
	}

	/**
	 * Connect to ldap server
	 *
	 * @param string $dn =null default $this->ldap_root_dn
	 * @param string $pw =null default $this->ldap_root_pw
	 * @param string $host =null default $this->ldap_host, hostname, ip or ldap-url
	 * @throws Api\Exception\WrongUserinput Can not connect to ldap ...
	 */
	private function connect($dn=null,$pw=null,$host=null)
	{
		if (is_null($dn)) $dn = $this->ldap_root_dn;
		if (is_null($pw)) $pw = $this->ldap_root_pw;
		if (is_null($host)) $host = $this->ldap_host;

		if (!$pw)	// Api\Ldap::ldapConnect use the current eGW's pw otherwise
		{
			throw new Api\Exception\WrongUserinput(lang('You need to specify a password!'));
		}

		try {
			$this->test_ldap = Api\Ldap::factory(false, $host, $dn, $pw);
		}
		catch (Api\Exception\NoPermission $e) {
			_egw_log_exception($e);
			throw new Api\Exception\WrongUserinput(lang('Can not connect to LDAP server on host %1 using DN %2!',
				$host,$dn).($this->test_ldap->ds ? ' ('.ldap_error($this->test_ldap->ds).')' : ''));
		}
		return lang('Successful connected to LDAP server on %1 using DN %2.',$this->ldap_host,$dn);
	}

	/**
	 * Count active (not expired) users
	 *
	 * @return int number of active users
	 * @throws Api\Exception\WrongUserinput
	 */
	private function users()
	{
		$this->connect();

		$sr = ldap_list($this->test_ldap->ds,$this->ldap_context,'ObjectClass=posixAccount',array('dn','shadowExpire'));
		if (!($entries = ldap_get_entries($this->test_ldap->ds, $sr)))
		{
			throw new Api\Exception('Error listing "dn=%1"!',$this->ldap_context);
		}
		$num = 0;
		foreach($entries as $n => $entry)
		{
			if ($n === 'count') continue;
			if (isset($entry['shadowexpire']) && $entry['shadowexpire'][0]*24*3600 < time()) continue;
			++$num;
		}
		return $num;
	}

	/**
	 * Check and if does not yet exist create the new database and user
	 *
	 * @return string with success message
	 * @throws Api\Exception\WrongUserinput
	 */
	private function create()
	{
		$this->connect($this->ldap_admin,$this->ldap_admin_pw);

		foreach(array(
			$this->ldap_base => array(),
			$this->ldap_context => array(),
			$this->ldap_group_context => array(),
			$this->ldap_root_dn => array('userPassword' => Api\Auth::encrypt_ldap($this->ldap_root_pw,'ssha')),
		) as $dn => $extra)
		{
			if (!$this->_create_node($dn,$extra,$this->check_only) && $dn == $this->ldap_root_dn)
			{
				// ldap_root already existed, lets check the pw is correct
				$this->connect();
			}
		}
		return lang('Successful connected to LDAP server on %1 and created/checked required structur %2.',
			$this->ldap_host,$this->ldap_base);
	}

	/**
	 * Delete whole LDAP tree of an instance dn=$this->ldap_base using $this->ldap_admin/_pw
	 *
	 * @return string with success message
	 * @throws Api\Exception if dn not found, not listable or delete fails
	 */
	private function delete_base()
	{
		$this->connect($this->ldap_admin,$this->ldap_admin_pw);

		// if base not set, use context minus one hierarchy, eg. ou=accounts,(o=domain,dc=local)
		if (empty($this->ldap_base) && $this->ldap_context)
		{
			list(,$this->ldap_base) = explode(',',$this->ldap_context,2);
		}
		// some precausion to not delete whole ldap tree!
		if (count(explode(',',$this->ldap_base)) < 2)
		{
			throw new Api\Exception\AssertionFailed(lang('Refusing to delete dn "%1"!',$this->ldap_base));
		}
		// check if base does exist
		if (!@ldap_read($this->test_ldap->ds,$this->ldap_base,'objectClass=*'))
		{
			throw new Api\Exception\WrongUserinput(lang('Base dn "%1" NOT found!',$this->ldap_base));
		}
		return lang('LDAP dn="%1" with %2 entries deleted.',
			$this->ldap_base,$this->rdelete($this->ldap_base));
	}

	/**
	 * Recursive delete a dn
	 *
	 * @param string $dn
	 * @return int integer number of deleted entries
	 * @throws Api\Exception if dn not listable or delete fails
	 */
	private function rdelete($dn)
	{
		if (!($sr = ldap_list($this->test_ldap->ds,$dn,'ObjectClass=*',array(''))) ||
			!($entries = ldap_get_entries($this->test_ldap->ds, $sr)))
		{
			throw new Api\Exception(lang('Error listing "dn=%1"!',$dn));
		}
		$deleted = 0;
		foreach($entries as $n => $entry)
		{
			if ($n === 'count') continue;
			$deleted += $this->rdelete($entry['dn']);
		}
		if (!ldap_delete($this->test_ldap->ds,$dn))
		{
			throw new Api\Exception(lang('Error deleting "dn=%1"!',$dn));
		}
		return ++$deleted;
	}

	/**
	 * Set mailbox attribute in $this->ldap_base according to given format
	 *
	 * Uses $this->ldap_host, $this->ldap_admin and $this->ldap_admin_pw to connect.
	 *
	 * @param string $this->object_class ='qmailUser'
	 * @param string $this->mbox_attr ='mailmessagestore' lowercase!!!
	 * @param string $this->mail_login_type ='email' 'email', 'vmailmgr', 'standard' or 'uidNumber'
	 * @return string with success message N entries modified
	 * @throws Api\Exception if dn not found, not listable or delete fails
	 */
	private function set_mailbox($check_only=false)
	{
		$this->connect($this->ldap_admin,$this->ldap_admin_pw);

		// if base not set, use context minus one hierarchy, eg. ou=accounts,(o=domain,dc=local)
		if (empty($this->ldap_base) && $this->ldap_context)
		{
			list(,$this->ldap_base) = explode(',',$this->ldap_context,2);
		}
		// check if base does exist
		if (!@ldap_read($this->test_ldap->ds,$this->ldap_base,'objectClass=*'))
		{
			throw new Api\Exception\WrongUserinput(lang('Base dn "%1" NOT found!',$this->ldap_base));
		}
		$object_class = $this->object_class ? $this->object_class : 'qmailUser';
		$mbox_attr = $this->mbox_attr ? $this->mbox_attr : 'mailmessagestore';
		$mail_login_type = $this->mail_login_type ? $this->mail_login_type : 'email';

		if (!($sr = ldap_search($this->test_ldap->ds,$this->ldap_base,
				'objectClass='.$object_class,array('mail','uidNumber','uid',$mbox_attr))) ||
			!($entries = ldap_get_entries($this->test_ldap->ds, $sr)))
		{
			throw new Api\Exception(lang('Error listing "dn=%1"!',$this->ldap_base));
		}
		$modified = 0;
		foreach($entries as $n => $entry)
		{
			if ($n === 'count') continue;

			$mbox = Api\Mail\Smtp\Ldap::mailbox_addr(array(
				'account_id' => $entry['uidnumber'][0],
				'account_lid' => $entry['uid'][0],
				'account_email' => $entry['mail'][0],
			),$this->domain,$mail_login_type);

			if ($mbox === $entry[$mbox_attr][0]) continue;	// nothing to change

			if (!$check_only && !ldap_modify($this->test_ldap->ds,$entry['dn'],array(
				$mbox_attr => $mbox,
			)))
			{
				throw new Api\Exception(lang("Error modifying dn=%1: %2='%3'!",$entry['dn'],$mbox_attr,$mbox));
			}
			++$modified;
			if ($check_only) echo "$modified: $entry[dn]: $mbox_attr={$entry[$mbox_attr][0]} --> $mbox\n";
		}
		return $check_only ? lang('%1 entries would have been modified.',$modified) :
			lang('%1 entries modified.',$modified);
	}

	/**
	 * array with objectclasses for the objects we can create
	 *
	 * @var array of name => objectClass pairs (or array with multiple)
	 */
	static $requiredObjectclasses = array(
		'o' => 'organization',
		'ou' => 'organizationalUnit',
		'cn' => array('organizationalRole','simpleSecurityObject'),
		'uid' => array('uidObject','organizationalRole','simpleSecurityObject'),
		'dc' => array('organization','dcObject'),
	);

	/**
	 * Create a new node in the ldap tree
	 *
	 * @param string $dn dn to create, eg. "cn=admin,dc=local"
	 * @param array $extra =array() extra attributes to set
	 * @return boolean true if the node was create, false if it was already there
	 * @throws Api\Exception\WrongUserinput
	 */
	private function _create_node($dn,$extra=array())
	{
		// check if the node already exists and return if it does
		if (@ldap_read($this->test_ldap->ds,$dn,'objectClass=*'))
		{
			return false;
		}
		list($node,$base) = explode(',',$dn,2);

		if (!@ldap_read($this->test_ldap->ds,$base,'objectClass=*'))
		{
			$this->_create_node($base);		// create the base if it's not already there
		}
		// now we need to create the node itself
		list($name,$value) = explode('=',$node);

		if (!isset(self::$requiredObjectclasses[$name]))
		{
			throw new Api\Exception\WrongUserinput(lang('Can not create DN %1!',$dn).' '.
				lang('Supported node types:').implode(', ',array_keys(self::$requiredObjectclasses)));
		}
		if ($name == 'dc') $extra['o'] = $value;	// required by organisation
		if ($name == 'uid') $extra['cn'] = $value;	// required by organizationalRole

		if (!@ldap_add($this->test_ldap->ds,$dn,$attr = array(
			$name => $value,
			'objectClass' => self::$requiredObjectclasses[$name],
		)+$extra))
		{
			throw new Api\Exception\WrongUserinput(lang('Can not create DN %1!',$dn).
				' ('.ldap_error($this->test_ldap->ds).', attributes='.print_r($attr,true).')');
		}
		return true;
	}

	/**
	 * Return default database settings for a given domain
	 *
	 * @return array
	 */
	static function defaults()
	{
		return array(
			'ldap_host'     => 'localhost',
			'ldap_suffix'   => 'dc=local',
			'ldap_admin'    => 'cn=admin,$suffix',
			'ldap_admin_pw' => '',
			'ldap_base'     => 'o=$domain,$suffix',
			'ldap_root_dn'  => 'cn=admin,$base',
			'ldap_root_pw'  => self::randomstring(),
			'ldap_context'  => 'ou=accounts,$base',
			'ldap_search_filter' => '(uid=%user)',
			'ldap_group_context' => 'ou=groups,$base',
		);
	}

	/**
	 * Merges the default into the current properties, if they are empty or contain placeholders
	 */
	private function _merge_defaults()
	{
		foreach(self::defaults() as $name => $default)
		{
			if ($this->sub_command == 'delete_ldap' && in_array($name,array('ldap_base','ldap_context')))
			{
				continue;	// no default on what to delete!
			}
			if (!$this->$name)
			{
				//echo "<p>setting $name='{$this->$name}' to it's default='$default'</p>\n";
				$this->set_defaults[$name] = $this->$name = $default;
			}
			if (strpos($this->$name,'$') !== false)
			{
				$this->set_defaults[$name] = $this->$name = str_replace(array(
					'$domain',
					'$suffix',
					'$base',
					'$admin_pw',
				),array(
					$this->domain,
					$this->ldap_suffix,
					$this->ldap_base,
					$this->ldap_admin_pw,
				),$this->$name);
			}
		}
	}
}
