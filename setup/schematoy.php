<?php
  /**************************************************************************\
  * phpGroupWare - Setup - Developer tools                                   *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$DEBUG = True;
	$phpgw_info['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',
		'noapi' => True
	);
	include ('./inc/functions.inc.php');

	$tpl_root = $phpgw_setup->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl',
		'T_login_main' => 'login_main.tpl',
		'T_login_stage_header' => 'login_stage_header.tpl',
		'T_setup_main' => 'schema.tpl'
	));

	$setup_tpl->set_block('T_login_stage_header','B_multi_domain','V_multi_domain');
	$setup_tpl->set_block('T_login_stage_header','B_single_domain','V_single_domain');
	$setup_tpl->set_block('T_setup_main','header','header');
	$setup_tpl->set_block('T_setup_main','app_header','app_header');
	$setup_tpl->set_block('T_setup_main','apps','apps');
	$setup_tpl->set_block('T_setup_main','detail','detail');
	$setup_tpl->set_block('T_setup_main','table','table');
	$setup_tpl->set_block('T_setup_main','hook','hook');
	$setup_tpl->set_block('T_setup_main','dep','dep');
	$setup_tpl->set_block('T_setup_main','app_footer','app_footer');
	$setup_tpl->set_block('T_setup_main','submit','submit');
	$setup_tpl->set_block('T_setup_main','footer','footer');

	// Check header and authentication
	$phpgw_info['setup']['stage']['header'] = $phpgw_setup->check_header();
	if ($phpgw_info['setup']['stage']['header'] != '10')
	{
		Header("Location: manageheader.php");
		exit;
	}
	elseif (!$phpgw_setup->auth('Config'))
	{
		$phpgw_setup->show_header(lang('Please login'),True);
		$phpgw_setup->login_form();
		$phpgw_setup->show_footer();
		exit;
	}

	$bgcolor = array('DDDDDD','EEEEEE');

	function parsedep($depends,$main=True)
	{
		$depstring = '(';
		while (list($a,$b) = each ($depends))
		{
			while (list($c,$d) = each($b))
			{
				if (is_array($d))
				{
					$depstring .= $c . ': ' .implode(',',$d) . '; ';
					$depver[] = $d;
				}
				else
				{
					$depstring .= $c . ': ' . $d . '; ';
					$depapp[] = $d;
				}
			}
		}
		$depstring .= ')';
		if ($main)
		{
			return $depstring;
		}
		else
		{
			return array($depapp,$depver);
		}
	}

	$phpgw_setup->loaddb();
	$phpgw_info['setup']['stage']['db'] = $phpgw_setup->check_db();

	$setup_info = $phpgw_setup->get_versions();
	//var_dump($setup_info);exit;
	$setup_info = $phpgw_setup->get_db_versions($setup_info);
	//var_dump($setup_info);exit;
	$setup_info = $phpgw_setup->compare_versions($setup_info);
	//var_dump($setup_info);exit;
	$setup_info = $phpgw_setup->check_depends($setup_info);
	//var_dump($setup_info);exit;
	@ksort($setup_info);

	if ($cancel)
	{
		Header("Location: index.php");
		exit;
	}

	$phpgw_setup->show_header(lang("Developers' Table Schema Toy"),False,'config',$ConfigDomain);

	if ($submit)
	{
		$setup_tpl->set_var('description',lang('App process') . ':');
		$setup_tpl->pparse('out','header');

		while (list($appname,$key) = @each($install))
		{
			$terror = array();
			$terror[$appname]['name'] = $appname;
			$terror[$appname]['version'] = $version[$appname];
			$terror[$appname]['status'] = 'U';

			$appdir  = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

			// Drop newest tables
			$terror[$appname]['tables'] = $setup_info[$appname]['tables'];
			$phpgw_setup->process_droptables($terror,$DEBUG);
			$terror[$appname]['tables'] = array();

			// Reset tables field to baseline table names
			if (file_exists($appdir.'tables_baseline.inc.php'))
			{
				include($appdir.'tables_baseline.inc.php');
				while(list($table,$null) = @each($phpgw_baseline))
				{
					$terror[$appname]['tables'][] = $table;
					echo '<br>Adding app table: ' . $table;
				}
			}

			if($version[$appname])
			{
				echo '<br>Processing ' . $terror[$appname]['name'] . ' to ' . $version[$appname];

				$terror = $phpgw_setup->process_droptables($terror,$DEBUG);
				$phpgw_setup->deregister_app($terror[$appname]['name']);

				$terror = $phpgw_setup->process_baseline($terror,$DEBUG);
				$terror = $phpgw_setup->process_test_data($terror,$DEBUG);

				$terror = $phpgw_setup->process_upgrade($terror,$DEBUG);
			}
			else
			{
				echo '<br>Baseline-only completed for ' . $terror[$appname]['name'];
			}
			echo '<br>' . $setup_info[$appname]['title'] . ' '
				. lang('tables installed, unless there are errors printed above') . '.';

			$setup_info[$appname]['version'] = $terror[$appname]['version'];
			$phpgw_setup->register_app($terror[$appname]['name']);
			echo '<br>' . $terror[$appname]['title'] . ' ' . lang('registered') . '.';
		}

		echo '<br><a href="schematoy.php">' . lang('Go back') . '</a>';
		$setup_tpl->pparse('out','footer');
		exit;
	}
	if($detail)
	{
		@ksort($setup_info[$detail]);
		@reset($setup_info[$detail]);
		$setup_tpl->set_var('description',lang('App details') . ':');
		$setup_tpl->pparse('out','header');
		
		while (list($key,$val) = each($setup_info[$detail]))
		{
			if ($i) { $i = 0; }
			else    { $i = 1; }

			//if(!$val) { $val = 'none'; }

			if ($key == 'tables')
			{
				if(is_array($val))
				{
					$key = '<a href="sqltoarray.php?appname=' . $detail . '&submit=True">' . $key . '</a>' . "\n";
					$val = implode(',',$val);
				}
			}
			if ($key == 'hooks')   { $val = implode(',',$val); }
			if ($key == 'depends') { $val = parsedep($val); }
			if (is_array($val))    { $val = implode(',',$val); }

			$setup_tpl->set_var('bg_color',$bgcolor[$i]);
			$setup_tpl->set_var('name',$key);
			$setup_tpl->set_var('details',$val);
			$setup_tpl->pparse('out','detail');
		}

		echo '<br><a href="schematoy.php">' . lang('Go back') . '</a>';
		$setup_tpl->pparse('out','footer');
		exit;
	}
	else
	{
		$setup_tpl->set_var('description',lang("Select an app, enter a target version, then submit to process to that version.<br>If you do not enter a version, only the baseline tables will be installed for the app.<br><blink>THIS WILL DROP ALL OF THE APPS' TABLES FIRST!</blink>"));
		$setup_tpl->pparse('out','header');

		$setup_tpl->set_var('appdata',lang('Application Data'));
		$setup_tpl->set_var('actions',lang('Actions'));
		$setup_tpl->set_var('action_url','schematoy.php');
		$setup_tpl->set_var('app_info',lang('Application Name and Status'));
		$setup_tpl->set_var('app_title',lang('Application Title'));
		$setup_tpl->set_var('app_version',lang('Target Version'));
		$setup_tpl->set_var('app_install',lang('Process'));
		$setup_tpl->pparse('out','app_header');

		@reset ($setup_info);
		while (list ($key, $value) = each ($setup_info))
		{
			unset($test);
			if (file_exists(PHPGW_SERVER_ROOT . '/' . $value['name'] . '/setup/tables_update.inc.php'))
			{
				include(PHPGW_SERVER_ROOT . '/' . $value['name'] . '/setup/tables_update.inc.php');
			}

			if (is_array($test))
			{
				reset($test);
			}

			$s = '<option value="">&nbsp;</option>';
			while (is_array($test) && list(,$versionnumber) = each($test))
			{
				$s .= '<option value="' . $versionnumber . '">' . $versionnumber . '</option>';
			}
			$setup_tpl->set_var('select_version',$s);

			if ($value['name'])
			{
				if ($i)
				{
					$i = 0;
				}
				else
				{
					$i = 1;
				}
				$setup_tpl->set_var('apptitle',$value['title']);
				$setup_tpl->set_var('currentver',$value['currentver']);
				$setup_tpl->set_var('bg_color',$bgcolor[$i]);

				$setup_tpl->set_var('instimg','completed.gif');
				$setup_tpl->set_var('instalt',lang('Completed'));
				$setup_tpl->set_var('install','<input type="checkbox" name="install[' . $value['name'] . ']">');
				$status = lang('OK') . ' - ' . $value['status'];

				$setup_tpl->set_var('appinfo',$value['name'] . '-' . $status);
				$setup_tpl->set_var('appname',$value['name']);

				$setup_tpl->pparse('out','apps',True);
			}
		}
	}
	$setup_tpl->set_var('submit',lang('Submit'));
	$setup_tpl->set_var('cancel',lang('Cancel'));
	$setup_tpl->pparse('out','app_footer');
	$setup_tpl->pparse('out','footer');
	$phpgw_setup->show_footer();
?>
