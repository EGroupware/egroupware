<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_flags["disable_message_class"] = True;
  $phpgw_flags["disable_send_class"] = True;
  $phpgw_flags["disable_nextmatchs_class"] = True;
  $phpgw_flags["disable_template_class"] = True;
  $phpgw_flags["currentapp"] = "logout";
  $phpgw_flags["noheader"] = True;

  include("header.inc.php");

  $sep = $phpgw->common->filesystem_separator();

/*
  if ($installed[cron_apps] != "Y") {
     $dh = opendir($phpgw_info["server"]["temp_dir"]);
     while ($dir = readdir($dh)) {
       if ($dir != "." && $dir != "..") {
          if (substr($dir,0,strlen(time())) <= (time() - 3600)) {
            echo $phpgw_info["server"]["temp_dir"] . $sep . $dir;
             $fh = opendir($phpgw_info["server"]["temp_dir"] . $sep . $dir);
             while ($file = readdir($fh)) {
               if ($file != "." && $file != "..") {
                  unlink($phpgw_info["server"]["temp_dir"] . $sep . $dir . $sep . $file);
               }
             }
             rmdir($phpgw_info["server"]["temp_dir"] . $sep . $dir);
          }
       }
     }
  }
*/

  if ($phpgw->session->verify($sessionid)) {
    if (file_exists($phpgw_info["server"]["temp_dir"] . $sep . $sessionid)) {
      $dh = opendir($phpgw_info["server"]["temp_dir"] . $sep . $sessionid);
      while ($file = readdir($dh)) {
        if ($file != "." && $file != "..") {
          unlink($phpgw_info["server"]["temp_dir"] . $sep . $sessionid . $sep . $file);
        }
      }
      rmdir($phpgw_info["server"]["temp_dir"] . $sep . $sessionid);
    }
    $phpgw->session->destroy();
  }

  Header("Location: " . $phpgw_info["server"]["webserver_url"] . "/login.php?cd=1");
?>
