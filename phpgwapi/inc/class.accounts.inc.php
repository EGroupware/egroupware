<?php
/**
 * API - accounts
 * 
 * This class extends a backend class (at them moment SQL or LDAP) and implements some
 * caching on to top of the backend functions. The cache is share for all instances of
 * the accounts class and for LDAP it is persistent through the whole session, for SQL 
 * it's only on a per request basis.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006 and earlier modifications
 * 
 * Implements the (now depricated) interfaces on the former accounts class written by 
 * Joseph Engo <jengo@phpgroupware.org> and Bettina Gille <ceb@phpgroupware.org>
 * Copyright (C) 2000 - 2002 Joseph Engo, Copyright (C) 2003 Joseph Engo, Bettina Gille
 * 
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @access public
 * @version $Id$
 */

// load the backend class, which this class extends
if (empty($GLOBALS['egw_info']['server']['account_repository']))
{
	if (!empty($GLOBALS['egw_info']['server']['auth_type']))
	{
		$GLOBALS['egw_info']['server']['account_repository'] = $GLOBALS['egw_info']['server']['auth_type'];
	}
	else
	{
		$GLOBALS['egw_info']['server']['account_repository'] = 'sql';
	}
}
include_once(EGW_API_INC . '/class.accounts_' . $GLOBALS['egw_info']['server']['account_repository'] . '.inc.php');

/**
 * API - accounts
 * 
 * This class extends a backend class (at them moment SQL or LDAP) and implements some
 * caching on to top of the backend functions. The cache is share for all instances of
 * the accounts class and for LDAP it is persistent through the whole session, for SQL 
 * it's only on a per request basis.
 * 
 * The backend only implements the read, save, delete, name2id and the {set_}members{hips} methods. 
 * The account class implements all other (eg. name2id, id2name) functions on top of these.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006
 * 
 * Implements the (now depricated) interfaces on the former accounts class written by 
 * Joseph Engo <jengo@phpgroupware.org> and Bettina Gille <ceb@phpgroupware.org>
 * Copyright (C) 2000 - 2002 Joseph Engo, Copyright (C) 2003 Joseph Engo, Bettina Gille
 * 
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @access public
 * @version $Id$
 */
class accounts extends accounts_backend
{
	var $xmlrpc_methods = array(
		array(
			'name'        => 'search',
			'description' => 'Returns a list of accounts and/or groups'
		),
		array(
			'name'        => 'name2id',
			'description' => 'Cross reference account_lid with account_id'
		),
		array(
			'name'        => 'id2name',
			'description' => 'Cross reference account_id with account_lid'
		),
		array(
			'name'        => 'get_list',
			'description' => 'Depricated: use search. Returns a list of accounts and/or groups'
		),
	);
	/**
	 * enables the session-cache, done in the constructor for the LDAP backend only
	 * 
	 * @var boolean $use_session_cache
	 */
	var $use_session_cache = true;
	
	/**
	 * Depricated: Account this class was instanciated for
	 *
	 * @deprecated dont use this in new code, always explcitly specify the account to use
	 * @var int account_id
	 */
	var $account_id;
	/**
	 * Depricated: Account data of $this->account_id
	 *
	 * @deprecated dont use this in new code, store the data in your own code
	 * @var unknown_type
	 */
	var $data;

	/**
	 * Keys for which both versions with 'account_' prefix and without (depricated!) can be used, if requested.
	 * Migrate your code to always use the 'account_' prefix!!!
	 *
	 * @var array
	 */
	var $depricated_names = array('firstname','lastname','fullname','email','type',
		'status','expires','lastlogin','lastloginfrom','lastpasswd_change');

	/**
	 * Constructor
	 * 
	 * @param int $account_id=0 account to instanciate the class for (depricated)
	 */
	function accounts($account_id=0)
	{
		if($account_id && is_numeric($account_id)) $this->account_id = (int) $account_id;

		$this->accounts_backend();	// call constructor of extended class
	}

	/**
	 * Searches / lists accounts: users and/or groups
	 *
	 * @param array with the following keys:
	 * @param $param['type'] string/int 'accounts', 'groups', 'owngroups' (groups the user is a member of), 'both'
	 *	or integer group-id for a list of members of that group
	 * @param $param['start'] int first account to return (returns offset or max_matches entries) or all if not set
	 * @param $param['sort'] string column to sort after, default account_lid if unset
	 * @param $param['order'] string 'ASC' or 'DESC', default 'DESC' if not set
	 * @param $param['query'] string to search for, no search if unset or empty
	 * @param $param['query_type'] string:
	 *	'all'   - query all fields for containing $param[query]
	 *	'start' - query all fields starting with $param[query]
	 *	'exact' - query all fields for exact $param[query]
	 *	'lid','firstname','lastname','email' - query only the given field for containing $param[query]
	 * @param $param['app'] string with an app-name, to limit result on accounts with run-right for that app
	 * @param $param['offset'] int - number of matches to return if start given, default use the value in the prefs
	 * @return array with uid / data pairs, data is an array with account_id, account_lid, account_firstname,
	 *	account_lastname, person_id (id of the linked addressbook entry), account_status, account_expires, account_primary_group
	 */
	function search($param)
	{
		//echo "<p>accounts::search(".print_r($param,True).")</p>\n";
		$this->setup_cache();
		$account_search = &$this->cache['account_search'];
		
		$serial = serialize($param);

		if (isset($account_search[$serial]))
		{
			$this->total = $account_search[$serial]['total'];
		}
		elseif (method_exists('accounts_','search'))	// implements its on search function ==> use it
		{
			$account_search[$serial]['data'] = parent::search($param);
			$account_search[$serial]['total'] = $this->total;
		}
		else
		{
			$serial2 = $serial;
			if (is_numeric($param['type']) || $param['app'] || $param['type'] == 'owngroups')	// do we need to limit the search on a group or app?
			{
				$app = $param['app'];
				unset($param['app']);
				if (is_numeric($param['type']))
				{
					$group = (int) $param['type'];
					$param['type'] = 'accounts';
				}
				elseif ($param['type'] == 'owngroups')
				{
					$group = true;
					$param['type'] = 'groups';
				}
				$start = $param['start'];
				unset($param['start']);
				$serial2 = serialize($param);
			}
			if (!isset($account_search[$serial2]))	// check if we already did this general search
			{
				$account_search[$serial2]['data'] = array();
				$accounts = parent::get_list($param['type'],$param['start'],$param['sort'],$param['order'],$param['query'],$param['offset'],$param['query_type']);
				if (!$accounts) $accounts = array();
				foreach($accounts as $data)
				{
					$account_search[$serial2]['data'][$data['account_id']] = $data;
				}
				$account_search[$serial2]['total'] = $this->total;
			}
			else
			{
				$this->total = $account_search[$serial2]['total'];
			}
			//echo "parent::get_list($param[type],$param[start],$param[sort],$param[order],$param[query],$param[offset],$param[query_type]) returned<pre>".print_r($account_search[$serial2],True)."</pre>\n";
			if ($app || $group)	// limit the search on accounts with run-rights for app or a group
			{
				$valid = array();
				if ($app)
				{
					$valid = $this->split_accounts($app,$param['type'] == 'both' ? 'merge' : $param['type']);
				}
				if ($group)
				{
					$members = is_int($group) ? $this->members($group,true) : $this->memberships($GLOBALS['egw_info']['user']['account_id'],true);
					if (!$members) $members = array();
					$valid = !$app ? $members : array_intersect($valid,$members);	// use the intersection
				}
				//echo "<p>limiting result to app='app' and/or group=$group valid-ids=".print_r($valid,true)."</p>\n";
				$offset = $param['offset'] ? $param['offset'] : $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
				$stop = $start + $offset;
				$n = 0;
				$account_search[$serial]['data'] = array();
				foreach ($account_search[$serial2]['data'] as $id => $data)
				{
					if (!in_array($id,$valid))
					{
						$this->total--;
						continue;
					}
					// now we have a valid entry
					if (!is_int($start) || $start <= $n && $n < $stop)
					{
						$account_search[$serial]['data'][$id] = $data;
					}
					$n++;
				}
				$account_search[$serial]['total'] = $this->total;
			}
		}
		//echo "<p>accounts::search('$serial')=<pre>".print_r($account_search[$serial]['data'],True).")</pre>\n";
		return $account_search[$serial]['data'];
	}
	
	/**
	 * Reads the data of one account
	 *
	 * It's depricated to use read with out parameter to read the internal data of this class!!!
	 * All key of the returned array use the 'account_' prefix. 
	 * For backward compatibility some values are additionaly availible without the prefix, using them is depricated!
	 * 
	 * @param int/string $id numeric account_id or string with account_lid (use of default value of 0 is depricated!!!)
	 * @param boolean $set_depricated_names=false set _additionaly_ the depricated keys without 'account_' prefix
	 * @return array/boolean array with account data (keys: account_id, account_lid, ...) or false if account not found
	 */
	function read($id=0,$set_depricated_names=false)
	{
		if (!$id)	// deprecated use!!!
		{
			return $this->data ? $this->data : $this->read_repository();
		}
		if (!is_int($id) && !is_numeric($id))
		{
			$id = $this->name2id($id);
		}
		if (!$id) return false;
		
		$this->setup_cache();
		$account_data = &$this->cache['account_data'];

		if (!isset($account_data[$id]))
		{
			$account_data[$id] = parent::read($id);
		}
		if (!$account_data[$id] || !$set_depricated_names) 
		{
			return $account_data[$id];
		}
		$data = $account_data[$id];
		foreach($this->depricated_names as $name)
		{
			$data[$name] =& $data['account_'.$name];
		}
		return $data;
	}
	
	/**
	 * Saves / adds the data of one account
	 * 
	 * If no account_id is set in data the account is added and the new id is set in $data.
	 *
	 * @param array $data array with account-data
	 * @param boolean $check_depricated_names=false check _additionaly_ the depricated keys without 'account_' prefix
	 * @return int/boolean the account_id or false on error
	 */
	function save(&$data,$check_depricated_names=false)
	{
		if ($check_depricated_names)
		{
			foreach($this->depricated_names as $name)
			{
				if (isset($data[$name]) && !isset($data['account_'.$name]))
				{
					$data['account_'.$name] =& $data[$name];
				}
			}
		}
		if (($id = parent::save($data)) && $data['account_type'] != 'g')
		{ 
			// if we are not on a pure LDAP system, we have to write the account-date via the contacts class now
			if (($GLOBALS['egw_info']['server']['account_repository'] != 'ldap' ||
				$GLOBALS['egw_info']['server']['contact_repository'] == 'sql-ldap') &&
				(!($old = $this->read($data['account_id'])) ||	// only for new account or changed contact-data
				$old['account_firstname'] != $data['account_firstname'] ||
				$old['account_lastname'] != $data['account_lastname'] ||
				$old['account_email'] != $data['account_email']))
			{
				if (!$data['person_id']) $data['person_id'] = $old['person_id'];

				if (!is_object($GLOBALS['egw']->contacts))
				{
					$GLOBALS['egw']->contacts =& CreateObject('phpgwapi.contacts');
				}
				$contact = array(
					'n_given'    => $data['account_firstname'],
					'n_family'   => $data['account_lastname'],
					'email'      => $data['account_email'],
					'account_id' => $data['account_id'],
					'id'         => $data['person_id'],
					'owner'      => 0,
				);
				$GLOBALS['egw']->contacts->save($contact,true);		// true = ignore addressbook acl
			}
			// save primary group if necessary
			if ($data['account_primary_group'] && (!($memberships = $this->memberships($id,true)) || 
				!in_array($data['account_primary_group'],$memberships)))
			{
				$this->cache_invalidate($data['account_id']);
				$memberships[] = $data['account_primary_group'];
				$this->set_memberships($memberships,$id);
			}
		}
		$this->cache_invalidate($data['account_id']);
		
		return $id;
	}
	
	/**
	 * Delete one account, deletes also all acl-entries for that account
	 *
	 * @param int/string $id numeric account_id or string with account_lid
	 * @return boolean true on success, false otherwise
	 */
	function delete($id)
	{
		if (!is_int($id) && !is_numeric($id))
		{
			$id = $this->name2id($id);
		}
		if (!$id) return false;

		$this->cache_invalidate($id);
		parent::delete($id);
		
		// delete all acl_entries belonging to that user or group
		$GLOBALS['egw']->acl->delete_account($id);
		
		return true;
	}

	/**
	 * test if an account is expired
	 *
	 * @param array $data=null array with account data, not specifying the account is depricated!!!
	 * @return boolean true=expired (no more login possible), false otherwise
	 */
	function is_expired($data=null)
	{
		if (is_null($data)) $data = $this->data;	// depricated use

		$expires = isset($data['account_expires']) ? $data['account_expires'] : $data['expires'];

		return $expires != -1 && $expires < time();
	}

	/**
	 * convert an alphanumeric account-value (account_lid, account_email) to the account_id
	 *
	 * Please note:
	 * - if a group and an user have the same account_lid the group will be returned (LDAP only)
	 * - if multiple user have the same email address, the returned user is undefined
	 * 
	 * @param string $name value to convert
	 * @param string $which='account_lid' type of $name: account_lid (default), account_email, person_id, account_fullname
	 * @param string $account_type=null u = user or g = group, or default null = try both
	 * @return int/false numeric account_id or false on error ($name not found)
	 */
	function name2id($name,$which='account_lid',$account_type=null)
	{
		$this->setup_cache();
		$name_list = &$this->cache['name_list'];

		if(@isset($name_list[$which][$name]) && $name_list[$which][$name])
		{
			return $name_list[$which][$name];
		}

		// Don't bother searching for empty account_lid
		if(empty($name))
		{
			return False;
		}
		return $name_list[$which][$name] = parent::name2id($name,$which,$account_type);
	}

	/**
	 * Convert an numeric account_id to any other value of that account (account_lid, account_email, ...)
	 * 
	 * Uses the read method to fetch all data.
	 *
	 * @param int $account_id numerica account_id
	 * @param string $which='account_lid' type to convert to: account_lid (default), account_email, ...
	 * @return string/false converted value or false on error ($account_id not found)
	 */
	function id2name($account_id,$which='account_lid')
	{
		if (!($data = $this->read($account_id))) return false;
		
		return $data[$which];
		
		$this->setup_cache();
		$id_list = &$this->cache['id_list'];
	}

	/**
	 * get the type of an account: 'u' = user, 'g' = group
	 *
	 * @param int/string $accountid numeric account-id or alphanum. account-lid, 
	 *	if !$accountid account of the user of this session
	 * @return string/false 'u' = user, 'g' = group or false on error ($accountid not found)
	 */
	function get_type($account_id)
	{
		if (!is_int($account_id) && !is_numeric($account_id))
		{
			$account_id = $this->name2id($account_id);
		}
		return $account_id > 0 ? 'u' : ($account_id < 0 ? 'g' : false);
	}
	
	/**
	 * check if an account exists and if it is an user or group
	 *
	 * @param int/string $account_id numeric account_id or account_lid
	 * @return int 0 = acount does not exist, 1 = user, 2 = group 
	 */
	function exists($account_id)
	{
		if (!($data = $this->read($account_id)))
		{
			return 0;
		}
		return $data['account_type'] == 'u' ? 1 : 2;
	}

	/**
	 * Get all memberships of an account $account_id / groups the account is a member off
	 *
	 * @param int/string $account_id numeric account-id or alphanum. account-lid
	 * @param boolean $just_id=false return just account_id's or account_id => account_lid pairs
	 * @return array with account_id's ($just_id) or account_id => account_lid pairs (!$just_id)
	 */
	function memberships($account_id,$just_id=false)
	{
		$this->setup_cache();
		$memberships_list = &$this->cache['memberships_list'];

		if (!is_int($account_id) && !is_numeric($account_id))
		{
			$account_id = $this->name2id($account_id,'account_lid','u');
		}
		if (!isset($memberships_list[$account_id]))
		{
			$memberships_list[$account_id] = parent::memberships($account_id);
		}
		//echo "accounts::memberships($account_id)"; _debug_array($memberships_list[$account_id]);
		return $just_id && $memberships_list[$account_id] ? array_keys($memberships_list[$account_id]) : $memberships_list[$account_id];
	}

	/**
	 * Sets the memberships of a given account
	 *
	 * @param array $groups array with gidnumbers
	 * @param int $account_id uidnumber
	 */
	function set_memberships($groups,$account_id)
	{
		if (!is_int($account_id) && !is_numeric($account_id))
		{
			$account_id = $this->name2id($account_id);
		}
		parent::set_memberships($groups,$account_id);

		$this->cache_invalidate($account_id);
	}

	/**
	 * Get all members of the group $account_id
	 *
	 * @param int/string $accountid='' numeric account-id or alphanum. account-lid, 
	 *	default account of the user of this session
	 * @param boolean $just_id=false return just an array of id's and not id => lid pairs, default false
	 * @return array with account_id ($just_id) or account_id => account_lid pairs (!$just_id)
	 */
	function members($account_id,$just_id=false)
	{
		$this->setup_cache();
		$members_list = &$this->cache['members_list'];

		if (!is_int($account_id) && !is_numeric($account_id))
		{
			$account_id = $this->name2id($account_id);
		}
		if (!isset($members_list[$account_id]))
		{
			$members_list[$account_id] = parent::members($account_id);
		}
		//echo "accounts::members($account_id)"; _debug_array($members_list[$account_id]);
		return $just_id && $members_list[$account_id] ? array_keys($members_list[$account_id]) : $members_list[$account_id];
	}

	/**
	 * Set the members of a group
	 * 
	 * @param array $members array with uidnumber or uid's
	 * @param int $gid gidnumber of group to set
	 */
	function set_members($members,$gid)
	{
		//echo "<p>accounts::set_members(".print_r($members,true).",$gid)</p>\n";
		parent::set_members($members,$gid);

		$this->cache_invalidate(0);
	}

	/**
	 * splits users and groups from a array of id's or the accounts with run-rights for a given app-name
	 *
	 * @param array $app_users array of user-id's or app-name (if you use app-name the result gets cached!)
	 * @param string $use what should be returned only an array with id's of either 'accounts' or 'groups'.
	 *	Or an array with arrays for 'both' under the keys 'groups' and 'accounts' or 'merge' for accounts
	 *	and groups merged into one array
	 * @return array/boolean see $use, false on error (wront $use)
	 */
	function split_accounts($app_users,$use='both')
	{
		if (!is_array($app_users))
		{
			$this->setup_cache();
			$cache = &$this->cache['account_split'][$app_user];

			if (is_array($cache))
			{
				return $cache;
			}
			$app_users = $GLOBALS['egw']->acl->get_ids_for_location('run',1,$app_users);
		}
		$accounts = array(
			'accounts' => array(),
			'groups' => array(),
		);
		foreach($app_users as $id)
		{
			$type = $this->get_type($id);
			if($type == 'g')
			{
				$accounts['groups'][$id] = $id;
				foreach($this->members($id,true) as $id)
				{
					$accounts['accounts'][$id] = $id;
				}
			}
			else
			{
				$accounts['accounts'][$id] = $id;
			}
		}

		// not sure why they need to be sorted, but we need to remove the keys anyway
		sort($accounts['groups']);
		sort($accounts['accounts']);

		if (isset($cache))
		{
			$cache = $accounts;
		}
		//echo "<p>accounts::split_accounts(".print_r($app_users,True).",'$use') = <pre>".print_r($accounts,True)."</pre>\n";

		switch($use)
		{
			case 'both':
				return $accounts;
			case 'groups':
				return $accounts['groups'];
			case 'accounts':
				return $accounts['accounts'];
			case 'merge':
				return array_merge($accounts['accounts'],$accounts['groups']);
		}
		return False;
	}

	/**
	 * Invalidate the cache (or parts of it) after change in $account_id
	 *
	 * Atm simplest approach - delete it all ;-)
	 * 
	 * @param int $account_id for which account_id should the cache be invalid, default 0 = all
	 */
	function cache_invalidate($account_id=0)
	{
		//echo "<p>accounts::cache_invalidate($account_id)</p>\n";
		$GLOBALS['egw_info']['accounts']['cache'] = array();
		
		if (method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
		{
			$GLOBALS['egw']->invalidate_session_cache();	// invalidates whole egw-enviroment if stored in the session
		}
	}
	
	/**
	 * Add an account for an authenticated user
	 *
	 * Expiration date and primary group are read from the system configuration.
	 * 
	 * @param string $account_lid
	 * @param string $passwd
	 * @param array $GLOBALS['auto_create_acct'] values for 'firstname', 'lastname', 'email' and 'primary_group'
	 * @return int/boolean account_id or false on error
	 */
	function auto_add($account_lid, $passwd)
	{
		$expires = !isset($GLOBALS['egw_info']['server']['auto_create_expire']) ||
			$GLOBALS['egw_info']['server']['auto_create_expire'] == 'never' ? -1 :
			time() + $GLOBALS['egw_info']['server']['auto_create_expire'] + 2;

		
		if (!($default_group_id = $this->name2id($GLOBALS['egw_info']['server']['default_group_lid'])))
		{
			$default_group_id = $this->name2id('Default');
		}
		$primary_group = $GLOBALS['auto_create_acct']['primary_group'] &&
			$this->get_type((int)$GLOBALS['auto_create_acct']['primary_group']) === 'g' ?
			(int)$GLOBALS['auto_create_acct']['primary_group'] : $default_group_id;

		$data = array(
			'account_lid'           => $account_lid,
			'account_type'          => 'u',
			'account_passwd'        => $passwd,
			'account_firstname'     => $GLOBALS['auto_create_acct']['firstname'] ? $GLOBALS['auto_create_acct']['firstname'] : 'New',
			'account_lastname'      => $GLOBALS['auto_create_acct']['lastname'] ? $GLOBALS['auto_create_acct']['lastname'] : 'User',
			'account_email'         => $GLOBALS['auto_create_acct']['email'],
			'account_status'        => 'A',
			'account_expires'       => $expires,
			'account_primary_group' => $primary_group,
		);
		// use given account_id, if it's not already used
		if (isset($GLOBALS['auto_create_acct']['account_id']) && 
			is_numeric($GLOBALS['auto_create_acct']['account_id']) && 
			!$this->id2name($GLOBALS['auto_create_acct']['account_id']))
		{
			$data['account_id'] = $GLOBALS['auto_create_acct']['account_id'];
		}
		if (!($data['account_id'] = $this->save($data)))
		{
			return false;
		}
		// call hook to notify interested apps about the new account
		$GLOBALS['hook_values'] = $data;
		$GLOBALS['egw']->hooks->process($data+array(
			'location' => 'addaccount',
			// at login-time only the hooks from the following apps will be called
			'order' => array('felamimail','fudforum'),
		),False,True);  // called for every app now, not only enabled ones

		return $data['account_id'];
	}

	function list_methods($_type='xmlrpc')
	{
		if (is_array($_type))
		{
			$_type = $_type['type'] ? $_type['type'] : $_type[0];
		}

		switch($_type)
		{
			case 'xmlrpc':
				$xml_functions = array(
					'get_list' => array(
						'function'  => 'get_list',
						'signature' => array(array(xmlrpcStruct)),
						'docstring' => lang('Returns a full list of accounts on the system.  Warning: This is return can be quite large')
					),
					'list_methods' => array(
						'function'  => 'list_methods',
						'signature' => array(array(xmlrpcStruct,xmlrpcString)),
						'docstring' => lang('Read this list of methods.')
					)
				);
				return $xml_functions;
				break;
			case 'soap':
				return $this->soap_functions;
				break;
			default:
				return array();
				break;
		}
	}

	/**
	 * Internal functions not meant to use outside this class!!!
	 */
	
	/**
	 * Sets up the account-data cache
	 *
	 * The cache is shared between all instances of the account-class and it can be save in the session,
	 * if use_session_cache is set to True
	 * 
	 * @internal 
	 */
	function setup_cache()
	{
		if ($this->use_session_cache &&		// are we supposed to use a session-cache
			!@$GLOBALS['egw_info']['accounts']['session_cache_setup'] &&	// is it already setup
			// is the account-class ready (startup !)
			is_object($GLOBALS['egw']->session) && $GLOBALS['egw']->session->account_id)
		{
			// setting up the session-cache
			$GLOBALS['egw_info']['accounts']['cache'] = $GLOBALS['egw']->session->appsession('accounts_cache','phpgwapi');
			$GLOBALS['egw_info']['accounts']['session_cache_setup'] = True;
			//echo "accounts::setup_cache() cache=<pre>".print_r($GLOBALS['egw_info']['accounts']['cache'],True)."</pre>\n";
		}
		if (!isset($this->cache))
		{
			$this->cache = &$GLOBALS['egw_info']['accounts']['cache'];
		}
		if (!is_array($this->cache)) $this->cache = array();
	}

	/**
	 * Saves the account-data cache in the session
	 *
	 * Gets called from common::egw_final()
	 * 
	 * @internal 
	 */
	function save_session_cache()
	{
		if ($this->use_session_cache &&		// are we supposed to use a session-cache
			$GLOBALS['egw_info']['accounts']['session_cache_setup'] &&	// is it already setup
			// is the account-class ready (startup !)
			is_object($GLOBALS['egw']->session))
		{
			$GLOBALS['egw']->session->appsession('accounts_cache','phpgwapi',$GLOBALS['egw_info']['accounts']['cache']);
		}
	}

	/**
	 * Depricated functions of the old accounts class. 
	 *
	 * Do NOT use them in new code, they will be removed after the next major release!!!
	 */

	/**
	 * Reads the data of the account this class is instanciated for
	 *
	 * @deprecated use read of $GLOBALS['egw']->accounts and not own instances of the accounts class
	 * @return array with the internal data
	 */
	function read_repository()
	{
		return $this->data = $this->account_id ? $this->read($this->account_id,true) : array();
	}

	/**
	 * saves the account-data in the internal data-structure of this class to the repository
	 * 
	 * @deprecated use save of $GLOBALS['egw']->accounts and not own instances of the accounts class
	 */
	function save_repository()
	{
		$this->save($this->data,true);
	}

	/**
	 * Searches / lists accounts: users and/or groups
	 *
	 * @deprecated use search
	 */
	function get_list($_type='both',$start = '',$sort = '', $order = '', $query = '', $offset = '',$query_type='')
	{
		//echo "<p>accounts::get_list(".print_r($_type,True).",start='$start',sort='$sort',order='$order',query='$query',offset='$offset')</p>\n";
		$this->setup_cache();
		$account_list = &$this->cache['account_list'];

		// For XML-RPC
		if (is_array($_type))
		{
			$p      = $_type;
			$_type  = $p['type'];
			$start  = $p['start'];
			$order  = $p['order'];
			$query  = $p['query'];
			$offset = $p['offset'];
			$query_type = $p['query_type'];
		}
		else
		{
			$p = array(
				'type' => $_type,
				'start' => $start,
				'order' => $order,
				'query' => $query,
				'offset' => $offset,
				'query_type' => $query_type ,
			);
		}
		$serial = serialize($p);

		if (isset($account_list[$serial]))
		{
			$this->total = $account_list[$serial]['total'];
		}
		else
		{
			$account_list[$serial]['data'] = parent::get_list($_type,$start,$sort,$order,$query,$offset,$query_type);
			$account_list[$serial]['total'] = $this->total;
		}
		return $account_list[$serial]['data'];
	}

	/**
	 * Create a new account with the given $account_info
	 * 
	 * @deprecated use save
	 * @param array $data account data for the new account
	 * @param booelan $default_prefs has no meaning any more, as we use "real" default prefs since 1.0
	 * @return int new nummeric account-id
	 */
	function create($account_info,$default_prefs=True)
	{
		return $this->save($account_info);
	}

	/**
	 * copies the given $data into the internal array $this->data
	 *
	 * @deprecated store data in your own code and use save to save it
	 * @param array $data array with account data
	 * @return array $this->data = $data
	 */
	function update_data($data)
	{
		return $this->data = $data;
	}

	/**
	 * Get all memberships of an account $accountid / groups the account is a member off
	 *
	 * @deprecated use memberships() which account_id => account_lid pairs
	 * @param int/string $accountid='' numeric account-id or alphanum. account-lid, 
	 *	default account of the user of this session
	 * @return array or arrays with keys 'account_id' and 'account_name' for the groups $accountid is a member of
	 */
	function membership($accountid = '')
	{
		$accountid = get_account_id($accountid);

		if (!($memberships = $this->memberships($accountid)))
		{
			return $memberships;
		}
		$old = array();
		foreach($memberships as $id => $lid)
		{
			$old[] = array('account_id' => $id, 'account_name' => $lid);
		}
		//echo "<p>accounts::membership($accountid)="; _debug_array($old);
		return $old;
	}
	
	/**
	 * Get all members of the group $accountid
	 *
	 * @deprecated use members which returns acount_id => account_lid pairs
	 * @param int/string $accountid='' numeric account-id or alphanum. account-lid, 
	 *	default account of the user of this session
	 * @return array of arrays with keys 'account_id' and 'account_name'
	 */
	function member($accountid)
	{
		if (!($members = $this->members($accountid)))
		{
			return $members;
		}
		$old = array();
		foreach($members as $uid => $lid)
		{
			$old[] = array('account_id' => $uid, 'account_name' => $lid);
		}
		return $old;
	}

	/**
	 * phpGW compatibility function, better use split_accounts
	 *
	 * @deprecated  use split_accounts
	 */
	function return_members($accounts)
	{
		$arr = $this->split_accounts($accounts);

		return array(
			'users'  => $arr['accounts'],
			'groups' => $arr['groups'],
		);
	}


	/**
	 * Gets account-name (lid), firstname and lastname of an account $accountid
	 *
	 * @deprecated use read to read account data
	 * @param int/string $accountid='' numeric account-id or alphanum. account-lid, 
	 *	if !$accountid account of the user of this session
	 * @param string &$lid on return: alphanumeric account-name (lid)
	 * @param string &$fname on return: first name
	 * @param string &$lname on return: last name
	 * @return boolean true if $accountid was found, false otherwise
	 */	 
	function get_account_name($accountid,&$lid,&$fname,&$lname)
	{
		if (!($data = $this->read($accountid))) return false;
		
		$lid   = $data['account_lid'];
		$fname = $data['account_firstname'];
		$lname = $data['account_lastname'];
		
		if (empty($fname)) $fname = $lid;
		if (empty($lname)) $lname = $this->get_type($accountid) == 'g' ? lang('Group') : lang('user');

		return true;
	}

	/**
	 * Reads account-data for a given $account_id from the repository AND sets the class-vars with it
	 *
	 * Same effect as instanciating the class with that account, dont do it with $GLOBALS['egw']->account !!!
	 *
	 * @deprecated use read to read account data and store it in your own code
	 * @param int $accountid numeric account-id 
	 * @return array with keys lid, firstname, lastname, fullname, type
	 */
	function get_account_data($account_id)
	{
		$this->account_id = $account_id;
		$this->read_repository();

		$data[$this->data['account_id']]['lid']       = $this->data['account_lid'];
		$data[$this->data['account_id']]['firstname'] = $this->data['firstname'];
		$data[$this->data['account_id']]['lastname']  = $this->data['lastname'];
		$data[$this->data['account_id']]['fullname']  = $this->data['fullname'];
		$data[$this->data['account_id']]['type']      = $this->data['account_type'];

		return $data;
	}
}

/**
 * Enable this only, if your system users are automatically eGroupWare users.
 * This is NOT the case for most installations and silently rejecting all this names causes a lot of trouble.

$GLOBALS['egw_info']['server']['global_denied_users'] = array(
	'root'     => True, 'bin'      => True, 'daemon'   => True,
	'adm'      => True, 'lp'       => True, 'sync'     => True,
	'shutdown' => True, 'halt'     => True, 'ldap'     => True,
	'mail'     => True, 'news'     => True, 'uucp'     => True,
	'operator' => True, 'games'    => True, 'gopher'   => True,
	'nobody'   => True, 'xfs'      => True, 'pgsql'    => True,
	'mysql'    => True, 'postgres' => True, 'oracle'   => True,
	'ftp'      => True, 'gdm'      => True, 'named'    => True,
	'alias'    => True, 'web'      => True, 'sweep'    => True,
	'cvs'      => True, 'qmaild'   => True, 'qmaill'   => True,
	'qmaillog' => True, 'qmailp'   => True, 'qmailq'   => True,
	'qmailr'   => True, 'qmails'   => True, 'rpc'      => True,
	'rpcuser'  => True, 'amanda'   => True, 'apache'   => True,
	'pvm'      => True, 'squid'    => True, 'ident'    => True,
	'nscd'     => True, 'mailnull' => True, 'cyrus'    => True,
	'backup'    => True
);

$GLOBALS['egw_info']['server']['global_denied_groups'] = array(
	'root'      => True, 'bin'       => True, 'daemon'    => True,
	'sys'       => True, 'adm'       => True, 'tty'       => True,
	'disk'      => True, 'lp'        => True, 'mem'       => True,
	'kmem'      => True, 'wheel'     => True, 'mail'      => True,
	'uucp'      => True, 'man'       => True, 'games'     => True,
	'dip'       => True, 'ftp'       => True, 'nobody'    => True,
	'floppy'    => True, 'xfs'       => True, 'console'   => True,
	'utmp'      => True, 'pppusers'  => True, 'popusers'  => True,
	'slipusers' => True, 'slocate'   => True, 'mysql'     => True,
	'dnstools'  => True, 'web'       => True, 'named'     => True,
	'dba'       => True, 'oinstall'  => True, 'oracle'    => True,
	'gdm'       => True, 'sweep'     => True, 'cvs'       => True,
	'postgres'  => True, 'qmail'     => True, 'nofiles'   => True,
	'ldap'      => True, 'backup'    => True
);
*/