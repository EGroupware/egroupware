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
		$this->db = $GLOBALS['phpgw']->db;
		$this->rs_table = 'egw_resources';
	}

	/*!
	@function search
	@abstract searches db for rows matching searchcriteria and categories
	@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
	@discussion '*' is replaced with sql-wildcard '%'
	@param array $criteria array of key => value for search. (or'ed together)
	@param array $cats array of cat_id => cat_name to be searched
	@param &array $data reference of data array with cols to return in first row ( key => '')
	@param int $accessory_of find accessories of id, default -1 = show all exept accessories
	@param string $order_by fieldnames + {ASC|DESC} separated by colons ','
	@param int $offset row to start from, default 0
	@param int $num_rows number of rows to return (optional), default -1 = all, 0 will use $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs']
	
	@return int number of matching rows
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
			$wherecats .= ($wherecats ? " OR " : " AND ( " ) .'cat_id' . "='".$cat_id."'";
		}
		$wherecats .= $wherecats ? " ) " : "";
		$whereacc = " AND (accessory_of ='".$accessory_of."')";

		$this->db->query( 'SELECT '.$select." FROM ".$this->rs_table." WHERE ".$where.$wherecats.$whereacc.
				($order_by != '' ? " ORDER BY $order_by" : ''),__LINE__,__FILE__);
	
		$nr = $this->db->nf();
		if($offset > 0)
		{
			$this->db->seek($offset-1);
		}
		if($num_rows==0)
		{
			$num_rows = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
		}
		for ($n = 1; $this->db->next_record() && $n!=$num_rows+1; ++$n)
		{
			$data[$n] = $this->db->row();
		}
		unset($data[0]);
		return $nr;
	}
	
	/*!
		@function get_value
		@abstract gets the value of $key from resource of $id
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@param string $key key of value to get
		@param int $id resource id
		@return mixed value of key and resource, false if key or id not found.
	*/
	function get_value($key,$id)
	{
		if($this->db->query( "SELECT ". $key . " FROM ".$this->rs_table." WHERE id = ".$id,__LINE__,__FILE__))
		{
			$this->db->next_record();
			(array)$value = $this->db->row();
			return $value[$key];
		}
		return false;
	}

	function delete($id)
        {
                $this->db->delete($this->rs_table,$id,__LINE__,__FILE__);
                return true;
        }
	
	/*!
		@function read
		@abstract reads a resource exept binary datas
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@param int $id resource id
		@return array with key => value or false if not found
	*/
	function read($id)
	{
		$tabledef = $this->db->metadata($table=$this->rs_table,$full=false);
		foreach($tabledef as $n => $fielddef)
		{
			if(!$fielddef['binary'])
			{
				$readfields[$n] = $fielddef['name'];
			}
		}
		
		if($this->db->query( "SELECT ". implode(',',$readfields) . " FROM ".$this->rs_table." WHERE id = ".$id,__LINE__,__FILE__))
		{
			$this->db->next_record();
			return $this->db->row();
		}
		return false;
	}

	/*!
		@function save
		@abstract saves a resource including binary datas
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@param array $resource key => value 
		@return mixed id of resource if all right, false if fale
	*/
	function save($resource)
	{
		$where = array('id' => $resource['id']);

		$tabledef = $this->db->metadata($table=$this->rs_table,$full=false);
		foreach($tabledef as $n => $fielddef)
		{
			if(isset($resource[$fielddef['name']]))
			{
				$data[$fielddef['name']] = $resource[$fielddef['name']];
			}
			elseif($resource['id'] > 0) //we need to reload old data! bug in db::update?
			{
				$data[$fielddef['name']] = $this->get_value($fielddef['name'],$resource['id']);
			}
		}
		return $this->db->insert($this->rs_table,$data,$where,__LINE__,__FILE__) ? $this->db->get_last_insert_id($this->rs_table, 'id') : false;
	}
	
}
