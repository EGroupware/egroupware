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
  
  $current_version = "0.9.1";

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("../header.inc.php");

  $phpgw_info["server"]["api_dir"] = $phpgw_info["server"]["include_root"]."/phpgwapi";
  
  /* Database setup */
  switch($phpgw_info["server"]["db_type"]){
    case "postgresql":
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_pgsql.inc.php");
      break;
    case "oracle":
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_oracle.inc.php");
      break;      
    default:
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_mysql.inc.php");
  }

  $db	            = new db;
  $db->Host	    = $phpgw_info["server"]["db_host"];
  $db->Type	    = $phpgw_info["server"]["db_type"];
  $db->Database   = $phpgw_info["server"]["db_name"];
  $db->User	    = $phpgw_info["server"]["db_user"];
  $db->Password   = $phpgw_info["server"]["db_pass"];

  $db->query("select * from config");
  while ($db->next_record()) {
    $current_config[$db->f("config_name")] = $db->f("config_value");
  }
?>
  <title>phpGroupWare - setup</title>
  <center>phpGroupWare version <?php echo $current_version; ?> setup</center><p>
  
 <form method="POST" action="config.php">
  <table border="0">
   <tr>
    <td>Enter path for temporey files.</td>
    <td><input name="newsettings[temp_dir]" value="<?php echo $current_config["temp_dir"]; ?>"></td>
   </tr>

   <tr>
    <td>Enter path for users and group files.</td>
    <td><input name="newsettings[files_dir]" value="<?php echo $current_config["files_dir"]; ?>"></td>
   </tr>

   <tr>
    <td>Enter some random text for app_session <br>encryption (requires mcrypt)</td>
    <td><input name="newsettings[encryptkey]" value="<?php echo $current_config["encryptkey"]; ?>"></td>
   </tr>

   <tr>
    <td>Enter the title for your site.</td>
    <td><input name="newsettings[site_title]" value="<?php echo $current_config["site_title"]; ?>"></td>
   </tr>

   <tr>
    <td>Enter the hostname of the machine this server is running on.</td>
    <td><input name="newsettings[hostname]" value="<?php echo $SERVER_NAME; ?>"></td>
   </tr>

   <tr>
    <td>Enter the location of phpGroupWares URL.<br>Example: /phpGroupWare</td>
    <td><input name="newsettings[webserver_url]" value="<?php echo $current_config["webserver_url"]; ?>"></td>
   </tr>

   <tr>
    <td>Select which type of authentication you are using.<br>SQL is only support currently</td>
    <td>
     <select name="newsettings[auth_type]">
      <option value="sql">SQL</option>
      <option value="ldap">LDAP</option>
     </select>
    </td>
   </tr>

   <tr>
    <td>LDAP host:</td>
    <td><input name="newsettings[ldap_host]" value="<?php echo $current_config["ldap_host"]; ?>"></td>
   </tr>

   <tr>
    <td>LDAP context:</td>
    <td><input name="newsettings[ldap_context]" value="<?php echo $current_config["ldap_context"]; ?>"></td>
   </tr>

   <tr>
    <td>Use cookies to pass sessionid:</td>
    <td><input type="checkbox" name="newsettings[usecookies]" value="True"></td>
   </tr>

   <tr>
    <td>Enter the location of your mail server:</td>
    <td><input name="newsettings[mail_server]" value="<?php echo $current_config["mail_server"]; ?>"></td>
   </tr>

   <tr>
    <td>Select your mail server type:</td>
    <td>
     <select name="newsettings[mail_server_type]">
      <option value="imap">IMAP</option>
      <option value="pop3">POP-3</option>
     </select>
    </td>
   </tr>

   <tr>
    <td>IMAP server type:</td>
    <td>
     <select name="newsettings[imap_server_type]">
      <option value="Cyrus">Cyrus</option>
      <option value="UWash">UWash</option>
     </select>
    </td>
   </tr>

   <tr>
    <td>Enter your mail sufix:</td>
    <td><input name="newsettings[mail_suffix]" value="<?php echo $current_config["mail_suffix"]; ?>"></td>
   </tr>

  
   <tr>
    <td colspan="2" align="center"><input type="submit" name="submit" value="Submit"></td>
   </tr>
 </table>
</form>