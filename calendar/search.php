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

  $phpgw_flags["currentapp"] = "calendar";

  if (! $keywords) {
     // If we reach this it becuase they didn't search for anything,
     // attempt to send them back to where they where.
     Header("Location: " . $phpgw->link($from,"date=$datemonth=$month&day=$day&year=$year"));
  }

  include("../header.inc.php");

  $error = "";

  if (strlen($keywords) == 0)
     $error = lang_calendar("You must enter one or more search keywords.");

  $matches = 0;

?>

<H2><FONT COLOR="<?php echo $H2COLOR . "\">" . lang_calendar("Search Results"); ?></FONT></H2>

<?php
  // There is currently a problem searching in with repeated events.
  // It spits back out the date it was entered.  I would like to to say that
  // it is a repeated event.
  if (strlen($error)) {
     echo "<B>" . lang_common("Error") . ":</B> $error";
  } else {
     $ids = array();
     $words = split(" ", $keywords);
     for ($i = 0; $i < count($words); $i++) {
         $sql = "SELECT DISTINCT webcal_entry.cal_id, webcal_entry.cal_name, "
              . "webcal_entry.cal_date,webcal_entry_repeats.cal_type "
              . "FROM webcal_entry, webcal_entry_user, webcal_entry_repeats, "
	         . "webcal_entry_groups WHERE (UPPER(webcal_entry.cal_name) LIKE UPPER('%"
              . $words[$i] . "%') OR UPPER(webcal_entry.cal_description) "
              . "LIKE UPPER('%" .  $words[$i] . "%')) AND (webcal_entry_user.cal_login = '"
	         . $phpgw_info["user"]["userid"] . "' OR (webcal_entry.cal_access='public' "
	         . sql_search_calendar() . ")) ORDER BY cal_date";

         $phpgw->db->query($sql);
         while ($phpgw->db->next_record()) {
            $matches++;
            $ids[strval( $phpgw->db->f(0) )]++;
            $info[strval( $phpgw->db->f(0) )] = $phpgw->db->f(1) . " ("
                                         . date_to_str($phpgw->db->f(2)) . ")";
         }
     }
  }

  if ($matches > 0)
     $matches = count($ids);

  if ($matches == 1)
     echo "<B>1 match found.</B><P>";
  else if ($matches > 0)
     echo "<B>" . lang_calendar("x matches found",$matches) . ".</B><P>";
  else
     $error = lang_calendar("no matches found.");

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
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

