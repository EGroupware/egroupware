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

  if(file_exists("../version.inc.php") || is_file("../version.inc.php")) {
    include("../version.inc.php");  // To set the current core version
  }else{
    $phpgw_info["server"]["version"] = "Undetected";
  }
  $phpgw_info["server"]["current_header_version"] = "1.4";

  function show_header($title = "",$nologoutbutton = False) {
    global $phpgw_info, $PHP_SELF;
    ?>
    <head>
     <title>phpGroupWare Setup
     <?php
     if ($title != ""){echo " - ".$title;} ?>
     </title>
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
  function auth(){
    global $phpgw_domain, $FormLogout, $FormDomain, $FormPW, $SetupPW, $SetupDomain, $db, $HTTP_POST_VARS, $login_msg;
    if (isset($FormLogout)) {
      setcookie("SetupPW");  // scrub the old one
      setcookie("SetupDomain");  // scrub the old one
      $login_msg = "You have sucessfully logged out";
      return False;
    } elseif (isset($SetupPW)) {
      if ($SetupPW != $phpgw_domain[$SetupDomain]["config_passwd"]) {
        setcookie("SetupPW");  // scrub the old one
        setcookie("SetupDomain");  // scrub the old one
        $login_msg = "Invalid session cookie (cookies must be enabled)";
        return False;
      }else{
        return True;
      }
    } elseif (isset($FormPW)) {
      if ($FormPW == $phpgw_domain[$FormDomain]["config_passwd"]) {
        setcookie("SetupPW",$FormPW);
        setcookie("SetupDomain",$FormDomain);
        $SetupDomain = $FormDomain;
        return True;
      }else{
        $login_msg = "Invalid password";
        return False;
      }
    } else {
      return False;
    }
  }

  function loaddb(){
    global $phpgw_info, $phpgw_domain, $SetupDomain, $db;
    /* Database setup */
    $phpgw_info["server"]["api_dir"] = $phpgw_info["server"]["include_root"]."/phpgwapi";
    include($phpgw_info["server"]["api_dir"] . "/phpgw_db_".$phpgw_domain[$SetupDomain]["db_type"].".inc.php");
    $db	          = new db;
    $db->Host       = $phpgw_domain[$SetupDomain]["db_host"];
    $db->Type       = $phpgw_domain[$SetupDomain]["db_type"];
    $db->Database   = $phpgw_domain[$SetupDomain]["db_name"];
    $db->User       = $phpgw_domain[$SetupDomain]["db_user"];
    $db->Password   = $phpgw_domain[$SetupDomain]["db_pass"];
    
  }

  function show_steps($stage, $note = False) {
    global $phpgw_info, $PHP_SELF;

    /* The stages are as follows:
      Stage 1.1 = header does not exists yet
      Stage 1.2 = header exists, but is the wrong version
      Stage 1.3 = header exists and is current
      Stage 2.1 = database does not exists yet
      Stage 2.2 = database exists pre-beta tables
      Stage 2.3 = database exists but no tables
      Stage 2.4 = database and tables exists but need upgrading
      Stage 2.5 = database and tables exists and are current
      Stage 3 = 
      Stage 4 = 
      Stage 5 = 
    */

    echo '<table border="1" width="100%" cellspacing="0" cellpadding="2">';
    echo '  <tr><td align="left" WIDTH="20%" bgcolor="486591"><font color="fefefe">Step 1 - header.inc.php</td><td align="right" bgcolor="486591">&nbsp;</td></tr>';
    if ($stage == 1.1) {
      echo '<tr><td align="center">O</td><td><form action="./createheader.php" method=post>You have not created your header.inc.php yet.<br> <input type=submit value="Create one now"></form></td></tr>';
    }elseif ($stage == 1.2) {
      echo '<tr><td align="center">O</td><td><form action="./createheader.php" method=post>Your header.inc.php is out of date. Please upgrade it.<br> <input type=submit value="Upgrade now"></form></td></tr>';
    }elseif ($stage >= 1.3) {
      echo '<tr><td align="center">X</td><td><form action="./createheader.php" method=post>
      Your header.inc.php is in place and current.<br> <input type=submit value="Edit existing header.inc.php"></form></td></tr>';
    }
    echo '  <tr><td align="left" bgcolor="486591"><font color="fefefe">Step 2 - database management</td><td align="right" bgcolor="486591">&nbsp;</td></tr>';
    if ($stage < 2.1) {
      echo '<tr><td align="center">O</td><td>Not ready for this stage yet.</td></tr>';
    }elseif ($stage == 2.1) {
      echo '<tr><td align="center">O</td><td><form action="./tables.php" method=post>Your database does not exist.<br> <input type=submit value="Create one now"></form></td></tr>';
    }elseif ($stage == 2.2) {
      echo '<tr><td align="center">O</td><td><form action="./tables.php" method=post>Your database exist but your pre-beta tables need upgrading.<br> <input type=submit value="Create one now"></form></td></tr>';
    }elseif ($stage == 2.3) {
      echo '<tr><td align="center">O</td><td><form action="./tables.php" method=post>Your database exist, would you like to create your tables now?<br> <input type=submit value="Create tables"></form></td></tr>';
    }elseif ($stage == 2.4) {
      echo '<tr><td align="center">O</td><td><form action="./tables.php" method=post>Your database exist but your tables need upgrading.<br> <input type=submit value="upgrade now"></form></td></tr>';
    }elseif ($stage == 2.5) {
      echo '<tr><td align="center">X</td><td>Your tables are current.</td></tr>';
    }

    echo '  <tr><td align="left" bgcolor="486591"><font color="fefefe">Step 3 - language management</td><td align="right" bgcolor="486591">&nbsp;</td></tr>';
    if ($stage < 3.1) {
      echo '<tr><td align="center">O</td><td>Not ready for this stage yet.</td></tr>';
    }elseif ($stage == 3.1) {
      echo '<tr><td align="center">O</td><td>stage 3.1.<br></td></tr>';
    }elseif ($stage == 3.2) {
      echo '<tr><td align="center">O</td><td>stage 3.2.<br></td></tr>';
    }

    echo '</table>';
  }

  function generate_header(){
    Global $SCRIPT_FILENAME, $HTTP_POST_VARS, $k, $v;
    $ftemplate = fopen(dirname($SCRIPT_FILENAME)."/../header.inc.php.template","r");
    if($ftemplate){
      $ftemplate = fopen(dirname($SCRIPT_FILENAME)."/../header.inc.php.template","r");
      $template = fread($ftemplate,filesize(dirname($SCRIPT_FILENAME)."/../header.inc.php.template"));
      fclose($ftemplate);
      while(list($k,$v) = each($HTTP_POST_VARS)) {
        $template = ereg_replace("__".strtoupper($k)."__",$v,$template);
      }
      return $template;
    }else{
      echo "Could not open the header template for reading!<br>";
      exit;
    }
  }
?>
