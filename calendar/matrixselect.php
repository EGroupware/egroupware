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

  $phpgw_info["flags"] = array("currentapp" => "calendar", "enable_calendar_class" => True, "enable_nextmatchs_class" => True, "parent_page" => "index.php");

  include("../header.inc.php");

  if(isset($friendly) && $friendly) {
    if(!isset($phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"]))
      $phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"] = "Sunday";

    if (isset($date) && strlen($date) > 0) {
       $thisyear  = substr($date, 0, 4);
       $thismonth = substr($date, 4, 2);
       $thisday   = substr($date, 6, 2);
    } else {
       if (!isset($day) || !$day)
          $thisday = $phpgw->calendar->today["day"];
       else
          $thisday = $day;
       if (!isset($month) || !$month)
          $thismonth = $phpgw->calendar->today["month"];
       else
          $thismonth = $month;
       if (!isset($year) || !$year)
          $thisyear = $phpgw->calendar->today["year"];
       else
          $thisyear = $year;
    }
  }

  $phpgw->template->set_file(array("matrix_query_begin" => "matrix_query.tpl",
				   "list"     	      => "list.tpl",
				   "matrix_query_end"   => "matrix_query.tpl",
				   "form_button"      => "form_button_script.tpl"));

  $phpgw->template->set_block("matrix_query_begin","list","matrix_query_end","form_button");

//  $phpgw->template->set_var("bg_color",$phpgw_info["theme"]["bg_text"]);
  $phpgw->template->set_var("matrix_action",lang("Daily Matrix View"));
  $phpgw->template->set_var("action_url",$phpgw->link("timematrix.php"));

  $phpgw->template->parse("out","matrix_query_begin");

  $phpgw->template->set_var("field",lang("Date"));

  $day_html = "<select name=\"day\">";
  for ($i = 1; $i <= 31; $i++)
    $day_html .= "<option value=\"$i\"" . ($i == $thisday ? " selected" : "") . ">$i"
	       . "</option>\n";
  $day_html .= "</select>";

  $month_html = "<select name=\"month\">";
  for ($i = 1; $i <= 12; $i++) {
    $m = lang(date("F", mktime(0,0,0,$i,1,$cal_info->year)));
    $month_html .= "<option value=\"$i\"" . ($i == $thismonth ? " selected" : "") . ">$m"
		 . "</option>\n";
  }
  $month_html .= "</select>";

  $year_html = "<select name=\"year\">";
  for ($i = ($thisyear - 1); $i < ($thisyear + 5); $i++) {
    $year_html .= "<option value=\"$i\"" . ($i == $thisyear ? " selected" : "") . ">$i"
	 	. "</option>\n";
  }
  $year_html .= "</select>";

  $phpgw->template->set_var("data",$phpgw->common->dateformatorder($year_html,$month_html,$day_html));
  $phpgw->template->parse("output","list",True);

  $phpgw->template->set_var("field",lang("Participants"));
  $db2 = $phpgw->db;
  $db2->query("select account_id,account_lastname,account_firstname "
	    . "from accounts where account_status !='L' and "
	    . "account_id != ".$phpgw_info["user"]["account_id"]." "
	    . "and account_permissions like '%:calendar:%' "
  	    . "order by account_lastname,account_firstname");

  $num_rows = $db2->num_rows();
  if ($num_rows > 50)
    $size = 15;
  elseif ($num_rows > 5)
    $size = 5;
  else
    $size = $num_rows;
  $str = "<select name=\"participants[]\" multiple size=\"$size\">";

  while($db2->next_record()) {
    $id = $db2->f("account_id");
    $str .= "<option value=\"".$id."\">".$phpgw->common->grab_owner_name($id)."</option>\n";
  }
  $str .= "</select>";
  $str .= "<input type=\"hidden\" name=\"participants[]\" value=\"".$phpgw_info["user"]["account_id"]."\">";
  $phpgw->template->set_var("data",$str);
  $phpgw->template->parse("output","list",True);

  $phpgw->template->set_var("submit_button",lang("Submit"));

  $phpgw->template->set_var("action_url_button","");
  $phpgw->template->set_var("action_text_button",lang("Cancel"));
  $phpgw->template->set_var("action_confirm_button","onClick=\"history.back(-1)\"");

  $phpgw->template->parse("cancel_button","form_button");

  $phpgw->template->pparse("out","matrix_query_end");

  $phpgw->common->phpgw_footer();

?>
