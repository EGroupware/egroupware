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

	$phpgw_info["flags"] = array(
		"noheader"	=> True,
		"nonavbar"	=> True,
		"currentapp"=> "home",
		"noapi"		=> True
	);

	include("../header.inc.php");
	include("./inc/functions.inc.php");

	// Authorize the user to use setup app and load the database
	if (!$phpgw_setup->auth("Config")){
		Header("Location: index.php");
		exit;
	}
	// Does not return unless user is authorized

	$common       = CreateObject("phpgwapi.common");
	$phpgw_setup->loaddb();

	$phpgw_info["server"]["auth_type"] = "ldap";
	$acct         = CreateObject("phpgwapi.accounts");
	$applications = CreateObject("phpgwapi.applications");

	$phpgw_setup->db->query("select config_name,config_value from phpgw_config where config_name like 'ldap%'",__LINE__,__FILE__);
	while ($phpgw_setup->db->next_record()) {
		$config[$phpgw_setup->db->f("config_name")] = $phpgw_setup->db->f("config_value");
	}

	// First, see if we can connect to the LDAP server, if not send `em back to config.php with an
	// error message.

	// connect to ldap server
	if (! $ldap = @ldap_connect($config["ldap_host"])) {
		$noldapconnection = True;
	}

	// bind as admin, we not to able to do everything
	if (! @ldap_bind($ldap,$config["ldap_root_dn"],$config["ldap_root_pw"])) {
		$noldapconnection = True;
	}
  
	if ($noldapconnection) {
		Header("Location: config.php?error=badldapconnection");
		exit;
	}

	$sr = ldap_search($ldap,$config["ldap_context"],"(|(uid=*))",array("sn","givenname","uid","uidnumber"));
	$info = ldap_get_entries($ldap, $sr);
  
	for ($i=0; $i<$info["count"]; $i++) {
		if (! $phpgw_info["server"]["global_denied_users"][$info[$i]["uid"][0]]) {
			$account_info[$i]["account_id"]        = $info[$i]["uidnumber"][0];
			$account_info[$i]["account_lid"]       = $info[$i]["uid"][0];
			$account_info[$i]["account_firstname"]  = $info[$i]["givenname"][0];
			$account_info[$i]["account_lastname"] = $info[$i]["sn"][0];
		}
	}

	$phpgw_setup->db->query("select app_name,app_title from phpgw_applications where app_enabled != '0' and "
		. "app_name != 'administration'",__LINE__,__FILE__);
	while ($phpgw_setup->db->next_record()) {
		$apps[$phpgw_setup->db->f("app_name")] = $phpgw_setup->db->f("app_title");
	}

	if ($submit) {
		if (! count($admins)) {
			$error = "<br>You must select at least 1 admin";
		}

		if (! count($s_apps)) {
			$error .= "<br>You must select at least 1 application";
		}

		if (! $error) {
			// Create the 'Default' group
 			$defaultgroupid = mt_rand (100, 600000);

			$acct = CreateObject('phpgwapi.accounts',$defaultgroupid);
			$acct->db = $phpgw_setup->db;

			// Check if the group account is already there
			// Since the id above is random, this will fail on a reload
			$acct_exist = $acct->exists($defaultgroupid);
			if(!$acct_exist && $defaultgroupid) {
				$acct->create('g','Default',$passwd,'Default','Group','A',$defaultgroupid);
			}

			$acl = CreateObject('phpgwapi.acl',$defaultgroupid);
			$acl->db = $phpgw_setup->db;
			$acl->read_repository();
			while ($app = each($s_apps)) {
				$acl->add($app[1],'run',1);
			}
			$acl->save_repository();

			while ($account = each($account_info)) {
				// do some checks before we try to import the data
				if (!empty($account[1]["account_id"]) && !empty($account[1]["account_lid"]))
					$accounts = CreateObject("phpgwapi.accounts",$account[1]["account_id"]);
					$accounts->db = $phpgw_setup->db;

					$acl = CreateObject("phpgwapi.acl",intval($account[1]["account_id"]));
					$acl->db = $phpgw_setup->db;
					$acl->read_repository();

					// Only give them admin if we asked for them to have it
					for ($a=0;$a<count($admins);$a++) {
						if ($admins[$a] == $account[1]["account_id"]) {
							$acl->add('admin','run',1);
						}
					}
					
					// Check if the account is already there
					$acct_exist = $accounts->exists($account[1]["account_id"]);

					if(!$acct_exist && $account[1]["account_id"]) {
						$accounts->create('u', $account[1]["account_lid"], 'x',
							$account[1]["account_firstname"], $account[1]["account_lastname"],
							'A',$account[1]["account_id"]
						);
					}

					// Now make them a member of the 'Default' group
					$acl->add("phpgw_group",$defaultgroupid,1);
					$acl->save_repository();
				}
				$setup_complete = True;
			}
		}

	// Add a check to see if there is no users in LDAP, if not create a default user.

	$phpgw_setup->show_header();
  
	if ($error) {
		echo "<br><center><b>Error:</b> $error</center>";
	}

	if ($setup_complete) {
		$phpgw_setup->db->query("select config_value from phpgw_config where config_name='webserver_url'",__LINE__,__FILE__);
		$phpgw_setup->db->next_record();
		echo '<br><center>Setup has been completed!  Click <a href="' . $phpgw_setup->db->f("config_value")
			. '/login.php">here</a> to login</center>';
		exit;
	}
?>

 <form action="ldap.php" method="POST">
  <table border="0" align="center" width="70%">
   <tr bgcolor="486591">
    <td colspan="2">&nbsp;<font color="fefefe">LDAP import users</font></td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td colspan="2">&nbsp;This section will help you import users from your LDAP tree into phpGroupWare's account tables.<br>&nbsp;</td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td align="left" valign="top">
     &nbsp;Select which user(s) will have the admin privileges
    </td>
    <td align="center">
     <select name="admins[]" multiple size="5">
      <?php
        while ($account = each($account_info)) {
          echo '<option value="' . $account[1]["account_id"] . '">'
             . $common->display_fullname($account[1]["account_lid"],$account[1]["account_firstname"],$account[1]["account_lastname"])
             . '</option>';
          echo "\n";
        }
      ?>
     </select>
    </td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td align="left" valign="top">
     &nbsp;Select the default applications your users will have access to.
     <br>&nbsp;Note: You will be able to customize this later.
    </td>
    <td>
     <select name="s_apps[]" multiple size="5">
      <?php
        while ($app = each($apps)) {
          echo '<option value="' . $app[0] . '" selected>' . $app[1] . '</option>';
          echo "\n";
        }
      ?>
     </select>
    </td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td colspan="2" align="center">
     <input type="submit" name="submit" value="import">
    </td>
   </tr> 
    
  </table>
 </form>
