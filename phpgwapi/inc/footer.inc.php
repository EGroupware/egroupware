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
  echo $tpl->finish($tpl->parse("out","footer"));

  // This will need to be converted into the classic template

/*  if ($phpgw_info["server"]["showpoweredbyon"] == "bottom" && $phpgw_info["server"]["showpoweredbyon"] != "top") {
     echo "<P>\n";
     echo "<Table Width=100% Border=0 CellPadding=0 CellSpacing=0 BGColor=".$phpgw_info["theme"]["navbar_bg"].">\n";
     echo " <TR><TD>";
     echo "<P><P>\n" . lang("Powered by phpGroupWare version x",
							$phpgw_info["server"]["versions"]["phpgwapi"]) . "<br>\n";
     echo "</TD>";
     if ($phpgw_info["flags"]["parent_page"])
       echo "<td align=\"right\"><a href=\"".$phpgw->link($phpgw_info["flags"]["parent_page"])."\">".lang("up")."</a></td>";
     echo "</TR>\n</Table>\n";
  } */
  $phpgw->db->disconnect();
  
?>
</BODY>
</HTML>
