<?php
	/**************************************************************************\
	* eGroupWare - Setup                                                       *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	// $Id$
	// $Source$

	/* Include older eGroupWare update support */
	include('tables_update_0_9_9.inc.php');
	include('tables_update_0_9_10.inc.php');
	include('tables_update_0_9_12.inc.php');
	include('tables_update_0_9_14.inc.php');

	// updates from the stable 1.0.0 branch
	$test[] = '1.0.0.001';
	function phpgwapi_upgrade1_0_0_001()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.0.004';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.0.002';
	function phpgwapi_upgrade1_0_0_002()
	{
		// identical to 1.0.0.001, only created to get a new version of the packages
		return phpgwapi_upgrade1_0_0_001();
	}

	$test[] = '1.0.0.003';
	function phpgwapi_upgrade1_0_0_003()
	{
		// identical to 1.0.0.001, only created to get a new version of the final 1.0 packages
		return phpgwapi_upgrade1_0_0_001();
	}
	
	$test[] = '1.0.0.004';
	function phpgwapi_upgrade1_0_0_004()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','id','async_id');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','next','async_next');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','times','async_times');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','method','async_method');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','data','async_data');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','account_id','async_account_id');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.0.005';
	function phpgwapi_upgrade1_0_0_005()
	{
		// identical to 1.0.0.001, only created to get a new version of the bugfix release
		return phpgwapi_upgrade1_0_0_004();
	}
	
	$test[] = '1.0.0.006';
	function phpgwapi_upgrade1_0_0_006()
	{
		// identical to 1.0.0.001, only created to get a new version of the bugfix release
		return phpgwapi_upgrade1_0_0_004();
	}
	
	$test[] = '1.0.0.007';
	function phpgwapi_upgrade1_0_0_007()
	{
		// identical to 1.0.0.001, only created to get a new version of the bugfix release
		return phpgwapi_upgrade1_0_0_004();
	}
	
	$test[] = '1.0.1.001';
	function phpgwapi_upgrade1_0_1_001()
	{
		// removing the ACL entries of deleted accounts
		$GLOBALS['phpgw_setup']->setup_account_object();
		if (($all_accounts = $GLOBALS['phpgw']->accounts->search(array('type'=>'both'))))
		{
			$all_accounts = array_keys($all_accounts);
			$GLOBALS['phpgw_setup']->oProc->query("DELETE FROM phpgw_acl WHERE acl_account NOT IN (".implode(',',$all_accounts).")",__LINE__,__FILE__);
			$GLOBALS['phpgw_setup']->oProc->query("DELETE FROM phpgw_acl WHERE acl_appname='phpgw_group' AND acl_location NOT IN ('".implode("','",$all_accounts)."')",__LINE__,__FILE__);
		}
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.002';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '1.0.1.002';
	function phpgwapi_upgrade1_0_1_002()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','file_id','vfs_file_id');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','owner_id','vfs_owner_id');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','createdby_id','vfs_createdby_id');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','modifiedby_id','vfs_modifiedby_id');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','created','vfs_created');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','modified','vfs_modified');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','size','vfs_size');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','mime_type','vfs_mime_type');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','deleteable','vfs_deleteable');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','comment','vfs_comment');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','app','vfs_app');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','directory','vfs_directory');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','name','vfs_name');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','link_directory','vfs_link_directory');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','link_name','vfs_link_name');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','version','vfs_version');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_vfs','content','vfs_content');
		$GLOBALS['phpgw_setup']->oProc->RenameTable('phpgw_vfs','egw_vfs');

		$GLOBALS['phpgw_setup']->oProc->RefreshTable('egw_vfs',array(
			'fd' => array(
				'vfs_file_id' => array('type' => 'auto','nullable' => False),
				'vfs_owner_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'vfs_createdby_id' => array('type' => 'int','precision' => '4'),
				'vfs_modifiedby_id' => array('type' => 'int','precision' => '4'),
				'vfs_created' => array('type' => 'date','nullable' => False,'default' => '1970-01-01'),
				'vfs_modified' => array('type' => 'date'),
				'vfs_size' => array('type' => 'int','precision' => '4'),
				'vfs_mime_type' => array('type' => 'varchar','precision' => '64'),
				'vfs_deleteable' => array('type' => 'char','precision' => '1','default' => 'Y'),
				'vfs_comment' => array('type' => 'varchar','precision' => '255'),
				'vfs_app' => array('type' => 'varchar','precision' => '25'),
				'vfs_directory' => array('type' => 'varchar','precision' => '255'),
				'vfs_name' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'vfs_link_directory' => array('type' => 'varchar','precision' => '255'),
				'vfs_link_name' => array('type' => 'varchar','precision' => '128'),
				'vfs_version' => array('type' => 'varchar','precision' => '30','nullable' => False,'default' => '0.0.0.0'),
				'vfs_content' => array('type' => 'text')
			),
			'pk' => array('vfs_file_id'),
			'fk' => array(),
			'ix' => array(array('vfs_directory','vfs_name','vfs_mime_type')),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.003';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.003';
	function phpgwapi_upgrade1_0_1_003()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'egw_api_content_history', array(
				'fd' => array(
					'sync_appname'	=>  array('type' => 'varchar','precision' => '60','nullable' => False),
					'sync_contentid' => array('type' => 'varchar','precision' => '60','nullable' => False),
					'sync_added'	=>  array('type' => 'timestamp', 'nullable' => False),
					'sync_modified'	=>  array('type' => 'timestamp', 'nullable' => False),
					'sync_deleted'	=>  array('type' => 'timestamp', 'nullable' => False),
					'sync_id'	=>  array('type' => 'auto','nullable' => False),
					'sync_guid'	=>  array('type' => 'varchar','precision' => '120','nullable' => False),
					'sync_changedby' => array('type' => 'int','precision' => '4','nullable' => False),
				),
				'pk' => array('sync_id'),
				'fk' => array(),
				'ix' => array(array('sync_appname','sync_contentid'),'sync_added','sync_modified','sync_deleted','sync_guid','sync_changedby'),
				'uc' => array()
			)
		);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.004';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.004';
	function phpgwapi_upgrade1_0_1_004()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_api_content_history','sync_added',array(
			'type' => 'timestamp'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_api_content_history','sync_modified',array(
			'type' => 'timestamp'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_api_content_history','sync_deleted',array(
			'type' => 'timestamp'
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.005';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.005';
	function phpgwapi_upgrade1_0_1_005()
	{
		/*********************************************************************\
		 *	                       VFS version 2                             *
		\*********************************************************************/

		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_vfs2_mimetypes', array(
				'fd' => array(
					'mime_id' => array('type' => 'auto','nullable' => False),
					'extension' => array('type' => 'varchar', 'precision' => 10, 'nullable' => false),
					'mime' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
					'mime_magic' => array('type' => 'varchar', 'precision' => 255, 'nullable' => true),
					'friendly' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
					'image' => array('type' => 'blob'),
					'proper_id' => array('type' => 'varchar', 'precision' => 4)
				),
				'pk' => array('mime_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_vfs2_files' , array(
				'fd' => array(
					'file_id' => array('type' => 'auto','nullable' => False),
					'mime_id' => array('type' => 'int','precision' => 4),
					'owner_id' => array('type' => 'int','precision' => 4,'nullable' => False),
					'createdby_id' => array('type' => 'int','precision' => 4),
					'created' => array('type' => 'timestamp','default' => '1970-01-01 00:00:00', 'nullable' => False),
					'size' => array('type' => 'int','precision' => 8),
					'deleteable' => array('type' => 'char','precision' => 1,'default' => 'Y'),
					'comment' => array('type' => 'varchar','precision' => 255),
					'app' => array('type' => 'varchar','precision' => 25),
					'directory' => array('type' => 'varchar','precision' => 255),
					'name' => array('type' => 'varchar','precision' => 128,'nullable' => False),
					'link_directory' => array('type' => 'varchar','precision' => 255),
					'link_name' => array('type' => 'varchar','precision' => 128),
					'version' => array('type' => 'varchar','precision' => 30,'nullable' => False,'default' => '0.0.0.0'),
					'content' => array('type' => 'longtext'),
					'is_backup' => array('type' => 'varchar', 'precision' => 1, 'nullable' => False, 'default' => 'N'),
					'shared' => array('type' => 'varchar', 'precision' => 1, 'nullable' => False,'default' => 'N'),
					'proper_id' => array('type' => 'varchar', 'precision' => 45)
				),
				'pk' => array('file_id'),
				'fk' => array('mime_id' => array ('phpgw_vfs2_mimetypes' => 'mime_id')),
				'ix' => array(array('directory','name')),
				'uc' => array()
			)
		);

		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_vfs2_customfields' , array(
				'fd' => array(
					'customfield_id' => array('type' => 'auto','nullable' => False),
					'customfield_name' => array('type' => 'varchar','precision' => 60,'nullable' => False),
					'customfield_description' => array('type' => 'varchar','precision' => 255,'nullable'=> True),
					'customfield_type' => array('type' => 'varchar','precision' => 20, 'nullable' => false),
					'customfield_precision' => array('type' => 'int', 'precision' => 4, 'nullable' => true),
					'customfield_active' => array('type' => 'varchar','precision' => 1,'nullable' => False,'default' => 'N')
				),
				'pk' => array('customfield_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_vfs2_quota' , array(
				'fd' => array(
					'account_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'quota' => array('type' => 'int','precision' => 4,'nullable' => false)
				),
				'pk' => array('account_id'),
				'fk' => array('account_id' => array('phpgw_accounts' => 'account_id')),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_vfs2_shares' , array(
				'fd' => array(
					'account_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'file_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'acl_rights' => array('type' => 'int','precision' => 4,'nullable' => false)
				),
				'pk' => array('account_id','file_id'),
				'fk' => array('account_id' => array('phpgw_accounts' => 'account_id'), 'file_id' => array('phpgw_vfs2_files' => 'file_id')),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_vfs2_versioning' , array(
				'fd' => array(
					'version_id' => array('type' => 'auto', 'nullable' => false),
					'file_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'operation' => array('type' => 'int','precision' => 4, 'nullable' => False),
					'modifiedby_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'modified' => array('type' => 'timestamp', 'nullable' => False ),
					'version' => array('type' => 'varchar', 'precision' => 30, 'nullable' => False ),
					'comment' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'backup_file_id' => array('type' => 'int','precision' => 4, 'nullable' => True),
					'backup_content' => array('type' => 'longtext', 'nullable' => True),
					'src' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'dest' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True)
				),
				'pk' => array('version_id'),
				'fk' => array('file_id' => array('phpgw_vfs2_files' => 'file_id')),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_vfs2_customfields_data' , array(
				'fd' => array(
					'file_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'customfield_id' => array('type' => 'int', 'precision' => 4, 'nullable' => false),
					'data' => array('type' => 'longtext', 'nullable' => True)
				),
				'pk' => array('file_id','customfield_id'),
				'fk' => array('file_id' => array('phpgw_vfs2_files' => 'file_id'),'customfield_id' => array('phpgw_vfs2_customfields' => 'customfield_id')),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_vfs2_prefixes' , array(
				'fd' => array(
					'prefix_id' => array('type' => 'auto','nullable' => false),
					'prefix' => array('type' => 'varchar', 'precision' => 8, 'nullable' => false),
					'owner_id' => array('type' => 'int', 'precision' => 4, 'nullable' => false),
					'prefix_description' => array('type' => 'varchar', 'precision' => 30, 'nullable' => True),
					'prefix_type' => array('type' => 'varchar', 'precision' => 1, 'nullable' => false, 'default' => 'p')
				),
				'pk' => array('prefix_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		/*************************************************************************\
		 *                    Default Records for VFS v2                         *
		\*************************************************************************/
		if ($GLOBALS['DEBUG'])
		{
			echo "<br>\n<b>initiating to create the default records for VFS SQL2...";
		}
		
		include PHPGW_INCLUDE_ROOT.'/phpgwapi/setup/default_records_mime.inc.php';

		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_vfs2_files (mime_id,owner_id,createdby_id,size,directory,name)
					   SELECT mime_id,0,0,4096,'/','' FROM phpgw_vfs2_mimetypes WHERE mime='Directory'");

		if ($GLOBALS['DEBUG'])
		{
			echo " DONE!</b>";
		}
		/*************************************************************************/

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.006';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


?>
