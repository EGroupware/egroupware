<?php
  /**************************************************************************\
  * phpGroupWare - Core Setup                                                *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  * --------------------------------------------                             *
  *  This file handles the setup for the core of phpGroupWare,               *
  *  it DOES NOT currently follow the Setup class' model because it wants    *
  *  to interct with the user. This will be fixed at some point in the       *
  *  (hopefully) near future.                                                *
  *  An upgrade or install of the core will _always_ happen before           *
  *  any of the additional applications are dealt with.                      *
  *                                                                          *
  \**************************************************************************/

  /* $Id$ */

  // $ok is chacked after including this file,
  // if false then no additional work is performed.
  // if true, ../index.php falls through to app setup
  $ok = true;

  $basedir = $phpgw_info["server"]["server_root"]."/setup";

  if (!isset($oldversion)){
    @$db->query("select app_version from applications where app_name='admin'");
    @$db->next_record();
    $oldversion = $db->f("app_version");
  }

  if ($action != "Delete my old tables" && ! isset($oldversion)) {
     setup_header();
     echo "<br>";
  }
  
  if (PHP_VERSION < "3.0.16") {
     echo "You appear to be running an old version of PHP.  It its recommend that you upgrade "
        . "to a new version.  Older version of PHP might not run phpGroupWare correctly, if at all.";
  }

  /* Database setup */
  switch($action){
    case "regularversion":
      echo "<html><head><title>phpGroupWare Setup</title></head>\n";
      echo "<body bgcolor='#ffffff'>\n"; 
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Analysis</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>You appear to be running version $oldversion of phpGroupWare.<br>\n";
      echo "    We will automaticly update your tables/records to ".$phpgw_info["server"]["version"].", but we highly recommend backing up your tables incase the script causes damage to your data.\n";
      echo "    These automated scripts can easily destroy your data. Please backup before going any further!</td>\n";
      echo "  </tr>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>";
      echo "      <form method=\"POST\" action=\"$PHP_SELF\">\n";
      echo "      <input type=\"hidden\" name=\"oldversion\" value=\"".$oldversion."\">\n";
      echo "      <input type=\"hidden\" name=\"useglobalconfigsettings\">\n";
      echo "      <input type=\"submit\" name=\"action\" value=\"Upgrade\">\n";
      echo "      <input type=\"submit\" name=\"action\" value=\"Delete my old tables\">\n";
      echo "      </form>\n";
      echo "      <form method=\"POST\" action=\"config.php\">\n";
      echo "      <input type=\"submit\" name=\"action\" value=\"Dont touch my data\">\n";
      echo "      </form>\n";
      echo "    </td>";
      echo "  </tr>\n";
      echo "</table>\n";
      echo "</body></html>\n";
      // Prevent app setup from running
      $ok = false;

      break;
    case "prebetaversion":
      echo "<html><head><title>phpGroupWare Setup</title></head>\n";
      echo "<body bgcolor='#ffffff'>\n"; 
      echo "<body bgcolor='#ffffff'>\n"; 
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Analysis</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>You appear to be running a pre-beta version of phpGroupWare<br>\n";
      echo "    We are providing an automated upgrade system, but we highly recommend backing up your tables incase the script causes damage to your data.<br>\n";
      echo "    These automated scripts can easily destroy your data. Please backup before going any further!</td>\n";
      echo "  </tr>\n";
      echo "</table>\n";
?>
      <form method="POST" action="<?php $PHP_SELF?>">
      <table border="0" align="center">
        <tr bgcolor="486591">
          <td colspan="2"><font color="fefefe">&nbsp;<b>Upgrade information</b></font></td>
        </tr>
        <tr bgcolor="e6e6e6">
          <td>Select your old version:</td>
          <td>
            <select name="oldversion">
              <option value="7122000">7122000</option>
              <option value="8032000">8032000</option>
              <option value="8072000">8072000</option>
              <option value="8212000">8212000</option>
              <option value="9052000">9052000</option>
              <option value="9072000">9072000</option>
              <option value="9262000">9262000</option>
              <option value="0_9_1">0.9.1</option>
              <option value="0_9_2">0.9.2</option>
            </select>
          </td>
        </tr>
        <tr bgcolor="e6e6e6">
          <td>Port old globalconfig settings.</td>
          <td><input type="checkbox" name="useglobalconfigsettings"></td>
        </tr>
        <tr>
          <td colspan="2" align="center"><input type="submit" name="action" value="Upgrade"></td>
        </tr>
        <tr>
          <td colspan="2" align="center"><input type="submit" name="action" value="Delete my old tables"></td>
        </tr>
      </table>
      </form>
<?php
      // Prevent app setup from running
      $ok = false;

      break;
    case "Delete my old tables":
      echo "<html><head><title>phpGroupWare Setup</title></head>\n";
      echo "<body bgcolor='#ffffff'>\n"; 
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Information</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>At your request, this script is going to take the evil action of deleting your existing tables and re-creating them in the new format.</td>\n";
      echo "  </tr>\n";
      $db->Halt_On_Error = "report";
      $currentver = "drop";
      include ($basedir."/sql/common_main.inc.php");
      $db->Halt_On_Error = "no";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Status</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>If you did not recieve any serious errors, your tables have been created.</td>\n";
      echo "  </tr>\n";
      echo "</table>\n";
      break;
    case "Upgrade":
      echo "<html><head><title>phpGroupWare Setup</title></head>\n";
      echo "<body bgcolor='#ffffff'>\n"; 
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Information</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>At your request, this script is going to attempt to upgrade your old tables to the new format.</td>\n";
      echo "  </tr>\n";
      echo "</table>\n";
      $currentver = $oldversion;
      $db->Halt_On_Error = "report";
      include ($basedir."/sql/common_main.inc.php");
      $db->Halt_On_Error = "no";
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Status</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>If you did not recieve any serious errors, your tables *should* have been ";
      echo "        updated (no warranty on data integrity).</td>\n";
      echo "  </tr>\n";
      echo "</table>\n";
      break;      
    default:
      if (isset($oldversion)){
         if ($phpgw_info["server"]["version"] != $oldversion){
            Header("Location: $PHP_SELF?action=regularversion");
      	  $ok = false;
         }
      }else{
        @$db->query("select * from config");
        if (@$db->num_rows() == 0){
          @$db->query("select * from accounts");
          if (@$db->num_rows() == 0){
            echo "<html><head><title>phpGroupWare Setup</title></head>\n";
            echo "<body bgcolor='#ffffff'>\n"; 
            echo "<table border=\"0\" align=\"center\">\n";
            echo "  <tr bgcolor=\"486591\">\n";
            echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Analysis</b></font></td>\n";
            echo "  </tr>\n";
            echo "  <tr bgcolor=\"e6e6e6\">\n";
            echo "    <td>You appear to be running a new install of phpGroupWare, so the tables will be created for you.</td>\n";
            echo "  </tr>\n";
            $db->Halt_On_Error = "report";
            $currentver = "new";
            include ($basedir."/sql/common_main.inc.php");
            $db->Halt_On_Error = "no";
            echo "  <tr bgcolor=\"486591\">\n";
            echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Status</b></font></td>\n";
            echo "  </tr>\n";
            echo "  <tr bgcolor=\"e6e6e6\">\n";
            echo "    <td>If you did not recieve any errors, your tables have been created.<br>\n";
            echo "  </tr>\n";
            echo "</table>\n";
          }else{
            Header("Location: $PHP_SELF?action=prebetaversion");
    	    $ok = false;
          }
        }else{
          echo "<table border=\"0\" align=\"center\">\n";
          echo "  <tr bgcolor=\"486591\">\n";
          echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b> phpGroupware Core Analysis</b></font></td>\n";
          echo "  </tr>\n";
          echo "  <tr bgcolor=\"e6e6e6\">\n";
          echo "    <td>Your database seems to be current.</td>\n";
          echo "  </tr>\n";
          echo "</table>\n";
        }
      }
    }

  if (!$ok) {
    echo "</body></html>";
  }

  // Leave php close tag off, don't want to mess up later Header() calls
