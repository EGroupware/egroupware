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

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);

  $phpgw_info["flags"]["currentapp"] = "preferences";
  include("../header.inc.php");
  if ($phpgw_info["user"]["permissions"]["anonymous"]) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/"));
     exit;
  } else if (! $submit) {
     $phpgw->common->header();
     $phpgw->common->navbar();
  }

  function display_option($text,$check,$option,$indent) {
    global $phpgw, $phpgw_info;
    if ($phpgw_info["user"]["apps"][$check]) {
?>
      <tr>
       <td>
        <?php
         for ($i=0; $i < $indent; $i++, print '<blockquote>') {};
         echo lang($text);
         for ($i=0; $i < $indent; $i++, print '</blockquote>') {};
         ?>
       </td>
       <td>
        <input type="checkbox" name="<?php echo $option; ?>" value="True"<?php if ($phpgw_info["user"]["preferences"][$option]) echo " checked"; ?>>
       </td>
      </tr>
<?php
      if ($check == "email") {
?>
      <tr>
       <td>
        <?php echo lang("email signature"); ?>
       </td>
       <td>
        <textarea name="email_sig" rows="3" cols="30"><?php echo $phpgw_info["user"]["preferences"]["email_sig"]; ?></textarea>
       </td>
      </tr>
<?php
      }
    }
  }

  if (! $submit) {
     ?>
      <form method="POST" action="<?php echo $phpgw->link("settings.php"); ?>">
       <table border=0>
       <tr>
        <td><?php echo lang("max matchs per page"); ?>: </td>
        <td>
         <input name="maxmatchs" value="<?php
           echo $phpgw_info["user"]["preferences"]["maxmatchs"]; ?>" size="2">
        </td>
       </tr>
       <tr>
        <td><?php echo lang("Show text on navigation icons"); ?>: </td>
        <td>
         <input type="checkbox" name="navbar_text"<?php
           if ($phpgw_info["user"]["preferences"]["navbar_text"])
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
               if ($i == $phpgw_info["user"]["preferences"]["tz_offset"])
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
         <?php $df[$phpgw_info["user"]["preferences"]["dateformat"]] = " selected"; ?>
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
            $timeformat_select[$phpgw_info["user"]["preferences"]["timeformat"]] = " selected";
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
          <?php $lang_select[$phpgw_info["user"]["preferences"]["lang"]] = " selected"; ?>
          <select name="lang">
           <option value="en"<?php echo $lang_select["en"]; ?>>English</option>
           <option value="de"<?php echo $lang_select["de"]; ?>>Deutsch</option>
           <option value="dk"<?php echo $lang_select["dk"]; ?>>Danish</option>
           <option value="sp"<?php echo $lang_select["sp"]; ?>>Spanish</option>
           <option value="br"<?php echo $lang_select["br"]; ?>>Brazilian Portuguese</option>
           <option value="no"<?php echo $lang_select["no"]; ?>>Norwegien</option>
           <option value="it"<?php echo $lang_select["it"]; ?>>Italian</option>
           <option value="fr"<?php echo $lang_select["fr"]; ?>>French</option>
           <option value="nl"<?php echo $lang_select["nl"]; ?>>Dutch</option>
           <option value="kr"<?php echo $lang_select["kr"]; ?>>Korean</option>
          </select>
         </td>
       </tr>
<?php
         display_option("show current users on navigation bar","admin","show_currentusers",0);
         display_option("show new messages on main screen","email","mainscreen_showmail",0);
         display_option("show birthday reminders on main screen","addressbook","mainscreen_showbirthdays",0);
         
         if ($phpgw_info["user"]["apps"]["calendar"]) {
            ?>
            <tr>
             <td><?php echo lang("show high priority events on main screen"); ?> ?</td>
	     <td><input type="checkbox" name="mainscreen_showevents" value="Y" <?php if ($phpgw_info["user"]["preferences"]["mainscreen_showevents"] == "Y") echo " checked"; ?>></td>

            </tr>
<?php
            $t_weekday[$phpgw_info["user"]["preferences"]["weekdaystarts"]] = " selected";
?>
             <tr>
              <td><?php echo lang("weekday starts on"); ?></td>
              <td>
               <select name="weekdaystarts">
	        <option value="Monday"<?php echo $t_weekday["monday"]; ?>><?php echo lang("monday"); ?></option>
	        <option value="Sunday"<?php echo $t_weekday["sunday"]; ?>><?php echo lang("sunday"); ?></option>
	       </select>
              </td>
<?php

            $t_workdaystarts[$phpgw_info["user"]["preferences"]["workdaystarts"]] = " selected";
?>
             <tr>
              <td><?php echo lang("work day starts on"); ?></td>
              <td>
               <select name="workdaystarts">
	       <?php
	         for ($i=0; $i<24; $i++)
	             echo "<option value=\"$i\"" . $t_workdaystarts[$i] . ">"
		        . $phpgw->common->formattime($i+1,"00") . "</option>";
	       ?>
               </select>
              </td>
             </tr>
            <?php $t_workdayends[$phpgw_info["user"]["preferences"]["workdayends"]] = " selected"; ?>
             <tr>
              <td><?php echo lang("work day ends on"); ?></td>
              <td>
               <select name="workdayends">
	        <?php
		  for ($i=0; $i<24; $i++)
		      echo "<option value=\"$i\"" . $t_workdayends[$i] . ">"
		         . $phpgw->common->formattime($i+1,"00") . "</option>";
	        ?>
               </select>
              </td>
             </tr>

             <tr>
              <td><?php echo lang("Default application"); ?></td>
	         <td><select name="default_app">
                   <option value="">&nbsp;</option>
                  <?php
 			     $db_perms = $phpgw->accounts->read_apps($phpgw_info["user"]["userid"]);
                    while ($permission = each($db_perms)) {
                       if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
				  echo "<option value=\"" . $permission[0] . "\"";
				  if ($phpgw_info["user"]["preferences"]["default_app"] == $permission[0]) {
					 echo " selected";
                          }
				  echo ">" . lang($phpgw_info["apps"][$permission[0]]["title"])
					 . "</option>";
                       }
                    }
              ?></select></td>
             </tr>

             <tr>
              <td><?php echo lang("Default sorting order"); ?></td>
	      <td><?php
                    $default_order_display[$phpgw_info["user"]["preferences"]["default_sorting"]] = " selected"; ?>
                  <select name="default_sorting">
             	   <option value="old_new"<?php echo $default_order_display["old_new"]; ?>>oldest -> newest</option>   
             	   <option value="new_old"<?php echo $default_order_display["new_old"]; ?>>newest -> oldest</option>
                  </select>
              </td>
             </tr>
<?php
         }
         if ($phpgw_info["user"]["apps"]["addressbook"]) {
             echo "<tr><td>Addressbook columns :</td><tr>";
             $abc = get_abc();		# AddressBook Columns
             while (list($col, $descr) = each($abc)) {
                 display_option($descr,"addressbook","addressbook_view_".$col,1);
             }
         }

         if ($phpgw_info["user"]["apps"]["headlines"]) {
?>
<?php
          }
?>

       <tr>
        <td colspan="2" align="center">
         <input type="submit" name="submit" value="<?php echo lang("submit"); ?>">
        </td>
       </tr>
       </table>
      </form>

 <?php
  } else {
     $phpgw->db->query("delete from preferences where owner='" . $phpgw_info["user"]["userid"]
		           . "' AND name != 'theme'");

     // If they don't have permissions to the headlines,
     // we don't need to lock the table.
     if ($phpgw_info["user"]["apps"]["headlines"]) {
        $phpgw->db->lock(array("preferences","users_headlines"));
     } else {
        $phpgw->db->lock("preferences");
     }

     $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"maxmatchs");
     $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"tz_offset");
     $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"dateformat");
     $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"timeformat");
     $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"lang");
     $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"default_sorting");
     $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"default_app");

     if ($navbar_text) {
        $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"navbar_text");
     }

     if ($phpgw_info["user"]["apps"]["admin"]) {
        if ($show_currentusers) {
           $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"show_currentusers");
        }
     }

     if ($phpgw_info["user"]["apps"]["email"]) {
        if ($mainscreen_showmail) {
           $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"mainscreen_showmail");
        }
        $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"email_sig");
     }

     if ($phpgw_info["user"]["apps"]["addressbook"]) {
        if ($mainscreen_showbirthdays) {
           $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"mainscreen_showbirthdays");
        }
        $abc = get_abc();	# AddressBook Columns
        while (list($col, $descr) = each($abc)) {
            $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"addressbook_view_".$col);
        }
     }

     if ($phpgw_info["user"]["apps"]["calendar"]) {
        $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"weekdaystarts");
        $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"workdaystarts");
        $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"workdayends");
        if ($mainscreen_showevents) {
           $phpgw->common->preferences_add($phpgw_info["user"]["userid"],"mainscreen_showevents");
        }
     }

     if ($phpgw_info["user"]["apps"]["headlines"]) {
        include($phpgw_info["server"]["server_root"] . "/headlines/inc/functions.inc.php");
	   headlines_update($phpgw_info["user"]["userid"],$headlines);
     }

     $phpgw->db->unlock();

     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]
	  . "/preferences/"));
  }
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

?>
