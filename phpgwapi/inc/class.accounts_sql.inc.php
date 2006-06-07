<?php
/**
 * API - accounts SQL backend
 * 
 * The SQL backend stores the group memberships via the ACL class (location 'phpgw_group')
 * 
 * The (positive) account_id's of groups are mapped in this class to negative numeric 
 * account_id's, to conform wit the way we handle groups in LDAP!
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
class accounts_backend
{
	/**
	 * instance of the db class
	 *
	 * @var object
	 */
	var $db;
	/**
	 * table name for the accounts
	 *
	 * @var string
	 */
	var $table = 'egw_accounts';
	/**
	 * total number of found entries from get_list method
	 *
	 * @var int
	 */
	var $total;

	function accounts_backend()
	{
		if (is_object($GLOBALS['egw_setup']->db))
		{
			$this->db = clone($GLOBALS['egw_setup']->db);
		}
		else
		{
			$this->db = clone($GLOBALS['egw']->db);
		}
		$this->db->set_app('phpgwapi');	// to load the right table-definitions for insert, select, update, ...

		if (!is_object($GLOBALS['egw']->acl))
		{
			$GLOBALS['egw']->acl =& CreateObject('phpgwapi.acl');
		}
	}

	/**
	 * Reads the data of one account
	 *
	 * @param int $account_id numeric account-id
	 * @return array/boolean array with account data (keys: account_id, account_lid, ...) or false if account not found
	 */
	function read($account_id)
	{
		if (!(int)$account_id) return false;
		
		$this->db->select($this->table,'*',array('account_id' => abs($account_id)),__LINE__,__FILE__);
		if (!($data = $this->db->row(true)))
		{
			return false;
		}
		if ($data['account_type'] == 'g')
		{
			$data['account_id'] = -$data['account_id'];
		}
		$data['account_fullname'] = $data['account_firstname'].' '.$data['account_lastname'];

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
		unset($to_write['account_id']);
		unset($to_write['account_passwd']);
		
		// encrypt password if given or unset it if not
		if ($data['account_passwd'])
		{
			if (!is_object($GLOBALS['egw']->auth))
			{
				$GLOBALS['egw']->auth =& CreateObject('phpgwapi.auth');
			}
			$to_write['account_pwd'] = $GLOBALS['egw']->auth->encrypt_sql($data['account_passwd']);
		}
		if (!(int)$data['account_id'])
		{
			if (!in_array($to_write['account_type'],array('u','g')) ||
				!$this->db->insert($this->table,$to_write,false,__LINE__,__FILE__)) return false;
				
			$data['account_id'] = $this->db->get_last_insert_id($this->table,'account_id');
			if ($data['account_type'] == 'g') $data['account_id'] *= -1;
		}
		elseif (!$this->db->update($this->table,$to_write,array('account_id' => abs($data['account_id'])),__LINE__,__FILE__))
		{
			return false;
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

		return !!$this->db->delete($this->table,array('account_id' => abs($account_id)),__LINE__,__FILE__);
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
		if (!($uids = $GLOBALS['egw']->acl->get_ids_for_location($account_id, 1, 'phpgw_group')))
		{
			return False;
		}
		$members = array();
		foreach ($uids as $uid)
		{
			$members[$uid] = $this->id2name($uid);
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
		//echo "<p>accounts::set_members(".print_r($members,true).",$gid)</p>\n";
		$GLOBALS['egw']->acl->delete_repository('phpgw_group',$gid);
		foreach($members as $id)
		{
			$GLOBALS['egw']->acl->add_repository('phpgw_group',$gid,$id,1);
		}
	}

	/**
	 * Searches users and/or groups
	 * 
	 * ToDo: implement a search like accounts::search
	 *
	 * @param string $_type
	 * @param int $start=null
	 * @param string $sort=''
	 * @param string $order=''
	 * @param string $query
	 * @param int $offset=null
	 * @param string $query_type
	 * @return array
	 */
	function get_list($_type='both', $start = '',$sort = '', $order = '', $query = '', $offset = null, $query_type='')
	{
		if (! $sort)
		{
			$sort = "DESC";
		}

		if (!empty($order) && preg_match('/^[a-zA-Z_0-9, ]+$/',$order) && (empty($sort) || preg_match('/^(DESC|ASC|desc|asc)$/',$sort)))
		{
			$orderclause = "ORDER BY $order $sort";
		}
		else
		{
			$orderclause = "ORDER BY account_lid ASC";
		}

		switch($_type)
		{
			case 'accounts':
				$whereclause = "WHERE account_type = 'u'";
				break;
			case 'groups':
				$whereclause = "WHERE account_type = 'g'";
				break;
			default:
				$whereclause = '';
		}

		if ($query)
		{
			if ($whereclause)
			{
				$whereclause .= ' AND ( ';
			}
			else
			{
				$whereclause = ' WHERE ( ';
			}
			switch($query_type)
			{
				case 'all':
				default:
					$query = '%'.$query;
					// fall-through
				case 'start':
					$query .= '%';
					// fall-through
				case 'exact':
					$query = $this->db->quote($query);
					$whereclause .= " account_firstname LIKE $query OR account_lastname LIKE $query OR account_lid LIKE $query )";
					break;
				case 'firstname':
				case 'lastname':
				case 'lid':
				case 'email':
					$query = $this->db->quote('%'.$query.'%');
					$whereclause .= " account_$query_type LIKE $query )";
					break;
			}
		}

		$sql = "SELECT * FROM $this->table $whereclause $orderclause";
		if ($offset)
		{
			$this->db->limit_query($sql,$start,__LINE__,__FILE__,$offset);
		}
		elseif (is_numeric($start))
		{
			$this->db->limit_query($sql,$start,__LINE__,__FILE__);
		}
		else
		{
			$this->db->query($sql,__LINE__,__FILE__);
		}
		while ($this->db->next_record())
		{
			$accounts[] = Array(
				'account_id'        => ($this->db->f('account_type') == 'g' ? -1 : 1) * $this->db->f('account_id'),
				'account_lid'       => $this->db->f('account_lid'),
				'account_type'      => $this->db->f('account_type'),
				'account_firstname' => $this->db->f('account_firstname'),
				'account_lastname'  => $this->db->f('account_lastname'),
				'account_status'    => $this->db->f('account_status'),
				'account_expires'   => $this->db->f('account_expires'),
				'person_id'         => $this->db->f('person_id'),
				'account_primary_group' => $this->db->f('account_primary_group'),
				'account_email'     => $this->db->f('account_email'),
			);
		}
		$this->db->query("SELECT count(*) FROM $this->table $whereclause");
		$this->total = $this->db->next_record() ? $this->db->f(0) : 0;

		return $accounts;
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
	 * @param string $account_type u = user, g = group, default null = try both
	 * @return int/false numeric account_id or false on error ($name not found)
	 */
	function name2id($name,$which='account_lid',$account_type=null)
	{
		$where = array();
		switch($which)
		{
			case 'account_fullname':
				$where[] = '('.$this->db->concat('account_firstname',"' '",'account_lastname').')='.$this->db->quote($name);
				break;

			default:
				$where[$which] = $name;
		}
		if ($account_type)
		{
			$where['account_type'] = $account_type;
		}
		$this->db->select($this->table,'account_id,account_type',$where,__LINE__,__FILE__);
		if(!$this->db->next_record()) return false;
		
		return ($this->db->f('account_type') == 'g' ? -1 : 1) * $this->db->f('account_id');
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
		$this->db->select($this->table,'account_lastlogin',array('account_id'=>abs($account_id)),__LINE__,__FILE__);
		$previous_login = $this->db->next_record() ? $this->db->f('account_lastlogin') : false;

		$this->db->update($this->table,array(
			'account_lastloginfrom' => $ip,
			'account_lastlogin'     => time(),
		),array(
			'account_id' => abs($account_id),
		),__LINE__,__FILE__);
		
		return $previous_login;
	}
}
