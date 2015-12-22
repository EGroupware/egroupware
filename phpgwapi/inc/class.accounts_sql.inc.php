<?php
/**
 * API - accounts SQL backend
 *
 * The SQL backend stores the group memberships via the ACL class (location 'phpgw_group')
 *
 * The (positive) account_id's of groups are mapped in this class to negative numeric
 * account_id's, to conform with the way we handle groups in LDAP!
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006 and
 * 	earlier to use the new DB functions
 *
 * This class replaces the former accounts_sql class written by
 * Joseph Engo <jengo@phpgroupware.org>, Dan Kuykendall <seek3r@phpgroupware.org>
 * and Bettina Gille <ceb@phpgroupware.org>.
 * Copyright (C) 2000 - 2002 Joseph Engo
 * Copyright (C) 2003 Lars Kneschke, Bettina Gille
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @version $Id$
 */

/**
 * SQL Backend for accounts
 *
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @access internal only use the interface provided by the accounts class
 */
class accounts_sql
{
	/**
	 * instance of the db class
	 *
	 * @var egw_db
	 */
	var $db;
	/**
	 * table name for the accounts
	 *
	 * @var string
	 */
	const TABLE = 'egw_accounts';
	var $table = self::TABLE;
	/**
	 * table name for the contacts
	 *
	 * @var string
	 */
	var $contacts_table = 'egw_addressbook';
	/**
	 * Join with the accounts-table used in contacts::search
	 *
	 * @var string
	 */
	var $contacts_join = ' RIGHT JOIN egw_accounts ON egw_accounts.account_id=egw_addressbook.account_id';
	/**
	 * total number of found entries from get_list method
	 *
	 * @var int
	 */
	var $total;

	/**
	 * Reference to our frontend
	 *
	 * @var accounts
	 */
	private $frontend;

	/**
	 * does backend allow to change account_lid
	 */
	const CHANGE_ACCOUNT_LID = true;

	/**
	 * does backend requires password to be set, before allowing to enable an account
	 */
	const REQUIRE_PASSWORD_FOR_ENABLE = false;

	/**
	 * Constructor
	 *
	 * @param accounts $frontend reference to the frontend class, to be able to call it's methods if needed
	 * @return accounts_sql
	 */
	function __construct(accounts $frontend)
	{
		$this->frontend = $frontend;

		if (is_object($GLOBALS['egw_setup']->db))
		{
			$this->db = $GLOBALS['egw_setup']->db;
		}
		else
		{
			$this->db = $GLOBALS['egw']->db;
		}
	}

	/**
	 * Reads the data of one account
	 *
	 * For performance reasons and because the contacts-object itself depends on the accounts-object,
	 * we directly join with the contacts table for reading!
	 *
	 * @param int $account_id numeric account-id
	 * @return array/boolean array with account data (keys: account_id, account_lid, ...) or false if account not found
	 */
	function read($account_id)
	{
		if (!(int)$account_id) return false;

		if ($account_id > 0)
		{
			$extra_cols = $this->contacts_table.'.n_given AS account_firstname,'.
				$this->contacts_table.'.n_family AS account_lastname,'.
				$this->contacts_table.'.contact_email AS account_email,'.
				$this->contacts_table.'.n_fn AS account_fullname,'.
				$this->contacts_table.'.contact_id AS person_id,'.
				$this->contacts_table.'.contact_created AS account_created,'.
				$this->contacts_table.'.contact_modified AS account_modified,'.
				$this->contacts_table.'.tel_work AS account_phone,';
				$join = 'LEFT JOIN '.$this->contacts_table.' ON '.$this->table.'.account_id='.$this->contacts_table.'.account_id';
		}
		// during setup emailadmin might not yet be installed and running below query
		// will abort transaction in PostgreSQL
		elseif (!isset($GLOBALS['egw_setup']) || in_array(emailadmin_smtp_sql::TABLE, $this->db->table_names(true)))
		{
			$extra_cols = emailadmin_smtp_sql::TABLE.'.mail_value AS account_email,';
			$join = 'LEFT JOIN '.emailadmin_smtp_sql::TABLE.' ON '.$this->table.'.account_id=-'.emailadmin_smtp_sql::TABLE.'.account_id AND mail_type='.emailadmin_smtp_sql::TYPE_ALIAS;
		}
		try {
			$rs = $this->db->select($this->table, $extra_cols.$this->table.'.*',
				$this->table.'.account_id='.abs($account_id),
				__LINE__, __FILE__, false, '', false, 0, $join);
		}
		catch (egw_exception_db $e) {
			unset($e);
		}

		if (!$rs)	// handle not (yet) existing mailaccounts table
		{
			$rs = $this->db->select($this->table, $this->table.'.*',
				$this->table.'.account_id='.abs($account_id), __LINE__, __FILE__);
		}
		if (!$rs || !($data = $rs->fetch()))
		{
			return false;
		}
		if ($data['account_type'] == 'g')
		{
			$data['account_id'] = -$data['account_id'];
			$data['mailAllowed'] = true;
		}
		if (!$data['account_firstname']) $data['account_firstname'] = $data['account_lid'];
		if (!$data['account_lastname'])
		{
			$data['account_lastname'] = $data['account_type'] == 'g' ? 'Group' : 'User';
			// if we call lang() before the translation-class is correctly setup,
			// we can't switch away from english language anymore!
			if (translation::$lang_arr)
			{
				$data['account_lastname'] = lang($data['account_lastname']);
			}
		}
		if (!$data['account_fullname']) $data['account_fullname'] = $data['account_firstname'].' '.$data['account_lastname'];

		//echo "accounts_sql::read($account_id)"; _debug_array($data);
		return $data;
	}

	/**
	 * Saves / adds the data of one account
	 *
	 * If no account_id is set in data the account is added and the new id is set in $data.
	 *
	 * @param array $data array with account-data
	 * @return int/boolean the account_id or false on error
	 */
	function save(&$data)
	{
		//echo "<p>accounts_sql::save(".print_r($data,true).")</p>\n";
		$to_write = $data;
		unset($to_write['account_passwd']);
		// encrypt password if given or unset it if not
		if ($data['account_passwd'])
		{
			// if password it's not already entcrypted, do so now
			if (!preg_match('/^\\{[a-z5]{3,5}\\}.+/i',$data['account_passwd']) &&
				!preg_match('/^[0-9a-f]{32}$/',$data['account_passwd']))	// md5 hash
			{
				$data['account_passwd'] = $GLOBALS['egw']->auth->encrypt_sql($data['account_passwd']);
			}
			$to_write['account_pwd'] = $data['account_passwd'];
			$to_write['account_lastpwd_change'] = time();
		}
		if ($data['mustchangepassword'] == 1) $to_write['account_lastpwd_change']=0;
		if (!(int)$data['account_id'] || !$this->id2name($data['account_id']))
		{
			if ($to_write['account_id'] < 0) $to_write['account_id'] *= -1;

			if (!isset($to_write['account_pwd'])) $to_write['account_pwd'] = '';	// is NOT NULL!
			if (!isset($to_write['account_status'])) $to_write['account_status'] = '';	// is NOT NULL!

			// postgres requires the auto-id field to be unset!
			if (isset($to_write['account_id']) && !$to_write['account_id']) unset($to_write['account_id']);

			if (!in_array($to_write['account_type'],array('u','g')) ||
				!$this->db->insert($this->table,$to_write,false,__LINE__,__FILE__)) return false;

			if (!(int)$data['account_id'])
			{
				$data['account_id'] = $this->db->get_last_insert_id($this->table,'account_id');
				if ($data['account_type'] == 'g') $data['account_id'] *= -1;
			}
		}
		else	// update of existing account
		{
			unset($to_write['account_id']);
			if (!$this->db->update($this->table,$to_write,array('account_id' => abs($data['account_id'])),__LINE__,__FILE__))
			{
				return false;
			}
		}
		// store group-email in mailaccounts table
		if ($data['account_id'] < 0 && class_exists('emailadmin_smtp_sql', isset($data['account_email'])))
		{
			try {
				if (isset($GLOBALS['egw_info']['apps']) && !isset($GLOBALS['egw_info']['apps']['emailadmin']) ||
					isset($GLOBALS['egw_setup']) && !in_array(emailadmin_smtp_sql::TABLE, $this->db->table_names(true)))
				{
					// cant store email, if emailadmin not (yet) installed
				}
				elseif (empty($data['account_email']))
				{
					$this->db->delete(emailadmin_smtp_sql::TABLE, array(
						'account_id' => $data['account_id'],
						'mail_type' => emailadmin_smtp_sql::TYPE_ALIAS,
					), __LINE__, __FILE__, emailadmin_smtp_sql::APP);
				}
				else
				{
					$this->db->insert(emailadmin_smtp_sql::TABLE, array(
						'mail_value' => $data['account_email'],
					), array(
						'account_id' => $data['account_id'],
						'mail_type' => emailadmin_smtp_sql::TYPE_ALIAS,
					), __LINE__, __FILE__, emailadmin_smtp_sql::APP);
				}
			}
			// ignore not (yet) existing mailaccounts table (does NOT work in PostgreSQL, because of transaction!)
			catch (egw_exception_db $e) {
				unset($e);
			}
		}
		return $data['account_id'];
	}

	/**
	 * Delete one account, deletes also all acl-entries for that account
	 *
	 * @param int $account_id numeric account_id
	 * @return boolean true on success, false otherwise
	 */
	function delete($account_id)
	{
		if (!(int)$account_id) return false;

		$contact_id = $this->id2name($account_id,'person_id');

		if (!$this->db->delete($this->table,array('account_id' => abs($account_id)),__LINE__,__FILE__))
		{
			return false;
		}
		if ($contact_id)
		{
			$GLOBALS['egw']->contacts->delete($contact_id,false);	// false = allow to delete accounts (!)
		}
		return true;
	}

	/**
	 * Get all memberships of an account $accountid / groups the account is a member off
	 *
	 * @param int $account_id numeric account-id
	 * @return array/boolean array with account_id => account_lid pairs or false if account not found
	 */
	function memberships($account_id)
	{
		if (!(int)$account_id) return false;

		$memberships = array();
		if(($gids = $GLOBALS['egw']->acl->get_location_list_for_id('phpgw_group', 1, $account_id)))
		{
			foreach($gids as $gid)
			{
				$memberships[(string) $gid] = $this->id2name($gid);
			}
		}
		//echo "accounts::memberships($account_id)"; _debug_array($memberships);
		return $memberships;
	}

	/**
	 * Sets the memberships of the account this class is instanciated for
	 *
	 * @param array $groups array with gidnumbers
	 * @param int $account_id numerical account-id
	 */
	function set_memberships($groups,$account_id)
	{
		if (!(int)$account_id) return;

		$acl =& CreateObject('phpgwapi.acl',$account_id);
		$acl->read_repository();
		$acl->delete('phpgw_group',false);

		foreach($groups as $group)
		{
			$acl->add('phpgw_group',$group,1);
		}
		$acl->save_repository();
	}

	/**
	 * Get all members of the group $accountid
	 *
	 * @param int/string $account_id numeric account-id
	 * @return array with account_id => account_lid pairs
	 */
	function members($account_id)
	{
		if (!is_numeric($account_id)) $account_id = $this->name2id($account_id);

		$members = array();
		foreach($this->db->select($this->table, 'account_id,account_lid',
			$this->db->expression(acl::TABLE, array(
				'acl_appname'  => 'phpgw_group',
				'acl_location' => $account_id,
			)), __LINE__, __FILE__, false, '', false, 0,
			'JOIN '.acl::TABLE.' ON account_id=acl_account'
		) as $row)
		{
			$members[$row['account_id']] = $row['account_lid'];
		}
		//echo "accounts::members($accountid)"; _debug_array($members);
		return $members;
	}

	/**
	 * Set the members of a group
	 *
	 * @param array $members array with uidnumber or uid's
	 * @param int $gid gidnumber of group to set
	 */
	function set_members($members,$gid)
	{
		//echo "<p align=right>accounts::set_members(".print_r($members,true).",$gid)</p>\n";
		$GLOBALS['egw']->acl->delete_repository('phpgw_group',$gid,false);

		if (is_array($members))
		{
			foreach($members as $id)
			{
				$GLOBALS['egw']->acl->add_repository('phpgw_group',$gid,$id,1);
			}
		}
	}

	/**
	 * Searches / lists accounts: users and/or groups
	 *
	 * @param array with the following keys:
	 * @param $param['type'] string/int 'accounts', 'groups', 'owngroups' (groups the user is a member of), 'both',
	 * 'groupmember' or 'groupmembers+memberships'
	 *	or integer group-id for a list of members of that group
	 * @param $param['start'] int first account to return (returns offset or max_matches entries) or all if not set
	 * @param $param['order'] string column to sort after, default account_lid if unset
	 * @param $param['sort'] string 'ASC' or 'DESC', default 'ASC' if not set
	 * @param $param['query'] string to search for, no search if unset or empty
	 * @param $param['query_type'] string:
	 *	'all'   - query all fields for containing $param[query]
	 *	'start' - query all fields starting with $param[query]
	 *	'exact' - query all fields for exact $param[query]
	 *	'lid','firstname','lastname','email' - query only the given field for containing $param[query]
	 * @param $param['offset'] int - number of matches to return if start given, default use the value in the prefs
	 * @param $param['objectclass'] boolean return objectclass(es) under key 'objectclass' in each account
	 * @return array with account_id => data pairs, data is an array with account_id, account_lid, account_firstname,
	 *	account_lastname, person_id (id of the linked addressbook entry), account_status, account_expires, account_primary_group
	 */
	function search($param)
	{
		static $order2contact = array(
			'account_firstname' => 'n_given',
			'account_lastname'  => 'n_family',
			'account_email'     => 'contact_email',
		);

		// fetch order of account_fullname from common::display_fullname
		if (strpos($param['order'],'account_fullname') !== false)
		{
			$param['order'] = str_replace('account_fullname', preg_replace('/[ ,]+/',',',str_replace(array('[',']'),'',
				common::display_fullname('account_lid','account_firstname','account_lastname'))), $param['order']);
		}
		$order = str_replace(array_keys($order2contact),array_values($order2contact),$param['order']);
		// allways add 'account_lid', as it is only valid one for groups
		if (strpos($order, 'account_lid') === false)
		{
			$order .= ($order?',':'').'account_lid';
		}
		if ($param['sort']) $order = implode(' '.$param['sort'].',', explode(',', $order)).' '.$param['sort'];

		$search_cols = array('account_lid','n_family','n_given','email');
		$join = $this->contacts_join;
		$email_cols = array('email');

		// Add in group email searching
		if (!isset($GLOBALS['egw_setup']) || in_array(emailadmin_smtp_sql::TABLE, $this->db->table_names(true)))
		{
			$email_cols = array('coalesce('.$this->contacts_table.'.contact_email,'.emailadmin_smtp_sql::TABLE.'.mail_value) as email');
			if ($this->db->Type == 'mysql' && !preg_match('/[\x80-\xFF]/', $param['query']))
			{
				$search_cols[] = emailadmin_smtp_sql::TABLE.'.mail_value';
			}
			$join .= ' LEFT JOIN '.emailadmin_smtp_sql::TABLE.' ON '.$this->table.'.account_id=-'.emailadmin_smtp_sql::TABLE.'.account_id AND mail_type='.emailadmin_smtp_sql::TYPE_ALIAS;
		}

		$filter = array();
		switch($param['type'])
		{
			case 'accounts':
				$filter['owner'] = 0;
				break;
			case 'groups':
				$filter[] = "account_type='g'";
				break;
			case 'owngroups':
				$filter['account_id'] = array_map('abs', $this->frontend->memberships($GLOBALS['egw_info']['user']['account_id'], true));
				$filter[] = "account_type='g'";
				break;
			case 'groupmembers':
			case 'groupmembers+memberships':
				$members = array();
				foreach((array)$this->memberships($GLOBALS['egw_info']['user']['account_id'], true) as $grp)
				{
					$members = array_unique(array_merge($members, (array)$this->members($grp,true)));
					if ($param['type'] == 'groupmembers+memberships') $members[] = abs($grp);
				}
				$filter['account_id'] = $members;
				break;
			default:
				if (is_numeric($param['type']))
				{
					$filter['account_id'] = $this->frontend->members($param['type'], true, $param['active']);
					$filter['owner'] = 0;
					break;
				}
				// fall-through
			case 'both':
				$filter[] = "(egw_addressbook.contact_owner=0 OR egw_addressbook.contact_owner IS NULL)";
				break;
		}
		// fix ambigous account_id (used in accounts and contacts table)
		if (array_key_exists('account_id', $filter))
		{
			if (!$filter['account_id'])	// eg. group without members (would give SQL error)
			{
				$this->total = 0;
				return array();
			}
			$filter[] = $this->db->expression($this->table, $this->table.'.', array(
				'account_id' => $filter['account_id'],
			));
			unset($filter['account_id']);
		}
		if ($param['active'])
		{
			$filter[] = str_replace('UNIX_TIMESTAMP(NOW())',time(),addressbook_sql::ACOUNT_ACTIVE_FILTER);
		}
		$criteria = array();
		$wildcard = $param['query_type'] == 'start' || $param['query_type'] == 'exact' ? '' : '%';
		if (($query = $param['query']))
		{
			switch($param['query_type'])
			{
				case 'start':
					$query .= '*';
					// fall-through
				case 'all':
				default:
				case 'exact':
					foreach($search_cols as $col)
					{
						$criteria[$col] = $query;
					}
					break;
				case 'account_firstname':
				case 'firstname':
					$criteria['n_given'] = $query;
					break;
				case 'account_lastname':
				case 'lastname':
					$criteria['n_family'] = $query;
					break;
				case 'account_lid':
				case 'lid':
					$criteria['account_lid'] = $query;
					break;
				case 'account_email':
				case 'email':
					$criteria['email'] = $query;
					// Group email
					if(in_array(emailadmin_smtp_sql::TABLE, $this->db->table_names(true)))
					{
						$criteria[emailadmin_smtp_sql::TABLE.'.mail_value'] = $query;
					}
					break;
			}
		}
		if (!is_object($GLOBALS['egw']->contacts)) throw new exception('No $GLOBALS[egw]->contacts!');

		$accounts = array();
		foreach((array) $GLOBALS['egw']->contacts->search($criteria,
			array_merge(array(1,'n_given','n_family','id','created','modified',$this->table.'.account_id AS account_id'),$email_cols),
			$order,"account_lid,account_type,account_status,account_expires,account_primary_group,account_description",
			$wildcard,false,$query[0] == '!' ? 'AND' : 'OR',
			$param['offset'] ? array($param['start'], $param['offset']) : (is_null($param['start']) ? false : $param['start']),
			$filter,$join) as $contact)
		{
			if ($contact)
			{
				$account_id = ($contact['account_type'] == 'g' ? -1 : 1) * $contact['account_id'];
				$accounts[$account_id] = array(
					'account_id'        => $account_id,
					'account_lid'       => $contact['account_lid'],
					'account_type'      => $contact['account_type'],
					'account_firstname' => $contact['n_given'],
					'account_lastname'  => $contact['n_family'],
					'account_email'     => $contact['email'],
					'person_id'         => $contact['id'],
					'account_status'	=> $contact['account_status'],
					'account_expires'	=> $contact['account_expires'],
					'account_primary_group'	=> $contact['account_primary_group'],
					// addressbook_bo::search() returns everything in user-time, need to convert to server-time
					'account_created'	=> egw_time::user2server($contact['created']),
					'account_modified'	=> egw_time::user2server($contact['modified']),
					'account_description' => $contact['account_description'],
				);
			}
		}
		$this->total = $GLOBALS['egw']->contacts->total;
		//error_log(__METHOD__."(".array2string($param).") returning ".count($accounts).'/'.$this->total);
		return $accounts;
	}

	/**
	 * convert an alphanumeric account-value (account_lid, account_email, account_fullname) to the account_id
	 *
	 * Please note:
	 * - if a group and an user have the same account_lid the group will be returned (LDAP only)
	 * - if multiple user have the same email address, the returned user is undefined
	 *
	 * @param string $name value to convert
	 * @param string $which ='account_lid' type of $name: account_lid (default), account_email, person_id, account_fullname
	 * @param string $account_type u = user, g = group, default null = try both
	 * @return int/false numeric account_id or false on error ($name not found)
	 */
	function name2id($name,$which='account_lid',$account_type=null)
	{
		if ($account_type === 'g' && $which != 'account_lid') return false;

		$where = array();
		$cols = 'account_id';
		switch($which)
		{
			case 'account_fullname':
				$table = $this->contacts_table;
				$where['n_fn'] = $name;
				break;
			case 'account_email':
				$table = $this->contacts_table;
				$where['contact_email'] = $name;
				break;
			case 'person_id':
				$table = $this->contacts_table;
				$where['contact_id'] = $name;
				break;
			default:
				$table = $this->table;
				$cols .= ',account_type';
				$where[$which] = $name;
				// check if we need to treat username case-insensitive
				if ($which == 'account_lid' && !$GLOBALS['egw_info']['server']['case_sensitive_username'])	// = is case sensitiv eg. on postgres, but not on mysql!
				{
					$where[] = 'account_lid '.$this->db->capabilities[egw_db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$this->db->quote($where['account_lid']);
					unset($where['account_lid']);
				}
		}
		if ($account_type)
		{
			$where['account_type'] = $account_type;
		}
		else
		{
			$where[] = 'account_id IS NOT NULL'.	// otherwise contacts with eg. the same email hide the accounts!
				($table == $this->contacts_table ? " AND contact_tid != 'D'" : '');	// ignore deleted accounts contact-data

		}
		if (!($rs = $this->db->select($table,$cols,$where,__LINE__,__FILE__)) || !($row = $rs->fetch()))
		{
			//error_log(__METHOD__."('$name', '$which', ".array2string($account_type).") db->select('$table', '$cols', ".array2string($where).") returned ".array2string($rs).' '.function_backtrace());
			return false;
		}
		return ($row['account_type'] == 'g' ? -1 : 1) * $row['account_id'];
	}

	/**
	 * Convert an numeric account_id to any other value of that account (account_lid, account_email, ...)
	 *
	 * Uses the read method to fetch all data.
	 *
	 * @param int $account_id numerica account_id
	 * @param string $which ='account_lid' type to convert to: account_lid (default), account_email, ...
	 * @return string/false converted value or false on error ($account_id not found)
	 */
	function id2name($account_id,$which='account_lid')
	{
		return $this->frontend->id2name($account_id,$which);
	}

	/**
	 * Update the last login timestamps and the IP
	 *
	 * @param int $account_id
	 * @param string $ip
	 * @return int lastlogin time
	 */
	function update_lastlogin($account_id, $ip)
	{
		$previous_login = $this->db->select($this->table,'account_lastlogin',array('account_id'=>abs($account_id)),__LINE__,__FILE__)->fetchColumn();

		$this->db->update($this->table,array(
			'account_lastloginfrom' => $ip,
			'account_lastlogin'     => time(),
		),array(
			'account_id' => abs($account_id),
		),__LINE__,__FILE__);

		return $previous_login;
	}
}
