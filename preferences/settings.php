<?php
  /**************************************************************************\
  * phpGroupWare - preferences                                               *
  * http://www.phpgroupware.org                                              *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  if ($submit) {
     $phpgw_info["flags"] = array("nonavbar" => True, "noheader" => True);  
  }
  $phpgw_info["flags"]["currentapp"] = "preferences";

  include("../header.inc.php");

  if (! $submit) {
  
     ?>
      <form method="POST" action="<?php echo $phpgw->link("settings.php"); ?>">
       <table border=0>
       <tr>
        <td><?php echo lang("max matchs per page"); ?>: </td>
        <td>
         <input name="maxmatchs" value="<?php
           echo $phpgw_info["user"]["preferences"]["common"]["maxmatchs"]; ?>" size="2">
        </td>
       </tr>
       <tr>
        <td><?php echo lang("Show text on navigation icons"); ?>: </td>
        <td>
         <input type="checkbox" name="navbar_text"<?php
           if ($phpgw_info["user"]["preferences"]["common"]["navbar_text"])
              echo " checked";
           ; ?>>
        </td>
       </tr>
       <tr>
        <td><?php echo lang("time zone offset"); ?>: </td>
        <td>
         <select name="tz_offset"><?php
           for ($i = -23; $i<24; $i++) {
               echo "<option value=\"$i\"";
               if ($i == $phpgw_info["user"]["preferences"]["common"]["tz_offset"])
                  echo " selected";
               if ($i < 1)
                  echo ">$i</option>\n";
               else
                  echo ">+$i</option>\n";
           }
         ?></select>
         <?php echo lang("This server is located in the x timezone",strftime("%Z")); ?>
        </td>
       </tr>

       <tr>
        <td><?php echo lang("date format"); ?>:</td>
        <td>
         <?php $df[$phpgw_info["user"]["preferences"]["common"]["dateformat"]] = " selected"; ?>
         <select name="dateformat">
          <option value="m/d/Y"<?php echo $df["m/d/Y"]; ?>>m/d/y</option>
          <option value="m-d-Y"<?php echo $df["m-d-Y"]; ?>>m-d-y</option>
          <option value="m.d.Y"<?php echo $df["m.d.Y"]; ?>>m.d.y</option>

          <option value="Y/d/m"<?php echo $df["Y/d/m"]; ?>>y/d/m</option>
          <option value="Y-d-m"<?php echo $df["Y-d-m"]; ?>>y-d-m</option>
          <option value="Y.d.m"<?php echo $df["Y.d.m"]; ?>>y.d.m</option>

          <option value="Y/m/d"<?php echo $df["Y/m/d"]; ?>>y/m/d</option>
          <option value="Y-m-d"<?php echo $df["Y-m-d"]; ?>>y-m-d</option>
          <option value="Y.m.d"<?php echo $df["Y.m.d"]; ?>>y.m.d</option>

          <option value="d/m/Y"<?php echo $df["d/m/Y"]; ?>>d/m/y</option>
	  <option value="d-m-Y"<?php echo $df["d-m-Y"]; ?>>d-m-y</option>
	  <option value="d.m.Y"<?php echo $df["d.m.Y"]; ?>>d.m.y</option>
         </select>
        </td>
       </tr>
       <tr>
        <td><?php echo lang("time format"); ?>:</td>
        <td><?php
            $timeformat_select[$phpgw_info["user"]["preferences"]["common"]["timeformat"]] = " selected";
            echo "<select name=\"timeformat\">"
               . "<option value=\"12\"$timeformat_select[12]>12 Hour</option>"
               . "<option value=\"24\"$timeformat_select[24]>24 Hour</option>"
	       . "</select>\n";
          ?>
        </td>
       </tr>
       <tr>
         <td><?php echo lang("language"); ?></td>
         <td>
          <?php $lang_select[$phpgw_info["user"]["preferences"]["common"]["lang"]] = " selected"; ?>
          <select name="lang">
           <option value="en"<?php echo $lang_select["en"]; ?>>English</option>
           <option value="de"<?php echo $lang_select["de"]; ?>>Deutsch</option>
           <option value="da"<?php echo $lang_select["da"]; ?>>Danish</option>
           <option value="sp"<?php echo $lang_select["sp"]; ?>>Spanish</option>
           <option value="br"<?php echo $lang_select["br"]; ?>>Brazilian Portuguese</option>
           <option value="no"<?php echo $lang_select["no"]; ?>>Norwegien</option>
           <option value="it"<?php echo $lang_select["it"]; ?>>Italian</option>
           <option value="fr"<?php echo $lang_select["fr"]; ?>>French</option>
           <option value="nl"<?php echo $lang_select["nl"]; ?>>Dutch</option>
           <option value="ko"<?php echo $lang_select["ko"]; ?>>Korean</option>
           <option value="cs"<?php echo $lang_select["cs"]; ?>>Czechoslovakian</option>
           <option value="sv"<?php echo $lang_select["sv"]; ?>>Swedish</option>
          </select>
         </td>
       </tr>
       <?php
         if ($phpgw_info["user"]["apps"]["admin"]) {
            echo '<tr><td>' . lang("show current users on navigation bar") . '</td><td>'
               . '<input type="checkbox" name="<?php echo $option; ?>" value="True"';
            if ($phpgw_info["user"]["preferences"]["common"]["show_currentusers"]) {
               echo " checked";
            }
            echo "></td></tr>";
         }
?>        
       <tr>
        <td><?php echo lang("Default application"); ?></td>
        <td>
         <select name="default_app">
          <option value="">&nbsp;</option>
           <?php
 			$db_perms = $phpgw->accounts->read_apps($phpgw_info["user"]["userid"]);
             while ($permission = each($db_perms)) {
               if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
				  echo "<option value=\"" . $permission[0] . "\"";
				  if ($phpgw_info["user"]["preferences"]["common"]["default_app"] == $permission[0]) {
					 echo " selected";
                  }
				  echo ">" . lang($phpgw_info["apps"][$permission[0]]["title"])
					 . "</option>";
               }
             }
           ?></select>
          </td>
         </tr>

         <tr>
          <td colspan="2" align="center">
           <input type="submit" name="submit" value="<?php echo lang("submit"); ?>">
          </td>
         </tr>
       </table>
      </form>

 <?php
  } else {
     $phpgw->common->preferences_delete("byappnotheme",$phpgw_info["user"]["account_id"],"common");

     $phpgw->db->lock("preferences");

     $phpgw->common->preferences_add($phpgw_info["user"]["account_id"],"maxmatchs","common");
     $phpgw->common->preferences_add($phpgw_info["user"]["account_id"],"tz_offset","common");
     $phpgw->common->preferences_add($phpgw_info["user"]["account_id"],"dateformat","common");
     $phpgw->common->preferences_add($phpgw_info["user"]["account_id"],"timeformat","common");
     $phpgw->common->preferences_add($phpgw_info["user"]["account_id"],"lang","common");
     $phpgw->common->preferences_add($phpgw_info["user"]["account_id"],"default_app","common");

     if ($navbar_text) {
        $phpgw->common->preferences_add($phpgw_info["user"]["account_id"],"navbar_text","common");
     }

     if ($phpgw_info["user"]["apps"]["admin"]) {
        if ($show_currentusers) {
           $phpgw->common->preferences_add($phpgw_info["user"]["account_id"],"show_currentusers","common");
        }
     }

     $phpgw->db->unlock();

     Header("Location: " . $phpgw->link("index.php"));
  }
  $phpgw->common->phpgw_footer();
?>
