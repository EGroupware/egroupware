<?php
	/**************************************************************************\
	* eGroupWare - resources - Resource Management System                      *
	* http://www.egroupware.org                                                *
	* Written by Cornelius Weiss [nelius@gmx.net]                              *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
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
	@discussion '*' is replaced with sql-wildcard '%'

	@param array $criteria array of key => value for search. (or'ed together)
	@param array $cats array of cat_id => cat_name to be searched
	@param &array $data reference of data array with cols to return in first row ( key => '')
	@param string $order_by fieldnames + {ASC|DESC} separated by colons ','
	@param int $offset row to start from, default 0
	@param int $num_rows number of rows to return (optional), default -1 = all, 0 will use $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs']
	
	@return int number of matching rows
	*/
	function search($criteria,$cats,&$data,$order_by='',$offset=false,$num_rows=-1)
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

		$this->db->query( 'SELECT '.$select." FROM ".$this->rs_table." WHERE ".$where.$wherecats.
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
		return $nr;
	}
	
	/*!
		@function get_value
		@abstract gets the value of $key from resource of $id
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
	
	/*!
		@function read
		@abstract reads a resource exept binary datas
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
		@param array $resource key => value 
		@return array with key => value or false if not found
	*/
	function save($resource)
	{
		$where = array('id' => $resource['id']);
		$data = array(	'name' 			=> $resource['name'],
				'cat_id'		=> $resource['cat_id'],
				'short_description'	=> $resource['short_description'],
				'long_description'	=> $resource['long_description'],
				'location'		=> $resource['location'],
				'quantity'		=> $resource['quantity'],
				'useable'		=> $resource['useable'],
				'bookable'		=> $resource['bookable'],
				'buyable'		=> $resource['buyable'],
				'prize'			=> $resource['prize'],
				'accessories'		=> $resource['accessories'],
				'picture_src'		=> $resource['picture_src'],
				'picture_thumb'		=> $resource['picture_thumb'],
				'picture'		=> $resource['picture']
		);
		return $this->db->insert($this->rs_table,$data,$where,__LINE__,__FILE__) ? true : false;
	}
	
}
