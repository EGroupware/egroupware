<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$GLOBALS['DEBUG'] = False;
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'noheader'   => True,
			'nonavbar'   => True,
			'currentapp' => 'home',
			'noapi'      => True,
			'nocachecontrol' => True
	));
	include('./inc/functions.inc.php');

	@set_time_limit(0);

	$tpl_root = $GLOBALS['egw_setup']->html->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('setup.Template',$tpl_root);
	$setup_tpl->set_file(array
	(
		'T_head'       => 'head.tpl',
		'T_footer'     => 'footer.tpl',
		'T_alert_msg'  => 'msg_alert_msg.tpl',
		'T_login_main' => 'login_main.tpl',
		'T_login_stage_header' => 'login_stage_header.tpl',
		'T_setup_main' => 'setup_main.tpl',
		'T_setup_db_blocks'    => 'setup_db_blocks.tpl'
	));

	$setup_tpl->set_block('T_login_stage_header','B_multi_domain','V_multi_domain');
	$setup_tpl->set_block('T_login_stage_header','B_single_domain','V_single_domain');

	$setup_tpl->set_block('T_setup_db_blocks','B_db_stage_1','V_db_stage_1');
	$setup_tpl->set_block('T_setup_db_blocks','B_db_stage_1a','V_db_stage_1a');
	$setup_tpl->set_block('T_setup_db_blocks','B_db_stage_2','V_db_stage_2');
	$setup_tpl->set_block('T_setup_db_blocks','B_db_stage_3','V_db_stage_3');
	$setup_tpl->set_block('T_setup_db_blocks','B_db_stage_4','V_db_stage_4');
	$setup_tpl->set_block('T_setup_db_blocks','B_db_stage_5','V_db_stage_5');
	$setup_tpl->set_block('T_setup_db_blocks','B_db_stage_6_pre','V_db_stage_6_pre');
	$setup_tpl->set_block('T_setup_db_blocks','B_db_stage_6_post','V_db_stage_6_post');
	$setup_tpl->set_block('T_setup_db_blocks','B_db_stage_10','V_db_stage_10');
	$setup_tpl->set_block('T_setup_db_blocks','B_db_stage_default','V_db_stage_default');

	// Check header and authentication
	$GLOBALS['egw_info']['setup']['stage']['header'] = $GLOBALS['egw_setup']->detection->check_header();
	if ($GLOBALS['egw_info']['setup']['stage']['header'] != '10')
	{
		if ($GLOBALS['egw_info']['setup']['stage']['header'] == 4)	// header needs update, go there direct
		{
			Header('Location: manageheader.php');
		}
		else	// run check-install first
		{
			Header('Location: check_install.php?intro=1');
		}
		exit;
	}
	elseif(!$GLOBALS['egw_setup']->auth('Config'))
	{
		$GLOBALS['egw_setup']->html->show_header(lang('Please login'),True);
		$GLOBALS['egw_setup']->html->login_form();
		$GLOBALS['egw_setup']->html->show_footer();
		exit;
	}

	$GLOBALS['egw_setup']->loaddb();

	$GLOBALS['egw_setup']->html->show_header(
		$GLOBALS['egw_info']['setup']['header_msg'],
		False,
		'config',
		$GLOBALS['egw_setup']->ConfigDomain . '(' . $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain]['db_type'] . ')'
	);
	/* Add cleaning of app_sessions per skeeter, but with a check for the table being there, just in case */
	/* $GLOBALS['egw_setup']->clear_session_cache(); */

	// Database actions
	$setup_info = $GLOBALS['egw_setup']->detection->get_versions();
	$GLOBALS['egw_info']['setup']['stage']['db'] = $GLOBALS['egw_setup']->detection->check_db($setup_info);
	if ($GLOBALS['egw_info']['setup']['stage']['db'] != 1)
	{
		$setup_info = $GLOBALS['egw_setup']->detection->get_versions();
		$setup_info = $GLOBALS['egw_setup']->detection->get_db_versions($setup_info);
		$GLOBALS['egw_info']['setup']['stage']['db'] = $GLOBALS['egw_setup']->detection->check_db($setup_info);
		if($GLOBALS['DEBUG'])
		{
			_debug_array($setup_info);
		}
	}

	if ($GLOBALS['DEBUG']) { echo 'Stage: ' . $GLOBALS['egw_info']['setup']['stage']['db']; }
	// begin DEBUG code
	//$GLOBALS['egw_info']['setup']['stage']['db'] = 0;
	//$action = 'Upgrade';
	// end DEBUG code

	switch(@get_var('action',Array('POST')))
	{
		case 'Uninstall all applications':
			$subtitle = lang('Deleting Tables');
			$submsg = lang('Are you sure you want to delete your existing tables and data?') . '.';
			$subaction = lang('uninstall');
			$GLOBALS['egw_info']['setup']['currentver']['phpgwapi'] = 'predrop';
			$GLOBALS['egw_info']['setup']['stage']['db'] = 5;
			break;
		case 'Create Database':
			$subtitle = lang('Create Database');
			$submsg = lang('At your request, this script is going to attempt to create the database and assign the db user rights to it');
			$subaction = lang('created');
			$GLOBALS['egw_info']['setup']['currentver']['phpgwapi'] = 'dbcreate';
			$GLOBALS['egw_info']['setup']['stage']['db'] = 6;
			break;
		case 'REALLY Uninstall all applications':
			$subtitle = lang('Deleting Tables');
			$submsg = lang('At your request, this script is going to take the evil action of uninstalling all your apps, which deletes your existing tables and data') . '.';
			$subaction = lang('uninstalled');
			$GLOBALS['egw_info']['setup']['currentver']['phpgwapi'] = 'drop';
			$GLOBALS['egw_info']['setup']['stage']['db'] = 6;
			break;
		case 'Upgrade':
			$subtitle = lang('Upgrading Tables');
			$submsg = lang('At your request, this script is going to attempt to upgrade your old applications to the current versions').'.';
			if ($_POST['backup'])
			{
				$submsg .= ' '.lang('After backing up your tables first.');
			}
			$subaction = lang('upgraded');
			$GLOBALS['egw_info']['setup']['currentver']['phpgwapi'] = 'oldversion';
			$GLOBALS['egw_info']['setup']['stage']['db'] = 6;
			break;
		case 'Install':
			$subtitle = lang('Creating Tables');
			if ($_POST['upload'])
			{
				$submsg = lang('At your request, this script is going to attempt to install a previous backup').'.';
			}
			else
			{
				$submsg = lang('At your request, this script is going to attempt to install the core tables and the admin and preferences applications for you').'.';
			}
			$subaction = lang('installed');
			$GLOBALS['egw_info']['setup']['currentver']['phpgwapi'] = 'new';
			$GLOBALS['egw_info']['setup']['stage']['db'] = 6;
			break;
	}
	$setup_tpl->set_var('subtitle',@$subtitle);
	$setup_tpl->set_var('submsg',@$submsg);
	$setup_tpl->set_var('subaction',@$subaction);

	// Old PHP
	if (!function_exists('version_compare'))//version_compare() is only available in PHP4.1+
	{
		$GLOBALS['egw_setup']->html->show_header($GLOBALS['egw_info']['setup']['header_msg'],True);
		$GLOBALS['egw_setup']->html->show_alert_msg('Error',
			 lang('You appear to be running an old version of PHP <br>It its recommend that you upgrade to a new version. <br>Older version of PHP might not run eGroupWare correctly, if at all. <br><br>Please upgrade to at least version %1','4.1'));
		$GLOBALS['egw_setup']->html->show_footer();
		exit;
	}

	// BEGIN setup page

	//$GLOBALS['egw_setup']->app_status();
	$GLOBALS['egw_info']['server']['app_images'] = 'templates/default/images';
	$incomplete = $GLOBALS['egw_info']['server']['app_images'] . '/incomplete.png';
	$completed  = $GLOBALS['egw_info']['server']['app_images'] . '/completed.png';

	$setup_tpl->set_var('img_incomplete',$incomplete);
	$setup_tpl->set_var('img_completed',$completed);

	$setup_tpl->set_var('db_step_text',lang('Step %1 - Simple Application Management',1));

	switch($GLOBALS['egw_info']['setup']['stage']['db'])
	{
		case 1:
			$setup_tpl->set_var('dbnotexist',lang('Your Database is not working!').':<p>'.$GLOBALS['egw_setup']->db->Error);
			$setup_tpl->set_var('makesure',lang('makesure'));
			$setup_tpl->set_var('notcomplete',lang('not complete'));
			$setup_tpl->set_var('oncesetup',lang('Once the database is setup correctly'));
			$setup_tpl->set_var('createdb',lang('Or we can attempt to create the database for you:'));
			$setup_tpl->set_var('create_database',lang('Create database'));
			$info = $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain];
			switch ($info['db_type'])
			{
				case 'mysql':
					$setup_tpl->set_var('instr',
						lang("Instructions for creating the database in %1:",'MySql')
						. '<br>'.lang('Login to mysql -')
						. '<br><i>[user@server user]# mysql -u root -p</i><br>'
						. lang('Create the empty database and grant user permissions -')
						. "<br><i>mysql> create database $info[db_name];</i>"
						. "<br><i>mysql> grant all on " . $info['db_name']
						. ".* to " . $info['db_user'] . "@localhost identified by '" . $info['db_pass'] . "';</i>");
					$setup_tpl->parse('V_db_stage_1','B_db_stage_1');
					break;
				case 'pgsql':
					$setup_tpl->set_var('instr',
						lang('Instructions for creating the database in %1:','PostgreSQL')
						. '<br>'.lang('Start the postmaster')
						. "<br><i>[user@server user]# postmaster -i -D /home/[username]/[dataDir]</i><br>"
						. lang('Create the empty database -')
						. "<br><i>[user@server user]# createdb " . $info['db_name'] . "</i>");
					$setup_tpl->parse('V_db_stage_1','B_db_stage_1');
					break;
				default:
					$setup_tpl->parse('V_db_stage_1','B_db_stage_1a');
			}
			$db_filled_block = $setup_tpl->get_var('V_db_stage_1');
			$setup_tpl->set_var('V_db_filled_block',$db_filled_block);
			break;
		case 2:
			$setup_tpl->set_var('prebeta',lang('You appear to be running a pre-beta version of eGroupWare.<br>These versions are no longer supported, and there is no upgrade path for them in setup.<br> You may wish to first upgrade to 0.9.10 (the last version to support pre-beta upgrades) <br>and then upgrade from there with the current version.'));
			$setup_tpl->set_var('notcomplete',lang('not complete'));
			$setup_tpl->parse('V_db_stage_2','B_db_stage_2');
			$db_filled_block = $setup_tpl->get_var('V_db_stage_2');
			$setup_tpl->set_var('V_db_filled_block',$db_filled_block);
			break;
		case 3:
			$setup_tpl->set_var('dbexists',lang('Your database is working, but you dont have any applications installed'));
			$setup_tpl->set_var('install',lang('Install'));
			$setup_tpl->set_var('proceed',lang('We can proceed'));
			$setup_tpl->set_var('coreapps',lang('all applications'));
			$setup_tpl->set_var('lang_debug',lang('enable for extra debug-messages'));
			$setup_tpl->set_var('lang_system_charset',lang('<b>charset to use</b> (use utf-8 if you plan to use languages with different charsets):'));
			$setup_tpl->set_var('system_charset',$GLOBALS['egw_setup']->translation->get_charsets('system_charset',lang('charset')));
			$setup_tpl->set_var('lang_restore',lang('Or you can install a previous backup.'));
			$setup_tpl->set_var('upload','<input type="file" name="uploaded" /> &nbsp;'.
				'<input type="submit" name="upload" value="'.htmlspecialchars(lang('install backup')).'" title="'.htmlspecialchars(lang("uploads a backup and installs it on your DB")).'" />');
			$setup_tpl->parse('V_db_stage_3','B_db_stage_3');
			$db_filled_block = $setup_tpl->get_var('V_db_stage_3');
			$setup_tpl->set_var('V_db_filled_block',$db_filled_block);
			break;
		case 4:
			$setup_tpl->set_var('oldver',lang('You appear to be running version %1 of eGroupWare',$setup_info['phpgwapi']['currentver']));
			$setup_tpl->set_var('automatic',lang('We will automatically update your tables/records to %1',$setup_info['phpgwapi']['version']));
			$setup_tpl->set_var('backupwarn',lang('but we <u>highly recommend backing up</u> your tables in case the script causes damage to your data.<br><strong>These automated scripts can easily destroy your data.</strong>'));
			$setup_tpl->set_var('lang_backup',lang('create a backup before upgrading the DB'));
			$setup_tpl->set_var('lang_debug',lang('enable for extra debug-messages'));
			$setup_tpl->set_var('upgrade',lang('Upgrade'));
			$setup_tpl->set_var('goto',lang('Go to'));
			$setup_tpl->set_var('configuration',lang('configuration'));
			$setup_tpl->set_var('admin_account',lang('Create admin account'));
			$setup_tpl->set_var('applications',lang('Manage Applications'));
			$setup_tpl->set_var('db_backup',lang('DB backup and restore'));
			$setup_tpl->set_var('language_management',lang('Manage Languages'));
			$setup_tpl->set_var('uninstall_all_applications',lang('Uninstall all applications'));
			$setup_tpl->set_var('dont_touch_my_data',lang('Dont touch my data'));
			$setup_tpl->set_var('dropwarn',lang('Your tables will be dropped and you will lose data'));

			$setup_tpl->parse('V_db_stage_4','B_db_stage_4');
			$db_filled_block = $setup_tpl->get_var('V_db_stage_4');
			$setup_tpl->set_var('V_db_filled_block',$db_filled_block);
			break;
		case 5:
			$setup_tpl->set_var('are_you_sure',lang('ARE YOU SURE?'));
			$setup_tpl->set_var('really_uninstall_all_applications',lang('REALLY Uninstall all applications'));
			$setup_tpl->set_var('dropwarn',lang('Your tables will be dropped and you will lose data'));
			$setup_tpl->set_var('cancel',lang('cancel'));
			$setup_tpl->parse('V_db_stage_5','B_db_stage_5');
			$db_filled_block = $setup_tpl->get_var('V_db_stage_5');
			$setup_tpl->set_var('V_db_filled_block',$db_filled_block);
			break;
		case 6:
			$setup_tpl->set_var('status',lang('Status'));
			$setup_tpl->set_var('notcomplete',lang('not complete'));
			$setup_tpl->set_var('tblchange',lang('Table Change Messages'));
			$setup_tpl->parse('V_db_stage_6_pre','B_db_stage_6_pre');
			$db_filled_block = $setup_tpl->get_var('V_db_stage_6_pre');
			$setup_tpl->set_var('tableshave',lang('If you did not receive any errors, your applications have been'));

			// FIXME : CAPTURE THIS OUTPUT
			$GLOBALS['egw_setup']->db->Halt_On_Error = 'report';

			switch ($GLOBALS['egw_info']['setup']['currentver']['phpgwapi'])
			{
				case 'dbcreate':
					$GLOBALS['egw_setup']->db->create_database($_POST['db_root'], $_POST['db_pass']);
					break;
				case 'drop':
					$setup_info = $GLOBALS['egw_setup']->detection->get_versions($setup_info);
					$setup_info = $GLOBALS['egw_setup']->process->droptables($setup_info);
					break;
				case 'new':
					// use uploaded backup, instead installing from scratch
					if ($_POST['upload'])
					{
						$db_backup = CreateObject('phpgwapi.db_backup');
						if (is_array($_FILES['uploaded']) && !$_FILES['uploaded']['error'] && 
							is_uploaded_file($_FILES['uploaded']['tmp_name']))
						{
							if (preg_match('/\.(bz2|gz)$/i',$_FILES['uploaded']['name'],$matches))
							{
								$ext = '.'.$matches[1];
								move_uploaded_file($_FILES['uploaded']['tmp_name'],$_FILES['uploaded']['tmp_name'].$ext);
								$_FILES['uploaded']['tmp_name'] .= $ext;
							}
							if (is_resource($f = $db_backup->fopen_backup($_FILES['uploaded']['tmp_name'],true)))
							{
								echo '<p align="center">'.lang('restore started, this might take a view minutes ...')."</p>\n".str_repeat(' ',4096);
								$db_backup->restore($f);
								fclose($f);
								echo '<p align="center">'.lang('restore finished')."</p>\n";
								unlink($_FILES['uploaded']['tmp_name']);
							}
							else	// backup failed ==> dont start the upgrade
							{
								$setup_tpl->set_var('submsg',lang('Restore failed'));
								$setup_tpl->set_var('tableshave','<b>'.$f.'</b>');
								$setup_tpl->set_var('subaction','');
							}
						}
					}
					else
					{
						$setup_info = $GLOBALS['egw_setup']->detection->upgrade_exclude($setup_info);
						// Set the DB's client charset if a system-charset is set
						if ($_REQUEST['system_charset'])
						{
							$GLOBALS['egw_setup']->db->Link_ID->SetCharSet($_REQUEST['system_charset']);
						}
						$setup_info = $GLOBALS['egw_setup']->process->pass($setup_info,'new',$_REQUEST['debug'],True,$_REQUEST['system_charset']);
						$GLOBALS['egw_info']['setup']['currentver']['phpgwapi'] = 'oldversion';
					}
					break;
				case 'oldversion':
					// create a backup, before upgrading the tables
					if ($_POST['backup'])
					{
						$db_backup = CreateObject('phpgwapi.db_backup');
						if (is_resource($f = $db_backup->fopen_backup()))
						{
							echo '<p align="center">'.lang('backup started, this might take a view minutes ...')."</p>\n".str_repeat(' ',4096);
							$db_backup->backup($f);
							fclose($f);
							echo '<p align="center">'.lang('backup finished')."</p>\n";
						}
						else	// backup failed ==> dont start the upgrade
						{
							$setup_tpl->set_var('submsg',lang('Backup failed'));
							$setup_tpl->set_var('tableshave','<b>'.$f.'</b>');
							$setup_tpl->set_var('subaction','');
						}
					}
					if (!@$_POST['backup'] || !is_string($f))
					{
						$setup_info = $GLOBALS['egw_setup']->process->pass($setup_info,'upgrade',$_REQUEST['debug']);
						$GLOBALS['egw_info']['setup']['currentver']['phpgwapi'] = 'oldversion';
					}
					break;
			}

			$GLOBALS['egw_setup']->db->Halt_On_Error = 'no';

			$setup_tpl->set_var('re-check_my_installation',lang('Re-Check My Installation'));
			$setup_tpl->parse('V_db_stage_6_post','B_db_stage_6_post');
			$db_filled_block = $db_filled_block . $setup_tpl->get_var('V_db_stage_6_post');
			$setup_tpl->set_var('V_db_filled_block',$db_filled_block);
			break;
		case 10:
			$setup_tpl->set_var('tablescurrent',lang('Your applications are current'));
			$setup_tpl->set_var('uninstall_all_applications',lang('Uninstall all applications'));
			$setup_tpl->set_var('insanity',lang('Insanity'));
			$setup_tpl->set_var('dropwarn',lang('Your tables will be dropped and you will lose data'));
			$setup_tpl->set_var('deletetables',lang('Uninstall all applications'));
			$setup_tpl->parse('V_db_stage_10','B_db_stage_10');
			$db_filled_block = $setup_tpl->get_var('V_db_stage_10');
			$setup_tpl->set_var('V_db_filled_block',$db_filled_block);
			break;
		default:
			$setup_tpl->set_var('dbnotexist',lang('Your database does not exist'));
			$setup_tpl->parse('V_db_stage_default','B_db_stage_default');
			$db_filled_block = $setup_tpl->get_var('V_db_stage_default');
			$setup_tpl->set_var('V_db_filled_block',$db_filled_block);
			break;
	}
	
function check_dir($dir,&$msg,$check_in_docroot=false)
{
	if (!@is_dir($dir))
	{
		$msg = lang('does not exist');
		return false;
	}
	if (!@is_writeable($dir))
	{
		$msg = lang('is not writeable by the webserver');
		return false;
	}
	if ($check_in_docroot)
	{
		$docroots = array(EGW_SERVER_ROOT,$_SERVER['DOCUMENT_ROOT']);
		$dir = realpath($dir);

		foreach ($docroots as $docroot)
		{
			$len = strlen($docroot);

			if ($docroot == substr($dir,0,$len))
			{
				$rest = substr($dir,$len);

				if (!strlen($rest) || $rest[0] == DIRECTORY_SEPARATOR)
				{
					$msg = lang('is in the webservers docroot');
					return false;
				}
			}
		}
	}
	return true;
}

	// Config Section
	$setup_tpl->set_var('config_step_text',lang('Step %1 - Configuration',2));
	$GLOBALS['egw_info']['setup']['stage']['config'] = $GLOBALS['egw_setup']->detection->check_config();

	// begin DEBUG code
	//$GLOBALS['egw_info']['setup']['stage']['config'] = 10;
	// end DEBUG code

	$setup_tpl->set_var('config_status_img',$incomplete);
	$setup_tpl->set_var('config_status_alt',lang('not completed'));
	switch($GLOBALS['egw_info']['setup']['stage']['config'])
	{
		case 1:
			$btn_config_now = $GLOBALS['egw_setup']->html->make_frm_btn_simple(
				lang('Please configure eGroupWare for your environment'),
				'POST','config.php',
				'submit',lang('Configure Now'),
				'');
			$setup_tpl->set_var('config_table_data',$btn_config_now);
			$setup_tpl->set_var('ldap_table_data','&nbsp;');
			break;
		case 10:
			$GLOBALS['egw_setup']->db->query("SELECT config_name,config_value FROM phpgw_config WHERE config_app='phpgwapi'");
			while($GLOBALS['egw_setup']->db->next_record())
			{
				$config[$GLOBALS['egw_setup']->db->f(0)] = $GLOBALS['egw_setup']->db->f(1);
			}
			// set and create the default backup_dir
			if (@is_writeable($config['files_dir']) && !isset($config['backup_dir']) && $config['file_store_contents'] == 'filesystem')
			{
				$config['backup_dir'] = $config['files_dir'].'/db_backup';
				if (!is_dir($config['backup_dir']) && mkdir($config['backup_dir']))
				{
					$GLOBALS['egw_setup']->db->query("INSERT INTO phpgw_config (config_app,config_name,config_value) VALUES ('phpgwapi','backup_dir',".$GLOBALS['egw_setup']->db->quote($config['backup_dir']).')',__LINE__,__FILE__);
				}
			}
			$config_msg = '';
			if (!check_dir($config['temp_dir'],$error_msg))
			{
				$config_msg = lang("Your temporary directory '%1' %2",$config['temp_dir'],$error_msg);
			}
			if (!check_dir($config['files_dir'],$error_msg,true))
			{
				$config_msg .= ($config_msg?"<br>\n":'').lang("Your files directory '%1' %2",$config['files_dir'],$error_msg);
			}
			if (!check_dir($config['backup_dir'],$error_msg,true))
			{
				$config_msg .= ($config_msg?"<br>\n":'').lang("Your backup directory '%1' %2",$config['backup_dir'],$error_msg);
			}
			if (!$config_msg)
			{
				$setup_tpl->set_var('config_status_img',$completed);
				$setup_tpl->set_var('config_status_alt',lang('completed'));
				$config_msg = lang('Configuration completed');
			}
			$btn_edit_config = $GLOBALS['egw_setup']->html->make_frm_btn_simple(
				$config_msg,
				'POST','config.php',
				'submit',lang('Edit Current Configuration'),
				''
			);
			if ($config['auth_type'] == 'ldap')
			{
				if ($config['ldap_host'] != '')
				{
					$btn_config_ldap = $GLOBALS['egw_setup']->html->make_frm_btn_simple(
						lang('LDAP account import/export'),
						'POST','ldap.php',
						'submit',lang('Configure Now'),
						''
					);
				}
				else
				{
					$btn_config_ldap = '';
				}
			}
			$setup_tpl->set_var('config_table_data',$btn_edit_config);
			$setup_tpl->set_var('ldap_table_data',$btn_config_ldap);
			break;
		default:
			$setup_tpl->set_var('config_status_img',$incomplete);
			$setup_tpl->set_var('config_status_alt',lang('not completed'));
			$setup_tpl->set_var('config_table_data',lang('Not ready for this stage yet'));
			$setup_tpl->set_var('ldap_table_data','&nbsp;');
			break;
	}
	// Admin Account Section
	$setup_tpl->set_var('admin_step_text',lang('Step %1 - Admin Account',3));

	switch($GLOBALS['egw_info']['setup']['stage']['config'])
	{
		case 10:
			// check if there is already a user account (not the anonymous account of sitemgr or a group)
			// Note: this does not check the availiblitly of accounts via other auth-methods then sql !!!
			$GLOBALS['egw_setup']->db->query("SELECT count(*) FROM phpgw_accounts WHERE account_type='u' AND account_lid!='anonymous'",__LINE__,__FILE__);
			$no_accounts = !$GLOBALS['egw_setup']->db->next_record() || !$GLOBALS['egw_setup']->db->f(0);
			$setup_tpl->set_var('admin_status_img',$no_accounts ? $incomplete : $completed);
			$setup_tpl->set_var('admin_status_alt',$no_accounts ? lang('not completed') : lang('completed'));
			$setup_tpl->set_var('admin_table_data',$GLOBALS['egw_setup']->html->make_frm_btn_simple(
				$no_accounts ? lang('No accounts existing') : lang('Accounts existing'),
				'POST','setup_demo.php',
				'submit',lang('Create admin account'),
				''
			));
			break;
		default:
			$setup_tpl->set_var('admin_status_img',$incomplete);
			$setup_tpl->set_var('admin_status_alt',lang('not completed'));
			$setup_tpl->set_var('admin_table_data',lang('Not ready for this stage yet'));
			break;
	}

	// Lang Section
	$setup_tpl->set_var('lang_step_text',lang('Step %1 - Language Management',4));
	$GLOBALS['egw_info']['setup']['stage']['lang'] = $GLOBALS['egw_setup']->detection->check_lang();

	// begin DEBUG code
	//$GLOBALS['egw_info']['setup']['stage']['lang'] = 0;
	// end DEBUG code

	switch($GLOBALS['egw_info']['setup']['stage']['lang'])
	{
		case 1:
			$setup_tpl->set_var('lang_status_img',$incomplete);
			$setup_tpl->set_var('lang_status_alt','not completed');
			$btn_install_lang = $GLOBALS['egw_setup']->html->make_frm_btn_simple(
				lang('You do not have any languages installed. Please install one now <br>'),
				'POST','lang.php',
				'submit',lang('Install Language'),
				'');
			$setup_tpl->set_var('lang_table_data',$btn_install_lang);
			break;
		case 10:
			$langs_list = array();
			$languages = get_langs();
			foreach ($GLOBALS['egw_info']['setup']['installed_langs'] as $key => $value)
			{
				$langs_list[] = isset($languages[$key]) ? $languages[$key]['descr'] : $value;
			}
			$setup_tpl->set_var('lang_status_img',$completed);
			$setup_tpl->set_var('lang_status_alt','completed');
			$btn_manage_lang = $GLOBALS['egw_setup']->html->make_frm_btn_simple(
				lang('This stage is completed<br>') . lang('Currently installed languages: %1 <br>',implode(', ',$langs_list)),
				'POST','lang.php',
				'submit',lang('Manage Languages'),
				'');
			// show system-charset and offer conversation
			include_once(EGW_API_INC.'/class.translation_sql.inc.php');
			$translation = new translation;
			$btn_manage_lang .= lang('Current system-charset is %1, click %2here%3 to change it.',
				$translation->system_charset ? "'$translation->system_charset'" : lang('not set'),
				'<a href="system_charset.php">','</a>');
			$setup_tpl->set_var('lang_table_data',$btn_manage_lang);
			break;
		default:
			$setup_tpl->set_var('lang_status_img',$incomplete);
			$setup_tpl->set_var('lang_status_alt',lang('not completed'));
			$setup_tpl->set_var('lang_table_data',lang('Not ready for this stage yet'));
			break;
	}

	$setup_tpl->set_var('apps_step_text',lang('Step %1 - Advanced Application Management',5));
//	$GLOBALS['egw_info']['setup']['stage']['apps'] = $GLOBALS['egw_setup']->check_apps();
	switch($GLOBALS['egw_info']['setup']['stage']['db'])
	{
		case 10:
			$setup_tpl->set_var('apps_status_img',$completed);
			$setup_tpl->set_var('apps_status_alt',lang('completed'));
			// check if we have apps to upgrade
			$to_upgrade = array();
			foreach($setup_info as $app => $data)
			{
				if ($data['currentver'] && $data['version'] && $data['version'] != $data['currentver'])
				{
					$to_upgrade[] = $app;
				}
			}
			$btn_manage_apps = $GLOBALS['egw_setup']->html->make_frm_btn_simple(
				count($to_upgrade) ? '<b>'.lang('The following applications need to be upgraded:').'</b> '.implode(', ',$to_upgrade) :
				lang('This stage is completed<br>'),
				'','applications.php',
				'submit',lang('Manage Applications'),
				'');
			$setup_tpl->set_var('apps_table_data',$btn_manage_apps);
			break;
		default:
			$setup_tpl->set_var('apps_status_img',$incomplete);
			$setup_tpl->set_var('apps_status_alt',lang('not completed'));
			$setup_tpl->set_var('apps_table_data',lang('Not ready for this stage yet'));
			break;
	}
	// Backup and restore section
	$setup_tpl->set_var('backup_step_text',lang('Step %1 - DB backup and restore',6));
	switch($GLOBALS['egw_info']['setup']['stage']['db'])
	{
		case 10:
			$setup_tpl->set_var('backup_status_img',$completed);
			$setup_tpl->set_var('backup_status_alt',lang('completed'));
			$setup_tpl->set_var('backup_table_data',$GLOBALS['egw_setup']->html->make_frm_btn_simple(
				''/*lang('This stage is completed<br>')*/,
				'','db_backup.php',
				'submit',lang('backup and restore'),
				''));
			break;
		default:
			$setup_tpl->set_var('backup_status_img',$incomplete);
			$setup_tpl->set_var('backup_status_alt',lang('not completed'));
			$setup_tpl->set_var('backup_table_data',lang('Not ready for this stage yet'));
			break;
	}
	
	$setup_tpl->pparse('out','T_setup_main');
	$GLOBALS['egw_setup']->html->show_footer();
?>
