<?php
	/**************************************************************************\
	* eGroupWare - LDAP wrapper class for contacts                             *
	* http://www.egroupware.org                                                *
	* Written by Cornelius Weiss <egw@von-und-zu-weiss.de>                     *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.contacts.inc.php');

/**
 * Wrapper class for phpgwapi.contacts_ldap
 * This makes it compatible with vars and parameters of so_sql
 * Maybe one day this becomes a generalized ldap storage object :-)
 *
 * @package contacts
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class so_ldap extends contacts
{
	var $data;
	var $db_data_cols;
	var $db_key_cols;
	
	/**
	 * constructor of the class
	 *
	 */
	function so_ldap()
	{
		parent::contacts();
		$this->db_data_cols = $this->stock_contact_fields + $this->non_contact_fields;
	}

	/**
	 * reads row matched by key and puts all cols in the data array
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($keys,$extra_cols='',$join='')
	{
		$contacts = parent::read_single_entry($keys);
		return $contacts[0];
	}

	/**
	 * saves the content of data to the db
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null)
	{
		$data =& $this->data;
		
		// new contact
		if(empty($this->data[$this->contacts_id]))
		{
			$ret = parent::add($data['owner'],$data,$data['access'],$data['cat_id'],$data['tid']);
		}
		else
		{
			$ret = parent::update($data[$this->contacts_id],$data['owner'],$data);
		}
		return $ret === false ? 1 : 0;
	}

	/**
	 * deletes row representing keys in internal data or the supplied $keys if != null
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null)
	{
		// single entry
		if($keys[$this->contacts_id]) $keys = array( 0 => $keys);

		$ret = 0;
		foreach($keys as $entry)
		{
			if(parent::delete($entry[$this->contacts_id]) === false)
			{
				return 0;
			}
			else
			{
				$ret++;
			}
		}
		return $ret;
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
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		$order_by = explode(',',$order_by);
		$order_by = explode(' ',$order_by);
		$sort = $order_by[0];
		$order = $order_by[1];
		$query = $criteria;
		$fields = $only_keys ? ($only_keys === true ? $this->contacts_id : $only_keys) : '';
		$limit = $need_full_no_count ? 0 : $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
		return parent::read($start,$limit,$fields,$query,$filter,$sort,$order);
	}

}
