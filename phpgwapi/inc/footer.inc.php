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

  $tpl = new Template($phpgw_info["server"]["template_dir"]);
  $tpl->set_unknowns("remove");

  $tpl->set_file(array("footer" => "footer.tpl"));
  $tpl->set_var("img_root",$phpgw_info["server"]["webserver_url"] . "/phpgwapi/templates/verdilak/images");
  $tpl->set_var("table_bg_color",$phpgw_info["theme"]["navbar_bg"]);
  $tpl->set_var("showpoweredbyon",$phpgw_info["server"]["showpoweredbyon"]);
  $tpl->set_var("version",$phpgw_info["server"]["versions"]["phpgwapi"]);
  echo $tpl->finish($tpl->parse("out","footer"));
  $phpgw->db->disconnect();
  
?>
</BODY>
</HTML>
