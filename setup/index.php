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
  
  // Idea:  This is so I don't forget.  When they are preforming a new install, after config,
  //        forward them right to index.php.  Create a session for them and have a nice little intro
  //        page explaining what to do from there (ie, create there own account)

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("./inc/functions.inc.php");
  /* processing and discovery phase */
  $phpgw_info["setup"]["stage"]["header"] = $phpgw_setup->check_header();
  if ( $phpgw_info["setup"]["stage"]["header"] == 10){
    if (!$phpgw_setup->auth("Config")){
      $phpgw_setup->show_header("Please login",True);
      $phpgw_setup->login_form();
      exit;
    }else{ /* authentication settled. Moving to the database portion. */
      $phpgw_setup->loaddb();
      $phpgw_info["setup"]["stage"]["db"] = $phpgw_setup->check_db();
      $phpgw_info["setup"]["stage"]["config"] = $phpgw_setup->check_config();
    }
  }else{
    Header("Location: manageheader.php");
    exit;
  }

  switch($action){
    case "Delete all my tables and data":
      $subtitle = "Deleting Tables";
      $submsg = "At your request, this script is going to take the evil action of deleting your existing tables and re-creating them in the new format.";
      $subaction = "deleted";
      $phpgw_info["setup"]["currentver"]["phpgwapi"] = "drop";
      $phpgw_info["setup"]["stage"]["db"] = 5;
      break;
    case "Upgrade":
      $subtitle = "Upgrading Tables";
      $submsg = "At your request, this script is going to attempt to upgrade your old tables to the new format.";
      $subaction = "upgraded";
      $phpgw_info["setup"]["currentver"]["phpgwapi"] = "oldversion";
      $phpgw_info["setup"]["stage"]["db"] = 5;
      break;      
    case "Create":
      $subtitle = "Creating Tables";
      $submsg = "At your request, this script is going to attempt to the tables for you.";
      $subaction = "created";
      $phpgw_info["setup"]["currentver"]["phpgwapi"] = "new";
      $phpgw_info["setup"]["stage"]["db"] = 5;
      break;      
  }

  /* Display code */

  $phpgw_setup->show_header($phpgw_info["setup"]["header_msg"]);
  if (PHP_VERSION < "3.0.16") {
    echo "You appear to be running an old version of PHP.  It its recommend that you upgrade "
      . "to a new version.  Older version of PHP might not run phpGroupWare correctly, if at all.";
    exit;
  }

  $phpgw_info["server"]["app_images"] = "templates/default/images";

  echo '<table border="1" width="100%" cellspacing="0" cellpadding="2">';
  
/*
  echo '  <tr><td align="left" WIDTH="20%" bgcolor="486591"><font color="fefefe">Step 0 - header.inc.php</td><td align="right" bgcolor="486591">&nbsp;</td></tr>';
  if ($phpgw_info["setup"]["stage"]["header"] <= 1) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td><form action="./manageheader.php" method=post>You have not created your header.inc.php yet.<br> <input type=submit value="Create one now"></form></td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["header"] == 2 || $phpgw_info["setup"]["stage"] == 3) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td><form action="./manageheader.php" method=post>Your header.inc.php is out of date. Please upgrade it.<br> <input type=submit value="Upgrade now"></form></td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["header"] == 10) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/completed.gif" alt="X" border="0"></td><td><form action="./manageheader.php" method=post>
    Your header.inc.php is in place and current.<br> <input type=submit value="Edit existing header.inc.php"></form></td></tr>';
  }
*/
  echo '  <tr><td align="left" bgcolor="486591"><font color="fefefe">Step 1 - database management</td><td align="right" bgcolor="486591">&nbsp;</td></tr>';
  if ($phpgw_info["setup"]["stage"]["header"] < 10) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>Not ready for this stage yet.</td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["db"] <= 1) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td><form action="index.php" method=post>Your database does not exist.<br> <input type=submit value="Create one now"></form></td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["db"] == 2) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>';
    echo '
      You appear to be running a pre-beta version of phpGroupWare<br>
      We are providing an automated upgrade system, but we highly recommend backing up your tables incase the script causes damage to your data.<br>
      These automated scripts can easily destroy your data. Please backup before going any further!<br>
      <form method="post" action="index.php">
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
       <input type="submit" name="action" value="Delete all my tables and data">
      </form>';
    echo '</td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["db"] == 3) {
    /* commented out because I cannot accuratly figure out if the DB exists */
    //echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td><form action="index.php" method=post>Your database exist, would you like to create your tables now?<br> <input type=submit value="Create tables"></form></td></tr>';
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>Make sure that your database is created and the account permissions are set.<br>';
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
    echo '<form action="index.php" method=post>';
    echo "<input type=\"hidden\" name=\"oldversion\" value=\"new\">\n";
    echo 'Once the database is setup correctly <br><input type=submit name="action" value="Create"> the tables</form></td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["db"] == 4) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>';
    echo "You appear to be running version ".$phpgw_info["setup"]["oldver"]["phpgwapi"]." of phpGroupWare.<br>\n";
    echo "We will automaticly update your tables/records to ".$phpgw_info["server"]["versions"]["phpgwapi"].", but we highly recommend backing up your tables in case the script causes damage to your data.\n";
    echo "These automated scripts can easily destroy your data. Please backup before going any further!\n";
    echo "<form method=\"POST\" action=\"index.php\">\n";
    echo "<input type=\"hidden\" name=\"oldversion\" value=\"".$phpgw_info["setup"]["oldver"]["phpgwapi"]."\">\n";
    echo "<input type=\"hidden\" name=\"useglobalconfigsettings\">\n";
    echo "<input type=\"submit\" name=\"action\" value=\"Upgrade\">\n";
    echo "<input type=\"submit\" name=\"action\" value=\"Delete all my tables and data\">\n";
    echo "</form>\n";
    echo "<form method=\"POST\" action=\"config.php\">\n";
    echo "<input type=\"submit\" name=\"action\" value=\"Dont touch my data\">\n";
    echo "</form>\n";
    echo '</td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["db"] == 5) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>';
    echo "<table width=\"100%\">\n";
    echo "  <tr bgcolor=\"486591\"><td><font color=\"fefefe\">&nbsp;<b>$subtitle</b></font></td></tr>\n";
    echo "  <tr bgcolor=\"e6e6e6\"><td>$submsg</td></tr>\n";
    echo "  <tr bgcolor=\"486591\"><td><font color=\"fefefe\">&nbsp;<b>Table Change Messages</b></font></td></tr>\n";
    $phpgw_setup->db->Halt_On_Error = "report";
    include ("./sql/common_main.inc.php");
    $phpgw_setup->db->Halt_On_Error = "no";
    echo "  <tr bgcolor=\"486591\"><td><font color=\"fefefe\">&nbsp;<b>Status</b></font></td></tr>\n";
    echo "  <tr bgcolor=\"e6e6e6\"><td>If you did not recieve any errors, your tables have been $subaction.<br></tr>\n";
    echo "</table>\n";
    echo "<form method=\"POST\" action=\"index.php\">\n";
    echo "<br><input type=\"submit\" value=\"Re-Check My Installation\">\n";
    echo '</form>';
    echo '</td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["db"] == 10) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/completed.gif" alt="X" border="0"></td><td>Your tables are current.';
    echo "<form method=\"POST\" action=\"index.php\">\n";
    echo "<input type=\"hidden\" name=\"oldversion\" value=\"new\">\n";
    echo "<br>Insanity: <input type=\"submit\" name=\"action\" value=\"Delete all my tables and data\">\n";
    echo '</form>';
    echo '</td></tr>';
  }
  echo '  <tr><td align="left" bgcolor="486591"><font color="fefefe">Step 2 - Configuration</td><td align="right" bgcolor="486591">&nbsp;</td></tr>';
  if ($phpgw_info["setup"]["stage"]["db"] < 10) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>Not ready for this stage yet.</td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["config"] <= 1) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>Please configure phpGroupWare for your environment.';
    echo "<form method=\"POST\" action=\"config.php\"><input type=\"submit\" value=\"Configure Now\"></form>";
    echo '</td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["config"] == 10) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/completed.gif" alt="X" border="0"></td><td>Configuration completed.';
    echo "<form method=\"POST\" action=\"config.php\"><input type=\"submit\" value=\"Edit Current Configuration\"></form>";
    echo '<br><a href="setup_demo.php">Click Here</a> to setup 1 admin account and 3 demo accounts. <br><b>This will delete all existing accounts</b>';
    echo '</td></tr>';
  }
  echo '  <tr><td align="left" bgcolor="486591"><font color="fefefe">Step 3 - language management</td><td align="right" bgcolor="486591">&nbsp;</td></tr>';
  if ($phpgw_info["setup"]["stage"]["config"] < 10) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>Not ready for this stage yet.</td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["lang"] <= 1) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>You are ready for this stage, but this stage is not yet written.<br></td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["lang"] == 10) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/completed.gif" alt="O" border="0"></td><td>This stage is completed<br></td></tr>';
  }
  echo '  <tr><td align="left" bgcolor="486591"><font color="fefefe">Step 4 - Add-on Application Installation</td><td align="right" bgcolor="486591">&nbsp;</td></tr>';
  if ($phpgw_info["setup"]["stage"]["lang"] < 10) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>Not ready for this stage yet.</td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["apps"] <= 1) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/incomplete.gif" alt="O" border="0"></td><td>You are ready for this stage, but this stage is not yet written.<br></td></tr>';
  }elseif ($phpgw_info["setup"]["stage"]["apps"] == 10) {
    echo '<tr><td align="center"><img src="'.$phpgw_info["server"]["app_images"].'/completed.gif" alt="O" border="0"></td><td>This stage is completed<br></td></tr>';
  }
  echo '</table>';
  echo "</body></html>";
?>
