<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("currentapp" => "calendar", "enable_nextmatchs_class" => True);

  if (! $keywords) {
     // If we reach this, it is because they didn't search for anything,
     // attempt to send them back to where they where.
     Header("Location: " . $phpgw->link($from,"date=$datemonth=$month&day=$day&year=$year"));
  }

  include("../header.inc.php");

  $error = "";

  if (strlen($keywords) == 0) {
    echo "<b>".lang("Error").":</b>";
    echo lang("You must enter one or more search keywords.");
    $phpgw->common->phpgw_footer();
    $phpgw->common->phpgw_exit();
  }
  $matches = 0;

  $phpgw->calendar->set_filter();

  // There is currently a problem searching in with repeated events.
  // It spits back out the date it was entered.  I would like to to say that
  // it is a repeated event.
  $ids = array();
  $words = split(" ", $keywords);
  for ($i = 0; $i < count($words); $i++) {
    $sql = "SELECT DISTINCT calendar_entry.cal_id, calendar_entry.cal_name, "
         . "calendar_entry.cal_datetime "
         . "FROM calendar_entry, calendar_entry_user "
	 . "WHERE "
	 . "(UPPER(calendar_entry.cal_name) LIKE UPPER('%".$words[$i]."%') OR "
	 . " UPPER(calendar_entry.cal_description) LIKE UPPER('%".$words[$i]."%')) AND "
	 . "calendar_entry_user.cal_id=calendar_entry.cal_id AND ";

    $sqlfilter = "";
// Private
    if($phpgw->calendar->filter==" all " || strpos($phpgw->calendar->filter,"private")) {
      $sqlfilter .= "(calendar_entry_user.cal_login = ".$phpgw_info["user"]["account_id"]." AND calendar_entry.cal_access='private') ";
    }

// Group Public
    if($phpgw->calendar->filter==" all " || strpos($phpgw->calendar->filter,"group")) {
      if($sqlfilter)
	$sqlfilter .= "OR ";
      $sqlfilter .= $phpgw->calendar->group_search($phpgw_info["user"]["account_id"])." ";
    }

// Global Public
    if($phpgw->calendar->filter==" all " || strpos($phpgw->calendar->filter,"public")) {
      if($sqlfilter)
	$sqlfilter .= "OR ";
      $sqlfilter .= "calendar_entry.cal_access='public' ";
    }
    $orderby = " ORDER BY calendar_entry.cal_datetime ASC";

    if($sqlfilter) $sql .= "(".$sqlfilter.") ";
    $sql .= $orderby;

    $phpgw->db->query($sql,__LINE__,__FILE__);
    while ($phpgw->db->next_record()) {
      $matches++;
      $ids[strval( $phpgw->db->f(0) )]++;
      $info[strval( $phpgw->db->f(0) )] = $phpgw->db->f(1) . " ("
                                        . $phpgw->common->show_date($phpgw->db->f(2)) . ")";
    }
  }

  if ($matches > 0)
    $matches = count($ids);

  if ($matches == 1)
    $quantity = "1 match found.";
  else if ($matches > 0)
    $quantity = lang("x matches found",$matches).".";
  else
    $error = lang("no matches found.");

  if($error) {
    echo "<b>".lang("Error").":</b>";
    echo $error;
    $phpgw->common->phpgw_footer();
    $phpgw->common->phpgw_exit();
  }

  $phpgw->template->set_file(array("search_t"	 => "search.tpl",
				   "search_list" => "search_list.tpl"));

  $phpgw->template->set_block("search_t","search_list");

  $phpgw->template->set_var("color",$phpgw_info["theme"]["bg_text"]);
  $phpgw->template->set_var("search_text",lang("Search Results"));
  $phpgw->template->set_var("quantity",$quantity);

// now sort by number of hits
  if (! strlen($error)) {
    arsort ($ids);
    for (reset($ids); $key = key($ids); next($ids)) {
      $phpgw->template->set_var("url_result",$phpgw->link("view.php","id=$key"));
      $phpgw->template->set_var("result_desc",$info[$key]);
      $phpgw->template->parse("output","search_list",True);
    }
  }

  $phpgw->template->pparse("out","search_t");

  $phpgw->common->phpgw_footer();
?>
