<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
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
		'nocachecontrol' => True,
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',
		'noapi' => True
	);
	include('./inc/functions.inc.php');

	include('../phpgwapi/setup/setup.inc.php');
	$GLOBALS['phpgw_info']['server']['versions']['current_header'] = $setup_info['phpgwapi']['versions']['current_header'];
	unset($setup_info);
	#include('../version.inc.php');

	function check_form_values()
	{
		if (! $GLOBALS['HTTP_POST_VARS']['setting']['config_pass'])
		{
			$errors .= '<br>' . lang("You didn't enter a config password");
		}
		if (! $GLOBALS['HTTP_POST_VARS']['setting']['HEADER_ADMIN_PASSWORD'])
		{
			$errors .= '<br>' . lang("You didn't enter a header admin password");
		}

		if ($errors)
		{
			$GLOBALS['phpgw_setup']->html->show_header('Error',True);
			echo $errors;
			exit;
		}
	}

	/* authentication phase */
	$GLOBALS['phpgw_info']['setup']['stage']['header'] = $GLOBALS['phpgw_setup']->detection->check_header();

	// added these to let the app work, need to templatize still
	$tpl_root = $GLOBALS['phpgw_setup']->html->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl',
		'T_login_main' => 'login_main.tpl',
		'T_login_stage_header' => 'login_stage_header.tpl',
		'T_setup_manage' => 'manageheader.tpl'
	));
	$setup_tpl->set_block('T_login_stage_header','B_multi_domain','V_multi_domain');
	$setup_tpl->set_block('T_login_stage_header','B_single_domain','V_single_domain');

	/* Detect current mode */
	switch($GLOBALS['phpgw_info']['setup']['stage']['header'])
	{
		case '1':
			$GLOBALS['phpgw_info']['setup']['HeaderFormMSG'] = lang('Create your header.inc.php');
			$GLOBALS['phpgw_info']['setup']['PageMSG'] = lang('You have not created your header.inc.php yet!<br> You can create it now.');
			break;
		case '2':
			$GLOBALS['phpgw_info']['setup']['HeaderFormMSG'] = lang('Your header admin password is NOT set. Please set it now!');
			$GLOBALS['phpgw_info']['setup']['PageMSG'] = lang('Your header admin password is NOT set. Please set it now!');
			break;
		case '3':
			$GLOBALS['phpgw_info']['setup']['HeaderFormMSG'] = lang('Your header.inc.php needs upgrading.');
			$GLOBALS['phpgw_info']['setup']['PageMSG'] = lang('Your header.inc.php needs upgrading.<br><blink><font color=CC0000><b>WARNING!</b></font></blink><br>If you are using virtual domain support, this will <b>NOT</b> copy those domains over.  You will need to do this manually, <b>MAKE BACKUPS!</b>');
			$GLOBALS['phpgw_info']['setup']['HeaderLoginMSG'] = lang('Your header.inc.php needs upgrading.');
			if (!$GLOBALS['phpgw_setup']->auth('Header'))
			{
				$GLOBALS['phpgw_setup']->html->show_header('Please login',True);
				$GLOBALS['phpgw_setup']->html->login_form();
				$GLOBALS['phpgw_setup']->html->show_footer();
				exit;
			}
			break;
		case '10':
			if (!$GLOBALS['phpgw_setup']->auth('Header'))
			{
				$GLOBALS['phpgw_setup']->html->show_header('Please login',True);
				$GLOBALS['phpgw_setup']->html->login_form();
				$GLOBALS['phpgw_setup']->html->show_footer();
				exit;
			}
			$GLOBALS['phpgw_info']['setup']['HeaderFormMSG'] = lang('Edit your header.inc.php');
			$GLOBALS['phpgw_info']['setup']['PageMSG'] = lang('Edit your existing header.inc.php');
			break;
	}

	switch($HTTP_POST_VARS['action'])
	{
		case 'download':
			check_form_values();
			$header_template = CreateObject('phpgwapi.Template','../');
			$b = CreateObject('phpgwapi.browser');
			$b->content_header('header.inc.php','application/octet-stream');
			/*
			header('Content-disposition: attachment; filename="header.inc.php"');
			header('Content-type: application/octet-stream');
			header('Pragma: no-cache');
			header('Expires: 0');
			*/
			$newheader = $GLOBALS['phpgw_setup']->html->generate_header();
			echo $newheader;
			break;
		case 'view':
			check_form_values();
			$header_template = CreateObject('phpgwapi.Template','../');
			$GLOBALS['phpgw_setup']->html->show_header('Generated header.inc.php', False, 'header');
			echo '<br>' . lang('Save this text as contents of your header.inc.php') . '<br><hr>';
			$newheader = $GLOBALS['phpgw_setup']->html->generate_header();
			echo '<pre>';
			echo htmlentities($newheader);
			echo '</pre><hr>';
			echo '<form action="index.php" method="post">';
			echo '<br>' . lang('After retrieving the file, put it into place as the header.inc.php.  Then, click "continue".') . '<br>';
			echo '<input type="hidden" name="FormLogout" value="header">';
			echo '<input type="submit" name="junk" value="continue">';
			echo '</form>';
			echo '</body></html>';
			break;
		case 'write config':
			check_form_values();
			$header_template = CreateObject('phpgwapi.Template','../');
			if(is_writeable('../header.inc.php') || (!file_exists('../header.inc.php') && is_writeable('../')))
			{
				$newheader = $GLOBALS['phpgw_setup']->html->generate_header();
				$fsetup = fopen('../header.inc.php','wb');
				fwrite($fsetup,$newheader);
				fclose($fsetup);
				$GLOBALS['phpgw_setup']->html->show_header('Saved header.inc.php', False, 'header');
				echo '<form action="index.php" method="post">';
				echo '<br>Created header.inc.php! ';
				echo '<input type="hidden" name="FormLogout" value="header">';
				echo '<input type="submit" name="junk" value="continue">';
				echo '</form>';
				echo '</body></html>';
				break;
			}
			else
			{
				$GLOBALS['phpgw_setup']->html->show_header('Error generating header.inc.php', False, 'header');
				echo lang('Could not open header.inc.php for writing!') . '<br>' . "\n";
				echo lang('Please check read/write permissions on directories, or back up and use another option.') . '<br>';
				echo '</td></tr></table></body></html>';
			}
			break;
		default:
			$GLOBALS['phpgw_setup']->html->show_header($GLOBALS['phpgw_info']['setup']['HeaderFormMSG'], False, 'header');

			$detected  = $GLOBALS['phpgw_info']['setup']['PageMSG'];
			$detected .= '<table border="0" width="100%" cellspacing="0" cellpadding="2">';
			$detected .= '<tr bgcolor="486591"><td align="center" colspan="2"><font color="fefefe">' . lang('Analysis') . '</font></td></tr><tr><td colspan="2">';

			$supported_db = array();
			if (extension_loaded('mysql') || function_exists('mysql_connect'))
			{
				$detected .= lang('You appear to have MySQL support enabled') . '<br>' . "\n";
				$supported_db[] = 'mysql';
			}
			else
			{
				$detected .= lang('No MySQL support found. Disabling') . '<br>' . "\n";
			}
			if (extension_loaded('pgsql') || function_exists('pg_connect'))
			{
				$detected .= lang('You appear to have Postgres-DB support enabled') . '<br>' . "\n";
				$supported_db[]  = 'pgsql';
			}
			else
			{
				$detected .= lang('No Postgres-DB support found. Disabling') . '<br>' . "\n";
			}
			if (extension_loaded('mssql') || function_exists('mssql_connect'))
			{
				$detected .= lang('You appear to have Microsoft SQL Server support enabled') . '<br>' . "\n";
				$supported_db[] = 'mssql';
			}
			else
			{
				$detected .= lang('No Microsoft SQL Server support found. Disabling') . '<br>' . "\n";
			}
			if (extension_loaded('oci8'))
			{
				$detected .= lang('You appear to have Oracle V8 (OCI) support enabled') . '<br>' . "\n";
				$supported_db[] = 'oracle';
			}
			else
			{
				if(extension_loaded('oracle'))
				{
					$detected .= lang('You appear to have Oracle support enabled') . '<br>' . "\n";
					$supported_db[] = 'oracle';
				}
				else
				{
					$detected .= lang('No Oracle-DB support found. Disabling') . '<br>' . "\n";
				}
			}
			if(!count($supported_db))
			{
				$detected .= '<b><p align="center"><font size="+2" color="red">'
					. lang('Did not find any valid DB support!')
					. '<br>'
					. lang('Try to configure your php to support one of the above mentioned DBMS, or install phpGroupWare by hand.')
					. '</font></p></b><td></tr></table></body></html>';
				echo $detected;
				exit;
			}

			if (floor(phpversion()) == 3)
			{
				$detected .= lang('You appear to be using PHP3. Disabling PHP4 sessions support') . '<br>' . "\n";
				$supported_sessions_type[] = 'db';
			}
			else
			{
				$detected .= lang('You appear to be using PHP4. Enabling PHP4 sessions support') . '<br>' . "\n";
				$supported_sessions_type[] = 'db';
				$supported_sessions_type[] = 'php4';
			}

			/*
			if (extension_loaded('xml') || function_exists('xml_parser_create'))
			{
				$detected .= lang('You appear to have XML support enabled') . '<br>' . "\n";
				$xml_enabled = 'True';
			}
			else
			{
				$detected .= lang('No XML support found. Disabling') . '<br>' . "\n";
			}
			*/

			$no_guess = False;
			if(file_exists('../header.inc.php') && is_file('../header.inc.php'))
			{
				$detected .= lang('Found existing configuration file. Loading settings from the file...') . '<br>' . "\n";
				$GLOBALS['phpgw_info']['flags']['noapi'] = True;
				include('../header.inc.php');
				$no_guess = true;
				/* This code makes sure the newer multi-domain supporting header.inc.php is being used */
				if(!isset($phpgw_domain))
				{
					$detected .= lang("You're using an old configuration file format...") . '<br>' . "\n";
					$detected .= lang('Importing old settings into the new format....') . '<br>' . "\n";
				}
				else
				{
					if($GLOBALS['phpgw_info']['server']['header_version'] != $GLOBALS['phpgw_info']['server']['current_header_version'])
					{
						$detected .= lang("You're using an old header.inc.php version...") . '<br>' . "\n";
						$detected .= lang('Importing old settings into the new format....') . '<br>' . "\n";
					}
					reset($phpgw_domain);
					$default_domain = each($phpgw_domain);
					$GLOBALS['phpgw_info']['server']['default_domain'] = $default_domain[0];
					unset($default_domain); // we kill this for security reasons
					$GLOBALS['phpgw_info']['server']['db_host'] = $phpgw_domain[$GLOBALS['phpgw_info']['server']['default_domain']]['db_host'];
					$GLOBALS['phpgw_info']['server']['db_name'] = $phpgw_domain[$GLOBALS['phpgw_info']['server']['default_domain']]['db_name'];
					$GLOBALS['phpgw_info']['server']['db_user'] = $phpgw_domain[$GLOBALS['phpgw_info']['server']['default_domain']]['db_user'];
					$GLOBALS['phpgw_info']['server']['db_pass'] = $phpgw_domain[$GLOBALS['phpgw_info']['server']['default_domain']]['db_pass'];
					$GLOBALS['phpgw_info']['server']['db_type'] = $phpgw_domain[$GLOBALS['phpgw_info']['server']['default_domain']]['db_type'];
					$GLOBALS['phpgw_info']['server']['config_passwd'] = $phpgw_domain[$GLOBALS['phpgw_info']['server']['default_domain']]['config_passwd'];
				}
				if (defined('PHPGW_SERVER_ROOT'))
				{
					$GLOBALS['phpgw_info']['server']['server_root'] = PHPGW_SERVER_ROOT;
					$GLOBALS['phpgw_info']['server']['include_root'] = PHPGW_INCLUDE_ROOT; 
				}
				elseif(!isset($GLOBALS['phpgw_info']['server']['include_root']) && $GLOBALS['phpgw_info']['server']['header_version'] <= 1.6)
				{
					$GLOBALS['phpgw_info']['server']['include_root'] = $GLOBALS['phpgw_info']['server']['server_root'];
				}
				elseif(!isset($GLOBALS['phpgw_info']['server']['header_version']) && $GLOBALS['phpgw_info']['server']['header_version'] <= 1.6)
				{
					$GLOBALS['phpgw_info']['server']['include_root'] = $GLOBALS['phpgw_info']['server']['server_root'];
				}
			}
			else
			{
				$detected .= lang('Sample configuration not found. using built in defaults') . '<br>' . "\n";
				$GLOBALS['phpgw_info']['server']['server_root'] = '/path/to/phpgroupware';
				$GLOBALS['phpgw_info']['server']['include_root'] = '/path/to/phpgroupware';
				/* This is the basic include needed on each page for phpGroupWare application compliance */
				$GLOBALS['phpgw_info']['flags']['htmlcompliant'] = True;

				/* These are the settings for the database system */
				$GLOBALS['phpgw_info']['server']['db_host'] = 'localhost';
				$GLOBALS['phpgw_info']['server']['db_name'] = 'phpgroupware';
				$GLOBALS['phpgw_info']['server']['db_user'] = 'phpgroupware';
				$GLOBALS['phpgw_info']['server']['db_pass'] = 'your_password';
				$GLOBALS['phpgw_info']['server']['db_type'] = 'mysql'; //mysql, pgsql (for postgresql), or oracle

				/* These are a few of the advanced settings */
				$GLOBALS['phpgw_info']['server']['db_persistent'] = True;
				$GLOBALS['phpgw_info']['server']['config_passwd'] = 'changeme';
				$GLOBALS['phpgw_info']['server']['mcrypt_enabled'] = False;
				$GLOBALS['phpgw_info']['server']['mcrypt_version'] = '2.6.3';

				srand((double)microtime()*1000000);
				$random_char = array(
					'0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
					'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
					'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
					'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
				);

				for($i=0; $i<30; $i++)
				{
					$GLOBALS['phpgw_info']['server']['mcrypt_iv'] .= $random_char[rand(1,count($random_char))];
				}
			}

			// now guessing better settings then the default ones 
			if(!$no_guess)
			{
				$detected .= lang('Now guessing better values for defaults...') . '<br>' . "\n";
				$this_dir = dirname($SCRIPT_FILENAME);
				$updir    = ereg_replace('/setup','',$this_dir);
				$GLOBALS['phpgw_info']['server']['server_root'] = $updir; 
				$GLOBALS['phpgw_info']['server']['include_root'] = $updir; 
			}

			$setup_tpl->set_var('detected',$detected);
			/* End of detected settings, now display the form with the detected or prior values */

			$setup_tpl->set_var('server_root',$GLOBALS['phpgw_info']['server']['server_root']);
			$setup_tpl->set_var('include_root',$GLOBALS['phpgw_info']['server']['include_root']);
			$setup_tpl->set_var('header_admin_password',$GLOBALS['phpgw_info']['server']['header_admin_password']);
			$setup_tpl->set_var('db_host',$GLOBALS['phpgw_info']['server']['db_host']);
			$setup_tpl->set_var('db_name',$GLOBALS['phpgw_info']['server']['db_name']);
			$setup_tpl->set_var('db_user',$GLOBALS['phpgw_info']['server']['db_user']);
			$setup_tpl->set_var('db_pass',$GLOBALS['phpgw_info']['server']['db_pass']);

			$selected = '';
			$db_type_option = '';
			$found_dbtype = False;
			while(list($k,$v) = each($supported_db))
			{
				if($v == $GLOBALS['phpgw_info']['server']['db_type'])
				{
					$selected = ' selected ';
					$found_dbtype = true;
				}
				else
				{
					$selected = '';
				}
				$dbtype_options .= '<option ' . $selected . ' value="' . $v . '">' . $v . "\n";
			}
			$setup_tpl->set_var('dbtype_options',$dbtype_options);

			$setup_tpl->set_var('config_passwd',$GLOBALS['phpgw_info']['server']['config_passwd']);

			if($GLOBALS['phpgw_info']['server']['db_persistent'])
			{
				$setup_tpl->set_var('db_persistent_yes',' selected');
			}
			else
			{
				$setup_tpl->set_var('db_persistent_no',' selected');
			}

			$selected = '';
			$session_type_option = '';
			while(list($k,$v) = each($supported_sessions_type))
			{
				if($v == $GLOBALS['phpgw_info']['server']['sessions_type'])
				{
					$selected = ' selected ';
				}
				else
				{
					$selected = '';
				}
				$session_options .= '<option ' . $selected . ' value="' . $v . '">' . $v . "\n";
			}
			$setup_tpl->set_var('session_options',$session_options);

			if($GLOBALS['phpgw_info']['server']['mcrypt_enabled'])
			{
				$setup_tpl->set_var('mcrypt_enabled_yes',' selected');
			}
			else
			{
				$setup_tpl->set_var('mcrypt_enabled_no',' selected');
			}

			$setup_tpl->set_var('mcrypt',$GLOBALS['phpgw_info']['server']['versions']['mcrypt']);
			$setup_tpl->set_var('mcrypt_iv',$GLOBALS['phpgw_info']['server']['mcrypt_iv']);

			if($GLOBALS['phpgw_info']['server']['domain_selectbox'])
			{
				$setup_tpl->set_var('domain_selectbox_yes',' selected');
			}
			else
			{
				$setup_tpl->set_var('domain_selectbox_no',' selected');
			}

			$errors = '';
			if(!$found_dbtype)
			{
				$errors .= '<br><font color="red">' . lang('Warning!') . '<br>'
					. lang('The db_type in defaults (x) is not supported on this server. using first supported type.',$GLOBALS['phpgw_info']['server']['db_type'])
					. '</font>';
			}

			if(is_writeable('../header.inc.php') ||
				(!file_exists('../header.inc.php') && is_writeable('../')))
			{
				$errors .= '<br><input type="submit" name="action" value="write config">&nbsp;'
					. lang('or') . '&nbsp;<input type="submit" name="action" value="download">&nbsp;'
					. lang('or') . '&nbsp;<input type=submit name="action" value="view"> the file.</form>';
			}
			else
			{
				$errors .= '<br>'
					. lang('Cannot create the header.inc.php due to file permission restrictions.<br> Instead you can x the file.',
					'<input type="submit" name="action" value="download">' . lang('or') . '&nbsp;<input type="submit" name="action" value="view">')
					. '</form>';
			}
			$setup_tpl->set_var('errors',$errors);

			$setup_tpl->set_var('lang_settings',lang('Settings'));
			$setup_tpl->set_var('lang_serverroot',lang('Server Root'));
			$setup_tpl->set_var('lang_includeroot',lang('Include Root (this should be the same as Server Root unless you know what you are doing)'));
			$setup_tpl->set_var('lang_adminpass',lang('Admin password to header manager'));
			$setup_tpl->set_var('lang_dbhost',lang('DB Host'));
			$setup_tpl->set_var('lang_dbname',lang('DB Name'));
			$setup_tpl->set_var('lang_dbuser',lang('DB User'));
			$setup_tpl->set_var('lang_dbpass',lang('DB Password'));
			$setup_tpl->set_var('lang_dbtype',lang('DB Type'));
			$setup_tpl->set_var('lang_whichdb',lang('Which database type do you want to use with phpGroupWare?'));
			$setup_tpl->set_var('lang_configpass',lang('Configuration Password'));
			$setup_tpl->set_var('lang_passforconfig',lang('Password needed for configuration'));
			$setup_tpl->set_var('lang_persist',lang('Persistent connections'));
			$setup_tpl->set_var('lang_persistdescr',lang('Do you want persistent connections (higher performance, but consumes more resources)'));
			$setup_tpl->set_var('lang_sesstype',lang('Sessions Type'));
			$setup_tpl->set_var('lang_sesstypedescr',lang('What type of sessions management do you want to use (PHP4 session management may perform better)?'));
			$setup_tpl->set_var('lang_enablemcrypt',lang('Enable MCrypt'));
			$setup_tpl->set_var('lang_mcryptversion',lang('MCrypt version'));
			$setup_tpl->set_var('lang_mcryptversiondescr',lang('Set this to "old" for versions &lt; 2.4, otherwise the exact mcrypt version you use.'));
			$setup_tpl->set_var('lang_mcryptiv',lang('MCrypt initialization vector'));
			$setup_tpl->set_var('lang_mcryptivdescr',lang('This should be around 30 bytes in length.<br>Note: The default has been randomly generated.'));
			$setup_tpl->set_var('lang_domselect',lang('Domain select box on login'));
			$setup_tpl->set_var('lang_finaldescr',lang('After retrieving the file, put it into place as the header.inc.php.  Then, click "continue".'));

			$setup_tpl->pfp('out','T_setup_manage');
			break; // ending the switch default
	}
?>
