<?php php_track_vars?>
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

  if (! $keywords) {
     // If we reach this it becuase they didn't search for anything,
     // attempt to send them back to where they where.
     Header("Location: " . $phpgw->link($from,"date=$datemonth=$month&day=$day&year=$year"));
  }

  include("../header.inc.php");

  $error = "";

  if (strlen($keywords) == 0)
     $error = lang("You must enter one or more search keywords.");

  $matches = 0;

?>

<H2><FONT COLOR="<?php echo $H2COLOR . "\">" . lang("Search Results"); ?></FONT></H2>

<?php
  // There is currently a problem searching in with repeated events.
  // It spits back out the date it was entered.  I would like to to say that
  // it is a repeated event.
  if (strlen($error)) {
     echo "<B>" . lang("Error") . ":</B> $error";
  } else {
     $ids = array();
     $words = split(" ", $keywords);
     for ($i = 0; $i < count($words); $i++) {
         $sql = "SELECT DISTINCT calendar_entry.cal_id, calendar_entry.cal_name, "
              . "calendar_entry.cal_datetime "
              . "FROM calendar_entry, calendar_entry_user "
	      . "WHERE "
	      . "(UPPER(calendar_entry.cal_name) LIKE UPPER('%".$words[$i]."%') OR "
	      . " UPPER(calendar_entry.cal_description) LIKE UPPER('%".$words[$i]."%')) AND "
	      . "calendar_entry_user.cal_id=calendar_entry.cal_id AND "
	      . "(((calendar_entry_user.cal_login=".$phpgw_info["user"]["account_id"].") AND "
	      . "(calendar_entry.cal_access='private')) "
	      . $phpgw->calendar->group_search()
	      . "OR calendar_entry.cal_access='public') "
	      . "ORDER BY cal_datetime";

         $phpgw->db->query($sql);
         while ($phpgw->db->next_record()) {
            $matches++;
            $ids[strval( $phpgw->db->f(0) )]++;
            $info[strval( $phpgw->db->f(0) )] = $phpgw->db->f(1) . " ("
                                         . $phpgw->common->show_date($phpgw->db->f(2)) . ")";
         }
     }
  }

  if ($matches > 0)
     $matches = count($ids);

  if ($matches == 1)
     echo "<B>1 match found.</B><P>";
  else if ($matches > 0)
     echo "<B>" . lang("x matches found",$matches) . ".</B><P>";
  else
     $error = lang("no matches found.");

// now sort by number of hits
  if (! strlen($error)) {
     arsort ($ids);
     for (reset($ids); $key = key($ids); next($ids)) {
         echo "<LI><A HREF=\"" . $phpgw->link("view.php","id=$key") . "\">" . $info[$key] . "</A>\n";

     }
  } else {
     echo $error;
  }

?>

<P>
<?php 
  $phpgw->common->phpgw_footer();
?>
