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

  $phpgw_info["server"]["api_dir"] = $phpgw_info["server"]["include_root"]."/phpgwapi";

  function setup_header($title = "",$nologoutbutton = False) {
    global $phpgw_info, $PHP_SELF, $dontshowtheheaderagain;

    // Ok, so it isn't the greatest idea, but it works for now.  Setup needs to be rewritten.
    if ($dontshowtheheaderagain) {
       return False;
    }

    $dontshowtheheaderagain = True;
    ?>
    
    <head>
     <title>phpGroupWare setup <?php echo $title; ?></title>
      <style type="text/css">
       <!--
         .link
         { 
            color: #FFFFFF;
         }
       -->
      </style>
    </head>
    <BODY BGCOLOR="FFFFFF" margintop="0" marginleft="0" marginright="0" marginbottom="0">
    <table border="0" width="100%" cellspacing="0" cellpadding="2">
     <tr>
      <td align="left" bgcolor="486591">&nbsp;<font color="fefefe">phpGroupWare version <?php 
       echo $phpgw_info["server"]["version"]; ?> setup</font>
      </td>
      <td align="right" bgcolor="486591">
       <?php
         if ($nologoutbutton) {
            echo "&nbsp;";
         } else {
            echo '<a href="' . $PHP_SELF . '?FormLogout=True" class="link">Logout</a>&nbsp;';
         }
       
         echo "</td></tr></table>";
  }

  function loginForm($err=""){
   	global $phpgw_info, $phpgw_domain, $SetupDomain, $SetupPW, $PHP_SELF;
 	
 	  setup_header("Please login",True);
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

  function loaddb(){
    global $phpgw_domain, $phpgw_info, $FormLogout, $FormDomain, $SetupPW, $SetupDomain, $db, $PHP_SELF, $HTTP_POST_VARS;

    /* This code makes sure the newer multi-domain supporting header.inc.php is being used */
    if (!isset($phpgw_domain)) {
      setup_header("Upgrade your header.inc.php");
      echo "<br><b>You will need to upgrade your header.inc.php before you can continue with this setup</b>";
      exit;
    }
  
    /* Based on authentication, the database will be loaded */
    reset($phpgw_domain);
    $default_domain = each($phpgw_domain);
    $phpgw_info["server"]["default_domain"] = $default_domain[0];
    unset ($default_domain); // we kill this for security reasons
  
    if (isset($FormLogout)) {
      setcookie("SetupPW");  // scrub the old one
      setcookie("SetupDomain");  // scrub the old one
      loginForm("You have sucessfully logged out");
      exit;
    } elseif (isset($SetupPW)) {
      if ($SetupPW != $phpgw_domain[$SetupDomain]["config_passwd"]) {
        setcookie("SetupPW");  // scrub the old one
        setcookie("SetupDomain");  // scrub the old one
        loginForm("Invalid session cookie (cookies must be enabled)");
        exit;
      }
    } elseif (isset($FormPW)) {
      if ($FormPW == $phpgw_domain[$FormDomain]["config_passwd"]) {
        setcookie("SetupPW",$FormPW);
        setcookie("SetupDomain",$FormDomain);
        $SetupDomain = $FormDomain;
      }else{
        loginForm("Invalid password.");
        exit;
      }
    } else {
      loginForm();
      exit;
    }


    /* Database setup */
    include($phpgw_info["server"]["api_dir"] . "/phpgw_db_".$phpgw_domain[$SetupDomain]["db_type"].".inc.php");
    $db	          = new db;
    $db->Host       = $phpgw_domain[$SetupDomain]["db_host"];
    $db->Type       = $phpgw_domain[$SetupDomain]["db_type"];
    $db->Database   = $phpgw_domain[$SetupDomain]["db_name"];
    $db->User       = $phpgw_domain[$SetupDomain]["db_user"];
    $db->Password   = $phpgw_domain[$SetupDomain]["db_pass"];
  }
  loaddb();
?>
