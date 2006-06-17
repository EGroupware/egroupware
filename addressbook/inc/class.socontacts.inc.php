<?php
/**
 * Addressbook - General storage object
 *
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw-AT-von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de> and Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
 * will only search the accounts and NOT the contacts! Only the filter accounts (owner=0) shows accounts.
 * 
 * If sql-ldap is used as contact-storage (LDAP is managed from eGroupWare) the filter all, searches
 * the accounts in the SQL contacts-table too. Change in made in LDAP, are not detected in that case!
 *
 * @package addressbook
 * @author Cornelius Weiss <egw-AT-von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de> and Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

class socontacts
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
	 * @var int $user
	 */
	var $user;
	
	/**
	 * memberships of the current user
	 * 
	 * @var array
	 */
	var $memberships;
	
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
	 * total number of matches of last search
	 * 
	 * @var int
	 */
	var $total;
	
	/**
	 * storage object: sql (socontacts_sql) or ldap (so_ldap) backend class
	 * 
	 * @var object
	 */
	var $somain;
	/**
	 * storage object for accounts, if not identical to somain (eg. accounts in ldap, contacts in sql)
	 *
	 * @var object
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
	 * @var so_sql-object
	 */
	var $soextra;

	function socontacts($contact_app='addressbook')
	{
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
			$this->somain =& CreateObject('addressbook.so_ldap');

			// static grants from ldap: all rights for the own personal addressbook and the group ones of the meberships
			$this->grants = array($this->user => ~0);
			foreach($this->memberships as $gid)
			{
				$this->grants[$gid] = ~0;
			}
			// LDAP uses a limited set for performance reasons, you NEED an index for that columns, ToDo: make it configurable
			// minimum: $this->columns_to_search = array('n_family','n_given','org_name');
			$this->columns_to_search = array('n_family','n_middle','n_given','org_name','org_unit','adr_one_location','adr_two_location','note');
		}
		else	// sql or sql->ldap
		{
			if ($GLOBALS['egw_info']['server']['contact_repository'] == 'sql-ldap')
			{
				$this->contact_repository = 'sql-ldap';
			}
			$this->somain =& CreateObject('addressbook.socontacts_sql');
			// group grants are now grants for the group addressbook and NOT grants for all its members, therefor the param false!
			$this->grants = $GLOBALS['egw']->acl->get_grants($contact_app,false);
			
			// remove some columns, absolutly not necessary to search in sql
			$this->columns_to_search = array_diff(array_values($this->somain->db_cols),array(
				'jpegphoto','owner','tid','private','id','cat_id',
				'modified','modifier','creator','created','tz','account_id',
			));
		}
		if ($this->account_repository == 'ldap' && $this->contact_repository == 'sql')
		{
			if ($this->account_repository != $this->contact_repository)
			{
				$this->so_accounts =& CreateObject('addressbook.so_ldap');
				$this->so_accounts->contacts_id = 'id';
				$this->account_cols_to_search = array('uid','n_family','n_middle','n_given','org_name','org_unit','adr_one_location','adr_two_location','note');
			}
			else
			{
				$this->account_extra_search = array('uid');
			}
		}
		// add grants for accounts: admin --> everything, everyone --> read
		$this->grants[0] = EGW_ACL_READ;	// everyone read access
		if (isset($GLOBALS['egw_info']['user']['apps']['admin']))	// admin rights can be limited by ACL!
		{
			if (!$GLOBALS['egw']->acl->check('account_access',16,'admin')) $this->grants[0] |= EGW_ACL_EDIT;
			// no add at the moment if (!$GLOBALS['egw']->acl->check('account_access',4,'admin'))  $this->grants[0] |= EGW_ACL_ADD;
			if (!$GLOBALS['egw']->acl->check('account_access',32,'admin')) $this->grants[0] |= EGW_ACL_DELETE;
		} 
		// ToDo: it should be the other way arround, the backend should set the grants it uses
		$this->somain->grants =& $this->grants;

		$this->somain->contacts_id = 'id';
		$this->soextra =& CreateObject('etemplate.so_sql');
		$this->soextra->so_sql('phpgwapi',$this->extra_table);
			
		$custom =& CreateObject('admin.customfields',$contact_app);
		$this->customfields = $custom->get_customfields();
		$this->content_types = $custom->get_content_types();
		if (!$this->content_types)
		{
			$this->content_types = $custom->content_types = array('n' => array(
				'name' => 'contact',
				'options' => array(
					'template' => 'addressbook.edit',
					'icon' => 'navbar.png'
			)));
			$custom->save_repository();
		}
	}
	
	/**
	 * Read all customfields of the given id's
	 *
	 * @param int/array $ids
	 * @return array id => name => value
	 */
	function read_customfields($ids)
	{
		foreach($ids as $key => $id)
		{
			if (!(int)$id) unset($ids[$key]);
		}
		if (!$ids) return array();	// nothing to do, eg. all these contacts are in ldap

		$fields = array();
		foreach((array)$this->soextra->search(array($this->extra_id => $ids),false) as $data)
		{
			if ($data) $fields[$data[$this->extra_id]][$data[$this->extra_key]] = $data[$this->extra_value];
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
	* @return boolean true on success or false on failiure
	*/
	function delete($contact)
	{
		if (is_array($contact)) $contact = $contact['id'];

		// delete mainfields
		if ($this->somain->delete($contact))
		{		
			// delete customfields, can return 0 if there are no customfields
			$this->soextra->delete(array($this->extra_id => $contact));
			
			if ($this->contact_repository == 'sql-ldap')
			{
				if ($contact['account_id'])
				{
					// LDAP uses the uid attributes for the contact-id (dn), 
					// which need to be the account_lid for accounts!
					$contact['id'] = $GLOBALS['egw']->account->id2name($contact['account_id']);
				}
				ExecMethod('addressbook.so_ldap.delete',$contact);
			}
			return true;
		}
		return false;
	}
	
	/**
	* saves contact data including custiom felds
	*
	* @param array &$contact contact data from etemplate::exec
	* @return bool false if all went wrong, errornumber on failure
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
			$this->somain->data = $this->data2db($contact);
			if (!($error_nr = $this->somain->save()))
			{
				$contact['id'] = $this->somain->data['id'];

				if ($this->contact_repository == 'sql-ldap')
				{
					$data = $this->somain->data;
					if ($contact['account_id'])
					{
						// LDAP uses the uid attributes for the contact-id (dn), 
						// which need to be the account_lid for accounts!
						$data['id'] = $GLOBALS['egw']->account->id2name($contact['account_id']);
					}
					ExecMethod('addressbook.so_ldap.save',$data);
				}
			}
		}
		if($error_nr) return $error_nr;
		
		// save customfields
		foreach ((array)$this->customfields as $field => $options)
		{
			if (!isset($contact['#'.$field])) continue;

			$data = array(
				$this->extra_id    => $contact['id'],
				$this->extra_owner => $contact['owner'],
				$this->extra_key   => $field,
			);
			if((string) $contact['#'.$field] === '')	// dont write empty values
			{
				$this->soextra->delete($data);	// just delete them, in case they were previously set
				continue;
			}
			$data[$this->extra_value] =  $contact['#'.$field];
			if (($error_nr = $this->soextra->save($data)))
			{
				return $error_nr;
			}
		}
		return false;	// no error
	}
	
	/**
	 * reads contact data including custom fields
	 *
	 * @param int/string $contact_id contact_id or 'a'.account_id
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($contact_id)
	{
		if (substr($contact_id,0,8) == 'account:' &&
			(!($contact_id = $GLOBALS['egw']->accounts->id2name((int) substr($contact_id,8),'person_id'))))
		{
			return false;
		}
		// read main data
		$backend =& $this->get_backend($contact_id);
		if (!($contact = $backend->read($contact_id)))
		{
			return $contact;
		}
		// read customfields
		$keys = array(
			$this->extra_id => $contact['id'],
			$this->extra_owner => $contact['owner'],
		);
		if ($this->customfields)	// try reading customfields only if we have some
		{
			$customfields = $this->soextra->search($keys,false);
			foreach ((array)$customfields as $field)
			{
				$contact['#'.$field[$this->extra_key]] = $field[$this->extra_value];
			}
		}
		return $this->db2data($contact);
	}
	
	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string $only_keys=true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function ex_search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
 		//echo 'socontacts::search->criteria:'; _debug_array($criteria);
 		// we can only deal with one category atm.
 		$criteria['cat_id'] = $criteria['cat_id'][0];
 		if (empty($criteria['cat_id'])) unset($criteria['cat_id']);
 		
		// We just want to deal with generalized vars, to simpyfie porting of this code to so_sql later...
		$this->main_id = $this->somain->contacts_id;

		// seperate custom fields from main fields
		foreach ($criteria as $crit_key => $crit_val)
		{
			if(!(isset($this->somain->db_data_cols [$crit_key]) || isset($this->somain->db_key_cols [$crit_key])))
			{
				if(strpos($crit_key,'#') !== false && $crit_key{0} != '!' )
				{
					$extra_crit_key = substr($crit_key,1);
					$criteria_extra[$extra_crit_key][$this->extra_key] = $extra_crit_key;
					$criteria_extra[$extra_crit_key][$this->extra_value] = $crit_val;
				}
				unset($criteria[$crit_key]);
			}
		}
		//_debug_array($criteria);
		//_debug_array($criteria_extra);

		// search in custom fields
		$resultextra = array();
		if (count($criteria_extra) >= 1)
		{
			$firstrun = true;
			foreach ((array)$criteria_extra as $extra_crit)
			{
				if($extra_crit[$this->extra_value]{0} == '!')
				{
					if(!isset($all_main_ids)) $all_main_ids = $this->somain->search(array($this->main_id => '*'));
					$extra_crit[$this->extra_value] = substr($extra_crit[$this->extra_value],1);
					$not_result = $this->soextra->search($extra_crit,true,'','',$wildcard);
					if(is_array($not_result))
					{
						$expr = '$not_result[0]';
						for($i=1; $i<count($not_result); $i++)
						{
							$expr .= ',$not_result['.$i.']';
						}
						@eval('$not_result = array_merge_recursive('.$expr.');');
					}
					foreach($all_main_ids as $entry)
					{
						if(array_search($entry[$this->main_id],(array)$not_result[$this->extra_id]) === false)
						{
							$result[] = array(
								$this->extra_id => $entry[$this->main_id],
								$this->extra_key => $extra_crit[$this->extra_key],
							);
						}
					}
				}
				else
				{
					$result = $this->soextra->search($extra_crit,true,'','',$wildcard);
				}

				if ($op == 'OR' && $result)
				{
					$resultextra = array_merge_recursive((array)$result,(array)$resultextra);
				}
				elseif ($op == 'AND')
				{
					if (!$result)
					{
						return false;
						//$resultextra = array();
						//break;
					}
					$expr = '$result[0]';
					for($i=1; $i<count($result); $i++)
					{
						$expr .= ',$result['.$i.']';
					}
					@eval('$merge = array_merge_recursive('.$expr.');');
					if(!is_array($merge[$this->extra_id]))
					{
						$merge[$this->extra_id] = (array)$merge[$this->extra_id];
					}
					if($firstrun)
					{
						$resultextra = $merge[$this->extra_id];
						$firstrun = false;
					}
					else
					{
						$resultextra = array_intersect((array)$resultextra,$merge[$this->extra_id]);
					}
				}
			}
			if($op == 'OR' && $resultextra)
			{
				$expr = '$resultextra[0]';
				for($i=1; $i<count($resultextra); $i++)
				{
					$expr .= ',$resultextra['.$i.']';
				}
				@eval('$merge = array_merge_recursive('.$expr.');');
				$resultextra = array_unique((array)$merge[$this->extra_id]);
			}
		}
		//echo 'socontacts::search->resultextra:'; _debug_array($resultextra);
		
		// search in main fields
		$result = array();
		// include results from extrafieldsearch
		if(!empty($resultextra))
		{
			$criteria[$this->main_id] = $resultextra;
		}
		if (count($criteria) >= 0)	// RB-CHANGED was 1
		{
			// We do have to apply wildcard by hand, as the result-ids of extrasearch are included in this search
			if($wildcard)
			{
				foreach ($criteria as $field => $value)
				{
					if ($field == $this->main_id) continue;
					$criteria[$field] = '*'.$value.'*';
				}
			}
			$result = $this->somain->search($criteria,true,$order_by,$extra_cols,false,$empty,$op,false,$filter);
			if(!is_array($result)) return false;
			$expr = '$result[0]';
			for($i=1; $i<count($result); $i++)
			{
				$expr .= ',$result['.$i.']';
			}
			@eval('$merge = array_merge_recursive('.$expr.');');
			$result = ($merge[$this->main_id]);
		}
		//echo 'socontacts::search->result:'; _debug_array($result);

		if(count($result) == 0) return false;
		if(!is_bool($only_keys_main = $only_keys))
		{
			$keys_wanted = explode(',',$only_keys);
			foreach ($keys_wanted as $num => $key_wanted)
			{
				if(!(isset($this->somain->db_data_cols [$key_wanted]) || isset($this->somain->db_key_cols [$key_wanted])))
				{
					unset($keys_wanted[$num]);
					$keys_wanted_custom[] = $key_wanted;
				}
			}
			$only_keys_main = implode(',',$keys_wanted);
		}
		$result = $this->somain->search(array($this->main_id => $result),$only_keys_main,$order_by,$extra_cols,'','','OR',$start,$filter,$join,$need_full_no_count);
		
		// append custom fields for each row
		if($only_keys === false || is_array($keys_wanted_custom))
		{
			foreach ($result as $num => $contact)
			{
				$extras = $this->soextra->search(array($this->extra_id => $contact[$this->main_id]),false);
				foreach ((array)$extras as $extra)
				{
					if ($only_keys === false || in_array($extra[$this->extra_key],$keys_wanted_custom))
					{
						$result[$num][$extra[$this->extra_key]] = $extra[$this->extra_value];
					}
				}
			}
		}
		foreach($result as $num => $contact)
		{
			$result[$num] = $this->db2data($contact);
		}
		return $need_full_no_count ? count($result) : $result;
	}
	
	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array/string $criteria array of key and data cols, OR string to search over all standard search fields
	 * @param boolean/string $only_keys=true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
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
		//echo "<p>socontacts::search(".print_r($criteria,true).",'$only_keys','$order_by','$extra_cols','$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";

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
			// search the customfields only if some exist, but only for sql!
			if (get_class($backend) == 'socontacts_sql' && $this->customfields)
			{
				$cols[] = $this->extra_value;
			}
			foreach($cols as $col)
			{
				$criteria[$col] = $search;
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
			foreach($this->columns_to_search as $col)
			{
				if ($col != 'contact_value') $param['search'][$col] = $search;	// we dont search the customfields
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

		list(,$by) = explode(',',$param['org_view']);
		if (!$by) $by = 'adr_one_locality';

		foreach($rows as $n => $row)
		{
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
					$rows[$n]['id'] .= '|||'.$by.':'.$row[$by];
				}
			}
		}
		return $rows;
	}

 	/**
	 * gets all contact fields from database
	 */
	function get_contact_columns()
	{
		$fields = $this->somain->db_data_cols;
		foreach ((array)$this->customfields as $cfield => $coptions)
		{
			$fields['#'.$cfield] = '#'.$cfield;
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
			$this->soextra->delete(array($this->extra_owner => $account_id));
		}
		else
		{
			$this->somain->change_owner($account_id,$new_owner);
			$this->soextra->db->update($this->soextra->table_name,array(
				$this->extra_owner => $new_owner
			),array(
				$this->extra_owner => $account_id
			),__LINE__,__FILE__);
		}
	}
	
	/**
	 * return the backend, to be used for the given $contact_id
	 *
	 * @param mixed $contact_id=null
	 * @param int $owner=null account_id of owner or 0 for accounts
	 * @return object
	 */
	function &get_backend($contact_id=null,$owner=null)
	{
		if ($this->contact_repository != $this->account_repository && is_object($this->so_accounts) &&
			(!is_null($owner) && !$owner || !is_null($contact_id) &&
			($this->contact_repository == 'sql' && !is_numeric($contact_id) ||
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
		$def = $this->soextra->db->get_table_definitions('phpgwapi','egw_addressbook');
		
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
}
