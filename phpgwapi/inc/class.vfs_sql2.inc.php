<?php
  /**************************************************************************\
  * eGroupWare API - VFS                                                     *
  * This file written by Vinicius Cubas Brand, strongly based on vfs_sql of  *
  *		Jason Wies (Zone) <zone@phpgroupware.org>                            *
  * This class handles file/dir access for eGroupWare                        *
  * Copyright (C) 2001 Jason Wies, (C) 2004 Vinicius Cubas Brand             *
  * -------------------------------------------------------------------------*
  * This library is part of the eGroupWare API                               *
  * http://www.egroupware.org/api                                            * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

	/*!
	@class vfs
	@abstract Virtual File System with SQL backend - version 2
	@description Authors: Zone, viniciuscb
	*/

	# viniciuscb 2004-09-06 Upgraded this class to the sql implementation v2.

	/* These are used in calls to extra_sql () */
	define ('DEFAULT_LOW_PERMS',0600);

	#upd viniciuscb 2004-09-06 Updated to the new database tables (the ones that
	#  match phpgw_vfs2*). I have created a class to handle mime types and one
	#  to handle the versioning system. 

	#upd viniciuscb 2005-03-11 Deleted some garbage

	/**
	 * Class: vfs
	 *
	 *	Virtual File System class - SQL v.2 implementation
	 */
	class vfs extends vfs_shared
	{
		var $working_id;
		var $working_lid;
		var $meta_types;
		var $now;
		var $file_actions;
		var $vfs_mimetypes;
		var $vfs_versionsystem;
		var $vfs_customfields;
		var $vfs_sharing;
		var $db;
		var $db_hl;
		var $appfiles_root;

		var $Debug;

		//other attributes may be in the custom fields....

		var $attribute_tables = array(
			'file_id'       => 'phpgw_vfs2_files',	
			'owner_id'      => 'phpgw_vfs2_files',
			'createdby_id'  => 'phpgw_vfs2_files',
			'modifiedby_id' => 'phpgw_vfs2_versioning',
			'created'       => 'phpgw_vfs2_files',
			'modified'      => 'phpgw_vfs2_versioning',
			'size'			=> 'phpgw_vfs2_files',
			'mime_type'     => 'phpgw_vfs2_mimetypes',
			'comment'       => 'phpgw_vfs2_files',
			'app'           => 'phpgw_vfs2_files',
			'directory'     => 'phpgw_vfs2_files',
			'name'          => 'phpgw_vfs2_files',
			'link_directory'=> 'phpgw_vfs2_files',
			'link_name'     => 'phpgw_vfs2_files',
			'version'       => 'phpgw_vfs2_files'
		);

		//to external use.
		//if $custom_field_support is set, then this class have support to
		//custom fields.
		var $custom_field_support = 1;

		//if $search_support is set, then this class have support to
		//searching in files for a particular value in a particular property.
		var $search_support = 1;


		/*!
		@function vfs
		@abstract constructor, sets up variables
		*/
		function vfs ()
		{

			//just in case... this must change soon.
			if (@$GLOBALS['phpgw_info']['flags']['currentapp']=='filemanager')
			{
				echo "FILEMANAGER UNTESTED WITH VFS2. ABORTED.";
				$GLOBALS['phpgw']->common->phpgw_exit();
			}

			$this->db =& $GLOBALS['phpgw']->db;
			$this->db_hl =& $GLOBALS['phpgw']->db_hl;
	
			$this->vfs_shared ();
			$this->basedir = $GLOBALS['phpgw_info']['server']['files_dir'];
			$this->working_id = $GLOBALS['phpgw_info']['user']['account_id'];
			$this->working_lid = $GLOBALS['phpgw']->accounts->id2name($this->working_id);
			$this->now = date ('Y-m-d H:i:s');


			/*
			   File/dir attributes, each corresponding to a database field.
			   Useful for use in loops If an attribute was added to the table,
			   add it here and possibly add it to set_attributes ()

			   set_attributes now uses this array().   07-Dec-01 skeeter
			*/

			$this->attributes[] = 'deleteable';
			$this->attributes[] = 'content';
			$this->attributes[] = 'is_backup';
			$this->attributes[] = 'shared';
			$this->attributes[] = 'proper_id';

			$this->attribute_tables['deleteable'] = 'phpgw_vfs2_files';
			$this->attribute_tables['content'] = 'phpgw_vfs2_files';
			$this->attribute_tables['is_backup'] = 'phpgw_vfs2_files';
			$this->attribute_tables['shared'] = 'phpgw_vfs2_files';
			$this->attribute_tables['proper_id'] = 'phpgw_vfs2_files';

			/*
			   Decide whether to use any actual filesystem calls (fopen(),
			   fread(), unlink(), rmdir(), touch(), etc.).  If not, then we're
			   working completely in the database.
			*/
			$this->file_actions = $GLOBALS['phpgw_info']['server']['file_store_contents'] == 'filesystem' ||
				!$GLOBALS['phpgw_info']['server']['file_store_contents'];

			// test if the files-dir is inside the document-root, and refuse
			// working if so
			if ($this->file_actions && $this->in_docroot($this->basedir))
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				if ($GLOBALS['phpgw_info']['flags']['noheader']) 
				{
					echo parse_navbar();
				}
				echo '<p align="center"><font color="red"><b>'.lang('Path to user and group files HAS TO BE OUTSIDE of the webservers document-root!!!')."</b></font></p>\n";
				$GLOBALS['phpgw']->common->phpgw_exit();
			}

			/* We store the linked directories in an array now, so we don't
			 * have to make the SQL call again */




			$this->linked_dirs = array ();
			while ($GLOBALS['phpgw']->db->next_record ())
			{
				$this->linked_dirs[] = $GLOBALS['phpgw']->db->Record;
			}

			//set_vfs to use this very object (pass by reference)
			$this->vfs_mimetypes =& CreateObject('phpgwapi.vfs_mimetypes',false);
			$this->vfs_mimetypes->set_vfs($this);

			$this->vfs_versionsystem =& CreateObject('phpgwapi.vfs_versionsystem',false);
			$this->vfs_versionsystem->set_vfs($this);

			$this->vfs_customfields =& CreateObject('phpgwapi.vfs_customfields');

			$this->vfs_sharing =& CreateObject('phpgwapi.vfs_sharing');
		}

		/*!
		@function in_docroot
		@abstract test if $path lies within the webservers document-root
		*/
		function in_docroot($path)
		{
			$docroots = array(PHPGW_SERVER_ROOT,$_SERVER['DOCUMENT_ROOT']);

			foreach ($docroots as $docroot)
			{
				$len = strlen($docroot);

				if ($docroot == substr($path,0,$len))
				{
					$rest = substr($path,$len);

					if (!strlen($rest) || $rest[0] == DIRECTORY_SEPARATOR)
					{
						return True;
					}
				}
			}
			return False;
		}

		/*!
		@function get_id_from_path
		@abstract Given a Directory and file name, finds the correspondent
		   file_id
		@param $directory string
		@param $name string
		@result int the file_id in repository
		*/
		function get_id_from_path($directory,$name)
		{
			$where = array(
				'directory' => $directory,
				'name' => $name
			);

			$this->db->select('phpgw_vfs2_files','file_id',$where,
				__LINE__,__FILE__);
	
			if ($this->db->next_record())
			{
				return $this->db->Record['file_id'];
			}

			return false;
		}
		
		//the inverse way of $this->get_id_from_path
		function get_path_from_id($id)
		{
			$where = array(
				'file_id' => $id
			);

			$this->db->select('phpgw_vfs2_files','directory,name',$where,
				__LINE__,__FILE__);
	
			if ($this->db->next_record())
			{
				return $this->db->Record['directory'].'/'.$this->db->Record['name'];
			}

			return false;
		}

		/*
		 * See vfs_shared
		 */
		function get_journal ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'type'	=> False
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string' => $p->fake_full_path,
					'relatives' => array ($p->mask)
				)))
			{
				return False;
			}

			//find the file_id from a file based on directory and name
			$file_id = $this->get_id_from_path($p->fake_leading_dirs_clean,$p->fake_name_clean);


			if ($file_id === false)
			{
				return false;
			}
			return $this->vfs_versionsystem->get_journal($file_id);
		}

		/*
		 * See vfs_shared
		 */
		function acl_check ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'operation'	=> PHPGW_ACL_READ,
					'must_exist'	=> False
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			/* Accommodate special situations */
			if ($this->override_acl || $data['relatives'][0] == RELATIVE_USER_APP || $GLOBALS['phpgw_info']['user']['apps']['admin'])
			{
				return True;
			}

			if (!$data['owner_id'])
			{
				$p = $this->path_parts (array(
						'string'	=> $data['string'],
						'relatives'	=> array ($data['relatives'][0])
					)
				);

				/* Temporary, until we get symlink type files set up */
				if ($p->outside)
				{
					return True;
				}

				/* Read access is always allowed here, but nothing else is */
				if ($data['string'] == '/' || $data['string'] == $this->fakebase)
				{
					if ($data['operation'] == PHPGW_ACL_READ)
					{
						return True;
					}
					else
					{
						return False;
					}
				}

				/* If the file doesn't exist, we get ownership from the parent
				 * directory */
				if (!$this->file_exists (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask)
					))
				)
				{
					if ($data['must_exist'])
					{
						return False;
					}

					$data['string'] = $p->fake_leading_dirs;
					$p2 = $this->path_parts (array(
							'string'	=> $data['string'],
							'relatives'	=> array ($p->mask)
						)
					);

					if (!$this->file_exists (array(
							'string'	=> $data['string'],
							'relatives'	=> array ($p->mask)
						))
					)
					{
						return False;
					}
				}
				else
				{
					$p2 = $p;
				}
				/*
				   We don't use ls () to get owner_id as we normally would,
				   because ls () calls acl_check (), which would create an infinite loop
				*/
				$query = $GLOBALS['phpgw']->db->query ("SELECT owner_id FROM phpgw_vfs2_files WHERE directory='".
					$GLOBALS['phpgw']->db->db_addslashes($p2->fake_leading_dirs_clean)."' AND name='".
					$GLOBALS['phpgw']->db->db_addslashes($p2->fake_name_clean)."'", __LINE__, __FILE__);
				$GLOBALS['phpgw']->db->next_record ();

				$owner_id = $GLOBALS['phpgw']->db->Record['owner_id'];
			}
			else
			{
				$owner_id = $data['owner_id'];
			}

			/* This is correct.  The ACL currently doesn't handle undefined values correctly */
			if (!$owner_id)
			{
				$owner_id = 0;
			}

			$user_id = $GLOBALS['phpgw_info']['user']['account_id'];

			/* They always have access to their own files */
			if ($owner_id == $user_id)
			{
				return True;
			}

				
			#viniciuscb: Check if the file is inside a dir which is an application dir. 
			#            If so, see if the dir has permissions in vfs_sharing. If not, 
			#            inherit permissions given by the source application

			if ($this->is_appfolder($p2->fake_full_path))
			{
				$file_id = $this->get_file_id(array(
					'string'    => $p2->fake_full_path,
					'relatives' => array($p2->mask)));
	
				$rights = $this->vfs_sharing->get_file_permissions($GLOBALS['phpgw_info']['user']['account_id'],$file_id);

				if ($rights & $data['operation'])
				{
					return true;
				}
				
				$exp_path = explode('/',$p2->fake_full_path);
				$appname = $exp_path[2];
				$id = $exp_path[3];

				//get hooks to know node permission
				$resp = $GLOBALS['phpgw']->hooks->process(array(
					'location' => 'files_info',
					'account_id' => $GLOBALS['phpgw_info']['user']['account_id']
					));

				return ($resp[$appname][$id]['permissions'] & $data['operation']);
			}

			#viniciuscb: rethink the group files role and working schema
			/* Check if they're in the group */
			$memberships = $GLOBALS['phpgw']->accounts->membership ($user_id);

			if (is_array ($memberships))
			{
				foreach ($memberships as $group_array)
				{
					if ($owner_id == $group_array['account_id'])
					{
						$group_ok = 1;
						break;
					}
				}
			}

			$acl = CreateObject ('phpgwapi.acl', $owner_id);
			$acl->account_id = $owner_id;
			$acl->read_repository ();

			$file_id = $this->get_file_id(array(
				'string'    => $p2->fake_full_path,
				'relatives' => array($p2->mask)));

			$rights = $this->vfs_sharing->get_file_permissions($user_id,$file_id);

			if ($this->Debug)
			{
				echo "<br>\nRIGHTS OF THE FILE ".$p2->fake_full_path." ARE=$rights<br>\n";
			}
			
			if ($rights & $data['operation'])
			{
				return True;
			}
			elseif (!$rights && $group_ok)
			{
				$conf =& CreateObject('phpgwapi.config', 'phpgwapi');
				$conf->read_repository();
				if ($conf->config_data['acl_default'] == 'grant')
				{
					return True;
				}
				else
				{
					return False;
				}
			}
			else
			{
				return False;
			}
		}

		/*
		 * See vfs_shared
		 */
		function read ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_READ
				))
			)
			{
				return False;
			}

			$conf =& CreateObject('phpgwapi.config', 'phpgwapi');
			$conf->read_repository();
			if ($this->file_actions || $p->outside)
			{
				if ($fp = fopen ($p->real_full_path, 'rb'))
				{
					$contents = fread ($fp, filesize ($p->real_full_path));
					fclose ($fp);
				}
				else
				{
					$contents = False;
				}
			}
			else
			{
				$ls_array = $this->ls (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask),
					)
				);

				$contents = $ls_array[0]['content'];
			}

			return $contents;
		}

		/*
		 * See vfs_shared
		 */
		function write ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'content'	=> ''
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if ($this->file_exists (array (
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask)
				))
			)
			{
				$acl_operation = PHPGW_ACL_EDIT;
				$journal_operation = VFS_OPERATION_EDITED;
			}
			else
			{
				$acl_operation = PHPGW_ACL_ADD;
			}

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> $acl_operation
				))
			)
			{
				return False;
			}

			umask(0177);

			/*
			 * If 'string' doesn't exist, touch () creates both the file and
			 * the database entry If 'string' does exist, touch () sets the
			 * modification time and modified by
			*/
			$this->touch (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask)
				)
			);

			$conf =& CreateObject('phpgwapi.config', 'phpgwapi');
			$conf->read_repository();


			$file_id = $this->get_id_from_path($p->fake_leading_dirs_clean,$p->fake_name_clean);

			//Saves a snapshot in the journal
			if ($journal_operation)
			{
				$this->vfs_versionsystem->save_snapshot($file_id,$journal_operation);
			}

			if ($this->file_actions)
			{
				if ($fp = fopen ($p->real_full_path, 'wb'))
				{
					fwrite ($fp, $data['content'], strlen ($data['content']));
					fclose ($fp);
					$write_ok = 1;
				}
			}

			if ($write_ok || !$this->file_actions)
			{

				
				if ($this->file_actions)
				{
					$set_attributes_array = array(
						'size' => filesize ($p->real_full_path)
					);
				}
				else
				{
					$set_attributes_array = array (
						'size'	=> strlen ($data['content']),
						'content'	=> $data['content']
					);
				}

				if ($journal_operation)
				{
					$this->vfs_versionsystem->commit($file_id);
				}



				$this->set_attributes (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'attributes'	=> $set_attributes_array
				));

				return True;
			}
			else
			{
				return False;
			}
		}

		/*
		 * See vfs_shared
		 */
		function touch ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array(
				'relatives'	=> array (RELATIVE_CURRENT)
			);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$account_id = $GLOBALS['phpgw_info']['user']['account_id'];
			$currentapp = $GLOBALS['phpgw_info']['flags']['currentapp'];

			$p = $this->path_parts (array(
				'string'	=> $data['string'],
				'relatives'	=> array ($data['relatives'][0])
			));

			umask (0177);

			if ($this->file_actions)
			{
				/*
				   PHP's touch function will automatically decide whether to
				   create the file or set the modification time
				*/
				$rr = @touch ($p->real_full_path);

				if ($p->outside)
				{
					return $rr;
				}
			}

			/* We, however, have to decide this ourselves */
			if ($this->file_exists (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask)
				))
			)
			{
				if (!$this->acl_check (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask),
						'operation'	=> PHPGW_ACL_EDIT
					)))
				{
					return False;
				}

				$vr = $this->set_attributes (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask),
						'attributes'	=> array(
									'modifiedby_id' => $account_id,
									'modified' => $this->now
								)
						)
					);
			}
			else
			{
				if (!$this->acl_check (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask),
						'operation'	=> PHPGW_ACL_ADD
					))
				)
				{
					return False;
				}

				//TODO VCB insert other fields

				$insert_data = array(
					'owner_id' => $this->working_id,
					'directory' => $p->fake_leading_dirs_clean,
					'name' => $p->fake_name_clean
				);

				$query = $this->db->insert('phpgw_vfs2_files',$insert_data,$insert_data,__LINE__,__FILE__);

				$file_id = $this->get_id_from_path($p->fake_leading_dirs_clean,$p->fake_name_clean);

				$this->vfs_versionsystem->create_initial_version($file_id);

				$this->set_attributes(array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'attributes'	=> array (
								'createdby_id' => $account_id,
								'created' => $this->now,
								'size' => 0,
								'deleteable' => 'Y',
								'app' => $currentapp
							)
					)
				);

				$this->correct_attributes (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask)
					)
				);
			}

			if ($rr || $vr || $query)
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		/*
		 * See vfs_shared
		 * If $data['symlink'] the file is symlinked instead of copied
		 */
		function cp ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT, RELATIVE_CURRENT),
					'journal' => true
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$account_id = $GLOBALS['phpgw_info']['user']['account_id'];

			$f = $this->path_parts (array(
					'string'	=> $data['from'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			$t = $this->path_parts (array(
					'string'	=> $data['to'],
					'relatives'	=> array ($data['relatives'][1])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask),
					'operation'	=> PHPGW_ACL_READ
				))
			)
			{
				trigger_error('vfs->cp: could not copy file from '.$f->fake_full_path.' to '.$t->fake_full_path.'. Without permission to read from source location. Line '.__LINE__.', File '.__FILE__,E_USER_NOTICE);
				return False;
			}

			if ($exists = $this->file_exists (array(
					'string'	=> $t->fake_full_path,
					'relatives'	=> array ($t->mask)
				))
			)
			{
				if (!$this->acl_check (array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask),
						'operation'	=> PHPGW_ACL_EDIT
					))
				)
				{
					trigger_error('vfs->cp: could not copy file from '.$f->fake_full_path.' to '.$t->fake_full_path.'. Without permission to edit destination. Line '.__LINE__.', File '.__FILE__,E_USER_NOTICE);
					return False;
				}
			}
			else
			{
				if (!$this->acl_check (array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask),
						'operation'	=> PHPGW_ACL_ADD
					))
				)
				{

					trigger_error('vfs->cp: could not copy file from '.$f->fake_full_path.' to '.$t->fake_full_path.'. Without permission to create new file. Line '.__LINE__.', File '.__FILE__,E_USER_NOTICE);
					return False;
				}
			}

			umask(0177);

			if ($this->file_type (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask)
				)) != 'Directory'
			)
			{
				if ($this->file_actions)
				{
					if (@$data['symlink'])
					{
						if ($exists)
						{
							@unlink($t->real_full_path);
						}
						if (!symlink($f->real_full_path, $t->real_full_path))
						{
							trigger_error('vfs->cp: could not copy file from '.$f->fake_full_path.' to '.$t->fake_full_path.'. Without permission to create symbolic link. Line '.__LINE__.', File '.__FILE__,E_USER_NOTICE);
							return False;
						}
					}
					elseif (!copy ($f->real_full_path, $t->real_full_path))
					{
						trigger_error('vfs->cp: could not copy file from '.$f->fake_full_path.' to '.$t->fake_full_path.'. Line '.__LINE__.', File '.__FILE__,E_USER_NOTICE);
						return False;
					}

					$size = filesize ($t->real_full_path);
				}
				else
				{
					$content = $this->read (array(
							'string'	=> $f->fake_full_path,
							'relatives'	=> array ($f->mask)
						)
					);


					$size = strlen ($content);
				}

				if ($t->outside)
				{
					return True;
				}

				$ls_array = $this->ls (array(
						'string'	=> $f->fake_full_path,
						'relatives'	=> array ($f->mask),
						'checksubdirs'	=> False,
						'mime_type'	=> False,
						'nofiles'	=> True
					)
				);
				$record = $ls_array[0];

				if ($this->file_exists (array(
						'string'	=> $data['to'],
						'relatives'	=> array ($data['relatives'][1])
					))
				)
				{ //Overwrite

/*
					$query = $GLOBALS['phpgw']->db->query ("UPDATE phpgw_vfs SET owner_id='$this->working_id', directory='".
						$GLOBALS['phpgw']->db->db_addslashes($t->fake_leading_dirs_clean)."', name='".
						$GLOBALS['phpgw']->db->db_addslashes($t->fake_name_clean)."' WHERE owner_id='$this->working_id' AND directory='".
						$GLOBALS['phpgw']->db->db_addslashes($t->fake_leading_dirs_clean)."' AND name='".
						$GLOBALS['phpgw']->db->db_addslashes($t->fake_name_clean)."'", __LINE__, __FILE__);*/

					$ls_array_dest = $this->ls (array(
							'string'	=> $t->fake_full_path,
							'relatives'	=> array ($t->mask),
							'checksubdirs'	=> False,
							'mime_type'	=> False,
							'nofiles'	=> True
						)
					);
					$record_dest = $ls_array_dest[0];

					$this->vfs_versionsystem->save_snapshot($record_dest['file_id'],VFS_OPERATION_EDITED,'Overwritten by copy of '.$f->fake_full_path_clean);
		
					$set_attributes_array = array (
						'createdby_id' => $account_id,
						'created' => $this->now,
						'size' => $size,
						'mime_type' => $record['mime_type'],
						'deleteable' => $record['deleteable'],
						'comment' => $record['comment'],
						'app' => $record['app']
					);

					if (!$this->file_actions)
					{
						$set_attributes_array['content'] = $content;
					}

					$res = $this->set_attributes(array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask),
						'attributes'	=> $set_attributes_array
						)
					);

					if ($res)
						$this->vfs_versionsystem->commit($record_dest['file_id']);
				}
				else //Create a new file
				{
					$this->touch (array(
							'string'	=> $t->fake_full_path,
							'relatives'	=> array ($t->mask)
						)
					);

					$set_attributes_array = array (
						'createdby_id' => $account_id,
						'created' => $this->now,
						'size' => $size,
						'mime_type' => $record['mime_type'],
						'deleteable' => $record['deleteable'],
						'comment' => $record['comment'],
						'app' => $record['app']
					);

					if (!$this->file_actions)
					{
						$set_attributes_array['content'] = $content;
					}

					$this->set_attributes(array(
							'string'	=> $t->fake_full_path,
							'relatives'	=> array ($t->mask),
							'attributes'	=> $set_attributes_array
						)
					);
				}
				$this->correct_attributes (array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask)
					)
				);
			}
			else	/* It's a directory */
			{
				/* First, make the initial directory */
				$this->mkdir (array(
						'string'	=> $data['to'],
						'relatives'	=> array ($data['relatives'][1])
					)
				);

				/* Next, we create all the directories below the initial directory */
				foreach($this->ls (array(
						'string'	=> $f->fake_full_path,
						'relatives'	=> array ($f->mask),
						'checksubdirs'	=> True,
						'mime_type'	=> 'Directory'
					)) as $entry)
				{
					$newdir = ereg_replace ("^$f->fake_full_path", "$t->fake_full_path", $entry['directory']);
					$this->mkdir (array(
							'string'	=> $newdir.'/'.$entry['name'],
							'relatives'	=> array ($t->mask)
						)
					);
				}

				/* Lastly, we copy the files over */
				foreach($this->ls (array(
						'string'	=> $f->fake_full_path,
						'relatives'	=> array ($f->mask)
					)) as $entry)
				{
					if ($entry['mime_type'] == 'Directory')
					{
						continue;
					}

					$newdir = ereg_replace ("^$f->fake_full_path", "$t->fake_full_path", $entry['directory']);
					$this->cp (array(
							'from'	=> "$entry[directory]/$entry[name]",
							'to'	=> "$newdir/$entry[name]",
							'relatives'	=> array ($f->mask, $t->mask)
						)
					);
				}
			}

			if (!$f->outside)
			{

				$ls_array = $this->ls(array(
					'string'    => $f->fake_full_path,
					'relatives' => $f->mask
				));

				$file = $ls_array[0];

				$this->vfs_versionsystem->save_snapshot($file['file_id'],VFS_OPERATION_COPIED,'',array('dest' =>$t->fake_full_path));

				$this->vfs_versionsystem->commit();

			}

			return True;
		}


		/*
		 * See vfs_shared
		 */
		function mv ($data)
		{
			//FIXME unknown bug tricky solving (temp)
			if (!is_object($this->vfs_versionsystem))
			{
				$this->vfs_versionsystem =& $GLOBALS['object_keeper']->GetObject('phpgwapi.vfs_versionsystem');
			}

			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT, RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$account_id = $GLOBALS['phpgw_info']['user']['account_id'];

			$f = $this->path_parts (array(
					'string'	=> $data['from'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			$t = $this->path_parts (array(
					'string'	=> $data['to'],
					'relatives'	=> array ($data['relatives'][1])
				)
			);

			if ($f->fake_full_path == $t->fake_full_path)
			{
				return true;
			}

			if (!$this->acl_check (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask),
					'operation'	=> PHPGW_ACL_READ
				))
				|| !$this->acl_check (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask),
					'operation'	=> PHPGW_ACL_DELETE
				))
			)
			{
				trigger_error('vfs->mv: could not move file from '.$f->fake_full_path.' to '.$t->fake_full_path.'. Not allowed to delete the file from its source location. Line '.__LINE__.', File '.__FILE__,E_USER_NOTICE);
				return False;
			}

			if (!$this->acl_check (array(
					'string'	=> $t->fake_full_path,
					'relatives'	=> array ($t->mask),
					'operation'	=> PHPGW_ACL_ADD
				))
			)
			{
				trigger_error('vfs->mv: could not move file from '.$f->fake_full_path.' to '.$t->fake_full_path.'. Not allowed to add a file in destination location. Line '.__LINE__.', File '.__FILE__,E_USER_NOTICE);
				return False;
			}

			if ($this->file_exists (array(
					'string'	=> $t->fake_full_path,
					'relatives'	=> array ($t->mask)
				))
			)
			{
				if (!$this->acl_check (array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask),
						'operation'	=> PHPGW_ACL_EDIT
					))
				)
				{
					trigger_error('vfs->mv: could not move file from '.$f->fake_full_path.' to '.$t->fake_full_path.'. Not allowed to edit existent file in destination location. Line '.__LINE__.', File '.__FILE__,E_USER_NOTICE);
					return False;
				}
			}

			umask (0177);

			/* We can't move directories into themselves */
			if (($this->file_type (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask)
				) == 'Directory'))
				&& ereg ("^$f->fake_full_path", $t->fake_full_path)
			)
			{
				if (($t->fake_full_path == $f->fake_full_path) || substr ($t->fake_full_path, strlen ($f->fake_full_path), 1) == '/')
				{
					trigger_error('vfs->mv: could not move file from '.$f->fake_full_path.' to '.$t->fake_full_path.'. Trying to write in invalid location. Line '.__LINE__.', File '.__FILE__,E_USER_NOTICE);
					return False;
				}
			}

			if ($this->file_exists (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask)
				))
			)
			{

				/* We get the listing now, because it will change after we update the database */
				$ls = $this->ls (array(
						'string'	=> $f->fake_full_path,
						'relatives'	=> array ($f->mask)
					)
				);

				//to the new version system.
				$ls_fileonly = $this->ls(array(
					'string'      => $f->fake_full_path,
					'relatives'   => array ($f->mask),
					'checksudirs' => false,
					'nofiles'     => true
				));


				$this->vfs_versionsystem->save_snapshot(
					$ls_fileonly[0]['file_id'],VFS_OPERATION_MOVED,
					'Moved from '.$f->fake_full_path.' to '.$t->fake_full_path,
					array('dest' => $t->fake_full_path));

				if ($this->file_exists (array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask)
					))
				)
				{
					$this->rm (array(
							'string'	=> $t->fake_full_path,
							'relatives'	=> array ($t->mask)
						)
					);
				}

				/*
				 *  We add the journal entry now, before we delete.  This way
				 *  the mime_type field will be updated to 'journal-deleted'
				 *  when the file is actually deleted
				 */
				if (!$f->outside)
				{
					//add_journal was here			
				}

				/*
				   If the from file is outside, it won't have a database entry,
				   so we have to touch it and find the size
				*/
				if ($f->outside)
				{
					$size = filesize ($f->real_full_path);

					$this->touch (array(
							'string'	=> $t->fake_full_path,
							'relatives'	=> array ($t->mask)
						)
					);
					$query = $GLOBALS['phpgw']->db->query ("UPDATE phpgw_vfs2_files SET size=$size WHERE directory='".
						$GLOBALS['phpgw']->db->db_addslashes($t->fake_leading_dirs_clean)."' AND name='".
						$GLOBALS['phpgw']->db->db_addslashes($t->fake_name_clean)."'", __LINE__, __FILE__);
				}
				elseif (!$t->outside)
				{

					$query = $GLOBALS['phpgw']->db->query ("UPDATE phpgw_vfs2_files SET name='".
						$GLOBALS['phpgw']->db->db_addslashes($t->fake_name_clean)."', directory='".
						$GLOBALS['phpgw']->db->db_addslashes($t->fake_leading_dirs_clean)."' WHERE directory='".
						$GLOBALS['phpgw']->db->db_addslashes($f->fake_leading_dirs_clean)."' AND name='".
						$GLOBALS['phpgw']->db->db_addslashes($f->fake_name_clean)."'", __LINE__, __FILE__);
				}


/*				$this->set_attributes(array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask),
						'attributes'	=> array (
									'modifiedby_id' => $account_id,
									'modified' => $this->now
								)
					)
				);*/

/*				$this->correct_attributes (array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask)
					)
				);*/

				if ($this->file_actions)
				{
					$rr = rename ($f->real_full_path, $t->real_full_path);
				}

				/*
				   This removes the original entry from the database The actual
				   file is already deleted because of the rename () above
				*/
				if ($t->outside)
				{
					$this->rm (array(
							'string'	=> $f->fake_full_path,
							'relatives'	=> $f->mask
						)
					);
				}
				
			}
			else
			{
				trigger_error('vfs->mv: could not move file from '.$f->fake_full_path.' to '.$t->fake_full_path.'. Source file not found. Line '.__LINE__.', File '.__FILE__,E_USER_NOTICE);
				return False;
			}
			
			if ($this->file_type (array(
					'string'	=> $t->fake_full_path,
					'relatives'	=> array ($t->mask)
				)) == 'Directory'
			)
			{
				/* We got $ls from above, before we renamed the directory */
				foreach ($ls as $entry)
				{
					$newdir = ereg_replace ("^$f->fake_full_path", $t->fake_full_path, $entry['directory']);
					$newdir_clean = $this->clean_string (array ('string' => $newdir));

					$query = $GLOBALS['phpgw']->db->query ("UPDATE phpgw_vfs2_files SET directory='".
						$GLOBALS['phpgw']->db->db_addslashes($newdir_clean)."' WHERE file_id='$entry[file_id]'",
						__LINE__, __FILE__);
					$this->correct_attributes (array(
							'string'	=> "$newdir/$entry[name]",
							'relatives'	=> array ($t->mask)
						)
					);
				}

			}


			$this->vfs_versionsystem->commit($ls_fileonly[0]['file_id']);

/*			$this->add_journal (array(
					'string'	=> $t->fake_full_path,
					'relatives'	=> array ($t->mask),
					'operation'	=> VFS_OPERATION_MOVED,
					'state_one'	=> $f->fake_full_path,
					'state_two'	=> $t->fake_full_path
				)
			);*/
			return True;
		}

		/*
		 * See vfs_shared
		 */
		function rm ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_DELETE
				))
			)
			{
				return False;
			}

			if (!$this->file_exists (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				))
			)
			{
				if ($this->file_actions)
				{
					$rr = unlink ($p->real_full_path);
				}
				else
				{
					$rr = True;
				}

				if ($rr)
				{
					return True;
				}
				else
				{
					return False;
				}
			}

			if ($this->file_type (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)) != 'Directory'
			)
			{
				$ls_array = $this->ls(array(
					'string'    => $p->fake_full_path_clean,
					'relatives' => $p->mask
				));

				$file = $ls_array[0];
	
				$this->vfs_versionsystem->save_snapshot($file['file_id'],VFS_OPERATION_DELETED);

				$query = $GLOBALS['phpgw']->db->query ("DELETE FROM phpgw_vfs2_files WHERE directory='".
					$GLOBALS['phpgw']->db->db_addslashes($p->fake_leading_dirs_clean)."' AND name='".
					$GLOBALS['phpgw']->db->db_addslashes($p->fake_name_clean)."'", __LINE__, __FILE__);

				if ($query)
					$this->vfs_versionsystem->commit($file['file_id']);

				if ($this->file_actions)
				{
					$rr = unlink ($p->real_full_path);
				}
				else
				{
					$rr = True;
				}

				if ($query || $rr)
				{
					return True;
				}
				else
				{
					return False;
				}
			}
			else
			{
				$ls = $this->ls (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask)
					)
				);

				/* First, we cycle through the entries and delete the files */
				foreach($ls as $entry)
				{
					if ($entry['mime_type'] == 'Directory')
					{
						continue;
					}

					$this->rm (array(
							'string'	=> "$entry[directory]/$entry[name]",
							'relatives'	=> array ($p->mask)
						)
					);
				}

				/* Now we cycle through again and delete the directories */
				foreach ($ls as $entry)
				{
					if ($entry['mime_type'] != 'Directory')
					{
						continue;
					}

					/* Only the best in confusing recursion */
					$this->rm (array(
							'string'	=> "$entry[directory]/$entry[name]",
							'relatives'	=> array ($p->mask)
						)
					);
				}

				/* If the directory is linked, we delete the placeholder directory */
				$ls_array = $this->ls (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask),
						'checksubdirs'	=> False,
						'mime_type'	=> False,
						'nofiles'	=> True
					)
				);
				$link_info = $ls_array[0];

				if ($link_info['link_directory'] && $link_info['link_name'])
				{
					$path = $this->path_parts (array(
							'string'	=> $link_info['directory'] . '/' . $link_info['name'],
							'relatives'	=> array ($p->mask),
							'nolinks'	=> True
						)
					);

					if ($this->file_actions)
					{
						rmdir ($path->real_full_path);
					}
				}

				$file = $link_info;
	
				$this->vfs_versionsystem->save_snapshot($file['file_id'],VFS_OPERATION_DELETED);

				$query = $GLOBALS['phpgw']->db->query ("DELETE FROM phpgw_vfs2_files WHERE directory='".
					$GLOBALS['phpgw']->db->db_addslashes($p->fake_leading_dirs_clean)."' AND name='".
					$GLOBALS['phpgw']->db->db_addslashes($p->fake_name_clean)."'",
					__LINE__, __FILE__);

				if ($query)
					$this->vfs_versionsystem->commit();

				if ($this->file_actions)
				{
					rmdir ($p->real_full_path);
				}

				return True;
			}
		}

		/*
		 * See vfs_shared
		 */
		function mkdir ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$account_id = $GLOBALS['phpgw_info']['user']['account_id'];
			$currentapp = $GLOBALS['phpgw_info']['flags']['currentapp'];

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_ADD)
				)
			)
			{
				echo "can't create dir ".$p->fake_full_path." due to permissions.";
				return False;
			}

			/* We don't allow /'s in dir names, of course */
			if (ereg ("/", $p->fake_name))
			{
				return False;
			}

			umask (077);

			if ($this->file_actions)
			{
				if (!@is_dir($p->real_leading_dirs_clean))	// eg. /home or /group does not exist
				{
					if (!@$this->mkdir(array(
						'string' => $p->fake_leading_dirs,
						'relatives' => array(RELATIVE_NONE) )))	// ==> create it
					{
						return False;
					}
				}	

				if (@is_dir($p->real_full_path))	// directory already exists
				{
					$this->update_real($data,True);		// update its contents
				}
				elseif (!@mkdir ($p->real_full_path, 0770))
				{
					return False;
				}
			}

			if (!$this->file_exists (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask)
				))
			)
			{
				$query = $GLOBALS['phpgw']->db->query ("INSERT INTO phpgw_vfs2_files (owner_id, name, directory) VALUES ($this->working_id, '".
					$GLOBALS['phpgw']->db->db_addslashes($p->fake_name_clean)."', '".
					$GLOBALS['phpgw']->db->db_addslashes($p->fake_leading_dirs_clean)."')", __LINE__, __FILE__);
	
				$this->set_attributes(array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'attributes'	=> array (
								'createdby_id' => $account_id,
								'size' => 4096,
								'mime_type' => 'Directory',
								'created' => $this->now,
								'deleteable' => 'Y',
								'app' => $currentapp
							)
					)
				);

				$this->correct_attributes (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask)
					)
				);

				//Get info to the versionsystem
				$res = $this->ls(array(
					'string'    => $p->fake_full_path_clean,
					'relatives' => $p->mask
				));

				$file = $res[0];

				$this->vfs_versionsystem->create_initial_version($file['file_id']);


			}
			else
			{
				return False;
			}

			return True;
		}

		/*
		 * See vfs_shared
		 */
		function make_link ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT, RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$account_id = $GLOBALS['phpgw_info']['user']['account_id'];
			$currentapp = $GLOBALS['phpgw_info']['flags']['currentapp'];

			$vp = $this->path_parts (array(
					'string'	=> $data['vdir'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			$rp = $this->path_parts (array(
					'string'	=> $data['rdir'],
					'relatives'	=> array ($data['relatives'][1])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $vp->fake_full_path,
					'relatives'	=> array ($vp->mask),
					'operation'	=> PHPGW_ACL_ADD
				))
			)
			{
				return False;
			}


			if ((!$this->file_exists (array(
					'string'	=> $rp->real_full_path,
					'relatives'	=> array ($rp->mask)
				)))
				&& !mkdir ($rp->real_full_path, 0770))
			{
				return False;
			}

			if (!$this->mkdir (array(
					'string'	=> $vp->fake_full_path,
					'relatives'	=> array ($vp->mask)
				))
			)
			{
				return False;
			}

			$size = $this->get_size (array(
					'string'	=> $rp->real_full_path,
					'relatives'	=> array ($rp->mask)
				)
			);

			$this->set_attributes(array(
					'string'	=> $vp->fake_full_path,
					'relatives'	=> array ($vp->mask),
					'attributes'	=> array (
								'link_directory' => $rp->real_leading_dirs,
								'link_name' => $rp->real_name,
								'size' => $size
							)
				)
			);

			$this->correct_attributes (array(
					'string'	=> $vp->fake_full_path,
					'relatives'	=> array ($vp->mask)
				)
			);

			return True;
		}

		/*
		 * See vfs_shared
		 */
		//upd 2004-09-16 viniciuscb: custom fields support
		//upd 2004-10-11 viniciuscb: proper_id for file: accepts:
		//               $data['proper_id'], for an all-ready proper id, else
		//               $data['prefix'] for a string with the prefix
		//                  (this can only be used if no proper_id 
		//                   specified now or before)
		//               $data['ptype'] For a specification of file type
		//               $data['prefix_type'] (FUTURE)
		//               if none specified, prefix will be user_lid
		function set_attributes ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'attributes'	=> array ()
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			/*
			 * This is kind of trivial, given that set_attributes () can change
			 * owner_id, size, etc.
			*/
			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_EDIT
				))
			)
			{
				return False;
			}

			if (!$this->file_exists (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				))
			)
			{
				return False;
			}

			/*
			 * All this voodoo just decides which attributes to update
			 * depending on if the attribute was supplied in the 'attributes'
			 * array
			*/

			$ls_array = $this->ls (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'checksubdirs'	=> False,
					'nofiles'	=> True
				)
			);
			$record = $ls_array[0];

			//handles mime type

			/* FIXME is mime_type is application/octet-stream, don't believe
			 * in passed mime_type and try to find a mime type based in 
			 * extension */
			/* TODO use mime magic capabilities */
			$mime_id = '';
			if ($data['attributes']['mime_type'] && $data['attributes']['mime_type'] != 'application/octet-stream')
			{
				$mime_data = array (
					'mime' => $data['attributes']['mime_type'],
					'extension' => @$this->get_file_extension($data['string'])
				);

				if (!$type = $this->vfs_mimetypes->get_type($mime_data))
				{
					$type = $this->vfs_mimetypes->add_filetype($mime_data);
				}
			}
			//try to find a compatible mime/type based in file extension
			else
			{
				$mime_data = array (
					'extension' => @$this->get_file_extension($data['string'])
				);

				if (!$type = $this->vfs_mimetypes->get_type($mime_data))
				{
					$type = $this->vfs_mimetypes->add_filetype($mime_data);

				}

				/* Finally if the file has no extension and no mime (or mime
				 * defined as application/octet-stream, will consider file type
				 * as application/octet-stream 
				 */
				if (!$type)
				{
					$type = $this->vfs_mimetypes->get_type(array(
						'mime' => 'application/octet-stream'
					));
				}

			}

			//will only change mime_type if mime_type was specified in attribts
			if ($data['attributes']['mime_type'])
			{
				unset($data['attributes']['mime_type']);
				$data['attributes']['mime_id'] = $type['mime_id'];
			}

			/*
			   Indicate that the EDITED_COMMENT operation needs to be journaled,
			   but only if the comment changed
			*/
			if (array_key_exists('comment',$data['attributes']) &&
				 $data['attributes']['comment'] != $record['comment'])
			{
				$edited_comment = 1;
			}

			#** proper id treating **
			
			#1.User does not specified proper_id, and file had not any proper id
			#generates a new proper_id
			if(!$data['attributes']['proper_id'] && $data['attributes']['prefix'])
			{
				$prefix = $data['attributes']['prefix'];

//				$data['attributes']['proper_id'] = $this->generate_proper_id($data['attributes']['prefix'],$type['proper_id']);
				$data['attributes']['proper_id'] = $this->generate_proper_id($data['attributes']['prefix'],$data['attributes']['ptype']);
			}
			#2.User specified proper_id
			#check if this id is not being used. If it is, do not change.
			elseif ($data['attributes']['proper_id'])
			{
				$this->db->select('phpgw_vfs2_files','proper_id',array('proper_id'=>$data['attributes']['proper_id']));

				if ($this->db->next_record())
				{
					unset($data['attributes']['proper_id']);
				}

			}

			//To be sure that olny fields from phpgw_vfs2_files will be inserted
			$update_data = array();
			foreach ($data['attributes'] as $key => $val)
			{
				if ($this->attribute_tables[$key] == 'phpgw_vfs2_files' || $key == 'mime_id')
					$update_data[$key] = $val;
			}

			$where = array(
				'file_id' => $record['file_id']
			);

			if ($edited_comment)
			{
				$this->vfs_versionsystem->save_snapshot($record['file_id'],
					VFS_OPERATION_EDITED_COMMENT);
			}

			if (count($update_data)) //if false, there is nothing to do
			{
				$res = $this->db->update('phpgw_vfs2_files',$update_data,$where,
					__LINE__,__FILE__);

				if ($res) 
				{
					//custom fields storing
					$customfields = $this->vfs_customfields->get_customfields('customfield_name');
					foreach ($customfields as $custom_name => $custom_val)
					{
						if (array_key_exists($custom_name,$data['attributes']))
						{
							$store_array[$record['file_id']][$custom_name] = $data['attributes'][$custom_name];

						}
					}

					if ($store_array)
					{
						$this->vfs_customfields->store_fields($store_array);
					}
				
					if ($edited_comment)
					{
						$this->vfs_versionsystem->commit($record['file_id']);
					}
					return True;
				}
			}
			return false;
		}

		/*!
		@function correct_attributes
		@abstract Set the correct attributes for 'string' (e.g. owner)
		@param string File/directory to correct attributes of
		@param relatives Relativity array
		@result Boolean True/False
		*/
		function correct_attributes ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if ($p->fake_leading_dirs != $this->fakebase && $p->fake_leading_dirs != '/')
			{
				$ls_array = $this->ls (array(
						'string'	=> $p->fake_leading_dirs,
						'relatives'	=> array ($p->mask),
						'checksubdirs'	=> False,
						'nofiles'	=> True
					)
				);
				$set_attributes_array = Array(
					'owner_id' => $ls_array[0]['owner_id']
				);
			}
			elseif (preg_match ("+^$this->fakebase\/(.*)$+U", $p->fake_full_path, $matches))
			{
				$set_attributes_array = Array(
					'owner_id' => $GLOBALS['phpgw']->accounts->name2id ($matches[1])
				);
			}
			else
			{
				$set_attributes_array = Array(
					'owner_id' => 0
				);
			}

			$this->set_attributes (array(
					'string'	=> $p->fake_full_name,
					'relatives'	=> array ($p->mask),
					'attributes'	=> $set_attributes_array
				)
			);

			return True;
		}

		/*
		 * See vfs_shared
		 */
		function file_type ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_READ,
					'must_exist'	=> True
				))
			)
			{
				return False;
			}

			if ($p->outside)
			{
				if (is_dir ($p->real_full_path))
				{
					return ('Directory');
				}

				/*
				   We don't return an empty string here, because it may still
				   match with a database query because of linked directories
				*/
			}

			/*
			   We don't use ls () because it calls file_type () to determine if
			   it has been passed a directory
			*/

			//TODO VCB change this also with filetypes class

			$db2 = $GLOBALS['phpgw']->db;

			$db2->query ("SELECT mime_id 
			              FROM   phpgw_vfs2_files
			              WHERE  directory='".$db2->db_addslashes($p->fake_leading_dirs_clean)."' 
			               AND   name='".$db2->db_addslashes($p->fake_name_clean)."'", __LINE__, __FILE__); 
			$db2->next_record ();

			$file_record = $db2->Record;

			$mime = $this->vfs_mimetypes->get_type(array(
				'mime_id' => $file_record['mime_id']
			));

			$mime_type = $mime['mime'];

			if(!$mime_type)
			{
				$extension = $this->get_file_extension($p->fake_name_clean);
				
				if (!$res = $this->vfs_mimetypes->get_type(array(
						'extension' => $extension)) )
				{
					$res = $this->vfs_mimetypes->add_filetype(array(
						'extension' => $extension)); 

					if ($res)
					{
						$this->db->update('phpgw_vfs2_files',
							array('mime_id'   => $res['mime_id']),
							array('directory' => $p->fake_leading_dirs_clean,
								  'name'      => $p->fake_name_clean
							),__LINE__,__FILE__);
					}
							
				}
				$mime_type = $res['mime'];

			}

			return $mime_type;
		}

		/*
		 * See vfs_shared
		 */
		function file_exists ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'allow_outside' => true
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if ($p->outside)
			{
				if (!$data['allow_outside'])
				{
					return false;
				}
				$rr = file_exists ($p->real_full_path);
				
				return $rr;
			}

			//TODO id: primary field
			$db2 =& $GLOBALS['phpgw']->db;
			$db2->query ("SELECT name FROM phpgw_vfs2_files WHERE directory='".
				$GLOBALS['phpgw']->db->db_addslashes($p->fake_leading_dirs_clean)."' AND name='".
				$GLOBALS['phpgw']->db->db_addslashes($p->fake_name_clean)."'", __LINE__, __FILE__);
			
			if ($db2->num_rows())
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		/*
		 * See vfs_shared
		 */
		function get_size ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'checksubdirs'	=> True
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_READ,
					'must_exist'	=> True
				))
			)
			{
				return False;
			}

			/*
			   WIP - this should run through all of the subfiles/directories in the directory and tally up
			   their sizes.  Should modify ls () to be able to return a list for files outside the virtual root
			*/
			if ($p->outside)
			{
				$size = filesize ($p->real_full_path);

				return $size;
			}

			foreach($this->ls (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'checksubdirs'	=> $data['checksubdirs'],
					'nofiles'	=> !$data['checksubdirs']
				)) as $file_array)
			{
				/*
				   Make sure the file is in the directory we want, and not
				   some deeper nested directory with a similar name
				*/
/*
				if (@!ereg ('^' . $file_array['directory'], $p->fake_full_path))
				{
					continue;
				}
*/

				$size += $file_array['size'];
			}

			//TODO VCB update this when id be primary key
			if ($data['checksubdirs'])
			{
				$query = $GLOBALS['phpgw']->db->query ("SELECT size FROM phpgw_vfs2_files WHERE directory='".
					$GLOBALS['phpgw']->db->db_addslashes($p->fake_leading_dirs_clean)."' AND name='".
					$GLOBALS['phpgw']->db->db_addslashes($p->fake_name_clean)."'",__LINE__,__FILE__);
				$GLOBALS['phpgw']->db->next_record ();
				$size += $GLOBALS['phpgw']->db->Record[0];
			}

			return $size;
		}

		/*!
		@function checkperms
		@abstract Check if $this->working_id has write access to create files in $dir
		@discussion Simple call to acl_check
		@param string Directory to check access of
		@param relatives Relativity array
		@result Boolean True/False
		*/
		function checkperms ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_ADD
				))
			)
			{
				return False;
			}
			else
			{
				return True;
			}
		}

		/*
		 * See vfs_shared
		 * If $data['readlink'] then a readlink is tryed on the real file
		 * If $data['file_id'] then the file_id is used instead of a path
		 */
		//upd 2004-09-16 viniciuscb: Support for custom fields
		function ls ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'checksubdirs'	=> True,
					'mime_type'	=> False,
					'nofiles'	=> False,
					'orderby'	=> 'directory,name',
					'backups'   => false, /* show or hide backups */
					'files_specified' => array(),
					'allow_outside' => true
				);

			//check if orderby is a valid field (or is composed by valid fields)
			//this prevents a sql error that happens when sort field is invalid.
			if ($data['orderby'])
			{
				$fields = explode(',',$data['orderby']);
				foreach ($fields as $field_name)
				{
					if ($this->attribute_tables[trim($field_name)] != 'phpgw_vfs2_files')
					{
						unset($data['orderby']);
						break;
					}
				}
			}

			$data = array_merge ($this->default_values ($data, $default_values), $data);


			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);
			$dir = $p->fake_full_path;

			//Abiliy to show or not backup files -> put it in sql queries
			$sql_backups = ($data['backups']) ? '' : ' AND is_backup=\'N\' ';


			/* If they pass us a file or 'nofiles' is set, return the info for $dir only */
			if (@$data['file_id']
				|| ((($type = $this->file_type (array(
					'string'	=> $dir,
					'relatives'	=> array ($p->mask)
				)) != 'Directory'))
				|| ($data['nofiles'])) && !$p->outside
			)
			{

				/* SELECT all, the, attributes */
				$sql = 'SELECT ';

				foreach ($this->attributes as $num => $attribute)
				{
					if ($this->attribute_tables[$attribute] == 'phpgw_vfs2_files')
					{
						if ($num)
						{
							$sql .= ', ';
						}

						$sql .= $attribute;
					}
				}

				$sql .= ",mime_id,is_backup FROM phpgw_vfs2_files WHERE ";


				if (@$data['file_id'])
				{
					$sql .= 'file_id='.(int)$data['file_id'].$sql_backups;
				}
				else
				{
					$sql .= "directory='".$GLOBALS['phpgw']->db->db_addslashes($p->fake_leading_dirs_clean).
						"' AND name='".$GLOBALS['phpgw']->db->db_addslashes($p->fake_name_clean)."'".$sql_backups;
				}
//				echo " select1: dir=".$p->fake_leading_dirs_clean." name=".$p->fake_name_clean." <br>\n";
				$query = $GLOBALS['phpgw']->db->query ($sql, __LINE__, __FILE__);

				$GLOBALS['phpgw']->db->next_record ();
				$record = $GLOBALS['phpgw']->db->Record;

				if (!$record)
				{
					return array();
				}

				/* We return an array of one array to maintain the standard */
				$rarray = array ();
				foreach($this->attributes as $attribute)
				{
					if ($attribute == 'mime_type')
					{
						if (!is_numeric($record['mime_id'])) 
						{
							//no mime type registered for file, must find one and if not exist add one.
							$extension = $this->get_file_extension($record['name']);

							if (!$res = $this->vfs_mimetypes->get_type(array('extension' => $extension)))
							{
								$res = $this->vfs_mimetypes->add_filetype(array('extension' => $extension));
							}

							if ($res)
							{
								$this->db->update('phpgw_vfs2_files',
									array('mime_id'   => $res['mime_id']),
									array('directory' => $p->fake_leading_dirs_clean,
										  'name'      => $p->fake_name_clean
									),__LINE__,__FILE__);

							}
						}
						else
						{
							$res = $this->vfs_mimetypes->get_type(array(
								'mime_id' => $record['mime_id']
							));
						}

						$record['mime_type'] = $res['mime'];
						$record['mime_friendly'] = $res['friendly'];
					}


					$rarray[0][$attribute] = $record[$attribute];
				}
				if ($this->file_actions && @$data['readlink'])	// test if file is a symlink and get it's target
				{
					$rarray[0]['symlink'] = @readlink($p->real_full_path);
				}

				//handle custom fields
				reset($rarray);
				while(list($key,$val) = each($rarray))
				{
					$custom =& $this->vfs_customfields->get_fields_by_fileid($val['file_id']);

					if ($custom)
					{
						$rarray[$key] = array_merge($val,$custom);
					}
				}

				return $rarray;
			}

			//WIP - this should recurse using the same options the virtual part of ls () does
			/* If $dir is outside the virutal root, we have to check the file system manually */
			if ($p->outside)
			{
				if (!$data['allow_outside'])
				{
					return false;
				}

				if ($this->file_type (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask)
					)) == 'Directory'
					&& !$data['nofiles']
				)
				{
					$dir_handle = opendir ($p->real_full_path);
					while ($filename = readdir ($dir_handle))
					{
						if ($filename == '.' || $filename == '..')
						{
							continue;
						}

						$rarray[] = $this->get_real_info (array(
								'string'	=> $p->real_full_path . SEP . $filename,
								'relatives'	=> array ($p->mask)
							)
						);
					}
				}
				else
				{
					$rarray[] = $this->get_real_info (array(
							'string'	=> $p->real_full_path,
							'relatives'	=> array ($p->mask)
						)
					);
				}

				//handle custom fields
				reset($rarray);
				while(list($key,$val) = each($rarray))
				{
					$custom =& $this->vfs_customfields->get_fields_by_fileid($val['file_id']);

					if ($custom)
					{
						$rarray[$key] = array_merge($val,$custom);
					}
				}

				return $rarray;
			}

			/* $dir's not a file, is inside the virtual root, and they want to check subdirs */
			/* SELECT all, the, attributes FROM phpgw_vfs WHERE file=$dir */

//			echo "DIR_CLEAN: ".$dir_clean."<BR>";

			$dir_clean = $this->clean_string (array ('string' => $dir));


			//This query must be fast and only bring things with the correct
			//permissions. So, I'll get the list of all groups the current user
			//belongs, get the list of the current dir and all parents over it
			//and pass all these things in one query.

			//gets the id of all groups $account_id belongs to
			$groups = $GLOBALS['phpgw']->accounts->membership($GLOBALS['phpgw_info']['user']['account_id']);

			foreach($groups as $group)
			{
				$accounts[] = $group['account_id'];
			}

			$accounts[] = $GLOBALS['phpgw_info']['user']['account_id'];
			$accounts[] = 0; //default permission for all users

			$paths = array();
			
			$dir_exploded = explode('/',$dir_clean);
			$dir_parts = count($dir_parts);

			for ($i=$dir_parts-1;$i>=0;$i--)
			{	
				$res = implode('/',array_slice($dir_explode,0,$i));
				$paths[] = "'".($res) ? $res : '/'."'";
			}

			if (!$data['backups'])
			{
				$append .= " AND fls.is_backup = 'N'";
			}


			if ($data['mime_type'])
			{
				$other_where .= " AND mime.mime = '".$data['mime_type']."'";
				$mime_join = ' INNER JOIN phpgw_vfs2_mimetypes mime ON fls.mime_id = mime.mime_id';
			}

			//orderby
			$orderby = explode(',',$data['orderby']);

			foreach($orderby as $key =>$orderby_instance)
			{
				$orderby[$key] = 'fls.'.$orderby_instance;
			}


			$append .= ' ORDER BY '.implode(',',$orderby);

			$dir_rule = $data['checksubdirs'] ? "fls.directory LIKE '".$dir_clean."%'" : "fls.directory = '".$dir_clean."'";
			//SQL to get all files in current dir, except those ones that user
			//does not have permission to see
			$sql = "SELECT	DISTINCT fls.*
					FROM	phpgw_vfs2_files fls
					$mime_join
					LEFT JOIN phpgw_vfs2_shares sh ON fls.file_id = sh.file_id
					WHERE	".$dir_rule." AND
							(   (sh.account_id in (".implode(',',$accounts).") AND fls.shared = 'Y') OR 
								(sh.account_id is NULL AND fls.shared = 'N') OR
								fls.owner_id = ".$GLOBALS['phpgw_info']['user']['account_id']." )
							$other_where $append";

			
//			echo "<xmp>\n$sql\n</xmp>";

			$query = $GLOBALS['phpgw']->db->query ($sql, __LINE__, __FILE__);

			$rarray = array ();
			for ($i = 0; $GLOBALS['phpgw']->db->next_record (); $i++)
			{
				$record = $GLOBALS['phpgw']->db->Record;


				/* Further checking on the directory.  This makes sure /home/user/test won't match /home/user/test22 */
				if (@!ereg ("^$dir(/|$)", $record['directory']))
				{
					continue;
				}

				/* If they want only this directory, then $dir should end without a trailing / */
				if (!$data['checksubdirs'] && ereg ("^$dir/", $record['directory']))
				{
					continue;
				}

				foreach($this->attributes as $attribute)
				{
					if ($attribute == 'mime_type')
					{
						if (!is_numeric($record['mime_id']))
						{
							$extension = $this->get_file_extension($record['name']);
							if(!$res = $this->vfs_mimetypes->get_type(array(
									'extension' => $extension)) )
							{
								$res = $this->vfs_mimetypes->add_filetype(array(
									'extension'
								));

								if ($res)
								{
									$this->db->update('phpgw_vfs2_files',
										array('mime_id'   => $res['mime_id']),
										array('directory' => $p->fake_leading_dirs_clean,
											  'name'      => $p->fake_name_clean
										),__LINE__,__FILE__);
								}

							}
						}
						else
						{
							$res = $this->vfs_mimetypes->get_type(array(
								'mime_id' => $record['mime_id']
							));
						}

						$record['mime_type'] = $res['mime'];
						$rarray[$i]['mime_friendly'] = $res['friendly'];
					}

					$rarray[$i][$attribute] = $record[$attribute];
				}
			}

			//handle custom fields
			reset($rarray);
			while(list($key,$val) = each($rarray))
			{
				$custom =& $this->vfs_customfields->get_fields_by_fileid($val['file_id']);

				if ($custom)
				{
					$rarray[$key] = array_merge($val,$custom);
				}
			}

			return $rarray;
		}

		/*
		 * See vfs_shared
		 */
		function update_real ($data,$recursive = False)
		{
			//FIXME this method does not work when there are registrys in
			//database, but not in filesystem. It starts corromping the
			//database by putting wrong things that are in the partition root.
			return false;
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (file_exists ($p->real_full_path))
			{
				if (is_dir ($p->real_full_path))
				{
					$dir_handle = opendir ($p->real_full_path);
					while ($filename = readdir ($dir_handle))
					{
						if ($filename == '.' || $filename == '..')
						{
							continue;
						}

						$rarray[] = $this->get_real_info (array(
								'string'	=> $p->fake_full_path . '/' . $filename,
								'relatives'	=> array (RELATIVE_NONE)
							)
						);
					}
				}
				else
				{
					$rarray[] = $this->get_real_info (array(
							'string'	=> $p->fake_full_path,
							'relatives'	=> array (RELATIVE_NONE)
						)
					);
				}

				if (!is_array ($rarray))
				{
					$rarray = array ();
				}

				foreach($rarray as $num => $file_array)
				{
					$p2 = $this->path_parts (array(
							'string'	=> $file_array['directory'] . '/' . $file_array['name'],
							'relatives'	=> array (RELATIVE_NONE)
						)
					);

					/* Note the mime_type.  This can be "Directory", which is
					 * how we create directories */
					$set_attributes_array = Array(
						'size' => $file_array['size'],
						'mime_type' => $file_array['mime_type']
					);

					if (!$this->file_exists (array(
							'string'	=> $p2->fake_full_path,
							'relatives'	=> array (RELATIVE_NONE)
						))
					)
					{
						$this->touch (array(
								'string'	=> $p2->fake_full_path,
								'relatives'	=> array (RELATIVE_NONE)
							)
						);
					}
					$this->set_attributes (array(
							'string'	=> $p2->fake_full_path,
							'relatives'	=> array (RELATIVE_NONE),
							'attributes'	=> $set_attributes_array
						)
					);
					if ($recursive && $file_array['mime_type'] == 'Directory')
					{
						$dir_data = $data;
						$dir_data['string'] = $file_array['directory'] . '/' . $file_array['name'];
						$this->update_real($dir_data,$recursive);
					}
				}
			}
		}

		/*!
		 * @function regenerate_database 
		 * @abstract This function regenerates the full database. It is like
		 *  the function update_real, but instead, it works for all files in a
		 *  folder, fixing all broken regs in a database (if it exists), or
		 *  recreating it.  
		 * @author Vinicius Cubas Brand
		 */
		function regenerate_database()
		{

		}

		/* Helper functions */

		/* This fetchs all available file system information for string (not using the database) */
		function get_real_info ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (is_dir ($p->real_full_path))
			{
				$mime_type = 'Directory';
			}
			else
			{
				$mime_type = $this->get_ext_mime_type (array(
						'string'	=> $p->fake_name
					)
				);

/*				if($mime_type)
				{
					$GLOBALS['phpgw']->db->query ("UPDATE phpgw_vfs2_files as file, phpgw_vfs2_mimetypes as mime SET mime.mime_id=file.mime_id WHERE mime.mime='".$mime_type."' AND file.directory='".
						$GLOBALS['phpgw']->db->db_addslashes($p->fake_leading_dirs_clean)."' AND file.name='".
						$GLOBALS['phpgw']->db->db_addslashes($p->fake_name_clean)."'", 
						__LINE__, __FILE__);
				}*/
			}

			$size = filesize ($p->real_full_path);
			$rarray = array(
				'directory' => $p->fake_leading_dirs,
				'name' => $p->fake_name,
				'size' => $size,
				'mime_type' => $mime_type
			);

			return ($rarray);
		}


		/*!
		 * @function search
		 * @abstract This function returns the files that have a field with
		 *           keyword
		 *
		 * @author Vinicius Cubas Brand
		 */
		function search($keyword,$fields=null,$is_backup='N')
		{
			//the fields in which the keyword will be searched
			$searchable_fields = array(
				'created',
				'comment',
				'app',
				'directory',
				'name',
				'link_directory',
				'link_name',
				'version',
				'proper_id'
			);

			if (is_array($fields))
			{
				$tmp = array_diff($searchable_fields,$fields);
				$searchable_fields = array_diff($searchable_fields,$tmp);
			}

			foreach ($searchable_fields as $val)
			{
				$sf[] = $val." LIKE '%$keyword%'";
			}
			
			$where = implode(' OR ',$sf);
			
			$sql = "SELECT file_id
			        FROM   phpgw_vfs2_files
					WHERE  $where
					  AND  is_backup = '$is_backup'";
			
			$this->db->query($sql,__LINE__,__FILE__);

			$res = array();
			while ($this->db->next_record())
			{
				$res[] = $this->db->Record['file_id'];
			}

			//search in the custom fields
			$res = array_unique(array_merge($res,$this->vfs_customfields->search_files($keyword,$fields)));

			sort($res);

			return $res;
		}

		/*!
		 * @function get_file_extension
		 * @abstract This function returns the file extension for a file.
		 *
		 * @author Vinicius Cubas Brand
		 */
		function get_file_extension($filename)
		{
			$ext = explode('.',$filename);
			
			$bla = array_pop($ext);

			if (count($ext))
			{
				return $bla;
			}

			return '';
		}

		/*!
		 * @function get_file_id
		 * @abstract This function returns the file id for a file, false on eror
		 * @param string, relatives
		 *
		 * @author Vinicius Cubas Brand
		 */
		function get_file_id($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
						'string'	=> $data['string'],
						'relatives'	=> array ($data['relatives'][0])
					)
			);

			$sql = "SELECT file_id
			        FROM phpgw_vfs2_files
					WHERE directory='".$this->db->db_addslashes($p->fake_leading_dirs_clean)."' 
				      AND name='".$this->db->db_addslashes($p->fake_name_clean)."'";
				
			$query = $this->db->query ($sql , __LINE__, __FILE__);
			$this->db->next_record ();

			return $this->db->Record['file_id'];
		}

		/*!
		 * @function id2name
		 * @abstract This function returns an array with all information about
		 * a file
		 * @param string, relatives
		 *
		 * @author Vinicius Cubas Brand
		 */
		function id2name($id)
		{
			$res = $this->db->select('phpgw_vfs2_files','*',
				array('file_id'=>$id),__LINE__,__FILE__);

			if($this->db->next_record())
			{
				$result = $this->db->Record;

				$res = $this->vfs_mimetypes->get_type(array(
									'mime_id' => $result['mime_id']
								));

					$result['mime_type'] = $res['mime'];
					$result['mime_friendly'] = $res['friendly'];

					return $result;
			}

			return array();
		}

		//TODO should generate a new name if archive exists, or have some other
		//sort of smart error handling.
		function compress($data)
		{
			$compression_handlers = array(
//				'gz'  => 'gzip',
//				'bz2' => 'bzip',
//				'tar' => 'tar',
				'zip' => 'zip'
				);

			if (!is_array($data['files']) || !$data['name'])
			{
				return false;
			}

			$default_values = array
			(
				'type'          => 'zip',
				'relatives'     => array(RELATIVE_CURRENT),
				'prefix'        => $GLOBALS['phpgw_info']['user']['account_lid'],
				'ptype'         => ''
			);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			if (!$data['type'] || !array_key_exists($data['type'],$compression_handlers))
			{
				//error: inexistent type given
				return false;
			}


			//put extension in archive name, if not exists
			if ($this->get_file_extension($data['name']) != $data['type'])
			{
				if ($data['type'] == 'zip' || $data['type'] == 'tar')
				{
					$data['name'] .= '.' . $data['type'];
				}
				else
				{
					$data['name'] .= '.tar.' . $data['type'];
				}
			}

			//last element in array $data['relatives'] is the id of destiny
			$dest_relativity = (count($data['relatives']))?array_pop($data['relatives']):RELATIVE_CURRENT;

			$dest = $this->path_parts(array(
				'string' => $data['name'],
				'relatives' => $dest_relativity
			));

			if ($this->file_exists(array(
				'string' => $dest->fake_full_path,
				'relatives' => $dest->mask	)))
			{
				//overwrite handling

				//check if acl allow overwriting
				if (!$this->acl_check(array(
					'string' => $dest->fake_full_path,
					'relatives' => $dest->mask,
					'operation' => PHPGW_ACL_EDIT))
					)
				{
					//error: cannot overwrite (HANDLE THIS)
					return false;
				}
			}
			else
			{	
				//Add handling

				//check acl
				if (!$this->acl_check(array(
					'string' => $dest->fake_leading_dirs,
					'relatives' => $dest->mask,
					'operation' => PHPGW_ACL_ADD))
					)
				{
					//error: cannot add in dir
					return false;
				}
			}

			$count_rels = count($data['relatives']);

			reset($data['relatives']);
			foreach($data['files'] as $filename)
			{
				if (!($relative = current($data['relatives'])))
				{
					$relative = RELATIVE_CURRENT;
				}
			
				$p = $this->path_parts(array(
					'string' => $filename,
					'relatives' => array($relative)
				));

				if ($this->acl_check(array(
					'string' => $p->fake_full_path,
					'relatives' => $p->mask,
					'operation' => PHPGW_ACL_READ,
					'must_exist' => true )) )
				{
					$filenames[] = $p->real_full_path;
				}
				else
				{
					//catch error: file not exists or no permission
				}

				next($data['relatives']);
			}

			//Save file in tmp folder, then move it to its definitive place.
			//This will handle file overwrites and journalling entries.

			$tmp_dir =  $GLOBALS['phpgw_info']['server']['temp_dir'];
			$tmp_filename = $tmp_dir.'/'.$this->random_filename();

			$archive_opts = array(
				'basedir' =>  $dest->real_leading_dirs,
				'inmemory' => 0,
				'overwrite' => 1, //provisory, todo error handling
				'recurse' => 1,
				'storepaths' => 1,
				'level' => 9//compression level, 9 is max, 0 is none, 3 ok
			);

/*			#Didn't work - class.archive.inc.php
			$compression_class = $compression_handlers[$data['type']].'_file';

			require_once(PHPGW_API_INC.'/class.archive.inc.php');	

			$arch = new $compression_class($tmp_filename);
			$arch->set_options($archive_opts);
			$arch->add_files($filenames);
			$arch->create_archive();

			$type_array = $this->vfs_mimetypes->get_type(array('extension'=>$data['type']));
			$arch_mime = $type_array['mime'];*/


			switch($data['type'])
			{
				case 'zip':
					$zip =& CreateObject('phpgwapi.PclZip',$tmp_filename);

					//FIXME not $dest->real_leading_dirs, but the path to be
					//removed from files
					$zip->create($filenames,PCLZIP_OPT_REMOVE_PATH,$dest->real_leading_dirs);
					$arch_mime = 'application/x-zip-compressed';
					break;

				default:
				/*
					$tar =& CreateObject('phpgwapi.Archive_Tar',$tmp_filename,$data['type']);
					//FIXME not $dest->real_leading_dirs, but the path to be
					//removed from files
					if (!$tar->createModify($filenames,'',$dest->real_leading_dirs.'/'))
					{
						//TODO throw error
					}
					$arch_mime = 'application/x-gzip';
					break;
				*/
			}

			/* VOILA! now the archive is created!!! but it is yet in /tmp and
			 * have no entry in the database and is not in its correct dir.
			 * The next and final step is then to make these tasks. */

			$dest_relativity = $data['relatives'][count($data['relatives'])-1];

			# ----------------------------------------------------------- #
			# All code under here copied from filescenter->bo->fileUpload #
			# ----------------------------------------------------------- #

			$file_comment = "Archive contents:\n\n".implode("\n",$data['files']);
			$file_comment = substr(0,255,$file_comment);

			# Check to see if the file exists in the database, and get
			# its info at the same time
			$ls_array = $this->ls(array(
				'string'=> $data['name'],
				'relatives'	=> array($dest_relativity),
				'checksubdirs'	=> False,
				'nofiles'	=> True
			));

			$fileinfo = $ls_array[0];

			if($fileinfo['name'])
			{
				if($fileinfo['mime_type'] == 'Directory')
				{
					$messages[]= $GLOBALS['phpgw']->common->error_list(array(lang('Cannot replace %1 because it is a directory', $fileinfo['name'])));
					return false;
				}
			}

			#overwriting
			if($fileinfo['name'] && $fileinfo['deleteable'] != 'N')
			{
				$tmp_arr=array(
					'string'=> $data['name'],
					'relatives'	=> array($dest_relativity),
					'attributes'	=> array(
						'owner_id' => $GLOBALS['phpgw_info']['user']['account_id'],
						'modifiedby_id' => $GLOBALS['phpgw_info']['user']['account_id'], 
						'modified' => date('Y-m-d'),
						'mime_type' => $arch_mime,
						'size' => filesize($tmp_filename),
						'deleteable' => 'Y',
						'comment' => $file_comment,
						'prefix' => $data['prefix'],
						'ptype' => $data['ptype']
						#if overwriting, do not change.
						#TODO rethink/decide policy for that
						#'prefix' => $otherelms['prefix'.$file_number])
						
					)
				);
				$this->set_attributes($tmp_arr);

				$tmp_arr=array(
					'from'	=> $tmp_filename,
					'to'	=> $data['name'],
					'relatives'	=> array(RELATIVE_NONE|VFS_REAL, $dest_relativity)
				);
				$this->cp($tmp_arr);

			}
			else #creating a new file
			{
				$this->cp(array(
					'from'=> $tmp_filename,
					'to'=> $data['name'],
					'relatives'	=> array(RELATIVE_NONE|VFS_REAL, $dest_relativity)
				));

				$this->set_attributes(array(
					'string'=> $data['name'],
					'relatives'	=> array($dest_relativity),
					'attributes'=> array(
						'comment' => $file_comment,
						'mime_type' => $arch_mime,
						'prefix' => $data['prefix'],
						'ptype' => $data['ptype']
						
					)
				));

			}
		}


		#two parts: 1: extract files in tmp_dir.  2: move files to dir, adding
		# them in db
		function extract($data)
		{
			$compression_handlers = array(
				'gz'  => 'gzip',
				'bz2' => 'bzip',
				'tar' => 'tar',
				'zip' => 'zip'
				);


			if (!$data['name'] || !$data['dest'])
			{
				return false;
			}

			$default_values = array
			(
				'relatives'	=> array(RELATIVE_CURRENT,RELATIVE_CURRENT),
				'prefix'    => $GLOBALS['phpgw_info']['user']['account_lid'],
				'ptype'     => ''
				
			);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			if (!$data['type'])
			{
				$data['type'] = strtolower($this->get_file_extension($data['name']));
			}

			if (!$data['type'] || !array_key_exists($data['type'],$compression_handlers))
			{
				//error: inexistent type given
				return false;
			}


			$arch = $this->path_parts (array(
				'string' => $data['name'],
				'relatives' => array ($data['relatives'][0])
			));

			$dest = $this->path_parts (array(
				'string' => $data['dest'],
				'relatives' => array_pop($data['relatives'])
			));

			//Extract files in tmp folder, then move it to its definitive place.
			//This will handle file overwrites and journalling entries.

			$tmp_dir =  $GLOBALS['phpgw_info']['server']['temp_dir'];
			$tmp_dirname = $tmp_dir.'/'.$this->random_filename();


			$tmp_dest = $this->path_parts(array(
				'string' => $tmp_dirname,
				'relatives' => array(RELATIVE_NONE|VFS_REAL)
				));

			if ($this->file_exists(array(
				'string'=> $tmp_dest->fake_full_path,
				'relatives' => $tmp_dest->mask )))
			{
				$this->rm(array(
					'string' => $tmp_dest->fake_full_path,
					'relatives' => $tmp_dest->mask 
				));
			}

			$this->mkdir(array(
				'string' => $tmp_dest->fake_full_path,
				'relatives' => array($tmp_dest->mask)
			));


			# see if user has add permission in destination folder
			if (!$this->acl_check(array(
				'string'     => $dest->fake_full_path,
				'relatives'  => $dest->mask,
				'operation'  => PHPGW_ACL_ADD,
				'must_exist' => true ))
				)
			{
				return false; //TODO error handling
			}

			#extract files
			switch ($data['type'])
			{
				case 'zip':
					$zip =& CreateObject('phpgwapi.PclZip',$arch->real_full_path);
					if (!$zip->extract(PCLZIP_OPT_PATH,$tmp_dest->real_full_path,PCLZIP_OPT_SET_CHMOD,DEFAULT_LOW_PERMS))
					{
						return false; //TODO handle error
					}
					break;
				default:
					return false;
				/*

					$archive_opts = array(
						'basedir' =>  $tmp_dest->real_full_path,
						'overwrite' => 1 //provisory, todo error handling
					);

					$compression_class = $compression_handlers[$data['type']].'_file';
					$archive_obj = new $compression_class($arch->real_full_path);
					$archive_obj->set_options($archive_opts);
					$archive_obj->extract_files();
					*/

	/*				$tar =& CreateObject('phpgwapi.Archive_Tar',$arch->real_full_path);
					if (!$tar->extract($tmp_dest->real_full_path))
					{
						return false; //TODO handle error
					}
					break;*/
				default:
					return false; //TODO handle error
			}

			#refresh db
			$filelist = $this->ls(array(
				'string'    => $tmp_dest->fake_full_path,
				'relatives' => array($tmp_dest->mask),
				'checksubdirs' => true,
				'nofiles' => false
			));

			foreach ($filelist as $file)
			{
				

				$this->mv(array(
					'from' => $file['directory'].'/'.$file['name'],
					'to'   => $dest->fake_full_path.'/'.$file['name'],
					'relatives' => array(RELATIVE_NONE|VFS_REAL,$dest->mask)
				));

				$this->set_attributes(array(
					'string' => $dest->fake_full_path.'/'.$file['name'],
					'relatives' => array($dest->mask),
					'attributes' => array(
						'prefix' => $data['prefix'],
						'ptype'  => $data['ptype'],
						'mime_type' => $file['mime_type'],
						'size' => $file['size']
					)
				));
			}

			return true;
		}

		/*!
		 * @function random_filename()
		 * @abstract Generates a Random Filename
		 *
		 * @result string
		 */
		function random_filename()
		{
			$filename = '';
			$filename_length = 8;
			while (strlen($filename) < $filename_length) {
				$filename .= chr(rand (97,122));
			}

			return $filename.'.tmp';
		}

		function generate_proper_id($owner_name,$filetype_identifier)
		{
			$prefix = $owner_name.'-'.$filetype_identifier.'-';
			#$prefix = 'P-' . $this->year . '-';

			$prefix = str_replace('--','-',$prefix);

			$qry = "select max(proper_id) from phpgw_vfs2_files where proper_id like ('$prefix%') AND LENGTH(proper_id) <= ".(strlen($prefix)+4);

//			echo $qry;
//			exit();

			$this->db->query("select max(proper_id) from phpgw_vfs2_files where proper_id like ('$prefix%') AND LENGTH(proper_id) <= ".(strlen($prefix)+4));
			$this->db->next_record();
			$max = $this->add_leading_zero(array_pop(explode('-',$this->db->f(0))));
			return $prefix . $max;
		}

		function add_leading_zero($num)  
		{
/*			if ($id_type == "hex")
			{
				$num = hexdec($num);
				$num++;
				$num = dechex($num);
			}
			else
			{
				$num++;
			} */

			$num++;

			if (is_numeric($num))
			{
				if (strlen($num) == 4)
					$return = $num;
				if (strlen($num) == 3)
					$return = "0$num";
				if (strlen($num) == 2)
					$return = "00$num";
				if (strlen($num) == 1)
					$return = "000$num";
				if (strlen($num) == 0)
					$return = "0001";
			}
			else
			{
				$return = $num;
			}

			return strtoupper($return);
		}

			
		//import function from old vfs
		function import_vfs()
		{
			$filename = PHPGW_API_INC.'/class.vfs_sql.inc.php';
			
			if (function_exists('file_get_contents'))
			{
				$file_vfs_sql = file_get_contents($filename);
			}
			else
			{
				$fp = fopen($filename,'r');
				$file_vfs_sql = fread($fp,filesize($filename));
				fclose($fp);
			}
			
			$file_vfs_sql = str_replace(
				array('class vfs','function vfs','<?php','<?','?>'),
				array('class vfs_sql_old','function vfs_sql_old'),$file_vfs_sql
			);

			eval($file_vfs_sql);

			$obj_vfs_sql = new vfs_sql_old();

			$obj_vfs_sql->override_acl = true;

			$directories = $obj_vfs_sql->ls(array(
				'string' => '/',
				'relatives' => array(RELATIVE_ROOT),
				'checksubdirs' => true,
				'nofiles' => false,
				'mime_type' => 'Directory'
				));

			$global_content = $obj_vfs_sql->ls(array(
				'string' => '/',
				'relatives' => array(RELATIVE_ROOT),
				'checksubdirs' => true,
				'nofiles' => false
				));

			$obj_vfs_sql->override_acl = false;

			$this->override_acl = true;

			foreach($directories as $key => $dir)
			{
				$dirname = str_replace('//','/',$dir['directory'].'/'.$dir['name']);
				$dir_exists_in_vfs2 = $this->file_exists(array(
					'string' => $dirname,
					'relatives' => array(RELATIVE_ROOT),
					'allow_outside' => false
				));


				if ($dir_exists_in_vfs2)
				{
					$dir_is_dir = $this->file_type(array(
						'string' => $dirname,
						'relatives' => array(RELATIVE_ROOT)
					)) == 'Directory';

					if ($dir_is_dir)
					{
						//good - just add permissions to the old owner

						//1. get information about file

						$file_info = $this->ls(array(
							'string' => $dirname,
							'relatives' => array(RELATIVE_ROOT),
							'nofiles' => true,
							'checksubdirs' => false,
							'allow_outside' => false
						));

						if (!$file_info['proper_id'])
						{
							$this->set_attributes(array(
								'string' => $dirname,
								'relatives' => array(RELATIVE_ROOT),
								'attributes' => array(
									'prefix' => $GLOBALS['phpgw']->accounts->id2name($dir['owner_id']),
									'comment' => $dir['comment']
									)
							));
						}

						//if user is not the owner of the file, will
						//add him in the list of authorized personnel
						if (array_key_exists(0,$file_info) && $file_info[0]['owner_id'] != $dir['owner_id'])
						{
							$file_id = $this->get_file_id(array(
								'string' => $dirname,
								'relatives' => array(RELATIVE_ROOT)
							));

							if ($file_id != 0)
							{
								if ($dirname != "/" && $dirname != "/home")
								{
									$perms = $this->vfs_sharing->get_permissions($file_id);
									$perms[$dir['owner_id']] = PHPGW_ACL_READ | PHPGW_ACL_ADD | PHPGW_ACL_EDIT | PHPGW_ACL_DELETE;

									$this->vfs_sharing->set_permissions(array($file_id => $perms));
								}

							}
							else
							{
								//something gone wrong... This should not have
								//happened
								trigger_error('Failed in permission setting of file <b>'.$dirname.'</b> at importing procedure.');
							}
						}
						continue;
					}
					else
					{
						$this->mv(array(
							'from' => $dirname,
							'to'   => $dirname.'_renamed',
							'relatives' => array(RELATIVE_ROOT,RELATIVE_ROOT)
						));
						//error, bacuse wanting to touch a dir that is a
						//file in new vfs
					}
				}
				
				$this->mkdir(array(
					'string' => $dirname,
					'relatives' => array(RELATIVE_ROOT)
				));

				unset($dir['file_id']);

				$dir['prefix'] = $GLOBALS['phpgw']->accounts->id2name($dir['owner_id']);

				$this->set_attributes(array(
					'string' => $dirname,
					'relatives' => array(RELATIVE_ROOT),
					'attributes' => $dir
				));
			}

			foreach($global_content as $file)
			{
				$filename = str_replace('//','/',$file['directory'].'/'.$file['name']);
				$file_exists_in_vfs2 = $this->file_exists(array(
					'string' => $filename,
					'relatives' => array(RELATIVE_ROOT),
					'allow_outside' => false
				));


				if ($file['mime_type'] != 'Directory')
				{
					if ($file_exists_in_vfs2)
					{
						$file_is_dir = $this->file_type(array(
							'string' => $filename,
							'relatives' => array(RELATIVE_ROOT)
						)) == 'Directory';

						if (!$file_is_dir)
						{
							//good - just add permissions to the old owner

							//1. get information about file

							$file_info = $this->ls(array(
								'string' => $filename,
								'relatives' => array(RELATIVE_ROOT),
								'nofiles' => true,
								'checksubdirs' => false,
								'allow_outside' => false
							));

							if (!$file_info['proper_id'])
							{
								$this->set_attributes(array(
									'string' => $filename,
									'relatives' => array(RELATIVE_ROOT),
									'attributes' => array(
										'prefix' => $GLOBALS['phpgw']->accounts->id2name($file['owner_id']),
										'comment' => $file['comment']
										)
								));
							}

							//if user is not the owner of the file, will
							//add him in the list of authorized personnel
							if (array_key_exists(0,$file_info) && $file_info[0]['owner_id'] != $file['owner_id'])
							{
								$file_id = $this->get_file_id(array(
									'string' => $filename,
									'relatives' => array(RELATIVE_ROOT)
								));

								if ($file_id != 0)
								{
									if ($filename != "/" && $filename != "/home")
									{
										$perms = $this->vfs_sharing->get_permissions($file_id);
										$perms[$file['owner_id']] = PHPGW_ACL_READ | PHPGW_ACL_ADD | PHPGW_ACL_EDIT | PHPGW_ACL_DELETE;

										$this->vfs_sharing->set_permissions(array($file_id => $perms));
									}

								}
								else
								{
									//something gone wrong... This should not have
									//happened
									trigger_error('Failed in permission setting of file <b>'.$filename.'</b> at importing procedure.');
								}
							}
							continue;
						}
						else 
						{
							$this->mv(array(
								'from' => $filename,
								'to'   => $filename.'_renamed',
								'relatives' => array(RELATIVE_ROOT,RELATIVE_ROOT)
							));
							//error, bacuse wanting to touch a file that is a
							//dir in new vfs
						}
						
					}

					$this->touch(array(
						'string' => $filename,
						'relatives' => array(RELATIVE_ROOT)
					));

					unset($file['file_id']);

					$file['prefix'] = $GLOBALS['phpgw']->accounts->id2name($file['owner_id']);

					$this->set_attributes(array(
						'string' => $filename,
						'relatives' => array(RELATIVE_ROOT),
						'attributes' => $file
						
					));

				}
			}
			$this->override_acl = false;
		}

		#                                              #
		#  Functions that treat with application dirs  #
		#                                              #

		//gets the root of the application files dir
		function get_appfiles_root()
		{
			if ($this->appfiles_root)
			{
				return $this->appfiles_root;
			}
			
			$appfiles_root = '/'.$GLOBALS['phpgw_info']['server']['file_appfolder'];

			if ($appfiles_root == '/') //folder not set in setup
			{
				//see if there is a /infolog file in vfs. If have, use it. Else use /appfiles.
				$res = $this->db->select('phpgw_vfs2_files','file_id',array('directory'=>'/','name'=>'infolog'),__LINE__,__FILE__);
				if ($this->db->next_record()) //dir exists
				{
					$appfiles_root = '/infolog';
				}
				else
				{
					$appfiles_root = '/appfiles';
				}
			}
			$this->appfiles_root = $appfiles_root;
			return $appfiles_root;
		}

		//is folder belonging to an application (like for instance projects)
		//$path is the virtual path. Pass the real path here seems nonsense now.
		function is_appfolder($path) 
		{
			$app_files_root = $this->get_appfiles_root();
			
			return preg_match('/'.str_replace('/',"\\/",$app_files_root).'/',$path);
		}	
	

		/**
		 * Method: get_external_name
		 *
		 *		Given an application name and an id of this application,
		 *		returns its name.
		 */
		function get_external_name($p_appname,$p_appid)
		{
			if (!$this->external_files_info)
			{
				$this->_consult_external_files_info();
			}
			
			if (!$this->external_files_info[$p_appname][$p_appid]['caption'])
			{
				return $GLOBALS['phpgw']->hooks->single(array(
					'appname' => $p_appname,
					'location' => 'get_name',
					'id' => $p_appid
					));
			}
			else
			{
				return $this->external_files_info[$p_appname][$p_appid]['caption'];
			}
		}

		/**
		 * Method: get_external_files_info
		 *
		 *		Returns all the external files info. This is good for some applications
		 *		that need to show all the external files or all the external folders
		 */
		function &get_external_files_info()
		{
			if (!$this->external_files_info)
			{
				$this->_consult_external_files_info();
			}
			return $this->external_files_info;
		}
		
		/**
		 * Method: _consult_external_files_info
		 *
		 *		Consults the external files info and store it in the vfs 
		 *		object attribute $this->external_files_info
		 */
		function _consult_external_files_info()
		{
			//Gets the application dirs that were set as shared, to include them
			//also in the tree
			$application_shared_files = $this->vfs_sharing->get_shares(array(
				'only_dir' => $this->get_appfiles_root(),
				'account_id' => $GLOBALS['phpgw_info']['user']['account_id']
				));

			foreach($application_shared_files as $shared_file)
			{
				$shared_file = ereg_replace($this->get_appfiles_root().'\/*','',$shared_file['directory'].'/'.$shared_file['name']);
				$shared_file = ereg_replace('\/*$','',$shared_file);
				$shared_file = explode('/',$shared_file);

				$appname = $shared_file[0];
				$appid = $shared_file[1];

				$include_also[$appname][] = $appid;
			}

			$this->external_files_info = $GLOBALS['phpgw']->hooks->process(array(
				'location' => 'files_info',
				'account_id' => $this->userinfo['account_id'],
				'include_also' => $include_also
				));
		}

		/**
		 * Method: set_sharing
		 *
		 *		Sets the file sharing of ONE file to the given ACL
		 *
		 * Parameters:
		 * 
		 * 		string - the full string describing the file
		 *      relatives - the array of relativity
		 *      permissions - an array with the user id as key, and the permissions
		 * 		          (that are a boolean operation of PHPGW_ACLs) as value.
		 *		          If you want to apply a default permission to all users,
		 * 		          use the 0 id as key
		 */
		function set_sharing($params)
		{
			//sets the file attibute to be 'shared=Y'
			$this->set_attributes(array(
				'string' => $params['string'],
				'relatives' => $params['relatives'],
				'attributes' => array('shared' => 'Y')
				));

			$file_id = $this->get_file_id(array(
				'string' => $params['string'],
				'relatives' => $params['relatives']
				));

			$this->vfs_sharing->set_permissions(array($file_id => $params['permissions']));
		}


		/**
		 * Method: unset_sharing
		 *
		 *		Unsets all file sharing and ACL permissions for a file
		 *
		 * Parameters:
		 * 
		 * 		string - the full string describing the file
		 *      relatives - the array of relativity
		 */
		function unset_sharing($params)
		{
			$this->set_attributes(array(
				'string' => $params['string'],
				'relatives' => $params['relatives'],
				'attributes' => array('shared' => 'N')
				));

			$file_id = $this->get_file_id(array(
				'string' => $params['string'],
				'relatives' => $params['relatives']
				));
		
			$this->vfs_sharing->remove_all_permissions($file_id);
		}
	}
?>
