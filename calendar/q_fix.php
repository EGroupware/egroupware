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

  $phpgw_info["flags"] = array("currentapp" => "calendar", "enable_calendar_class" => True);

  include("../header.inc.php");

  $db2 = $phpgw->db;

  $phpgw->db->query("SELECT cal_datetime, cal_mdatetime, cal_id, cal_owner FROM calendar_entry ORDER BY cal_id",__LINE__,__FILE__);
  if($phpgw->db->num_rows()) {
    while($phpgw->db->next_record()) {
      $db2->query("SELECT preference_value FROM preferences WHERE preference_name='tz_offset' AND preference_appname='common' AND preference_owner=".$db->("cal_owner"),__LINE__,__FILE__);
      $db2->next_record();
      $tz = $db2->f("preference_value");
      $datetime = $phpgw->db->f("cal_datetime") + ((60*60) * $tz);
      $mdatetime = $phpgw->db->f("cal_mdatetime") + ((60*60) * $tz);
      $db2->query("UPDATE calendar_entry SET cal_datetime=".$datetime.", cal_mdatetime=".$mdatetime." WHERE cal_id=".$phpgw->db->f("cal_id"),__LINE__,__FILE__);
    }
  }
?>
