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
    @$phpgw_setup->db->query("delete from config");
    while ($newsetting = each($newsettings)) {
   	  if ($newsetting[0] == "nntp_server") {
 	      $phpgw_setup->db->query("select config_value FROM config WHERE config_name='nntp_server'");
	      if ($phpgw_setup->db->num_rows()) {
	        $phpgw_setup->db->next_record();
  	      if ($phpgw_setup->db->f("config_value") <> $newsetting[1]) {
	          $phpgw_setup->db->query("DELETE FROM newsgroups");
//   	        $phpgw_setup->db->query("DELETE FROM users_newsgroups");
   	      }
	      }
   	  }
      $phpgw_setup->db->query("insert into config (config_name, config_value) values ('" . addslashes($newsetting[0])
        . "','" . addslashes($newsetting[1]) . "')");
    }
    if ($newsettings["auth_type"] == "ldap") {
      Header("Location: ldap.php");
      exit;
    } else {
      //echo "<center>Your config has been updated<br><a href='".$newsettings["webserver_url"]."/login.php'>Click here to login</a>";
      Header("Location: index.php");
      exit;
    }
  }

  if ($newsettings["auth_type"] != "ldap") {
    $phpgw_setup->show_header("Configuration");
  }

  @$phpgw_setup->db->query("select * from config");
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
   <tr bgcolor="486591">
    <td colspan="2"><font color="fefefe">&nbsp;<b>Directory information</b></font></td>
   </tr>
   
   </tr>
   <tr bgcolor="e6e6e6">
    <td>Enter path for temporary files.</td>
    <td><input name="newsettings[temp_dir]" value="<?php echo $current_config["temp_dir"]; ?>" size="40"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter path for users and group files.</td>
    <td><input name="newsettings[files_dir]" value="<?php echo $current_config["files_dir"]; ?>" size="40"></td>
   </tr>
   
   <tr bgcolor="e6e6e6">
    <td>Enter the location of phpGroupWare's URL.<br>Example: /phpGroupWare<br>(leave blank if at http://yourserver/)</td>
    <td><input name="newsettings[webserver_url]" value="<?php echo $current_config["webserver_url"]; ?>"></td>
   </tr>

   <tr bgcolor="FFFFFF">
    <td colspan="2">&nbsp;</td>
   </tr>

   <tr bgcolor="486591">
    <td colspan="2"><font color="fefefe">&nbsp;<b>Server information</b></font></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter your default FTP server.</td>
    <td><input name="newsettings[default_ftp_server]" value="<?php echo $current_config["default_ftp_server"]; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter your HTTP proxy server.</td>
    <td><input name="newsettings[httpproxy_server]" value="<?php echo $current_config["httpproxy_server"]; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter your HTTP proxy server port.</td>
    <td><input name="newsettings[httpproxy_port]" value="<?php echo $current_config["httpproxy_port"]; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter the title for your site.</td>
    <td><input name="newsettings[site_title]" value="<?php echo $current_config["site_title"]; ?>"></td>
   </tr>

   <tr bgcolor="e6e6e6">
    <td>Enter the hostname of the machine this server is running on.</td>
    <td><input name="newsettings[hostname]" value="<?php echo $SERVER_NAME; ?>"></td>
   </tr>

  <?php
    $phpgw_setup->execute_script("config");
    //$phpgw_setup->execute_script("config",array("accounts", "preferences","email"));
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
