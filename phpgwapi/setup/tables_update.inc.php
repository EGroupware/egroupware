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

	/* Include older eGroupWare update support */
	include('tables_update_0_9_9.inc.php');
	include('tables_update_0_9_10.inc.php');
	include('tables_update_0_9_12.inc.php');
	include('tables_update_0_9_14.inc.php');
	include('tables_update_1_0.inc.php');
	include('tables_update_1_2.inc.php');

	// updates from the stable 1.4 branch
	$test[] = '1.4.001';
	function phpgwapi_upgrade1_4_001()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.5.001';
	}

	$test[] = '1.4.002';
	function phpgwapi_upgrade1_4_002()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.5.001';
	}

	$test[] = '1.4.003';
	function phpgwapi_upgrade1_4_003()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.5.001';
	}

	$test[] = '1.4.004';
	function phpgwapi_upgrade1_4_004()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.5.001';
	}

	$test[] = '1.5.001';
	function phpgwapi_upgrade1_5_001()
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','org_name',array(
			'type' => 'varchar',
			'precision' => '128',
			'nullable' => true
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','contact_email',array(
			'type' => 'varchar',
			'precision' => '128',
			'nullable' => true
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','contact_email_home',array(
			'type' => 'varchar',
			'precision' => '128',
			'nullable' => true
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.5.002';
	}

	$test[] = '1.5.002';
	function phpgwapi_upgrade1_5_002()
	{
		$GLOBALS['egw_setup']->oProc->CreateTable('egw_sqlfs',array(
			'fd' => array(
				'fs_id' => array('type' => 'auto','nullable' => False),
				'fs_dir' => array('type' => 'int','precision' => '4','nullable' => False),
				'fs_name' => array('type' => 'varchar','precision' => '200','nullable' => False),
				'fs_mode' => array('type' => 'int','precision' => '2','nullable' => False),
				'fs_uid' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'fs_gid' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'fs_created' => array('type' => 'timestamp','precision' => '8','nullable' => False,'default' => 'current_timestamp'),
				'fs_modified' => array('type' => 'timestamp','precision' => '8','nullable' => False),
				'fs_mime' => array('type' => 'varchar','precision' => '64','nullable' => False),
				'fs_size' => array('type' => 'int','precision' => '8','nullable' => False),
				'fs_creator' => array('type' => 'int','precision' => '4','nullable' => False),
				'fs_modifier' => array('type' => 'int','precision' => '4'),
				'fs_active' => array('type' => 'bool','nullable' => False,'default' => 't'),
				'fs_comment' => array('type' => 'varchar','precision' => '255'),
				'fs_content' => array('type' => 'blob')
			),
			'pk' => array('fs_id'),
			'fk' => array(),
			'ix' => array(array('fs_dir','fs_active','fs_name')),
			'uc' => array()
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.5.003';
	}

	$test[] = '1.5.003';
	function phpgwapi_upgrade1_5_003()
	{
		// import the current egw_vfs into egw_sqlfs
		// ToDo: moving /infolog and /infolog/$app to /apps in the files dir!!!
		$debug = $GLOBALS['DEBUG'];

		// delete the table in case this update runs multiple times
		$GLOBALS['egw_setup']->db->query('DELETE FROM egw_sqlfs',__LINE__,__FILE__);
		if ($GLOBALS['egw_setup']->db->Type == 'mysql')
		{
			$GLOBALS['egw_setup']->db->query('ALTER TABLE egw_sqlfs  AUTO_INCREMENT=1',__LINE__,__FILE__);
		}
		// make sure the required dirs are there and have the following id's
		$dirs = array(
			'/' => 1,
			'/home' => 2,
			'/apps' => 3,
		);
		foreach($dirs as $path => $id)
		{
			$nrow = array(
				'fs_id' => $id,
				'fs_dir'  => $path == '/' ? 0 : $dirs['/'],
				'fs_name' => substr($path,1),
				'fs_mode' => 05,
				'fs_uid' => 0,
				'fs_gid' => 0,
				'fs_created' => time(),
				'fs_modified' => time(),
				'fs_mime' => 'httpd/unix-directory',
				'fs_size' => 0,
				'fs_creator' => 0,
				'fs_modifier' => 0,
				'fs_comment' => null,
				'fs_content' => null,
			);
			$GLOBALS['egw_setup']->db->insert('egw_sqlfs',$nrow,false,__LINE__,__FILE__,'phpgwapi');
		}
		$query = $GLOBALS['egw_setup']->db->select('egw_vfs','*',"vfs_mime_type != 'journal' AND vfs_mime_type != 'journal-deleted'",__LINE__,__FILE__,false,'ORDER BY length(vfs_directory) ASC','phpgwapi');
		if ($debug) echo "rows=<pre>\n";

		foreach($query as $row)
		{
			// rename the /infolog dir to /apps/infolog and /infolog/$app /apps/$app
			if (substr($row['vfs_directory'],0,8) == '/infolog')
			{
				$parts = explode('/',$row['vfs_directory']);	// 0 = '', 1 = 'infolog', 2 = app or info_id
				//$parts[1] = is_numeric($parts[2]) ? 'apps/infolog' : 'apps';
				$parts[1] = $row['vfs_directory']=='/infolog' && is_numeric($row['vfs_name']) ||
					$parts[1]=='infolog' && is_numeric($parts[2]) ? 'apps/infolog' : 'apps';
				$row['vfs_directory'] = implode('/',$parts);
			}
			elseif ($row['vfs_directory'] == '/' && $row['vfs_name'] == 'infolog')
			{
				$row['vfs_directory'] = '/apps';
			}
			$nrow = array(
				'fs_dir'  => $dirs[$row['vfs_directory']],
				'fs_name' => $row['vfs_name'],
				'fs_mode' => $row['vfs_owner_id'] > 0 ?
					($row['vfs_mime_type'] == 'Directory' ? 0700 : 0600) :
					($row['vfs_mime_type'] == 'Directory' ? 0070 : 0060),
				'fs_uid' => $row['vfs_owner_id'] > 0 ? $row['vfs_owner_id'] : 0,
				'fs_gid' => $row['vfs_owner_id'] < 0 ? -$row['vfs_owner_id'] : 0,
				'fs_created' => $row['vfs_created'],
				'fs_modified' => $row['vfs_modified'] ? $row['vfs_modified'] : $row['vfs_created'],
				'fs_mime' => $row['vfs_mime_type'] == 'Directory' ? 'httpd/unix-directory' :
					($row['vfs_mime_type'] ? $row['vfs_mime_type'] : 'application/octet-stream'),
				'fs_size' => $row['vfs_size'],
				'fs_creator' => $row['vfs_createdby_id'],
				'fs_modifier' => $row['vfs_modifedby_id'],
				'fs_comment' => $row['vfs_comment'] ? $row['vfs_comment'] : null,
				'fs_content' => $row['vfs_content'],
			);
			if ($debug)
			{
				foreach($row as $key => $val)
				{
					if (is_numeric($key)) unset($row[$key]);
				}
				print_r($row);
				print_r($nrow);
			}
			if ($row['vfs_mime_type'] == 'Directory')
			{
				$dir = ($row['vfs_directory'] == '/' ? '' : $row['vfs_directory']).'/'.$row['vfs_name'];

				if (!isset($dirs[$dir]))	// ignoring doublicate dirs, my devel box has somehow many of them specially /home
				{
					$GLOBALS['egw_setup']->db->insert('egw_sqlfs',$nrow,false,__LINE__,__FILE__,'phpgwapi');
					$dirs[$dir] = $GLOBALS['egw_setup']->db->get_last_insert_id('egw_sqlfs','fs_id');
					if ($debug) echo "<b>$dir = {$dirs[$dir]}</b>\n";
				}
				elseif ($debug)
				{
					echo "<b>ignoring doublicate directory '$dir'!</b>\n";
				}
			}
			else
			{
				$GLOBALS['egw_setup']->db->insert('egw_sqlfs',$nrow,false,__LINE__,__FILE__,'phpgwapi');
			}

		}
		if ($debug)
		{
			echo "dirs=";
			print_r($dirs);
			echo "</pre>\n";
		}
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.5.004';
	}

	$test[] = '1.5.004';
	function phpgwapi_upgrade1_5_004()
	{
		// convert the filemanager group grants into extended ACL

		// delete all sqlfs entries from the ACL table, in case we run multiple times
		$GLOBALS['egw_setup']->db->delete('egw_acl',array('acl_appname' => sqlfs_stream_wrapper::EACL_APPNAME),__LINE__,__FILE__);

		$GLOBALS['egw_setup']->setup_account_object();
		$accounts = $GLOBALS['egw_setup']->accounts;
		$accounts = new accounts();

		egw_vfs::$is_root = true;	// we need root rights to set the extended acl, without being the owner

		foreach($GLOBALS['egw_setup']->db->select('egw_acl','*',array(
			'acl_appname' => 'filemanager',
			"acl_location != 'run'",
		),__LINE__,__FILE__) as $row)
		{
			$rights = egw_vfs::READABLE | egw_vfs::EXECUTABLE;
			if($row['acl_rights'] > 1) $rights |= egw_vfs::WRITABLE;

			if (($lid = $accounts->id2name($row['acl_account'])) && $accounts->exists($row['acl_location']))
			{
				$ret = sqlfs_stream_wrapper::eacl('/home/'.$lid,$rights,(int)$row['acl_location']);
				//echo "<p>sqlfs_stream_wrapper::eacl('/home/$lid',$rights,$row[acl_location])=$ret</p>\n";
			}
		}
		egw_vfs::$is_root = false;

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.5.005';
	}

	$test[] = '1.5.005';
	function phpgwapi_upgrade1_5_005()
	{
		// move /infolog/$app to /apps/$app and /infolog to /apps/infolog

		$files_dir = $GLOBALS['egw_setup']->db->select('egw_config','config_value',array(
			'config_name' => 'files_dir',
			'config_app' => 'phpgwapi',
		),__LINE__,__FILE__)->fetchSingle();

		if ($files_dir && file_exists($files_dir) && file_exists($files_dir.'/infolog'))
		{
			mkdir($files_dir.'/apps',0700,true);
			if (($dir = opendir($files_dir.'/infolog')))
			{
				while(($app = readdir($dir)))
				{
					if (!is_numeric($app) && $app[0] != '.')	// ingore infolog entries and . or ..
					{
						rename($files_dir.'/infolog/'.$app,$files_dir.'/apps/'.$app);
					}
				}
				closedir($dir);
				rename($files_dir.'/infolog',$files_dir.'/apps/infolog');
			}
		}

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.5.006';
	}
