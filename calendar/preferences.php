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

  $phpgw_info["flags"] = array("currentapp" => "calendar", "enable_calendar_class" => True, 
                                "enable_nextmatchs_class" => True, 
                                "noheader" => True, "nonavbar" => True,
  							                "nocalendarheader" => True, "nocalendarfooter" => True);
  include("../header.inc.php");

  if ($submit) {
     $phpgw->preferences->preferences_delete("byapp",$phpgw_info["user"]["account_id"],"calendar");

     $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"weekdaystarts","calendar");
     $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"workdaystarts","calendar");
     $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"workdayends","calendar");
     if ($mainscreen_showevents) {
        $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"mainscreen_showevents","calendar");
     }
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/preferences/index.php"));
     exit;
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
   <td><?php echo lang("show high priority events on main screen"); ?> ?</td>
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
  <tr><td align="center"><input type="submit" name="submit" value="<?php echo lang("submit"); ?>"></td></tr>
 </table>
</form>

<?php include($phpgw_info["server"]["api_dir"] . "/footer.inc.php"); ?>
