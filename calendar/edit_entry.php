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

  include("../header.inc.php");

  $cal_info = new calendar_item;

  if ($id > 0) {
    $can_edit = false;
    $phpgw->db->query("SELECT cal_id FROM webcal_entry_user WHERE cal_login="
		            . $phpgw_info["user"]["account_id"] . " AND cal_id = $id");
    $phpgw->db->next_record();
    if ($phpgw->db->f("cal_id") > 0)
       $can_edit = true;

    $cal = $phpgw->calendar->getevent((int)$id);

    $cal_info = $cal[0];

  } else {

    if (!isset($day) || !$day)
      $thisday = (int)$phpgw->calendar->today["day"];
    else
      $thisday = $day;
    if (!isset($month) || !$month)
      $thismonth = (int)$phpgw->calendar->today["month"];
    else
      $thismonth = $month;
    if (!isset($year) || !$year)
      $thisyear = (int)$phpgw->calendar->today["year"];
    else
      $thisyear = $year;

    if (!isset($hour))
      $thishour = 0;
    else
      $thishour = (int)$hour;
    if (!isset($minute))
      $thisminute = 00;
    else
      $thisminute = (int)$minute;

    $time = $phpgw->calendar->splittime_($phpgw->calendar->fixtime($thishour,$thisminute));

    $cal_info->name = "";
    $cal_info->description = "";

    $cal_info->day = $thisday;
    $cal_info->month = $thismonth;
    $cal_info->year = $thisyear;

    $cal_info->rpt_day = $thisday + 1;
    $cal_info->rpt_month = $thismonth;
    $cal_info->rpt_year = $thisyear;

    $cal_info->hour = (int)$time["hour"];
    $cal_info->minute = (!(int)$time["minute"]?"00":(int)$time["minute"]);
    $cal_info->ampm = "am";
    if($cal_info->hour > 12 && $phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
      $cal_info["hour"] = $cal_info["hour"] - 12;
      $cal_info["ampm"] = "pm";
    }
    $can_edit = true;
  }
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
<BODY BGCOLOR="#C0C0C0">

<H2><FONT COLOR="#000000"><?php 
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
   <INPUT NAME="name" SIZE=25 VALUE="<?php echo ($cal_info->name); ?>">
  </TD>
</TR>

<TR>
  <TD VALIGN="top"><B><?php echo lang("Full Description"); ?>:</B></TD>
  <TD>
   <TEXTAREA NAME="description" ROWS=5 COLS=40 WRAP="virtual"><?php
    echo ($cal_info->description); ?></TEXTAREA>
  </TD>
</TR>

<TR>
  <TD><B><?php echo lang("Date"); ?>:</B></TD>
  <TD>
<?php
  $day_html = "<SELECT NAME=\"day\">";
  for ($i = 1; $i <= 31; $i++)
      $day_html .= "<OPTION value=\"$i\"" . ($i == $cal_info->day ? " SELECTED" : "") . ">$i"
	 	 . "</option>\n";
  $day_html .= "</select>";

  $month_html = "<SELECT NAME=\"month\">";
  for ($i = 1; $i <= 12; $i++) {
    $m = lang(date("F", mktime(0,0,0,$i,1,$cal_info->year)));
    $month_html .= "<OPTION VALUE=\"$i\"" . ($i == $cal_info->month ? " SELECTED" : "") . ">$m"
		 . "</option>\n";
  }
  $month_html .= "</select>";

  $year_html = "<SELECT NAME=\"year\">";
  for ($i = ($cal_info->year - 1); $i < ($cal_info->year + 5); $i++) {
    $year_html .= "<OPTION VALUE=\"$i\"" . ($i == $cal_info->year ? " SELECTED" : "") . ">$i"
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
  $amsel = "CHECKED"; $pmsel = "";
  if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
     if ($cal_info->ampm == "pm") {
        $amsel = ""; $pmsel = "CHECKED";
     } else {
        $amsel = "CHECKED"; $pmsel = "";
     }
  }
?>
  <TD>
   <INPUT NAME="hour" SIZE=2 VALUE="<?php
    echo $cal_info->hour;?>" MAXLENGTH=2>:<INPUT NAME="minute" SIZE=2 VALUE="<?php echo $cal_info->minute>"0" && $cal_info->minute<"9"?"0".$cal_info->minute:$cal_info->minute; ?>" MAXLENGTH=2>
<?php
  if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
     echo "<INPUT TYPE=radio NAME=ampm VALUE=\"am\" $amsel>am\n";
     echo "<INPUT TYPE=radio NAME=ampm VALUE=\"pm\" $pmsel>pm\n";
  }
?>
</TD></TR>

<TR>
 <TD><B><?php echo lang("Duration"); ?>:</B></TD>
  <TD><INPUT NAME="duration" SIZE=3 VALUE="<?php
    !$cal_info->duration?0:$cal_info->duration; ?>"> <?php echo lang("minutes"); ?></TD>
</TR>

<TR>
  <TD><B><?php echo lang("Priority"); ?>:</B></TD>
  <TD><SELECT NAME="priority">
    <OPTION VALUE="1"<?php if ($cal_info->priority == 1) echo " SELECTED";?>><?php echo lang("Low"); ?> </option>
    <OPTION VALUE="2"<?php if ($cal_info->priority == 2 || $cal_info->priority == 0 ) echo " SELECTED";?>><?php echo lang("Medium"); ?></option>
    <OPTION VALUE="3"<?php if ($cal_info->priority == 3) echo " SELECTED";?>><?php echo lang("High"); ?></option>
  </SELECT></TD>
</TR>

<TR>
 <TD><B><?php echo lang("Access"); ?>:</B></TD>
 <TD><SELECT NAME="access">
  <OPTION VALUE="private"<?php
   if ($cal_info->access == "private" || ! $id) echo " SELECTED";?>><?php echo lang("Private"); ?></option>
  <OPTION VALUE="public"<?php
   if ($cal_info->access == "public") echo " SELECTED"; ?>><?php echo lang("Global Public"); ?></option>
  <OPTION VALUE="group"<?php
   if ($cal_info->access == "public" || strlen($cal_info->access)) echo " SELECTED";?>><?php echo lang("Group Public"); ?></option>
  </SELECT>
 </TD>
 </tr>
 <tr>

 <TD><B><?php echo lang("group access"); ?>:</B></TD>
 <TD><SELECT NAME="n_groups[]" multiple size="5">
  <?php
    $user_groups = $phpgw->accounts->read_group_names();
    for ($i=0;$i<count($user_groups);$i++) {
	echo "<option value=\"" . $user_groups[$i][0] . "\"";
	if (ereg(",".$user_groups[$i][0].",",$cal_info->groups))
	   echo " selected";
	echo ">" . $user_groups[$i][1] . "</option>\n";
    }
  ?></SELECT></TD>
</TR>


<?php
  $phpgw->db->query("select account_id,account_lid,account_lastname, account_firstname from "
  			   . "accounts where account_status !='L' and account_lid != '"
  			   . $phpgw_info["user"]["userid"] . "' and account_permissions like '%:calendar:%' "
  			   . "order by account_lastname,account_firstname,account_lid");

  if ($phpgw->db->num_rows() > 50)
     $size = 15;
  else if ($phpgw->db->num_rows() > 5)
     $size = 5;
  else
     $size = $phpgw->db->num_rows();

  echo "<TR><TD VALIGN=\"top\"><B>" . lang("Participants") . ":</B></TD>"
     . "<TD>\n<SELECT NAME=\"participants[]\" multiple size=\"$size\">\n";

  while ($phpgw->db->next_record()) {
    echo "<option value=\"" . $phpgw->db->f("account_id") . "\"";  
    if ($cal_info->participants[$phpgw->db->f("account_id")])
       echo " selected";

    echo ">" . $phpgw->common->display_fullname($phpgw->db->f("account_lid"),
    											$phpgw->db->f("account_firstname"),
    											$phpgw->db->f("account_lastname"));
    echo "</option>\n";
  }

  echo "</select><input type=\"hidden\" name=\"participants[]\" value=\""
     . $phpgw_info["user"]["account_id"] ."\">"
     . "</td></tr>\n";

?>

<tr>
 <td><b><?php echo lang("Repeat type"); ?>:</b></td>
 <td><select name="rpt_type">
 <?php
   echo "<option value=\"none\"" . (strcmp($cal_info->rpt_type,'none')==0?"selected":"") . ">"
      . lang("None") . "</option>";

   echo "<option value=\"daily\"" . (strcmp($cal_info->rpt_type,'daily')==0?"selected":"") . ">"
      . lang("Daily") . "</option>";

   echo "<option value=\"weekly\"" . (strcmp($cal_info->rpt_type,'weekly')==0?"selected":"") . ">"
      . lang("Weekly") . "</option>";

   echo "<option value=\"monthlyByDay\"".(strcmp($cal_info->rpt_type,'monthlyByDay')==0?"selected":"")
      . ">" . lang("Monthly (by day)") . "</option>";

   echo "<option value=\"monthlyByDate\"".(strcmp($cal_info->rpt_type,'monthlyByDate')==0?"checked":"")
      . "> " . lang("Monthly (by date)") . "</option>";

   echo "<option value=\"yearly\"" . (strcmp($cal_info->rpt_type,'yearly')==0?"checked":"") . ">"
      . lang("Yearly") . "</option>";
?>
  </select>
 </td>
<tr>
 <td><b><?php echo lang("Repeat End date"); ?>:</b></td>
 <td><input type=checkbox name=rpt_end_use value=y <?php
      echo ($cal_info->rpt_end?"checked":""); ?>> <?php echo lang("Use End date"); ?>

<?php
  $day_html = "<SELECT NAME=\"rpt_day\">";
  for ($i = 1; $i <= 31; $i++) {
    $day_html .= "<OPTION value=\"$i\"" . ($i == $cal_info->rpt_day ? " SELECTED" : "")
	       . ">$i</option>\n";
  }
  $day_html .= "</select>";

  $month_html = "<select name=\"rpt_month\">";
  for ($i = 1; $i <= 12; $i++) {
    $m = lang(date("F", mktime(0,0,0,$i,1,$cal_info->rpt_year)));
    $month_html .= "<OPTION VALUE=\"$i\"" . ($i == $cal_info->rpt_month ? " SELECTED" : "")
		 . ">$m</option>\n";
  }
  $month_html .= "</select>";

  $year_html = "<select name=\"rpt_year\">";
  for ($i = ($cal_info->rpt_year - 1); $i < ($cal_info->rpt_year + 5); $i++) {
    $year_html .= "<OPTION VALUE=\"$i\"" . ($i == $cal_info->rpt_year ? " SELECTED" : "") . ">$i"
       		. "</option>\n";
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
     . ($cal_info->rpt_sun?"checked":"") . "> " . lang("Sunday");
  echo "<input type=checkbox name=rpt_mon value=y "
     . ($cal_info->rpt_mon?"checked":"") . "> " . lang("Monday");
  echo "<input type=checkbox name=rpt_tue value=y "
     . ($cal_info->rpt_tue?"checked":"") . "> " . lang("Tuesday");
  echo "<input type=checkbox name=rpt_wed value=y "
     . ($cal_info->rpt_wed?"checked":"") . "> " . lang("Wednesday");
  echo "<input type=checkbox name=rpt_thu value=y "
     . ($cal_info->rpt_thu?"checked":"") . "> " . lang("Thursday");
  echo "<input type=checkbox name=rpt_fri value=y "
     . ($cal_info->rpt_fri?"checked":"") . "> " . lang("Friday");
  echo "<input type=checkbox name=rpt_sat value=y "
     . ($cal_info->rpt_sat?"checked":"") . "> " . lang("Saturday");
  ?></td>
</tr>

<tr>
 <td><b><?php echo lang("Frequency"); ?>: </b></td>
 <td>
  <input name="rpt_freq" size="4" maxlength="4" value="<?php
							echo $cal_info->rpt_freq; ?>">
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
    $phpgw->common->phpgw_footer();
?>
