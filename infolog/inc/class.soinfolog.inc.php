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
		var $filters = array( );

		function soinfolog( $info_id = 0)
		{
			global $phpgw;
			$this->db     = $phpgw->db;
			$this->grants = $phpgw->acl->get_grants('infolog');
			
			$this->read( $info_id );
		}
				

		function readProj($proj_id)
		{
			if ($proj_id) {
				if (!is_object($this->projects)) {
					$this->projects = createobject('projects.projects');
				}            
				if (list( $proj ) = $this->projects->read_single_project( $proj_id))
					return $proj;
			}
			return False;         
		}               

		function readAddr($addr_id)
		{
			if ($addr_id) {
				if (!is_object($this->contacts)) {
					$this->contacts = createobject('phpgwapi.contacts');
				}            
				if (list( $addr ) = $this->contacts->read_single_entry( $addr_id ))
					return $addr;
			}
			return False;                  
		}      
					
		
		function check_access( $info_id,$required_rights )
		{
			global $phpgw_info;
			if ($info_id != $this->data['info_id'])      	// already loaded?
			{
				// dont change our own internal data,
				// dont use new as it changes $phpgw->db
				$private_info = $this;                      
				$info = $private_info->read($info_id);
			}
			else
			{
				$info = $this->data;
			}
			if (!$info || !$info_id)
			{
				return False;
			}
			$owner = $info['info_owner'];
			$user  = $phpgw_info['user']['account_id'];

			$access_ok = $owner == $user ||                // user has all rights
							 // ACL only on public entrys || $owner granted _PRIVATE
							 !!($this->grants[$owner] & $required_rights) &&
							 ($info['info_access'] == 'public' ||
							 !!($this->grants[$owner] & PHPGW_ACL_PRIVATE));   
							
			// echo "check_access(info_id=$info_id (owner=$owner, user=$user),required_rights=$required_rights): access".($access_ok?"Ok":"Denied");
			
			return $access_ok;         
		}

		// sql to be AND into a query to ensure ACL is respected (incl. _PRIVATE)
		// filter: none|all    - list all entrys user have rights to see
		//         private|own - list only his personal entrys
		//							(incl. those he is responsible for !!!)            
		function aclFilter($filter = 'none')
		{
			global $phpgw_info;

			ereg('.*(own|privat|all|none).*',$filter,$vars);
			$filter = $vars[1];

			if (isset($this->acl_filter[$filter]))
			{
				return $this->acl_filter[$filter];  // used cached filter if found
			}
			if (is_array($this->grants))
			{
				while (list($user,$grant) = each($this->grants))
				{
					// echo "<p>grants: user=$user, grant=$grant</p>";
					if ($grant & (PHPGW_ACL_READ|PHPGW_ACL_EDIT))
					{
						$public_user_list[] = $user;
					}
					if ($grant & PHPGW_ACL_PRIVATE)
					{
						$private_user_list[] = $user;
					}
				}
				if (count($private_user_list))
				{               
					$has_private_access = 'info_owner IN ('.
												 implode(',',$private_user_list).')';
				}            
			}
			$user = $phpgw_info['user']['account_id'];
			
			$filtermethod = " (info_owner=$user"; // user has all rights

			// private: own entries plus the one user is responsible for 
			if ($filter == 'private' || $filter == 'own')
			{
				$filtermethod .= " OR info_responsible=$user AND (info_access='public'".($has_private_access?" OR $has_private_access":'').')';
			}
			else      				// none --> all entrys user has rights to see
			{
				if ($has_private_access)
				{
					$filtermethod .= " OR $has_private_access";
				}
				if (count($public_user_list))
				{ 
					$filtermethod .= " OR (info_access='public' AND info_owner IN(" . implode(',',$public_user_list) . '))';
				}
			}
			$filtermethod .= ') ';
			
			return $this->acl_filter[$filter] = $filtermethod;  // cache the filter
		}      
	
		function statusFilter($filter = '')
		{
			ereg('.*(done|open|offer).*',$filter,$vars);
			$filter = $vars[1];

			switch ($filter)
			{
				case 'done':	return " AND info_status IN ('done','billed')";
				case 'open':	return " AND NOT (info_status IN ('done','billed'))";
				case 'offer':	return " AND info_status = 'offer'";
			}
			return '';
		}

		function dateFilter($filter = '')
		{
			ereg('.*(upcoming|today|overdue).*',$filter,$vars);
			$filter = $vars[1];

			$now = getdate(time());
			$tomorrow = mktime(0,0,0,$now['mon'],$now['mday']+1,$now['year']);

			switch ($filter)
			{
				case 'upcoming':	return " AND info_startdate >= '$tomorrow'";
				case 'today':		return " AND info_startdate < '$tomorrow'";
				case 'overdue':	return " AND (info_enddate != 0 AND info_enddate < '$tomorrow')";
			}
			return '';
		}

		function init()
		{
			global $phpgw_info;
			
			$this->data = array( 'info_owner' => $phpgw_info['user']['account_id'],
										'info_pri'    => 'normal' );
		}      
				
		function read($info_id)		// did _not_ ensure ACL
		{
			if ($info_id <= 0 || $info_id != $this->data['info_id'] && 
										(!$this->db->query("select * FROM phpgw_infolog where info_id='$info_id'") ||   !$this->db->next_record())) 
			{
				$this->init( );
				return False;
			}
			if ($info_id != $this->data['info_id'])      // data yet read in
			{
				$this->data = $this->db->Record;
			}         
			return $this->data;         
		}
		
		function delete($info_id)  // did _not_ ensure ACL
		{
			global $phpgw_info;

			$this->db->query("delete FROM phpgw_infolog where info_id='$info_id' or info_id_parent='"
				. "$info_id' AND ((info_access='public' and info_owner != '"
				. $phpgw_info['user']['account_id'] . "') or (info_owner='"
				. $phpgw_info['user']['account_id'] . "'))" ,__LINE__,__FILE__);
				
			if ($this->data['info_id'] == $info_id)
			{
				$this->init( );            
			}
		}

		function write($values)  // did _not_ ensure ACL
		{
			while (list($key,$val) = each($values))
			{
				$this->data['info_'.$key] = $val;   // update internal data

				switch ($key)
				{
					case 'info_id':
						break;
					case 'des': case 'subject': case 'from': case 'addr':
						$val = addslashes($val);
					default:
						if ($query) $query .= ',';
						$query .= "info_$key='$val'";
				}
			}
			if ($values['info_id'])
			{
				$query = 'update phpgw_infolog set '.$query.' where info_id=\'' .
							$values['info_id'] .'\'';
			}
			else
			{
				$query = 'insert INTO phpgw_infolog set '.$query;
				/*
				 * need to set $this->data['info_id'] with assigned autoincrement id
				 * now data will be rereaded
				 */
			}                  
			$this->db->query($query,__LINE__,__FILE__);         
		}

		function anzSubs( $info_id )
		{
			$this->db->query('select count(*) FROM phpgw_infolog where '.
								  "info_id_parent=$info_id",__LINE__,__FILE__);

			$this->db->next_record();

			return $this->db->f(0);
		}

		function readIdArray($order,$sort,$filter,$cat_id,$query,$action,$addr_id,
									$proj_id,$info_id,$ordermethod,&$start,&$total)
		{
			if ($order)
			{
			  $ordermethod = 'order by ' . $order . ' ' . $sort;
			}
			else
			{
			  $ordermethod = 'order by info_datecreated desc';   // newest first
			}
			$filtermethod = $this->aclFilter($filter);
			$filtermethod .= $this->statusFilter($filter);
			$filtermethod .= $this->dateFilter($filter);
			// echo "<p>filtermethod='$filtermethod'</p>";

			if ($cat_id)
			{
			  $filtermethod .= " AND info_cat='$cat_id' "; 
			}
			switch ($action)
			{
				case 'addr':	$filtermethod .= " AND info_addr_id=$addr_id ";
									break;
				case 'proj':	$filtermethod .= " AND info_proj_id=$proj_id ";
									break;
			}
			if ($query)			  // we search in _from, _subject and _des for $query
			{
				$sql_query = "AND (info_from like '%$query%' OR info_subject ".
								 "like '%$query%' OR info_des like '%$query%') ";
			}
			$pid = 'AND info_id_parent='.($action == 'sp' ? $info_id : 0);  

			if (!$phpgw_info['user']['preferences']['infolog']['listNoSubs'] &&
				 $action != 'sp')
			{
				$pid = '';
			}
			$this->db->query("SELECT COUNT(*) FROM phpgw_infolog WHERE $filtermethod $pid $sql_query",__LINE__,__FILE__);

			$this->db->next_record();
			$total = $this->db->f(0);

			if (!$start || $start > $total)
			{
				$start = 0;
			}
			$this->db->limit_query("SELECT info_id,info_id_parent FROM phpgw_infolog WHERE $filtermethod $pid $sql_query $ordermethod",$start,__LINE__,__FILE__);

			$ids = array( );
			while ($this->db->next_record())
			{
				$ids[$this->db->f('info_id')] = $this->db->f('info_parent_id');
			}
			return $ids;
		}
	}
