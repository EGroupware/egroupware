<?php
	/**************************************************************************\
	* eGroupWare - Resources                                                   *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	* --------------------------------------------                             *
	\**************************************************************************/

	/* $Id: */

	class so_acl
	{
		var $db;

		function so_acl()
		{
			copyobj($GLOBALS['phpgw']->db,$this->db);
		}

		function get_rights($location)
		{
			$result = array();
			$sql = "SELECT acl_account, acl_rights from phpgw_acl WHERE acl_appname = 'resources' AND acl_location = '$location'";
			$this->db->query($sql,__LINE__,__FILE__);
			while($this->db->next_record())
			{
				$result[$this->db->f('acl_account')] = $this->db->f('acl_rights');
			}
			return $result;
		}

		function remove_location($location)
		{
			$sql = "delete from phpgw_acl where acl_appname='resources' and acl_location='$location'";
			$this->db->query($sql,__LINE__,__FILE__);
		}

		/*!
			@function get_permission
			@abstract gets permissions for resources of user 
			@discussion This function is needed, cause eGW api dosn't provide a usefull function for that topic!
			@discussion Using api-functions for that, would resault bad performace :-(
			@autor autor of news_admin ?
			
			@param int $user user_id we want to get perms for
			@param bool $inc_groups get rights due to groupmembership of user
			
		*/
		function get_permissions($user, $inc_groups)
		{
			$groups = $GLOBALS['phpgw']->acl->get_location_list_for_id('phpgw_group', 1, $user);
			$result = array();
			$sql  = 'SELECT acl_location, acl_rights FROM phpgw_acl ';
			$sql .= "WHERE acl_appname = 'resources' ";
			if($inc_groups)
			{
				$sql .= 'AND acl_account IN('. (int)$user;
				$sql .= ($groups ? ',' . implode(',', $groups) : '');
				$sql .= ')';
			}
			else
			{
				$sql .= 'AND acl_account ='. (int)$user;
			}
			$this->db->query($sql,__LINE__,__FILE__);
			while ($this->db->next_record())
			{
				$result[$this->db->f('acl_location')] |= $this->db->f('acl_rights');
			}
			return $result;
		}
	}
