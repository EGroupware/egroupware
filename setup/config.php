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

  /* $Id$ */

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'noheader' => True,
			'nonavbar' => True,
			'currentapp' => 'home',
			'noapi' => True
	));
	include('./inc/functions.inc.php');

	/*
	Authorize the user to use setup app and load the database
	Does not return unless user is authorized
	*/
	if(!$GLOBALS['egw_setup']->auth('Config') || @$_POST['cancel'])
	{
		Header('Location: index.php');
		exit;
	}

	$tpl_root = $GLOBALS['egw_setup']->html->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('setup.Template',$tpl_root);

	$setup_tpl->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl',
		'T_config_pre_script' => 'config_pre_script.tpl',
		'T_config_post_script' => 'config_post_script.tpl'
	));

	/* Following to ensure windows file paths are saved correctly */
	set_magic_quotes_runtime(0);

	$GLOBALS['egw_setup']->loaddb();

	/* Check api version, use correct table */
	$setup_info = $GLOBALS['egw_setup']->detection->get_db_versions();

	if($GLOBALS['egw_setup']->alessthanb($setup_info['phpgwapi']['currentver'], '0.9.10pre7'))
	{
		$configtbl = 'config';
	}
	else
	{
		$configtbl = 'phpgw_config';
	}

	$newsettings = $_POST['newsettings'];

	if(@get_var('submit',Array('POST')) && @$newsettings)
	{
		/* Load hook file with functions to validate each config (one/none/all) */
		$GLOBALS['egw_setup']->hook('config_validate','setup');

		$datetime = CreateObject('phpgwapi.datetime');
		switch((int)$newsettings['daytime_port'])
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

		$GLOBALS['egw_setup']->db->transaction_begin();
		/* This is only temp: */
		$GLOBALS['egw_setup']->db->query("DELETE FROM $configtbl WHERE config_name='useframes'");
		$GLOBALS['egw_setup']->db->query("INSERT INTO $configtbl (config_app,config_name, config_value) values ('phpgwapi','useframes','never')");

		while(list($setting,$value) = @each($newsettings))
		{
			if($GLOBALS['egw_info']['server']['found_validation_hook'] && @function_exists($setting))
			{
				call_user_func($setting,$newsettings);
				if($GLOBALS['config_error'])
				{
					$GLOBALS['error'] .= '<br>' . lang($GLOBALS['config_error']) . '&nbsp;';
					$GLOBALS['config_error'] = '';
					/* Bail out, stop writing config data */
					break;
				}
				else
				{
					/* echo '<br>Updating: ' . $setting . '=' . $value; */
					/* Don't erase passwords, since we also do not print them below */
					if($value || (!stristr($setting,'passwd') && !stristr($setting,'password') && !stristr($setting,'root_pw')))
					{
						@$GLOBALS['egw_setup']->db->query("DELETE FROM $configtbl WHERE config_name='" . $setting . "'");
					}
					if($value)
					{
						$GLOBALS['egw_setup']->db->query("INSERT INTO $configtbl (config_app,config_name, config_value) VALUES ('phpgwapi','" . $GLOBALS['egw_setup']->db->db_addslashes($setting)
						. "','" . $GLOBALS['egw_setup']->db->db_addslashes($value) . "')");
					}
				}
			}
			else
			{
				if($value || (!stristr($setting,'passwd') && !stristr($setting,'password') && !stristr($setting,'root_pw')))
				{
					@$GLOBALS['egw_setup']->db->query("DELETE FROM $configtbl WHERE config_name='" . $setting . "'");
				}
				if($value)
				{
					$GLOBALS['egw_setup']->db->query("INSERT INTO $configtbl (config_app,config_name, config_value) VALUES ('phpgwapi','" . $GLOBALS['egw_setup']->db->db_addslashes($setting)
					. "','" . $GLOBALS['egw_setup']->db->db_addslashes($value) . "')");
				}
			}
		}
		if(!$GLOBALS['error'])
		{
			$GLOBALS['egw_setup']->db->transaction_commit();

			/* Add cleaning of app_sessions per skeeter, but with a check for the table being there, just in case */
			$tablenames = $GLOBALS['egw_setup']->db->table_names();
			while(list($key,$val) = @each($tablenames))
			{
				$tables[] = $val['table_name'];
			}
			if(in_array('phpgw_app_sessions',$tables))
			{
				$GLOBALS['egw_setup']->db->lock(array('phpgw_app_sessions'));
				@$GLOBALS['egw_setup']->db->query("DELETE FROM phpgw_app_sessions WHERE sessionid = '0' and loginid = '0' and app = 'phpgwapi' and location = 'config'",__LINE__,__FILE__);
				@$GLOBALS['egw_setup']->db->query("DELETE FROM phpgw_app_sessions WHERE app = 'phpgwapi' and location = 'phpgw_info_cache'",__LINE__,__FILE__);
				$GLOBALS['egw_setup']->db->unlock();
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
	}

	$GLOBALS['egw_setup']->html->show_header(lang('Configuration'),False,'config',$GLOBALS['egw_setup']->ConfigDomain . '(' . $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain]['db_type'] . ')');

	@$GLOBALS['egw_setup']->db->query("SELECT * FROM $configtbl");
	while(@$GLOBALS['egw_setup']->db->next_record())
	{
		$GLOBALS['current_config'][$GLOBALS['egw_setup']->db->f('config_name')] = $GLOBALS['egw_setup']->db->f('config_value');
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
	$GLOBALS['egw'] = new phpgw;
	$GLOBALS['egw']->common = CreateObject('phpgwapi.common');
	$GLOBALS['egw']->db     = $GLOBALS['egw_setup']->db;

	$t = CreateObject('setup.Template',$GLOBALS['egw']->common->get_tpl_dir('setup'));

	$t->set_unknowns('keep');
	$t->set_file(array('config' => 'config.tpl'));
	$t->set_block('config','body','body');

	$vars = $t->get_undefined('body');
	$GLOBALS['egw_setup']->hook('config','setup');

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
				$newval = str_replace(' ','_',$newval);
				/* Don't show passwords in the form */
				if(strstr($value,'passwd') || strstr($value,'password') || strstr($value,'root_pw'))
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
				$newval = str_replace(' ','_',$newval);
				$t->set_var($value,$newval($current_config));
				break;
			default:
				$t->set_var($value,'');
				break;
		}
	}

	if($GLOBALS['error'])
	{
		if($GLOBALS['error'] == 'badldapconnection')
		{
			/* Please check the number and dial again :) */
			$GLOBALS['egw_setup']->html->show_alert_msg('Error',
				lang('There was a problem trying to connect to your LDAP server. <br>'
					.'please check your LDAP server configuration') . '.');
		}

		$GLOBALS['egw_setup']->html->show_alert_msg('Error',$GLOBALS['error']);
	}

	$t->pfp('out','body');
	unset($t);

	$setup_tpl->set_var('more_configs',lang('Please login to egroupware and run the admin application for additional site configuration') . '.');

	$setup_tpl->set_var('lang_submit',lang('Save'));
	$setup_tpl->set_var('lang_cancel',lang('Cancel'));
	$setup_tpl->pparse('out','T_config_post_script');

	$GLOBALS['egw_setup']->html->show_footer();
?>
