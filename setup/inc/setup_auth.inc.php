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
 	global $phpgw_info, $domain, $PHP_SELF;
 	
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
    if ($phpgw_info["multiable_domains"] == True){
      echo "      <table><tr><td>Domain: </td><td><input type='text' name='domain' value=''></td></tr>\n";
      echo "      <tr><td>Password: </td><td><input type='password' name='FormPW' value=''></td></tr></table>\n";
    }else{
      echo "      <input type='password' name='FormPW' value=''>\n";
    }
    echo "      <input type='submit' name='Login' value='Login'>\n";
    echo "    </form></td>\n";
    echo "  </tr>\n";
    echo "</table>\n";
 	  echo "</body></html>\n";
  }

  if (isset($FormPW)) {
    if ($phpgw_info["multiable_domains"] == True){
      if ($FormPW != $domains[$domain]["config_passwd"]) {
        loginForm("Invalid password.");
        exit;
      }
    }else{
      if ($FormPW != $phpgw_info["server"]["config_passwd"]) {
        loginForm("Invalid password.");
        exit;
      } 
    }
    // Valid login, fall through and set the cookie 
    $SetupCookie = $FormPW;
  } else if (isset($SetupCookie)) {
    if ($phpgw_info["multiable_domains"] == True){
      if ($SetupCookie != $domains[$SetupDomain]["config_passwd"]) {
        setcookie("SetupCookie","");  // scrub the old one
        setcookie("SetupDomain","");  // scrub the old one
        loginForm("Invalid session cookie (cookies must be enabled)");
        exit;
      }
    }else{
      if ($SetupCookie != $phpgw_info["server"]["config_passwd"]) {
        setcookie("SetupCookie","");  // scrub the old one
        loginForm("Invalid session cookie (cookies must be enabled)");
        exit;
      }
    }
  } else {
    loginForm();
    exit;
  }

  // Auth ok.
  setcookie("SetupCookie","$SetupCookie");
  if ($phpgw_info["multiable_domains"] == True){
    setcookie("SetupDomain","$domain");
  }
?>