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
	var $table = 'egw_accounts';
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

		$join = $extra_cols = '';
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
		if (!($data = $this->db->select($this->table,$extra_cols.$this->table.'.*',$this->table.'.account_id='.abs($account_id),
			__LINE__,__FILE__,false,'',false,0,$join)->fetch()))
		{
			return false;
		}
		if ($data['account_type'] == 'g')
		{
			$data['account_id'] = -$data['account_id'];
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
		return $data['account_id'];
	}

	/**
	 * Delete one account, deletes also all acl-entries for that account
	 *
	 * @param int $id numeric account_id
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
		if (!is_numeric($account_id)) $account_id = $this-name2id($account_id);

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
	 * Searches users and/or groups
	 *
	 * ToDo: implement a search like accounts::search
	 *
	 * @param string $_type='both', 'accounts', 'groups'
	 * @param int $start=null
	 * @param string $sort='' ASC or DESC
	 * @param string $order=''
	 * @param string $query=''
	 * @param int $offset=null
	 * @param string $query_type='all' 'start', 'all' (default), 'exact'
	 * @return array
	 */
	function get_list($_type='both', $start = null,$sort = '', $order = '', $query = '', $offset = null, $query_type='')
	{
		//echo "<p>accounts_sql($_type,$start,$sort,$order,$query,$offset,$query_type)</p>\n";
		static $order2contact = array(
			'account_firstname' => 'n_given',
			'account_lastname'  => 'n_family',
			'account_email'     => 'contact_email',
		);

		// fetch order of account_fullname from common::display_fullname
		if (strpos($order,'account_fullname') !== false)
		{
			$order = str_replace('account_fullname',preg_replace('/[ ,]+/',',',str_replace(array('[',']'),'',
				common::display_fullname('account_lid','account_firstname','account_lastname'))),$order);
		}
		$order = str_replace(array_keys($order2contact),array_values($order2contact),$order);
		if ($sort) $order .= ' '.$sort;

		switch($_type)
		{
			case 'accounts':
				$filter = array('owner' => 0);
				break;
			case 'groups':
				$filter = "account_type = 'g'";
				break;
			default:
			case 'both':
				$filter = "(egw_addressbook.contact_owner=0 OR egw_addressbook.contact_owner IS NULL)";
				break;
		}
		$criteria = array();
		$wildcard = $query_type == 'start' || $query_type == 'exact' ? '' : '%';
		if ($query)
		{
			switch($query_type)
			{
				case 'start':
					$query .= '*';
					// fall-through
				case 'all':
				default:
				case 'exact':
					foreach(array('account_lid','n_family','n_given','email') as $col)
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
					break;
			}
		}
		if (!is_object($GLOBALS['egw']->contacts)) throw new exception('No $GLOBALS[egw]->contacts!');

		$accounts = array();
		foreach((array) $GLOBALS['egw']->contacts->search($criteria,"1,n_given,n_family,email,id,created,modified,$this->table.account_id AS account_id",
			$order,"account_lid,account_type,account_status",
			$wildcard,false,$query[0] == '!' ? 'AND' : 'OR',$offset ? array($start,$offset) : is_null($start) ? false : $start,
			$filter,$this->contacts_join) as $contact)
		{
			if ($contact)
			{
				$accounts[] = array(
					'account_id'        => ($contact['account_type'] == 'g' ? -1 : 1) * $contact['account_id'],
					'account_lid'       => $contact['account_lid'],
					'account_type'      => $contact['account_type'],
					'account_firstname' => $contact['n_given'],
					'account_lastname'  => $contact['n_family'],
					'account_email'     => $contact['email'],
					'person_id'         => $contact['id'],
					'account_status'	=> $contact['account_status'],
					// addressbook_bo::search() returns everything in user-time, need to convert to server-time
					'account_created'	=> egw_time::user2server($contact['created']),
					'account_modified'	=> egw_time::user2server($contact['modified']),
				);
			}
		}
		$this->total = $GLOBALS['egw']->contacts->total;

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
	 * @param string $which='account_lid' type of $name: account_lid (default), account_email, person_id, account_fullname
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
		}
		if ($account_type)
		{
			$where['account_type'] = $account_type;
		}
		else
		{
			$where[] = 'account_id IS NOT NULL';	// otherwise contacts with eg. the same email hide the accounts!
		}
		if (!($row = $this->db->select($table,$cols,$where,__LINE__,__FILE__)->fetch())) return false;

		return ($row['account_type'] == 'g' ? -1 : 1) * $row['account_id'];
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
