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
	if(!$GLOBALS['phpgw_setup']->auth('Config') || @$_POST['cancel'])
	{
		Header('Location: index.php');
		exit;
	}

	$tpl_root = $GLOBALS['phpgw_setup']->html->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('setup.Template',$tpl_root);

	// test if $path lies within the webservers document-root
	//
	function in_docroot($path)
	{
		$docroots = array(PHPGW_SERVER_ROOT,$GLOBALS['HTTP_SERVER_VARS']['DOCUMENT_ROOT']);
		
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

	$setup_tpl->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl',
		'T_config_pre_script' => 'config_pre_script.tpl',
		'T_config_post_script' => 'config_post_script.tpl'
	));

	/* Following to ensure windows file paths are saved correctly */
	set_magic_quotes_runtime(0);

	$GLOBALS['phpgw_setup']->loaddb();

	/* Check api version, use correct table */
	$setup_info = $GLOBALS['phpgw_setup']->detection->get_db_versions();

	if($GLOBALS['phpgw_setup']->alessthanb($setup_info['phpgwapi']['currentver'], '0.9.10pre7'))
	{
		$configtbl = 'config';
	}
	else
	{
		$configtbl = 'phpgw_config';
	}

	$newsettings = $_POST['newsettings'];
	$files_in_docroot = in_docroot($newsettings['files_dir']);

	if(@get_var('submit',Array('POST')) && @$newsettings && !$files_in_docroot)
	{
		$datetime = CreateObject('phpgwapi.datetime');
		switch (intval($newsettings['daytime_port']))
		{
			case 13:
				$newsettings['tz_offset'] = $datetime->getntpoffset();
				break;
			case 80:
				$newsettings['tz_offset'] = $datetime->gethttpoffset();
				break;
			default:
				$newsettings['tz_offset'] = $datetime->getbestguess();
				break;
		}
		unset($datetime);

		print_debug('TZ_OFFSET',$newsettings['tz_offset']);

		$GLOBALS['phpgw_setup']->db->transaction_begin();
		/* This is only temp: */
		$GLOBALS['phpgw_setup']->db->query("DELETE FROM $configtbl WHERE config_name='useframes'");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO $configtbl (config_app,config_name, config_value) values ('phpgwapi','useframes','never')");

		while(list($setting,$value) = @each($newsettings))
		{
			/* echo '<br>Updating: ' . $setting . '=' . $value; */
			/* Don't erase passwords, since we also do not print them below */
			if($value || (!ereg('passwd',$setting) && !ereg('password',$setting) && !ereg('root_pw',$setting)))
			{
				@$GLOBALS['phpgw_setup']->db->query("DELETE FROM $configtbl WHERE config_name='" . $setting . "'");
			}
			if($value)
			{
				$GLOBALS['phpgw_setup']->db->query("INSERT INTO $configtbl (config_app,config_name, config_value) VALUES ('phpgwapi','" . $GLOBALS['phpgw_setup']->db->db_addslashes($setting)
					. "','" . $GLOBALS['phpgw_setup']->db->db_addslashes($value) . "')");
			}
		}
		$GLOBALS['phpgw_setup']->db->transaction_commit();

		/* Add cleaning of app_sessions per skeeter, but with a check for the table being there, just in case */
		$tablenames = $GLOBALS['phpgw_setup']->db->table_names();
		while(list($key,$val) = @each($tablenames))
		{
			$tables[] = $val['table_name'];
		}
		if(in_array('phpgw_app_sessions',$tables))
		{
			$GLOBALS['phpgw_setup']->db->lock(array('phpgw_app_sessions'));
			@$GLOBALS['phpgw_setup']->db->query("DELETE FROM phpgw_app_sessions WHERE sessionid = '0' and loginid = '0' and app = 'phpgwapi' and location = 'config'",__LINE__,__FILE__);
			@$GLOBALS['phpgw_setup']->db->query("DELETE FROM phpgw_app_sessions WHERE app = 'phpgwapi' and location = 'phpgw_info_cache'",__LINE__,__FILE__);
			$GLOBALS['phpgw_setup']->db->unlock();
		}

		if($newsettings['auth_type'] == 'ldap')
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

	if($newsettings['auth_type'] != 'ldap')
	{
		$GLOBALS['phpgw_setup']->html->show_header(lang('Configuration'),False,'config',$ConfigDomain . '(' . $phpgw_domain[$ConfigDomain]["db_type"] . ')');
	}

	@$GLOBALS['phpgw_setup']->db->query("SELECT * FROM $configtbl");
	while(@$GLOBALS['phpgw_setup']->db->next_record())
	{
		$GLOBALS['current_config'][$GLOBALS['phpgw_setup']->db->f('config_name')] = $GLOBALS['phpgw_setup']->db->f('config_value');
	}
	
	// are we here because of an error: files-dir in docroot
	if (is_array($GLOBALS['HTTP_POST_VARS']['newsettings']) && $files_in_docroot)
	{
		echo '<p align="center"><font color="red"><b>'.lang('Path to user and group files HAS TO BE OUTSIDE of the webservers document-root!!!')."</b></font></p>\n";

		foreach($GLOBALS['HTTP_POST_VARS']['newsettings'] as $key => $val)
		{
			$GLOBALS['current_config'][$key] = $val;
		}
	}

	if($GLOBALS['error'] == 'badldapconnection')
	{
		/* Please check the number and dial again :) */
		$GLOBALS['phpgw_setup']->html->show_alert_msg('Error',
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
	$GLOBALS['phpgw']->db     = $GLOBALS['phpgw_setup']->db;

	/*$cfg_apps = array('phpgwapi','admin','preferences');
	while(list(,$cfg_app) = each($cfg_apps))
	{*/
		$t = CreateObject('setup.Template',$GLOBALS['phpgw']->common->get_tpl_dir('setup'));

		$t->set_unknowns('keep');
		$t->set_file(array('config' => 'config.tpl'));
		$t->set_block('config','body','body');

		$vars = $t->get_undefined('body');
		$GLOBALS['phpgw_setup']->hook('config','setup');

		while(list($null,$value) = each($vars))
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
						$t->set_var($value,@$current_config[$newval]);
					}
					break;
				case 'selected':
					$configs = array();
					$config  = '';
					$newvals = explode(' ',$newval);
					$setting = end($newvals);
					for($i=0;$i<(count($newvals) - 1); $i++)
					{
						$configs[] = $newvals[$i];
					}
					$config = implode('_',$configs);
					/* echo $config . '=' . $current_config[$config]; */
					if(@$current_config[$config] == $setting)
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
	//}

	$setup_tpl->set_var('more_configs',lang('Please login to phpgroupware and run the admin application for additional site configuration') . '.');

	$setup_tpl->set_var('lang_submit',lang('Save'));
	$setup_tpl->set_var('lang_cancel',lang('Cancel'));
	$setup_tpl->pparse('out','T_config_post_script');

	$GLOBALS['phpgw_setup']->html->show_footer();
?>

