<?php
  /**************************************************************************\
  * phpGroupWare - preferences                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  //$phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);

  $phpgw_info["flags"]["currentapp"] = "preferences";
  include("../header.inc.php");
  if ($phpgw_info["user"]["apps"]["anonymous"]) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/"));
     exit;
  }

  echo "<br><a href=\"" . $phpgw->link("changepassword.php") . "\">"
     . lang("change your password") . "</a>";
  echo "<br><a href=\"" . $phpgw->link("changetheme.php") . "\">"
     . lang("select different theme") . "</a>";
  echo "<br><a href=\"" . $phpgw->link("settings.php") . "\">"
     . lang("change your settings") . "</a>";
  echo "<br><a href=\"" . $phpgw->link("changeprofile.php") . "\">"
     . lang("change your profile") . "</a>";
//  if ($phpgw_info["user"]["permissions"]["nntp"])
  if ($phpgw_info["user"]["apps"]["nntp"])
    echo "<br><a href=\"" . $phpgw->link("nntp.php") . "\">"
       . lang("monitor newsgroups") . "</a>";

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
