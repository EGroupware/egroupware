<?php
/**************************************************************************\
* eGroupWare - Adressbook - General storage object                         *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Cornelius_weiss <egw@von-und-zu-weiss.de>        *
* and Ralf Becker <RalfBecker-AT-outdoor-training.de>                      *
* ------------------------------------------------------------------------ *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

/**
 * General storage object of the adressbook
 *
 * @package addressbook
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de> and Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

class socontacts
{
	/**
	 * @var string $links_table table name 'egw_links'
	 */
	var $links_table = 'egw_links';
	
	/**
	 * @var string $extra_table name of customefields table
	 */
	var $extra_table = 'egw_addressbook_extra';
	
	/**
	* @var string $extra_id
	*/
	var $extra_id = 'contact_id';
	
	/**
	* @var string $extra_owner
	*/
	var $extra_owner = 'contact_owner';

	/**
	* @var string $extra_key
	*/
	var $extra_key = 'contact_name';
	
	/**
	* @var string $extra_value
	*/
	var $extra_value = 'contact_value';
	
	/**
	 * @var string $contacts_repository 'sql' or 'ldap'
	 */
	var $contacts_repository = 'sql';
	
	/**
	 * @var array $grants account_id => rights pairs
	 */
	var $grants;

	/**
	* @var int $user userid of current user
	*/
	var $user;
	
	/**
	 * @var array $memberships of the current user
	 */
	var $memberships;
	
	/**
	 * @var array $columns_to_search when we search for a single pattern
	 */
	var $columns_to_search = array();
	/**
	 * @var array $account_extra_search extra columns to search if accounts are included, eg. account_lid
	 */
	var $account_extra_search = array();
	
	/**
	 * @var array $customfields name => array(...) pairs
	 */
	var $customfields = array();
	/**
	 * @var array $content_types name => array(...) pairs
	 */
	var $content_types = array();
	
	function socontacts($contact_app='addressbook')
	{
		$this->user = $GLOBALS['egw_info']['user']['account_id'];
		foreach($GLOBALS['egw']->accounts->membership($this->user) as $group)
		{
			$this->memberships[] = $group['account_id'];
		}

		if($GLOBALS['egw_info']['server']['contact_repository'] == 'ldap')
		{
			$this->contact_repository = 'ldap';
			$this->somain =& CreateObject('addressbook.so_'.$this->contact_repository);

			// static grants from ldap: all rights for the own personal addressbook and the group ones of the meberships
			$this->grants = array($this->user => ~0);
			foreach($this->memberships as $gid)
			{
				$this->grants[$gid] = ~0;
			}
			// LDAP uses a limited set for performance reasons, you NEED an index for that columns, ToDo: make it configurable
			// minimum: $this->columns_to_search = array('n_family','n_given','org_name');
			$this->columns_to_search = array('n_family','n_middle','n_given','org_name','org_unit','adr_one_location','adr_two_location','note');
			$this->account_extra_search = array('uid');
		}
		else
		{
			$this->somain =& CreateObject('addressbook.socontacts_sql','addressbook','egw_addressbook',null,'contact_');
			// group grants are now grants for the group addressbook and NOT grants for all its members, therefor the param false!
			$this->grants = $GLOBALS['egw']->acl->get_grants($contact_app,false);
			
			// remove some columns, absolutly not necessary to search in sql
			$this->columns_to_search = array_diff(array_values($this->somain->db_cols),array('jpegphoto','owner','tid','private','id','cat_id','modified','modifier','creator','created'));
			$this->account_extra_search = array('account_firstname','account_lastname','account_email','account_lid');
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

		$this->total =& $this->somain->total;
		$this->somain->contacts_id = 'id';
		$this->soextra =& CreateObject('etemplate.so_sql');
		$this->soextra->so_sql('addressbook',$this->extra_table);
			
		$custom =& CreateObject('admin.customfields',$contact_app);
		$this->customfields = $custom->get_customfields();
		if ($this->customfields && !is_array($this->customfields)) $this->customfields = unserialize($this->customfields);
		if (!$this->customfields) $this->customfields = array();
		$this->content_types = $custom->get_content_types();
		if ($this->content_types && !is_array($this->content_types)) $this->content_types = unserialize($this->content_types);
	}
	
	/**
	 * Read all customfields of the given id's
	 *
	 * @param int/array $ids
	 * @return array id => name => value
	 */
	function read_customfields($ids)
	{
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
		// do the necessare changes here

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
		// do the necessary changes here

		return $data;
	}

	/**
	* deletes contact entry including custom fields
	*
	* @param mixed $contact array with key id or just the id
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
		$this->somain->data = $this->data2db($contact);
		$error_nr = $this->somain->save();
		$contact['id'] = $this->somain->data['id'];
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
	 * @param interger/string $contact_id contact_id or 'a'.account_id
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($contact_id)
	{
		// read main data
		if (!($contact = $this->somain->read($contact_id)))
		{
			return $contact;
		}
		// read customfields
		$keys = array(
			$this->extra_id => $contact['id'],
			$this->extra_owner => $contact['owner'],
		);
		$customfields = $this->soextra->search($keys,false);
		foreach ((array)$customfields as $field)
		{
			$contact['#'.$field[$this->extra_key]] = $field[$this->extra_value];
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
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
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
	function &regular_search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		//echo "<p>socontacts::search(".print_r($criteria,true).",'$only_keys','$order_by','$extra_cols','$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";

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
		$rows =& $this->somain->search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
		
		if ($rows)
		{
			foreach($rows as $n => $row)
			{
				$rows[$n] = $this->db2data($row);
			}
		}
		// ToDo: read custom-fields, if displayed in the index page
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
}
