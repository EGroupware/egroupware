<?php
	/**************************************************************************\
	* eGroupWare - resources - Resource Management System                      *
	* http://www.egroupware.org                                                *
	* Written by Cornelius Weiss <egw@von-und-zu-weiss.de>                     *
	* and Lukas Weiss <wnz_gh05t@users.sourceforge.net>                        *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id: */
	
class so_resources
{
	function so_resources()
	{
		$this->db = clone($GLOBALS['egw']->db);
		$this->db->set_app('resources');
		$this->rs_table = 'egw_resources';
	}

	/**
	 * searches db for rows matching searchcriteria and categories
	 *
	 * Cornelius Wei� <egw@von-und-zu-weiss.de>
	 * '*' is replaced with sql-wildcard '%'
	 * @param array $criteria array of key => value for search. (or'ed together)
	 * @param array $cats array of cat_id => cat_name to be searched
	 * @param &array $data reference of data array with cols to return in first row ( key => '')
	 * @param int $accessory_of find accessories of id, default -1 = show all exept accessories
	 * @param string $order_by fieldnames + {ASC|DESC} separated by colons ','
	 * @param int $offset row to start from, default 0
	 * @param int $num_rows number of rows to return (optional), default -1 = all, 0 will use $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']
	 * 
	 * @return int number of matching rows
	 */
	function search($criteria,$cats,&$data,$accessory_of=-1,$order_by='',$offset=false,$num_rows=-1)
	{
		$select = implode(',',array_keys($data[0]));
		foreach($criteria as $col => $value)
		{
			$where .= ($where ? " OR " : " ( " ). $col . ((strstr($value,'*') || strstr($value,'*')) ?
				" LIKE '" . strtr(str_replace('_','\\_',addslashes($value)),'*?','%_') ."'": 
				"='" .$value."'");
		}
		$where .= " ) ";
		foreach ((array)$cats as $cat_id => $cat_name)
		{
			$wherecats .= ($wherecats ? " OR " : " AND ( " ) .'cat_id' . "=".(int)$cat_id;
		}
		$wherecats .= $wherecats ? " ) " : "";
		$whereacc = " AND (accessory_of ='".$accessory_of."')";

		$this->db->query( 'SELECT '.$select." FROM ".$this->rs_table." WHERE ".$where.$wherecats.$whereacc.
				($order_by != '' ? " ORDER BY $order_by" : ''),__LINE__,__FILE__);
	
		$nr = $this->db->nf();
		if($offset > 0 && $nr > $offset)
		{
			$this->db->seek($offset-1);
		}
		if($num_rows==0)
		{
			$num_rows = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
		}
		for ($n = 1; $this->db->next_record() && $n!=$num_rows+1; ++$n)
		{
			$data[$n] = $this->db->row();
		}
		unset($data[0]);
		return $nr;
	}
	
	/**
	 * gets the value of $key from resource of $id
	 *
	 * Cornelius Wei� <egw@von-und-zu-weiss.de>
	 * @param string $key key of value to get
	 * @param int $id resource id
	 * @return mixed value of key and resource, false if key or id not found.
	 */
	function get_value($key,$id)
	{
		if($this->db->select($this->rs_table,$key,array('id' => $id),__LINE__,__FILE__))
		{
			$value = $this->db->row(row);
			return $value[$key];
		}
		return false;
	}

	function delete($id)
	{
		$this->db->delete($this->rs_table,$id,__LINE__,__FILE__);
		return true;
	}
	
	/**
	 * reads a resource exept binary datas
	 *
	 * Cornelius Wei� <egw@von-und-zu-weiss.de>
	 * @param int $id resource id
	 * @return array with key => value or false if not found
	 */
	function read($id)
	{
		if($this->db->select($this->rs_table,'*',array('id' => $id),__LINE__,__FILE__))
		{
			return $this->db->row(true);
		}
		return false;
	}

	/**
		 * saves a resource including binary datas
		 *
		 * Cornelius Wei� <egw@von-und-zu-weiss.de>
		 * @param array $resource key => value 
		 * @return mixed id of resource if all right, false if fale
	 */
	function save($resource)
	{
		return $this->db->insert($this->rs_table,$resource,array('id' => $resource['id']),__LINE__,__FILE__) ? $this->db->get_last_insert_id($this->rs_table, 'id') : false;
	}
	
}
