<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',
		'noapi' => True
	);
	include('./inc/functions.inc.php');

	/*
	Authorize the user to use setup app and load the database
	Does not return unless user is authorized
	*/
	if (!$phpgw_setup->auth('Config'))
	{
		Header('Location: index.php');
		exit;
	}

	$tpl_root = $phpgw_setup->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl',
		'T_config_pre_script' => 'config_pre_script.tpl',
		'T_config_post_script' => 'config_post_script.tpl'
	));

	/* Following to ensure windows file paths are saved correctly */
	set_magic_quotes_runtime(0);

	$phpgw_setup->loaddb();

	/* Guessing default values. */
	$GLOBALS['current_config']['hostname']  = $HTTP_HOST;
	$GLOBALS['current_config']['files_dir'] = ereg_replace('/setup','/files',dirname($SCRIPT_FILENAME));
	if (@is_dir('/tmp'))
	{
		$GLOBALS['current_config']['temp_dir'] = '/tmp';
	}
	else
	{
		$GLOBALS['current_config']['temp_dir'] = '/path/to/temp/dir';
	}

	if ($HTTP_POST_VARS['cancel'])
	{
		Header('Location: index.php');
		exit;
	}

	/* Check api version, use correct table */
	$setup_info = $phpgw_setup->get_db_versions();
	if($phpgw_setup->alessthanb($setup_info['phpgwapi']['currentver'], '0.9.10pre7'))
	{
		$configtbl = 'config';
	}
	else
	{
		$configtbl = 'phpgw_config';
	}

	if ($HTTP_POST_VARS['submit'] && $HTTP_POST_VARS['newsettings'])
	{
		$phpgw_setup->db->transaction_begin();
		/* This is only temp: */
		$phpgw_setup->db->query("DELETE FROM $configtbl WHERE config_name='useframes'");
		$phpgw_setup->db->query("INSERT INTO $configtbl (config_app,config_name, config_value) values ('phpgwapi','useframes','never')");

		$newsettings = $HTTP_POST_VARS['newsettings'];

		while (list($setting,$value) = @each($newsettings))
		{
			/* echo '<br>Updating: ' . $setting . '=' . $value; */
			/* Don't erase passwords, since we also do not print them below */
			if(!ereg('passwd',$setting) && !ereg('password',$setting) && !ereg('root_pw',$setting))
			{
				@$phpgw_setup->db->query("DELETE FROM $configtbl WHERE config_name='" . $setting . "'");
			}
			if($value)
			{
				$phpgw_setup->db->query("INSERT INTO $configtbl (config_app,config_name, config_value) VALUES ('phpgwapi','" . $phpgw_setup->db->db_addslashes($setting)
					. "','" . $phpgw_setup->db->db_addslashes($value) . "')");
			}
		}
		$phpgw_setup->db->transaction_commit();

		/* Add cleaning of app_sessions per skeeter, but with a check for the table being there, just in case */
		$tablenames = $phpgw_setup->db->table_names();
		while(list($key,$val) = @each($tablenames))
		{
			$tables[] = $val['table_name'];
		}
		if ($phpgw_setup->isinarray('phpgw_app_sessions',$tables))
		{
			$phpgw_setup->db->lock(array('phpgw_app_sessions'));
			@$phpgw_setup->db->query("DELETE FROM phpgw_app_sessions WHERE sessionid = '0' and loginid = '0' and app = 'phpgwapi' and location = 'config'",__LINE__,__FILE__);
			@$phpgw_setup->db->query("DELETE FROM phpgw_app_sessions WHERE app = 'phpgwapi' and location = 'phpgw_info_cache'",__LINE__,__FILE__);
			$phpgw_setup->db->unlock();
		}

		if ($newsettings['auth_type'] == 'ldap')
		{
			Header('Location: '.$newsettings['webserver_url'].'/setup/ldap.php');
			exit;
		}
		else
		{
			Header('Location: index.php');
			exit;
		}
	}

	if ($newsettings['auth_type'] != 'ldap')
	{
		$phpgw_setup->show_header(lang('Configuration'),False,'config',$ConfigDomain . '(' . $phpgw_domain[$ConfigDomain]["db_type"] . ')');
	}

	@$phpgw_setup->db->query("SELECT * FROM $configtbl");
	while (@$phpgw_setup->db->next_record())
	{
		$GLOBALS['current_config'][$phpgw_setup->db->f('config_name')] = $phpgw_setup->db->f('config_value');
	}

	if ($GLOBALS['current_config']['files_dir'] == '/path/to/dir/phpgroupware/files')
	{
		$GLOBALS['current_config']['files_dir'] = $GLOBALS['phpgw_info']['server']['server_root'] . '/files';
	}

	if ($error == 'badldapconnection')
	{
		/* Please check the number and dial again :) */
		$phpgw_setup->show_alert_msg('Error',
			lang('There was a problem trying to connect to your LDAP server. <br>'
				.'please check your LDAP server configuration') . '.');
	}

	$setup_tpl->pparse('out','T_config_pre_script');

	/* Now parse each of the templates we want to show here */
	class phpgw
	{
		var $common;
		var $accounts;
		var $applications;
		var $db;
	}
	$GLOBALS['phpgw'] = new phpgw;
	$GLOBALS['phpgw']->common = CreateObject('phpgwapi.common');

	$cfg_apps = array('phpgwapi','admin','preferences');
	while(list(,$cfg_app) = each($cfg_apps))
	{
		$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir($cfg_app));

		$t->set_unknowns('keep');
		$t->set_file(array('config' => 'config.tpl'));
		$t->set_block('config','body','body');
		$t->set_var('th_bg',   '486591');
		$t->set_var('th_text', 'FFFFFF');
		$t->set_var('row_on',  'DDDDDD');
		$t->set_var('row_off', 'EEEEEE');

		$vars = $t->get_undefined('body');
		$GLOBALS['phpgw']->hooks->single('config',$cfg_app);

		while (list($null,$value) = each($vars))
		{
			$valarray = explode('_',$value);
			$type = $valarray[0];
			$new = $newval = '';

			while($chunk = next($valarray))
			{
				$new[] = $chunk;
			}
			$newval = implode(' ',$new);

			switch ($type)
			{
				case 'lang':
					$t->set_var($value,lang($newval));
					break;
				case 'value':
					$newval = ereg_replace(' ','_',$newval);
					/* Don't show passwords in the form */
					if(ereg('passwd',$value) || ereg('password',$value) || ereg('root_pw',$value))
					{
						$t->set_var($value,'');
					}
					else
					{
						$t->set_var($value,$current_config[$newval]);
					}
					break;
				case 'selected':
					$configs = array();
					$config  = '';
					$newvals = explode(' ',$newval);
					$setting = end($newvals);
					for ($i=0;$i<(count($newvals) - 1); $i++)
					{
						$configs[] = $newvals[$i];
					}
					$config = implode('_',$configs);
					/* echo $config . '=' . $current_config[$config]; */
					if ($current_config[$config] == $setting)
					{
						$t->set_var($value,' selected');
					}
					else
					{
						$t->set_var($value,'');
					}
					break;
				case 'hook':
					$newval = ereg_replace(' ','_',$newval);
					$t->set_var($value,$newval($current_config));
					break;
				default:
					$t->set_var($value,'');
					break;
			}
		}

		$t->pfp('out','body');
		unset($t);
	}

	//$phpgw_setup->execute_script('config',array('phpgwapi','admin','preferences')); /* ;,'preferences','email','nntp')); */
	$setup_tpl->set_var('more_configs',lang('Please login to phpgroupware and run the admin application for additional site configuration') . '.');

	$setup_tpl->set_var('lang_submit',lang('submit'));
	$setup_tpl->set_var('lang_cancel',lang('cancel'));
	$setup_tpl->pparse('out','T_config_post_script');
	$phpgw_setup->show_footer();
?>
