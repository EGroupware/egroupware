<?php
  /***************************************************************************\
  * eGroupWare - File Manager                                                 *
  * http://www.egroupware.org                                                 *
  * Written by:                                                               *
  *  - Vinicius Cubas Brand <viniciuscb@users.sourceforge.net>                *
  *  sponsored by Thyamad - http://www.thyamad.com                            *
  * ------------------------------------------------------------------------- *
  * Description: File ID Prefixes class handler for SQL implementation v2     *
  * ------------------------------------------------------------------------- *
  *  This program is free software; you can redistribute it and/or modify it  *
  *  under the terms of the GNU General Public License as published by the    *
  *  Free Software Foundation; either version 2 of the License, or (at your   *
  *  option) any later version.                                               *
  \***************************************************************************/

	#12 Oct 2004 viniciuscb Initial Release

	define ('PHPGW_VFS2_PREFIX_APP','vfs2_prefix'); //for prefix
	define ('PHPGW_VFS2_PTYPE_APP','vfs2_ptype');   //for file type

	class vfs_prefixes
	{
		var $db;

		var $table_fields = array(
			'prefix_id',
			'owner_id',
			'prefix',
			'prefix_description',
			'prefix_type'
		);

		function vfs_prefixes()
		{
			$this->db = $GLOBALS['phpgw']->db;
		}

		/*!
		 * function add
		 * @description Adds a new File ID Prefix to the prefixes repository
		 * @param prefix string (required) A prefix
		 * @param prefix_description string (optional) Brief prefix description
		 * @param owner_id int (required) Owner Id of prefix
		 * @param prefix_type (optional)  can be 'p' for prefix, 't' for type.
		 * @result (int) prefix_id
		 *
		 * @note: will search for another equal $prefix in repository. If
		 *        exists, returns its prefix_id and if user have permission,
		 *        updates this prefix. If don't exists, insert and return its
		 *        prefix_id. $dont_update will not do any update if exists, even
		 *        if current user can update.
		 */
		function add($data,$dont_update=false)
		{

			if (!$data['prefix'])
			{
				return false;
			}

			if (!$data['prefix_type'])
			{
				$data['prefix_type'] = 'p';
			}

			//eliminate keys which are not a field in table
			foreach($data as $key => $val)
			{
				if (!in_array($key,$this->table_fields))
				{
					unset($data[$key]);
				}
			}
			
			//see if exists some equal prefix id
			$this->db->select('phpgw_vfs2_prefixes','prefix_id',array('prefix' => $data['prefix']));
			
			if($this->db->next_record()) //exists
			{
				if ($dont_update)
				{
					return $this->db->Record['prefix_id'];
				}
				$data['prefix_id'] = $this->db->Record['prefix_id'];
				return $this->edit($data);
			}

			if (!$data['owner_id'])
			{
				$data['owner_id'] = $GLOBALS['phpgw_info']['user']['account_id'];
			}

			$this->db->insert('phpgw_vfs2_prefixes',$data,false,__LINE__,__FILE__);

			$this->db->select('phpgw_vfs2_prefixes','prefix_id',array('prefix' => $data['prefix']));

			if($this->db->next_record()) //exists
			{
				return $this->db->Record['prefix_id'];
			}

			return false;
		}

		/*!
		 * function edit
		 * @description Edits a File ID Prefix
		 * @param prefix_id int (required) The ID for prefix
		 * @param prefix string (optional) A prefix
		 * @param prefix_description string (optional) Brief prefix description
		 * @param owner_id int (optional) Owner Id of prefix
		 * @param prefix_type (optional)  can be 'p' for prefix, 't' for type.
		 * @result (int) prefix_id
		 * @result (bool) true on success, false on any other possibility
		 */
		function edit($data)
		{
			if (!$data['prefix_id'])
			{
				return false;
			}

			//eliminate keys which are not a field in table
			foreach($data as $key => $val)
			{
				if (!in_array($key,$this->table_fields))
				{
					unset($data[$key]);
				}
			}

			$where['prefix_id'] = $data['prefix_id'];
			unset($data['prefix_id']);

			return $this->db->update('phpgw_vfs2_prefixes',$data,$where,__LINE__,__FILE__);
			
		}

		/*!
		 * function remove
		 * @description Removes a File ID Prefix
		 * @param prefix_id int (required) The ID for prefix
		 * @result (bool) true on success, false on any other possibility
		 */
		function remove($prefix_id)
		{
			return $this->db->delete('phpgw_vfs2_prefixes',array('prefix_id' => $prefix_id),__LINE__,__FILE__);
		}

		/*!
		 * function get
		 * @description Gets information about a prefix just based in prefix_id
		 * @param prefix_id int (required) The ID for prefix
		 *       OR
		 * @param prefix int (required) The prefix
		 * @result (array) with column names as indexes, empty array if inexist
		 */
		function get($data)
		{
			if (!$data['prefix_id'] && !$data['prefix'])
				return false;

			
			$this->db->select('phpgw_vfs2_prefixes','*',$data,__LINE__,__FILE__);

			if ($this->db->next_record())
			{
				return $this->db->Record;
			}
			return array();
		}

		/*!
		 * function get_prefixes
		 * @description Gets all prefixes this user can view, based in querying
		 *   acl
		 * @param user_id int (required) The ID of user to whom you want to
		 *   know, or will get current user as default
		 * @param status string  If 'view', returns info about all prefixes
		 *   user can view. if 'owns', return only prefixes user owns
		 * @result (array) with column names as indexes, empty array if inexist
		 */
		function get_prefixes($status='view',$user_id=false,$type='p')
		{
			if (!$user_id)
			{
				$user_id = $GLOBALS['phpgw_info']['user']['account_id']; 
			}

			switch ($status)
			{
				case 'owns':
					$this->db->select('phpgw_vfs2_prefixes','*',array('owner_id'=>$user_id,'prefix_type'=>$type),__LINE__,__FILE__);
					
					while($this->db->next_record())
					{
						$return[] = $this->db->Record;
					}
					
					break;
				case 'view':
					$acl = CreateObject('phpgwapi.acl',$user_id);

					//fetch ids of prefixes that user can view
					if (!$pr_list =  $acl->get_location_list_for_id(PHPGW_VFS2_PREFIX_APP,PHPGW_ACL_READ,$user_id))
					{
						$pr_list = array();
					}

					//fetch ids of prefixes that groups user belong to can view
					//Note: this will be in two phases. One: fetch groups user
					//     belongs to. Two: fetch prefix list for these groups

					/* Note: prefixes are organized in phpgwapi.acl in the
					 * following schema:
					 * - appname: (PHPGW_VFS2_PREFIX_APP)
					 * - location: id_prefix
					 * - account_id: Id of user that has grants (not the
					 *   grantor. The grantor is only the owner of prefix,
					 *   defined in prefixes repository).
					 * - acl_rights: PHPGW_ACL_READ
					 */

					$user_groups = $GLOBALS['phpgw']->accounts->membership($user_id);

					foreach($user_groups as $group)
					{
						if (!$group_pr_list = $acl->get_location_list_for_id(PHPGW_VFS2_PREFIX_APP,PHPGW_ACL_READ,$group['account_id']))
						{
							$group_pr_list = array();
						}

						$pr_list = array_merge($pr_list,$group_pr_list);
					}

					//remove dupliate values
					$pr_list = array_unique($pr_list);

					//now we have the list of prefixes user can view. We must
					//now fetch complete information about prefixes of
					//phpgw_vfs2_prefixes

					if (!count($pr_list))
					{
						return array();
					}
	
					if ($pr_list)
					{
						$prefix_string = '('.implode(',',$pr_list).')';
						
						$this->db->select('phpgw_vfs2_prefixes','*','prefix_id IN '.$prefix_string." AND prefix_type='$type'",__LINE__,__FILE__);
						
						while($this->db->next_record())
						{
							$return[] = $this->db->Record;
						}
					}
					else
					{
						return array();
					}

					break;
				default:
					return false;
			}
			return $return;
		}

		/*!
		 * function update_permissions
		 * @description Updates users who can see a prefix
		 *
		 * @param prefix_id int (required) The prefix that will have permissions
		 *   changed 
		 * @param user_list array  Array with account_ids that can read prefix
		 *   as values.
		 *
		 * @result (bool)
		 */
		function update_permissions($prefix_id,$user_list)
		{

			//1. see if current user is owner of the prefix
			$current_user_id = $GLOBALS['phpgw_info']['user']['account_id'];

			$prefix_info = $this->get(array('prefix_id'=>$prefix_id));

			if ($current_user_id != $prefix_info['owner_id'])
			{
				return false;
			}

			//2. get current permission for prefix
			$current_permissions = $this->get_permissions(array('prefix_id'=>$prefix_id));

			//3. change permissions
			$list_of_users_to_add = array_diff($user_list,$current_permissions);
			$list_of_users_to_del = array_diff($current_permissions,$user_list);


			$acl = CreateObject('phpgwapi.acl',$current_user_id);

			foreach($list_of_users_to_add as $user_id)
			{
				$acl->account_id = $user_id;
				$acl->read_repository();
				#echo "<br>\nAdded: prefix $prefix_id ; user $user_id";
				$acl->add(PHPGW_VFS2_PREFIX_APP,$prefix_id,PHPGW_ACL_READ);
				$acl->save_repository();
			}

			foreach($list_of_users_to_del as $user_id)
			{	
				$acl->account_id = $user_id;
				$acl->read_repository();
				#echo "<br>\nDeleted: prefix $prefix_id ; user $user_id";
				$acl->delete(PHPGW_VFS2_PREFIX_APP,$prefix_id);
				$acl->save_repository();
			}

		}

		/*!
		 * function get_permissions
		 * @description This will get all permissions for a given prefix.
		 *              In other words, will return an array of all accounts who
		 *              can see a prefix. Will not dive into groups' users,
		 *              only showing user and group accounts who can see
		 *              prefix.
		 *
		 * @param prefix int (required) The File ID Prefix
		 *    OR
		 * @param prefix_id int (required) The ID of the File ID Prefix
		 * @param prefixes array  The same type of the return of get_prefixes
		 *
		 * @result (array) with column names as indexes, empty array if inexist
		 */
		function get_permissions($data)
		{
			if (is_numeric($data))
			{
				$prefix_id = $data;
			}
			elseif ($data['prefix'])
			{
				$this->db->select('phpgw_vfs2_prefixes','prefix_id',array('prefix'=>$data['prefix']),__LINE__,__FILE__);
				if ($this->db->next_record())
				{
					$prefix_id = $this->db->Record['prefix_id'];
				}
			}
			elseif($data['prefix_id'])
			{
				$prefix_id = $data['prefix_id'];
			}

			if (!$prefix_id)
			{
				return false;
			}

			$acl = CreateObject('phpgwapi.acl');
			$user_ids = $acl->get_ids_for_location($prefix_id,PHPGW_ACL_READ,PHPGW_VFS2_PREFIX_APP);

			return ($user_ids)?$user_ids:array();
		}

	}

?>
