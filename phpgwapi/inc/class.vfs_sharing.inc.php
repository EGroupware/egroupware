<?php
  /***************************************************************************\
  * eGroupWare - File Manager                                                 *
  * http://www.egroupware.org                                                 *
  * Written by:                                                               *
  *  - Vinicius Cubas Brand <viniciuscb@users.sourceforge.net>                *
  *  sponsored by Thyamad - http://www.thyamad.com                            *
  * ------------------------------------------------------------------------- *
  * Description: File Sharing class handler for VFS (SQL implementation v2)   *
  * ------------------------------------------------------------------------- *
  *  This program is free software; you can redistribute it and/or modify it  *
  *  under the terms of the GNU General Public License as published by the    *
  *  Free Software Foundation; either version 2 of the License, or (at your   *
  *  option) any later version.                                               *
  \***************************************************************************/

	// This class had to be created because file sharing work very differently
	// In fm2 than in fm.

	#FIXME this class is completely vfs_sql2 oriented. Must exist implementation
	# to other types

	class vfs_sharing
	{
		var $accounts;
		var $db;
		
		/*!
		 * function vfs_sharing
		 * @description Class constructor
		 */
		function vfs_sharing()
		{
			$this->accounts =& $GLOBALS['phpgw']->accounts;
			$this->db = $GLOBALS['phpgw']->db;
		}

		/*!
		 * function set_permissions
		 * @description Add specified permissions that do not exist, remove
		 *    unspecified permissions that exist. Easier to call than 
		 *    add_permissions and then remove_permissions
		 * @param array $data in the following format:
		 *         array(
		 *           file_id => array(
		 *              account_id  => acl_rights,
		 *              account_id2 => acl_rights2,...
		 *			 ),
		 *           file_id2 ... );
		 */
		function set_permissions($data)
		{
			//TODO see if a user have permissions in a file. Only if he have 
			//(or if is inside his homedir) he can change permissions
			if (!$data || !is_array($data))
			{
				return false;
			}
			
			//search for permissions on files, to know which ones must be
			//updated/inserted
			reset($data);
			while(list($file_id,$account_ids) = each($data))
			{
				$file_ids[] = $file_id;
			}
			
			$sql = 'SELECT * from phpgw_vfs2_shares 
					WHERE file_id IN ('.implode(',',$file_ids).')';

			$this->db->query($sql,__LINE__,__FILE__);

			while ($this->db->next_record())
			{
				$current_shares[$this->db->Record['file_id']][$this->db->Record['account_id']] = $this->db->Record['acl_rights']; 
			}

			//now that we have the current permissions, must know which ones to
			//insert/update and which ones to delete
			reset($data);
			while(list($file_id,$account_ids) = each($data))
			{
				reset($account_ids);
				while(list($account_id,$acl_rights) = each($account_ids))
				{
					//exists
					if (array_key_exists($account_id,$current_shares[$file_id]))
					{
						if ($current_shares[$file_id][$account_id] != $acl_rights)
						{
							$insert[$file_id][$account_id] = $acl_rights;
						}

						unset($current_shares[$file_id][$account_id]);
						
					}
					else
					{
						$insert[$file_id][$account_id] = $acl_rights;
					}
				}
			}

			//get which ones to delete
			reset($current_shares);
			while(list($file_id,$account_ids) = each($current_shares))
			{
				if (is_array($account_ids))
				{
					reset($account_ids);
					while(list($account_id,$acl_rights) = each($account_ids))
					{
						$delete[$file_id][$account_id] = $acl_rights;
					}
				}
			}

			foreach($insert as $file_id => $account_ids)
			{
				$this->store_permissions($file_id,$account_ids);
			}
			
			foreach($delete as $file_id => $account_ids)
			{
				$this->remove_permissions($file_id,$account_ids);
			}

			return true;
		}

		/*!
		 * function store_qpermissions
		 * @description Add/update new permissions to a file id
		 * @param account_ids: array('account_id'=> acl_rights,acc_id2=>acl_r2,)
		 */
		function store_permissions($file_id,$account_ids)
		{
			if (!is_array($account_ids) || !is_numeric($file_id))
			{
				return false;
			}

			foreach($account_ids as $account_id => $acl_rights)
			{
				$this->db->insert('phpgw_vfs2_shares',
					array('acl_rights'=>$acl_rights),
					array('account_id'=>$account_id,'file_id'=>$file_id),
					__LINE__,__FILE__);
			}
			return true;
		}

		/*!
		 * function remove_permissions
		 * @description Remove some permissions of a file id
		 */
		function remove_permissions($file_id,$account_ids)
		{
			if (!is_array($account_ids) || !is_numeric($file_id))
			{
				return false;
			}

			foreach($account_ids as $account_id => $acl_rights)
			{
				$this->db->delete('phpgw_vfs2_shares',
					array('account_id'=>$account_id,'file_id'=>$file_id),
					__LINE__,__FILE__);
			}
			return true;
		}

		/*!
		 * function get_permissions
		 * @description This function will get the permissions set for a given
		 *   file, makeing a simple query in the file repository. Does not
		 *   search for permissions in parent dirs. If you want to know which
		 *   is the permission for a user in a given file TAKING IN COUNT the
		 *   parent dirs, use $this->get_file_permissions instead.
		 * @param int file_id The id of the file
		 * @result array with account_id as index, acl_rights as value
		 * @author Vinicius Cubas Brand
		 */
		function get_permissions($file_id)
		{
			$this->db->select('phpgw_vfs2_shares','acl_rights,account_id',
				array('file_id'=>$file_id),__LINE__,__FILE__);

			$result = array();

			while ($this->db->next_record())
			{
				$result[$this->db->Record['account_id']] = $this->db->Record['acl_rights'];
			}

			return ($result);

		}

		/*!
		 * function get_shares
		 * @description Get all shares in which the user have $permission
		 * @param $account_id The id of the user that can read the shared folder
		 * @param $is_owner If true, will get only the shared folders that
		 *   $account_id owns. Useful to get the shares that account_id owns
		 *   and have configured himself (true), or instead the shares of the 
		 *   others that he have $permission (false)
		 * @result array with the list of the file_id's of all shares
		 */
		function get_shares($account_id,$is_owner=false,$permission=PHPGW_ACL_READ)
		{
			if ($is_owner)
			{
				$sql = "SELECT DISTINCT sh.file_id     as file_id,
							   sh.acl_rights  as acl_rights,
							   fls.directory  as directory,
							   fls.name       as name
						FROM   phpgw_vfs2_shares sh,
							   phpgw_vfs2_files  fls
						WHERE  sh.file_id = fls.file_id
						  AND  fls.shared = 'Y' 
						  AND  fls.owner_id = $account_id";
			}
			else
			{
				//gets the id of all groups $account_id belongs to
				$groups = $GLOBALS['phpgw']->accounts->membership($account_id);

				foreach($groups as $group)
				{
					$accounts[] = $group['account_id'];
				}

				$accounts[] = $account_id;
			
				$sql = "SELECT DISTINCT sh.file_id     as file_id,
							   sh.acl_rights  as acl_rights,
							   fls.directory  as directory,
							   fls.name       as name
						FROM   phpgw_vfs2_shares sh,
							   phpgw_vfs2_files  fls
						WHERE  sh.file_id = fls.file_id
						  AND  sh.account_id IN (".implode(',',$accounts).")
						  AND  fls.shared = 'Y' 
						  AND  fls.owner_id != $account_id";
			}

			$this->db->query($sql,__LINE__,__FILE__);

			$res = array();
			while($this->db->next_record())
			{
				if($this->db->Record['acl_rights'] & $permission)
				{
					$res[] = $this->db->Record;
				}
			}
			
			//should be returned the array with complete file description
			return $res; 
		}

		/*!
		 * function search_shares
		 * @description Search for a shared folder which the user have
		 *   $permission and have $keyword related (in directory or filename)
		 * @result array with the list of all shares
		 */
		//TODO search by file owner's name
		function search_shares($account_id,$keyword,$permission=PHPGW_ACL_READ)
		{
			if ($account_id != ((int)$account_id))
			{
				return false;
			}

			//gets the id of all groups $account_id belongs to
			$groups = $GLOBALS['phpgw']->accounts->membership($account_id);

			foreach($groups as $group)
			{
				$accounts[] = $group['account_id'];
			}

			$accounts[] = $account_id;
		
			$sql = "SELECT DISTINCT sh.file_id     as file_id,
						   sh.acl_rights  as acl_rights,
						   fls.directory  as directory,
						   fls.name       as name
					FROM   phpgw_vfs2_shares sh,
						   phpgw_vfs2_files  fls
					WHERE  sh.file_id = fls.file_id
					  AND  sh.account_id IN (".implode(',',$accounts).")
					  AND  (  fls.directory LIKE '%$keyword%' 
					       OR fls.name LIKE '%$keyword%')
					  AND  fls.shared = 'Y' 
					  AND  fls.owner_id != $account_id";

			$this->db->query($sql,__LINE__,__FILE__);

			while($this->db->next_record())
			{
				if ($this->db->Record['acl_rights'] & $permission)
				{
					$res[] = $this->db->Record;
				}
			}
			return $res;
		}

		/*!
		 * function get_file_permissions
		 * @description Get the permissions for a user in a given file
		 *   For files in a shared dir, will get the acl rights of the parent
		 *   dir, and if not specified, of the parent of the parent, and so on.
		 *   NOTE: this consider that files CANNOT have permissions set, only
		 *   their parent dir, and the file inherits the nearer parent with 
		 *   permissions defined
		 * @result int some mask of various PHPGW_ACL_*
		 */
		function get_file_permissions($account_id,$file_id)
		{
			//get directory/file names
			$this->db->select('phpgw_vfs2_files','directory,name',
				array('file_id' => $file_id),__LINE__,__FILE__);

			$this->db->next_record();

			$directory = $this->db->Record['directory'];
			$name = $this->db->Record['name'];

			$fullname = $directory.'/'.$name;

			$parent_dirs = array();
	
			$dirs_expl = explode('/',$fullname);

			//put all parents hierarchy in an array
			$parent_dirs_array[]=$fullname;
			while(1)
			{
				array_pop($dirs_expl);
				if($dirs_expl[1])
				{
					$parent_dirs_array[]=implode('/',$dirs_expl);
				}
				else
				{
					$parent_dirs_array[]='/';
					break;
				}
			}

			//gets the id of all groups $account_id belongs to
			$groups = $GLOBALS['phpgw']->accounts->membership($account_id);

			foreach($groups as $group)
			{
				$accounts[] = $group['account_id'];
			}

			$accounts[] = $account_id;

			$accounts = implode(',',$accounts);


			//searches for information in the parent dirs
			for($i=0; $i<count($parent_dirs_array);$i++)
			{
				$dir_name = array_pop(explode('/',$parent_dirs_array[$i]));
				if ($dir_name)
				{
					$sql = "SELECT sh.acl_rights  as acl_rights,
								   fls.directory  as directory,
								   fls.name       as name,
								   fls.owner_id   as owner_id
							FROM   phpgw_vfs2_shares sh,
								   phpgw_vfs2_files  fls
							WHERE  sh.file_id = fls.file_id
							  AND  (sh.account_id IN ($accounts)
							       OR fls.owner_id = $account_id)
							  AND  fls.directory = '".$parent_dirs_array[$i+1]."' 
							  AND  fls.name      = '".$dir_name."'";

					$this->db->query($sql,__LINE__,__FILE__);
					
/*
					$this->db->select('phpgw_vfs2_files','file_id',
						array('directory'=>$parent_dirs_array[$i+1],
						      'name'=>$dir_name), __LINE__,__FILE__);

					$this->db->next_record();
					
					$this->db->select('phpgw_vfs2_shares','acl_rights',
						array('file_id'=>$this->db->Record['file_id'],
						      'account_id'=>$account_id),__LINE__,__FILE__);*/

					while ($this->db->next_record())
					{
						if ($this->db->Record['owner_id'] == $account_id)
						{
							//the user can do anything with any dir or file
							//inside a dir that belongs to him.
							return PHPGW_ACL_READ|PHPGW_ACL_EDIT|PHPGW_ACL_ADD;
						}
						else
						{
							$entered = true;
							$result |= $this->db->Record['acl_rights'];
						}
					}
					if($entered)
					{
						return $result;
					}
				}
			}
			return false;
		}

	}

?>
