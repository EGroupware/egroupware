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

  $phpgw_info["flags"] = array("noheader"   => True,   "nonavbar" => True,
                               "currentapp" => "home", "noapi"    => True);
  include("../header.inc.php");
  include("./inc/functions.inc.php");

  // Authorize the user to use setup app and load the database
  if (!$phpgw_setup->auth("Config")){
    Header("Location: index.php");
    exit;
  }
  // Does not return unless user is authorized
  include( PHPGW_INCLUDE_ROOT . "/phpgwapi/inc/class.common.inc.php");

  $common = new common;
  $phpgw_setup->loaddb();

  $phpgw_info["server"]["auth_type"] = "ldap";
  $acct = CreateObject("phpgwapi.accounts");

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
        $account_info[$i]["account_lastname"]  = $info[$i]["givenname"][0];
        $account_info[$i]["account_firstname"] = $info[$i]["sn"][0];
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
		// This section is being modified to use the accounts and acl classes soon
		// Create a default group
		$defaultgroupid = mt_rand (100, 600000);
		$sql = "insert into phpgw_accounts";
		$sql .= "(account_id, account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
		$sql .= "values (".$defaultgroupid.", 'Default', 'g', '".md5($passwd)."', 'Default', 'Group', ".time().", 'A')";
		$phpgw_setup->db->query($sql);
		// Give the group access to apps
		while ($app = each($s_apps)) {
			$sql = "DELETE FROM phpgw_acl WHERE acl_appname='".$app[1]."' AND acl_location='run' AND acl_account="
				. $defaultgroupid;
			$phpgw_setup->db->query($sql ,__LINE__,__FILE__);

			$sql = "INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)"
				. " VALUES('".$app[1]."','run',".$defaultgroupid.",1)";
			$phpgw_setup->db->query($sql ,__LINE__,__FILE__);
		}

		while ($account = each($account_info)) {
			// do some checks before we try to import the data
			if (!empty($account[1]["account_id"]) && !empty($account[1]["account_lid"]))
/*				// This is where each new user was given app rights, instead of per the group
				@reset($s_apps);
				while ($app = each($s_apps)) {
					$sql = "DELETE FROM phpgw_acl WHERE acl_appname='".$app[1]."' AND acl_location='run' AND acl_account="
						. $account[1]["account_id"];
					$phpgw_setup->db->query($sql ,__LINE__,__FILE__);

					$sql = "INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)"
						. " VALUES('".$app[1]."','run',".$account[1]["account_id"].",1)";
					$phpgw_setup->db->query($sql ,__LINE__,__FILE__);
				} */

				// Cleanup admin permissions
				$sql = "DELETE FROM phpgw_acl WHERE acl_appname='admin' AND acl_location='run' AND acl_account="
					. $account[1]["account_id"];
				$phpgw_setup->db->query($sql ,__LINE__,__FILE__);

				// Give the user admin only if we asked for them to have it
				for ($a=0;$a<count($admins);$a++) {
					if ($admins[$a] == $account[1]["account_id"]) {
						$sql = "INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)"
							. " VALUES('admin','run',".$account[1]["account_id"].",1)";
						$phpgw_setup->db->query($sql ,__LINE__,__FILE__);
					}
				}

				// Actually create the account
				$phpgw_setup->db->query("SELECT account_id FROM phpgw_accounts WHERE account_id=" . $account[1]["account_id"]
					. " AND account_lid='" . $account[1]["account_lid"] . "'");
				if(!$phpgw_setup->db->num_rows() && $account[1]["account_lid"]) {
					$phpgw_setup->db->query("INSERT INTO phpgw_accounts (account_id,account_lid,account_pwd,account_type,"
						. "account_status,account_lastpwd_change) VALUES (" . $account[1]["account_id"] . ",'"
						. $account[1]["account_lid"] . "','x','u','A',".time().")",__LINE__,__FILE__);
				}

				// Now make them a member of the default group
				$sql = "INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)"
					. " VALUES('phpgw_group',".$defaultgroupid.",".$account[1]["account_id"].",'1')";
				$phpgw_setup->db->query($sql);
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
