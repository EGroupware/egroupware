<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $d1 = strtolower(substr($phpgw_info["server"]["app_inc"],0,3));
  if($d1 == "htt" || $d1 == "ftp") {
    echo "Failed attempt to break in via an old Security Hole!<br>\n";
    exit;
  } unset($d1);

  /**************************************************************************\
  * Include the apps footer files if it exists                               *
  \**************************************************************************/
  if (file_exists ($phpgw_info["server"]["app_inc"]."/footer.inc.php") 
   && $phpgw_info["flags"]["currentapp"] != "home"
   && $phpgw_info["flags"]["currentapp"] != "login"
   && $phpgw_info["flags"]["currentapp"] != "logout"){
    include($phpgw_info["server"]["app_inc"]."/footer.inc.php");
  }

  parse_navbar_end();
  $phpgw->db->disconnect();
  
?>
</BODY>
</HTML>
