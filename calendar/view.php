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

  $phpgw_info["flags"] = array("currentapp" => "calendar", "enable_calendar_class" => True, "enable_nextmatchs_class" => True);
  include("../header.inc.php");

  if ($id < 1) {
     echo lang("Invalid entry id.");
     exit;
  }

  function add_day($repeat_days,$day) {
    if($repeat_days) $repeat_days .= ", ";
    return $repeat_days . $day;
  }

  if ($year) $thisyear = $year;
  if ($month) $thismonth = $month;

  $pri[1] = lang("Low");
  $pri[2] = lang("Medium");
  $pri[3] = lang("High");

  $unapproved = FALSE;

  // first see who has access to view this entry
  $is_my_event = false;
  $cal = $phpgw->calendar->getevent((int)$id);

  $cal_info = $cal[0];

  if ($cal_info->owner == $phpgw_info["user"]["account_id"])
     $is_my_event = true;

  $description = nl2br($description);

  $phpgw->template->set_file(array("view_begin" => "view.tpl",
				   "list"       => "list.tpl",
				   "view_end"   => "view.tpl"));

  $phpgw->template->set_block("view_begin","list","view_end");

  $phpgw->template->set_var("bg_color",$phpgw_info["theme"]["bg_text"]);
  $phpgw->template->set_var("name",$cal_info->name);
  $phpgw->template->parse("out","view_begin");

  // Some browser add a \n when its entered in the database. Not a big deal
  // this will be printed even though its not needed.
  if (nl2br($cal_info->description)) {
    $phpgw->template->set_var("field",lang("Description"));
    $phpgw->template->set_var("data",nl2br($cal_info->description));
    $phpgw->template->parse("output","list",True);
  }

  $phpgw->template->set_var("field",lang("Date"));
  $phpgw->template->set_var("data",$phpgw->common->show_date(mktime(0,0,0,$cal_info->month,$cal_info->day,$cal_info->year),"l, F d, Y"));
  $phpgw->template->parse("output","list",True);

  // save date so the trailer links are for the same time period
  $thisyear	= (int)$cal_info->year;
  $thismonth	= (int)$cal_info->month;
  $thisday 	= (int)$cal_info->day;

  if($cal_info->hour || $cal_info->minute) {
    $phpgw->template->set_var("field",lang("Time"));
    $phpgw->template->set_var("data",$phpgw->calendar->build_time_for_display($phpgw->calendar->fixtime($cal_info->hour,$cal_info->minute,$cal_info->ampm)));
    $phpgw->template->parse("output","list",True);
  }

  if ($cal_info->duration > 0) {
    $phpgw->template->set_var("field",lang("Duration"));
    $phpgw->template->set_var("data",$cal_info->duration." ".lang("minutes"));
    $phpgw->template->parse("output","list",True);
  }

  $phpgw->template->set_var("field",lang("Priority"));
  $phpgw->template->set_var("data",$pri[$cal_info->priority]);
  $phpgw->template->parse("output","list",True);

  $phpgw->template->set_var("field",lang("Created by"));
  if($is_my_event)
    $phpgw->template->set_var("data","<a href=\""
	.$phpgw->link("timematrix.php","participants=".$cal_info->owner."&date=".$cal_info->year.$cal_info->month.$cal_info->day)
	."\">".$phpgw->common->grab_owner_name($cal_info->owner)."</a>");
  else
    $phpgw->template->set_var("data",$phpgw->common->grab_owner_name($cal_info->owner));
  $phpgw->template->parse("output","list",True);

  $phpgw->template->set_var("field",lang("Updated"));
  $phpgw->template->set_var("data",$phpgw->common->show_date(mktime(0,0,0,$cal_info->mod_month,$cal_info->mod_day,$cal_info->mod_year),"l, F d, Y")." ".$phpgw->calendar->build_time_for_display($phpgw->calendar->fixtime($cal_info->mod_hour,$cal_info->mod_minute,$cal_info->mod_ampm)));
  $phpgw->template->parse("output","list",True);

  if($cal_info->groups) {
    $cal_groups = explode(",",$phpgw->accounts->convert_str_to_names_access($cal_info->groups));
    $cal_grps = "";
    for($i=1;$i<=count($cal_groups);$i++) {
      if($i>1) $cal_grps .= "<br>";
      $cal_grps .= $cal_groups[$i];
    }
    $phpgw->template->set_var("field",lang("Groups"));
    $phpgw->template->set_var("data",$cal_grps);
    $phpgw->template->parse("output","list",True);
  }

  $str = "";
  for($i=0;$i<count($cal_info->participants);$i++) {
    if($i) $str .= "<br>";
    $str .= $phpgw->common->grab_owner_name($cal_info->participants[$i]);
  }
  $phpgw->template->set_var("field",lang("Participants"));
  $phpgw->template->set_var("data",$str);
  $phpgw->template->parse("output","list",True);

// Repeated Events
  $str = $cal_info->rpt_type;
  if($str <> "none" || ($cal_info->rpt_end_month && $cal_info->rpt_end_day && $cal_info->rpt_end_year)) {
    $str .= " (";
    if($cal_info->rpt_end_month && $cal_info->rpt_end_day && $cal_info->rpt_end_year) 
      $str .= lang("ends").": ".$phpgw->common->show_date(mktime(0,0,0,$cal_info->rpt_end_month,$cal_info->rpt_end_day,$cal_info->rpt_end_year),"l, F d, Y")." ";
    if($cal_info->rpt_type == "weekly") {
      if ($cal_info->rpt_sun)
	$repeat_days = add_day($repeat_days,lang("Sunday "));
      if ($cal_info->rpt_mon)
	$repeat_days = add_day($repeat_days,lang("Monday "));
      if ($cal_info->rpt_tue)
	$repeat_days = add_day($repeat_days,lang("Tuesay "));
      if ($cal_info->rpt_wed)
	$repeat_days = add_day($repeat_days,lang("Wednesday "));
      if ($cal_info->rpt_thu)
	$repeat_days = add_day($repeat_days,lang("Thursday "));
      if ($cal_info->rpt_fri)
	$repeat_days = add_day($repeat_days,lang("Friday "));
      if ($cal_info->rpt_sat)
	$repeat_days = add_day($repeat_days,lang("Saturday "));
      $str .= lang("days repeated").": ".$repeat_days;
    }
    if($cal_info->rpt_freq) $str .= lang("frequency")." ".$cal_info->rpt_freq;
    $str .= ")";

    $phpgw->template->set_var("field",lang("Repetition"));
    $phpgw->template->set_var("data",$str);
    $phpgw->template->parse("output","list",True);
  }

  if ($is_my_event) {
    $phpgw->template->set_var("edit","<a href=\"".$phpgw->link("edit_entry.php","id=$id")."\">".lang("Edit")."</a>");
    $phpgw->template->set_var("delete","<a href=\"".$phpgw->link("delete.php","id=$id")."\" onClick=\"return confirm('".lang("Are you sure\\nyou want to\\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.")."');\">".lang("Delete")."</a>");
  } else {
    $phpgw->template->set_var("edit","");
    $phpgw->template->set_var("delete","");
  }
  $phpgw->template->pparse("out","view_end");
  $phpgw->common->phpgw_footer();
?>
