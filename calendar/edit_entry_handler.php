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

  $phpgw_flags["noheader"]="True";
  $phpgw_flags["currentapp"]="calendar";
  include("../header.inc.php");
  // Input time format "2359"
  function add_duration($time, $duration)
  {
    $hour = (int)($time / 10000);
    $min = $time % 100;
    $minutes = $hour * 60 + $min + $duration;
    $h = $minutes / 60;
    $m = $minutes % 60;
    $ret = sprintf ("%d%02d",$h,$m);
    //echo "add_duration ( $time, $duration ) = $ret <BR>";
    return $ret;
  }

  // check to see if two events overlap
  function times_overlap($time1, $duration1, $time2, $duration2)
  {
    //echo "times_overlap ( $time1, $duration1, $time2, $duration2 )<BR>";
    $hour1 = (int) ($time1 / 100);
    $min1 = $time1 % 100;
    $hour2 = (int) ($time2 / 100);
    $min2 = $time2 % 100;
    // convert to minutes since midnight
    $tmins1start = $hour1 * 60 + $min1;
    $tmins1end = $tmins1start + $duration1;
    $tmins2start = $hour2 * 60 + $min2;
    $tmins2end = $tmins2start + $duration2;
    //echo "tmins1start=$tmins1start, tmins1end=$tmins1end, tmins2start="
    //	 . "$tmins2start, tmins2end=$tmins2end<BR>";

    if ($tmins1start >= $tmins2start && $tmins1start <= $tmins2end)
       return true;
    if ($tmins1end >= $tmins2start && $tmins1end <= $tmins2end)
       return true;
    if ($tmins2start >= $tmins1start && $tmins2start <= $tmins1end)
       return true;
    if ($tmins2end >= $tmins1start && $tmins2end <= $tmins1end)
       return true;
    return false;
  }

  $phpgw->db->lock(array('webcal_entry','webcal_entry_user','webcal_entry_groups',
		         'webcal_entry_repeats'));

  // first check for any schedule conflicts
  if (strlen($hour) > 0) {
    $date = mktime(0,0,0,$month,$day,$year);
    if ($phpgw_info["user"]["preferences"]["timeformat"] == "12") {
      $hour %= 12;
      if ($ampm == "pm")
	$hour += 12;
  }

  $sql = "SELECT webcal_entry_user.cal_login, webcal_entry.cal_time," .
    "webcal_entry.cal_duration, webcal_entry.cal_name, " .
    "webcal_entry.cal_id, webcal_entry.cal_access " .
    "FROM webcal_entry, webcal_entry_user " .
    "WHERE webcal_entry.cal_id = webcal_entry_user.cal_id " .
    "AND webcal_entry.cal_date = " . date("Ymd", $date) . " AND ( ";

  for ($i = 0; $i < count($participants); $i++) {
    if ($i) $sql .= " OR ";
    $sql .= " webcal_entry_user.cal_login = '" . $participants[$i] . "'";
  }
  $sql .= " )";

  $phpgw->db->query($sql);
  $time1 = sprintf("%d:%02d", $hour, $minute);
  $duration1 = sprintf("%d", $duration);

  while ($phpgw->db->next_record()) {
    // see if either event overlaps one another
    if ($phpgw->db->f(4) != $id) {
       $time2 = $phpgw->db->f(1);
       $duration2 = $phpgw->db->f(2);
       if (times_overlap($time1, $duration1, $time2, $duration2)) {
          $overlap .= "<LI>";
          if ($phpgw->db->f(5) == 'R' && $phpgw->db->f(0) != $login)
             $overlap .=  "(PRIVATE)";
          else {
            $overlap .=  "<A HREF=\"".$phpgw->link("view.php",
				"id=".$phpgw->db->f(4))."\">"
		     . $phpgw->db->f(3) . "</A>";
          }
          $overlap .= " (" . display_time($time2);
          if ($duration2 > 0)
             $overlap .= "-" . display_time(add_duration($time2,$duration2))
		       . ")";
        }
      }
    }
  }

if ($overlap)
  $error = lang_calendar("The following conflicts with the suggested time:<ul>x</ul>",
				 $overlap);

if (! $error) {
  // now add the entries

  if ($id != 0) {
    $phpgw->db->query("DELETE FROM webcal_entry WHERE cal_id = $id");
    $phpgw->db->query("DELETE FROM webcal_entry_user WHERE cal_id = $id");
    $phpgw->db->query("DELETE FROM webcal_entry_repeats WHERE cal_id = $id");
    $phpgw->db->query("DELETE FROM webcal_entry_groups WHERE cal_id = $id");
  }

  $sql = "INSERT INTO webcal_entry (cal_create_by, cal_date, " .
    "cal_time, cal_mod_date, cal_mod_time, cal_duration, cal_priority, " .
    "cal_access, cal_type, cal_name, cal_description ) " .
    "VALUES ('" . $phpgw->session->loginid . "', ";

  $date = mktime(0,0,0,$month,$day,$year);
  $sql .= date("Ymd", $date) . ", ";
  if (strlen($hour) > 0) {
    if ($phpgw_info["user"]["preferences"]["timeformat"] == "12") {
      $hour %= 12;
      if ($ampm == "pm")
       $hour += 12;
    }
    $sql .= sprintf("%02d%02d00, ",$hour,$minute);
  } else
    $sql .= "'-1', ";

  $sql .= date("Ymd") . ", " . date("Gis") . ", ";
  $sql .= sprintf("%d, ",$duration);
  $sql .= sprintf("%d, ",$priority);
  $sql .= "'$access', ";

  if ($rpt_type != 'none')
     $sql .= "'M', ";
  else
     $sql .= "'E', ";
     
  if (strlen($name) == 0)
     $name = "Unnamed Event";

  $sql .= "'" . addslashes($name) .  "', ";
  if (! $description)
     $sql .= "'" . addslashes($name) . "')";
  else
     $sql .= "'" . addslashes($description) . "' )";
  
  $error = "";
  $phpgw->db->query($sql);

  $phpgw->db->query("SELECT MAX(cal_id) FROM webcal_entry");
  $phpgw->db->next_record();
  $id = $phpgw->db->f(0);

  
  for ($i = 0; $i < count($participants); $i++) {
      // Rewrite
      $sql = "INSERT INTO webcal_entry_user (cal_id,cal_login,cal_status ) "
	   . "VALUES ($id, '" . $participants[$i] . "', 'A')";
      $phpgw->db->query($sql);
  }

  if (count($participants) == 0)
     $phpgw->db->query("insert into webcal_entry_user (cal_id,cal_login,cal_status"
		 . ") values ($id,'" . $phpgw->session->loginid . "','A' )");
  }
  
  if (strlen($rpt_type) || ! strcmp($rpt_type,'none') == 0) {
     // clearly, we want to delete the old repeats, before inserting new...
     $phpgw->db->query("delete from webcal_entry_repeats where cal_id='$id'");
     $freq = ($rpt_freq?$rpt_freq:1);

     if ($rpt_end_use) {
	$end = "'" . date("Ymd",mktime(0,0,0,$rpt_month,$rpt_day,$rpt_year))
	     . "'";
     } else
	$end = 'NULL';

     if ($rpt_type == 'weekly') {
	$days = ($rpt_sun?'y':'n')
	      . ($rpt_mon?'y':'n')
	      . ($rpt_tue?'y':'n')
	      . ($rpt_wed?'y':'n')
	      . ($rpt_thu?'y':'n')
	      . ($rpt_fri?'y':'n')
	      . ($rpt_sat?'y':'n');
     } else {
	$days = "nnnnnnn";
     }

     $phpgw->db->query("insert into webcal_entry_repeats (cal_id,cal_type,"
	         . "cal_end,cal_days,cal_frequency) values($id,'$rpt_type',"
	         . "$end,'$days',$freq)");
  }
  $phpgw->db->query("insert into webcal_entry_groups values ('$id','"
	      . $phpgw->groups->array_to_string($access,$n_groups) . "') ");

  
  Header("Location: ".$phpgw->link("index.php","year=$year&month=$month&cd=14"));

?>

$phpgw->common->header();
<BODY BGCOLOR="<?php echo $BGCOLOR; ?>">

<?php if (strlen($overlap)) { ?>
<H2><FONT COLOR="<?php echo $H2COLOR;?>">Scheduling Conflict</H2></FONT>
<?php
  $time = sprintf("%d:%02d",$hour,$minute);
  echo lang_calendar("Your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:", display_time($time),display_time(add_duration($time,$duration))); ?>

?>

<UL>
<?php echo $overlap; ?>
</UL>

<?php } else { ?>
<H2><FONT COLOR="<?php echo $H2COLOR;?>">Error</H2></FONT>
<BLOCKQUOTE>
<?php echo $error; ?>
</BLOCKQUOTE>

<?php 
  } 

  $phpgw->db->unlock();
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
