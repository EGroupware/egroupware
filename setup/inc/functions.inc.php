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
    <html>
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
    global $phpgw_info, $phpgw_domain, $SetupDomain, $oldversion, $currentver, $db, $subtitle, $submsg, $subaction;
    /* The stages are as follows:
      Stage 1.1 = header does not exists yet
      Stage 1.2 = header exists, but is the wrong version
      Stage 1.3 = header exists and is current
      Stage 2.1 = database does not exists yet
      Stage 2.2 = database exists pre-beta tables
      Stage 2.3 = database exists but no tables
      Stage 2.4 = database and tables exists but need upgrading
      Stage 2.5 = tables being modified in some way
      Stage 2.6 = database and tables exists and are current
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
      echo '<tr><td align="center">O</td><td>';
      echo '
        You appear to be running a pre-beta version of phpGroupWare<br>
        We are providing an automated upgrade system, but we highly recommend backing up your tables incase the script causes damage to your data.<br>
        These automated scripts can easily destroy your data. Please backup before going any further!<br>
        <form method="post" action="tables.php">
        Select your old version: 
        <select name="oldversion">
           <option value="7122000">7122000</option>
           <option value="8032000">8032000</option>
           <option value="8072000">8072000</option>
           <option value="8212000">8212000</option>
           <option value="9052000">9052000</option>
           <option value="9072000">9072000</option>
           <option value="9262000">9262000</option>
           <option value="0_9_1">0.9.1</option>
           <option value="0_9_2">0.9.2</option>
         </select>
         <input type="submit" name="action" value="Upgrade">
         <input type="submit" name="action" value="Delete my old tables">
        </form>';
      echo '</td></tr>';
    }elseif ($stage == 2.3) {
      /* commented out because I cannot accuratly figure out if the DB exists */
      //echo '<tr><td align="center">O</td><td><form action="./tables.php" method=post>Your database exist, would you like to create your tables now?<br> <input type=submit value="Create tables"></form></td></tr>';
      echo '<tr><td align="center">O</td><td>Make sure that your database is created and the account permissions are set.<br>';
      if ($phpgw_domain[$SetupDomain]["db_type"] == "mysql"){
        echo "
        <br>Instructions for creating the database in MySQL:<br>
        Login to mysql -<br>
        <i>[user@server user]# mysql -u root -p</i><br>
        Create the empty database and grant user permissions -<br>
        <i>mysql> create database phpgroupware;</i><br>
        <i>mysql> grant all on phpgroupware.* to phpgroupware@localhost identified by 'password';</i><br>
        ";
      }elseif ($phpgw_domain[$SetupDomain]["db_type"] == "pgsql"){
        echo "
        <br>Instructions for creating the database in PostgreSQL:<br>
        Start the postmaster<br>
        <i>[user@server user]# postmaster -i -D /home/[username]/[dataDir]</i><br>
        Create the empty database -<br>
        <i>[user@server user]# createdb phpgroupware</i><br>
        ";
      }
      echo '<form action="./tables.php" method=post>';
      echo "<input type=\"hidden\" name=\"oldversion\" value=\"new\">\n";
      echo 'Once the database is setup correctly <br><input type=submit name="action" value="Create"> the tables</form></td></tr>';
    }elseif ($stage == 2.4) {
      echo '<tr><td align="center">O</td><td>';
      echo "You appear to be running version $oldversion of phpGroupWare.<br>\n";
      echo "We will automaticly update your tables/records to ".$phpgw_info["server"]["version"].", but we highly recommend backing up your tables in case the script causes damage to your data.\n";
      echo "These automated scripts can easily destroy your data. Please backup before going any further!\n";
      echo "<form method=\"POST\" action=\"$PHP_SELF\">\n";
      echo "<input type=\"hidden\" name=\"oldversion\" value=\"".$oldversion."\">\n";
      echo "<input type=\"hidden\" name=\"useglobalconfigsettings\">\n";
      echo "<input type=\"submit\" name=\"action\" value=\"Upgrade\">\n";
      echo "<input type=\"submit\" name=\"action\" value=\"Delete my old tables\">\n";
      echo "</form>\n";
      echo "<form method=\"POST\" action=\"config.php\">\n";
      echo "<input type=\"submit\" name=\"action\" value=\"Dont touch my data\">\n";
      echo "</form>\n";
      echo '</td></tr>';
    }elseif ($stage == 2.5) {
      echo '<tr><td align="center">O</td><td>';
      echo "<table width=\"100%\">\n";
      echo "  <tr bgcolor=\"486591\"><td><font color=\"fefefe\">&nbsp;<b>$subtitle</b></font></td></tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\"><td>$submsg</td></tr>\n";
      echo "  <tr bgcolor=\"486591\"><td><font color=\"fefefe\">&nbsp;<b>Table Change Messages</b></font></td></tr>\n";
      $db->Halt_On_Error = "report";
      include ("./sql/common_main.inc.php");
      $db->Halt_On_Error = "no";
      echo "  <tr bgcolor=\"486591\"><td><font color=\"fefefe\">&nbsp;<b>Status</b></font></td></tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\"><td>If you did not recieve any errors, your tables have been $subaction.<br></tr>\n";
      echo "</table>\n";
      echo "<form method=\"POST\" action=\"tables.php\">\n";
      echo "<br><input type=\"submit\" value=\"Re-Check My Installation\">\n";
      echo '</form>';
      echo '</td></tr>';
    }elseif ($stage >= 2.6) {
      echo '<tr><td align="center">X</td><td>Your tables are current.';
      echo "<form method=\"POST\" action=\"tables.php\">\n";
      echo "<input type=\"hidden\" name=\"oldversion\" value=\"new\">\n";
      echo "<br>Insanity: <input type=\"submit\" name=\"action\" value=\"Delete all my tables and data\">\n";
      echo '</form>';
      echo '</td></tr>';
    }
    echo '  <tr><td align="left" bgcolor="486591"><font color="fefefe">Step 3 - Configuration</td><td align="right" bgcolor="486591">&nbsp;</td></tr>';
    if ($stage < 3.1) {
      echo '<tr><td align="center">O</td><td>Not ready for this stage yet.</td></tr>';
    }elseif ($stage == 3.1) {
      echo '<tr><td align="center">O</td><td>Please phpGroupWare for your environment.';
      echo "<form method=\"POST\" action=\"config.php\"><input type=\"submit\" value=\"Configure Now\"></form>";
      echo '</td></tr>';
    }elseif ($stage == 3.2) {
      echo '<tr><td align="center">X</td><td>Configuration completed.';
      echo "<form method=\"POST\" action=\"config.php\"><input type=\"submit\" value=\"Edit Current Configuration\"></form>";
      echo '</td></tr>';
    }
    echo '  <tr><td align="left" bgcolor="486591"><font color="fefefe">Step 4 - language management</td><td align="right" bgcolor="486591">&nbsp;</td></tr>';
    if ($stage < 4.1) {
      echo '<tr><td align="center">O</td><td>Not ready for this stage yet.</td></tr>';
    }elseif ($stage == 4.1) {
      echo '<tr><td align="center">O</td><td>stage 3.1.<br></td></tr>';
    }elseif ($stage == 4.2) {
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
