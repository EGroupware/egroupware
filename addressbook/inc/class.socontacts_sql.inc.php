<?php
/**************************************************************************\
* eGroupWare - Adressbook - SQL storage object                             *
* http://www.egroupware.org                                                *
* Written and (c) 2006 by  Ralf Becker <RalfBecker-AT-outdoor-training.de> *
* ------------------------------------------------------------------------ *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql.inc.php');

/**
 * SQL storage object of the adressbook
 *
 * @package addressbook
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

class socontacts_sql extends so_sql 
{
	var $accounts_table = 'egw_accounts';
	var $accounts_join = ' JOIN egw_accounts ON person_id=egw_addressbook.contact_id';
	var $extra_join = ' LEFT JOIN egw_addressbook_extra ON egw_addressbook.contact_id=egw_addressbook_extra.contact_id';
	
	/**
	 * Query organisations by given parameters
	 *
	 * @var array $param
	 * @var string $param[org_view] 'org_name', 'org_name,adr_one_location', 'org_name,org_unit' how to group
	 * @var int $param[owner] addressbook to search
	 * @var string $param[search] search pattern for org_name
	 * @var string $param[searchletter] letter the org_name need to start with
	 * @var array $param[col_filter] filter
	 * @var string $param[search] or'ed search pattern
	 * @var int $param[start]
	 * @var int $param[num_rows]
	 * @var string $param[sort] ASC or DESC
	 * @return array or arrays with keys org_name,count and evtl. adr_one_location or org_unit
	 */ 
	function organisations($param)
	{
		$filter = is_array($param['col_filter']) ? $param['col_filter'] : array();

		// fix cat_id filter to search in comma-separated multiple cats and return subcats
		if ((int)$filter['cat_id'])
		{
			$filter[] = $this->_cat_filter($filter['cat_id']);
			unset($filter['cat_id']);
		}
		// add filter for read ACL in sql, if user is NOT the owner of the addressbook
		if ($param['owner'] && $param['owner'] == $GLOBALS['egw_info']['user']['account_id'])
		{
			$filter['owner'] = $param['owner'];
		}
		else
		{
			// we have no private grants in addressbook at the moment, they have then to be added here too
			if ($param['owner'])
			{
				if (!$this->grants[(int) $filter['owner']]) return false;	// we have no access to that addressbook
				
				$filter['owner'] = $param['owner'];
				$filter['private'] = 0;
			}
			else	// search all addressbooks, incl. accounts
			{
				$filter[] = "(contact_owner=".(int)$GLOBALS['egw_info']['user']['account_id'].
					" OR contact_private=0 AND contact_owner IN (".
					implode(',',array_keys($this->grants))."))";
			}
		}
		if ($param['searchletter'])
		{
			$filter[] = 'org_name LIKE '.$this->db->quote($param['searchletter'].'%');
		}
		else
		{
			$filter[] = "org_name != ''";// AND org_name IS NOT NULL";
		}
		$sort = $param['sort'] == 'DESC' ? 'DESC' : 'ASC';

		list(,$by) = explode(',',$param['org_view']);
		if (!$by)
		{
			$extra = array(
				'COUNT(org_name) AS org_count',
				"COUNT(DISTINCT CASE WHEN org_unit IS NULL THEN '' ELSE org_unit END) AS org_unit_count",
				"COUNT(DISTINCT CASE WHEN adr_one_locality IS NULL THEN '' ELSE adr_one_locality END) AS adr_one_locality_count",
			);
			$append = "GROUP BY org_name ORDER BY org_name $sort";
		}
		else	// by adr_one_location or org_unit
		{
			// org total for more then one $by
			$append = "GROUP BY org_name HAVING {$by}_count > 1 ORDER BY org_name $sort";
			parent::search($param['search'],array('org_name'),$append,array(
				"NULL AS $by",
				'COUNT(org_name) AS org_count',
				"COUNT(DISTINCT CASE WHEN org_unit IS NULL THEN '' ELSE org_unit END) AS org_unit_count",
				"COUNT(DISTINCT CASE WHEN adr_one_locality IS NULL THEN '' ELSE adr_one_locality END) AS adr_one_locality_count",
			),'%',false,'OR','UNION',$filter);
			// org by location
			$append = "GROUP BY org_name,$by ORDER BY org_name $sort,$by $sort";
			parent::search($param['search'],array('org_name'),$append,array(
				"CASE WHEN $by IS NULL THEN '' ELSE $by END AS $by",
				'COUNT(org_name) AS org_count',
				"COUNT(DISTINCT CASE WHEN org_unit IS NULL THEN '' ELSE org_unit END) AS org_unit_count",
				"COUNT(DISTINCT CASE WHEN adr_one_locality IS NULL THEN '' ELSE adr_one_locality END) AS adr_one_locality_count",
			),'%',false,'OR','UNION',$filter);
			$append = "ORDER BY org_name $sort,CASE WHEN $by IS NULL THEN 1 ELSE 2 END,$by $sort";
		}
		$rows = parent::search($param['search'],array('org_name'),$append,$extra,'%',false,'OR',
			array($param['start'],$param['num_rows']),$filter);
			
		if (!$rows) return false;

		// query the values for *_count == 1, to display them instead
		$filter['org_name'] = $orgs = array();
		foreach($rows as $n => $row)
		{
			if ($row['org_unit_count'] == 1 || $row['adr_one_locality_count'] == 1)
			{
				$filter['org_name'][$row['org_name']] = $row['org_name'];	// use as key too to have every org only once
			}
			$org_key = $row['org_name'].($by ? '|||'.($row[$by] || $row[$by.'_count']==1 ? $row[$by] : '|||') : '');
			$orgs[$org_key] = $row; 
		}
		unset($rows);
		
		if (count($filter['org_name']))
		{
			foreach((array) parent::search($criteria,array('org_name','org_unit','adr_one_locality'),'GROUP BY org_name,org_unit,adr_one_locality',
				'','%',false,'AND',false,$filter) as $row)
			{
				$org_key = $row['org_name'].($by ? '|||'.$row[$by] : '');
				if ($orgs[$org_key]['org_unit_count'] == 1)
				{
					$orgs[$org_key]['org_unit'] = $row['org_unit'];
				}
				if ($orgs[$org_key]['adr_one_locality_count'] == 1)
				{
					$orgs[$org_key]['adr_one_locality'] = $row['adr_one_locality'];
				}
				if ($by && isset($orgs[$org_key = $row['org_name'].'||||||']))
				{
					if ($orgs[$org_key]['org_unit_count'] == 1)
					{
						$orgs[$org_key]['org_unit'] = $row['org_unit'];
					}
					if ($orgs[$org_key]['adr_one_locality_count'] == 1)
					{
						$orgs[$org_key]['adr_one_locality'] = $row['adr_one_locality'];
					}
				}
			}
		}
		return array_values($orgs);
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * For a union-query you call search for each query with $start=='UNION' and one more with only $order_by and $start set to run the union-query.
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string/array $only_keys=true True returns only keys, False returns all cols. or 
	 *	comma seperated list or array of columns to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	 * @return boolean/array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		if ((int) $this->debug >= 4) echo "<p>socontacts_sql::search(".print_r($criteria,true).",".print_r($only_keys,true).",'$order_by','$extra_cols','$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";
		
		$owner = isset($filter['owner']) ? $filter['owner'] : (isset($criteria['owner']) ? $criteria['owner'] : null);

		// fix cat_id filter to search in comma-separated multiple cats and return subcats
		if ((int)$filter['cat_id'])
		{
			$filter[] = $this->_cat_filter($filter['cat_id']);
			unset($filter['cat_id']);
		}
		// add filter for read ACL in sql, if user is NOT the owner of the addressbook
		if (!(isset($filter['owner']) && $filter['owner'] == $GLOBALS['egw_info']['user']['account_id']))
		{
			// we have no private grants in addressbook at the moment, they have then to be added here too
			if (isset($filter['owner']))
			{
				if (!$this->grants[(int) $filter['owner']]) return false;	// we have no access to that addressbook
				
				$filter['private'] = 0;
			}
			else	// search all addressbooks, incl. accounts
			{
				$filter[] = "($this->table_name.contact_owner=".(int)$GLOBALS['egw_info']['user']['account_id'].
					" OR contact_private=0 AND $this->table_name.contact_owner IN (".
					implode(',',array_keys($this->grants)).") OR $this->table_name.contact_owner IS NULL)";
			}
		}	
		if (!$owner)	// owner not set (=all) or 0 --> include accounts
		{
			if (!is_array($extra_cols)) $extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
			$accounts2contacts = array(
				'contact_id'      => "CASE WHEN $this->table_name.contact_id IS NULL THEN ".$this->db->concat("'account:'",'account_id').
					" ELSE $this->table_name.contact_id END AS contact_id",
				'contact_owner'   => "CASE WHEN $this->table_name.contact_owner IS NULL THEN 0 ELSE $this->table_name.contact_owner END AS contact_owner",
				'contact_tid'     => 'CASE WHEN contact_tid IS NULL THEN \'n\' ELSE contact_tid END AS contact_tid',
				'n_family'        => 'CASE WHEN n_family IS NULL THEN account_lastname ELSE n_family END AS n_family',
				'n_given'         => 'CASE WHEN n_given IS NULL THEN account_firstname ELSE n_given END AS n_given',
				'contact_email'   => 'CASE WHEN contact_email IS NULL THEN account_email ELSE contact_email END AS contact_email',
			);
			$extra_cols = $extra_cols ? array_merge(is_array($extra_cols) ? $extra_cols : implode(',',$extra_cols),array_values($accounts2contacts)) :
				array_values($accounts2contacts);

			// we need to remove the above columns from the select list, as they are added again via extra_cols and 
			// having them double is ambigues
			if (!$only_keys)
			{
				$only_keys = array_diff(array_keys($this->db_cols),array_keys($accounts2contacts));
			}
			elseif($only_keys !== true)
			{
				if (!is_array($only_keys)) $only_keys = explode(',',$only_keys);

				foreach(array_keys($accounts2contacts) as $col)
				{
					if (($key = array_search($col,$only_keys)) !== false ||
						($key = array_search(str_replace('contact_','',$col),$only_keys)) !== false)
					{
						unset($only_keys[$key]);
					}
				}						
			}
			foreach($filter as $col => $value)
			{
				if (!is_int($col) && ($db_col = array_search($col,$this->db_cols)) !== false)
				{
					if (isset($accounts2contacts[$db_col]))
					{
						unset($filter[$col]);
						$filter[] = str_replace(' AS '.$db_col,'',$accounts2contacts[$db_col]).
							($value === "!''" ? "!=''" : '='.$this->db->quote($value,$this->table_def['fd'][$db_col]['type']));
					}
					elseif($value === "!''")		// not empty query, will match all accounts, as their value is NULL not ''
					{
						unset($filter[$col]);
						$filter[] = "($db_col != '' AND $db_col IS NOT NULL)";
					}
				}
				elseif (preg_match("/^([a-z0-9_]+) *(=|!=|LIKE|NOT LIKE|=!) *'(.*)'\$/i",$value,$matches))
				{
					if (($db_col = array_search($matches[1],$this->db_cols)) !== false && isset($accounts2contacts[$db_col]))
					{
						if ($matches[2] == '=!') $matches[2] = '!=';
						$filter[$col] = str_replace(' AS '.$db_col,'',$accounts2contacts[$db_col]).' '.$matches[2].' \''.$matches[3].'\'';
					}
				}
			}
			// dont list groups
			$filter[] = "(account_type != 'g' OR account_type IS NULL)";
		}
		if ($criteria['contact_value'])	// search the custom-fields
		{
			$join .= $this->extra_join;
			if (is_string($only_keys)) $only_keys = 'DISTINCT '.str_replace(array('contact_id','contact_owner'),
				array($this->table_name.'.contact_id',$this->table_name.'.contact_owner'),$only_keys);
				
			if (isset($filter['owner']))
			{
				$filter[] = $this->table_name.'.contact_owner='.(int)$filter['owner'];
				unset($filter['owner']);
			}
		}
		if (is_null($owner))	// search for accounts AND contacts of all addressbooks
		{
			/* only enable that after testing with postgres, I dont want to break more postgres stuff ;-)
			if ($this->db->capabilities['outer_join'])
			{
				$join = 'OUTER'.$this->accounts_join.' '.$join;
			}
			else */ // simulate the outer join with a union
			{
				parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,'UNION',$filter,
					'LEFT'.$this->accounts_join.$join,$need_full_no_count);
				$filter[] = '(person_id=0 OR person_id IS NULL)';	// unfortunally both is used in eGW
				parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,'UNION',$filter,
					'RIGHT'.$this->accounts_join.$join,$need_full_no_count);
			}
		}
		elseif (!$owner)		// search for accounts only
		{
			$join = ' RIGHT'.$this->accounts_join.$join;
			$filter[] =  "account_type='u'";	// no groups
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
	}
	
	/**
	 * fix cat_id filter to search in comma-separated multiple cats and return subcats
	 * 
	 * @internal 
	 * @param int $cat_id
	 * @return string sql to filter by given cat
	 */
	function _cat_filter($cat_id)
	{
		if (!is_object($GLOBALS['egw']->categories))
		{
			$GLOBALS['egw']->categories = CreateObject('phpgwapi.categories');
		}
		foreach($GLOBALS['egw']->categories->return_all_children((int)$cat_id) as $cat)
		{
			$cat_filter[] = $this->db->concat("','",cat_id,"','")." LIKE '%,$cat,%'";
		}
		return '('.implode(' OR ',$cat_filter).')';
	}
	
	/**
	 * reads contact data including custom fields
	 *
	 * reimplemented to read/convert accounts and return the account_id for them
	 *
	 * @param integer/string $contact_id contact_id or 'account:'.account_id
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($contact_id)
	{
		//echo "<p>socontacts_sql::read($contact_id)</p>\n";
		if (substr($contact_id,0,8) == 'account:')
		{
			$account_id = (int) substr($contact_id,8);
			
			if (!$GLOBALS['egw']->accounts->exists($account_id)) return false;	// account does not exist
			
			// check if the account is already linked with a contact, if not create one with the content of the account
			if (!($contact_id = $GLOBALS['egw']->accounts->id2name($account_id,'person_id')) &&
				!($matching_contact_id = $this->_find_unique_contact(
					$GLOBALS['egw']->accounts->id2name($account_id,'account_firstname'),
					$GLOBALS['egw']->accounts->id2name($account_id,'account_lastname'))))
			{
				// as the account object has no function to just read a record and NOT override it's internal data,
				// we have to instanciate a new object and can NOT use $GLOBALS['egw']->accounts !!!
				$account =& new accounts($account_id,'u');
				$account->read();
				
				if (!$account->data['account_id']) return false;	// account not found
				
				$this->init();
				$this->save(array(
					'n_family' => $account->data['lastname'],
					'n_given'  => $account->data['firstname'],
					'n_fn'     => $account->data['firstname'].' '.$account->data['lastname'],
					'n_fileas' => $account->data['lastname'].', '.$account->data['firstname'],
					'email'    => $account->data['email'],
					'owner'    => 0,
					'tid'      => 'n',
					'creator'  => $GLOBALS['egw_info']['user']['account_id'],
					'created'  => time(),
					'modifier' => $GLOBALS['egw_info']['user']['account_id'],
					'modified' => time(),
				),$account_id);
	
				return $this->data+array('account_id' => $account_id);
			}
			elseif ($matching_contact_id)
			{
				//echo "<p>socontacts_sql($contact_id) account_id=$account_id, matching_contact_id=$matching_contact_id</p>\n";
				$contact = parent::read($matching_contact_id);
				$contact['owner'] = 0;
				$this->save($contact,$account_id);

				return $this->data+array('account_id' => $account_id);
			}
			//echo "<p>socontacts_sql::read() account_id='$account_id', contact_id='$contact_id'</p>\n"; exit;
		}
		if (($contact = parent::read($contact_id)) && !$contact['owner'])	// return account_id for accounts
		{
			$contact['account_id'] = $GLOBALS['egw']->accounts->name2id($contact_id,'person_id');
		}
		return $contact;
	}
	
	function _find_unique_contact($firstname,$lastname)
	{
		$contacts =& $this->search(array(
			'contact_owner != 0',
			'n_given'  => $firstname,
			'n_family' => $lastname,
			'private'  => 0,
		));

		return $contacts && count($contacts) == 1 ? $contacts[0]['id'] : false;
	}

	/**
	 * saves the content of data to the db
	 *
	 * reimplemented to write for accounts some of the data to the account too and link it with the contact
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @param int $account_id if called by read account_id of account to save/link with contact (we dont overwrite the account-data in that case),
	 *	otherwise we try getting it by accounts::name2id('person_id',$contact_id)
	 * @return int 0 on success and errno != 0 else
	 */
	function save($data=null,$account_id=0)
	{
		$this->data_merge($data);

		// if called by read's automatic conversation --> dont change the email of the account (if set)
		if (!$this->data['owner'] && $account_id &&	
			($email = $GLOBALS['egw']->accounts->id2name($account_id,'account_email')) && $data['email'] != $email)
		{
			if (!$data['email_home']) $data['email_home'] = $data['email'];
			$data['email'] = $email;
		}
		if (!($error = parent::save()) && !$this->data['owner'])	// successfully saved an account --> sync our data in the account-table
		{
			if (!$account_id && !($account_id = $GLOBALS['egw']->accounts->name2id($this->data['id'],'person_id')) &&
				// try find a matching account for migration
				!($account_id = $GLOBALS['egw']->accounts->name2id($this->data['n_given'].' '.$this->data['n_family'],'account_fullname')))
			{
				// ToDo create new account
			}
			// as the account object has no function to just read a record and NOT override it's internal data,
			// we have to instanciate a new object and can NOT use $GLOBALS['egw']->accounts !!!
			$account =& new accounts($account_id,'u');
			$account->read_repository();
			
			if (!$account->data['account_id']) return false;	// account not found
			
			foreach(array(
				'n_family'  => 'lastname',
				'n_given'   => 'firstname',
				'email'     => 'email',
				'id'        => 'person_id',
			) as $c_name => $a_name)
			{
				$account->data[$a_name] = $this->data[$c_name];
			}
			$account->save_repository();
		}
		return $error;
	}
}