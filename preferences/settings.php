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

  $phpgw_flags = array("noheader" => True, "nonavbar" => True);

  $phpgw_flags["currentapp"] = "preferences";
  include("../header.inc.php");
  if ($phpgw_info["user"]["permissions"]["anonymous"]) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/"));
     exit;
  } else if (! $submit) {
     $phpgw->common->header();
     $phpgw->common->navbar();
  }

  function display_option($text,$check,$option) {
    global $phpgw, $phpgw_info;
    if ($phpgw_info["user"]["permissions"][$check]) {
?>
      <tr>
       <td>
        <?php echo lang_pref($text); ?> ?
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
        <?php echo lang_pref("email signature"); ?>
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
      <form method="POST" action="settings.php">
       <?php echo $phpgw->form_sessionid(); ?>
       <table border=0>
       <tr>
        <td><?php echo lang_pref("max matchs per page"); ?>: </td>
        <td>
         <input name="maxmatchs" value="<?php
           echo $phpgw_info["user"]["preferences"]["maxmatchs"]; ?>" size="2">
        </td>
       </tr>
       <tr>
        <td><?php echo lang_pref("Show text on navigation icons"); ?>: </td>
        <td>
         <input type="checkbox" name="navbar_text"<?php
           if ($phpgw_info["user"]["preferences"]["navbar_text"])
              echo " checked";
           ; ?>>
        </td>
       </tr>
       <tr>
        <td><?php echo lang_pref("time zone offset"); ?>: </td>
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
         <?php echo lang_pref("This server is located in the x timezone",strftime("%Z")); ?>
        </td>
       </tr>

       <tr>
        <td><?php echo lang_pref("date format"); ?>:</td>
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
        <td><?php echo lang_pref("time format"); ?>:</td>
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
         <td><?php echo lang_pref("language"); ?></td>
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
         display_option("show current users on navigation bar","admin","show_currentusers");
         display_option("show new messages on main screen","email","mainscreen_showmail");
         display_option("show birthday reminders on main screen","addressbook","mainscreen_showbirthdays");
         
         if ($phpgw_info["user"]["permissions"]["calendar"]) {
            ?>
            <tr>
             <td><?php echo lang_pref("show high priority events on main screen"); ?> ?</td>
	     <td><input type="checkbox" name="mainscreen_showevents" value="Y" <?php if ($phpgw_info["user"]["preferences"]["mainscreen_showevents"] == "Y") echo " checked"; ?>></td>

            </tr>
<?php
            $t_weekday[$phpgw_info["user"]["preferences"]["weekdaystarts"]] = " selected";
?>
             <tr>
              <td><?php echo lang_pref("weekday starts on"); ?></td>
              <td>
               <select name="weekdaystarts">
	        <option value="Monday"<?php echo $t_weekday["monday"]; ?>><?php echo lang_common("monday"); ?></option>
	        <option value="Sunday"<?php echo $t_weekday["sunday"]; ?>><?php echo lang_common("sunday"); ?></option>
	       </select>
              </td>
<?php

            $t_workdaystarts[$phpgw_info["user"]["preferences"]["workdaystarts"]] = " selected";
?>
             <tr>
              <td><?php echo lang_pref("work day starts on"); ?></td>
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
              <td><?php echo lang_pref("work day ends on"); ?></td>
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
              <td><?php echo lang_pref("Default application"); ?></td>
	      <td><select name="default_app">
                   <option value="">&nbsp;</option>
                  <?php
 			     $db_perms = $phpgw->accounts->read_apps($phpgw_info["user"]["sessionid"]);
                    while ($permission = each($db_perms)) {
                       if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
				  echo "<option value=\"" . $permission[0] . "\"";
				  if ($phpgw_info["user"]["preferences"]["default_app"] == $permission[0]) {
					 echo " selected";
                          }
				  echo ">" . lang_common($phpgw_info["apps"][$permission[0]]["title"])
					 . "</option>";
                       }
                    }
              ?></select></td>
             </tr>

             <tr>
              <td><?php echo lang_pref("Default sorting order"); ?></td>
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

         if ($phpgw_info["user"]["permissions"]["headlines"]) {
?>
            <tr>
             <td><?php echo lang_pref("select headline news sites"); ?>:</td>
<?php
	     echo "<td><select name=\"headlines[]\" multiple size=5>\n";

               $phpgw->db->query("select * from users_headlines where owner='"
				   . $phpgw->session->loginid . "'");
	       while ($phpgw->db->next_record())
		 $users_headlines[$phpgw->db->f("site")] = " selected";


	       $phpgw->db->query("SELECT con,display FROM news_site ORDER BY display asc");
	       while ($phpgw->db->next_record()) {
                 echo "<option value=\"" . $phpgw->db->f("con") . "\""
                    . $users_headlines[$phpgw->db->f("con")] . ">"
			. $phpgw->db->f("display") . "</option>";
	       }
               echo "</select></td>\n";
?>
            </tr>             
<?php
          }
?>

       <tr>
        <td colspan="2" align="center">
         <input type="submit" name="submit" value="<?php echo lang_common("submit"); ?>">
        </td>
       </tr>
       </table>
      </form>

 <?php
  } else {
     $phpgw->db->query("delete from preferences where owner='" . $phpgw->session->loginid
			. "' AND name != 'theme'");

     // If they don't have permissions to the headlines,
     // we don't need to lock the table.
     if ($phpgw_info["user"]["permissions"]["headlines"]) {
        $phpgw->db->lock(array("preferences","users_headlines"));
     } else {
        $phpgw->db->lock("preferences");
     }

     $phpgw->common->preferences_add($phpgw->session->loginid,"maxmatchs");
     $phpgw->common->preferences_add($phpgw->session->loginid,"tz_offset");
     $phpgw->common->preferences_add($phpgw->session->loginid,"dateformat");
     $phpgw->common->preferences_add($phpgw->session->loginid,"timeformat");
     $phpgw->common->preferences_add($phpgw->session->loginid,"lang");
     $phpgw->common->preferences_add($phpgw->session->loginid,"default_sorting");
     $phpgw->common->preferences_add($phpgw->session->loginid,"default_app");

     if ($navbar_text) {
        $phpgw->common->preferences_add($phpgw->session->loginid,"navbar_text");
     }

     if ($phpgw_info["user"]["permissions"]["admin"]) {
        if ($show_currentusers) {
           $phpgw->common->preferences_add($phpgw->session->loginid,"show_currentusers");
        }
     }

     if ($phpgw_info["user"]["permissions"]["email"]) {
        if ($mainscreen_showmail) {
           $phpgw->common->preferences_add($phpgw->session->loginid,"mainscreen_showmail");
        }
        $phpgw->common->preferences_add($phpgw->session->loginid,"email_sig");
     }

     if ($phpgw_info["user"]["permissions"]["addressbook"]) {
        if ($mainscreen_showbirthdays) {
           $phpgw->common->preferences_add($phpgw->session->loginid,"mainscreen_showbirthdays");
        }
     }

     if ($phpgw_info["user"]["permissions"]["calendar"]) {
        $phpgw->common->preferences_add($phpgw->session->loginid,"weekdaystarts");
        $phpgw->common->preferences_add($phpgw->session->loginid,"workdaystarts");
        $phpgw->common->preferences_add($phpgw->session->loginid,"workdayends");
        if ($mainscreen_showevents) {
           $phpgw->common->preferences_add($phpgw->session->loginid,"mainscreen_showevents");
        }
     }

     if ($phpgw_info["user"]["permissions"]["headlines"]) {
        include($phpgw_info["server"]["server_root"] . "/headlines/inc/functions.inc.php");
	headlines_update($phpgw->session->loginid,$headlines);
     }

     $phpgw->db->unlock();

     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]
	  . "/preferences/"));
  }
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");


