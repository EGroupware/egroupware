<?php
/**
 * Addressbook - General storage object
 *
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw-AT-von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2005-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * General storage object of the adressbook
 *
 * The contact storage has 3 operation modi (contact_repository):
 * - sql: contacts are stored in the SQL table egw_addressbook & egw_addressbook_extra (custom fields)
 * - ldap: contacts are stored in LDAP (accounts have to be stored in LDAP too!!!).
 *   Custom fields are not availible in that case!
 * - sql-ldap: contacts are read and searched in SQL, but saved to both SQL and LDAP.
 *   Other clients (Thunderbird, ...) can use LDAP readonly. The get maintained via eGroupWare only.
 *
 * The accounts can be stored in SQL or LDAP too (account_repository):
 * If the account-repository is different from the contacts-repository, the filter all (no owner set)
 * will only search the contacts and NOT the accounts! Only the filter accounts (owner=0) shows accounts.
 *
 * If sql-ldap is used as contact-storage (LDAP is managed from eGroupWare) the filter all, searches
 * the accounts in the SQL contacts-table too. Change in made in LDAP, are not detected in that case!
 */

class addressbook_so
{
	/**
	 * name of customefields table
	 *
	 * @var string
	 */
	var $extra_table = 'egw_addressbook_extra';

	/**
	* @var string
	*/
	var $extra_id = 'contact_id';

	/**
	* @var string
	*/
	var $extra_owner = 'contact_owner';

	/**
	* @var string
	*/
	var $extra_key = 'contact_name';

	/**
	* @var string
	*/
	var $extra_value = 'contact_value';

	/**
	 * view for distributionlistsmembership
	 *
	 * @var string
	 */
	var $distributionlist_view ='(SELECT contact_id, egw_addressbook_lists.list_id as list_id, egw_addressbook_lists.list_name as list_name, egw_addressbook_lists.list_owner as list_owner FROM egw_addressbook_lists, egw_addressbook2list where egw_addressbook_lists.list_id=egw_addressbook2list.list_id) d_view ';
	var $distributionlist_tabledef = array();
	/**
	* @var string
	*/
	var $distri_id = 'contact_id';

	/**
	* @var string
	*/
	var $distri_owner = 'list_owner';

	/**
	* @var string
	*/
	var $distri_key = 'list_id';

	/**
	* @var string
	*/
	var $distri_value = 'list_name';

	/**
	 * Contact repository in 'sql' or 'ldap'
	 *
	 * @var string
	 */
	var $contact_repository = 'sql';

	/**
	 * Grants as  account_id => rights pairs
	 *
	 * @var array
	 */
	var $grants;

	/**
	 *  userid of current user
	 *
	 * @var int
	 */
	var $user;

	/**
	 * memberships of the current user
	 *
	 * @var array
	 */
	var $memberships;

	/**
	 * LDAP searches only a limited set of attributes for performance reasons,
	 * you NEED an index for that columns, ToDo: make it configurable
	 * minimum: $this->columns_to_search = array('n_family','n_given','org_name','email');
	 */
	var $ldap_search_attributes = array(
		'n_family','n_middle','n_given','org_name','org_unit',
		'adr_one_location','adr_two_location','note',
		'email','mozillasecondemail','uidnumber',
	);
	/**
	 * In SQL we can search all columns, though a view make on real sense
	 */
	var $sql_cols_not_to_search = array(
		'jpegphoto','owner','tid','private','cat_id','etag',
		'modified','modifier','creator','created','tz','account_id',
		'uid',
	);
	/**
	 * columns to search, if we search for a single pattern
	 *
	 * @var array
	 */
	var $columns_to_search = array();
	/**
	 * extra columns to search if accounts are included, eg. account_lid
	 *
	 * @var array
	 */
	var $account_extra_search = array();
	/**
	 * columns to search for accounts, if stored in different repository
	 *
	 * @var array
	 */
	var $account_cols_to_search = array();

	/**
	 * customfields name => array(...) pairs
	 *
	 * @var array
	 */
	var $customfields = array();
	/**
	 * content-types as name => array(...) pairs
	 *
	 * @var array
	 */
	var $content_types = array();

	/**
	* Special content type to indicate a deleted addressbook
	*
	* @var String;
	*/
	const DELETED_TYPE = 'D';

	/**
	 * total number of matches of last search
	 *
	 * @var int
	 */
	var $total;

	/**
	 * storage object: sql (addressbook_sql) or ldap (addressbook_ldap) backend class
	 *
	 * @var addressbook_sql
	 */
	var $somain;
	/**
	 * storage object for accounts, if not identical to somain (eg. accounts in ldap, contacts in sql)
	 *
	 * @var so_ldap
	 */
	var $so_accounts;
	/**
	 * account repository sql or ldap
	 *
	 * @var string
	 */
	var $account_repository = 'sql';
	/**
	 * custom fields backend
	 *
	 * @var addressbook_sql
	 */
	var $soextra;
	var $sodistrib_list;
	var $backend;

	/**
	 * Constructor
	 *
	 * @param string $contact_app='addressbook' used for acl->get_grants()
	 * @param egw_db $db=null
	 */
	function __construct($contact_app='addressbook',egw_db $db=null)
	{
		$this->db     = is_null($db) ? $GLOBALS['egw']->db : $db;

		$this->user = $GLOBALS['egw_info']['user']['account_id'];
		$this->memberships = $GLOBALS['egw']->accounts->memberships($this->user,true);

		// account backend used
		if ($GLOBALS['egw_info']['server']['account_repository'])
		{
			$this->account_repository = $GLOBALS['egw_info']['server']['account_repository'];
		}
		elseif ($GLOBALS['egw_info']['server']['auth_type'])
		{
			$this->account_repository = $GLOBALS['egw_info']['server']['auth_type'];
		}
		// contacts backend (contacts in LDAP require accounts in LDAP!)
		if($GLOBALS['egw_info']['server']['contact_repository'] == 'ldap' && $this->account_repository == 'ldap')
		{
			$this->contact_repository = 'ldap';
			$this->somain = new addressbook_ldap();

			$this->columns_to_search = $this->ldap_search_attributes;
		}
		else	// sql or sql->ldap
		{
			if ($GLOBALS['egw_info']['server']['contact_repository'] == 'sql-ldap')
			{
				$this->contact_repository = 'sql-ldap';
			}
			$this->somain = new addressbook_sql($db);

			// remove some columns, absolutly not necessary to search in sql
			$this->columns_to_search = array_diff(array_values($this->somain->db_cols),$this->sql_cols_not_to_search);
		}
		if ($this->user)
		{
			$this->grants = $this->get_grants($this->user,$contact_app);
		}
		if ($this->account_repository == 'ldap' && $this->contact_repository == 'sql')
		{
			if ($this->account_repository != $this->contact_repository)
			{
				$this->so_accounts = new addressbook_ldap();
				$this->account_cols_to_search = $this->ldap_search_attributes;
			}
			else
			{
				$this->account_extra_search = array('uid');
			}
		}
		if ($this->contact_repository == 'sql' || $this->contact_repository == 'sql-ldap')
		{
			$tda2list = $this->db->get_table_definitions('phpgwapi','egw_addressbook2list');
			$tdlists = $this->db->get_table_definitions('phpgwapi','egw_addressbook_lists');
			$this->distributionlist_tabledef = array('fd' => array(
					$this->distri_id => $tda2list['fd'][$this->distri_id],
					$this->distri_owner => $tdlists['fd'][$this->distri_owner],
        	    	$this->distri_key => $tdlists['fd'][$this->distri_key],
					$this->distri_value => $tdlists['fd'][$this->distri_value],
				), 'pk' => array(), 'fk' => array(), 'ix' => array(), 'uc' => array(),
			);
		}
		// add grants for accounts: if account_selection not in ('none','groupmembers'): everyone has read access,
		// if he has not set the hide_accounts preference
		// ToDo: be more specific for 'groupmembers', they should be able to see the groupmembers
		if (!in_array($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'],array('none','groupmembers')))
		{
			$this->grants[0] = EGW_ACL_READ;
		}
		// add account grants for admins
		if ($this->is_admin())	// admin rights can be limited by ACL!
		{
			$this->grants[0] = EGW_ACL_READ;	// admins always have read-access
			if (!$GLOBALS['egw']->acl->check('account_access',16,'admin')) $this->grants[0] |= EGW_ACL_EDIT;
			// no add at the moment if (!$GLOBALS['egw']->acl->check('account_access',4,'admin'))  $this->grants[0] |= EGW_ACL_ADD;
			if (!$GLOBALS['egw']->acl->check('account_access',32,'admin')) $this->grants[0] |= EGW_ACL_DELETE;
		}
		// ToDo: it should be the other way arround, the backend should set the grants it uses
		$this->somain->grants =& $this->grants;

		if($this->somain instanceof addressbook_sql)
		{
			$this->soextra =& $this->somain;
		}
		else
		{
			$this->soextra = new addressbook_sql($db);
		}

		$this->customfields = config::get_customfields('addressbook');
		$this->content_types = config::get_content_types('addressbook');
		if (!$this->content_types)
		{
			$this->content_types = array('n' => array(
				'name' => 'contact',
				'options' => array(
					'template' => 'addressbook.edit',
					'icon' => 'navbar.png'
			)));
		}

		// Add in deleted type, if holding deleted contacts
		$config = config::read('phpgwapi');
		if($config['history'])
		{
			$this->content_types[self::DELETED_TYPE] = array(
				'name'	=>	lang('Deleted'),
				'options' =>	array(
					'template'	=>	'addressbook.edit',
					'icon'		=>	'deleted.png'
				)
			);
		}
	}

	/**
	 * Get grants for a given user, taking into account static LDAP ACL
	 *
	 * @param int $user
	 * @param string $contact_app='addressbook'
	 * @return array
	 */
	function get_grants($user,$contact_app='addressbook')
	{
		if ($user)
		{
			// contacts backend (contacts in LDAP require accounts in LDAP!)
			if($GLOBALS['egw_info']['server']['contact_repository'] == 'ldap' && $this->account_repository == 'ldap')
			{
				// static grants from ldap: all rights for the own personal addressbook and the group ones of the meberships
				$grants = array($user => ~0);
				foreach($GLOBALS['egw']->accounts->memberships($user,true) as $gid)
				{
					$grants[$gid] = ~0;
				}
			}
			else	// sql or sql->ldap
			{
				// group grants are now grants for the group addressbook and NOT grants for all its members,
				// therefor the param false!
				$grants = $GLOBALS['egw']->acl->get_grants($contact_app,false,$user);
			}
		}
		else
		{
			$grants = array();
		}
		return $grants;
	}

	/**
	 * Check if the user is an admin (can unconditionally edit accounts)
	 *
	 * We check now the admin ACL for edit users, as the admin app does it for editing accounts.
	 *
	 * @param array $contact=null for future use, where admins might not be admins for all accounts
	 * @return boolean
	 */
	function is_admin($contact=null)
	{
		return isset($GLOBALS['egw_info']['user']['apps']['admin']) && !$GLOBALS['egw']->acl->check('account_access',16,'admin');
	}

	/**
	 * Read all customfields of the given id's
	 *
	 * @param int|array $ids
	 * @param array $field_names=null custom fields to read, default all
	 * @return array id => name => value
	 */
	function read_customfields($ids,$field_names=null)
	{
		return $this->soextra->read_customfields($ids,$field_names);
	}

	/**
	 * Read all distributionlists of the given id's
	 *
	 * @param int|array $ids
	 * @return array id => name => value
	 */
	function read_distributionlist($ids, $dl_allowed=array())
	{
		if ($this->contact_repository == 'ldap')
		{
			return array();	// ldap does not support distributionlists
		}
		foreach($ids as $key => $id)
		{
			if (!is_numeric($id)) unset($ids[$key]);
		}
		if (!$ids) return array();	// nothing to do, eg. all these contacts are in ldap
		$fields = array();
		$filter[$this->distri_id]=$ids;
		if (count($dl_allowed)) $filter[$this->distri_key]=$dl_allowed;
		$distri_view = str_replace(') d_view',' and '.$this->distri_id.' in ('.implode(',',$ids).')) d_view',$this->distributionlist_view);
		#_debug_array($this->distributionlist_tabledef);
		foreach($this->db->select($distri_view,'*',$filter,__LINE__,__FILE__,
			false,'ORDER BY '.$this->distri_id,false,$num_rows=0,$join='',$this->distributionlist_tabledef) as $row)
		{
			if ((isset($row[$this->distri_id])&&strlen($row[$this->distri_value])>0))
			{
				$fields[$row[$this->distri_id]][$row[$this->distri_key]] = $row[$this->distri_value].' ('.$GLOBALS['egw']->common->grab_owner_name($row[$this->distri_owner]).')';
			}
		}
		return $fields;
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * it gets called everytime when data is read from the db
	 * This function needs to be reimplemented in the derived class
	 *
	 * @param array $data
	 */
	function db2data($data)
	{
		return $data;
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * It gets called everytime when data gets writen into db or on keys for db-searches
	 * this needs to be reimplemented in the derived class
	 *
	 * @param array $data
	 */
	function data2db($data)
	{
		return $data;
	}

	/**
	* deletes contact entry including custom fields
	*
	* @param mixed $contact array with id or just the id
	* @param int $check_etag=null
	* @return boolean|int true on success or false on failiure, 0 if etag does not match
	*/
	function delete($contact,$check_etag=null)
	{
		if (is_array($contact)) $contact = $contact['id'];

		$where = array('id' => $contact);
		if ($check_etag) $where['etag'] = $check_etag;

		// delete mainfields
		if ($this->somain->delete($where))
		{
			// delete customfields, can return 0 if there are no customfields
			if(!($this->somain instanceof addressbook_sql))
			{
				$this->soextra->delete_customfields(array($this->extra_id => $contact));
			}

			// delete from distribution list(s)
			$this->remove_from_list($contact);

			if ($this->contact_repository == 'sql-ldap')
			{
				if ($contact['account_id'])
				{
					// LDAP uses the uid attributes for the contact-id (dn),
					// which need to be the account_lid for accounts!
					$contact['id'] = $GLOBALS['egw']->accounts->id2name($contact['account_id']);
				}
				ExecMethod('addressbook.addressbook_ldap.delete',$contact);
			}
			return true;
		}
		return $check_etag ? 0 : false;		// if etag given, we return 0 on failure, thought it could also mean the whole contact does not exist
	}

	/**
	* saves contact data including custom fields
	*
	* @param array &$contact contact data from etemplate::exec
	* @return bool false on success, errornumber on failure
	*/
	function save(&$contact)
	{
		// save mainfields
		if ($contact['id'] && $this->contact_repository != $this->account_repository && is_object($this->so_accounts) &&
			($this->contact_repository == 'sql' && !is_numeric($contact['id']) ||
			 $this->contact_repository == 'ldap' && is_numeric($contact['id'])))
		{
			$this->so_accounts->data = $this->data2db($contact);
			$error_nr = $this->so_accounts->save();
			$contact['id'] = $this->so_accounts->data['id'];
		}
		else
		{
			// contact_repository sql-ldap (accounts in ldap) the person_id is the uid (account_lid)
			// for the sql write here we need to find out the existing contact_id
			if ($this->contact_repository == 'sql-ldap' && $contact['id'] && !is_numeric($contact['id']) &&
				$contact['account_id'] && ($old = $this->somain->read(array('account_id' => $contact['account_id']))))
			{
				$contact['id'] = $old['id'];
			}
			$this->somain->data = $this->data2db($contact);

			if (!($error_nr = $this->somain->save()))
			{
				$contact['id'] = $this->somain->data['id'];
				$contact['uid'] = $this->somain->data['uid'];
				$contact['etag'] = $this->somain->data['etag'];

				if ($this->contact_repository == 'sql-ldap')
				{
					$data = $this->somain->data;
					if ($contact['account_id'])
					{
						// LDAP uses the uid attributes for the contact-id (dn),
						// which need to be the account_lid for accounts!
						$data['id'] = $GLOBALS['egw']->accounts->id2name($contact['account_id']);
					}
					ExecMethod('addressbook.addressbook_ldap.save',$data);
				}
			}
		}
		if($error_nr) return $error_nr;

		return false;	// no error
	}

	/**
	 * reads contact data including custom fields
	 *
	 * @param int|string $contact_id contact_id or 'a'.account_id
	 * @return array|boolean data if row could be retrived else False
	*/
	function read($contact_id)
	{
		if (!is_array($contact_id) && substr($contact_id,0,8) == 'account:')
		{
			$contact_id = array('account_id' => (int) substr($contact_id,8));
		}
		// read main data
		$backend =& $this->get_backend($contact_id);
		if (!($contact = $backend->read($contact_id)))
		{
			return $contact;
		}
		$dl_list=$this->read_distributionlist(array($contact['id']));
		if (count($dl_list)) $contact['distrib_lists']=implode("\n",$dl_list[$contact['id']]);
		return $this->db2data($contact);
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array|string $criteria array of key and data cols, OR string to search over all standard search fields
	 * @param boolean|string $only_keys=true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join (only used by sql backend!), eg. " RIGHT JOIN egw_accounts USING(account_id)"
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='')
	{
		//echo '<p>'.__METHOD__.'('.array2string($criteria,true).','.array2string($only_keys).",'$order_by','$extra_cols','$wildcard','$empty','$op',$start,".array2string($filter,true).",'$join')</p>\n";
		//error_log(__METHOD__.'('.array2string($criteria,true).','.array2string($only_keys).",'$order_by','$extra_cols','$wildcard','$empty','$op',$start,".array2string($filter,true).",'$join')");

		// Handle 'None' country option
		if(is_array($filter) && $filter['adr_one_countrycode'] == '-custom-')
		{
			$filter[] = 'adr_one_countrycode IS NULL';
			unset($filter['adr_one_countrycode']);
		}
		// Hide deleted items unless type is specifically deleted
		if(!is_array($filter)) $filter = $filter ? (array) $filter : array();

		// if no tid set or tid==='' do NOT return deleted entries ($tid === null returns all entries incl. deleted)
		if(!array_key_exists('tid', $filter) || $filter['tid'] === '')
		{
			if ($join && strpos($join,'RIGHT JOIN') !== false)	// used eg. to search for groups
			{
				$filter[] = '(contact_tid != \'' . self::DELETED_TYPE . '\' OR contact_tid IS NULL)';
			}
			else
			{
				$filter[] = 'contact_tid != \'' . self::DELETED_TYPE . '\'';
			}
		}
		elseif(is_null($filter['tid']))
		{
			unset($filter['tid']);	// return all entries incl. deleted
		}

		$backend =& $this->get_backend(null,$filter['owner']);
		// single string to search for --> create so_sql conformant search criterial for the standard search columns
		if ($criteria && !is_array($criteria))
		{
			$op = 'OR';
			$wildcard = '%';
			$search = $criteria;
			$criteria = array();

			if ($backend === $this->somain)
			{
				$cols = $this->columns_to_search;
			}
			else
			{
				$cols = $this->account_cols_to_search;
			}
			if($backend instanceof addressbook_sql)
			{
				// Keep a string, let the parent handle it
				$criteria = $search;

				foreach($cols as $key => &$col)
				{
					if(!array_key_exists($col, $backend->db_cols))
					{
						if(!($col = array_search($col, $backend->db_cols)))
						{
							// Can't search this column, it will error if we try
							unset($cols[$key]);
						}
					}
					if ($col=='contact_id') $col='egw_addressbook.contact_id';
				}

				$backend->columns_to_search = $cols;
			}
			else
			{
				foreach($cols as $col)
				{
					$criteria[$col] = $search;
				}
			}
		}
		if (is_array($criteria) && count($criteria))
		{
			$criteria = $this->data2db($criteria);
		}
		if (is_array($filter) && count($filter))
		{
			$filter = $this->data2db($filter);
		}
		else
		{
			$filter = $filter ? array($filter) : array();
		}
		// get the used backend for the search and call it's search method
		$rows = $backend->search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
		$this->total = $backend->total;

		if ($rows)
		{
			foreach($rows as $n => $row)
			{
				$rows[$n] = $this->db2data($row);
			}
		}
		return $rows;
	}

	/**
	 * Query organisations by given parameters
	 *
	 * @var array $param
	 * @var string $param[org_view] 'org_name', 'org_name,adr_one_location', 'org_name,org_unit' how to group
	 * @var int $param[owner] addressbook to search
	 * @var string $param[search] search pattern for org_name
	 * @var string $param[searchletter] letter the org_name need to start with
	 * @var int $param[start]
	 * @var int $param[num_rows]
	 * @var string $param[sort] ASC or DESC
	 * @return array or arrays with keys org_name,count and evtl. adr_one_location or org_unit
	 */
	function organisations($param)
	{
		if (!method_exists($this->somain,'organisations'))
		{
			$this->total = 0;
			return false;
		}
		if ($param['search'] && !is_array($param['search']))
		{
			$search = $param['search'];
			$param['search'] = array();
			if($this->somain instanceof addressbook_sql)
			{
				// Keep the string, let the parent deal with it
				$param['search'] = $search;
			}
			else
			{
				foreach($this->columns_to_search as $col)
				{
					if ($col != 'contact_value') $param['search'][$col] = $search;	// we dont search the customfields
				}
			}
		}
		if (is_array($param['search']) && count($param['search']))
		{
			$param['search'] = $this->data2db($param['search']);
		}
		$rows = $this->somain->organisations($param);
		$this->total = $this->somain->total;
		//echo "<p>socontacts::organisations(".print_r($param,true).")<br />".$this->somain->db->Query_ID->sql."</p>\n";

		if (!$rows) return array();

		foreach($rows as $n => $row)
		{
			if (strpos($row['org_name'],'&')!==false) $row['org_name'] = str_replace('&','*AND*',$row['org_name']); //echo "Ampersand found<br>";
			$rows[$n]['id'] = 'org_name:'.$row['org_name'];
			foreach(array(
				'org_unit' => lang('departments'),
				'adr_one_locality' => lang('locations'),
			) as $by => $by_label)
			{
				if ($row[$by.'_count'] > 1)
				{
					$rows[$n][$by] = $row[$by.'_count'].' '.$by_label;
				}
				else
				{
					if (strpos($row[$by],'&')!==false) $row[$by] = str_replace('&','*AND*',$row[$by]); //echo "Ampersand found<br>";
					$rows[$n]['id'] .= '|||'.$by.':'.$row[$by];
				}
			}
		}
		return $rows;
	}

 	/**
	 * gets all contact fields from database
	 *
	 * @return array of (internal) field-names
	 */
	function get_contact_columns()
	{
		$fields = $this->get_fields('all');
		foreach ((array)$this->customfields as $cfield => $coptions)
		{
			$fields[] = '#'.$cfield;
		}
		return $fields;
	}

	/**
	 * delete / move all contacts of an addressbook
	 *
	 * @param array $data
	 * @param int $data['account_id'] owner to change
	 * @param int $data['new_owner']  new owner or 0 for delete
	 */
	function deleteaccount($data)
	{
		$account_id = $data['account_id'];
		$new_owner =  $data['new_owner'];

		if (!$new_owner)
		{
			$this->somain->delete(array('owner' => $account_id));
			if(!($this->somain instanceof addressbook_sql))
			{
				$this->soextra->delete_customfields(array($this->extra_owner => $account_id));
			}
		}
		else
		{
			$this->somain->change_owner($account_id,$new_owner);
			$this->db->update($this->soextra->table_name,array(
				$this->extra_owner => $new_owner
			),array(
				$this->extra_owner => $account_id
			),__LINE__,__FILE__);
		}
	}

	/**
	 * return the backend, to be used for the given $contact_id
	 *
	 * @param array|string|int $keys=null
	 * @param int $owner=null account_id of owner or 0 for accounts
	 * @return object
	 */
	function get_backend($keys=null,$owner=null)
	{
		if ($owner === '') $owner = null;

		$contact_id = !is_array($keys) ? $keys :
			(isset($keys['id']) ? $keys['id'] : $keys['contact_id']);

		if ($this->contact_repository != $this->account_repository && is_object($this->so_accounts) &&
			(!is_null($owner) && !$owner || is_array($keys) && $keys['account_id'] || !is_null($contact_id) &&
			($this->contact_repository == 'sql' && (!is_numeric($contact_id) && !is_array($contact_id) )||
			 $this->contact_repository == 'ldap' && is_numeric($contact_id))))
		{
			return $this->so_accounts;
		}
		return $this->somain;
	}

	/**
	 * Returns the supported, all or unsupported fields of the backend (depends on owner or contact_id)
	 *
	 * @param sting $type='all' 'supported', 'unsupported' or 'all'
	 * @param mixed $contact_id=null
	 * @param int $owner=null account_id of owner or 0 for accounts
	 * @return array with eGW contact field names
	 */
	function get_fields($type='all',$contact_id=null,$owner=null)
	{
		$def = $this->db->get_table_definitions('phpgwapi','egw_addressbook');

		$all_fields = array();
		foreach($def['fd'] as $field => $data)
		{
			$all_fields[] = substr($field,0,8) == 'contact_' ? substr($field,8) : $field;
		}
		if ($type == 'all')
		{
			return $all_fields;
		}
		$backend =& $this->get_backend($contact_id,$owner);

		$supported_fields = method_exists($backend,supported_fields) ? $backend->supported_fields() : $all_fields;
		//echo "supported fields=";_debug_array($supported_fields);

		if ($type == 'supported')
		{
			return $supported_fields;
		}
		//echo "unsupported fields=";_debug_array(array_diff($all_fields,$supported_fields));
		return array_diff($all_fields,$supported_fields);
	}

	/**
	 * Migrates an SQL contact storage to LDAP or SQL-LDAP
	 *
	 * @param string $type "contacts" (default), "contacts+accounts" or "contacts+accounts-back" (sql-ldap!)
	 */
	function migrate2ldap($type)
	{
		$sql_contacts  = new addressbook_sql();
		$ldap_contacts = new addressbook_ldap();

		$start = $n = 0;
		$num = 100;
		while ($type != 'sql' && ($contacts = $sql_contacts->search(false,false,'n_family,n_given','','',false,'AND',
			array($start,$num),$type != 'contacts,accounts' ? array('contact_owner != 0') : false)))
		{
			// very worse hack, until Ralf finds a better solution
			// when migrating data, we need to bind as global ldap admin account
			// and not as currently logged in user
			$ldap_contacts->ds = $GLOBALS['egw']->ldap->ldapConnect();
			foreach($contacts as $contact)
			{
				if ($contact['account_id']) $contact['id'] = $GLOBALS['egw']->accounts->id2name($contact['account_id']);

				$ldap_contacts->data = $contact;
				$n++;
				if (!($err = $ldap_contacts->save()))
				{
					echo '<p style="margin: 0px;">'.$n.': '.$contact['n_fn'].
						($contact['org_name'] ? ' ('.$contact['org_name'].')' : '')." --> LDAP</p>\n";
				}
				else
				{
					echo '<p style="margin: 0px; color: red;">'.$n.': '.$contact['n_fn'].
						($contact['org_name'] ? ' ('.$contact['org_name'].')' : '').': '.$err."</p>\n";
				}
			}
			$start += $num;
		}
		if ($type == 'contacts,accounts-back' || $type == 'sql')  // migrate the accounts to sql
		{
			// very worse hack, until Ralf finds a better solution
			// when migrating data, we need to bind as global ldap admin account
			// and not as currently logged in user
			$ldap_contacts->ds = $GLOBALS['egw']->ldap->ldapConnect();
			foreach($ldap_contacts->search(false,false,'n_family,n_given','','',false,'AND',
				false,$type == 'sql'?null:array('owner' => 0)) as $contact)
			{
				if ($contact['jpegphoto'])	// photo is NOT read by LDAP backend on search, need to do an extra read
				{
					$contact = $ldap_contacts->read($contact['id']);
				}
				unset($contact['id']);	// ldap uid/account_lid
				if ($type != 'sql' && $contact['account_id'] && ($old = $sql_contacts->read(array('account_id' => $contact['account_id']))))
				{
					$contact['id'] = $old['id'];
				}
				$sql_contacts->data = $contact;

				$n++;
				if (!($err = $sql_contacts->save()))
				{
					echo '<p style="margin: 0px;">'.$n.': '.$contact['n_fn'].
						($contact['org_name'] ? ' ('.$contact['org_name'].')' : '')." --> SQL (".
						($contact['owner']?lang('User'):lang('Contact')).")</p>\n";
				}
				else
				{
					echo '<p style="margin: 0px; color: red;">'.$n.': '.$contact['n_fn'].
						($contact['org_name'] ? ' ('.$contact['org_name'].')' : '').': '.$err."</p>\n";
				}
			}
		}
	}

	/**
	 * Get the availible distribution lists for a user
	 *
	 * @param int $required=EGW_ACL_READ required rights on the list or multiple rights or'ed together,
	 * 	to return only lists fullfilling all the given rights
	 * @param string $extra_labels=null first labels if given (already translated)
	 * @return array with id => label pairs or false if backend does not support lists
	 */
	function get_lists($required=EGW_ACL_READ,$extra_labels=null)
	{
		if (!method_exists($this->somain,'get_lists')) return false;

		$uids = array();
		foreach($this->grants as $uid => $rights)
		{
			if (($rights & $required) == $required)
			{
				$uids[] = $uid;
			}
		}
		$lists = is_array($extra_labels) ? $extra_labels : array();

		foreach($this->somain->get_lists($uids) as $list_id => $data)
		{
			$lists[$list_id] = $data['list_name'];
			if ($data['list_owner'] != $this->user)
			{
				$lists[$list_id] .= ' ('.$GLOBALS['egw']->common->grab_owner_name($data['list_owner']).')';
			}
		}
		//echo "<p>socontacts_sql::get_lists($required,'$extra_label')</p>\n"; _debug_array($lists);
		return $lists;
	}

	/**
	 * Get the availible distribution lists for givens users and groups
	 *
	 * @param array $keys column-name => value(s) pairs, eg. array('list_uid'=>$uid)
	 * @param string $member_attr='contact_uid' null: no members, 'contact_uid', 'contact_id', 'caldav_name' return members as that attribute
	 * @param boolean $limit_in_ab=false if true only return members from the same owners addressbook
	 * @return array with list_id => array(list_id,list_name,list_owner,...) pairs
	 */
	function read_lists($keys,$member_attr=null,$limit_in_ab=false)
	{
		if (!method_exists($this->somain,'get_lists')) return false;

		return $this->somain->get_lists($keys,null,$member_attr,$limit_in_ab);
	}

	/**
	 * Adds / updates a distribution list
	 *
	 * @param string|array $keys list-name or array with column-name => value pairs to specify the list
	 * @param int $owner user- or group-id
	 * @param array $contacts=array() contacts to add (only for not yet existing lists!)
	 * @param array &$data=array() values for keys 'list_uid', 'list_carddav_name', 'list_name'
	 * @return int|boolean integer list_id or false on error
	 */
	function add_list($keys,$owner,$contacts=array(),array &$data=array())
	{
		if (!method_exists($this->somain,'add_list')) return false;

		return $this->somain->add_list($keys,$owner,$contacts,$data);
	}

	/**
	 * Adds contact(s) to a distribution list
	 *
	 * @param int|array $contact contact_id(s)
	 * @param int $list list-id
	 * @param array $existing=null array of existing contact-id(s) of list, to not reread it, eg. array()
	 * @return false on error
	 */
	function add2list($contact,$list,array $existing=null)
	{
		if (!method_exists($this->somain,'add2list')) return false;

		return $this->somain->add2list($contact,$list,$existing);
	}

	/**
	 * Removes one contact from distribution list(s)
	 *
	 * @param int|array $contact contact_id(s)
	 * @param int $list=null list-id or null to remove from all lists
	 * @return false on error
	 */
	function remove_from_list($contact,$list=null)
	{
		if (!method_exists($this->somain,'remove_from_list')) return false;

		return $this->somain->remove_from_list($contact,$list);
	}

	/**
	 * Deletes a distribution list (incl. it's members)
	 *
	 * @param int|array $list list_id(s)
	 * @return number of members deleted or false if list does not exist
	 */
	function delete_list($list)
	{
		if (!method_exists($this->somain,'delete_list')) return false;

		return $this->somain->delete_list($list);
	}

	/**
	 * Read data of a distribution list
	 *
	 * @param int $list list_id
	 * @return array of data or false if list does not exist
	 */
	function read_list($list)
	{
		if (!method_exists($this->somain,'read_list')) return false;

		return $this->somain->read_list($list);
	}

	/**
	 * Check if distribution lists are availible for a given addressbook
	 *
	 * @param int|string $owner='' addressbook (eg. 0 = accounts), default '' = "all" addressbook (uses the main backend)
	 * @return boolean
	 */
	function lists_available($owner='')
	{
		$backend =& $this->get_backend(null,$owner);

		return method_exists($backend,'read_list');
	}

	/**
	 * Get ctag (max list_modified as timestamp) for lists
	 *
	 * @param int|array $owner=null null for all lists user has access too
	 * @return int
	 */
	function lists_ctag($owner=null)
	{
		if (!method_exists($this->somain,'lists_ctag')) return 0;

		return $this->somain->lists_ctag($owner);
	}
}
