<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog                                                   *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* originaly based on todo written by Joseph Engo <jengo@phpgroupware.org>  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class soinfolog 				// DB-Layer
	{
		var $db,$db2;
		var $grants;
		var $data = array( );

		function soinfolog( $info_id = 0) {
			global $phpgw;
			$this->db     = $phpgw->db;
			$this->grants = $phpgw->acl->get_grants('infolog');
			
			$this->read( $info_id);
		}
				

		function readProj($proj_id) {
			if ($proj_id) {
				if (!is_object($this->projects)) {
					$this->projects = createobject('projects.projects');
				}            
				if (list( $proj ) = $this->projects->read_single_project( $proj_id ))
					return $proj;
			}
			return False;         
		}               

		function readAddr($addr_id) {
			if ($addr_id) {
				if (!is_object($this->contacts)) {
					$this->contacts = createobject('phpgwapi.contacts');
				}            
				if (list( $addr ) = $this->contacts->read_single_entry( $addr_id ))
					return $addr;
			}
			return False;                  
		}      
					
		
		function check_access( $info_id,$required_rights ) {
			global $phpgw_info;
			if ($info_id != $this->data['info_id']) {      // already loaded?
				$private_info = $this;                      // dont change our own internal data, dont use new as it changes $phpgw->db
				$info = $private_info->read($info_id);
			} else {
				$info = $this->data;
			}
			if (!$info || !$info_id)
				return False;
			
			$owner = $info['info_owner'];
			$user  = $phpgw_info['user']['account_id'];
			$access_ok = $owner == $user ||                // user has all rights
							 !!($this->grants[$owner] & $required_rights) &&    // ACL only on public entrys || $owner granted _PRIVATE
							 ($info['info_access'] == 'public' || !!($this->grants[$owner] & PHPGW_ACL_PRIVATE));   
							
			// echo "check_access(info_id=$info_id (owner=$owner, user=$user),required_rights=$required_rights): access".($access_ok?"Ok":"Denied");
			
			return $access_ok;         
		}
		
		function aclFilter($filter = 'none') {            // sql to be AND into a query to ensure ACL is respected (incl. _PRIVATE)
			global $phpgw_info;                           // filter: none    - list all entrys user have rights to see
																		//         private - list only his personal entrys (incl. those he is responsible for !!!)            
			if (isset($this->acl_filter[$filter]))
				return $this->acl_filter[$filter];         // used cached filter if found
				
			if (is_array($this->grants)) {
				while (list($user,$grant) = each($this->grants)) {
						// echo "<p>grants: user=$user, grant=$grant</p>";
					if ($grant & (PHPGW_ACL_READ|PHPGW_ACL_EDIT))
						$public_user_list[] = $user;
					if ($grant & PHPGW_ACL_PRIVATE)
						$private_user_list[] = $user;
				}
				if (count($private_user_list)) {               
					$has_private_access = 'info_owner IN ('.implode(',',$private_user_list).')';
				}            
			}
			$user = $phpgw_info['user']['account_id'];
			
			$filtermethod = " (info_owner=$user";         // user has all rights

			if ($filter == 'private') {                  // private means own entrys plus the one user is responsible for (and has rights to see)
				$filtermethod .= " OR info_responsible=$user AND (info_access='public'".($has_private_access?" OR $has_private_access":'').')';
			} else {      // none --> all entrys user has rights to see
				if ($has_private_access) {
					$filtermethod .= " OR $has_private_access";
				}
				if (count($public_user_list)) {         
					$filtermethod .= " OR (info_access='public' AND info_owner IN(" . implode(',',$public_user_list) . '))';
				}
			}
			$filtermethod .= ') ';
			
			// echo "<p>aclFilter('$filter')(user='$user') = '$filtermethod'</p>";
			
			return $this->acl_filter[$filter] = $filtermethod;   // cache the filter         
		}      
	
		function init() {
			global $phpgw_info;
			
			$this->data = array( 'info_owner' => $phpgw_info['user']['account_id'],
										'info_pri'    => 'normal' );
		}      
				
		function read($info_id) {                        // did _not_ ensure ACL, has to be done by the calling code
			if ($info_id <= 0 || $info_id != $this->data['info_id'] && 
										(!$this->db->query("select * FROM phpgw_infolog where info_id='$info_id'") ||   !$this->db->next_record())) 
			{
				$this->init( );
				return False;
			}
			if ($info_id != $this->data['info_id']) {      // data yet read in
				$this->data = $this->db->Record;
			}         
			return $this->data;         
		}
		
		function delete($info_id) { // did _not_ ensure ACL, has to be done by the calling code
			global $phpgw_info;
			$this->db->query("delete FROM phpgw_infolog where info_id='$info_id' or info_id_parent='"
				. "$info_id' AND ((info_access='public' and info_owner != '"
				. $phpgw_info['user']['account_id'] . "') or (info_owner='"
				. $phpgw_info['user']['account_id'] . "'))" ,__LINE__,__FILE__);
				
			if ($this->data['info_id'] == $info_id)
				$this->init( );            
		}

		function write($values) { // did _not_ ensure ACL, has to be done by the calling code
			while (list($key,$val) = each($values)) {
				$this->data['info_'.$key] = $val;   // update internal data
				switch ($key) {
					case 'info_id':
						break;         // later in where clause
					case 'des': case 'subject': case 'from': case 'addr':
						$val = addslashes($val);
					default:
						if ($query) $query .= ',';
						$query .= "info_$key='$val'";
				}
			}
			if ($values['info_id']) {
				$query = 'update phpgw_infolog set '.$query.' where info_id=\'' . $values['info_id'] .'\'';
			} else {
				$query = 'insert INTO phpgw_infolog set '.$query;
				/*
				 * need to set $this->data['info_id'] with assigned autoincrement id
				 */
			}                  
				
			//echo '<br>edit(): query: '.$query;
			
			$this->db->query($query,__LINE__,__FILE__);         
		}
	}
