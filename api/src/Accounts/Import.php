<?php
/**
 * EGroupware Setup - Account import from LDAP (incl. ADS) to SQL
 *
 * @link https://www.egroupware.org
 * @package setup
 * @author Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts;

use EGroupware\Api;

/**
 * Account import from LDAP (incl. ADS) to SQL
 *
 * @todo test with LDAP eg. update modification time of account, if memberships change
 * @todo support changed account_lid of users e.g. be checking UID (not possible for groups)
 */
class Import
{
	/** @var Api\Accounts */
	protected $frontend_sql;
	/** @var Api\Contacts\Ldap|Api\Contacts\Ldap */
	protected $contacts;
	/** @var Api\Contacts\Sql  */
	protected $contacts_sql;
	/** @var Api\Contacts  */
	protected $contacts_sql_frontend;
	/** @var Ldap|Ads */
	protected $accounts;
	/** @var Sql */
	protected $accounts_sql;
	/** @var callable */
	protected $_logger;

	/**
	 * Conflict offset is added to an account_id/uidNumber, if it's already used (as gidNumber or by another user)
	 */
	const CONFLICT_OFFSET = 1000000;
	/**
	 * Max. value for 32-bit signed integer columns like account_id
	 */
	const MAX_INTEGER = 2147483647;
	/**
	 * Filename => [attr, mask, regexp] for jpegphoto and pubkey attributes
	 *
	 * @var array[]
	 */
	protected $files2attrs;

	/**
	 * Constructor
	 * 
	 * @param callable|null $logger function($str, $level) level: "debug", "detail", "info", "error" or "fatal"
	 */
	public function __construct(callable $logger=null)
	{
		// if we run from setup, we need to take care of loading db and egw_info/server
		if (isset($GLOBALS['egw_setup']))
		{
			if (!is_object($GLOBALS['egw_setup']->db))
			{
				$GLOBALS['egw_setup']->loaddb();
			}
			$GLOBALS['egw_info']['server'] += Api\Config::read('phpgwapi');
		}

		try
		{
			$this->_logger = $logger;

			if (!in_array($source = $GLOBALS['egw_info']['server']['account_import_source'], ['ldap', 'ads', 'univention']))
			{
				throw new \InvalidArgumentException("Invalid account_import_source='{$GLOBALS['egw_info']['server']['account_import_source']}'!");
			}

			$this->contacts = ($frontend = self::contactsFactory($source))->so_accounts ?: $frontend->somain;
			$this->contacts_sql_frontend = self::contactsFactory('sql');
			$this->contacts_sql = $this->contacts_sql_frontend->so_accounts ?: $this->contacts_sql_frontend->somain;

			$this->accounts = self::accountsFactory($source)->backend;
			$this->frontend_sql = self::accountsFactory('sql');
			$this->accounts_sql = $this->frontend_sql->backend;

			$this->files2attrs = [
				Api\Contacts::FILES_PHOTO => ['jpegphoto', Api\Contacts::FILES_BIT_PHOTO, null],
				Api\Contacts::FILES_PGP_PUBKEY => ['pubkey', Api\Contacts::FILES_BIT_PGP_PUBKEY, \addressbook_bo::$pgp_key_regexp],
				Api\Contacts::FILES_SMIME_PUBKEY => ['pubkey', Api\Contacts::FILES_BIT_SMIME_PUBKEY, Api\Mail\Smime::$certificate_regexp],
			];
		}
		catch (\Exception $e) {
			$this->logger('Error: '.$e->getMessage(), 'fatal');
			throw $e;
		}
	}

	/**
	 * Instantiate accounts object with given accounts-backend
	 *
	 * @param string $account_repository backend to use
	 * @return Api\Accounts
	 */
	protected static function accountsFactory(string $account_repository)
	{
		static $cache = [];
		if (!isset($cache[$account_repository]))
		{
			$backup_repo = $GLOBALS['egw_info']['server']['account_repository'];
			$GLOBALS['egw_info']['server']['account_repository'] = $account_repository;

			$cache[$account_repository] = new Api\Accounts();

			$GLOBALS['egw_info']['server']['account_repository'] = $backup_repo;
		}
		return $cache[$account_repository];
	}

	/**
	 * Instantiate contacts object for given accounts backend
	 *
	 * @param string $account_repository
	 * @return Api\Contacts
	 */
	protected static function contactsFactory(string $account_repository)
	{
		static $cache = [];
		if (!isset($cache[$account_repository]))
		{
			$backup_repo = $GLOBALS['egw_info']['server']['account_repository'];
			$backup_accounts = $GLOBALS['egw']->accounts;
			$GLOBALS['egw_info']['server']['account_repository'] = $account_repository;

			if ($backup_repo !== $account_repository)
			{
				$GLOBALS['egw']->accounts = self::accountsFactory($account_repository);
			}
			$cache[$account_repository] = new Api\Contacts();

			$GLOBALS['egw_info']['server']['account_repository'] = $backup_repo;
			$GLOBALS['egw']->accounts = $backup_accounts;
		}
		return $cache[$account_repository];
	}

	/**
	 * @param string $message
	 * @param string $level	log-level: "debug", "detail", "info", "error" or "fatal"

	 * @return void
	 */
	function logger(string $message, string $level)
	{
		if ($this->_logger)
		{
			call_user_func($this->_logger, $message, $level);
		}

		$loglevel = $GLOBALS['egw_info']['server']['account_import_loglevel'] ?? 'info';
		if ($loglevel === 'info' && in_array($level, ['debug', 'detail']) ||
			$loglevel === 'detail' && $level === 'debug')
		{
			return;
		}

		// log to file too
		$log = $GLOBALS['egw_info']['server']['files_dir'].'/'.self::LOG_FILE;
		if (!file_exists($dir=dirname($log)) && !mkdir($dir) || !is_dir($dir) ||
			!($fp = fopen($log, 'a+')))
		{
			error_log(__METHOD__.' '.strtoupper($level).' '.$message);
		}
		else
		{
				fwrite($fp, date('Y-m-d H:i:s O').' '.strtoupper($level).' '.$message."\n");
		}
		if (!empty($fp)) fclose($fp);
	}

	/**
	 * @param bool $initial_import true: initial sync, false: incremental sync
	 * @param bool $dry_run true: only log what would be done, but do NOT make any changes
	 * @return array with int values for keys 'created', 'updated', 'uptodate', 'errors' and string 'result'
	 * @throws \Exception also gets logged as level "fatal"
	 * @throws \InvalidArgumentException if not correctly configured
	 */
	public function run(bool $initial_import=true, bool $dry_run=false)
	{
		try {
			// determine from where we migrate to what
			if (!in_array($source = $GLOBALS['egw_info']['server']['account_import_source'], ['ldap', 'ads', 'univention']))
			{
				throw new \InvalidArgumentException("Invalid account_import_source='{$GLOBALS['egw_info']['server']['account_import_source']}'!");
			}
			if (!in_array($type = $GLOBALS['egw_info']['server']['account_import_type'], ['users', 'users+groups', 'users+local+groups']))
			{
				throw new \InvalidArgumentException("Invalid account_import_type='{$GLOBALS['egw_info']['server']['account_import_type']}'!");
			}
			if (!in_array($delete = $GLOBALS['egw_info']['server']['account_import_delete'], ['yes', 'deactivate', 'no']))
			{
				throw new \InvalidArgumentException("Invalid account_import_delete='{$GLOBALS['egw_info']['server']['account_import_delete']}'!");
			}
			if (!$initial_import && empty($GLOBALS['egw_info']['server']['account_import_lastrun']))
			{
				throw new \InvalidArgumentException(lang("You need to run the initial import first!"));
			}

			Api\Accounts::cache_invalidate();   // to not get any cached data eg. from the wrong backend

			// deleting accounts currently only works with (manual) initial import
			if (!$initial_import && $delete !== 'no')
			{
				$delete = 'no';
			}

			$created = $updated = $uptodate = $errors = $deleted = 0;
			$default_memberships = $default_group_id = null;
			if (in_array('groups', explode('+', $type)))
			{
				// for AD always do a full import, as AD seems not to update the groups modification date, if only members change
				foreach($this->groups($initial_import || $source === 'ads' ? null : $GLOBALS['egw_info']['server']['account_import_lastrun'],
					in_array('local', explode('+', $type)) ? 'no' : $delete,
					$groups, $set_members, $dry_run, $sql_groups) as $name => $val)
				{
					$$name += $val;
				}
			}
			// if only users get imported, set their primary group and memberships as configured for auto-created accounts
			else
			{
				// check if we have a comma or semicolon delimited list of groups --> add first as primary and rest as memberships
				foreach(preg_split('/[,;] */', $GLOBALS['egw_info']['server']['default_group_lid'] ?? 'Default') as $group_lid)
				{
					if (($group_id = $this->accounts_sql->name2id(trim($group_lid), 'account_lid', 'g')))
					{
						if (!$default_group_id) $default_group_id = $group_id;
						$default_memberships[] = $group_id;
					}
				}
				if (!$default_group_id && ($default_group_id = $this->accounts_sql->name2id('Default', 'account_lid', 'g')))
				{
					$default_memberships[] = $default_group_id;
				}
			}

			// query all groups in SQL
			$sql_users = [];
			if ($delete !== 'no')
			{
				$where = ['account_type' => 'u'];
				if ($delete === 'deactivate')
				{
					$where['account_status'] = 'A'; // no need to deactivate already deactivated users
				}
				foreach($GLOBALS['egw']->db->select(Sql::TABLE, 'account_id,account_lid', $where, __LINE__, __FILE__) as $row)
				{
					$sql_users[$row['account_id']] = $row['account_lid'];
				}
			}

			$filter = [
				'owner' => '0',
			];
			if (!$initial_import)
			{
				$filter[] = 'modified>='.$GLOBALS['egw_info']['server']['account_import_lastrun'];
			}
			$num = 0;
			$last_modified = null;
			$start_import = time();
			$cookie = '';
			$start = ['', 500, &$cookie]; // cookie must be a reference!
			do
			{
				$contact = $reconnected = null;
				foreach ($this->contacts->search('', false, '', ['account_lid', 'jpegphoto'], '', '', 'AND', $start, $filter) as $contact)
				{
					// if we have a regexp to filter the DN, continue on non-match
					if (!empty($GLOBALS['egw_info']['server']['account_import_dn_regexp']) &&
						!preg_match($GLOBALS['egw_info']['server']['account_import_dn_regexp'], $contact['dn']))
					{
						continue;
					}
					$new = null;
					if (!isset($last_modified) || (int)$last_modified < (int)$contact['modified'])
					{
						$last_modified = $contact['modified'];
					}
					$account = $this->accounts->read($contact['account_id']);
					// if we sync groups, change (numeric) account_id's in primary group and memberships, in case they are different
					if (in_array('groups', explode('+', $type)))
					{
						static $primary_groups=[];
						if (!isset($primary_groups[$account['account_primary_group']]) &&
							($group = $this->accounts->read($account['account_primary_group'])))
						{
							$primary_groups[$account['account_primary_group']] = $group['account_lid'];
						}
						if (($primary_grp_name=$primary_groups[$account['account_primary_group']] ?? null) &&
							($grp_id=$this->accounts_sql->name2id($primary_grp_name, 'account_lid', 'g')))
						{
							$account['account_primary_group'] = $grp_id;
						}
						// LDAP backend does not query it automatic
						if (!isset($account['memberships']))
						{
							$account['memberships'] = $this->accounts->memberships($account['account_id']) ?: [];
						}
						// primary group might not be set in memberships, but EGroupware requires it to be taken into account
						if ($primary_grp_name && !in_array($primary_grp_name, $account['memberships']) &&
							($sql_grp_id = array_search(self::strtolower($primary_grp_name), $groups)))
						{
							$account['memberships'][$sql_grp_id] = $primary_grp_name;
						}
						foreach ($account['memberships'] as $grp_id => $grp_name)
						{
							if (($sql_grp_id = array_search(self::strtolower($grp_name), $groups)) && $sql_grp_id != $grp_id)
							{
								unset($account['memberships'][$grp_id]);
								$account['memberships'][$sql_grp_id] = $grp_name;
							}
						}
					}
					// do NOT log binary content of image
					$hide_binary = ['jpegphoto' => $contact['jpegphoto'] ? bytes($contact['jpegphoto']).' bytes binary data' : null];
					$this->logger(++$num.'. User: '.json_encode($hide_binary + $contact + $account, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), 'debug');
					// check if account exists in sql
					if (!($account_id = $this->accounts_sql->name2id($account['account_lid'])))
					{
						$sql_account = $account;
						// if only users are imported set primary group as configured
						if (isset($default_group_id)) $sql_account['account_primary_group'] = $default_group_id;
						// check if account_id is not yet taken by another user or group --> add offset or unset it to let DB assign a new one
						while ($this->accounts_sql->read($sql_account['account_id']))
						{
							if (($sql_account['account_id'] += self::CONFLICT_OFFSET) > self::MAX_INTEGER)
							{
								unset($sql_account['account_id']);
								break;
							}
						}
						if ($dry_run)
						{
							$this->logger("Dry-run: would created user '$account[account_lid]' (#$account[account_id])", 'detail');
							$new = true;
						}
						elseif (($account_id = $sql_account['account_id'] = $this->accounts_sql->save($sql_account, true)) > 0)
						{
							// run addaccount hook to create eg. home-directory or mail account
							// only if account-repository is already SQL, NOT for migration
							if (($GLOBALS['egw_info']['server']['account_repository'] ?? 'sql') === 'sql')
							{
								Api\Hooks::process($sql_account+array(
										'location' => 'addaccount'
									),False,True);	// called for every app now, not only enabled ones)
							}

							$this->logger("Successful created user '$account[account_lid]' (#$account[account_id]".
								($account['account_id'] != $account_id ? " as #$account_id" : '').')', 'detail');
						}
						else
						{
							$this->logger("Error creating user '$account[account_lid]' (#$account[account_id])", 'error');
							$errors++;
							continue;
						}
					}
					elseif ($account_id < 0)
					{
						$this->logger("User '$account[account_lid]' (#$account[account_id]) already exists as group --> NOT imported!", 'error');
						$errors++;
						continue;
					}
					elseif (!($sql_account = $this->accounts_sql->read($account_id)))
					{
						throw new \Exception("User '$account[account_lid]' (#$account_id) should exist, but not found!");
					}
					else
					{
						// ignore LDAP specific fields, and empty fields
						$relevant = array_filter(array_intersect_key($account, $sql_account), static function ($attr) {
							return $attr !== null && $attr !== '';
						});
						unset($relevant['person_id']);  // is always different as it's the UID, no need to consider
						$to_update = ['account_id' => $account_id] + $relevant + $sql_account;
						// fix accounts without firstname
						if (!isset($to_update['account_firstname']) && $to_update['account_lastname'] === $to_update['account_fullname'])
						{
							$to_update['account_firstname'] = null;
						}
						if (($diff = array_diff_assoc($to_update, $sql_account)))
						{
							if ($dry_run)
							{
								$this->logger("Dry-run: would updated user '$account[account_lid]' (#$account_id): " .
									json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'detail');
								$new = false;
							}
							else
							{
								try {
									if ($this->accounts_sql->save($to_update) > 0)
									{
										// run editaccount hook to create eg. home-directory or mail account
										// only if account-repository is already SQL, NOT for migration
										if (($GLOBALS['egw_info']['server']['account_repository'] ?? 'sql') === 'sql')
										{
											Api\Hooks::process($to_update + array(
												// if there was no email set before, call add account hook, to activate mail-account
												'location' => empty($sql_account['account_email']) && !empty($to_update['account_email']) ?
													'addaccount' : 'editaccount',
											), False, True);    // called for every app now, not only enabled ones)
										}
										$this->logger("Successful updated user '$account[account_lid]' (#$account_id): " .
											json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'detail');
										if (!$new) $new = false;
									}
									else
									{
										$this->logger("Error updating user '$account[account_lid]' (#$account_id)", 'error');
										$errors++;
										continue;
									}
								}
								catch (\Exception $e) {
									$this->logger("Error updating user '$account[account_lid]' (#$account_id): ".$e->getMessage().' ('.$e->getCode().')', 'error');
									$this->logger('$to_update='.json_encode($to_update, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'detail');
									$errors++;
									continue;
								}
							}
						}
						else
						{
							$this->logger("User '$account[account_lid]' (#$account_id) already up to date", 'debug');
						}
					}
					if (!$dry_run && !($sql_contact = $this->contacts_sql->read(['account_id' => $account_id])))
					{
						$sql_contact = $contact;
						unset($sql_contact['id']);  // LDAP contact-id is the UID!
						$sql_contact['account_id'] = $account_id;
						if (!$this->contacts_sql->save($sql_contact))
						{
							$sql_contact['id'] = $this->contacts_sql->data['id'];
							$this->logger("Successful created contact for user '$account[account_lid]' (#$account_id)", 'detail');
							$new = true;
						}
						else
						{
							$this->logger("Error creating contact for user '$account[account_lid]' (#$account_id)", 'error');
							$errors++;
							continue;
						}
					}
					elseif (!$dry_run)
					{
						// photo and public keys are not stored in SQL but in filesystem, fetch it to compare
						$contact['files'] = 0;
						if ($contact['jpegphoto'] === false) $contact['jpegphoto'] = null;
						Api\Vfs::$is_root = true;
						foreach($this->files2attrs as $file => [$attr, $mask, $regexp])
						{
							if (isset($contact[$attr]))
							{
								if ($sql_contact['files'] & $mask)
								{
									$sql_contact[$attr] = ($last_attr === $attr && !empty($sql_contact[$attr]) ? $sql_contact[$attr]."\n" : '').
										file_get_contents(Api\Link::vfs_path('addressbook', $sql_contact['id'], $file));
								}
								if (!isset($regexp) || preg_match($regexp, $contact[$attr]))
								{
									$contact['files'] |= $mask;
								}
							}
							$last_attr = $attr;
						}
						Api\Vfs::$is_root = false;
						$to_update = array_merge($sql_contact, array_filter($contact, static function ($attr) {
							return $attr !== null && $attr !== '';
						}));
						// files need to be or'ed with the sql value, as otherwise e.g. picture would disappear
						$to_update['files'] |= $sql_contact['files'];
						unset($to_update['account_id'], $to_update['dn']);    // no need to update, specially as account_id might be different!
						$to_update['id'] = $sql_contact['id'];
						if (($diff = array_diff_assoc($to_update, $sql_contact)))
						{
							$need_update = $diff;
/*
							Api\Vfs::$is_root = true;
							foreach($this->files2attrs as $file => [$attr, $mask, $regexp])
							{
								if (array_key_exists($attr, $diff) && (!isset($regexp) || preg_match($regexp, $diff[$attr], $matches)))
								{
									if (($written=file_put_contents($path=Api\Link::vfs_path('addressbook', $sql_contact['id'], $file),
										isset($regexp) ? $matches[0] : $diff[$attr])) !== ($size=strlen($diff[$attr])))
									{
										$this->logger("Error updating contact data $attr ($path) of '$account[account_lid]' (#$account_id)", 'error');
										++$errors;
									}
									unset($need_update[$attr]);
									if (isset($regexp))
									{
										$to_update[$attr] = trim(preg_replace($regexp, '', $to_update[$attr])) ?: null;
									}
									$diff[$attr] = $attr === 'jpegphoto' ? 'binary data skipped' : substr($diff[$attr], 0, 100).'...';
								}
							}
							Api\Vfs::$is_root = false;
*/
							// photo_unchanged=true must be set, to not delete the photo, if it's not in LDAP
							$diff['photo_unchanged'] = $this->contacts_sql->data['photo_unchanged'] =
								!isset($diff['jpegphoto']) && (!isset($diff['files']) ||
									($to_update['files']&Api\Contacts::FILES_BIT_PHOTO) === ($sql_contact['files']&Api\Contacts::FILES_BIT_PHOTO));
							if ($need_update && $this->contacts_sql->save($to_update))
							{
								$this->logger("Error updating contact data of '$account[account_lid]' (#$account_id)", 'error');
								++$errors;
								continue;
							}
							$hide_binary = !empty($diff['jpegphoto']) ? ['jpegphoto' => bytes($diff['jpegphoto']).' bytes binary data'] : [];
							$this->logger("Successful updated contact data of '$account[account_lid]' (#$account_id): ".
								json_encode($hide_binary+$diff, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), 'detail');
							if (!$new) $new = false;
						}
						else
						{
							$this->logger("Contact data of '$account[account_lid]' (#$account_id) already up to date", 'debug');
						}
					}
					// if requested, also set memberships
					if (in_array('groups', explode('+', $type)) && !$dry_run)
					{
						// preserve memberships of local groups, if they are allowed
						$local_memberships = [];
						if (in_array('local', explode('+', $type)))
						{
							if (!isset($local_groups))
							{
								$local_groups = [];
								foreach(array_diff($sql_groups, $groups) as $gid => $group)
								{
									$local_groups[$gid] = $this->accounts_sql->members($gid);
								}
								if ($local_groups)
								{
									$this->logger("Preserving members of local groups: ".implode(', ', array_diff($sql_groups, $groups)), 'detail');
								}
							}
							$local_memberships = array_keys(array_filter($local_groups, static function($members) use ($account_id)
							{
								return isset($members[$account_id]);
							}));
						}
						// we need to convert the account_id's of memberships, in case we use different ones in SQL
						$this->accounts_sql->set_memberships(array_merge(array_filter(array_map(static function($account_lid) use ($groups)
						{
							return array_search(self::strtolower($account_lid), $groups);
						}, $account['memberships'] ?: [])), $local_memberships), $account_id);
					}
					// if only users are synced add new users to default group(s) as configured for auto-created accounts
					elseif ($new && $default_memberships)
					{
						$this->accounts_sql->set_memberships($default_memberships, $account_id);
					}

					if ($new)
					{
						++$created;
					}
					elseif ($new === false)
					{
						++$updated;
					}
					else
					{
						++$uptodate;
					}
					// remember the users we imported, to be able to delete the ones we dont
					unset($sql_users[$account_id]);
				}
				/* check if connection was somehow lost / timed out and reconnect
				if ($initial_import && !isset($contact) && ldap_errno($this->contacts->ds) === -1)
				{
					$this->contacts->ds = $this->accounts->ldap_connection(true);
					$reconnected = true;
					$this->logger("Reconnected to LDAP server", 'info');
				}*/
			}
			while ($reconnected || $start[2] !== '');

			if ($set_members)
			{
				foreach($this->setMembers($set_members, $dry_run) as $name => $num)
				{
					$$name += $num;
				}
			}

			// ignore / never delete anonymous user, which is required for EGroupware to function properly
			$sql_users = array_diff($sql_users, ['anonymous']);
			// do we need to delete (or deactivate) no longer existing users
			if ($delete !== 'no' && $sql_users)
			{
				$num = count($sql_users);
				if ($dry_run)
				{
					$this->logger("Dry-run: would ".($delete === 'deactivate' ? 'deactivate' : 'delete')." $num no longer existing user(s): ".implode(', ', array_map(static function ($account_id, $account_lid)
					{
						return $account_lid.' (#'.$account_id.')';
					}, array_keys($sql_users), $sql_users)), 'detail');
					$deleted += $num;
				}
				elseif ($delete === 'deactivate')
				{
					$GLOBALS['egw']->db->update(Sql::TABLE, ['account_status' => null], ['account_id' => array_keys($sql_users)], __LINE__, __FILE__);
					$this->logger("Deactivated $num no longer existing user(s): ".implode(', ', array_map(static function ($account_id, $account_lid)
					{
						return $account_lid.' (#'.$account_id.')';
					}, array_keys($sql_users), $sql_users)), 'detail');
					$deleted += $num;
				}
				else
				{
					foreach($sql_users as $account_id => $account_lid)
					{
						if ($this->deleteAccount($account_id, $account_lid))
						{
							$deleted++;
						}
						else
						{
							$errors++;
						}
					}
				}
			}

			$last_run = max($start_import-1, $last_modified);
			Api\Config::save_value('account_import_lastrun', $last_run, 'phpgwapi');
			$str = gmdate('Y-m-d H:i:s', $last_run). ' UTC';
			if (!$errors && !$dry_run)
			{
				$this->logger("Setting new incremental import time to: $str ($last_run)", 'detail');
			}
			if ($created || $updated || $errors || $deleted)
			{
				if ($dry_run)
				{
					$result = "Dry-run: would created $created, updated $updated and deleted $deleted account(s).";
				}
				else
				{
					$result = "Created $created, updated $updated and deleted $deleted account(s), with $errors error(s).";
				}
			}
			else
			{
				$result = "All accounts are up-to-date.";
			}
			$this->logger($result, 'info');

			if (!$dry_run && $initial_import && self::installAsyncJob((float)$GLOBALS['egw_info']['server']['account_import_frequency'] ?? 0.0,
					$GLOBALS['egw_info']['server']['account_import_time'] ?? null))
			{
				$this->logger('Async job for periodic import installed', 'info');
			}
		}
		catch(\Exception $e) {
			$this->logger($e->getMessage(), 'fatal');
			$GLOBALS['egw']->accounts = $frontend;
			throw $e;
		}
		// restore regular frontend
		$GLOBALS['egw']->accounts = $frontend;

		return [
			'created'  => $created,
			'updated'  => $updated,
			'uptodate' => $uptodate,
			'errors'   => $errors,
			'deleted'  => $deleted,
			'result'   => $result,
		];
	}

	/**
	 * Import all groups
	 *
	 * We assume we can list all groups without running into memory or timeout issues.
	 * Groups with identical names as users are skipped, but logged as error.
	 *
	 * We can only delete no longer existing groups, if we query all groups!
	 * So $delete !== 'no', requires $modified === null.
	 *
	 * @param int|null $modified null: initial import, int: timestamp of last import
	 * @param string $delete what to do with no longer existing groups: "yes": delete incl. data, "deactivate": delete group, "no": do nothing
	 * @param array|null &$groups on return all current groups as account_id => account_lid pairs
	 * @param array|null &$set_members on return, if modified: (sql)account_id => [(ldap)account_id => account_lid] pairs
	 * @param bool $dry_run true: only log what would be done, but do NOT make any changes
	 * @param array|null &$sql_groups on return all current groups in SQL as account_id => account_lid pairs
	 * @return int[] values for keys "created", "updated", "uptodate", "errors", "deleted"
	 */
	protected function groups(?int $modified, string $delete, array &$groups=null, array &$set_members=null, bool $dry_run=false, array &$sql_groups=null)
	{
		// to delete no longer existing groups, we have to query all groups!
		if ($modified) $delete = 'no';
		$local_groups = in_array('local', explode('+', $GLOBALS['egw_info']['server']['account_import_type']));

		// query all groups in SQL
		$sql_groups = $groups = $set_members = [];
		foreach($GLOBALS['egw']->db->select(Sql::TABLE, 'account_id,account_lid', ['account_type' => 'g'], __LINE__, __FILE__) as $row)
		{
			$sql_groups[-$row['account_id']] = self::strtolower($row['account_lid']);
		}
		// fill groups with existing ones, for incremental sync, but only if we have NO local groups, otherwise we need to query them all
		$filter = ['type' => 'groups'];
		if ($modified && !$local_groups)
		{
			$groups = $sql_groups;
			$filter['modified'] = $modified;
		}

		$created = $updated = $uptodate = $errors = $deleted = $num = 0;
		foreach($this->accounts->search($filter) as $account_id => $group)
		{
			// if we have a regexp to filter the DN, continue on non-match
			if (!empty($GLOBALS['egw_info']['server']['account_import_dn_regexp']) &&
				!preg_match($GLOBALS['egw_info']['server']['account_import_dn_regexp'], $group['account_dn']))
			{
				continue;
			}
			// for local-groups, we always have to read all groups (to be able to determine which ones are local and preserve their memberships)
			if ($modified && $local_groups && $group['account_modified'] < $modified &&
				($sql_id = $this->accounts_sql->name2id($group['account_lid'])))
			{
				$groups[$sql_id] = self::strtolower($group['account_lid']);
				continue;   // not logging them as changed, just had to get them (efficient in one query)
			}
			$this->logger(++$num.'. Group: '.json_encode($group, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 'debug');

			if (!($sql_id = array_search(self::strtolower($group['account_lid']), $sql_groups)))
			{
				if ($this->accounts_sql->name2id($group['account_lid']) > 0)
				{
					$this->logger("Group '$group[account_lid]' already exists as user --> skipped!", 'error');
					$errors++;
					continue;
				}
				// check if the numeric account_id is not yet taken --> unset account_id and let DB create a new one
				if ($this->accounts_sql->read($account_id))
				{
					unset($group['account_id']);
				}
				if ($dry_run)
				{
					$this->logger("Dry-run: would create group '$group[account_lid]' (#$account_id".($sql_id != $account_id ? " as #$sql_id" : '').')', 'detail');
					$created++;
					continue;
				}
				if (($sql_id = $group['account_id'] = $this->accounts_sql->save($group, true)) < 0)
				{
					// run addgroup hook to create eg. home-directory or mail account
					Api\Hooks::process($group+array(
							'location' => 'addgroup'
						),False,True);	// called for every app now, not only enabled ones)

					$this->logger("Successful created group '$group[account_lid]' (#$account_id".($sql_id != $account_id ? " as #$sql_id" : '').')', 'detail');
					$created++;
				}
				else
				{
					$this->logger("Error creating group '$group[account_lid]' (#$account_id)", 'error');
					$errors++;
					continue;
				}
			}
			elseif (!($sql_group = $this->accounts_sql->read($sql_id)))
			{
				throw new \Exception("Group '$group[account_lid]' (#$sql_id) should exist, but not found!");
			}
			else
			{
				$group['account_id'] = $sql_id;
				unset($sql_group['account_fullname'], $sql_group['account_firstname'], $sql_group['account_lastname']); // not stored anywhere
				// ignore LDAP specific fields, and empty fields
				$relevant = array_filter(array_intersect_key($group, $sql_group), static function ($attr) {
					return $attr !== null && $attr !== '';
				});
				$to_update = $relevant + $sql_group;
				if (($diff = array_diff_assoc($to_update, $sql_group)))
				{
					if ($dry_run)
					{
						$this->logger("Dry-run: would update group '$group[account_lid]' (#$sql_id): ".json_encode($diff), 'detail');
						$updated++;
					}
					elseif ($this->accounts_sql->save($to_update) < 0)
					{
						Api\Hooks::process($to_update+array(
								'location' => 'editgroup',
								'old_name' => $sql_group['account_lid'],
							),False,True);	// called for every app now, not only enabled ones)

						$this->logger("Successful updated group '$group[account_lid]' (#$sql_id): " .
							json_encode($diff, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'detail');
						$updated++;
					}
					else
					{
						$this->logger("Error updating group '$group[account_lid]' (#$sql_id)", 'error');
						$errors++;
						continue;
					}
				}
				else
				{
					$this->logger("Group '$group[account_lid]' (#$sql_id) already up to date", 'debug');
					$uptodate++;
				}
				// unset the updated groups, so we can delete the ones not returned from LDAP
				unset($sql_groups[$sql_id]);
			}
			$groups[$sql_id] = self::strtolower($group['account_lid']);

			// we need to record and return the id's to update members, AFTER users are created/updated
			if (is_a($this->accounts, Ads::class))
			{
				// ADS::members() calls the frontend, have to use ADS::getMembers() instead
				$set_members[$sql_id] = $this->accounts->getMembers($group);
			}
			else
			{
				$set_members[$sql_id] = $this->accounts->members($group['account_id']);
			}
		}

		// delete the groups not returned from LDAP, groups can NOT be deactivated, we just delete them in the DB
		foreach($delete !== 'no' ? $sql_groups : [] as $account_id => $account_lid)
		{
			if ($delete === 'yes')
			{
				if ($dry_run)
				{
					$this->logger("Dry-run: would delete group '$group[account_lid]' (#$sql_id)", 'detail');
					$delete++;
				}
				elseif ($this->deleteAccount($account_id, $account_lid))
				{
					$deleted++;
				}
				else
				{
					$errors++;
				}
			}
		}
		return [
			'created'  => $created,
			'updated'  => $updated,
			'uptodate' => $uptodate,
			'errors'   => $errors,
			'deleted'  => $deleted,
		];
	}

	/**
	 * Delete an account from SQL including its data
	 *
	 * We use admin_cmd_delete_account to also log that the account was deleted.
	 *
	 * @param int $account_id
	 * @param string $account_lid
	 * @param bool $dry_run true: only log what would be done, but do NOT make any changes
	 * @return bool
	 */
	protected function deleteAccount(int $account_id, string $account_lid, bool $dry_run=false)
	{
		// make sure admin_cmd_delete_account uses the SQL accounts object, to not delete in the source, but in EGroupware DB!
		$backup_accounts = $GLOBALS['egw']->accounts;
		$GLOBALS['egw']->accounts = $this->frontend_sql;

		$type = $account_id < 0 ? 'group' : 'user';

		try {
			if ($dry_run)
			{
				$this->logger("Dry-run: would deleted no longer existing $type '$account_lid' (#$account_id)", 'detail');
			}
			else
			{
				$cmd = new \admin_cmd_delete_account($account_id, null, $account_id > 0);
				$this->logger("Successful deleted no longer existing $type '$account_lid' (#$account_id): ".$cmd->run(), 'detail');
			}
		}
		catch (\Exception $e) {
			$this->logger("Error deleting no longer existing $type '$account_lid' (#$account_id): ".$e->getMessage(), 'error');
		}
		$GLOBALS['egw']->accounts = $backup_accounts;

		return !isset($e);
	}

	/**
	 * Hook called when setup configuration is being stored:
	 * - install/removing cron job to periodic import accounts from LDAP/ADS
	 *
	 * @param array $location key "newsettings" with reference to changed settings from setup > configuration
	 * @throws \Exception for errors
	 */
	public static function setupConfig(array $location)
	{
		$config =& $location['newsettings'];

		// check if periodic import is configured AND initial sync already done
		foreach(['account_import_type', 'account_import_source', 'account_import_frequency'] as $name)
		{
			if (empty($config[$name]))
			{
				self::installAsyncJob();
				return;
			}
		}
		if (empty(Api\Config::read('phpgwapi')['account_import_lastrun']))
		{
			self::installAsyncJob();
		}

		self::installAsyncJob((float)$config['account_import_frequency'], $config['account_import_time']);
	}

	const ASYNC_JOB_ID = 'AccountsImport';

	/**
	 * Install async job for periodic import, if configured
	 *
	 * @param float $frequency
	 * @param string|null $time
	 * @return bool true: job installed, false: job canceled, if it was already installed
	 */
	protected static function installAsyncJob(float $frequency=0.0, string $time=null)
	{
		$async = new Api\Asyncservice();
		$async->cancel_timer(self::ASYNC_JOB_ID);

		if (empty($frequency) && !empty($time) && preg_match('/^\d{2}:\d{2}$/', $time))
		{
			$frequency = 24.0;
		}
		if ($frequency > 0.0)
		{
			[$hour, $min] = explode(':', $time ?: '00:00');
			$times = ['hour' => (int)$hour, 'min' => (int)$min];
			if ($frequency >= 36)
			{
				$times['day'] = '*/'.round($frequency/24.0);    // 48h => day: */2
			}
			elseif ($frequency >= 24)
			{
				$times['day'] = '*';
			}
			elseif ($frequency >= 1)
			{
				$times['hour'] = round($frequency) == 1 ? '*' : '*/'.round($frequency);
			}
			elseif ($frequency >= .1)
			{
				$times = ['min' => '*/'.(5*round(12*$frequency))];   // .1 => */5, .5 => */30
			}
			$async->set_timer($times, self::ASYNC_JOB_ID, self::class.'::async');

			return true;
		}

		return false;
	}

	const LOG_FILE = 'setup/account-import.log';

	/**
	 * Run import via async job
	 *
	 * First daily run is a full import, if deleting or deactivating accounts is configured, all others are incremental imports
	 *
	 * @return void
	 */
	public static function async()
	{
		try {
			$import = new self();
			$import->logger(date('Y-m-d H:i:s O').' LDAP account import started', 'info');
			$import->run(in_array($GLOBALS['egw_info']['server']['account_import_delete'] ?? 'no', ['yes', 'deactivate']) &&
				self::firstRunToday());
			$import->logger(date('Y-m-d H:i:s O').' LDAP account import finished', 'info');
		}
		catch (\InvalidArgumentException $e) {
			_egw_log_exception($e);
			// disable async job, something is not configured correct
			self::installAsyncJob();
			$import->logger('Async job for periodic import canceled', 'fatal');
		}
		catch (\Exception $e) {
			_egw_log_exception($e);
			$import->logger('Error: '.$e->getMessage(), 'fatal');
		}
	}

	/**
	 * Check if current time / run is the first one for today
	 *
	 * @return bool
	 */
	public static function firstRunToday()
	{
		if (empty($frequency=$GLOBALS['egw_info']['server']['account_import_frequency']))
		{
			return false;
		}
		// check current time <= time of first run today (frequency is in hours)
		return time() <= mktime(floor($frequency), round((60*$frequency)%60), 60);   // 60 seconds grace time
	}

	/**
	 * Tail the async import log
	 *
	 * @return void
	 * @throws Api\Exception\WrongParameter
	 * @todo get this working in setup
	 */
	public function showLog()
	{
		echo (new Api\Framework\Minimal())->header(['pngfix' => '']);
		$tailer = new Api\Json\Tail(self::LOG_FILE);
		echo $tailer->show();
	}

	/**
	 * Set members of a group specified by its (sql)account_id after an incremental update of the groups
	 *
	 * We need to take into account:
	 * - members/users might not yet be added, if visible members are by membership to that group (e.g. custom account-filter by membership in Default group)
	 * - members might not be readable from LDAP, because they are not in account-filter
	 *
	 * @param array $set_members (sql)account_id => [(ldap)account_id => account_lid] pairs
	 * @param bool $dry_run true: only log what would be done, but do NOT make any changes
	 * @return int[] values for keys "created", "updated" and "errors"
	 * @todo add dry_run support
	 */
	protected function setMembers(array $set_members, bool $dry_run=false)
	{
		if ($dry_run)
		{
			$this->logger("Dry-run: setting (or adding) members of groups not (yet) supported --> ignored", 'detail');
			return [];
		}
		// setting (new) members
		$created = $updated = $errors = 0;
		foreach($set_members as $sql_group_id => $members)
		{
			$group = $this->accounts_sql->id2name($sql_group_id) ?: '#'.$sql_group_id;
			foreach($members as $ldap_id => $account_lid)
			{
				if (!($account_id = $this->accounts_sql->name2id($account_lid)))
				{
					if (!($account = $this->accounts->read($ldap_id)))
					{
						$this->logger("Failed reading user '$account_lid' (#$ldap_id) from LDAP to set as member of group '$group', maybe he is not contained in filter --> ignored", 'detail');
						continue;
					}
					// LDAP backend does not query it automatic
					if (!isset($account['memberships']))
					{
						$account['memberships'] = $this->accounts->memberships($ldap_id);
					}
					if (!($contact = $this->contacts->read($account['person_id'])))
					{
						$this->logger("Error reading contact-data of user '$account_lid' (#$ldap_id)", 'error');
						$errors++;
						continue;
					}
					$this->logger(json_encode($account + $contact, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 'debug');

					$sql_account = $account;
					// check if ldap-id is already taken --> create with own id
					if ($this->accounts_sql->id2name($ldap_id))
					{
						unset($sql_account['account_id']);
					}
					if (($sql_account['account_id'] = $this->accounts_sql->save($sql_account, true)))
					{
						$this->logger("Successful created user '$account_lid' (#$ldap_id)", 'detail');
						$created++;

						// save contact-data of user
						$sql_contact = $contact;
						$sql_contact['account_id'] = $sql_account['account_id'];
						unset($sql_contact['id']);
						if ($this->contacts_sql->save($sql_contact))
						{
							$this->logger("Error saving contact-data of user '$account_lid' (#$ldap_id)", 'error');
							$errors++;
						}
					}
					else
					{
						$this->logger("Error creating user '$account_lid' (#$ldap_id)", 'error');
						$errors++;
						continue;
					}
					// set memberships using id's in sql (!)
					$this->accounts_sql->set_memberships(array_filter(array_map(function($account_lid)
					{
						return $this->accounts_sql->name2id($account_lid);
					}, $account['memberships'] ?? [])), $sql_account['account_id']);
				}
				else
				{
					if (($memberships = $this->accounts_sql->memberships($account_id)) === false)
					{
						$this->logger("Error reading memberships of (existing) user '$account_lid' (#$account_id)!", 'error');
						$errors++;
						continue;
					}
					if (!isset($memberships[$sql_group_id]))
					{
						$this->accounts_sql->set_memberships(array_merge(array_keys($memberships), [$sql_group_id]), $account_id);
						$this->logger("Adding membership of user '$account_lid' (#$account_id) to group '$group' (#$sql_group_id)", 'detail');
						$updated++;
					}
				}
			}

			// removing no longer set members
			if (($sql_members = $this->accounts_sql->members($sql_group_id)) &&
				($removed = array_diff($sql_members, $members)))
			{
				foreach($removed as $sql_account_id => $sql_account_lid)
				{
					if (($memberships = $this->accounts_sql->memberships($sql_account_id)) && isset($memberships[$sql_group_id]))
					{
						unset($memberships[$sql_group_id]);
						$this->accounts_sql->set_memberships(array_keys($memberships), $sql_account_id);
						$this->logger("Removing membership of user '$sql_account_lid' (#$sql_account_id) from group '$group' (#$sql_group_id)", 'detail');
						$updated++;
					}
				}
			}
		}
		return [
			'created' => $created,
			'updated' => $updated,
			'errors'  => $errors,
		];
	}

	/**
	 * Lowercase a string using mb_strtolower, if available
	 *
	 * @param string $str
	 * @return string
	 */
	static protected function strtolower($str)
	{
		static $strtolower = null;
		if (!isset($strtolower))
		{
			$strtolower = function_exists('mb_strtolower') ? 'mb_strtolower' : 'strtolower';
		}
		return $strtolower($str);
	}
}