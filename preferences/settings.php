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
          <select name="lang">
          <?php
            $phpgw->db->query("select preference_value from preferences where preference_owner='"
                            . $phpgw_info["user"]["account_id"] . "' and preference_name='lang' and "
                            . "preference_appname='common'",__LINE__,__FILE__);
            $phpgw->db->next_record();

            if ($phpgw->db->f("preference_value") == "") {
               $phpgw_info["user"]["preferences"]["common"]["lang"] = "en";
            }
            $lang_select[$phpgw_info["user"]["preferences"]["common"]["lang"]] = " selected"; 
            $strSql = "SELECT lang_id, lang_name FROM languages WHERE available = 'Yes'";
            $phpgw->db->query($strSql);
            while ($phpgw->db->next_record()) {
                echo "<option value=\"" . $phpgw->db->f("lang_id") . "\"";
                if ($phpgw_info["user"]["preferences"]["common"]["lang"]) {
                   if ($phpgw->db->f("lang_id") == $phpgw_info["user"]["preferences"]["common"]["lang"]) {
                      echo " selected";
                   }
                } elseif ($phpgw->db->f("lang_id") == "en") {
                   echo " selected";
                }
                echo ">" . $phpgw->db->f("lang_name") . "</option>";
            }
            ?>
          </select>
         </td>
       </tr>
       <?php
         if ($phpgw_info["user"]["apps"]["admin"]) {
            echo '<tr><td>' . lang("show current users on navigation bar") . '</td><td>'
               . '<input type="checkbox" name="show_currentusers" value="True"';
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
        <td><?php echo lang("Currency"); ?></td>
        <td>
         <?php
           if (! isset($phpgw_info["user"]["preferences"]["common"]["currency"])) {
              $phpgw_info["user"]["preferences"]["common"]["currency"] = '$';
           }
         ?>
         <input name="currency" value="<?php echo $phpgw_info["user"]["preferences"]["common"]["currency"]; ?>">
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
     $phpgw->preferences->preferences_delete("byappnotheme",$phpgw_info["user"]["account_id"],"common");

     $phpgw->db->lock("preferences");

     $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"maxmatchs","common");
     $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"tz_offset","common");
     $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"dateformat","common");
     $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"timeformat","common");
     $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"lang","common");
     $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"default_app","common");
     $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"currency","common");

     if ($navbar_text) {
        $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"navbar_text","common");
     }

     if ($phpgw_info["user"]["apps"]["admin"]) {
        if ($show_currentusers) {
           $phpgw->preferences->preferences_add($phpgw_info["user"]["account_id"],"show_currentusers","common");
        }
     }

     $phpgw->db->unlock();

     Header("Location: " . $phpgw->link("index.php"));
  }
  $phpgw->common->phpgw_footer();
?>
