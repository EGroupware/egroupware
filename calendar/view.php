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

  function add_day(&$repeat_days,$day) {
    if($repeat_days) $repeat_days .= ", ";
    $repeat_days .= $day;
  }

  function display_item($field,$data) {
    global $phpgw;

    $phpgw->template->set_var("field",$field);
    $phpgw->template->set_var("data",$data);
    $phpgw->template->parse("output","list",True);
  }


  if ($year) $thisyear = $year;
  if ($month) $thismonth = $month;

  $pri[1] = lang("Low");
  $pri[2] = lang("Normal");
  $pri[3] = lang("High");

  $db = $phpgw->db;

  $unapproved = FALSE;

  // first see who has access to view this entry
  $is_my_event = false;
  $cal = $phpgw->calendar->getevent(intval($id));

  $cal_info = $cal[0];

  if(($cal_info->owner == $phpgw_info["user"]["account_id"]) || $phpgw_info["user"]["apps"]["admin"]) 
     $is_my_event = true;

  $description = nl2br($description);

  $phpgw->template->set_file(array("view_begin" => "view.tpl",
				   "list"       => "list.tpl",
				   "view_end"   => "view.tpl",
				   "form_button"=> "form_button_script.tpl"));

  $phpgw->template->set_block("view_begin","list","view_end","form_button");

  $phpgw->template->set_var("bg_text",$phpgw_info["theme"]["bg_text"]);
  $phpgw->template->set_var("name",$cal_info->name);
  $phpgw->template->parse("out","view_begin");

  // Some browser add a \n when its entered in the database. Not a big deal
  // this will be printed even though its not needed.
  if (nl2br($cal_info->description)) {
    display_item(lang("Description"),nl2br($cal_info->description));
  }

  display_item(lang("Start Date/Time"),$phpgw->common->show_date($cal_info->datetime));

  // save date so the trailer links are for the same time period
  $thisyear	= (int)$cal_info->year;
  $thismonth	= (int)$cal_info->month;
  $thisday 	= (int)$cal_info->day;

  display_item(lang("End Date/Time"),$phpgw->common->show_date($cal_info->edatetime));

  display_item(lang("Priority"),$pri[$cal_info->priority]);

  $phpgw->template->set_var("field",lang("Created by"));
  $participate = False;
  for($i=0;$i<count($cal_info->participants);$i++) {
    if($cal_info->participants[$i] == $phpgw_info["user"]["account_id"]) {
      $participate = True;
    }
  }
  if($is_my_event && $participate)
    display_item(lang("Created by"),"<a href=\""
	.$phpgw->link("viewmatrix.php","participants=".$cal_info->owner."&date=".$cal_info->year.$cal_info->month.$cal_info->day."&matrixtype=free/busy")
	."\">".$phpgw->common->grab_owner_name($cal_info->owner)."</a>");
  else
    display_item(lang("Created by"),$phpgw->common->grab_owner_name($cal_info->owner));

  display_item(lang("Updated"),$phpgw->common->show_date($cal_info->mdatetime));

  if($cal_info->groups[0]) {
    $cal_grps = "";
    for($i=0;$i<count($cal_info->groups);$i++) {
      if($i>0) $cal_grps .= "<br>";
      $db->query("SELECT group_name FROM groups WHERE group_id=".$cal_info->groups[$i],__LINE__,__FILE__);
      $db->next_record();
      $cal_grps .= $db->f("group_name");
    }
    display_item(lang("Groups"),$cal_grps);
  }

  $str = "";
  for($i=0;$i<count($cal_info->participants);$i++) {
    if($i) $str .= "<br>";
    $str .= $phpgw->common->grab_owner_name($cal_info->participants[$i]);
  }
  display_item(lang("Participants"),$str);

// Repeated Events
  $str = $cal_info->rpt_type;
  if($str <> "none" || $cal_info->rpt_use_end) {
    $str .= " (";
    if($cal_info->rpt_use_end) 
      $str .= lang("ends").": ".$phpgw->common->show_date($cal_info->rpt_end,"l, F d, Y")." ";
    if($cal_info->rpt_type == "weekly" || $cal_info->rpt_type == "daily") {
      $repeat_days = "";
      if ($cal_info->rpt_sun)
	add_day($repeat_days,lang("Sunday "));
      if ($cal_info->rpt_mon)
	add_day($repeat_days,lang("Monday "));
      if ($cal_info->rpt_tue)
	add_day($repeat_days,lang("Tuesay "));
      if ($cal_info->rpt_wed)
	add_day($repeat_days,lang("Wednesday "));
      if ($cal_info->rpt_thu)
	add_day($repeat_days,lang("Thursday "));
      if ($cal_info->rpt_fri)
	add_day($repeat_days,lang("Friday "));
      if ($cal_info->rpt_sat)
	add_day($repeat_days,lang("Saturday "));
      $str .= lang("days repeated").": ".$repeat_days;
    }
    if($cal_info->rpt_freq) $str .= lang("frequency")." ".$cal_info->rpt_freq;
    $str .= ")";

    display_item(lang("Repetition"),$str);
  }

  if ($is_my_event) {
    $phpgw->template->set_var("action_url_button",$phpgw->link("edit_entry.php","id=$id"));
    $phpgw->template->set_var("action_text_button","  ".lang("Edit")."  ");
    $phpgw->template->set_var("action_confirm_button","");
    $phpgw->template->parse("edit_button","form_button");

    $phpgw->template->set_var("action_url_button",$phpgw->link("delete.php","id=$id"));
    $phpgw->template->set_var("action_text_button",lang("Delete"));
    $phpgw->template->set_var("action_confirm_button","onClick=\"return confirm('".lang("Are you sure\\nyou want to\\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.")."')\"");
    $phpgw->template->parse("delete_button","form_button");
  } else {
    $phpgw->template->set_var("edit_button","");
    $phpgw->template->set_var("delete_button","");
  }
  $phpgw->template->pparse("out","view_end");
  $phpgw->common->phpgw_footer();
?>
