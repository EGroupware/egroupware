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

	$GLOBALS['DEBUG'] = True;

	$phpgw_info = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',
		'noapi' => True
	);
	include ('./inc/functions.inc.php');

	// Check header and authentication
	if (!$GLOBALS['phpgw_setup']->auth('Config'))
	{
		Header('Location: index.php');
		exit;
	}
	// Does not return unless user is authorized

	$tpl_root = $GLOBALS['phpgw_setup']->html->setup_tpl_dir('setup');
	$GLOBALS['setup_tpl'] = CreateObject('setup.Template',$tpl_root);
	$GLOBALS['setup_tpl']->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl',
		'T_login_main' => 'login_main.tpl',
		'T_login_stage_header' => 'login_stage_header.tpl',
		'T_setup_main' => 'schema.tpl'
	));

	$GLOBALS['setup_tpl']->set_block('T_login_stage_header','B_multi_domain','V_multi_domain');
	$GLOBALS['setup_tpl']->set_block('T_login_stage_header','B_single_domain','V_single_domain');
	$GLOBALS['setup_tpl']->set_block('T_setup_main','header','header');
	$GLOBALS['setup_tpl']->set_block('T_setup_main','app_header','app_header');
	$GLOBALS['setup_tpl']->set_block('T_setup_main','apps','apps');
	$GLOBALS['setup_tpl']->set_block('T_setup_main','detail','detail');
	$GLOBALS['setup_tpl']->set_block('T_setup_main','table','table');
	$GLOBALS['setup_tpl']->set_block('T_setup_main','hook','hook');
	$GLOBALS['setup_tpl']->set_block('T_setup_main','dep','dep');
	$GLOBALS['setup_tpl']->set_block('T_setup_main','app_footer','app_footer');
	$GLOBALS['setup_tpl']->set_block('T_setup_main','submit','submit');
	$GLOBALS['setup_tpl']->set_block('T_setup_main','footer','footer');

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

	$GLOBALS['phpgw_setup']->loaddb();
	$GLOBALS['phpgw_info']['setup']['stage']['db'] = $GLOBALS['phpgw_setup']->detection->check_db();

	$GLOBALS['setup_info'] = $GLOBALS['phpgw_setup']->detection->get_versions();
	//var_dump($GLOBALS['setup_info']);exit;
	$GLOBALS['setup_info'] = $GLOBALS['phpgw_setup']->detection->get_db_versions($GLOBALS['setup_info']);
	//var_dump($GLOBALS['setup_info']);exit;
	$GLOBALS['setup_info'] = $GLOBALS['phpgw_setup']->detection->compare_versions($GLOBALS['setup_info']);
	//var_dump($GLOBALS['setup_info']);exit;
	$GLOBALS['setup_info'] = $GLOBALS['phpgw_setup']->detection->check_depends($GLOBALS['setup_info']);
	//var_dump($GLOBALS['setup_info']);exit;
	@ksort($GLOBALS['setup_info']);

	if (get_var('cancel',Array('POST')))
	{
		Header('Location: index.php');
		exit;
	}

	$ConfigDomain = get_var('ConfigDomain',Array('POST','COOKIE'));
	$GLOBALS['phpgw_setup']->html->show_header(lang("Developers' Table Schema Toy"),False,'config',$ConfigDomain);

	if(get_var('submit',Array('POST')))
	{
		$GLOBALS['setup_tpl']->set_var('description',lang('App process') . ':');
		$GLOBALS['setup_tpl']->pparse('out','header');

		$appname = get_var('appname',Array('POST'));
		$install = get_var('install',Array('POST'));

		while (list($appname,$key) = @each($install))
		{
			$terror = array();
			$terror[$appname]['name'] = $appname;
			$terror[$appname]['version'] = $version[$appname];
			$terror[$appname]['status'] = 'U';

			$appdir  = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

			// Drop newest tables
			$terror[$appname]['tables'] = $GLOBALS['setup_info'][$appname]['tables'];
			$GLOBALS['phpgw_setup']->process->droptables($terror,$GLOBALS['DEBUG']);
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

				$terror = $GLOBALS['phpgw_setup']->process->droptables($terror,$GLOBALS['DEBUG']);
				$GLOBALS['phpgw_setup']->deregister_app($terror[$appname]['name']);

				$terror = $GLOBALS['phpgw_setup']->process->baseline($terror,$GLOBALS['DEBUG']);
				$terror = $GLOBALS['phpgw_setup']->process->test_data($terror,$GLOBALS['DEBUG']);

				$terror = $GLOBALS['phpgw_setup']->process->upgrade($terror,$GLOBALS['DEBUG']);
			}
			else
			{
				echo '<br>Baseline-only completed for ' . $terror[$appname]['name'];
			}
			echo '<br>' . $GLOBALS['setup_info'][$appname]['title'] . ' '
				. lang('tables installed, unless there are errors printed above') . '.';

			$GLOBALS['setup_info'][$appname]['version'] = $terror[$appname]['version'];
			$GLOBALS['phpgw_setup']->register_app($terror[$appname]['name']);
			echo '<br>' . $terror[$appname]['title'] . ' ' . lang('registered') . '.';
		}

		echo '<br><a href="schematoy.php">' . lang('Go back') . '</a>';
		$GLOBALS['setup_tpl']->pparse('out','footer');
		exit;
	}
	$detail = get_var('detail',Array('POST'));
	if($detail)
	{
		@ksort($GLOBALS['setup_info'][$detail]);
		@reset($GLOBALS['setup_info'][$detail]);
		$GLOBALS['setup_tpl']->set_var('description',lang('App details') . ':');
		$GLOBALS['setup_tpl']->pparse('out','header');
		
		while (list($key,$val) = each($GLOBALS['setup_info'][$detail]))
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

			$GLOBALS['setup_tpl']->set_var('bg_color',$bgcolor[$i]);
			$GLOBALS['setup_tpl']->set_var('name',$key);
			$GLOBALS['setup_tpl']->set_var('details',$val);
			$GLOBALS['setup_tpl']->pparse('out','detail');
		}

		echo '<br><a href="schematoy.php">' . lang('Go back') . '</a>';
		$GLOBALS['setup_tpl']->pparse('out','footer');
		exit;
	}
	else
	{
		$GLOBALS['setup_tpl']->set_var('description',lang("Select an app, enter a target version, then submit to process to that version.<br>If you do not enter a version, only the baseline tables will be installed for the app.<br><blink>THIS WILL DROP ALL OF THE APPS' TABLES FIRST!</blink>"));
		$GLOBALS['setup_tpl']->pparse('out','header');

		$GLOBALS['setup_tpl']->set_var('appdata',lang('Application Data'));
		$GLOBALS['setup_tpl']->set_var('actions',lang('Actions'));
		$GLOBALS['setup_tpl']->set_var('action_url','schematoy.php');
		$GLOBALS['setup_tpl']->set_var('app_info',lang('Application Name and Status'));
		$GLOBALS['setup_tpl']->set_var('app_title',lang('Application Title'));
		$GLOBALS['setup_tpl']->set_var('app_version',lang('Target Version'));
		$GLOBALS['setup_tpl']->set_var('app_install',lang('Process'));
		$GLOBALS['setup_tpl']->pparse('out','app_header');

		@reset ($GLOBALS['setup_info']);
		while (list ($key, $value) = each ($GLOBALS['setup_info']))
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
			$GLOBALS['setup_tpl']->set_var('select_version',$s);

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
				$GLOBALS['setup_tpl']->set_var('apptitle',$value['title']);
				$GLOBALS['setup_tpl']->set_var('currentver',$value['currentver']);
				$GLOBALS['setup_tpl']->set_var('bg_color',$bgcolor[$i]);

				$GLOBALS['setup_tpl']->set_var('instimg','completed.png');
				$GLOBALS['setup_tpl']->set_var('instalt',lang('Completed'));
				$GLOBALS['setup_tpl']->set_var('install','<input type="checkbox" name="install[' . $value['name'] . ']">');
				$status = lang('OK') . ' - ' . $value['status'];

				$GLOBALS['setup_tpl']->set_var('appinfo',$value['name'] . '-' . $status);
				$GLOBALS['setup_tpl']->set_var('appname',$value['name']);

				$GLOBALS['setup_tpl']->pparse('out','apps',True);
			}
		}
	}
	$GLOBALS['setup_tpl']->set_var('submit',lang('Save'));
	$GLOBALS['setup_tpl']->set_var('cancel',lang('Cancel'));
	$GLOBALS['setup_tpl']->pparse('out','app_footer');
	$GLOBALS['setup_tpl']->pparse('out','footer');
	$GLOBALS['phpgw_setup']->html->show_footer();
?>
