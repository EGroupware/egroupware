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

  // Include to check user authorization against  the 
  // password in ../header.inc.php to protect all of the setup
  // pages from unauthorized use.
  
  function setup_header($title = "")
  {
    global $phpgw_info;

    echo '<title>phpGroupWare setup ' . $title . '</title><BODY BGCOLOR="FFFFFF" margintop="0" marginleft="0" '
      . 'marginright="0" marginbottom="0"><table border="0" width="100%"><tr>'
      . '<td align="left" bgcolor="486591">&nbsp;<font color="fefefe">phpGroupWare version '
      . $phpgw_info["server"]["version"] . ' setup</font></td></tr></table>';
  }

  function loginForm($err="")
  {
 	global $phpgw_info, $phpgw_domain, $SetupDomain, $SetupPasswd, $PHP_SELF;
 	
 	  setup_header("Please login");
    echo "<p><body bgcolor='#ffffff'>\n";
    echo "<table border=\"0\" align=\"center\">\n";
    echo "  <tr bgcolor=\"486591\">\n";
    echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Setup Login</b></font></td>\n";
    echo "  </tr>\n";
    if ($err != "") {
      echo "   <tr bgcolor='#e6e6e6'><td colspan='2'><font color='#ff0000'>".$err."</font></td></tr>\n";
    }
    echo "  <tr bgcolor=\"e6e6e6\">\n";
    echo "    <td><form action='".$PHP_SELF."' method='POST'>\n";
    if (count($phpgw_domain) > 1){
      echo "      <table><tr><td>Domain: </td><td><input type='text' name='FormDomain' value=''></td></tr>\n";
      echo "      <tr><td>Password: </td><td><input type='password' name='FormPW' value=''></td></tr></table>\n";
    }else{
      echo "      <input type='password' name='FormPW' value=''>\n";
      echo "      <input type='hidden' name='FormDomain' value='".$phpgw_info["server"]["default_domain"]."'>\n";
    }
    echo "      <input type='submit' name='Login' value='Login'>\n";
    echo "    </form></td>\n";
    echo "  </tr>\n";
    echo "</table>\n";
 	  echo "</body></html>\n";
  }

//if (count($phpgw_domain) > 1){
//  echo "count: ".count($phpgw_domain)."<br>\n";;
//}

  reset($phpgw_domain);
  $default_domain = each($phpgw_domain);
  $phpgw_info["server"]["default_domain"] = $default_domain[0];
  unset ($default_domain); // we kill this for security reasons

  if (isset($FormPW)) {
    if ($FormPW == $phpgw_domain[$FormDomain]["config_passwd"]) {
      setcookie("SetupPasswd","$FormPW");
      setcookie("SetupDomain","$FormDomain");
    }else{
      loginForm("Invalid password.");
      exit;
    }
  } elseif (isset($SetupPasswd)) {
    if ($SetupPasswd != $phpgw_domain[$SetupDomain]["config_passwd"]) {
      setcookie("SetupPasswd","");  // scrub the old one
      setcookie("SetupDomain","");  // scrub the old one
      loginForm("Invalid session cookie (cookies must be enabled)");
      exit;
    }
  } else {
    loginForm();
    exit;
  }
  /* Database setup */
  include($phpgw_info["server"]["api_dir"] . "/phpgw_db_".$phpgw_info["server"]["db_type"].".inc.php");
  $db	          = new db;
  if ($phpgw_info["multiable_domains"] != True){
    $db->Host       = $phpgw_info["server"]["db_host"];
    $db->Type       = $phpgw_info["server"]["db_type"];
    $db->Database   = $phpgw_info["server"]["db_name"];
    $db->User       = $phpgw_info["server"]["db_user"];
    $db->Password   = $phpgw_info["server"]["db_pass"];
  }else{
    $db->Host       = $phpgw_domain[$SetupDomain]["db_host"];
    $db->Type       = $phpgw_domain[$SetupDomain]["db_type"];
    $db->Database   = $phpgw_domain[$SetupDomain]["db_name"];
    $db->User       = $phpgw_domain[$SetupDomain]["db_user"];
    $db->Password   = $phpgw_domain[$SetupDomain]["db_pass"];
  }

?>
