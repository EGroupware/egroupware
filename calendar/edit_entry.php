<?phpphp_track_vars?>
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

  $phpgw_info["flags"]["currentapp"] = "calendar";

  include("../header.inc.php");

if ($id > 0) {
    $can_edit = false;
    $phpgw->db->query("SELECT cal_id FROM webcal_entry_user WHERE cal_login="
		          . "'" . $phpgw_info["user"]["userid"] . "' AND cal_id = $id");
    $phpgw->db->next_record();
    if ($phpgw->db->f("cal_id") > 0)
       $can_edit = true;

    $phpgw->db->query("SELECT cal_create_by, cal_date, cal_time, cal_mod_date, "
	               . "cal_mod_time, cal_duration, cal_priority, cal_type, "
	               . "cal_access, cal_name, cal_description FROM webcal_entry "
	               . "WHERE cal_id=$id");

    $phpgw->db->next_record();
    $year = (int)($phpgw->db->f(1) / 10000);
    $month = ($phpgw->db->f(1) / 100) % 100;
    $day = $phpgw->db->f(1) % 100;
    $time = $phpgw->db->f(2);
    if ($time > 0) {
       $hour = $time / 10000;
       $minute = ($time / 100) % 100;
    }
    $duration	 = $phpgw->db->f(5);
    $priority	 = $phpgw->db->f(6);
    $type 	 = $phpgw->db->f(7);
    $access 	 = $phpgw->db->f(8);
    $name 	 = $phpgw->db->f(9);
    $description = $phpgw->db->f(10);

    $name        = stripslashes($name);
    $name        = htmlspecialchars($name);
    $description = stripslashes($description);
    $description = htmlspecialchars($description);

    $phpgw->db->query("SELECT cal_login FROM webcal_entry_user WHERE cal_id=$id");
    while ($phpgw->db->next_record()) {
      $participants[$phpgw->db->f("cal_login")] = 1;
    }
    $phpgw->db->query("select * from webcal_entry_repeats where cal_id='$id"
		. "'");

    $phpgw->db->next_record();
    $rpt_type = $phpgw->db->f("cal_type");
    if ($phpgw->db->f("cal_end"))
       $rpt_end = date_to_epoch($phpgw->db->f("cal_end"));
    else
       $rpt_end = 0;

    $rpt_freq = $phpgw->db->f("cal_frequency");
    $rpt_days = $phpgw->db->f("cal_days");

    $rpt_sun  = (substr($rpt_days,0,1)=='y');
    $rpt_mon  = (substr($rpt_days,1,1)=='y');
    $rpt_tue  = (substr($rpt_days,2,1)=='y');
    $rpt_wed  = (substr($rpt_days,3,1)=='y');
    $rpt_thu  = (substr($rpt_days,4,1)=='y');
    $rpt_fri  = (substr($rpt_days,5,1)=='y');
    $rpt_sat  = (substr($rpt_days,6,1)=='y');

} else {
  $can_edit = true;
}

  if ($year)
     $thisyear = $year;
  if ($month)
     $thismonth = $month;
  if (! $rpt_type)
     $rpt_type = "none";
?>

<SCRIPT LANGUAGE="JavaScript">
// do a little form verifying
function validate_and_submit() {
  if (document.addform.name.value == "") {
    alert("<?php echo lang("You have not entered a\\nBrief Description"); ?>.");
    return false;
  }
  h = parseInt(document.addform.hour.value);
  m = parseInt(document.addform.minute.value);
  if (h > 23 || m > 59) {
     alert ("<?php echo lang("You have not entered a\\nvalid time of day."); ?>");
     return false;
  }
  // would be nice to also check date to not allow Feb 31, etc...
  document.addform.submit();
  return true;
}
</SCRIPT>
</HEAD>
<BODY BGCOLOR="<?php echo $BGCOLOR; ?>">

<H2><FONT COLOR="<?php echo $H2COLOR;?>"><?php 
    if ($id)
       echo lang("Calendar - Edit");
    else
       echo lang("Calendar - Add");

?></FONT></H2>

<?php
  if ($can_edit) {
?>
<FORM ACTION="<?php echo $phpgw->link("edit_entry_handler.php"); ?>" METHOD="POST" name="addform">

<?php if ($id) echo "<INPUT TYPE=\"hidden\" NAME=\"id\" VALUE=\"$id\">\n"; ?>

<TABLE BORDER=0>
<TR>
  <TD><B><?php echo lang("Brief Description"); ?>:</B></TD>
  <TD>
   <INPUT NAME="name" SIZE=25 VALUE="<?php echo ($name); ?>">
  </TD>
</TR>

<TR>
  <TD VALIGN="top"><B><?php echo lang("Full Description"); ?>:</B></TD>
  <TD>
   <TEXTAREA NAME="description" ROWS=5 COLS=40 WRAP="virtual"><?php
    echo ($description); ?></TEXTAREA>
  </TD>
</TR>

<TR>
  <TD><B><?php echo lang("Date"); ?>:</B></TD>
  <TD>
<?php
  $day_html = "<SELECT NAME=\"day\">";
  if ($day == 0)
     $day = date("d");
  for ($i = 1; $i <= 31; $i++)
      $day_html .= "<OPTION value=\"$i\"" . ($i == $day ? " SELECTED" : "") . ">$i"
	 	 . "</option>\n";
  $day_html .= "</select>";

  $month_html = "<SELECT NAME=\"month\">";
  if ($month == 0)
     $month = date("m");
  if ($year == 0)
     $year = date("Y");
  for ($i = 1; $i <= 12; $i++) {
    $m = lang(date("F", mktime(0,0,0,$i,1,$year)));
    $month_html .= "<OPTION VALUE=\"$i\"" . ($i == $month ? " SELECTED" : "") . ">$m"
		 . "</option>\n";
  }
  $month_html .= "</select>";

  $year_html = "<SELECT NAME=\"year\">";
  for ($i = -1; $i < 5; $i++) {
    $y = date("Y") + $i;
    $year_html .= "<OPTION VALUE=\"$y\"" . ($y == $year ? " SELECTED" : "") . ">$y"
       		. "</option>\n";
  }
  $year_html .= "</select>";

  echo $phpgw->common->dateformatorder($year_html,$month_html,$day_html);
?>

 </TD>
</TR>

<TR>
 <TD><B><?php echo lang("Time"); ?>:</B></TD>
<?php
  $h12 = $hour;
  $amsel = "CHECKED"; $pmsel = "";
  if ($phpgw_info["user"]["preferences"]["timeformat"] == "12") {
     if ($h12 < 12) {
        $amsel = "CHECKED"; $pmsel = "";
     } else {
        $amsel = ""; $pmsel = "CHECKED";
     }

     $h12 %= 12;
     if ($h12 == 0 && $hour)   $h12 = 12;
     if ($h12 == 0 && ! $hour) $h12 = "";
  }
?>
  <TD>
   <INPUT NAME="hour" SIZE=2 VALUE="<?php
    echo $h12;?>" MAXLENGTH=2>:<INPUT NAME="minute" SIZE=2 VALUE="<?php
    if ($hour > 0) printf ("%02d", $minute); ?>" MAXLENGTH=2>
<?php
  if ($phpgw_info["user"]["preferences"]["timeformat"] == "12") {
     echo "<INPUT TYPE=radio NAME=ampm VALUE=\"am\" $amsel>am\n";
     echo "<INPUT TYPE=radio NAME=ampm VALUE=\"pm\" $pmsel>pm\n";
  }
?>
</TD></TR>

<TR>
 <TD><B><?php echo lang("Duration"); ?>:</B></TD>
  <TD><INPUT NAME="duration" SIZE=3 VALUE="<?php
    echo $duration;?>"> <?php echo lang("minutes"); ?></TD>
</TR>

<TR>
  <TD><B><?php echo lang("Priority"); ?>:</B></TD>
  <TD><SELECT NAME="priority">
    <OPTION VALUE="1"<?php if ($priority == 1) echo " SELECTED";?>><?php echo lang("Low"); ?> </option>
    <OPTION VALUE="2"<?php if ($priority == 2 || $priority == 0 ) echo " SELECTED";?>><?php echo lang("Medium"); ?></option>
    <OPTION VALUE="3"<?php if ($priority == 3) echo " SELECTED";?>><?php echo lang("High"); ?></option>
  </SELECT></TD>
</TR>

<TR>
 <TD><B><?php echo lang("Access"); ?>:</B></TD>
 <TD><SELECT NAME="access">
  <OPTION VALUE="private"<?php
   if ($access == "private" || ! $id) echo " SELECTED";?>><?php echo lang("Private"); ?></option>

  <OPTION VALUE="group"<?php
   if ($access == "public" || strlen($access)) echo " SELECTED";?>><?php echo lang("Group Public"); ?></option>
  <OPTION VALUE="public"<?php
   if ($access == "public") echo " SELECTED"; ?>><?php echo lang("Global Public"); ?></option>

  </SELECT>
 </TD>
 </tr>
 <tr>

 <TD><B><?php echo lang("group access"); ?>:</B></TD>
 <TD><SELECT NAME="n_groups[]" multiple size="5">
  <?php
    if ($id > 0) {
       $phpgw->db->query("select groups from webcal_entry_groups where cal_id='$id'");
       $phpgw->db->next_record();
       $db_groups = $phpgw->db->f("groups");
    }

    $user_groups = $phpgw->accounts->read_group_names();
    for ($i=0;$i<count($user_groups);$i++) {
	echo "<option value=\"" . $user_groups[$i][0] . "\"";
	if (ereg(",".$user_groups[$i][0].",",$db_groups))
	   echo " selected";
	echo ">" . $user_groups[$i][1] . "</option>\n";
    }

  ?></SELECT></TD>
</TR>


<?php
  // This will cause problems if there are more then 13 permissions.
  // The permissions class needs to be updated to handle this.
  $phpgw->db->query("select loginid, lastname, firstname from accounts where "
	             . "status !='L' and loginid != '" . $phpgw_info["user"]["userid"] . "' and "
	             . "permissions like '%:calendar:%' order by lastname,firstname,loginid");

  if ($phpgw->db->num_rows() > 50)
     $size = 15;
  else if ($phpgw->db->num_rows() > 5)
     $size = 5;
  else
     $size = $phpgw->db->num_rows();

  echo "<TR><TD VALIGN=\"top\"><B>" . lang("Participants") . ":</B></TD>"
     . "<TD>\n<SELECT NAME=\"participants[]\" multiple size=\"$size\">\n";

  while ($phpgw->db->next_record()) {
    echo "<option value=\"" . $phpgw->db->f("loginid") . "\"";  
    if (($participants[$phpgw->db->f("loginid")]
	|| $phpgw->db->f("loginid") == $loginid))
       echo " selected";

    if (! $phpgw->db->f("lastname"))
       echo ">" . $phpgw->db->f("loginid");
    else
       echo ">" . $phpgw->db->f("lastname") . ", " . $phpgw->db->f("firstname");

    echo "</option>\n";
  }

  echo "<input type=\"hidden\" name=\"participants[]\" value=\""
     . $phpgw_info["user"]["userid"] ."\">"
     . "</select></td></tr>\n";

?>

<tr>
 <td><b><?php echo lang("Repeat type"); ?>:</b></td>
 <td><select name="rpt_type">
 <?php
   echo "<option value=\"none\"" . (strcmp($rpt_type,'none')==0?"selected":"") . ">"
      . lang("None") . "</option>";

   echo "<option value=\"daily\"" . (strcmp($rpt_type,'daily')==0?"selected":"") . ">"
      . lang("Daily") . "</option>";

   echo "<option value=\"weekly\"" . (strcmp($rpt_type,'weekly')==0?"selected":"") . ">"
      . lang("Weekly") . "</option>";

   echo "<option value=\"monthlyByDay\"".(strcmp($rpt_type,'monthlyByDay')==0?"selected":"")
      . ">" . lang("Monthly (by day)") . "</option>";

   echo "<option value=\"monthlyByDate\"".(strcmp($rpt_type,'monthlyByDate')==0?"checked":"")
      . "> " . lang("Monthly (by date)") . "</option>";

   echo "<option value=\"yearly\"" . (strcmp($rpt_type,'yearly')==0?"checked":"") . ">"
      . lang("Yearly") . "</option>";
?>
  </select>
 </td>
<tr>
 <td><b><?php echo lang("Repeat End date"); ?>:</b></td>
 <td><input type=checkbox name=rpt_end_use value=y <?php
      echo ($rpt_end?"checked":""); ?>> <?php echo lang("Use End date"); ?>

<?php
  if ($rpt_end) {
     $rpt_day 	= date("d",$rpt_end);
     $rpt_month = date("m",$rpt_end);
     $rpt_year 	= date("Y",$rpt_end);
  } else {
     $rpt_day 	= $day+1;
     $rpt_month = $month;
     $rpt_year 	= $year;
  }

  $day_html = "<SELECT NAME=\"rpt_day\">";
  for ($i = 1; $i <= 31; $i++) {
    $day_html .= "<OPTION value=\"$i\"" . ($i == $rpt_day ? " SELECTED" : "")
	       . ">$i</option>\n";
  }
  $day_html .= "</select>";

  $month_html = "<select name=\"rpt_month\">";
  for ($i = 1; $i <= 12; $i++) {
    $m = lang(date("F", mktime(0,0,0,$i,1,$rpt_year)));
    $month_html .= "<OPTION VALUE=\"$i\"" . ($i == $rpt_month ? " SELECTED" : "")
		 . ">$m</option>\n";
  }
  $month_html .= "</select>";

  $year_html = "<select name=\"rpt_year\">";
  for ($i = -1; $i < 5; $i++) {
    $y = date("Y") + $i;
    $year_html .= "<OPTION VALUE=\"$y\"" . ($y == $rpt_year ? " SELECTED" : "")
	   . ">$y</option>\n";
  }
  $year_html .= "</select>";

  echo $phpgw->common->dateformatorder($year_html,$month_html,$day_html);
?>

 </td>
</tr>
<tr>
  <td><b><?php echo lang("Repeat day"); ?>: </b><?php echo lang("(for Weekly)"); ?></td>
  <td><?php
  echo "<input type=checkbox name=rpt_sun value=y "
     . ($rpt_sun?"checked":"") . "> " . lang("Sunday");
  echo "<input type=checkbox name=rpt_mon value=y "
     . ($rpt_mon?"checked":"") . "> " . lang("Monday");
  echo "<input type=checkbox name=rpt_tue value=y "
     . ($rpt_tue?"checked":"") . "> " . lang("Tuesday");
  echo "<input type=checkbox name=rpt_wed value=y "
     . ($rpt_wed?"checked":"") . "> " . lang("Wednesday");
  echo "<input type=checkbox name=rpt_thu value=y "
     . ($rpt_thu?"checked":"") . "> " . lang("Thursday");
  echo "<input type=checkbox name=rpt_fri value=y "
     . ($rpt_fri?"checked":"") . "> " . lang("Friday");
  echo "<input type=checkbox name=rpt_sat value=y "
     . ($rpt_sat?"checked":"") . "> " . lang("Saturday");
  ?></td>
</tr>

<tr>
 <td><b><?php echo lang("Frequency"); ?>: </b></td>
 <td>
  <input name="rpt_freq" size="4" maxlength="4" value="<?php
							echo $rpt_freq; ?>">
 </td>
</tr>
</TABLE>

<SCRIPT LANGUAGE="JavaScript">
  document.writeln ( '<INPUT TYPE="button" VALUE="<?php echo lang("Submit"); ?>" ONCLICK="validate_and_submit()">' );
  /* document.writeln ( '<INPUT TYPE="button" VALUE="<?php echo lang("Help"); ?>" ONCLICK="window.open ( \'help_edit_entry.php\', \'cal_help\', \'dependent,menubar,height=365,width=650,innerHeight=365,outerWidth=420,resizable=1\');">' ); */
</SCRIPT>
<NOSCRIPT>
<INPUT TYPE="submit" VALUE="<?php echo lang("Submit"); ?>">
</NOSCRIPT>

<INPUT TYPE="hidden" NAME="participant_list" VALUE="">

</FORM>

<?php
  if ($id > 0) {
     echo "<A HREF=\"" . $phpgw->link("delete.php","id=$id") . "\" onClick=\"return confirm('"
	. lang("Are you sure\\nyou want to\\ndelete this entry ?") . "');\">"
	. lang("Delete") . "</A><BR>";
  } 
  } // ***** This might be out of place.  I was getting tons of parse errors
    // from if ($can_edit) {   This needs to be rewritten, because if you do
    // not own the entry.  You should not get into this portion of the program.
    include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
