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

  $phpgw_info["flags"] = array("currentapp" => "calendar", "noheader" => True, "nonavbar" => True, "enable_nextmatchs_class" => True, "noappheader" => True, "noappfooter" => True);
  include("../header.inc.php");

  $cal_info = new calendar_item;

  function validate($cal_info) {
    $error = 0;
    // do a little form verifying
    if ($cal_info->name == "") {
      $error = 40;
    } elseif (($cal_info->hour < 0 || $cal_info->hour > 23) || ($cal_info->end_hour < 0 || $cal_info->end_hour > 23)) {
      $error = 41;
    } elseif (($cal_info->minute < 0 || $cal_info->minute > 59) || ($cal_info->end_minute < 0 || $cal_info->minute > 59)) {
      $error = 41;
    } elseif (($cal_info->year == $cal_info->end_year) && ($cal_info->month == $cal_info->end_month) && ($cal_info->day == $cal_info->end_day)) {
      if ($cal_info->hour > $cal_info->end_hour) {
	$error = 42;
      } elseif (($cal_info->hour == $cal_info->end_hour) && ($cal_info->minute > $cal_info->end_minute)) {
	$error = 42;
      }
    } elseif (($cal_info->year == $cal_info->end_year) && ($cal_info->month == $cal_info->end_month) && ($cal_info->day > $cal_info->end_day)) {
      $error = 42;
    } elseif (($cal_info->year == $cal_info->end_year) && ($cal_info->month > $cal_info->end_month)) {
      $error = 42;
    } elseif ($cal_info->year > $cal_info->end_year) {
      $error = 42;
    }
    return $error;
  }

  if(!isset($readsess)) {
    for(reset($cal);$key=key($cal);next($cal)) {
      $data = $cal[$key];
      $cal_info->set($key,$data);
    }

    $participating = False;
    if($phpgw_info["user"]["account_id"] == $cal_info->participants[count($cal_info->participants) - 1]) {
      $participating = True;
    }
    if(!$participating && count($cal_info->participants) == 1) {
      $cal_info->owner = $cal_info->participants[0];
    }
    if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
      if ($cal_info->ampm == "pm" && $cal_info->hour <> 12) {
	$cal_info->hour += 12;
      }
      if ($cal_info->end_ampm == "pm" && $cal_info->end_hour <> 12) {
	$cal_info->end_hour += 12;
      }
    }
    $cal_info->datetime = mktime($cal_info->hour,$cal_info->minute,0,$cal_info->month,$cal_info->day,$cal_info->year) - ((60 * 60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);
    $cal_info->edatetime = mktime($cal_info->end_hour,$cal_info->end_minute,0,$cal_info->end_month,$cal_info->end_day,$cal_info->end_year) - ((60 * 60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);
    $cal_info->rpt_end = mktime(12,0,0,$cal_info->rpt_month,$cal_info->rpt_day,$cal_info->rpt_year) - ((60 * 60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);

    $cal_info = $phpgw->common->appsession($cal_info);
    $datetime_check = validate($cal_info);
    if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
      if ($cal_info->hour >= 12) {
        $cal_info->ampm = "";
      }
      if ($cal_info->end_hour >= 12) {
        $cal_info->end_ampm = "";
      }
    }
    $cal_info->datetime += ((60 * 60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);
    $cal_info->edatetime += ((60 * 60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);
    $overlapping_events = $phpgw->calendar->overlap($cal_info->datetime,$cal_info->edatetime,$cal_info->participants,$cal_info->groups,$cal_info->owner,$cal_info->id);
  } else {
    $cal_info = $phpgw->common->appsession();
  }

  if($datetime_check) {
    Header("Location: ".$phpgw->link("edit_entry.php","readsess=".$cal_info->id."&cd=".$datetime_check));
  } elseif($overlapping_events) {
    $phpgw->common->phpgw_header();
    echo parse_navbar();
    $phpgw->template->set_file(array("overlap" => "overlap.tpl",
				   "form_button"     => "form_button_script.tpl"));

    $phpgw->template->set_block("overlap","form_button");

    $phpgw->template->set_var("color",$phpgw_info["theme"]["bg_text"]);

    $calendar_overlaps = $phpgw->calendar->getevent($overlapping_events);

    $format = $phpgw_info["user"]["preferences"]["common"]["dateformat"] . " - ";
    if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
      $format .= "h:i:s a";
    } else {
      $format .= "H:i:s";
    }

    $overlap = "";
    for($i=0;$i<count($calendar_overlaps);$i++) {
      $cal_over = $calendar_overlaps[$i];
      if($cal_over) {
        $overlap .= "<li>";
        $private = $phpgw->calendar->is_private($cal_over,$cal_over->owner);
        if(strtoupper($private) == "PRIVATE")
          $overlap .= "(PRIVATE)";
        else
          $overlap .= $phpgw->calendar->link_to_entry($cal_over->id,"circle.gif",$cal_over->description).$cal_over->name;
        $overlap .= " (".$phpgw->common->show_date($cal_over->datetime)." - ".$phpgw->common->show_date($cal_over->edatetime).")<br>";
      }
    }
    if(strlen($overlap)) {
      $phpgw->template->set_var("overlap_text",lang("Your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:",date($format,$cal_info->datetime),date($format,$cal_info->edatetime)));
      $phpgw->template->set_var("overlap_list",$overlap);
    } else {
      $phpgw->template->set_var("overlap_text","");
      $phpgw->template->set_var("overlap_list","");
    }

    $phpgw->template->set_var("action_url_button",$phpgw->link("","readsess=".$cal_info->id));
    $phpgw->template->set_var("action_text_button",lang("Ignore Conflict"));
    $phpgw->template->set_var("action_confirm_button","");
    $phpgw->template->parse("resubmit_button","form_button");

    $phpgw->template->set_var("action_url_button",$phpgw->link("edit_entry.php","readsess=".$cal_info->id));
    $phpgw->template->set_var("action_text_button",lang("Re-Edit Event"));
    $phpgw->template->set_var("action_confirm_button","");
    $phpgw->template->parse("reedit_button","form_button");

    $phpgw->template->pparse("out","overlap");
  } else {
    $phpgw->calendar->add($cal_info,$cal_info->id);
    Header("Location: ".$phpgw->link("index.php","year=$year&month=$month&cd=14"));
  }
  $phpgw->common->phpgw_footer();
?>

