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
	
	/* $Id$ */
	
include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql.inc.php');
	
class so_resources extends so_sql
{
	function so_resources()
	{
		$this->so_sql('resources','egw_resources');
	}

	/**
	 * gets the value of $key from resource of $id
	 *
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param string $key key of value to get
	 * @param int $id resource id
	 * @return mixed value of key and resource, false if key or id not found.
	 */
	function get_value($key,$id)
	{
		if($this->db->select($this->table_name,$key,array('id' => $id),__LINE__,__FILE__))
		{
			$value = $this->db->row(row);
			return $value[$key];
		}
		return false;
	}

	/**
	* saves a resource including binary datas
	*
	* Cornelius Weiss <egw@von-und-zu-weiss.de>
	* @param array $resource key => value 
	* @return mixed id of resource if all right, false if fale
	 */
	function save($resource)
	{
		$this->data = $resource;
		return  parent::save() == 0 ? $this->data['id'] : false; 
	}
	
}
