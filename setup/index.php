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
      if (!auth()){
        show_header("Please login",True);
        loginForm($login_msg);
        exit;
      }else{ /* authentication settled. Moving to the database portion. */
        loaddb();
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
      } /* from authentication check */
    } /* from header version check */
  } /* From header.inc.php not existing */
  show_header($header_msg);
  show_steps($stage);
  echo "</body></html>";
?>
