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

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("./inc/functions.inc.php");
  include("../header.inc.php");

  // Authorize the user to use setup app and load the database
  // Does not return unless user is authorized
  if (!$phpgw_setup->auth("Config")){
    Header("Location: index.php");
    exit;
  }
  $phpgw_setup->loaddb();

  /* Guessing default paths. */
  $current_config["files_dir"] = ereg_replace("/setup","/files",dirname($SCRIPT_FILENAME));
  if (is_dir("/tmp")){
    $current_config["temp_dir"] = "/tmp";
  }else{
    $current_config["temp_dir"] = "/path/to/temp/dir";
  }

  if ($submit) {
     @$phpgw_setup->db->query("delete from phpgw_config");
     // This is only temp.
     $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('useframes','never')");
     while ($newsetting = each($newsettings)) {
        if ($newsetting[0] == "nntp_server") {
 	      $phpgw_setup->db->query("select config_value FROM phpgw_config WHERE config_name='nntp_server'");
           if ($phpgw_setup->db->num_rows()) {
              $phpgw_setup->db->next_record();
              if ($phpgw_setup->db->f("config_value") <> $newsetting[1]) {
                 $phpgw_setup->db->query("DELETE FROM newsgroups");
//   	        $phpgw_setup->db->query("DELETE FROM users_newsgroups");
              }
           }
        }
        $phpgw_setup->db->query("insert into phpgw_config (config_name, config_value) values ('" . addslashes($newsetting[0])
                              . "','" . addslashes($newsetting[1]) . "')");
    }
    if ($newsettings["auth_type"] == "ldap") {
      Header('Location: '.$newsettings['webserver_url'].'/setup/ldap.php');
      exit;
    } else {
      //echo "<center>Your config has been updated<br><a href='".$newsettings["webserver_url"]."/login.php'>Click here to login</a>";
      Header("Location: '.$newsettings['webserver_url'].'/index.php');
      exit;
    }
  }

  if ($newsettings["auth_type"] != "ldap") {
    $phpgw_setup->show_header("Configuration");
  }

  @$phpgw_setup->db->query("select * from phpgw_config");
  while (@$phpgw_setup->db->next_record()) {
    $current_config[$phpgw_setup->db->f("config_name")] = $phpgw_setup->db->f("config_value");
  }

  if ($current_config["files_dir"] == "/path/to/dir/phpgroupware/files") {
     $current_config["files_dir"] = $phpgw_info["server"]["server_root"] . "/files";
  }

  
  if ($error == "badldapconnection") {
     // Please check the number and dial again :)
     echo "<br><center><b>Error:</b> There was a problem tring to connect to your LDAP server, please "
        . "check your config.</center>";
  }
  
?>  
 <form method="POST" action="config.php">
  <table border="0" align="center">

  <?php
    $phpgw_setup->execute_script("config",array("phpgwapi","admin", "preferences","email"));
  ?>

   <tr bgcolor="FFFFFF">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr>
    <td colspan="2" align="center"><input type="submit" name="submit" value="Submit"></td>
   </tr>
 </table>
</form>
</body></html>
