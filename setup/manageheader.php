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

	$phpgw_info['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',
		'noapi' => True
	);
	include('./inc/functions.inc.php');

	include('../phpgwapi/setup/setup.inc.php');
	$phpgw_info['server']['versions']['current_header'] = $setup_info['phpgwapi']['versions']['current_header'];
	unset($setup_info);
	#include('../version.inc.php');

	function check_form_values()
	{
		global $setting, $phpgw_setup;

		if (! $setting['config_pass'])
		{
			$errors .= "<br>You didn't enter a config password";
		}
		if (! $setting['HEADER_ADMIN_PASSWORD'])
		{
			$errors .= "<br>You didn't enter a header admin password";
		}

		if ($errors)
		{
			$phpgw_setup->show_header('Error',True);
			echo $errors;
			exit;
		}
	}

	/* authentication phase */
	$phpgw_info['setup']['stage']['header'] = $phpgw_setup->check_header();

	// added these to let the app work, need to templatize still
	$tpl_root = $phpgw_setup->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl',
		'T_login_main' => 'login_main.tpl',
		'T_login_stage_header' => 'login_stage_header.tpl',
		'T_setup_main' => 'setup_main.tpl',
		'T_setup_db_blocks' => 'setup_db_blocks.tpl'
	));
	$setup_tpl->set_block('T_login_stage_header','B_multi_domain','V_multi_domain');
	$setup_tpl->set_block('T_login_stage_header','B_single_domain','V_single_domain');

	switch($phpgw_info['setup']['stage']['header'])
	{
		case '1':
			$phpgw_info['setup']['HeaderFormMSG'] = 'Create your header.inc.php';
			$phpgw_info['setup']['PageMSG'] = 'You have not created your header.inc.php yet!<br> You can create it now.';
			break;
		case '2':
			$phpgw_info['setup']['HeaderFormMSG'] = 'Your header admin password is NOT set. Please set it now!';
			$phpgw_info['setup']['PageMSG'] = 'Your header admin password is NOT set. Please set it now!';
			break;
		case '3':
			$phpgw_info['setup']['HeaderFormMSG'] = 'Your header.inc.php needs upgrading.';
			$phpgw_info['setup']['PageMSG'] = 'Your header.inc.php needs upgrading.<br><blink><font color=CC0000><b>WARNING!</b></font></blink><br>If you are using virtual domain support, this will <b>NOT</b> copy those domains over.  You will need to do this manually, <b>MAKE BACKUPS!</b>';
			$phpgw_info['setup']['HeaderLoginMSG'] = 'Your header.inc.php needs upgrading.';
			if (!$phpgw_setup->auth('Header'))
			{
				$phpgw_setup->show_header('Please login',True);
				$phpgw_setup->login_form();
				exit;
			}
			break;
		case '10':
			if (!$phpgw_setup->auth('Header'))
			{
				$phpgw_setup->show_header('Please login',True);
				$phpgw_setup->login_form();
				exit;
			}
			$phpgw_info['setup']['HeaderFormMSG'] = 'Edit your header.inc.php';
			$phpgw_info['setup']['PageMSG'] = 'Edit your existing header.inc.php';
			break;
	}

	switch($action)
	{
		case 'download':
			check_form_values();
			$header_template = new Template('../');
			header('Content-disposition: attachment; filename="header.inc.php"');
			header('Content-type: application/octet-stream');
			header('Pragma: no-cache');
			header('Expires: 0');
			$newheader = $phpgw_setup->generate_header();
			echo $newheader;
			break;
		case 'view':
			check_form_values();
			$header_template = new Template('../');
			$phpgw_setup->show_header('Generated header.inc.php', False, 'header');
			echo '<br>Save this text as contents of your header.inc.php<br><hr>';
			$newheader = $phpgw_setup->generate_header();
			echo '<pre>';
			echo htmlentities($newheader);
			echo '</pre><hr>';
			echo '<form action="index.php" method=post>';
			echo '<br> After retrieving the file put it into place as the header.inc.php, then click continue.<br>';
			echo '<input type=hidden name="FormLogout" value="header">';
			echo '<input type=submit name="junk" value="continue">';
			echo '</form>';
			echo '</body></html>';
			break;
		case 'write config':
			check_form_values();
			$header_template = new Template('../');
			if(is_writeable ('../header.inc.php')|| (!file_exists ('../header.inc.php') && is_writeable ('../')))
			{
				$newheader = $phpgw_setup->generate_header();
				$fsetup = fopen('../header.inc.php','wb');
				fwrite($fsetup,$newheader);
				fclose($fsetup);
				$phpgw_setup->show_header('Saved header.inc.php', False, 'header');
				echo '<form action="index.php" method=post>';
				echo '<br>Created header.inc.php! ';
				echo '<input type=hidden name="FormLogout" value="header">';
				echo '<input type=submit name="junk" value="continue">';
				echo '</form>';
				echo '</body></html>';
				break;
			}
			else
			{
				$phpgw_setup->show_header('Error generating header.inc.php', False, 'header');
				echo 'Could not open header.inc.php for writing!<br>' . "\n";
				echo 'Please check read/write permissions on directories or back up and use another option.<br>';
				echo '</td></tr></table></body></html>';
			}
			break;
		default:
			$phpgw_setup->show_header($phpgw_info['setup']['HeaderFormMSG'], False, 'header');
			echo $phpgw_info['setup']['PageMSG'];
/*
			echo '<table border="0" width="100%" cellspacing="0" cellpadding="2">';
			echo '  <tr><td align="center" WIDTH="20%" bgcolor="486591" colspan=2><font color="fefefe">Analysis</td></tr>';
			echo '</table>';
*/
			echo '<table border="0" width="100%" cellspacing="0" cellpadding="2">';
			echo '<tr bgcolor="486591"><td align="center" colspan=2><font color="fefefe"> Analysis </font></td></tr><tr><td colspan=2>';
			// Hardly try to find what DB-support is compiled in
			// this dont work with PHP 3.0.10 and lower !

			$supported_db = array();
			if (extension_loaded('mysql') || function_exists('mysql_connect'))
			{
				echo 'You appear to have MySQL support enabled<br>' . "\n";
				$supported_db[] = 'mysql';
			}
			else
			{
				echo 'No MySQL support found. Disabling<br>' . "\n";
			}
			if (extension_loaded('pgsql') || function_exists('pg_connect'))
			{
				echo 'You appear to have Postgres-DB support enabled<br>' . "\n";
				$supported_db[]  = 'pgsql';
			}
			else
			{
				echo 'No Postgres-DB support found. Disabling<br>' . "\n";
			}
			if (extension_loaded('mssql') || function_exists('mssql_connect'))
			{
				echo 'You appear to have Microsoft SQL Server support enabled<br>' . "\n";
				$supported_db[] = 'mssql';
			}
			else
			{
				echo 'No Microsoft SQL Server support found. Disabling<br>' . "\n";
			}
			if (extension_loaded('oci8'))
			{
				echo 'You appear to have Oracle V8 (OCI) support enabled<br>' . "\n";
				$supported_db[] = 'oracle';
			}
			else
			{
				if(extension_loaded('oracle'))
				{
					echo 'You appear to have Oracle support enabled<br>' . "\n";
					$supported_db[] = 'oracle';
				}
				else
				{
					echo 'No Oracle-DB support found. Disabling<br>' . "\n";
				}
			}
			if(!count($supported_db))
			{
				echo '<b><p align=center><font size=+2 color=red>did not found any valid DB support !<br>try to configure your php to support one of the above mentioned dbs or install phpgroupware by hand </font></p></b><td></tr></table></body></html>';
				exit;
			}
			$no_guess = false;
			if(file_exists('../header.inc.php') && is_file('../header.inc.php'))
			{
				echo 'Found existing configuration file. Loading settings from the file...<br>' . "\n";
				$phpgw_info['flags']['noapi'] = True;
				include('../header.inc.php');
				$no_guess = true;
					/* This code makes sure the newer multi-domain supporting header.inc.php is being used */
				if (!isset($phpgw_domain))
				{
					echo 'Your using an old configuration file format...<br>' . "\n";
					echo 'Importing old settings into the new format....<br>' . "\n";
				}
				else
				{
					if ($phpgw_info['server']['header_version'] != $phpgw_info['server']['current_header_version'])
					{
						echo 'Your using an old header.inc.php version...<br>' . "\n";
						echo 'Importing old settings into the new format....<br>' . "\n";
					}
					reset($phpgw_domain);
					$default_domain = each($phpgw_domain);
					$phpgw_info['server']['default_domain'] = $default_domain[0];
					unset ($default_domain); // we kill this for security reasons
					$phpgw_info['server']['db_host'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['db_host'];
					$phpgw_info['server']['db_name'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['db_name'];
					$phpgw_info['server']['db_user'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['db_user'];
					$phpgw_info['server']['db_pass'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['db_pass'];
					$phpgw_info['server']['db_type'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['db_type'];
					$phpgw_info['server']['config_passwd'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['config_passwd'];
				}
				if (defined('PHPGW_SERVER_ROOT'))
				{
					$phpgw_info['server']['server_root'] = PHPGW_SERVER_ROOT;
					$phpgw_info['server']['include_root'] = PHPGW_INCLUDE_ROOT; 
				}
				elseif (!isset($phpgw_info['server']['include_root']) && $phpgw_info['server']['header_version'] <= 1.6)
				{
					$phpgw_info['server']['include_root'] = $phpgw_info['server']['server_root'];
				}
				elseif (!isset($phpgw_info['server']['header_version']) && $phpgw_info['server']['header_version'] <= 1.6)
				{
					$phpgw_info['server']['include_root'] = $phpgw_info['server']['server_root'];
				}
			}
			else
			{
				echo 'sample configuration not found. using built in defaults<br>' . "\n";
				$phpgw_info['server']['server_root'] = '/path/to/phpgroupware';
				$phpgw_info['server']['include_root'] = '/path/to/phpgroupware';
					/* This is the basic include needed on each page for phpGroupWare application compliance */
				$phpgw_info['flags']['htmlcompliant'] = True;
	
					/* These are the settings for the database system */
				$phpgw_info['server']['db_host'] = 'localhost';
				$phpgw_info['server']['db_name'] = 'phpgroupware';
				$phpgw_info['server']['db_user'] = 'phpgroupware';
				$phpgw_info['server']['db_pass'] = 'your_password';
				$phpgw_info['server']['db_type'] = 'mysql'; //mysql, pgsql (for postgresql), or oracle

				/* These are a few of the advanced settings */
				$phpgw_info['server']['db_persistent'] = True;
				$phpgw_info['server']['config_passwd'] = 'changeme';
				$phpgw_info['server']['mcrypt_enabled'] = False;
				$phpgw_info['server']['mcrypt_version'] = '2.6.3';

				srand((double)microtime()*1000000);
				$random_char = array(
					'0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
					'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
					'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
					'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');

				for ($i=0; $i<30; $i++)
				{
					$phpgw_info['server']['mcrypt_iv'] .= $random_char[rand(1,count($random_char))];
				}
			}

			// now guessing better settings then the default ones 
			if(!$no_guess)
			{
				echo 'Now guessing better values for defaults <br>' . "\n";
				$this_dir = dirname($SCRIPT_FILENAME);
				$updir    = ereg_replace('/setup','',$this_dir);
				$phpgw_info['server']['server_root'] = $updir; 
				$phpgw_info['server']['include_root'] = $updir; 
			}
?>
      </td></tr>
      <tr bgcolor="486591"><th colspan="2"><font color="fefefe">Settings</font></th></tr>
      <form action="manageheader.php" method="post">
      <input type="hidden" name="setting[write_config]" value="true">
        <tr><td colspan="2"><b>Server Root</b><br><input type="text" name="setting[server_root]" size="80" value="<?php echo $phpgw_info['server']['server_root'] ?>"></td></tr>
        <tr><td colspan="2"><b>Include Root (this should be the same as Server Root unless you know what you are doing)</b><br><input type="text" name="setting[include_root]" size="80" value="<?php echo $phpgw_info['server']['include_root'] ?>"></td></tr>
        <tr><td colspan="2"><b>Admin password to header manager </b><br><input type="text" name="setting[HEADER_ADMIN_PASSWORD]" size="80" value="<?php echo $phpgw_info['server']['header_admin_password'] ?>"></td></tr>
        <br><br>
        <tr><td><b>DB Host</b><br><input type="text" name="setting[db_host]" value="<?php echo $phpgw_info['server']['db_host'] ?>"></td><td>Hostname/IP of Databaseserver</td></tr>
        <tr><td><b>DB Name</b><br><input type="text" name="setting[db_name]" value="<?php echo $phpgw_info['server']['db_name'] ?>"></td><td>Name of Database</td></tr>
        <tr><td><b>DB User</b><br><input type="text" name="setting[db_user]" value="<?php echo $phpgw_info['server']['db_user'] ?>"></td><td>Name of DB User as phpgroupware has to connect as</td></tr>
        <tr><td><b>DB Password</b><br><input type="text" name="setting[db_pass]" value="<?php echo $phpgw_info['server']['db_pass'] ?>"></td><td>Password of DB User</td></tr>
        <tr><td><b>DB Type</b><br><select name="setting[db_type]">
      <?php
		$selected = '';
		$found_dbtype = false;
		while(list($k,$v) = each($supported_db))
		{
			if($v == $phpgw_info['server']['db_type'])
			{
				$selected = ' selected ';
				$found_dbtype = true;
			}
			else
			{
				$selected = '';
			}
			print '<option ' . $selected . ' value="' . $v . '">' . $v . "\n";
		}
?>
        </select>
        </td><td>What Database do you want to use with PHPGroupWare?

        <tr><td><b>Configuration Password</b><br><input type=text name="setting[config_pass]" value="<?php echo $phpgw_info['server']['config_passwd'] ?>"></td><td>Password needed for configuration</td></tr>
        <tr><td><b>Persistent connection</b><br>
        <select type="checkbox" name="setting[db_persistent]">
        <option value="True"<?php echo ($phpgw_info['server']['db_persistent']?' selected':''); ?>>True</option>
        <option value="False"<?php echo (! $phpgw_info['server']['db_persistent']?' selected':''); ?>>False</option>
		  </select></td>
		  <td>Do you want persistent connections (higher performance, but eats memory)</td></tr>
        <tr><td colspan=2><b>Enable MCrypt</b><br>
        <select name="setting[enable_mcrypt]">
        <?php if($phpgw_info["server"]["mcrypt_enabled"] == True) { ?>
        <option value="True" selected>True
        <option value="False">False
        <?php } else { ?>
        <option value="True">True
        <option value="False" selected>False
        <?php } ?>
        </select>
        </td></tr>
        <tr><td><b>MCrypt version</b><br><input type="text" name="setting[mcrypt_version]" value="<?php echo $phpgw_info['server']['versions']['mcrypt'] ?>"></td><td>Set this to "old" for versions &lt; 2.4, otherwise the exact mcrypt version you use</td></tr>
        <tr><td><b>MCrypt initilazation vector</b><br><input type="text" name="setting[mcrypt_iv]" value="<?php echo $phpgw_info['server']['mcrypt_iv'] ?>" size="30"></td><td>It should be around 30 bytes in length.<br>Note: The default has been randomly generated.</td></tr>
        <tr><td><b>Domain select box on login</b><br>
         <select name="setting[domain_selectbox]">
          <option value="True"<?php echo ($phpgw_info['server']['domain_selectbox']?' selected':''); ?>>True</option>
          <option value="False"<?php echo (! $phpgw_info['server']['domain_selectbox']?' selected':''); ?>>False</option>
         </select></td><td>&nbsp;</td>
        </tr>
       </table>
      <?php
		if(!$found_dbtype)
		{
			echo '<br><font color=red>Warning!<br>The db_type in defaults ('.$phpgw_info['server']['db_type'].') is not supported on this server. using first supported type.</font>';
		}
		echo "<br>";

		if(is_writeable ('../header.inc.php')|| (!file_exists ('../header.inc.php') && is_writeable ('../')))
		{
			echo '<input type=submit name="action" value="write config">';
			echo ' or <input type=submit name="action" value="download"> or <input type=submit name="action" value="view"> the file.</form>';
		}
		else
		{
			echo 'Cannot create the header.inc.php due to file permission restrictions.<br> Instead you can ';
			echo '<input type=submit name="action" value="download">or <input type=submit name="action" value="view"> the file.</form>';
		}
		echo '<form action="index.php" method=post>';
		echo '<br> After retrieving the file put it into place as the header.inc.php, then click continue.<br>';
		echo '<input type=hidden name="FormLogout" value="header">';
		//echo '<input type=hidden name="FormLogout" value="config">';
		//echo '<input type=hidden name="ConfigLogin" value="Login">';
		//echo '<input type=hidden name="FormPW" value="'.$phpgw_domain[$phpgw_info["server"]["default_domain"]]["config_passwd"].'">';
		//echo '<input type=hidden name="FormDomain" value="'.$phpgw_info["server"]["default_domain"].'">';
		echo '<input type=submit name="junk" value="continue">';
		echo '</form>';
		echo '</body>';
		echo '</html>';

		break; // ending the switch default
	}
?>
