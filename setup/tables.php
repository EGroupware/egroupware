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
  include("../header.inc.php");

  // Authorize the user to use setup app and load the database
  // Does not return unless user is authorized
  if (!auth()){
    Header("Location: index.php");
    exit;
  }
  loaddb();
  $db->Halt_On_Error = "no";
  //$db->Halt_On_Error = "report";

  if (!isset($oldversion)){
    Header("Location: index.php");
    exit;
//    $db->query("select app_version from applications where app_name='admin'");
//    $db->next_record();
//    $oldversion = $db->f("app_version");
  }

  if (PHP_VERSION < "3.0.16") {
     echo "You appear to be running an old version of PHP.  It its recommend that you upgrade "
        . "to a new version.  Older version of PHP might not run phpGroupWare correctly, if at all.";
  }

  /* Database setup */
  switch($action){
    case "Delete all my tables and data":
      $subtitle = "Deleting Tables";
      $submsg = "At your request, this script is going to take the evil action of deleting your existing tables and re-creating them in the new format.";
      $subaction = "deleted";
      $currentver = "drop";
      break;
    case "Upgrade":
      $subtitle = "Upgrading Tables";
      $submsg = "At your request, this script is going to attempt to upgrade your old tables to the new format.";
      $subaction = "upgraded";
      $currentver = $oldversion;
      break;      
    case "Create":
      $subtitle = "Creating Tables";
      $submsg = "At your request, this script is going to attempt to the tables for you.";
      $subaction = "created";
      $currentver = "new";
      break;      
  }
  $stage = 2.5;
  show_header($header_msg);
  show_steps($stage);
  echo "</body></html>";
?>