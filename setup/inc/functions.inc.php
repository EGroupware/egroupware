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
//  $phpgw_info["server"]["current_header_version"] = "1.4";

  function show_header($title = "",$nologoutbutton = False) 
  {
    global $phpgw_info, $PHP_SELF;
    echo '
      <html>
      <head>
        <title>phpGroupWare Setup';
        if ($title != ""){echo " - ".$title;}
        echo'</title>
        <style type="text/css"><!-- .link { color: #FFFFFF; } --></style>
      </head>
      <BODY BGCOLOR="FFFFFF" margintop="0" marginleft="0" marginright="0" marginbottom="0">
      <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td align="left" bgcolor="486591">&nbsp;<font color="fefefe">phpGroupWare version '.$phpgw_info["server"]["version"].' setup</font>
      </td>
      <td align="right" bgcolor="486591">';
      if ($nologoutbutton) {
        echo "&nbsp;";
      } else {
        echo '<a href="' . $PHP_SELF . '?FormLogout=True" class="link">Logout</a>&nbsp;';
      }
      echo "</td></tr></table>";
  }
  function loginForm($err="")
  {
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
      reset($phpgw_domain);
      $default_domain = each($phpgw_domain);
      echo "      <input type='password' name='FormPW' value=''>\n";
      echo "      <input type='hidden' name='FormDomain' value='".$default_domain[0]."'>\n";
    }
    echo "      <input type='submit' name='Login' value='Login'>\n";
    echo "    </form></td>\n";
    echo "  </tr>\n";
    echo "</table>\n";
 	  echo "</body></html>\n";
  }

  function check_header()
  {
    global $phpgw_domain, $phpgw_info, $stage, $header_msg;
    if(!file_exists("../header.inc.php") || !is_file("../header.inc.php")) {
      $stage = 1.1;
      $header_msg = "Stage One";
    }else{
      include("../header.inc.php");
      if (!isset($phpgw_domain) || $phpgw_info["server"]["header_version"] != $phpgw_info["server"]["current_header_version"]) {
        $stage = 1.2;
        $header_msg = "Stage One (Upgrade your header.inc.php)";
      }else{ /* header.inc.php part settled. Moving to authentication */
        $stage = 1.3;
        $header_msg = "Stage One (Completed)";
      }
    }
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

  function auth()
  {
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

  function loaddb()
  {
    global $phpgw_info, $phpgw_domain, $SetupDomain, $db;
    /* Database setup */
    if (!isset($phpgw_info["server"]["api_dir"])) {
      $phpgw_info["server"]["api_dir"] = $phpgw_info["server"]["server_root"]."/phpgwapi";
    }
    include($phpgw_info["server"]["api_dir"] . "/phpgw_db_".$phpgw_domain[$SetupDomain]["db_type"].".inc.php");
    $db	          = new db;
    $db->Host       = $phpgw_domain[$SetupDomain]["db_host"];
    $db->Type       = $phpgw_domain[$SetupDomain]["db_type"];
    $db->Database   = $phpgw_domain[$SetupDomain]["db_name"];
    $db->User       = $phpgw_domain[$SetupDomain]["db_user"];
    $db->Password   = $phpgw_domain[$SetupDomain]["db_pass"];
    
  }

  function check_db()
  {
    global $phpgw_info, $oldversion, $db, $stage, $header_msg;
    $db->Halt_On_Error = "no";
    $tables = $db->table_names();
    if (is_array($tables) && count($tables) > 0){
      /* tables exists. checking for post beta version */
      $db->query("select app_version from applications where app_name='admin'");
      $db->next_record();
      $oldversion = $db->f("app_version");
      if (isset($oldversion)){
        if ($oldversion == $phpgw_info["server"]["version"]){
          $db->query("select config_value from config where config_name='freshinstall'");
          $db->next_record();
          $configed = $db->f("config_value");
          if ($configed){
            $stage = 3.1;
            $header_msg = "Stage 3 (Needs Configuration)";
          }else{
            $stage = 3.2;
            $header_msg = "Stage 3 (Configuration OK)";
          }
        }else{
          $stage = 2.4;
          $header_msg = "Stage 2 (Tables need upgrading)";
        }
      }else{
        $stage = 2.2;
        $header_msg = "Stage 2 (Tables appear to be pre-beta)";
      }
    }else{
      /* no tables, so checking if we can create them */

      /* I cannot get either to work properly
      $isdb = $db->connect("kljkjh", "localhost", "phpgroupware", "phpgr0upwar3");
      */
      
      $db_rights = $db->query("CREATE TABLE phpgw_testrights ( testfield varchar(5) NOT NULL )");

      if (isset($db_rights)){
      //if (isset($isdb)){
        $stage = 2.3;
        $header_msg = "Stage 2 (Create tables)";
      }else{
        $stage = 2.1;
        $header_msg = "Stage 2 (Create Database)";
      }
      $db->query("DROP TABLE phpgw_testrights");
    }
  }

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


?>
