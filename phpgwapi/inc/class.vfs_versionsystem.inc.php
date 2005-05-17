<?php
  /***************************************************************************\
  * eGroupWare - File Manager                                                 *
  * http://www.egroupware.org                                                 *
  * Written by:                                                               *
  *  - Vinicius Cubas Brand <viniciuscb@users.sourceforge.net>                *
  *  sponsored by Thyamad - http://www.thyamad.com                            *
  * ------------------------------------------------------------------------- *
  * Description: File version class handler for VFS (SQL implementation v2)   *
  * ------------------------------------------------------------------------- *
  *  This program is free software; you can redistribute it and/or modify it  *
  *  under the terms of the GNU General Public License as published by the    *
  *  Free Software Foundation; either version 2 of the License, or (at your   *
  *  option) any later version.                                               *
  \***************************************************************************/

	class vfs_versionsystem
	{

		/* The high level database handler object */
//		var $db_highlevel;

		/* Stores the possible amount number of old file backups; In an
		 * inserction, this number will be verified and if there are already 
		 * $backups backed up for a file, will delete backup of the oldest
		 * (although keeping the record of operations). 0 for no backup system
		 * and -1 to infinite versions. */ 
		var $backups; 

		/* tmp dir (without end slash) to store temporary file backups (when
		 * file is snapshotted) */
		var $tmp_dir;

		/* Virtual file system base class */
		var $vfs;

		/* Stores information about snapshotted files. Array with the file_id 
		   as index. */
		var $snapshotted_files;

		/* Database handling */
		var $db;

		/* Now */
		var $now;

		var $account_id;

		var $last_saved_snapshot=-1;

		var $backup_foldername = '_backup';

		//Operations that create file backups
		var $backup_operations = array(
			VFS_OPERATION_EDITED
		);

		var $attributes = array(
			'version_id',     /* Integer. Unique to each modification. */
			'file_id',        /* Integer. Id of the file that modif. belongs.*/
			'operation',      /* Operation made in modification. */
			'modifiedby_id',  /* phpGW account_id of who last modified */
			'modified',       /* Datetime of modification, in SQL format */
			'version',        /* Version of file prior modification. */
			'comment',        /* Human-readable description of modification. */
			'backup_file_id', /* file_id of file that is a backup . */
			'backup_content', /* usable if files are stored in database. */
			'src',            /* source directory in a copy or move operation.*/
			'dest'       /* destination directory in a copy or move operation.*/
		);

		/*!
		 * @function vfs_versionsystem
		 * @abstract Object constructor
		 * @author Vinicius Cubas Brand
		 */
		function vfs_versionsystem($create_vfs=true)
		{
			//use create_vfs=false and after this use $this->set_vfs to keep the
			// same object (i.e. use reference) in $this->vfs instead of 
			// creating a new object.
			if ($create_vfs)
			{
				$this->vfs =& CreateObject('phpgwapi.vfs');
			}

			/* FIXME this takes a value defined in the filescenter
			 * configuration. Better next time to take a value from global api
			 * configuration. must fix here and in the filescenter */
			if (array_key_exists('filescenter',$GLOBALS['phpgw_info']['user']['preferences']))
			{
				$this->backups = $GLOBALS['phpgw_info']['user']['preferences']['filescenter']['vfs_backups'];
			}
			else
			{
				$this->backups = 5;
			}

			$this->snapshotted_files = array();
			$this->db =& $GLOBALS['phpgw']->db;
			$this->now = date('Y-m-d H:i:s');
			$this->account_id = $GLOBALS['phpgw_info']['user']['account_id'];
			$this->tmp_dir = $GLOBALS['phpgw_info']['server']['temp_dir'];
			
		}

		/*!
		 * @function create_initial_version()
		 * @abstract Creates a initial journal entry for a file 
		 * @description Must be used after a file has been created. Will create
		 *              an initial journal entry in the database. If somehow
		 *              the database already have any journal for that file,
		 *              this method is wrongly called and will do nothing.
		 *              Also if no file is found with that file_id, fails.
		 *
		 * @author Vinicius Cubas Brand
		 */
		function create_initial_version($file_id)
		{
			if ($GLOBALS['phpgw']->banish_journal)
			{
				return;
			}

			$GLOBALS['phpgw']->banish_journal = true;

			//See if file exists
			$this->db->select('phpgw_vfs2_files','*',
				array('file_id'=>$file_id),	__LINE__,__FILE__);

			if (!$this->db->next_record())
			{
				$GLOBALS['phpgw']->banish_journal = false;
				return false;
			}

			$file_record = $this->db->Record;

			//See if journal for the file already exists
			$this->db->select('phpgw_vfs2_versioning','*',
				array('file_id'=>$file_id),__LINE__,__FILE__);
	
			if ($this->db->next_record())
			{
				$GLOBALS['phpgw']->banish_journal = false;
				return true; //TODO error message
			}
			
			$insert_data = array(
				'file_id'       => $file_record['file_id'],
				'operation'     => VFS_OPERATION_CREATED,
				'modified'      => $this->now,
				'modifiedby_id' => $this->account_id,
				'version'       => '0.0.0.0'
			);
			
			$res = $this->db->insert('phpgw_vfs2_versioning',$insert_data,null,
				__LINE__,__FILE__);
			


			if ($res)
			{
				$GLOBALS['phpgw']->banish_journal = false;
				return true;
			}

			$GLOBALS['phpgw']->banish_journal = false;
			return false;
		}

		/*!
		 * @function save_snapshot()
		 * @abstract Saves a snapshot from a file 
		 * @description Must be called before any modification in a file. After
		 * the modification was successful, one must do a vfs_version->commit()
		 * Depending of the type of operation and how backups are set, will
		 * handle backups. If a commit is not made until the end of the script,
		 * no modifications in the journal will be saved.
		 *
		 * @param $file_id  int  The file_id 
		 * @param $operation int  A VFS_OPERATION as defined in vfs_shared file
		 * @param $other string  Its use will differ depending on the operation:
		 *    Copy,Move: $other contains the fake_full_path_clean of destination
		 *
		 * @author Vinicius Cubas Brand
		 * @result bool 
		 */
		function save_snapshot($file_id,$operation,$comment='',$other='')
		{

			//Prevent recursive reentrant when working in vfs->copy, f.inst
			if ($GLOBALS['phpgw']->banish_journal)
			{
				return;
			}

			$GLOBALS['phpgw']->banish_journal = true;

			$this->db->select('phpgw_vfs2_files','*',
				array('file_id'=>$file_id),	__LINE__,__FILE__);

			if (!$this->db->next_record())
			{

				$GLOBALS['phpgw']->banish_journal = false;
				return false;
			}

			$file_record = $this->db->Record;

			//If already snapshotted, will do a rollback in the old snapshot
			//before make a new one.
			if ($this->snapshotted_files[$file_record['file_id']])
			{
				$this->rollback($file_record['file_id']);
			}

			//Create a backup if necessary
			if ($this->backups != 0 && in_array($operation,$this->backup_operations))
			{
				$random_filename = $this->tmp_dir.SEP.$this->random_filename();

				$this->vfs->cp(array(
					'from' => $file_record['directory'].SEP.$file_record['name'],
					'to'   => $random_filename,
					'relatives' => array(RELATIVE_ROOT,RELATIVE_NONE|VFS_REAL)
				));

				$this->vfs->set_attributes(array(
					'string' => $random_filename,
					'relatives' => array(RELATIVE_NONE|VFS_REAL),
					'attributes' => array('is_backup' => 'Y')
				));

			}
		
			//backup_file_id and backup_data will be set in commit() only.
			$insert_data = array(
				'file_id' => $file_record['file_id'],
				'operation' => $operation,
				'modifiedby_id' => $this->account_id,
				'modified' => $this->now, //Datetime of entry
				'version' => $file_record['version'],
				'comment' => $comment,
			);

			if ($operation == VFS_OPERATION_COPIED || $operation == VFS_OPERATION_MOVED)
			{
				$insert_data['src'] = $file_record['directory'].'/'.$file_record['name'];
				$insert_data['dest'] = $other['dest'];

			}

			/* $file_data is the information of the file, stored in 
			 * $this->snapshotted_files. 'insert_data' have the data to be
			 * inserted in the versioning table, 'tmp_filename' is the name of
			 * the temporary backup copy, if any, and 'record' is the 
			 * information of the file before changes (that will be made between
			 * the call to save_snapshot() and the call to commit(). 
			 */
			$file_data = array(
				'insert_data' => $insert_data,
				'tmp_filename' => $random_filename,
				'record' => $file_record
			);

			$this->snapshotted_files[$file_id] = $file_data;
			$this->last_saved_snapshot = $file_id;

			$GLOBALS['phpgw']->banish_journal = false;
			return true;
		}

		/*!
		 * @function commit()
		 * @abstract Commits the creation of a journal entry
		 * @description Will have to be called after a save_snapshot is made.
		 *    If a vfs_version->save_snapshot() call is not made before, this
		 *    method does nothing. If no parameter is passed, will commit the
		 *    file from the last saved snapshot.
		 *
		 * @param $file_id  int  The file_id 
		 *
		 * @author Vinicius Cubas Brand
		 * @result bool 
		 */
		function commit($file_id=-1)
		{
			//Prevent recursive reentrant when working in vfs->copy, f.inst
			if ($GLOBALS['phpgw']->banish_journal)
			{
				return;
			}

			$GLOBALS['phpgw']->banish_journal = true;

			if ($file_id == -1)
			{
				if ($this->last_saved_snapshot == -1)
				{

					$GLOBALS['phpgw']->banish_journal = false;
					return false;
				}

				$file_id = $this->last_saved_snapshot;
			}

			if (!$this->snapshotted_files[$file_id])
			{

				$GLOBALS['phpgw']->banish_journal = false;
				return false;
			}

			$file_data = $this->snapshotted_files[$file_id];

			//if there is any backup to be made, will make these backups and
			//remove too old backup versions, as defined in configuration.
			if ($this->backups != 0 && in_array($file_data['insert_data']['operation'],$this->backup_operations))
			{

				//counts the number of stored backups
				$where = "file_id=$file_id AND (backup_file_id != NULL OR backup_file_id != 0)";

				$this->db->select('phpgw_vfs2_versioning','count(*)',$where,
					__LINE__,__FILE__);

				$this->db->next_record();

				if ($this->db->Record[0] >= $this->backups && $this->backups != -1)
				{
					//Remove old backups

					//Deletes oldest backup(s)
					$backups_to_be_deleted = $this->db->Record[0] - $this->backups + 1;

					$sql = "SELECT vers.version_id      as version_id,
					               vers.backup_file_id  as backup_file_id,
					               files.directory      as directory, 
					               files.name           as name 
					        FROM   phpgw_vfs2_versioning  as vers,
					               phpgw_vfs2_files       as files
					        WHERE  vers.file_id=$file_id 
					          AND  vers.backup_file_id = files.file_id 
					        ORDER BY vers.modified";

					$this->db->query($sql,__LINE__,__FILE__);

					for ($i = 0; $i < $backups_to_be_deleted; $i++)
					{
						//FIXME don't know why this only works 4 the 1st cycle
						$this->db->next_record();

						$version_file_id = $this->db->Record['backup_file_id'];
						$version_id = $this->db->Record['version_id'];


						$version_directory = $this->db->Record['directory'];
						$version_name = $this->db->Record['name'];

						//Removes old backup
						$this->vfs->rm(array(
							'string' => $version_directory.SEP.$version_name,
							'relatives' => array(RELATIVE_ROOT)
						));

						$versions_to_update[] = $version_id;

					}

					if ($versions_to_update)
					{
						//updates old journal			
						$update_data = array(
							'backup_file_id' => '',
							'backup_content' => ''
						);

						foreach ($versions_to_update as $key => $val)
						{
		
							$update_where = array(
								'version_id' => $val
							);

							$this->db->update('phpgw_vfs2_versioning',
								$update_data,$update_where,__LINE__,__FILE__);
						}

					}
					unset($version_id);
				}

				//create backup folder, if not exists
				//Important: the backup dir will be inside the virtual root
				$backup_foldername = $file_data['record']['directory'].SEP.$this->backup_foldername;
			
				$dir = array(
					'string' => $backup_foldername,
					'relatives' => array(RELATIVE_ROOT)
				);

				if (!$this->vfs->file_exists($dir))
				{
					$this->vfs->mkdir($dir); //TODO error messages

					$attributes=array_merge($dir,array(
						'attributes' => array(
							'is_backup' => 'Y'
						)
					));
				
					$this->vfs->set_attributes($attributes);
				}

				//create a backup filename
				$backup_filename = $this->backup_filename(
					$file_data['record']['name'],
					$file_data['insert_data']['version']
				);

				//move file from temporary location to its definitive location
				$res = $this->vfs->mv(array(
					'from' => $file_data['tmp_filename'],
					'to' => $backup_foldername.SEP.$backup_filename,
					'relatives' => array(RELATIVE_NONE|VFS_REAL,RELATIVE_ROOT)
				));

				//sets its attribute as backup
				$this->vfs->set_attributes(array(
					'string' => $backup_foldername.SEP.$backup_filename,
					'relatives' => array(RELATIVE_ROOT),
					'attributes' => array('is_backup' => 'Y')
				));

				//TODO backup content in database support

				//Fetch the backup file_id to put this information in the 
				//version table
				if ($res)
				{
					$res_ls = $this->vfs->ls(array(
						'string' => $backup_foldername.SEP.$backup_filename,
						'relatives' => RELATIVE_ROOT,
						'nofiles' => True,
						'backups' => True
					));

					if ($res_ls)
					{
						$file_data['insert_data']['backup_file_id'] = $res_ls[0]['file_id'];
					}
				}
			}

			$res = $this->db->insert('phpgw_vfs2_versioning',
				$file_data['insert_data'],null,__LINE__,__FILE__);



			if ($res)
			{
				//If operation is one of the type that increments file version
				if (in_array($file_data['insert_data']['operation'],$this->backup_operations))
				{
	
					$this->db->update('phpgw_vfs2_files',
						array('version' => $this->inc($file_data['insert_data']['version'])),
						array('file_id' => $file_data['insert_data']['file_id']),
						__LINE__, __FILE__
					);
				}

				unset($this->snapshotted_files[$file_id]);
				$this->last_saved_snapshot = -1;

				$GLOBALS['phpgw']->banish_journal = false;
				return true;
			}

			$GLOBALS['phpgw']->banish_journal = false;
			return false;
		}


		/*!
		 * @function rollback()
		 * @abstract Rollbacks the save of the snapshot
		 * @description Will have to be called after a save_snapshot is made.
		 *    If a vfs_version->save_snapshot() call is not made before, this
		 *    method does nothing. If no parameter is passed, will rollback the
		 *    file from the last saved snapshot. This method only deletes the
		 *    temporary backup file and the saved file information
		 *
		 * @param $file_id  int  The file_id 
		 *
		 * @author Vinicius Cubas Brand
		 * @result bool 
		 */
		function rollback($file_id=-1)
		{
			//Prevent recursive reentrant when working in vfs->copy, f.inst
			if ($GLOBALS['phpgw']->banish_journal)
			{
				return;
			}

			$GLOBALS['phpgw']->banish_journal = true;

			if ($file_id == -1)
			{
				if ($this->last_saved_snapshot == -1)
				{

					$GLOBALS['phpgw']->banish_journal = false;
					return false;
				}

				$file_id = $this->last_saved_snapshot;
			}

			if (!$this->snapshotted_files[$file_id])
			{

				$GLOBALS['phpgw']->banish_journal = false;
				return false;
			}

			$file_data = $this->snapshotted_files[$file_id];

			$this->vfs->rm(array(
				'string' => $file_data['tmp_filename'],
				'relatives' => array(RELATIVE_NONE | VFS_REAL)
			));

			unset($this->snapshotted_files[$file_id]);
			$this->last_saved_snapshot = -1;

			$GLOBALS['phpgw']->banish_journal = false;
			return true;
		}

		/*!
		 * @function get_journal()
		 * @abstract Returns an array with the journal for a file
		 */
		function get_journal($file_id)
		{
			//TODO support for database-only storage.
			$fields = array_diff($this->attributes,array('backup_content'));
			
			$where = 'file_id='.$file_id.' ORDER BY modified DESC, version DESC, operation DESC';

			
			$this->db->select('phpgw_vfs2_versioning',$fields,$where,
				__LINE__,__FILE__);

			while ($this->db->next_record())
			{
				$result[] = $this->db->Record;
			}
				
			return $result;	
		}


		/*!
		 * @function inc()
		 * @abstract Given a file version, increments it using the vfs
		 *   versioning pattern and returns the incremented file version.
		 *   Analyzes operation and increments the file version taking
		 *   consideration of this operation.
		 *
		 * @param $version string The file version
		 * @param $operation int Some VFS_OPERATION as defined in vfs_shared
		 *
		 * @result string
		 */
		function inc($version)
		{
			/*
			 * Let's increment the version for the file itself.  We keep the
			 * current version when making the journal entry, because that was
			 * the version that was operated on.  The maximum numbers for each
			 * part in the version string: none.99.9.9
			*/
			$version_parts = split ("\.", $version);
			$newnumofparts = $numofparts = count ($version_parts);

			if ($version_parts[3] >= 9)
			{
				$version_parts[3] = 0;
				$version_parts[2]++;
				$version_parts_3_update = 1;
			}
			elseif (isset ($version_parts[3]))
			{
				$version_parts[3]++;
			}

			if ($version_parts[2] >= 9 && $version_parts[3] == 0 && $version_parts_3_update)
			{
				$version_parts[2] = 0;
				$version_parts[1]++;
			}

			if ($version_parts[1] > 99)
			{
				$version_parts[1] = 0;
				$version_parts[0]++;
			}

			for ($i = 0; $i < $newnumofparts; $i++)
			{
				if (!isset ($version_parts[$i]))
				{
					break;
				}

				if ($i)
				{
					$newversion .= '.';
				}

				$newversion .= $version_parts[$i];
			}

			return $newversion;
		}

		function set_vfs(&$vfs)
		{
			$this->vfs =& $vfs;
		}

		#helper, private functions

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

		/*!
		 * @function backup_filename()
		 * @abstract Return the backup filename for a certain filename + version
		 *
		 * @result string
		 */
		function backup_filename($filename,$version)
		{
			$version = str_replace('.','_',$version);
			$fbrk = explode('.',$filename);
			$fbrk[0] .= '-'.$version;
			return implode('.',$fbrk);
		}

	}


?>
