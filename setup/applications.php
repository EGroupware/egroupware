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

	$DEBUG = False;
	// TODO: We allow a user to hose their setup here, need to make use
	// of dependencies so they are warned that they are pulling the rug
	// out from under other apps.  e.g. if they select to uninstall the api
	// this will happen without further warning.

	$phpgw_info = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',
		'noapi' => True
	);
	include ('./inc/functions.inc.php');

	// Check header and authentication
	if (!$phpgw_setup->auth('Config'))
	{
		Header('Location: index.php');
		exit;
	}
	// Does not return unless user is authorized

	$ConfigDomain = $HTTP_COOKIE_VARS['ConfigDomain'] ? $HTTP_COOKIE_VARS['ConfigDomain'] : $HTTP_POST_VARS['ConfigDomain'];

	$tpl_root = $phpgw_setup->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl',
		'T_login_main' => 'login_main.tpl',
		'T_login_stage_header' => 'login_stage_header.tpl',
		'T_setup_main' => 'applications.tpl'
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

	$bgcolor = array('#DDDDDD','#EEEEEE');

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
	$GLOBALS['phpgw_info']['setup']['stage']['db'] = $phpgw_setup->check_db();

	$setup_info = $phpgw_setup->get_versions();
	//var_dump($setup_info);exit;
	$setup_info = $phpgw_setup->get_db_versions($setup_info);
	//var_dump($setup_info);exit;
	$setup_info = $phpgw_setup->compare_versions($setup_info);
	//var_dump($setup_info);exit;
	$setup_info = $phpgw_setup->check_depends($setup_info);
	//var_dump($setup_info);exit;
	@ksort($setup_info);

	if ($HTTP_POST_VARS['cancel'])
	{
		Header("Location: index.php");
		exit;
	}

	if ($HTTP_POST_VARS['submit'])
	{
		$phpgw_setup->show_header(lang('Application Management'),False,'config',$ConfigDomain . '(' . $phpgw_domain[$ConfigDomain]['db_type'] . ')');
		$setup_tpl->set_var('description',lang('App install/remove/upgrade') . ':');
		$setup_tpl->pparse('out','header');

		$appname = $HTTP_POST_VARS['appname'];
		$remove  = $HTTP_POST_VARS['remove'];
		$install = $HTTP_POST_VARS['install'];
		$upgrade = $HTTP_POST_VARS['upgrade'];

		while (list($appname,$key) = @each($remove))
		{
			$terror = array();
			$terror[] = $setup_info[$appname];

			if ($setup_info[$appname]['tables'])
			{
				$phpgw_setup->process_droptables($terror,$DEBUG);
				echo '<br>' . $setup_info[$appname]['title'] . ' ' . lang('tables dropped') . '.';
			}

			$phpgw_setup->deregister_app($setup_info[$appname]['name']);
			echo '<br>' . $setup_info[$appname]['title'] . ' ' . lang('deregistered') . '.';

			if ($setup_info[$appname]['hooks'])
			{
				$phpgw_setup->deregister_hooks($setup_info[$appname]['name']);
				echo '<br>' . $setup_info[$appname]['title'] . ' ' . lang('hooks deregistered') . '.';
			}

			$dropped = False;
			$dropped = $phpgw_setup->drop_langs($appname);
			if($dropped)
			{
				echo '<br>' . $setup_info[$appname]['title'] . ' ' . lang('Translations removed') . '.';
			}
		}

		while (list($appname,$key) = @each($install))
		{
			$terror = array();
			$terror[] = $setup_info[$appname];

			if ($setup_info[$appname]['tables'])
			{
				$terror = $phpgw_setup->process_current($terror,$DEBUG);
				$terror = $phpgw_setup->process_default_records($terror,$DEBUG);
				echo '<br>' . $setup_info[$appname]['title'] . ' '
					. lang('tables installed, unless there are errors printed above') . '.';
			}
			else
			{
				if ($phpgw_setup->app_registered($setup_info[$appname]['name']))
				{
					$phpgw_setup->update_app($setup_info[$appname]['name']);
				}
				else
				{
					$phpgw_setup->register_app($setup_info[$appname]['name']);
				}
				echo '<br>' . $setup_info[$appname]['title'] . ' ' . lang('registered') . '.';

				if ($setup_info[$appname]['hooks'])
				{
					$phpgw_setup->register_hooks($setup_info[$appname]['name']);
					echo '<br>' . $setup_info[$appname]['title'] . ' ' . lang('hooks registered') . '.';
				}
			}
			$phpgw_setup->add_langs($appname);
			echo '<br>' . $setup_info[$appname]['title'] . ' ' . lang('Translations added') . '.';
		}

		while (list($appname,$key) = @each($upgrade))
		{
			$terror = array();
			$terror[] = $setup_info[$appname];

			$phpgw_setup->process_upgrade($terror,$DEBUG);
			if ($setup_info[$appname]['tables'])
			{
				echo '<br>' . $setup_info[$appname]['title'] . ' ' . lang('tables upgraded') . '.';
				// The process_upgrade() function also handles registration
			}
			else
			{
				echo '<br>' . $setup_info[$appname]['title'] . ' ' . lang('upgraded') . '.';
			}
		}

		//$setup_tpl->set_var('goback',
		echo '<br><a href="applications.php">' . lang('Go back') . '</a>';
		//$setup_tpl->pparse('out','submit');
		$setup_tpl->pparse('out','footer');
		exit;
	}
	else
	{
		$phpgw_setup->show_header(lang('Application Management'),False,'config',$ConfigDomain . '(' . $phpgw_domain[$ConfigDomain]['db_type'] . ')');
	}

	if($HTTP_GET_VARS['detail'])
	{
		$detail = $HTTP_GET_VARS['detail'];
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
				$tblcnt = count($setup_info[$detail][$key]);
				if(is_array($val))
				{
					$key = '<a href="sqltoarray.php?appname=' . $detail . '&submit=True">' . $key . '(' . $tblcnt . ')</a>' . "\n";
					$val = implode(',' . "\n",$val);
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

		echo '<br><a href="applications.php">' . lang('Go back') . '</a>';
		$setup_tpl->pparse('out','footer');
		exit;
	}
	elseif ($HTTP_GET_VARS['resolve'])
	{
		$resolve  = $HTTP_GET_VARS['resolve'];
		$version  = $HTTP_GET_VARS['version'];
		$notables = $HTTP_GET_VARS['notables'];
		$setup_tpl->set_var('description',lang('Problem resolution'). ':');
		$setup_tpl->pparse('out','header');
		if ($badinstall)
		{
			echo $setup_info[$resolve]['title'] . ' ' . lang('is broken') . ' ';
			echo lang('because of a failed upgrade or install') . '.';
			echo '<br>';
			echo lang('Some or all of its tables are missing') . '.';
			echo '<br>';
			echo lang('You should either uninstall and then reinstall it, or attempt manual repairs') . '.';
		}
		elseif (!$version)
		{
			if($setup_info[$resolve]['enabled'])
			{
				echo $setup_info[$resolve]['title'] . ' ' . lang('is broken') . ' ';
			}
			else
			{
				echo $setup_info[$resolve]['title'] . ' ' . lang('is disabled') . ' ';
			}

			if (!$notables)
			{
				if($setup_info[$resolve]['status'] == 'D')
				{
					echo lang('because it depends upon') . ':<br>' . "\n";
					list($depapp,$depver) = parsedep($setup_info[$resolve]['depends'],False);
					for ($i=0;$i<count($depapp);$i++)
					{
						echo '<br>' . $depapp[$i] . ': ';
						$list = '';
						while(list($x,$y) = @each($depver[$i]))
						{
							$list .= $y . ', ';
						}
						$list = substr($list,0,-2);
						echo "$list\n";
					}
					echo '<br><br>' . lang('The table definition was correct, and the tables were installed') . '.';
				}
				else
				{
					echo lang('because it was manually disabled') . '.';
				}
			}
			elseif($setup_info[$resolve]['enable'] == 2)
			{
				echo lang('because it is not a user application, or access is controlled via acl') . '.';
			}
			elseif($setup_info[$resolve]['enable'] == 0)
			{
				echo lang('because the enable flag for this app is set to 0, or is undefined') . '.';
			}
			else
			{
				echo lang('because it requires manual table installation, <br>or the table definition was incorrect') . ".\n"
					. lang("Please check for sql scripts within the application's directory") . '.';
			}
			echo '<br>' . lang('However, the application is otherwise installed') . '.';
		}
		else
		{
			echo $setup_info[$resolve]['title'] . ' ' . lang('has a version mismatch') . ' ';
			echo lang('because of a failed upgrade, or the database is newer than the installed version of this app') . '.';
			echo '<br>';
			echo lang('If the application has no defined tables, selecting upgrade should remedy the problem') . '.';
			echo '<br>' . lang('However, the application is otherwise installed') . '.';
		}

		echo '<br><a href="applications.php">' . lang('Go back') . '</a>';
		$setup_tpl->pparse('out','footer');
		exit;
	}
	else
	{
		$setup_tpl->set_var('description',lang('Select the desired action(s) from the available choices'));
		$setup_tpl->pparse('out','header');

		$setup_tpl->set_var('appdata',lang('Application Data'));
		$setup_tpl->set_var('actions',lang('Actions'));
		$setup_tpl->set_var('action_url','applications.php');
		$setup_tpl->set_var('app_info',lang('Application Name and Status Information'));
		$setup_tpl->set_var('app_title',lang('Application Title'));
		$setup_tpl->set_var('app_currentver',lang('Current Version'));
		$setup_tpl->set_var('app_version',lang('Available Version'));
		$setup_tpl->set_var('app_install',lang('Install'));
		$setup_tpl->set_var('app_remove',lang('Remove'));
		$setup_tpl->set_var('app_upgrade',lang('Upgrade'));
		$setup_tpl->set_var('app_resolve',lang('Resolve'));
		$setup_tpl->pparse('out','app_header');

		@reset ($setup_info);
		while (list ($key, $value) = each ($setup_info))
		{
			if($value['name'])
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
				$setup_tpl->set_var('version',$value['version']);
				$setup_tpl->set_var('bg_color',$bgcolor[$i]);

				switch($value['status'])
				{
					case 'C':
						$setup_tpl->set_var('remove','<input type="checkbox" name="remove[' . $value['name'] . ']">');
						$setup_tpl->set_var('upgrade','&nbsp;');
						if (!$phpgw_setup->check_app_tables($value['name']))
						{
							// App installed and enabled, but some tables are missing
							$setup_tpl->set_var('instimg','table.gif');
							$setup_tpl->set_var('bg_color','FFCCAA');
							$setup_tpl->set_var('instalt',lang('Not Completed'));
							$setup_tpl->set_var('resolution','<a href="applications.php?resolve=' . $value['name'] . '&badinstall=True">' . lang('Potential Problem') . '</a>');
							$status = lang('Requires reinstall or manual repair') . ' - ' . $value['status'];
						}
						else
						{
							$setup_tpl->set_var('instimg','completed.gif');
							$setup_tpl->set_var('instalt',lang('Completed'));
							$setup_tpl->set_var('install','&nbsp;');
							if($value['enabled'])
							{
								$setup_tpl->set_var('resolution','');
								$status = lang('OK') . ' - ' . $value['status'];
							}
							else
							{
								if ($value['tables'][0] != '')
								{
									$notables = '&notables=True';
								}
								$setup_tpl->set_var('bg_color','CCCCFF');
								$setup_tpl->set_var('resolution',
									'<a href="applications.php?resolve=' . $value['name'] .  $notables . '">' . lang('Possible Reasons') . '</a>'
								);
								$status = lang('Disabled') . ' - ' . $value['status'];
							}
						}
						break;
					case 'U':
						$setup_tpl->set_var('instimg','incomplete.gif');
						$setup_tpl->set_var('instalt',lang('Not Completed'));
						if (!$value['currentver'])
						{
							if ($value['tables'] && $phpgw_setup->check_app_tables($value['name'],True))
							{
								// Some tables missing
								$setup_tpl->set_var('remove','<input type="checkbox" name="remove[' . $value['name'] . ']">');
								$setup_tpl->set_var('resolution','<a href="applications.php?resolve=' . $value['name'] . '&badinstall=True">' . lang('Potential Problem') . '</a>');
								$status = lang('Requires reinstall or manual repair') . ' - ' . $value['status'];
							}
							else
							{
								$setup_tpl->set_var('remove','&nbsp;');
								$setup_tpl->set_var('resolution','');
								$status = lang('Requires upgrade') . ' - ' . $value['status'];
							}
							$setup_tpl->set_var('bg_color','CCFFCC');
							$setup_tpl->set_var('install','<input type="checkbox" name="install[' . $value['name'] . ']">');
							$setup_tpl->set_var('upgrade','&nbsp;');
							$status = lang('Please install') . ' - ' . $value['status'];
						}
						else
						{
							$setup_tpl->set_var('bg_color','CCCCFF');
							$setup_tpl->set_var('install','&nbsp;');
							// TODO display some info about breakage if you mess with this app
							$setup_tpl->set_var('upgrade','<input type="checkbox" name="upgrade[' . $value['name'] . ']">');
							$setup_tpl->set_var('remove','<input type="checkbox" name="remove[' . $value['name'] . ']">');
							$setup_tpl->set_var('resolution','');
							$status = lang('Requires upgrade') . ' - ' . $value['status'];
						}
						break;
					case 'V':
						$setup_tpl->set_var('instimg','incomplete.gif');
						$setup_tpl->set_var('instalt',lang('Not Completed'));
						$setup_tpl->set_var('install','&nbsp;');
						$setup_tpl->set_var('remove','<input type="checkbox" name="remove[' . $value['name'] . ']">');
						$setup_tpl->set_var('upgrade','<input type="checkbox" name="upgrade[' . $value['name'] . ']">');
						$setup_tpl->set_var('resolution','<a href="applications.php?resolve=' . $value['name'] . '&version=True">' . lang('Possible Solutions') . '</a>');
						$status = lang('Version Mismatch') . ' - ' . $value['status'];
						break;
					case 'D':
						$setup_tpl->set_var('bg_color','FFCCCC');
						$depstring = parsedep($value['depends']);
						$depstring .= ')';
						$setup_tpl->set_var('instimg','dep.gif');
						$setup_tpl->set_var('instalt',lang('Dependency Failure'));
						$setup_tpl->set_var('install','&nbsp;');
						$setup_tpl->set_var('remove','&nbsp;');
						$setup_tpl->set_var('upgrade','&nbsp;');
						$setup_tpl->set_var('resolution','<a href="applications.php?resolve=' . $value['name'] . '">' . lang('Possible Solutions') . '</a>');
						$status = lang('Dependency Failure') . ':' . $depstring . $value['status'];
						break;
					default:
						$setup_tpl->set_var('instimg','incomplete.gif');
						$setup_tpl->set_var('instalt',lang('Not Completed'));
						$setup_tpl->set_var('install','&nbsp;');
						$setup_tpl->set_var('remove','&nbsp;');
						$setup_tpl->set_var('upgrade','&nbsp;');
						$setup_tpl->set_var('resolution','');
						$status = '';
						break;
				}
				//$setup_tpl->set_var('appname',$value['name'] . '-' . $status . ',' . $value['filename']);
				$setup_tpl->set_var('appinfo',$value['name'] . '-' . $status);
				$setup_tpl->set_var('appname',$value['name']);

				$setup_tpl->pparse('out','apps',True);
			}
		}

		$setup_tpl->set_var('submit',lang('Submit'));
		$setup_tpl->set_var('cancel',lang('Cancel'));
		$setup_tpl->pparse('out','app_footer');
		$setup_tpl->pparse('out','footer');
		$phpgw_setup->show_footer();
	}
?>
