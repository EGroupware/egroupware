<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("currentapp" => "calendar", "enable_nextmatchs_class" => True, "noheader" => True, "nonavbar" => True, "noappheader" => True, "noappfooter" => True);
  include("../header.inc.php");

  if ($submit) {
     $phpgw->preferences->change("calendar","weekdaystarts");
     $phpgw->preferences->change("calendar","workdaystarts");
     $phpgw->preferences->change("calendar","workdayends");
     $phpgw->preferences->change("calendar","defaultcalendar");
     $phpgw->preferences->change("calendar","defaultfilter");
     if ($mainscreen_showevents) {
        $phpgw->preferences->change("calendar","mainscreen_showevents");
     } else {
        $phpgw->preferences->delete("calendar","mainscreen_showevents");
     }
     $phpgw->preferences->commit();
     
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/preferences/index.php"));
     $phpgw->common->phpgw_exit();
  }

  $phpgw->common->phpgw_header();
  $phpgw->common->navbar();

  if ($totalerrors) {  
     echo "<p><center>" . $phpgw->common->error_list($errors) . "</center>";
  }

  echo "<p><b>" . lang("Calendar preferences") . ":" . "</b><hr><p>";

?>
 <form action="<?php echo $phpgw->link(); ?>" method="POST">
  <table border="0" align="center" width="50%">
  <tr bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>">
   <td colspan="2">&nbsp;</td>
  </tr>
  <?php $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color); ?>
  <tr bgcolor="<?php echo $tr_color; ?>">
   <td><?php echo lang("show day view on main screen"); ?> ?</td>
   <td align="center"><input type="checkbox" name="mainscreen_showevents" value="Y" <?php
	 if ($phpgw_info["user"]["preferences"]["calendar"]["mainscreen_showevents"] == "Y") echo " checked"; 
   ?>>
   </td>
  </tr>

  <?php
    $t_weekday[$phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"]] = " selected";
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
  ?>
  <tr bgcolor="<?php echo $tr_color; ?>">
   <td><?php echo lang("weekday starts on"); ?></td>
   <td align="center">
    <select name="weekdaystarts">
	 <option value="Monday"<?php echo $t_weekday["Monday"]; ?>><?php echo lang("Monday"); ?></option>
	 <option value="Sunday"<?php echo $t_weekday["Sunday"]; ?>><?php echo lang("Sunday"); ?></option>
	</select>
  </td>
<?php
  $t_workdaystarts[$phpgw_info["user"]["preferences"]["calendar"]["workdaystarts"]] = " selected";
  $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
?>
  <tr bgcolor="<?php echo $tr_color; ?>">
   <td><?php echo lang("work day starts on"); ?></td>
   <td align="center">
    <select name="workdaystarts">
	 <?php
	   for ($i=0; $i<24; $i++)
	       echo "<option value=\"$i\"" . $t_workdaystarts[$i] . ">"
		      . $phpgw->common->formattime($i+1,"00") . "</option>";
	 ?>
    </select>
   </td>
  </tr>
  <?php
    $t_workdayends[$phpgw_info["user"]["preferences"]["calendar"]["workdayends"]] = " selected";
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
  ?>
  <tr bgcolor="<?php echo $tr_color; ?>">
   <td><?php echo lang("work day ends on"); ?></td>
   <td align="center">
    <select name="workdayends">
	 <?php
	   for ($i=0; $i<24; $i++) {
		   echo "<option value=\"$i\"" . $t_workdayends[$i] . ">"
		      . $phpgw->common->formattime($i+1,"00") . "</option>";
	   }
	 ?>
    </select>
   </td>
  </tr>
  <?php
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
  ?>
  <tr bgcolor="<?php echo $tr_color; ?>">
   <td><?php echo lang("default calendar view"); ?></td>
   <td align="center">
    <select name="defaultcalendar">
     <?php
       $selected = array();
       $selected[$phpgw_info["user"]["preferences"]["common"]["defaultcalendar"]] = " selected";
       if (! isset($phpgw_info["user"]["preferences"]["common"]["defaultcalendar"]) || $phpgw_info["user"]["preferences"]["common"]["defaultcalendar"] == "index.php") {
          $selected["index.php"] = " selected";
       }
     ?>
     <option value="year.php"<?php echo $selected["year.php"] . ">" . lang("Yearly"); ?></option>
     <option value="index.php"<?php echo $selected["index.php"] . ">" . lang("Monthly"); ?></option>
     <option value="week.php"<?php echo $selected["week.php"]  . ">" . lang("Weekly"); ?></option>
     <option value="day.php"<?php echo $selected["day.php"] . ">" . lang("Daily"); ?></option>
    </select>
   </td>
  </tr>
  <?php
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
  ?>
  <tr bgcolor="<?php echo $tr_color; ?>">
   <td><?php echo lang("default calendar filter"); ?></td>
   <td align="center">
    <select name="defaultfilter">
     <?php
       $selected = array();
       $selected[$phpgw_info["user"]["preferences"]["calendar"]["defaultfilter"]] = " selected";
       if (! isset($phpgw_info["user"]["preferences"]["calendar"]["defaultfilter"]) || $phpgw_info["user"]["preferences"]["calendar"]["defaultfilter"] == "private") {
          $selected["private"] = " selected";
       }
     ?>
     <option value="all"<?php echo $selected["all"].">".lang("all"); ?></option>
     <option value="private"<?php echo $selected["private"]. ">".lang("private only"); ?></option>
     <option value="public"<?php echo $selected["public"].">".lang("global public only"); ?></option>
     <option value="group"<?php echo $selected["group"].">".lang("group public only"); ?></option>
     <option value="private+public"<?php echo $selected["private+public"].">".lang("private and global public"); ?></option>
     <option value="private+group"<?php echo $selected["private+group"].">".lang("private and group public"); ?></option>
     <option value="public+group"<?php echo $selected["public+group"].">".lang("global public and group public") ?></option>
    </select>
   </td>
  </tr>
  <tr><td align="center"><input type="submit" name="submit" value="<?php echo lang("submit"); ?>"></td></tr>
 </table>
</form>

<?php include($phpgw_info["server"]["api_inc"] . "/footer.inc.php"); ?>
