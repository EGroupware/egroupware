<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
  $phpgw_info["flags"] = array("currentapp" => "calendar", "noheader" => True, "nonavbar" => True);
  include("../header.inc.php");

  if ($id > 0) {
     $phpgw->db->query("SELECT cal_datetime FROM calendar_entry WHERE cal_id = $id",__LINE__,__FILE__);
     $phpgw->db->next_record();

     $thisyear = intval($phpgw->common->show_date($phpgw->db->f("cal_datetime"),"Y"));
     $thismonth = intval($phpgw->common->show_date($phpgw->db->f("cal_datetime"),"n"));

     $phpgw->calendar->delete(intval($id));
  }

  Header("Location: " . $phpgw->link("index.php","year=$thisyear&month=$thismonth"));
?>
