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

namespace EGroupware\Api\Accounts;

use EGroupware\Api;

/**
 * SQL Backend for accounts
 *
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @access internal only use the interface provided by the accounts class
 */
class Sql
{
	/**
	 * instance of the db class
	 *
	 * @var Api\Db
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
	 * Location for group-memberships in ACL table
	 */
	const ACL_GROUP_LOCATION = 'phpgw_group';
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
	 * @var Api\Accounts
	 */
	private $frontend;

	/**
	 * Instance of contacts object, NOT automatic instanciated!
	 *
	 * @var Api\Contacts
	 */
	private $contacts;

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
	 * @param Api\Accounts $frontend reference to the frontend class, to be able to call it's methods if needed
	 */
	function __construct(Api\Accounts $frontend=null)
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
	 * Set used frontend object
	 *
	 * @param Api\Accounts $frontend
	 */
	public function setFrontend(Api\Accounts $frontend)
	{
		$this->frontend = $frontend;
	}

	/**
	 * Set used contacts object
	 *
	 * @param Api\Contacts $contacts
	 */
	public function setContacts(Api\Contacts $contacts)
	{
		$this->contacts = $contacts;
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
		elseif (!isset($GLOBALS['egw_setup']) || in_array(Api\Mail\Smtp\Sql::TABLE, $this->db->table_names(true)))
		{
			$extra_cols = Api\Mail\Smtp\Sql::TABLE.'.mail_value AS account_email,';
			$join = 'LEFT JOIN '.Api\Mail\Smtp\Sql::TABLE.' ON '.$this->table.'.account_id=-'.Api\Mail\Smtp\Sql::TABLE.'.account_id AND mail_type='.Api\Mail\Smtp\Sql::TYPE_ALIAS;
		}
		try {
			$rs = $this->db->select($this->table, $extra_cols.$this->table.'.*',
				$this->table.'.account_id='.abs($account_id),
				__LINE__, __FILE__, false, '', false, 0, $join);
		}
		catch (Api\Db\Exception $e) {
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
		if (empty($data['account_firstname'])) $data['account_firstname'] = $data['account_lid'];
		if (empty($data['account_lastname']))
		{
			$data['account_lastname'] = $data['account_type'] == 'g' ? 'Group' : 'User';
			// if we call lang() before the translation-class is correctly setup,
			// we can't switch away from english language anymore!
			if (Api\Translation::$lang_arr)
			{
				$data['account_lastname'] = lang($data['account_lastname']);
			}
		}
		if (empty($data['account_fullname'])) $data['account_fullname'] = $data['account_firstname'].' '.$data['account_lastname'];

		return $data;
	}

	/**
	 * Saves / adds the data of one account
	 *
	 * If no account_id is set in data the account is added and the new id is set in $data.
	 *
	 * @param array $data array with account-data
	 * @param bool $force_create true: do NOT check with frontend, if account exists
	 * @return int|false the account_id or false on error
	 */
	function save(&$data, $force_create=false)
	{
		$to_write = $data;
		unset($to_write['account_passwd']);
		// encrypt password if given or unset it if not
		if ($data['account_passwd'])
		{
			// if password it's not already entcrypted, do so now
			if (!preg_match('/^\\{[a-z5]{3,5}\\}.+/i',$data['account_passwd']) &&
				!preg_match('/^[0-9a-f]{32}$/',$data['account_passwd']))	// md5 hash
			{
				$data['account_passwd'] = Api\Auth::encrypt_sql($data['account_passwd']);
			}
			$to_write['account_pwd'] = $data['account_passwd'];
			$to_write['account_lastpwd_change'] = time();
		}
		if ($data['mustchangepassword'] == 1) $to_write['account_lastpwd_change']=0;
		if ($force_create || !(int)$data['account_id'] || !$this->id2name($data['account_id']))
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
		if ($data['account_id'] < 0 && class_exists('EGroupware\\Api\\Mail\\Smtp\\Sql', isset($data['account_email'])))
		{
			try {
				if (isset($GLOBALS['egw_setup']) && !in_array(Api\Mail\Smtp\Sql::TABLE, $this->db->table_names(true)))
				{
					// cant store email, if table not yet exists
				}
				elseif (empty($data['account_email']))
				{
					$this->db->delete(Api\Mail\Smtp\Sql::TABLE, array(
						'account_id' => $data['account_id'],
						'mail_type' => Api\Mail\Smtp\Sql::TYPE_ALIAS,
					), __LINE__, __FILE__, Api\Mail\Smtp\Sql::APP);
				}
				else
				{
					$this->db->insert(Api\Mail\Smtp\Sql::TABLE, array(
						'mail_value' => $data['account_email'],
					), array(
						'account_id' => $data['account_id'],
						'mail_type' => Api\Mail\Smtp\Sql::TYPE_ALIAS,
					), __LINE__, __FILE__, Api\Mail\Smtp\Sql::APP);
				}
			}
			// ignore not (yet) existing mailaccounts table (does NOT work in PostgreSQL, because of transaction!)
			catch (Api\Db\Exception $e) {
				unset($e);
			}
		}
		return $data['account_id'];
	}

	/**
	 * Delete one account
	 *
	 * Does NOT delete acl-entries and memberships, use Acl::delete_account($account_id) for that!
	 * Users need to be deleted via admin_cmd_delete_account, to ensure proper data removal.
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
		if ($account_id > 0 && $contact_id)
		{
			if (!isset($this->contacts)) $this->contacts = new Api\Contacts();
			$this->contacts->delete($contact_id,false, false, true);	// false = allow to delete accounts (!)
		}
		return true;
	}

	/**
	 * Get all memberships of an account $account_id / groups the account is a member off
	 *
	 * @param int $account_id numeric account-id
	 * @return array|boolean array with account_id => account_lid pairs or false if account not found
	 */
	function memberships($account_id)
	{
		if (!(int)$account_id) return false;

		$memberships = array();
		foreach($this->db->select(Api\Acl::TABLE, 'account_id,account_lid',
		[
			'acl_account' => $account_id,
			'acl_appname' => self::ACL_GROUP_LOCATION
		], __LINE__, __FILE__, false, 'ORDER BY account_lid', false, 0,
		'JOIN '.self::TABLE.' ON ABS('.$this->db->to_int('acl_location').')=account_id') as $row)
		{
			$memberships['-'.$row['account_id']] = $row['account_lid'];
		}
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

		$acl = new Api\Acl($account_id);
		$acl->read_repository();
		$acl->delete(self::ACL_GROUP_LOCATION,false);

		foreach($groups as $group)
		{
			$acl->add(self::ACL_GROUP_LOCATION,$group,1);
		}
		$acl->save_repository();
	}

	/**
	 * Get all members of the group $accountid
	 *
	 * @param int|string $account_id numeric account-id
	 * @return array with account_id => account_lid pairs
	 */
	function members($account_id)
	{
		if (!is_numeric($account_id)) $account_id = $this->name2id($account_id);

		$members = array();
		foreach($this->db->select($this->table, 'account_id,account_lid',
			$this->db->expression(Api\Acl::TABLE, array(
				'acl_appname'  => self::ACL_GROUP_LOCATION,
				'acl_location' => $account_id,
			)), __LINE__, __FILE__, false, 'ORDER BY account_lid', false, 0,
			'JOIN '.Api\Acl::TABLE.' ON account_id=acl_account'
		) as $row)
		{
			$members[$row['account_id']] = $row['account_lid'];
		}
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
		$GLOBALS['egw']->acl->delete_repository(self::ACL_GROUP_LOCATION,$gid,false);

		if (is_array($members))
		{
			foreach($members as $id)
			{
				$GLOBALS['egw']->acl->add_repository(self::ACL_GROUP_LOCATION,$gid,$id,1);
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
	 * @param $param['account_id'] int[] return only given account_id's
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

		// fetch order of account_fullname from Api\Accounts::format_username
		if (strpos($param['order'] ?? '','account_fullname') !== false)
		{
			$param['order'] = str_replace('account_fullname', preg_replace('/[ ,]+/',',',str_replace(array('[',']'),'',
				Api\Accounts::format_username('account_lid','account_firstname','account_lastname'))), $param['order']);
		}
		$order = str_replace(array_keys($order2contact),array_values($order2contact),$param['order'] ?? '');

		// always add 'account_lid'
		if (strpos($order, 'account_lid') === false)
		{
			$order .= ($order?',':'').'account_lid';
		}
		if ($param['sort']) $order = implode(' '.$param['sort'].',', explode(',', $order)).' '.$param['sort'];

		$search_cols = array('account_lid','n_family','n_given','email');
		$join = $this->contacts_join;
		$email_cols = array('email');

		// Add in group email searching
		if (!isset($GLOBALS['egw_setup']) || in_array(Api\Mail\Smtp\Sql::TABLE, $this->db->table_names(true)))
		{
			$email_cols = array('coalesce('.$this->contacts_table.'.contact_email,'.Api\Mail\Smtp\Sql::TABLE.'.mail_value) as email');
			if ($this->db->Type == 'mysql' && !preg_match('/[\x80-\xFF]/', $param['query'] ?? ''))
			{
				$search_cols[] = Api\Mail\Smtp\Sql::TABLE.'.mail_value';
			}
			$join .= ' LEFT JOIN '.Api\Mail\Smtp\Sql::TABLE.' ON '.$this->table.'.account_id=-'.Api\Mail\Smtp\Sql::TABLE.'.account_id AND mail_type='.Api\Mail\Smtp\Sql::TYPE_ALIAS;
		}

		$filter = empty($param['account_id']) ? [] : ['account_id' => (array)$param['account_id']];
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
				foreach((array)$this->memberships($GLOBALS['egw_info']['user']['account_id'], true) as $grp => $name)
				{
					unset($name);
					$members = array_unique(array_merge($members, array_keys((array)$this->members($grp))));
					if ($param['type'] == 'groupmembers+memberships') $members[] = abs($grp);
				}
				$filter['account_id'] = empty($filter['account_id']) ? $members : array_merge($members, $filter['account_id']);
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
		// fix ambiguous account_id (used in accounts and contacts table)
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
			$filter[] = str_replace('UNIX_TIMESTAMP(NOW())',time(),Api\Contacts\Sql::ACOUNT_ACTIVE_FILTER);
		}
		$criteria = array();
		$wildcard = in_array($param['query_type'] ?? '', ['start', 'exact']) ? '' : '%';
		if (($query = $param['query'] ?? null))
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
					if(in_array(Api\Mail\Smtp\Sql::TABLE, $this->db->table_names(true)))
					{
						$criteria[Api\Mail\Smtp\Sql::TABLE.'.mail_value'] = $query;
					}
					break;
			}
		}
		if (!isset($this->contacts)) $this->contacts = new Api\Contacts();

		$accounts = array();
		foreach($this->contacts->search($criteria,
			array_merge(array(1,'n_given','n_family','id','created','modified','files',$this->table.'.account_id AS account_id'),$email_cols),
			$order, "account_lid,account_type,account_status,account_expires,account_primary_group,account_description".
			",account_lastlogin,account_lastloginfrom,account_lastpwd_change",
			$wildcard,false,$query[0] == '!' ? 'AND' : 'OR',
			!empty($param['offset']) ? array($param['start'], $param['offset']) : $param['start'] ?? false,
			$filter,$join) ?? [] as $contact)
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
					// Api\Contacts::search() returns everything in user-time, need to convert to server-time
					'account_created'	=> Api\DateTime::user2server($contact['created']),
					'account_modified'	=> Api\DateTime::user2server($contact['modified']),
					'account_lastlogin'	=> $contact['account_lastlogin'] ?
						Api\DateTime::user2server($contact['account_lastlogin']) : null,
					'account_lastloginfrom'	=> $contact['account_lastloginfrom'],
					'account_lastpwd_change'	=> $contact['account_lastpwd_change'] ?
						Api\DateTime::user2server($contact['account_lastpwd_change']) : null,
					'account_description' => $contact['account_description'],
					'account_has_photo' => Api\Contacts::hasPhoto($contact),
				);
			}
		}
		$this->total = $this->contacts->total;
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
				if ($which === 'account_lid' && empty($GLOBALS['egw_info']['server']['case_sensitive_username']))	// = is case sensitiv eg. on postgres, but not on mysql!
				{
					$where[] = 'account_lid '.$this->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$this->db->quote($where['account_lid']);
					unset($where['account_lid']);
				}
		}
		if ($account_type && $table !== $this->contacts_table)
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