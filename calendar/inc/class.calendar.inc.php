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

  class calendar
  {
    var $today = array("full","month","day","year");
    var $printer_friendly = False;
    var $repeated_events;
    var $checked_events;
    var $re = 0;
    var $checkd_re = 0;
    var $sorted_re = 0;
    var $hour_arr = Array();
    var $rowspan_arr = Array();
    var $days = Array();
    var $first_hour;
    var $last_hour;
    var $rowspan;
    var $weekstarttime;
    var $daysinweek = 7;
    var $filter;
    var $tempyear;
    var $tempmonth;
    var $tempday;

    function calendar_($p_friendly=False) {
      global $phpgw;

      $this->printer_friendly = $p_friendly;

//      $now = time();
      $this->today = $this->localdates(time());
    }

    function set_filter() {
      global $phpgw_info, $phpgw, $filter;
      if (!isset($this->filter) || !$this->filter) {
         if (isset($filter) && $filter) {
            $this->filter = " ".$filter." ";
         } else {
            if (! $phpgw_info["user"]["preferences"]["calendar"]["defaultfilter"]) {
               $phpgw->preferences->change("calendar","defaultfilter","all");
               $phpgw->preferences->commit();
            }
            $this->filter = " ".$phpgw_info["user"]["preferences"]["calendar"]["defaultfilter"]." ";
         }
      }
    }

    function group_search($owner=0) {
      global $phpgw;
      global $phpgw_info;
      $owner = $owner==$phpgw_info["user"]["account_id"]?0:$owner;
      $groups = substr($phpgw->accounts->sql_search("calendar_entry.cal_group",$owner),4);
      if (!$groups) {
	return "";
      } else {
	return "(calendar_entry.cal_access='group' AND (". $groups .")) ";
      }
    }

    function get_weekday_start($year,$month,$day) {
      global $phpgw;
      global $phpgw_info;

      $weekday = date("w",mktime(0,0,0,$month,$day,$year));
      if ($phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"] == "Monday") {
        $this->days = array(0 => "Mon", 1 => "Tue", 2 => "Wed", 3 => "Thu", 4 => "Fri", 5 => "Sat", 6 => "Sun");
        if ($weekday == 0)
          $sb = mktime(0,0,0,$month,$day - 6,$year);
        if ($weekday == 1)
          $sb = mktime(0,0,0,$month,$day,$year);
        $sb = mktime(0,0,0,$month,$day - ($weekday - 1),$year);
      } else {
         $this->days = array(0 => "Sun", 1 => "Mon", 2 => "Tue", 3 => "Wed", 4 => "Thu", 5 => "Fri", 6 => "Sat");
         $sb = mktime(0,0,0,$month,$day - $weekday,$year);
      }

      return $sb;
//       - ((60 * 60) * intval($phpgw_info["user"]["preferences"]["common"]["tz_offset"]));
    }

    function normalizeminutes(&$minutes) {
      $hour = 0;
      $min = intval($minutes);
      if($min >= 60) {
	$hour += $min / 60;
	$min %= 60;
      }
      settype($minutes,"integer");
      $minutes = $min;
      return $hour;
    }

    function addduration($hour,$minute,$ampm,$duration) {
      $minute += $duration;
      return $this->fixtime($hour,$minute,$ampm);
    }

    function fixtime($hour=0,$minute=0,$ampm="") {
      global $phpgw_info;

      $hour += (int)$this->normalizeminutes(&$minute);
      if ($hour > 0) {
         if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
//            $hour %= 12;
            if (strtolower($ampm) == "pm" && $hour <> 12) {
               $hour += 12;
            }
         }
      }
      return ($hour * 10000) + ($minute * 100);
    }

    function splittime_($time)
    {
      global $phpgw_info;

      $temp = array("hour","minute","second","ampm");
      $time = strrev($time);
      $second = (int)strrev(substr($time,0,2));
      $minute = (int)strrev(substr($time,2,2));
      $hour   = (int)strrev(substr($time,4));
      $temp["second"] = (int)$second;
      $temp["minute"] = (int)$minute;
      $temp["hour"]   = (int)$hour;
      $temp["ampm"]   = "  ";

      return $temp;
    }

    function splittime($time) {
      global $phpgw_info;

      $temp = array("hour","minute","second","ampm");
      $time = strrev($time);
      $second = intval(strrev(substr($time,0,2)));
      $minute = intval(strrev(substr($time,2,2)));
      $hour   = intval(strrev(substr($time,4)));
      $hour += $this->normalizeminutes(&$minute);
      $temp["second"] = $second;
      $temp["minute"] = $minute;
      $temp["hour"]   = $hour;
      $temp["ampm"]   = "  ";
      if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "24") {
         return $temp;
      }
      $temp["ampm"] = "am";
      if ((int)$temp["hour"] > 12) {
     	$temp["hour"] = (int)((int)$temp["hour"] - 12);
     	$temp["ampm"] = "pm";
      } elseif ((int)$temp["hour"] == 12) {
	$temp["ampm"] = "pm";
      }
      return $temp;
    }

    function makegmttime($hour,$minute,$second,$month,$day,$year) {
      global $phpgw;
      global $phpgw_info;

      return $this->gmtdate(mktime($hour, $minute, $second, $month, $day, $year));
    }

    function localdates($localtime) {
      global $phpgw;
      global $phpgw_info;

      $date = Array("raw","day","month","year","full","dow","dm");
      $date["raw"] = $localtime;
      $date["year"] = intval($phpgw->common->show_date($date["raw"],"Y"));
      $date["month"] = intval($phpgw->common->show_date($date["raw"],"m"));
      $date["day"] = intval($phpgw->common->show_date($date["raw"],"d"));
      $date["full"] = intval($phpgw->common->show_date($date["raw"],"Ymd"));
      $date["dm"] = intval($phpgw->common->show_date($date["raw"],"dm"));
      $date["dow"] = intval($phpgw->common->show_date($date["raw"],"w"));
      $date["hour"] = intval($phpgw->common->show_date($date["raw"],"H"));
      $date["minute"] = intval($phpgw->common->show_date($date["raw"],"i"));
      return $date;
    }

    function gmtdate($localtime) {
      global $phpgw_info;
      
      $localtime -= ((60 * 60) * intval($phpgw_info["user"]["preferences"]["common"]["tz_offset"]));
      return $this->localdates($localtime);
    }

    function splitdate($date) {
      $temp = array("day","month","year","full","raw","dayofweek");
      $temp["raw"] = intval($date);
      $temp["day"] = intval(date("d",(int)$date));
      $temp["month"] = intval(date("m",(int)$date));
      $temp["year"] = intval(date("Y",(int)$date));
      $temp["full"] = intval(date("Ymd",(int)$date));
      $temp["dayofweek"] = intval(date("w",(int)$date));
      return $temp;
    }

    function date_to_epoch($d) {
      return $this->splitdate(mktime(0,0,0,intval(substr($d,4,2)),intval(substr($d,6,2)),intval(substr($d,0,4))));
    }

    function overlap($starttime,$endtime,$participants,$groups,$owner=0,$id=0) {
      global $phpgw;
      global $phpgw_info;

      $retval = Array();
      $ok = False;

      $starttime -= ((60 * 60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);
      $endtime -= ((60 * 60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);

      if($starttime == $endtime) $endtime = mktime($phpgw->common->show_date($starttime,"H"),$phpgw->common->show_date($starttime,"i"),0,$phpgw->common->show_date($starttime,"m"),$phpgw->common->show_date($starttime,"d") + 1,$phpgw->common->show_date($starttime,"Y")) - ((60*60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]) - 1;

      $sql = "SELECT DISTINCT calendar_entry.cal_id "
	   . "FROM calendar_entry, calendar_entry_user "
	   . "WHERE (calendar_entry_user.cal_id = calendar_entry.cal_id) AND "
	   . "  (((".$starttime." <= calendar_entry.cal_datetime) AND (".$endtime." >= calendar_entry.cal_datetime) AND (".$endtime." <= calendar_entry.cal_edatetime)) "
	   . "OR ((".$starttime." >= calendar_entry.cal_datetime) AND (".$starttime." <= calendar_entry.cal_edatetime) AND (".$endtime." >= calendar_entry.cal_edatetime)) "
	   . "OR ((".$starttime." <= calendar_entry.cal_datetime) AND (".$endtime." >= calendar_entry.cal_edatetime)) "
	   . "OR ((".$starttime." >= calendar_entry.cal_datetime) AND (".$endtime." <= calendar_entry.cal_edatetime))) ";

      if(count($participants) || is_array($groups)) {
        $p_g = "";
        if(count($participants)) {
          $p_g .= "(";
          for($i=0;$i<count($participants);$i++) {
            if($i) $p_g .= " OR ";
              $p_g .= "calendar_entry_user.cal_login=".$participants[$i];
          }
          $p_g .= ") ";
        }
        $group = $this->group_search($owner);
        if ($group) {
          if ($p_g) $p_g .= "OR ";
            $p_g .= $group;
        }
        if($p_g) $sql .= " AND (" . $p_g . ")";
      }
      
      if($id) $sql .= " AND calendar_entry.cal_id <> ".$id;

      $db2 = $phpgw->db;

      $phpgw->db->query($sql,__LINE__,__FILE__);
      if(!$phpgw->db->num_rows()) return false;
      while($phpgw->db->next_record()) {
        $cal_id  = intval($phpgw->db->f("cal_id"));
        $db2->query('SELECT cal_type FROM calendar_entry_repeats WHERE cal_id='.$cal_id,__LINE__,__FILE__);
        if(!$db2->num_rows()) {
          $retval[] = $cal_id;
          $ok = True;
        } else {
          if($db2->f("cal_type") <> 'monthlyByDay') {
            $retval[] = $cal_id;
            $ok = True;
          }          
        }
      }
      if($ok) return $retval; else return False;
    }

    function is_private($cal_info,$owner) {
      global $phpgw;
      global $phpgw_info;

      $is_private  = False;
      if ($owner == $phpgw_info["user"]["account_id"] || $owner == 0) {
      } elseif ($cal_info->access == "private") {
	$is_private = True;
      } elseif($cal_info->access == "group") {
	$is_private = True;
	$phpgw->db->query("SELECT account_lid FROM accounts WHERE account_id=".$owner,__LINE__,__FILE__);
	$phpgw->db->next_record();
	$groups = $phpgw->accounts->read_groups($phpgw->db->f("account_lid"));
	while ($group = each($groups)) {
	  if (strpos(" ".$cal_info->groups." ",",".$group[0]).",") $is_private = False;
	}
      }
      if ($is_private) {
	$str = "private";
      } elseif (strlen($cal_info->name) > 19) {
	$str = substr($cal_info->name, 0 , 19);
	$str .= "...";
      } else {
	$str = $cal_info->name;
      }
      return $str;
    }

    function timematrix($date,$starttime,$endtime,$participants) {
      global $phpgw;
      global $phpgw_info;

      if(!isset($phpgw_info["user"]["preferences"]["calendar"]["interval"]) ||
	      !$phpgw_info["user"]["preferences"]["calendar"]["interval"]) {
	$phpgw_info["user"]["preferences"]["calendar"]["interval"] = 15;
      }
      $datetime = $this->gmtdate($date["raw"]);
      $increment = $phpgw_info["user"]["preferences"]["calendar"]["interval"];
      $interval = (int)(60 / $increment);

      $str = "<center>".$phpgw->common->show_date($datetime["raw"],"l, F d, Y")."<br>";
      $str .= "<table width=\"85%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" cols=\"".((24 * $interval) + 1)."\">";
      $str .= "<tr><td height=\"1\" colspan=\"".((24 * $interval) + 1)."\" bgcolor=\"black\"><img src=\"".$phpgw_info["server"]["app_images"]."/pix.gif\"></td></tr>";
      $str .= "<tr><td width=\"15%\">Participant</td>";
      for($i=0;$i<24;$i++) {
	for($j=0;$j<$interval;$j++)
	  switch($j) {
	    case 0:
	      if($interval == 4) {
		$k = ($i<=9?"0":substr($i,0,1));
	      }
	      $str .= "<td align=\"right\" bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\"><font color=\"".$phpgw_info["theme"]["bg_text"]."\">";
	      $str .= "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/edit_entry.php","year=".$datetime["year"]."&month=".$datetime["month"]."&day=".$datetime["day"]."&hour=".$i."&minute=".(interval * $j))."\" onMouseOver=\"window.status='".$i.":".($increment * $j<=9?"0":"").($increment * $j)."'; return true;\">";
	      $str .= $k."</a></font></td>";
	      break;
	    case 1:
	      if($interval == 4) {
		$k = ($i<=9?substr($i,0,1):substr($i,1,2));
	      }
	      $str .= "<td align=\"right\" bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\"><font color=\"".$phpgw_info["theme"]["bg_text"]."\">";
	      $str .= "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/edit_entry.php","year=".$datetime["year"]."&month=".$datetime["month"]."&day=".$datetime["day"]."&hour=".$i."&minute=".(interval * $j))."\" onMouseOver=\"window.status='".$i.":".($increment * $j)."'; return true;\">";
	      $str .= $k."</a></font></td>";
	      break;
	    default:
	      $str .= "<td align=\"left\" bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\"><font color=\"".$phpgw_info["theme"]["bg_text"]."\">";
	      $str .= "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/edit_entry.php","year=".$datetime["year"]."&month=".$datetime["month"]."&day=".$datetime["day"]."&hour=".$i."&minute=".(interval * $j))."\" onMouseOver=\"window.status='".$i.":".($increment * $j)."'; return true;\">";
	      $str .= "&nbsp</a></font></td>";
	      break;
	  }
      }
      $str .= "</tr>";
      $str .= "<tr><td height=\"1\" colspan=\"".((24 * $interval) + 1)."\" bgcolor=\"black\"><img src=\"".$phpgw_info["server"]["app_images"]."/pix.gif\"></td></tr>";
      if(!$endtime) $endtime = $starttime;
//      $endtime = $this->splittime_($this->addduration(intval($starttime["hour"]),intval($starttime["minute"]),$starttime["ampm"],$duration));

      for($i=0;$i<count($participants);$i++) {
	$this->read_repeated_events($participants[$i]);
	$str .= "<tr>";
	$str .= "<td width=\"15%\">".$phpgw->common->grab_owner_name($participants[$i])."</td>";
	$events = $this->get_sorted_by_date($datetime["raw"],$participants[$i]);
	if(!$this->sorted_re) {
	  for($j=0;$j<24;$j++) {
	    for($k=0;$k<$interval;$k++) {
	      $str .= "<td height=\"1\" align=\"left\" bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\" color=\"#999999\">&nbsp;</td>";
	    }
	  }
	} else {
	  for($h=0;$h<24;$h++) {
	    for($m=0;$m<$interval;$m++) {
	      $index = (($h * 10000) + (($m * $increment) * 100));
	      $time_slice[$index]["marker"] = "&nbsp";
	      $time_slice[$index]["color"] = $phpgw_info["theme"]["bg_color"];
	      $time_slice[$index]["description"] = "";
	    }
	  }
	  for($k=0;$k<$this->sorted_re;$k++) {
	    $event = $events[$k];
	    $eventstart = $this->localdates($event->datetime);
	    $eventend = $this->localdates($event->edatetime);
	    $start = ($eventstart["hour"] * 10000) + ($eventstart["minute"] * 100);
	    $starttemp = $this->splittime("$start");
	    $subminute = 0;
	    for($m=0;$m<$interval;$m++) {
	      $minutes = $increment * $m;
	      if(intval($starttemp["minute"]) > $minutes && intval($starttemp["minute"]) < ($minutes + $increment)) {
		$subminute = ($starttemp["minute"] - $minutes) * 100;
	      }
	    }
	    $start -= $subminute;
	    $end =  ($eventend["hour"] * 10000) + ($eventend["minute"] * 100);
	    $endtemp = $this->splittime("$end");
	    $addminute = 0;
	    for($m=0;$m<$interval;$m++) {
	      $minutes = ($increment * $m);
	      if($endtemp["minute"] < ($minutes + $increment) && $endtemp["minute"] > $minutes) {
		$addminute = ($minutes + $increment - $endtemp["minute"]) * 100;
	      }
	    }
	    $end += $addminute;
	    $starttemp = $this->splittime("$start");
	    $endtemp = $this->splittime("$end");
// Do not display All-Day events in this free/busy time
	    if((($starttemp["hour"] == 0) && ($starttemp["minute"] == 0)) && (($endtemp["hour"] == 23) && ($endtemp["minute"] == 59))) {
	    } else {
	      for($h=$starttemp["hour"];$h<=$endtemp["hour"];$h++) {
		$startminute = 0;
		$endminute = $interval;
		$hour = $h * 10000;
		if($h == intval($starttemp["hour"]))
		  $startminute = ($starttemp["minute"] / $increment);
		if($h == intval($endtemp["hour"]))
		  $endminute = ($endtemp["minute"] / $increment);
		for($m=$startminute;$m<=$endminute;$m++) {
	          $index = ($hour + (($m * $increment) * 100));
	          $time_slice[$index]["marker"] = "-";
	          $time_slice[$index]["color"] = $phpgw_info["theme"]["bg01"];
		  $time_slice[$index]["description"] = $this->is_private($event,$participants[$i]);
	        }
	      }
	    }
	  }
	  for($h=0;$h<24;$h++) {
	    $hour = $h * 10000;
	    for($m=0;$m<$interval;$m++) {
	      $index = ($hour + (($m * $increment) * 100));
	      $str .= "<td height=\"1\" align=\"left\" bgcolor=\"".$time_slice[$index]["color"]."\" color=\"#999999\"  onMouseOver=\"window.status='".$time_slice[$index]["description"]."'; return true;\">".$time_slice[$index]["marker"]."</td>";
	    }
	  }	  
	} 
	$str .= "</tr>";
	$str .= "<tr><td height=\"1\" colspan=\"".((24 * $interval) + 1)."\" bgcolor=\"#999999\"><img src=\"".$phpgw_info["server"]["app_images"]."/pix.gif\"></td></tr>";
      }
      $str .= "</table></center>";
      return $str;
    }      

    // The orginal patch read this 30+ times in a loop, only read it once.
    function read_repeated_events($owner=0) {
      global $phpgw;
      global $phpgw_info;

      $this->re = 0;
      $this->set_filter();
      $owner = !$owner?$phpgw_info["user"]["account_id"]:$owner;
      $sql = "SELECT calendar_entry.cal_id "
	   . "FROM calendar_entry, calendar_entry_repeats, calendar_entry_user "
	   . "WHERE calendar_entry.cal_id=calendar_entry_repeats.cal_id AND "
	   . "calendar_entry.cal_id = calendar_entry_user.cal_id AND calendar_entry.cal_type='M' AND ";
      $sqlfilter="";
// Private
      if($this->filter==" all " || strpos($this->filter,"private")) {
	$sqlfilter .= "(calendar_entry_user.cal_login=".$owner." AND calendar_entry.cal_access='private') ";
      }

// Group Public
      if($this->filter==" all " || strpos($this->filter,"group")) {
	if($sqlfilter)
	  $sqlfilter .= "OR ";
	$sqlfilter .= "(calendar_entry_user.cal_login=".$owner." OR ".$this->group_search($owner).") ";
      }

// Global Public
      if($this->filter==" all " || strpos($this->filter,"public")) {
	if($sqlfilter)
	  $sqlfilter .= "OR ";
	$sqlfilter .= "calendar_entry.cal_access='public' ";
      }
      $orderby = " ORDER BY calendar_entry.cal_datetime ASC, calendar_entry.cal_edatetime ASC, calendar_entry.cal_priority ASC";

      $db2 = $phpgw->db;

      if($sqlfilter) $sql .= "(".$sqlfilter.") ";
      $sql .= $orderby;

      $db2->query($sql,__LINE__,__FILE__);

      $i = 0;
      if($db2->num_rows()) {
	while ($db2->next_record()) {
	  $repeated_event_id[$i++] = (int)$db2->f("cal_id");
	}
	$this->re = $i;
	$this->repeated_events = $this->getevent($repeated_event_id);
      } else {
	$this->repeated_events = Null;
      }
    }

    function link_to_entry($id, $pic, $description) {
      global $phpgw;
      global $phpgw_info;

      $str = "";
      if (!$this->printer_friendly) {
        $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
        $p->set_unknowns("remove");
        $p->set_file(array('link_pict' => 'link_pict.tpl'));
        $p->set_block('link_pict','link_pict');
        $p->set_var('link_link',$phpgw->link($phpgw_info["server"]["webserver_url"].'/calendar/view.php','id='.$id));
        $p->set_var('lang_view',lang('View this entry'));
        $p->set_var('pic_image',$phpgw->common->get_image_path('calendar').'/'.$pic);
        $p->set_var('description',$description);
        $str = $p->finish($p->parse('out','link_pict'));
      }
      return $str;
    }

    function build_time_for_display($fixed_time) {
      global $phpgw_info;
//      echo "<br>before: $fixed_time";
      $time = $this->splittime($fixed_time);
//      echo "<br>test -> build_time_for_display () in if " . $time["hour"] . " " . $time["ampm"];
//      echo "<br>&nbsp;&nbsp;$fixed_time";
      $str = "";
      $str .= $time["hour"].":".((int)$time["minute"]<=9?"0":"").$time["minute"];
      if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
         $str .= " " . $time["ampm"];
      }
      return $str;
    }

    function check_repeating_entries($datetime) {
      global $phpgw;
      global $phpgw_info;

      $this->checked_re = 0;
      if(!$this->re) return False;
      $link = Array();
//      $date = $this->gmtdate($datetime);
      $date = $this->localdates($datetime);
      for ($i=0;$i<$this->re;$i++) {
        $rep_events = $this->repeated_events[$i];
        $start = $this->localdates($rep_events->datetime);
        if($rep_events->rpt_use_end)
          $enddate = $this->gmtdate($rep_events->rpt_end);
        else
          $enddate   = $this->makegmttime(0,0,0,1,1,2007);
        // only repeat after the beginning, and if there is an rpt_end before the end date
        if ($rep_events->rpt_use_end && ($date["full"] > $enddate["full"])) {
          continue;
        }

        if ($date["full"] <= $start["full"]) {
          continue;
        }

        if ($date["full"] == $start["full"]) {
          $link[$this->checked_re] = $i;
          $this->checked_re++;
        } elseif ($rep_events->rpt_type == 'daily') {
          if (floor(($date["raw"] - $start["raw"])/86400) % intval($rep_events->rpt_freq)) {
            continue;
          }
          $link[$this->checked_re] = $i;
          $this->checked_re++;
        } elseif ($rep_events->rpt_type == 'weekly') {
          $isDay = strtoupper(substr($rep_events->rpt_days, $date["dow"], 1));

          /*if ( (floor($diff/86400) % $this->rep_events->rpt_freq) ) // Whats this for ?
           **   continue;
          */
          
          if (floor(($date["raw"] - $start["raw"])/604800) % intval($rep_events->rpt_freq)) {
            continue;
          }
          if (strcmp($isDay,"Y") == 0) {
            $link[$this->checked_re] = $i;
            $this->checked_re++;
          }
        } elseif ($rep_events->rpt_type == 'monthlybyday') {
          if ((($date["year"] - $start["year"]) * 12 + $date["month"] - $start["month"]) % intval($rep_events->rpt_freq)) {
            continue;
          }
	  
          if (($start["dow"] == $date["dow"]) && 
              (ceil($start["day"]/7) == ceil($date["day"]/7))) {
            $link[$this->checked_re] = $i;
            $this->checked_re++;
          }
        } elseif ($rep_events->rpt_type == 'monthlybydate') {
          if ((($date["year"] - $start["year"]) * 12 + $date["month"] - $start["month"]) % intval($rep_events->rpt_freq)) {
            continue;
          }
          if ($date["day"] == $start["day"]) {
            $link[$this->checked_re] = $i;
            $this->checked_re++;
          }
        } elseif ($rep_events->rpt_type == 'yearly') {
          if (($date["year"] - $start["year"]) % intval($rep_events->rpt_freq)) {
            continue;
          }
          if ($date["dm"] == $start["dm"]) {
            $link[$this->checked_re] = $i;
            $this->checked_re++;
          }
        } else {
          // unknown rpt type - because of all our else ifs
        }
      }	// end for loop

      if($this->checked_re) {
        return $link;
      } else {
        return False;
      }
    }	// end function

    function get_sorted_by_date($datetime,$owner=0) {
      global $phpgw;
      global $phpgw_info;

      $this->sorted_re = 0;
      $this->set_filter();
      $owner = !$owner?$phpgw_info["user"]["account_id"]:$owner;
      $rep_event = $this->check_repeating_entries($datetime);
      $sql = "SELECT DISTINCT calendar_entry.cal_id, calendar_entry.cal_datetime, "
	   . "calendar_entry.cal_edatetime, calendar_entry.cal_priority "
	   . "FROM calendar_entry, calendar_entry_user "
	   . "WHERE ((calendar_entry.cal_datetime >= ".$datetime." AND calendar_entry.cal_datetime <= ".($datetime + 86399).") OR "
	   . "(calendar_entry.cal_datetime <= ".$datetime." AND calendar_entry.cal_edatetime >= ".($datetime + 86399).") OR "
	   . "(calendar_entry.cal_edatetime >= ".$datetime." AND calendar_entry.cal_edatetime <= ".($datetime + 86399).")) AND "
	   . "calendar_entry_user.cal_id=calendar_entry.cal_id AND calendar_entry.cal_type != 'M' AND ";
      $sqlfilter = "";
// Private
      if($this->filter==" all " || strpos($this->filter,"private")) {
	$sqlfilter .= "(calendar_entry_user.cal_login = ".$owner." AND calendar_entry.cal_access='private') ";
      }

// Group Public
      if($this->filter==" all " || strpos($this->filter,"group")) {
	if($sqlfilter)
	  $sqlfilter .= "OR ";
	$sqlfilter .= $this->group_search($owner)." ";
      }

// Global Public
      if($this->filter==" all " || strpos($this->filter,"public")) {
	if($sqlfilter)
	  $sqlfilter .= "OR ";
	$sqlfilter .= "calendar_entry.cal_access='public' ";
      }
      $orderby = " ORDER BY calendar_entry.cal_datetime ASC, calendar_entry.cal_edatetime ASC, calendar_entry.cal_priority ASC";

      $db2 = $phpgw->db;

      if($sqlfilter) $sql .= "(".$sqlfilter.") ";
      $sql .= $orderby;

      $db2->query($sql,__LINE__,__FILE__);

      $events = Null;
      $rep_events = Array();
      if($db2->num_rows()) {
	while($db2->next_record()) {
	  $rep_events[$this->sorted_re++] = (int)$db2->f(0);
	}
	$events = $this->getevent($rep_events);
      } else
	$events = Array(CreateObject('calendar.calendar_item'));

      if(!$this->checked_re && !$this->sorted_re) return False;

      $e = CreateObject('calendar.calendar_item');
      for ($j=0;$j<$this->checked_re;$j++) {
	$e = $this->repeated_events[$rep_event[$j]];
	$events[$this->sorted_re++] = $e;
      }
      if(!$this->sorted_re) return False;
      if($this->sorted_re == 1) return $events;
      for($outer_loop=0;$outer_loop<$this->sorted_re - 1;$outer_loop++) {
	$outer = $events[$outer_loop];
	for($inner_loop=$outer_loop;$inner_loop<$this->sorted_re;$inner_loop++) {
	  $inner = $events[$inner_loop];
	  if(($outer->datetime > $inner->datetime) || (($outer->datetime == $inner->datetime) && ($outer->edatetime > $inner->edatetime))) {
	    $temp = $events[$inner_loop];
	    $events[$inner_loop] = $events[$outer_loop];
	    $events[$outer_loop] = $temp;
	  }
	}
      }
      
      if(isset($events)) return $events; else return False;
    }

    function large_month_header($month,$year,$display_name = False) {
      global $phpgw;
      global $phpgw_info;

      $this->weekstarttime = $this->get_weekday_start($year,$month,1);

	  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
//	  $p->halt_on_error("report");
	  $p->set_unknowns("remove");
      $p->set_file(array('month_header' => 'month_header.tpl',
      				     'column_title' => 'column_title.tpl'));
      $p->set_block('month_header','month_header');
      $p->set_block('column_title','column_title');

      $p->set_var(array('bgcolor' => $phpgw_info["theme"]["th_bg"],
                        'font_color' => $phpgw_info["theme"]["th_text"]));
      if($display_name) {
        $p->set_var('col_title',lang("name"));
        $p->parse('column_header','column_title',True);
      }

      for($i=0;$i<$this->daysinweek;$i++) {
        $p->set_var('col_title',lang($this->days[$i]));
        $p->parse('column_header','column_title',True);
      }
      return $p->finish($p->parse('out','month_header'));
    }

    function display_week($startdate,$weekly,$cellcolor,$display_name = False,$owner=0,$monthstart=0,$monthend=0) {
      global $phpgw;
      global $phpgw_info;

      $str = "";
      $gr_events = CreateObject('calendar.calendar_item');
      $lr_events = CreateObject('calendar.calendar_item');

	  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
	  $p->set_unknowns("remove");
      $p->set_file(array('month_header' => 'month_header.tpl',
      				     'month_column' => 'month_column.tpl',
      				     'month_day' => 'month_day.tpl',
      				     'week_day_event' => 'week_day_event.tpl',
      				     'week_day_events' => 'week_day_events.tpl'));
      $p->set_block('month_header','month_header');
      $p->set_block('month_column','month_column');
      $p->set_block('month_day','month_day');
      $p->set_block('week_day_event','week_day_event');
      $p->set_block('week_day_events','week_day_events');

      $p->set_var('extra','');
      if($display_name) {
        $p->set_var('column_data',$phpgw->common->grab_owner_name($owner));
        $p->parse('column_header','month_column',True);
      }
      for ($j=0;$j<$this->daysinweek;$j++) {
        $date = $this->gmtdate($startdate + ($j * 24 * 3600));
        $p->set_var('column_data','');
        $p->set_var('extra','');
        if ($weekly || ($date["full"] >= $monthstart && $date["full"] <= $monthend)) {
          if($weekly) $cellcolor = $phpgw->nextmatchs->alternate_row_color($cellcolor);
          if ($date["full"] == $this->today["full"]) {
            $p->set_var('extra',' bgcolor="'.$phpgw_info["theme"]["cal_today"].'"');
          } else {
            $p->set_var('extra',' bgcolor="'.$cellcolor.'"');
          }

          if (!$this->printer_friendly) {
            $str = '<a href="'.$phpgw->link($phpgw_info["server"]["webserver_url"].'/calendar/edit_entry.php','year='.$date_year.'&month='.$date["month"].'&day='.$date["day"]).'">'
                 . '<img src="'.$phpgw->common->get_image_path('calendar').'/new.gif" width="10" height="10" alt="'.lang('New Entry').'" border="0" align="right"></a>';
            $p->set_var('new_event_link',$str);
            $str = '<a href="'.$phpgw->link($phpgw_info["server"]["webserver_url"].'/calendar/day.php','month='.$date["month"].'&day='.$date["day"].'&year='.$date["year"]).'">'.$date["day"].'</a>';
            $p->set_var('day_number',$str);
          } else {
            $p->set_var('new_event_link','');
            $p->set_var('day_number',$date["day"]);
          }
          $p->parse('column_data','month_day',True);

          $rep_events = $this->get_sorted_by_date($date["raw"],$owner);

          if ($this->sorted_re) {
            $lr_events = CreateObject('calendar.calendar_item');
            $p->set_var('week_day_font_size','2');
            $p->set_var('events','');
            for ($k=0;$k<$this->sorted_re;$k++) {
              $lr_events = $rep_events[$k];
              $pict = "circle.gif";
              for ($outer_loop=0;$outer_loop<$this->re;$outer_loop++) {
                $gr_events = $this->repeated_events[$outer_loop];
                if ($gr_events->id == $lr_events->id) {
                  $pict = "rpt.gif";
                }
              }
              $p->set_var('link_entry',$this->link_to_entry($lr_events->id, $pict, $lr_events->description));
              if (intval($phpgw->common->show_date($lr_events->datetime,"Hi"))) {
                if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
                  $format = "h:i a";
                } else {
                  $format = "H:i";
                }
                if($lr_events->datetime < $date["raw"] && $lr_events->rpt_type=="none") {
                  $temp_time = $this->makegmttime(0,0,0,$date["month"],$date["day"],$date["year"]);
                  $start_time = $phpgw->common->show_date($temp_time["raw"],$format);
                } else {
                  $start_time = $phpgw->common->show_date($lr_events->datetime,$format);
                }
                
                if($lr_events->edatetime > ($date["raw"] + 86400)) {
                  $temp_time = $this->makegmttime(23,59,59,$date["month"],$date["day"],$date["year"]);
                  $end_time = $phpgw->common->show_date($temp_time["raw"],$format);
                } else {
                  $end_time = $phpgw->common->show_date($lr_events->edatetime,$format);
                }
                $p->set_var('start_time',$start_time);
                $p->set_var('end_time',$end_time);
              } else {
                $p->set_var('start_time','');
                $p->set_var('end_time','');
              }
              $p->set_var('name',$this->is_private($lr_events,$owner));
              $p->parse('events','week_day_event',True);
            }
          }
          $p->parse('column_data','week_day_events',True);
          $p->set_var('events','');
          if (!$j || ($j && $date["full"] == $monthstart)) {
            $p->set_var('week_day_font_size','-2');
            if(!$this->printer_friendly) {
              $str = "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/week.php","date=".$date["full"])."\">week " .(int)((date("z",($startdate+(24*3600*4)))+7)/7)."</a>";
            } else {
              $str = "week " .(int)((date("z",($startdate+(24*3600*4)))+7)/7);
            }
            $p->set_var('events',$str);
            $p->parse('column_data','week_day_events',True);
            $p->set_var('events','');
          }
        }
        $p->parse('column_header','month_column',True);
        $p->set_var('column_data','');
      }
      return $p->finish($p->parse('out','month_header'));
    }

    function display_large_month($month,$year,$showyear,$owner=0) {
      global $phpgw, $phpgw_info;

      if($owner == $phpgw_info["user"]["account_id"]) $owner = 0;
      $this->read_repeated_events($owner);

	  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
	  $p->set_unknowns("remove");
      $p->set_file(array('month' => 'month.tpl',
                         'month_filler' => 'month_filler.tpl',
                         'month_header' => 'month_header.tpl'));
      $p->set_block('month','month');
      $p->set_block('month_filler','month_filler');
      $p->set_block('month_header','month_header');

      $p->set_var('month_filler_text',$this->large_month_header($month,$year,False));
      $p->parse('row','month_filler',True);

      $monthstart = intval(date("Ymd",mktime(0,0,0,$month    ,1,$year)));
      $monthend   = intval(date("Ymd",mktime(0,0,0,$month + 1,0,$year)));

      $today = $this->splitdate(time());

      $cellcolor = $phpgw_info["theme"]["row_on"];

      for ($i=$this->weekstarttime;intval(date("Ymd",$i))<=$monthend;$i += (24 * 3600 * 7)) {
         $cellcolor = $phpgw->nextmatchs->alternate_row_color($cellcolor);
         $p->set_var('month_filler_text',$this->display_week($i,False,$cellcolor,False,$owner,$monthstart,$monthend));
         $p->parse('row','month_filler',True);
      }
      return $p->finish($p->parse('out','month'));
    }

    function display_large_week($day,$month,$year,$showyear,$owners=0) {
      global $phpgw;
      global $phpgw_info;

	  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
	  $p->set_unknowns("remove");
      $p->set_file(array('month' => 'month.tpl',
                         'month_filler' => 'month_filler.tpl',
                         'month_header' => 'month_header.tpl'));
      $p->set_block('month','month');
      $p->set_block('month_filler','month_filler');
      $p->set_block('month_header','month_header');

      $start = $this->get_weekday_start($year, $month, $day);

      $cellcolor = $phpgw_info["theme"]["row_off"];

//      if ($phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"] == "Monday") {
//         $start += 86400;
//      }

//      $str  = "";

      $true_printer_friendly = $this->printer_friendly;

      if(is_array($owners)) {
        $display_name = True;
        $counter = count($owners);
        $owners_array = $owners;
      } else {
        $display_name = False;
        $counter = 1;
        $owners_array[0] = $owners;
      }
      $p->set_var('month_filler_text',$this->large_month_header($month,$year,$display_name));
      $p->parse('row','month_filler',True);

      for($i=0;$i<$counter;$i++) {
        $this->repeated_events = Null;
        $owner = $owners_array[$i];
        if($owner <> $phpgw_info["user"]["account_id"] && $owner <> 0) {
          $this->printer_friendly = True;
        } else {
          $this->printer_friendly = $true_printer_friendly;
        }
        $this->read_repeated_events($owner);
        $p->set_var('month_filler_text',$this->display_week($start,True,$cellcolor,$display_name,$owner));
        $p->parse('row','month_filler',True);
      }
      $this->printer_friendly = $true_printer_friendly;
      return $p->finish($p->parse('out','month'));
    }

    function pretty_small_calendar($day,$month,$year,$link="") {
      global $phpgw, $phpgw_info, $view;

//      $tz_offset = (-1 * ((60 * 60) * intval($phpgw_info["user"]["preferences"]["common"]["tz_offset"])));
      $date = $this->makegmttime(0,0,0,$month,$day,$year);
      $month_ago = intval(date("Ymd",mktime(0,0,0,$month - 1,$day,$year)));
      $month_ahead = intval(date("Ymd",mktime(0,0,0,$month + 1,$day,$year)));
      $monthstart = intval(date("Ymd",mktime(0,0,0,$month,1,$year)));
      $monthend = intval(date("Ymd",mktime(0,0,0,$month + 1,0,$year)));

      $weekstarttime = $this->get_weekday_start($year,$month,1);

      $str  = "";
      $str .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" valign=\"top\">";
      $str .= "<tr valign=\"top\">";
      $str .= "<td bgcolor=\"".$phpgw_info["theme"]["bg_text"]."\">";
      $str .= "<table border=\"0\" width=\"100%\" cellspacing=\"1\" cellpadding=\"2\" border=\"0\" valign=\"top\">";
      if ($view == "day") {
         $str .= "<tr><th colspan=\"7\" bgcolor=\"".$phpgw_info["theme"]["th_bg"]."\"><font size=\"+4\" color=\"".$phpgw_info["theme"]["th_text"]."\">".$day."</font></th></tr>";
      }
      $str .= "<tr>";

      if ($view == "year") {
         $str .= '<td align="center" colspan="7" bgcolor="' . $phpgw_info["theme"]["th_bg"] . '">';
      } else {
         $str .= '<td align="left" bgcolor="' . $phpgw_info["theme"]["th_bg"] .'">';
      }

      if ($view != "year") {
        if (!$this->printer_friendly) {
	  $str .= "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/day.php","date=".$month_ago)."\" class=\"monthlink\">";
        }
        $str .= "&lt;";
        if (!$this->printer_friendly) $str .= "</a>";
        $str .= "</td>";
        $str .= "<th colspan=\"5\" bgcolor=\"".$phpgw_info["theme"]["th_bg"]."\"><font color=\"".$phpgw_info["theme"]["th_text"]."\">";
      }
      if (!$this->printer_friendly) {
     	$str .= "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/index.php","year=".$year."&month=".$month)."\">";
      }
      $str .= lang($phpgw->common->show_date($date["raw"],"F"))." ".$year;
      if(!$this->printer_friendly) $str .= "</a>";
      if ($view != "year") {
	$str .= "</font></th>";
      }

      if ($view != "year") {
	$str .= "<td align=\"right\" bgcolor=\"".$phpgw_info["theme"]["th_bg"]."\">";
	if (!$this->printer_friendly) {
	  $str .= "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/day.php","date=".$month_ahead)."\" class=\"monthlink\">";
	}
	$str .= "&gt;";
	if (!$this->printer_friendly) {
	  $str .= "</a>";
	}
	$str .= "</td>";
      }
      $str .= "</tr>";
      $str .= "<tr>";
      for($i=0;$i<7;$i++) {
	$str .= "<td bgcolor=\"".$phpgw_info["theme"]["cal_dayview"]."\"><font size=\"-2\">".substr(lang($days[$i]),0,2)."</td>";
      }
      $str .= "</tr>";
      for($i=$weekstarttime;date("Ymd",$i)<=$monthend;$i += (24 * 3600 * 7)) {
	$str .= "<tr>";
	for($j=0;$j<7;$j++) {
	  $cal = $this->gmtdate($i + ($j * 24 * 3600));
	  if($cal["full"] >= $monthstart && $cal["full"] <= $monthend) {
	    $str .= "<td align=\"center\" bgcolor=\"";
	    if($cal["full"] == $this->today["full"]) {
	      $str .= $phpgw_info["theme"]["cal_today"];
	    } else {
	      $str .= $phpgw_info["theme"]["cal_dayview"];
	    }
	    $str .= "\"><font size=\"-2\">";

	    if(!$this->printer_friendly) {
	      $str .= "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/".$link,"year=".$cal["year"]."&month=".$cal["month"]."&day=".$cal["day"])."\" class=\"monthlink\">";
	    }
	    $str .= $cal["day"];
	    if(!$this->printer_friendly) $str .= "</a>";
	    $str .= "</font></td>";
	  } else {
	    $str .= '<td bgcolor="' . $phpgw_info["theme"]["cal_dayview"] 
	          . '"><font size="-2" color="' . $phpgw_info["theme"]["cal_dayview"] . '">.</font></td>';
	  }
	}
	$str .= "</tr>";
      }
      $str .= "</table>";
      $str .= "</td>";
      $str .= "</tr>";
      $str .= "</table>";
      return $str;
    }

    function display_small_month($month,$year,$showyear,$link="") {
      global $phpgw;
      global $phpgw_info;

      $weekstarttime = $this->get_weekday_start($year,$month,1);

      $str  = "";
      $str .= "<table border=\"0\" bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">";

      $monthstart = $this->splitdate(mktime(0,0,0,$month    ,1,$year));
      $monthend   = $this->splitdate(mktime(0,0,0,$month + 1,0,$year));

      $str .= "<tr><td colspan=\"7\" align=\"center\"><font size=\"2\">";

      if(!$this->printer_friendly) {
	$str .= "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/index.php","year=$year&month=$month")."\">";
      }
      $str .= lang(date("F",$monthstart["raw"]));

      if($showyear) {
	$str .= " ".$year;
      }
    
      if(!$this->printer_friendly) {
	$str .= "</a>";
      }

      $str .= "</font></td></tr>"
	    . "<tr>";
      for($i=0;$i<$daysinweek;$i++) {
	$str .= "<td>".lang($days[$i])."</td>";
      }
      $str .= "</tr>";

      for($i=$weekstarttime;date("Ymd",$i)<=$monthend["full"];$i+=604800) {
	$str .= "<tr>";
	for($j=0;$j<$daysinweek;$j++) {
	  $date = $this->splitdate($i + ($j * 86400));
	  if($date["full"]>=$monthstart["full"] &&
	     $date["full"]<=$monthend["full"]) {
	    $str .= "<td align=\"right\">";
	    if(!$this->printer_friendly || $link) {
	      $str .= "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/".$link,"year=".$date["year"]."&month=".$date["month"]."&day=".$date["day"])."\">";
	    }
	    $str .= "<font size=\"2\">".date("j",$date["raw"]);
	    if(!$this->printer_friendly || $link) $str .= "</a>";
	    $str .= "</font></td>";
	  } else {
	    $str .= "<td></td>";
	  }
	}
	$str .= "</tr>";
      }
      $str .= "</table>";
      return $str;
    }

    function mini_calendar($day,$month,$year,$link="") {
      global $phpgw, $phpgw_info, $view;

      $date = $this->makegmttime(0,0,0,$month,$day,$year);
      $month_ago = intval(date("Ymd",mktime(0,0,0,$month - 1,$day,$year)));
      $month_ahead = intval(date("Ymd",mktime(0,0,0,$month + 1,$day,$year)));
      $monthstart = intval(date("Ymd",mktime(0,0,0,$month,1,$year)));
      $monthend = intval(date("Ymd",mktime(0,0,0,$month + 1,0,$year)));

      $weekstarttime = $this->get_weekday_start($year,$month,1);

	  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
	  $p->set_unknowns("remove");
      $p->set_file(array('mini_cal' => 'mini_cal.tpl',
      					 'mini_day' => 'mini_day.tpl',
      					 'mini_week' => 'mini_week.tpl'));
      $p->set_block('mini_cal','mini_week','mini_day');
      $p->set_var('img_root',$phpgw->common->get_image_path('phpgwapi'));
      $p->set_var("cal_img_root",$phpgw->common->get_image_path('calendar'));
      $p->set_var('bgcolor',$phpgw_info["theme"]["bg_color"]);
      $p->set_var('bgcolor1',$phpgw_info["theme"]["bg_color"]);
      $p->set_var('month','<a href="' . $phpgw->link($phpgw_info["server"]["webserver_url"].'/calendar/index.php','month=' . date('m',$date["raw"]).'&year=' . date('Y',$date["raw"])) . '" class="minicalendar">' . lang($phpgw->common->show_date($date["raw"],'F')).' '.$year) . '</a>';
      $p->set_var('prevmonth',$phpgw->link($phpgw_info["server"]["webserver_url"].'/calendar/index.php','date='.$month_ago));
      $p->set_var('nextmonth',$phpgw->link($phpgw_info["server"]["webserver_url"].'/calendar/index.php','date='.$month_ahead));

      $p->set_var('bgcolor2',$phpgw_info["theme"]["cal_dayview"]);
      for($i=0;$i<7;$i++) {
		$p->set_var('dayname',"<b>" . substr(lang($this->days[$i]),0,2) . "</b>");
		$p->parse('daynames','mini_day',True);
      }
      for($i=$weekstarttime;date("Ymd",$i)<=$monthend;$i += (24 * 3600 * 7)) {
		for($j=0;$j<7;$j++) {
		  $str = '';
		  $cal = $this->gmtdate($i + ($j * 24 * 3600));
		  if($cal["full"] >= $monthstart && $cal["full"] <= $monthend) {
			if ($cal["full"] == $this->today["full"]) {
			   $p->set_var("day_image",' background="' . $phpgw_info["server"]["webserver_url"]
			                         . "/calendar/templates/" . $phpgw_info["server"]["template_set"]
			                         . "/images/mini_day_block.gif" . '"');
			   //$p->set_var('bgcolor2','#'.$phpgw_info["theme"]["cal_today"]);
			} else {
  			$p->set_var("day_image","");
	          $p->set_var('bgcolor2','#FFFFFF');
	        }
	        if(!$this->printer_friendly) {
	      	  $str .= '<a href="'.$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/".$link,'year='.$cal["year"].'&month='.$cal["month"].'&day='.$cal["day"]).'" class="minicalendar">';
	    	}
	        $str .= $cal["day"];
	    	if (!$this->printer_friendly) $str .= '</a>';
	    	if ($cal["full"] == $this->today["full"]) {
               $p->set_var('dayname',"<b>$str</b>");
            } else {
               $p->set_var('dayname',$str);
            }
	  	  } else {
            $p->set_var('bgcolor2','#FEFEFE');
            $str = "";
//            if(!$this->printer_friendly) {
//              $str .= '<a href="'.$phpgw->link($phpgw_info["server"]["webserver_url"]
//                    . '/calendar/'.$link,'year='.$cal["year"].'&month='.$cal["month"].'&day='
//                    . $cal["day"]).'" class="minicalendargrey">';
//            }
//            $str .= $cal["day"];
//	    	if (!$this->printer_friendly) $str .= '</a>';
            $p->set_var('dayname',$str);
	  	  }
	  	  $p->parse('monthweek_day','mini_day',True);
		}
		$p->parse('display_monthweek','mini_week',True);
		$p->set_var('dayname','');
		$p->set_var('monthweek_day','');
      }
      return $p->finish($p->parse('out','mini_cal'));
    }

    function html_for_event_day_at_a_glance ($event) {
      global $phpgw, $phpgw_info;

      if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
        $format = "h:i a";
      } else {
        $format = "H:i";
      }

      $ind = intval($phpgw->common->show_date($event->datetime,"H"));

      if($ind<$this->first_hour || $ind>$this->last_hour) $ind = 99;

      if(!isset($this->hour_arr[$ind]) || !$this->hour_arr[$ind]) $this->hour_arr[$ind] = "";

      if (!$this->printer_friendly) {
	$this->hour_arr[$ind] .= "<a href=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/view.php","id=".$event->id)
			      . "\" onMouseOver=\"window.status='"
			      . lang("View this entry")."'; return true;\">";
      }

      $this->hour_arr[$ind] .= "[" . $phpgw->common->show_date($event->datetime,$format);
      if ($event->datetime <> $event->edatetime) {    // calc end time
	$this->hour_arr[$ind] .= " - " . $phpgw->common->show_date($event->edatetime,$format);
	$end_t_h = intval($phpgw->common->show_date($event->edatetime,"H"));
	$end_t_m = intval($phpgw->common->show_date($event->edatetime,"i"));
	if (end_t_m == 0)
	  $this->rowspan = $end_t_h - $ind;
	else
	  $this->rowspan = $end_t_h - $ind + 1;
	if(isset($this->rowspan_arr[$ind])) $r = $this->rowspan_arr[$ind]; else $r = 0;
	if ($this->rowspan > $r && $this->rowspan > 1)
	  $this->rowspan_arr[$ind] = $this->rowspan;
      }
      $this->hour_arr[$ind] .= "] ";
      $this->hour_arr[$ind] .= "<img src=\"".$phpgw->common->get_image_path('calendar')."/circle.gif\" border=0 alt=\"" . $event->description . "\"></a>";
      if ($event->priority == 3) {
        $this->hour_arr[$ind] .= "<font color=\"CC0000\">";
      }
      $this->hour_arr[$ind] .= $event->name;

      if ($event->priority == 3) {
        $this->hour_arr[$ind] .= "</font>";
      }
      $this->hour_arr[$ind] .= "<br>";
    }

    function print_day_at_a_glance($date,$owner=0) {
      global $phpgw;
      global $phpgw_info;

      $this->read_repeated_events($owner);

	  $p = new Template($phpgw->common->get_tpl_dir('calendar'));
	  $p->set_unknowns("remove");
      $p->set_file(array('day_cal' => 'day_cal.tpl',
      					 'mini_week' => 'mini_week.tpl',
//      					 'day_row_99' => 'day_row_99.tpl',
      					 'day_row_event' => 'day_row_event.tpl',
      					 'day_row_time' => 'day_row_time.tpl'));
      $p->set_block('day_cal','mini_week','day_row_event','day_row_time');


      if (! $phpgw_info["user"]["preferences"]["calendar"]["workdaystarts"] &&
          ! $phpgw_info["user"]["preferences"]["calendar"]["workdayends"]) {

         $phpgw_info["user"]["preferences"]["calendar"]["workdaystarts"] = 8;
         $phpgw_info["user"]["preferences"]["calendar"]["workdayends"]   = 16;
      }

      $this->first_hour = (int)$phpgw_info["user"]["preferences"]["calendar"]["workdaystarts"] + 1;
      $this->last_hour  = (int)$phpgw_info["user"]["preferences"]["calendar"]["workdayends"] + 1;

      $events = array(CreateObject('calendar.calendar_item'));

      $events = $this->get_sorted_by_date($date["raw"]);

      if(!$events) {
      } else {
       $event = CreateObject('calendar.calendar_item');
       for($i=0;$i<count($events);$i++) {
         $event = $events[$i];
         if($event) $this->html_for_event_day_at_a_glance($event);
        }
      }

      // squish events that use the same cell into the same cell.
      // For example, an event from 8:00-9:15 and another from 9:30-9:45 both
      // want to show up in the 8:00-9:59 cell.
      $this->rowspan = 0;
      $this->last_row = -1;
      for ($i=0;$i<24;$i++) {
        if(isset($this->rowspan_arr[$i])) $r = $this->rowspan_arr[$i]; else $r = 0;
        if(isset($this->hour_arr[$i])) $h = $this->hour_arr[$i]; else $h = "";
        if ($this->rowspan > 1) {
          if (strlen($h)) {
            $this->hour_arr[$this->last_row] .= $this->hour_arr[$i];
            $this->hour_arr[$i] = "";
            $this->rowspan_arr[$i] = 0;
          }
          $this->rowspan--;
        } elseif ($r > 1) {
          $this->rowspan = $this->rowspan_arr[$i];
          $this->last_row = $i;
        }
      }
      $p->set_var('time_bgcolor',$phpgw_info["theme"]["cal_dayview"]);
      $p->set_var('bg_time_image',$phpgw->common->get_image_path('phpgwapi').'/navbar_filler.jpg');
      $p->set_var('font_color',$phpgw_info["theme"]["bg_text"]);
      $p->set_var('font',$phpgw_info["theme"]["font"]);
      if (isset($this->hour_arr[99]) && strlen($this->hour_arr[99])) {
        $p->set_var('event',$this->hour_arr[99]);
        $p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
        $p->parse('monthweek_day','day_row_event',False);
        
        $p->set_var('open_link','');
        $p->set_var('close_link','');
        $p->set_var('time','&nbsp;');
        $p->parse('monthweek_day','day_row_time',True);
        $p->parse('row','mini_week',True);
        $p->set_var('monthweek_day','');
      }
      $this->rowspan = 0;
      $times = 0;
      for ($i=$this->first_hour;$i<=$this->last_hour;$i++) {
        if(isset($this->hour_arr[$i])) $h = $this->hour_arr[$i]; else $h = "";
        $time = $this->build_time_for_display($i * 10000);
        $p->set_var('extras','');
        $p->set_var('event','&nbsp');
        if ($this->rowspan > 1) {
          // this might mean there's an overlap, or it could mean one event
          // ends at 11:15 and another starts at 11:30.
          if (strlen($h)) {
            $p->set_var('event',$this->hour_arr[$i]);
            $p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
            $p->parse('monthweek_day','day_row_event',False);
          }
          $this->rowspan--;
        } else {
          if (!strlen($h)) {
            $p->set_var('event','&nbsp;');
            $p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
            $p->parse('monthweek_day','day_row_event',False);
          } else {
            $this->rowspan = isset($this->rowspan_arr[$i])?$this->rowspan_arr[$i]:0;
            if ($this->rowspan > 1) {
              $p->set_var('extras',' rowspan="'.$this->rowspan.'"');
              $p->set_var('event',$this->hour_arr[$i]);
              $p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
              $p->parse('monthweek_day','day_row_event',False);
            } else {
              $p->set_var('event',$this->hour_arr[$i]);
              $p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
              $p->parse('monthweek_day','day_row_event',False);
            }
          }
        }
        $p->set_var('open_link','');
        $p->set_var('close_link','');
        $str = ' - ';
        if(!$this->printer_friendly) {
          $str .= '<a href="'.$phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/edit_entry.php","year=".$date["year"]
                . "&month=".$date["month"]."&day=".$date["day"]
                . "&hour=".substr($time,0,strpos($time,":"))
                . "&minute=".substr($time,strpos($time,":")+1,2)).'">';
        }
        $p->set_var('open_link',$str);
        $p->set_var('time',(intval(substr($time,0,strpos($time,':'))) < 10 ? '0'.$time : $time) );
        if(!$this->printer_friendly) {
          $p->set_var('close_link','</a>');
        }
        $p->parse('monthweek_day','day_row_time',True);
        $p->parse('row','mini_week',True);
        $p->set_var('monthweek_day','');
      }	// end for
      return $p->finish($p->parse('out','day_cal'));
    }	// end function

    function prep($calid) {
      global $phpgw;
      global $phpgw_info;

      if(!$phpgw_info["user"]["apps"]["calendar"]) return false;

      $cal_id = array();
      if(is_long($calid)) {
	if(!$calid) return false;
	$cal_id[0] = $calid;
      } elseif(is_string($calid)) {

	$phpgw->db->query("SELECT account_id FROM accounts WHERE account_lid='$calid'",__LINE__,__FILE__);
	$phpgw->db->next_record();
	$calid = $phpgw->db->f("account_id");
	$phpgw->db->query("SELECT cal_id FROM calendar_entry WHERE cal_owner=".$calid,__LINE__,__FILE__);
        while($phpgw->db->next_record()) {
	  $cal_id[count($cal_id)] = $phpgw->db->f("cal_id");
	}
      } elseif(is_array($calid)) {
	if(is_string($calid[0])) {
	  for($i=0;$i<count($calid);$i++) {
	    $phpgw->db->query("SELECT cal_id FROM calendar_entry WHERE cal_owner=".$calid[$i],__LINE__,__FILE__);
            while($phpgw->db->next_record()) {
	      $cal_id[count($cal_id)] = $phpgw->db->f("cal_id");
	    }
	  }
	} elseif(is_long($calid[0])) {
	  $cal_id = $calid;
	}
      }
      return $cal_id;
    }

    function getwithindates($from,$to) {
      global $phpgw;
      global $phpgw_info;

      if(!$phpgw_info["user"]["apps"]["calendar"]) return false;


      $phpgw->db->query("SELECT cal_id FROM calendar_entry WHERE cal_date >= ".$from." AND cal_date <= ".$to,__LINE__,__FILE__);
      if($phpgw->db->num_rows()) {
	while($phpgw->db->next_record()) {
	  $calid[count($calid)] = intval($phpgw->db->f("cal_id"));
	}
	return $this->getevent($calid);
      } else {
	return false;
      }
    }

    function add($calinfo,$calid=0) {
      global $phpgw;
      global $phpgw_info;

      if(!$phpgw_info["user"]["apps"]["calendar"]) return false;
      if(!$calid) {
	$phpgw->db->lock(array('calendar_entry','calendar_entry_user','calendar_entry_repeats'));
	$phpgw->db->query("INSERT INTO calendar_entry(cal_name) VALUES('".addslashes($calinfo->name)."')",__LINE__,__FILE__);
	$phpgw->db->query("SELECT MAX(cal_id) FROM calendar_entry",__LINE__,__FILE__);
	$phpgw->db->next_record();
	$calid = $phpgw->db->f(0);
	$phpgw->db->unlock();
      }
      if($calid) return $this->modify($calinfo,$calid);
    }

    function delete($calid=0) {
      global $phpgw;

      $cal_id = $this->prep($calid);

      if(!$cal_id) return false;

      $phpgw->db->lock(array("calendar_entry","calendar_entry_user","calendar_entry_repeats"));

      for($i=0;$i<count($cal_id);$i++) {
	$phpgw->db->query("DELETE FROM calendar_entry_user WHERE cal_id=".$cal_id[$i],__LINE__,__FILE__);
	$phpgw->db->query("DELETE FROM calendar_entry_repeats WHERE cal_id=".$cal_id[$i],__LINE__,__FILE__);
	$phpgw->db->query("DELETE FROM calendar_entry WHERE cal_id=".$cal_id[$i],__LINE__,__FILE__);
      }
      $phpgw->db->unlock();
    }

    function modify($calinfo,$calid=0) {
      global $phpgw;
      global $phpgw_info;

      if(!$phpgw_info["user"]["apps"]["calendar"]) return false;

      if(!$calid) return false;

      $phpgw->db->lock(array("calendar_entry","calendar_entry_user","calendar_entry_repeats"));

      $owner = ($calinfo->owner?$calinfo->owner:$phpgw_info["user"]["account_id"]);
      if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
	if ($calinfo->ampm == "pm" && ($calinfo->hour < 12 && $calinfo->hour <> 12)) {
	  $calinfo->hour += 12;
	}
	if ($calinfo->end_ampm == "pm" && ($calinfo->end_hour < 12 && $calinfo->end_hour <> 12)) {
	  $calinfo->end_hour += 12;
	}
      }
      $date = mktime($calinfo->hour,$calinfo->minute,0,$calinfo->month,$calinfo->day,$calinfo->year) - ((60*60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);
      $enddate = mktime($calinfo->end_hour,$calinfo->end_minute,0,$calinfo->end_month,$calinfo->end_day,$calinfo->end_year) - ((60*60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);
      $today = time() - ((60*60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);

      if($calinfo->rpt_type != "none")
	$rpt_type = "M";
      else
	$rpt_type = "E";

      $query = "UPDATE calendar_entry SET cal_owner=".$owner.", cal_name='".addslashes($calinfo->name)."', "
	     . "cal_description='".addslashes($calinfo->description)."', cal_datetime=".$date.", "
	     . "cal_mdatetime=".$today.", cal_edatetime=".$enddate.", "
	     . "cal_priority=".$calinfo->priority.", cal_type='".$rpt_type."' ";

      if(($calinfo->access == "public" || $calinfo->access == "group") && count($calinfo->groups)) { 
	$query .= ", cal_access='".$calinfo->access."', cal_group = '".$phpgw->accounts->array_to_string($calinfo->access,$calinfo->groups)."' ";
      } elseif(($calinfo->access == "group") && !count($calinfo->groups)) {
	$query .= ", cal_access='private', cal_group = '' ";
      } else {
	$query .= ", cal_access='".$calinfo->access."', cal_group = '' ";
      }

      $query .= "WHERE cal_id=".$calid;

      $phpgw->db->query($query,__LINE__,__FILE__);

      $phpgw->db->query("DELETE FROM calendar_entry_user WHERE cal_id=".$calid,__LINE__,__FILE__);

      while ($participant = each($calinfo->participants)) {
	$phpgw->db->query("INSERT INTO calendar_entry_user(cal_id,cal_login,cal_status) "
	                . "VALUES($calid,".$participant[1].",'A')",__LINE__,__FILE__);
      }

      if(strcmp($calinfo->rpt_type,"none") <> 0) {
	$freq = ($calinfo->rpt_freq?$calinfo->rpt_freq:0);

	if($calinfo->rpt_use_end) {
	  $end = mktime(12,0,0,$calinfo->rpt_month,$calinfo->rpt_day,$calinfo->rpt_year) - ((60*60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);
	  $use_end = 1;
	} else {
	  $end = "NULL";
	  $use_end = 0;
	}

	if($calinfo->rpt_type == 'weekly' || $calinfo->rpt_type == 'daily') {
	  $days = ($calinfo->rpt_sun?'y':'n')
	        . ($calinfo->rpt_mon?'y':'n')
	        . ($calinfo->rpt_tue?'y':'n')
	        . ($calinfo->rpt_wed?'y':'n')
	        . ($calinfo->rpt_thu?'y':'n')
	        . ($calinfo->rpt_fri?'y':'n')
	        . ($calinfo->rpt_sat?'y':'n');
	} else {
	  $days = "nnnnnnn";
	}
	$phpgw->db->query("SELECT count(cal_id) FROM calendar_entry_repeats WHERE cal_id=".$calid,__LINE__,__FILE__);
	$phpgw->db->next_record();
	$num_rows = $phpgw->db->f(0);
	if(!$num_rows) {
	  $phpgw->db->query("INSERT INTO calendar_entry_repeats(cal_id,cal_type,cal_use_end,cal_end,cal_days,cal_frequency) "
			   ."VALUES($calid,'".$calinfo->rpt_type."',$use_end,$end,'$days',$freq)",__LINE__,__FILE__);
	} else {
	  $phpgw->db->query("UPDATE calendar_entry_repeats SET cal_type='".$calinfo->rpt_type."', cal_use_end=".$use_end.", "
			   ."cal_end='".$end."', cal_days='".$days."', cal_frequency=".$freq." "
			   ."WHERE cal_id=".$calid,__LINE__,__FILE__);
	}
      } else {
	$phpgw->db->query("DELETE FROM calendar_entry_repeats WHERE cal_id=".$calid,__LINE__,__FILE__);
      }
      $phpgw->db->unlock();      
    }

    function getevent($calid) {
      global $phpgw;

      $cal_id = $this->prep($calid);

      if(!$cal_id) return false;

      $phpgw->db->lock(array("calendar_entry","calendar_entry_user","calendar_entry_repeats"));

      $calendar = CreateObject('calendar.calendar_item');

      for($i=0;$i<count($cal_id);$i++) {

	$phpgw->db->query("SELECT * FROM calendar_entry WHERE cal_id=".$cal_id[$i],__LINE__,__FILE__);
	$phpgw->db->next_record();

        $calendar->id = (int)$phpgw->db->f("cal_id");
	$calendar->owner = $phpgw->db->f("cal_owner");

	$calendar->datetime = $phpgw->db->f("cal_datetime");
	$date = $this->date_to_epoch($phpgw->common->show_date($calendar->datetime,"Ymd"));
	$calendar->day = $date["day"];
	$calendar->month = $date["month"];
	$calendar->year = $date["year"];

	$time = $this->splittime($phpgw->common->show_date($calendar->datetime,"His"));
	$calendar->hour   = (int)$time["hour"];
	$calendar->minute = (int)$time["minute"];
	$calendar->ampm   = $time["ampm"];

//	echo "<br>TEST: hour: " . (int)$time["hour"];
//	echo "<br>TEST: minute: " . (int)$time["minute"];
//	echo "<br>TEST: ampm: " . $time["ampm"];
//	echo "<br>TEST: hour: " . $calendar->hour;
//	echo "<br>TEST: minute: " . $calendar->minute;
//	echo "<br>TEST: ampm: " . $calendar->ampm;

	$calendar->mdatetime = $phpgw->db->f("cal_mdatetime");
	$date = $this->date_to_epoch($phpgw->common->show_date($calendar->mdatetime,"Ymd"));
	$calendar->mod_day = $date["day"];
	$calendar->mod_month = $date["month"];
	$calendar->mod_year = $date["year"];

	$time = $this->splittime($phpgw->common->show_date($calendar->mdatetime,"His"));
	$calendar->mod_hour = (int)$time["hour"];
	$calendar->mod_minute = (int)$time["minute"];
	$calendar->mod_second = (int)$time["second"];
	$calendar->mod_ampm = $time["ampm"];

	$calendar->edatetime = $phpgw->db->f("cal_edatetime");
	$date = $this->date_to_epoch($phpgw->common->show_date($calendar->edatetime,"Ymd"));
	$calendar->end_day = $date["day"];
	$calendar->end_month = $date["month"];
	$calendar->end_year = $date["year"];

	$time = $this->splittime($phpgw->common->show_date($calendar->edatetime,"His"));
	$calendar->end_hour = (int)$time["hour"];
	$calendar->end_minute = (int)$time["minute"];
	$calendar->end_second = (int)$time["second"];
	$calendar->end_ampm = $time["ampm"];

	$calendar->priority = $phpgw->db->f("cal_priority");
// not loading webcal_entry.cal_type
	$calendar->access = $phpgw->db->f("cal_access");
	$calendar->name = htmlspecialchars(stripslashes($phpgw->db->f("cal_name")));
	$calendar->description = htmlspecialchars(stripslashes($phpgw->db->f("cal_description")));
	if($phpgw->db->f("cal_group"))
	  $calendar->groups = $phpgw->accounts->string_to_array($phpgw->db->f("cal_group"));

	$phpgw->db->query("SELECT * FROM calendar_entry_repeats WHERE cal_id=".$cal_id[$i],__LINE__,__FILE__);
	if($phpgw->db->num_rows()) {
	  $phpgw->db->next_record();

	  $rpt_type = strtolower($phpgw->db->f("cal_type"));
	  $calendar->rpt_type = !$rpt_type?"none":$rpt_type;
	  $calendar->rpt_use_end = $phpgw->db->f("cal_use_end");
	  if($calendar->rpt_use_end) {
	    $calendar->rpt_end = $phpgw->db->f("cal_end");
	    $rpt_end = $phpgw->common->show_date($phpgw->db->f("cal_end"),"Ymd");
	    $date = $this->date_to_epoch($rpt_end);
	    $calendar->rpt_end_day = (int)$date["day"];
	    $calendar->rpt_end_month = (int)$date["month"];
	    $calendar->rpt_end_year = (int)$date["year"];
	  } else {
	    $calendar->rpt_end = 0;
	    $calendar->rpt_end_day = 0;
	    $calendar->rpt_end_month = 0;
	    $calendar->rpt_end_year = 0;
	  }
	  $calendar->rpt_freq = (int)$phpgw->db->f("cal_frequency");
	  $rpt_days = strtoupper($phpgw->db->f("cal_days"));
	  $calendar->rpt_days = $rpt_days;
	  $calendar->rpt_sun = (substr($rpt_days,0,1)=="Y"?1:0);
	  $calendar->rpt_mon = (substr($rpt_days,1,1)=="Y"?1:0);
	  $calendar->rpt_tue = (substr($rpt_days,2,1)=="Y"?1:0);
	  $calendar->rpt_wed = (substr($rpt_days,3,1)=="Y"?1:0);
	  $calendar->rpt_thu = (substr($rpt_days,4,1)=="Y"?1:0);
	  $calendar->rpt_fri = (substr($rpt_days,5,1)=="Y"?1:0);
	  $calendar->rpt_sat = (substr($rpt_days,6,1)=="Y"?1:0);
	}

	$phpgw->db->query("SELECT * FROM calendar_entry_user WHERE cal_id=".$cal_id[$i],__LINE__,__FILE__);
	if($phpgw->db->num_rows()) {
	  while($phpgw->db->next_record()) {
	    $calendar->participants[] = $phpgw->db->f("cal_login");
	    $calendar->status[] = $phpgw->db->f("cal_status");
	  }
	}
	$calendar_item[$i] = $calendar;
      }
      $phpgw->db->unlock();
      return $calendar_item;
    }

    function findevent() {
      global $phpgw_info;

      if(!$phpgw_info["user"]["apps"]["calendar"]) return false;
    }
  }
?>
