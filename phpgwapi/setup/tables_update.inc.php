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
?>
