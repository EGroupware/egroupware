<?php
/**************************************************************************\
* eGroupWare - Adressbook - General storage object                         *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Cornelius_weiss <egw@von-und-zu-weiss.de>        *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

/**
 * General storage object of the adressbook
 *
 * @package adressbook
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @copyright (c) 2005 by Cornelius Weiss <egw@von-und-zu-weiss.de>
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
	var $extra_table = 'phpgw_addressbook_extra';
	
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
	
	function socontacts($contact_app='addressbook')
	{
		if($GLOBALS['egw_info']['server']['contact_repository'] == 'sql' || !isset($GLOBALS['egw_info']['server']['contact_repository']))
		{
			$this->somain = CreateObject('etemplate.so_sql');
			$this->somain->so_sql('phpgwapi','phpgw_addressbook');
		}
		else
		{
			$this->somain = CreateObject('addressbook.so_'.$GLOBALS['egw_info']['server']['contact_repository']);
		}
		$this->somain->contacts_id = 'id';
		$this->soextra = CreateObject('etemplate.so_sql');
		$this->soextra->so_sql('phpgwapi',$this->extra_table);
			
		$custom =& CreateObject('admin.customfields',$contact_app);
		$this->customfields = $custom->get_customfields();

	}
	
	/**
	* deletes contact entry including custom fields
	*
	* @param array &$contact contact data from etemplate::exec
	* @return bool false if all went right
	*/
	function delete(&$contact)
	{
		// delete mainfields
		$ok_main = $this->somain->delete(array('id' => $contact['id']));
		
		// delete customfields
		$ok_extra = $this->soextra->delete(array($this->extra_id => $contact['id']));
		return !((bool)$ok_extra & (bool)$ok);
		
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
		$this->somain->data = $contact;
		$error_nr = $this->somain->save();
		$contact['id'] = $this->somain->data['id'];
		if($error_nr) return $error_nr_main;
		
		// save customfields
		foreach ($this->customfields as $field => $options)
		{
			$value = $contact['#'.$field];
			$data = array(
				$this->extra_id => $contact['id'],
				$this->extra_owner => $contact['owner'],
				$this->extra_key => $field,
				$this->extra_value => $value,
			);
			$this->soextra->data = $data;
			$error_nr = $this->soextra->save();
			if($error_nr) return $error_nr;
		}
		return;
	}
	
	/**
	 * reads contact data including custom fields
	 *
	 * @param interger $contact_id contact_id
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($contact_id)
	{
		// read main data
		$contact = $this->somain->read($contact_id);
		
		// read customfilds
		$keys = array(
			$this->extra_id => $contact['id'],
			$this->extra_owner => $contact['owner'],
		);
		$customfields = $this->soextra->search($keys,false);
		foreach ((array)$customfields as $field)
		{
			$contact['#'.$field[$this->extra_key]] = $field[$this->extra_value];
		}
		return $contact;
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
 		// echo 'socontacts::search->criteria:'; _debug_array($criteria);
		// We just want to deal with generalized vars, to simpyfie porting of this code to so_sql later...
		$this->main_id = $this->somain->contacts_id;
		
		// seperate custom fields from main fields
		foreach ($criteria as $crit_key => $crit_val)
		{
			if(!(isset($this->somain->db_data_cols [$crit_key]) || isset($this->somain->db_key_cols [$crit_key])))
			{
				if(strpos($crit_key,'#') !== false)
				{
					$extra_crit_key = substr($crit_key,1);
					$criteria_extra[$extra_crit_key][$this->extra_key] = $extra_crit_key;
					$criteria_extra[$extra_crit_key][$this->extra_value] = $extra_crit_val;
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
				$result = $this->soextra->search($extra_crit,true,'','',$wildcard);
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
					eval('$merge = array_merge_recursive('.$expr.');');
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
				eval('$merge = array_merge_recursive('.$expr.');');
				$resultextra = array_unique($merge[$this->extra_id]);
			}
		}
// 		_debug_array($resultextra);
		
		// search in main fields
		$result = array();
		// include results from extrafieldsearch
		if(!empty($resultextra))
		{
			$criteria[$this->main_id] = $resultextra;
		}
		if (count($criteria) >= 1)
		{
			$result = $this->somain->search($criteria,true,$order_by,$extra_cols,$wildcard,$empty,$op,false,$filter);
			if(!is_array($result)) return false;
			$expr = '$result[0]';
			for($i=1; $i<count($result); $i++)
			{
				$expr .= ',$result['.$i.']';
			}
			eval('$merge = array_merge_recursive('.$expr.');');
			$result = ($merge[$this->main_id]);
		}
// 		_debug_array($result);

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
		return $need_full_no_count ? count($result) : $result;
	}
}
