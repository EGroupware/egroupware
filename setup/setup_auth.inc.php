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

  // Include to check user authorization against  the 
  // password in ../header.inc.php to protect all of the setup
  // pages from unauthorized use.

function loginForm($err="") {
	global $PHP_SELF;
        echo "<html><head><title>phpGroupWare Setup - please Login</title></head>\n";
        echo "<body bgcolor='#ffffff'>\n";
        echo "<table border=\"0\" align=\"center\">\n";
        echo "  <tr bgcolor=\"486591\">\n";
        echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Setup Login</b></font></td>\n";
        echo "  </tr>\n";
        if ($err != "") {
          echo "   <tr bgcolor='#e6e6e6'><td colspan='2'><font color='#ff0000'>".$err."</font></td></tr>\n";
        }
        echo "  <tr bgcolor=\"e6e6e6\">\n";
        echo "    <td><form action='".$PHP_SELF."' method='POST'>\n";
        echo "      <input type='password' name='FormPW' value=''>\n";
        echo "      <input type='submit' name='Login' value='Login'>\n";
        echo "    </form></td>\n";
        echo "  </tr>\n";
        echo "</table>\n";
        echo "<!-- cookipw = ".$SetupCookie." should be ".$phpgw_info["server"]["config_passwd"]." -->\n";
	echo "</body></html>\n";
}

if (isset($FormPW) ) {
  if ($FormPW != $phpgw_info["server"]["config_passwd"]) {
    loginForm("Invalid password.");
    exit;
  } 
  // Valid login, fall through and set the cookie 
  $SetupCookie = $FormPW;
} else if (isset($SetupCookie)) {
  if ($SetupCookie != $phpgw_info["server"]["config_passwd"]) {
    setcookie("SetupCookie","");  // scrub the old one
    loginForm("Invalid session cookie (cookies must be enabled)");
    exit;
  }
} else {
  loginForm();
  exit;
}
// Auth ok.
setcookie("SetupCookie","$SetupCookie");
?>
