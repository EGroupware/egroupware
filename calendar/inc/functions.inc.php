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

  // only temp
  $BGCOLOR = "#C0C0C0"; // document background color
  $H2COLOR = "#000000"; // color of page titles
  $CELLBG = $phpgw_info["theme"]["cal_dayview"]; // color of table cells in month view
  $TABLEBG = "#000000"; // lines separating table cells
  $THBG = "#FFFFFF"; // background color of table column headers
  $THFG = "#000000"; // text color of table column headers
  $POPUP_FG = "#000000"; // text color in popup of event description
  $POPUP_BG = "#FFFFFF"; // background color in popup of event description
  $TODAYCELLBG = "#E0E0E0";// color of table cells of current day in month view


  $month_names = array("01" =>	lang_common("January"), "07" =>	lang_common("July"),
			   "02" =>	lang_common("February"),"08" =>	lang_common("August"),
			   "03" =>	lang_common("March"),	"09" =>	lang_common("September"),
			   "04" =>	lang_common("April"),	"10" =>	lang_common("October"),
			   "05" =>	lang_common("May"),		"11" =>	lang_common("November"),
			   "06" =>	lang_common("June"),	"12" =>	lang_common("December")
			  );

  $weekday_names = array( "0" => lang_common("Sunday"),	"1" => lang_common("Monday"),
				 "2" =>	lang_common("Tuesday"), "3" => lang_common("Wednesday"),
				 "4" => lang_common("Thursday"),"5" => lang_common("Friday"),
				 "6" => lang_common("Saturday")
				);


  function display_small_month($thismonth,$thisyear,$showyear, $link = "")
  {
    global $phpgw, $phpgw_info, $friendly;

    if (! $link)
       $link = "edit_entry.php";

    echo "<TABLE BORDER=0 bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">";
    $sun = get_sunday_before($thisyear, $thismonth, 1);

    $monthstart = mktime(2,0,0,$thismonth,1,$thisyear);
    $monthend = mktime(2,0,0,$thismonth + 1,0,$thisyear);

    echo "<TR><TD COLSPAN=7 ALIGN=\"center\"><FONT SIZE=\"2\">";

    if (! $friendly) {
       echo "<A HREF=\"".$phpgw->link("index.php","year=$thisyear&month=$thismonth") . "\">";
    }

    if ($showyear) {
       echo lang_common(date("F",$monthstart)) . " $thisyear" . "</a></font></td></tr>";
    } else {
       echo lang_common(date("F",$monthstart)) . "</A></FONT></TD></TR>";
    }

    echo "<tr>"
       . "<td>" . lang_calendar("Su") . "</td>"
       . "<td>" . lang_calendar("Mo") . "</td>"
       . "<td>" . lang_calendar("Tu") . "</td>"
       . "<td>" . lang_calendar("We") . "</td>"
       . "<td>" . lang_calendar("Th") . "</td>"
       . "<td>" . lang_calendar("Fr") . "</td>"
       . "<td>" . lang_calendar("Sa") . "</td>"
       . "</tr>";

    for ($i = $sun; date("Ymd",$i) <= date ("Ymd",$monthend);
	$i += (24 * 3600 * 7) ) {
	echo "<TR>";

	for ($j = 0; $j < 7; $j++) {
	    $date = $i + ($j * 24 * 3600);
	    if (date("Ymd",$date) >= date ("Ymd",$monthstart) && date("Ymd",$date) <= date ("Ymd",$monthend)) {
	       echo "<TD align=right>";
	       if (! $friendly)
   		     echo "<a href=\"".$phpgw->link($link,"year=".date("Y",$date)."&month=".date("m",$date)
				."&day=".date("d",$date)) . "\">";
	       echo "<FONT SIZE=\"2\">" . date ("j", $date) . "</a></FONT>"
		  . "</TD>";
	    } else
	       echo "<TD></TD>";
        } 		// end for $j
        echo "</TR>";
    } 			// end for $i
    echo "</TABLE>";
  } 			// end function
/*


  function weekday_short_name($w) {
    switch($w)
    {
      case 0: return lang_calendar("Sun");
      case 1: return lang_calendar("Mon");
      case 2: return lang_calendar("Tue");
      case 3: return lang_calendar("Wed");
      case 4: return lang_calendar("Thu");
      case 5: return lang_calendar("Fri");
      case 6: return lang_calendar("Sat");
      case 7: return lang_calendar("Jul");
    }
    return "unknown-weekday($w)";
  }

function month_name ( $m ) {
  switch ( $m ) {
    case 0: return lang_calendar("January");
    case 1: return lang_calendar("February");
    case 2: return lang_calendar("March");
    case 3: return lang_calendar("April");
    case 4: return lang_calendar("May");
    case 5: return lang_calendar("June");
    case 6: return lang_calendar("July");
    case 7: return lang_calendar("August");
    case 8: return lang_calendar("September");
    case 9: return lang_calendar("October");
    case 10: return lang_calendar("November");
    case 11: return lang_calendar("December");
  }
  return "unknown-month($m)";
}



  function display_small_month($thismonth, $thisyear, $showyear)
  {
    global $phpgw, $phpgw_info;

    echo "<TABLE BORDER=\"0\" CELLPADDING=\"1\" CELLSPACING=\"2\">";
    if ($phpgw_info["user"]["preferences"]["weekdaystarts"] == "monday") {
       $wkstart = get_monday_before($thisyear, $thismonth, 1);
    } else {
       $wkstart = get_sunday_before($thisyear, $thismonth, 1);
    }

    $monthstart = mktime(2,0,0,$thismonth,1,$thisyear);
    $monthend = mktime(2,0,0,$thismonth + 1,0,$thisyear);
    echo "<TR><TD COLSPAN=7 ALIGN=\"center\">"
       . "<A HREF=\"month.php?year=$thisyear&month=$thismonth"
       . $u_url . "\" CLASS=\"monthlink\">";
    echo month_name ( $thismonth - 1 ) . "</A></TD></TR>";
    echo "<TR>";
    if ($phpgw_info["user"]["preferences"]["weekdaystarts"] == "sunday")
       echo "<TD><FONT SIZE=\"-3\">" . weekday_short_name(0) . "</TD>";
    for ($i = 1; $i < 7; $i++) {
        echo "<TD><FONT SIZE=\"-3\">" . weekday_short_name ( $i ) . "</TD>";
    }
    if ($phpgw_info["user"]["preferences"]["weekdaystarts"] == "monday")
       echo "<TD><FONT SIZE=\"-3\">" .

    weekday_short_name(0) . "</TD>";
    for ($i = $wkstart; date("Ymd",$i) <= date ("Ymd",$monthend); $i += (24 * 3600 * 7) ) {
        echo "<TR>";
        for ($j = 0; $j < 7; $j++) {
      $date = $i + ($j * 24 * 3600);
      if ( date("Ymd",$date) >= date ("Ymd",$monthstart) &&
        date("Ymd",$date) <= date ("Ymd",$monthend) ) {
        echo "<TD align=right><a href=\"day.php?year=" .
          date("Y", $date) . "&month=" .
          date("m", $date) . "&day=" . date("d", $date) . $u_url .
          "\" CLASS=\"dayofmonthyearview\">";
        echo "<FONT SIZE=\"-1\">" . date ( "j", $date ) .
          "</a></FONT></TD>";
      } else
        echo "<TD></TD>";
    }                 // end for $j
    echo "</TR>";
  }                         // end for $i
  echo "</TABLE>";
}

*/







  // LC: links back to an entry view for $id using $pic
  function link_to_entry($id, $pic, $description)
  {
    global $phpgw, $phpgw_info, $friendly, $appname;
    if (! $friendly)
       echo "<A HREF=\"".$phpgw->link($phpgw_info["server"]["webserver_url"]
			."/".$phpgw_info["flags"]["currentapp"]."/view.php","id=$id")."\"><img src=\""
	   . $phpgw_info["server"]["app_images"]."/$pic\" "
	   . "border=\"0\" alt=\"".htmlentities($description)."\"></a>";
  }

  // Get all the repeating events for the specified data and return them
  // in an array (which is sorted by time of day).
  // This NEEDS to be combined with make_repeating_entires(),
  // this just needs an array returned.  Thats tommorows job.

  function get_repeating_entries($date)
  {
    global $repeated_events, $phpgw;
    $n = 0;

    $thisyear = substr($date, 0, 4);
    $thismonth = substr($date, 4, 2);
    for ($i=0; $i < count($repeated_events); $i++) {
       $start = date_to_epoch($repeated_events[$i][cal_date]);
       $end   = date_to_epoch($repeated_events[$i][cal_end]);
       $freq = $repeated_events[$i][cal_frequency];

      // only repeat after the beginning, and if there is an end
      // before the end
      if ($repeated_events[$i][cal_end] && date("Ymd",$date) > date("Ymd",$end))
         continue;

      if (date("Ymd",$date) < date("Ymd",$start))
         continue;
      $id = $repeated_events[$i][cal_id];

      if ($repeated_events[$i][cal_type] == 'daily') {
         if ((floor(($date - $start)/86400)%$freq))
            continue;
         $ret[$n++] = $repeated_events[$i];
      } else if ($repeated_events[$i][cal_type] == 'weekly') {
         $dow = date("w", $date);
         $isDay = substr($repeated_events[$i][cal_days], $dow, 1);
         if (floor(($date - $start)/604800)%$freq)
            continue;
         if (strcmp($isDay,"y") == 0) {
            $ret[$n++] = $repeated_events[$i];
      }

    } else if ($repeated_events[$i][cal_type] == 'monthlyByDay') {
      $dowS = date("w", $start);
      $dayS = floor(date("d", $start)/7);
      $mthS = date("m", $start);
      $yrS  = date("Y", $start);

      $dow  = date("w", $date);
      $day  = floor(date("d", $date)/7);
      $mth  = date("m", $date);
      $yr   = date("Y", $date);

      if ((($yr - $yrS)*12 + $mth - $mthS) % $freq)
        continue;

      if (($dowS == $dow) && ($day == $dayS)) {
        $ret[$n++] = $repeated_events[$i];
      }

    } else if ($repeated_events[$i][cal_type] == 'monthlyByDate') {
      $mthS = date("m", $start);
      $yrS  = date("Y", $start);

      $mth  = date("m", $date);
      $yr   = date("Y", $date);

      if ((($yr - $yrS)*12 + $mth - $mthS) % $freq)
         continue;

      if (date("d", $date) == date("d", $start)) {
         $ret[$n++] = $repeated_events[$i];
      }
    }

    else if ($repeated_events[$i][cal_type] == 'yearly') {
      $yrS = date("Y", $start);
      $yr  = date("Y", $date);

      if (($yr - $yrS)%$freq)
        continue;

      if (date("dm", $date) == date("dm", $start)) {
        $ret[$n++] = $repeated_events[$i];
      }
    } else {
      // unknown repeat type
    }
  }
  return $ret;
}

  // LC: does the repeating entry thang
  // There is no need to pass $user, needs to be removed.
  function make_repeating_entries($date,$hide_icons)
  {
    global $repeated_events;		// Pass it through?

    $thisyear = substr($date, 0, 4);
    $thismonth = substr($date, 4, 2);

    for ($i=0; $i < count($repeated_events); $i++ ) {
        $start = date_to_epoch($repeated_events[$i][cal_date]);
        $end   = date_to_epoch($repeated_events[$i][cal_end]);

	$freq = $repeated_events[$i][cal_frequency];
	// only repeat after the beginning, and if there is an end
	// before the end
	if ($repeated_events[$i][cal_end] && date("Ymd",$date) > date("Ymd",$end) )
	   continue;

	if (date("Ymd",$date) < date("Ymd",$start)) 
	   continue;

	$id = $repeated_events[$i][cal_id];

	if ($repeated_events[$i][cal_type] == 'daily') {
	   if ( (floor(($date - $start)/86400)%$freq) )
	      continue;
           link_to_entry( $id, "rpt.gif", $repeated_events[$i][cal_description]);
	   echo $repeated_events[$i][cal_name] . "<br>";
	} else if ($repeated_events[$i][cal_type] == 'weekly') {
	   $dow = date("w", $date);
	   $isDay = substr($repeated_events[$i][cal_days], $dow, 1);

	   /*if ( (floor($diff/86400)%$freq) ) // Whats this for ?
	   **   continue;
	   */

	   if (floor(($date - $start)/604800)%$freq)
	      continue;
	   if (strcmp($isDay,"y") == 0) {
	      link_to_entry($id, "rpt.gif", $repeated_events[$i][cal_description]);
	      echo $repeated_events[$i][cal_name] . "<br>";
	   }
	} else if ($repeated_events[$i][cal_type] == 'monthlyByDay') {
	   $dowS = date("w", $start);
	   $dayS = floor(date("d", $start)/7);
	   $mthS = date("m", $start);
	   $yrS  = date("Y", $start);
	   $dow  = date("w", $date);
	   $day  = floor(date("d", $date)/7);
	   $mth  = date("m", $date);
	   $yr   = date("Y", $date);
	   if ((($yr - $yrS)*12 + $mth - $mthS) % $freq)
	      continue;

	   if (($dowS == $dow) && ($day == $dayS)) {
	      link_to_entry($id, "rpt.gif", $repeated_events[$i][cal_description]);
	      echo $repeated_events[$i][cal_name] . "<br>";
	   }
	} else if ($repeated_events[$i][cal_type] == 'monthlyByDate') {
	   $mthS = date("m", $start);
	   $yrS  = date("Y", $start);
	   $mth  = date("m", $date);
	   $yr   = date("Y", $date);
	   if ((($yr - $yrS)*12 + $mth - $mthS) % $freq)
	      continue;
	   if (date("d", $date) == date("d", $start)) {
	      link_to_entry($id, "rpt.gif", $repeated_events[$i][cal_description]);
	      echo $repeated_events[$i][cal_name] . "<br>";
	   }
	} else if ($repeated_events[$i][cal_type] == 'yearly') {
	   $yrS = date("Y", $start);
	   $yr  = date("Y", $date);
	   if (($yr - $yrS)%$freq)
	      continue;
	   if (date("dm", $date) == date("dm", $start)) {
	      link_to_entry($id, "rpt.gif", $repeated_events[$i][cal_description]);
	      echo $repeated_events[$i][cal_name] . "<br>";
	   }
	} else {
	// unknown rpt type - because of all our else ifs
	}
    }	// end for loop
  }	// end function


  // The orginal patch read this 30+ times in a loop, only read it once.
  function read_repeated_events()
  {
    global $phpgw;

    $sql = "SELECT webcal_entry.cal_name, webcal_entry.cal_date, "
	 . "webcal_entry_repeats.*, webcal_entry.cal_description, "
	 . "webcal_entry.cal_time,webcal_entry.cal_priority, "
	 . "webcal_entry.cal_duration "
	 . "FROM webcal_entry, webcal_entry_repeats, webcal_entry_user "
	 . "WHERE webcal_entry.cal_id = webcal_entry_repeats.cal_id "
	 . "AND webcal_entry.cal_id = webcal_entry_user.cal_id "
	 . "AND webcal_entry_user.cal_login = '" . $phpgw_info["user"]["userid"] . "' "
	 . "AND webcal_entry.cal_type='M'";

    $phpgw->db->query($sql);

    $i = 0;
    while ($phpgw->db->next_record()) {
       $repeated_events[$i] = array("cal_name"			=> $phpgw->db->f("cal_name"),
							"cal_date"			=> $phpgw->db->f("cal_date"),
 							"cal_id"			=> $phpgw->db->f("cal_id"),
							"cal_type"			=> $phpgw->db->f("cal_type"),
							"cal_end"			=> $phpgw->db->f("cal_end"),
							"cal_frequency"		=> $phpgw->db->f("cal_frequency"),
							"cal_days"			=> $phpgw->db->f("cal_days"),
							"cal_description"	=> $phpgw->db->f("cal_description"),
							"cal_time"			=> $phpgw->db->f("cal_time"),
							"cal_priority"		=> $phpgw->db->f("cal_priority"),
							"cal_duration"		=> $phpgw->db->f("cal_duration")
						   );
    $i++;
    }
    return $repeated_events;
  }


  function get_sunday_before($year,$month,$day)
  {
    $weekday = date("w", mktime(0,0,0,$month,$day,$year) );
    $newdate = mktime(0,0,0,$month,$day - $weekday,$year);
    return $newdate;
  }


  function sql_search_calendar()
  {
     global $phpgw;
     $s .= $phpgw->accounts->sql_search("webcal_entry_groups.groups");
     $s .= " OR webcal_entry.cal_access='public'";
     return $s;
  }


  function print_date_entries($date,$hide_icons,$sessionid)
  {
    global $phpgw, $phpgw_info;

    if (! $hide_icons) {
       echo "<A HREF=\"".$phpgw->link("edit_entry.php",
				 "year=".date("Y",$date)
				."&month=".date("m",$date)
				."&day=".date("d",$date))
	  . "\">"
	  . "<IMG SRC=\"".$phpgw_info["server"]["app_images"]."/new.gif\" WIDTH=10 HEIGHT=10 ALT=\""
	  . lang_calendar("New Entry") . "\" BORDER=0 ALIGN=right></A>";
       echo "[ " . "<a href=\"".$phpgw->link("day.php","month=".date("m",$date)
									."&day=".date("d",$date)."&year=".date("Y",$date))
       									. "\">" . date("d", $date) . "</a> ]<BR>\n";
    } else {
       echo "[ " . date("d", $date) . " ]<BR>\n";

    }
    echo "<FONT SIZE=\"2\">";

    // This is only a temporey fix
    if ($phpgw_info["server"]["db_type"] == "mysql") {
       $sql = "SELECT DISTINCT webcal_entry.cal_id, webcal_entry.cal_name, "
	    . "webcal_entry.cal_priority, webcal_entry.cal_time, "
	    . "webcal_entry_user.cal_status, webcal_entry.cal_create_by, "
	    . "webcal_entry.cal_access,webcal_entry.cal_description "
    	    . "FROM webcal_entry LEFT JOIN webcal_entry_user "
            . "ON webcal_entry.cal_id=webcal_entry_user.cal_id "
            . "LEFT JOIN webcal_entry_groups "
	    . "ON webcal_entry_groups.cal_id=webcal_entry.cal_id "
	    . "WHERE webcal_entry.cal_date='" . date("Ymd", $date) . "' "
	    . "AND webcal_entry.cal_type != 'M' "
	    . "AND (webcal_entry_user.cal_login='" . $phpgw_info["user"]["userid"] . "' "
	    . sql_search_calendar() . ") "
	    . "ORDER BY webcal_entry.cal_priority, webcal_entry.cal_time";
    } else {
       $sql = "SELECT DISTINCT webcal_entry.cal_id, webcal_entry.cal_name, "
            . "webcal_entry.cal_priority, webcal_entry.cal_time, "
            . "webcal_entry_user.cal_status, webcal_entry.cal_create_by, "
            . "webcal_entry.cal_access,webcal_entry.cal_description "
            . "FROM webcal_entry, webcal_entry_user, webcal_entry_groups "
            . "WHERE webcal_entry.cal_date='" . date("Ymd", $date) . "' "
            . "AND webcal_entry.cal_type != 'M' AND "
            . "(webcal_entry_user.cal_login='" . $phpgw_info["user"]["userid"] . "' "
            . " AND webcal_entry.cal_id=webcal_entry_user.cal_id OR "
            . "(webcal_entry_groups.cal_id=webcal_entry.cal_id  "
            . sql_search_calendar() . ")) ORDER "
            . "BY webcal_entry.cal_priority, webcal_entry.cal_time";
    }

    make_repeating_entries($date, $hide_icons);

    $phpgw->db->query($sql);
       echo "<NOBR>";
       while ($phpgw->db->next_record()) {
         if (! $hide_icons) {
            echo "<A HREF=\"".$phpgw->link("view.php","id=".$phpgw->db->f(0))
	       . "\" onMouseOver=\"window.status='"
	       . lang_calendar("View this entry") . "'; return true;\"><IMG SRC=\"".$phpgw_info["server"]["app_images"]."/"
	       . "circle.gif\" WIDTH=5 HEIGHT=7 ALT=\"" . $phpgw->db->f("cal_description")
	       . "\" BORDER=0></A>";
         }
         if ($phpgw->db->f(2) == 3)
	    echo "<font color=\"CC0000\">";	// ***** put into theme
	 if ($phpgw->db->f(3) > 0) {
	    if ($phpgw_info["user"]["preferences"]["timeformat"] == "24")
	       printf ("%02d:%02d",$phpgw->db->f(3) / 10000, ($phpgw->db->f(3) / 100) % 100);
            else {
               $h = ((int)($phpgw->db->f(3) / 10000)) % 12;
	       if ($h == 0)
		  $h = 12;
	       echo $h;
	       $m = ($phpgw->db->f(3) / 100) % 100;
	       if ($m > 0)
		  printf(":%02d",$m);
	       echo ((int)($phpgw->db->f(3) / 10000)) < 12 ? "am" : "pm";
         }
         echo "&gt;";
       }
       echo "</NOBR>";
       echo htmlspecialchars(stripslashes($phpgw->db->f(1)));

       if ($phpgw->db->f(2) == 3)
	  echo "</font>";
       if ($phpgw->db->f(4) == "W") 
	  echo "</FONT>";
       echo "<BR>";
  } // end ?
  print "</FONT>";
  } // end function

  // display a time in either 12 or 24 hour format
  // Input time is an interger like 235900
  function display_time($time)
  {
    global $phpgw, $phpgw_info;
    $hour = (int)($time / 10000);
    $min  = ($time / 100) % 100;
    if ($phpgw_info["user"]["preferences"]["timeformat"] == "12") {
       $ampm = $hour >= 12 ? "pm" : "am";
       $hour %= 12;
       if ($hour == 0)
	  $hour = 12;
       $ret = sprintf("%d:%02d%s",$hour,$min,$ampm);
    } else
       $ret = sprintf("%d:%02d",$hour,$min);
    return $ret;
  }

  // convert a date from an int format "19991231" into "31 Dec 1999"
  function date_to_str($indate)
  {
    if (strlen($indate) > 0) {
       $y = (int)($indate / 10000);
       $m = ($indate / 100) % 100;
       $d = $indate % 100;
       $d = mktime(0,0,0,$m,$d,$y);
       return strftime("%A, %B %d, %Y", $d);
    } else
       return strftime("%A, %B %d, %Y");
  }

  // This should be a common function. (todo list)
  function date_to_epoch($d)
  {
    return mktime(0,0,0,substr($d,4,2),substr($d,6,2),substr($d,0,4));
  }

  function html_for_event_day_at_a_glance ($id, $date, $time,
           $name, $description, $status, $pri, $access, $duration, $hide_icons)
  {
     global $first_hour, $last_hour, $hour_arr, $rowspan_arr, $rowspan,
            $eventinfo, $phpgw, $phpgw_info;

  if ($time >= 0) {
     $ind = (int)($time / 10000);
     if ($ind < $first_hour)
        $first_hour = $ind;
     if ($ind > $last_hour)
        $last_hour = $ind;
  } else
    $ind = 99;

  $class = "entry";
  if (! $hide_icons) {
     $hour_arr[$ind] .= "<A HREF=\"".$phpgw->link("view.php","id=$id");
     $hour_arr[$ind] .= "\" onMouseOver=\"window.status='" . lang_calendar("View this entry")
			  . "'; return true;\">";
  }

  if ($time >= 0) {
    $hour_arr[$ind] .= "[" . display_time($time);
    if ($duration > 0) {
       // calc end time
       $h = (int)($time / 10000);
       $m = ($time / 100) % 100;
       $m += $duration;
       $d = $duration;
       while ( $m >= 60 ) {
         $h++;
         $m -= 60;
       }
       $end_time = sprintf("%02d%02d00", $h, $m);
       $hour_arr[$ind] .= "-" . display_time($end_time);
       if ($m == 0)
          $rowspan = $h - $ind;
       else
          $rowspan = $h - $ind + 1;
       if ($rowspan > $rowspan_arr[$ind] && $rowspan > 1)
          $rowspan_arr[$ind] = $rowspan;
    }
    $hour_arr[$ind] .= "] ";
  }
  $hour_arr[$ind] .= "<img src=".$phpgw_info["server"]["app_images"]."/circle.gif border=0 alt=\"" . htmlspecialchars(stripslashes($description)) . "\"></a>";
  if ($pri == 3)
     $hour_arr[$ind] .= "<font color=\"CC0000\">";
  $hour_arr[$ind] .= htmlspecialchars(stripslashes($name));

  if ($pri == 3)
     $hour_arr[$ind] .= "</font>";
  $hour_arr[$ind] .= "</A><BR>";
}


  function print_day_at_a_glance($date, $hide_icons)
  {
    global $hour_arr, $rowspan_arr, $rowspan, $phpgw, $phpgw_info,
           $CELLBG, $TODAYCELLBG, $THFG, $THBG;

    // get all the repeating events for this date and store in array $rep
    $rep = get_repeating_entries($date, $user);
    $cur_rep = 0;
    $sql = "SELECT DISTINCT webcal_entry.cal_id, webcal_entry.cal_name, "
	 . "webcal_entry.cal_priority, webcal_entry.cal_time, "
	 . "webcal_entry_user.cal_status, webcal_entry.cal_create_by, "
	 . "webcal_entry.cal_access,webcal_entry.cal_description "
    	 . "FROM webcal_entry "
          . "LEFT JOIN webcal_entry_user "
             . "ON webcal_entry.cal_id=webcal_entry_user.cal_id "
          . "LEFT JOIN webcal_entry_groups "
	          . "ON webcal_entry_groups.cal_id=webcal_entry.cal_id "
	 . "WHERE webcal_entry.cal_date='" . date("Ymd", $date) . "' "
	 . "AND webcal_entry.cal_type != 'M' "
	 . "AND (webcal_entry_user.cal_login='" . $phpgw_info["user"]["userid"] . "' "
	 . sql_search_calendar() . ") "
	 . "ORDER BY webcal_entry.cal_priority, webcal_entry.cal_time";

  $phpgw->db->query($sql);
  $hour_arr = Array();
  $first_hour = ($phpgw_info["user"]["preferences"]["workdaystarts"] + 1);
  $last_hour  = ($phpgw_info["user"]["preferences"]["workdayends"] + 1);

  $rowspan_arr = Array();

  while ($phpgw->db->next_record()) {
     // print out any repeating events that are before this one...
     while ($cur_rep < count($rep) && $rep[$cur_rep]["cal_time"] < $phpgw->db->f(3)) {
        html_for_event_day_at_a_glance($rep[$cur_rep]["cal_id"],
        $date, $rep[$cur_rep]["cal_time"],
        $rep[$cur_rep]["cal_name"], $rep[$cur_rep]["cal_description"],
        $rep[$cur_rep]["cal_status"], $rep[$cur_rep]["cal_priority"],
        $rep[$cur_rep]["cal_access"], $rep[$cur_rep]["cal_duration"],
        $hide_icons );
        $cur_rep++;
      }

      html_for_event_day_at_a_glance($phpgw->db->f("cal_id"),$date,$phpgw->db->f("cal_time"),
							 $phpgw->db->f("cal_name"),$phpgw->db->f("cal_description"),
							 $phpgw->db->f("cal_status"),$phpgw->db->f("cal_priority"),
							 $phpgw->db->f("cal_access"),$phpgw->db->f("cal_duration"),
							 $hide_icons);
  }

  // print out any remaining repeating events
  while ($cur_rep < count($rep)) {
    html_for_event_day_at_a_glance ( $rep[$cur_rep]["cal_id"],
      $date, $rep[$cur_rep]["cal_time"],
      $rep[$cur_rep]["cal_name"], $rep[$cur_rep]["cal_description"],
      $rep[$cur_rep]["cal_status"], $rep[$cur_rep]["cal_priority"],
      $rep[$cur_rep]["cal_access"], $rep[$cur_rep]["cal_duration"],
      $hide_icons );
    $cur_rep++;
  }

  // squish events that use the same cell into the same cell.
  // For example, an event from 8:00-9:15 and another from 9:30-9:45 both
  // want to show up in the 8:00-9:59 cell.
  $rowspan = 0;
  $last_row = -1;
  for ($i = 0; $i < 24; $i++) {
     if ($rowspan > 1) {
        if (strlen($hour_arr[$i])) {
           $hour_arr[$last_row] .= $hour_arr[$i];
           $hour_arr[$i] = "";
           $rowspan_arr[$i] = 0;
        }
        $rowspan--;
    } else if ($rowspan_arr[$i] > 1) {
        $rowspan = $rowspan_arr[$i];
        $last_row = $i;
    }
  }
  if (strlen($hour_arr[99])) {
    echo "<TR><TD BGCOLOR=\"$TODAYCELLBG\">&nbsp;</TD><TD BGCOLOR=\"$TODAYCELLBG\">"
       . "$hour_arr[99]</TD></TR>\n";
  }
  $rowspan = 0;
  for ($i = $first_hour; $i <= $last_hour; $i++) {
    $time = display_time($i * 10000);
//    echo "<TR><TH WIDTH=\"14%\" BGCOLOR=\"$THBG\">" . "<FONT COLOR=\"$THFG\">"
//       . $time . "</FONT></TH>\n";
      echo "<TR><TH WIDTH=\"14%\" BGCOLOR=\"$THBG\"><FONT COLOR=\"$THFG\">";

      // tooley: the hour - 36400 is a HACK for improper storage of hour allows
      // in user preference land.
      echo "<A HREF=\"".$phpgw->link("edit_entry.php",
		      "year=" . date("Y",$date)
		    . "&month=" . date("m",$date)
		    . "&day=" . date("d",$date)
		    . "&hour=" . substr($time,0,strpos($time,":"))
		    . "&minute=" . substr($time,strpos($time,":")+1,2))
		    . "\">$time</A></FONT></TH>";

    if ($rowspan > 1) {
       // this might mean there's an overlap, or it could mean one event
       // ends at 11:15 and another starts at 11:30.
       if (strlen($hour_arr[$i]))
          echo "<TD BGCOLOR=\"$TODAYCELLBG\">$hour_arr[$i]</TD>";
       $rowspan--;
    } else {
      if (! strlen($hour_arr[$i]))
         echo "<TD BGCOLOR=\"$TODAYCELLBG\">&nbsp;</TD></TR>\n";
      else {
        $rowspan = $rowspan_arr[$i];
        if ($rowspan > 1)
           echo "<TD VALIGN=\"top\" BGCOLOR=\"$TODAYCELLBG\" ROWSPAN=\"$rowspan\">"
	      . "$hour_arr[$i]</TD></TR>\n";
        else
           echo "<TD BGCOLOR=\"$TODAYCELLBG\">$hour_arr[$i]</TD></TR>\n";
      }
    }
   }	// end for
  }	// end function
?>
