<?phpphp_track_vars?>
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

  $phpgw_flags["currentapp"] = "calendar";
  include("../header.inc.php");

  if ($id > 0) {
     $phpgw->db->query("SELECT cal_date FROM webcal_entry WHERE cal_id = $id");
     // date format is 19991231
     $phpgw->db->next_record();

     $thisyear = (int)($phpgw->db->f(0) / 10000);
     $thismonth = ($phpgw->db->f(0) / 100) % 100;

     $phpgw->db->query("DELETE FROM webcal_entry WHERE cal_id = $id");
     $phpgw->db->query("DELETE FROM webcal_entry_user WHERE cal_id = $id");
     $phpgw->db->query("DELETE FROM webcal_entry_repeats WHERE cal_id = $id");
     $phpgw->db->query("DELETE FROM webcal_entry_groups WHERE cal_id = $id");
  }

  Header("Location: " . $phpgw->link("index.php,"year=$thisyear&month=$thismonth"));
