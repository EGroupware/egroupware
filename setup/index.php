<?
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("../header.inc.php");

  $phpgw_info["server"]["api_dir"] = $phpgw_info["server"]["include_root"]."/phpgwapi";
  
  /* Database setup */
  switch($phpgw_info["server"]["db_type"]){
    case "postgresql":
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_pgsql.inc.php");
      break;
    case "oracle":
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_oracle.inc.php");
      break;      
    default:
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_mysql.inc.php");
  }

  $db	            = new db;
  $db->Host	    = $phpgw_info["server"]["db_host"];
  $db->Type	    = $phpgw_info["server"]["db_type"];
  $db->Database   = $phpgw_info["server"]["db_name"];
  $db->User	    = $phpgw_info["server"]["db_user"];
  $db->Password   = $phpgw_info["server"]["db_pass"];
//  $db->Halt_On_Error = "report";
  $db->Halt_On_Error = "no";

  switch($msg){
    case "1":
      return "You have been successfully logged out";
      break;
    case "2":
      return "Your old tables were deleted";
      break;
  }

  /* Database setup */
  switch($action){
    case "askforupgrade":
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Analysis</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>You appear to be running a pre-beta version of phpGroupWare<br>\n";
      echo "    We are not providing an upgrade path at this time, please backup your tables and drop them, so that this script can recreate them.</td>\n";
      echo "  </tr>\n";
      echo "</table>\n";
?>
      <form method="POST" action=<?php $PHP_SELF?>>
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
          <td colspan="2" align="center"><input type="submit" name="action" value="Dump my old tables"></td>
        </tr>
      </table>
      </form>
<?php
      break;
    case "Dump my old tables":
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Information</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>At your request, this script is going to take the evil action of dropping your existing tables and re-creating them in the new format.</td>\n";
      echo "  </tr>\n";
      include ("droptables_".$phpgw_info["server"]["db_type"].".inc.php");
      include ("createtables_".$phpgw_info["server"]["db_type"].".inc.php");
      include ("default_records.inc.php");
      include ("lang_records.inc.php");
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Status</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>If you did not recieve any errors, your tables have been created.<br>\n";
      echo "    <a href=\"config.php\">Click here</a> to configure the environment.</td>\n";
      echo "  </tr>\n";
      echo "</table>\n";
      break;
    case "Upgrade":
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Information</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>At your request, this script is going to attempt to upgrade your tables to the new format.</td>\n";
      echo "  </tr>\n";
      echo "</table>\n";
      include ("createtables_".$phpgw_info["server"]["db_type"].".inc.php");
      include ("default_records.inc.php");
      include ("lang_records.inc.php");
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Status</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>If you did not recieve any errors, your tables have been updated.<br>\n";
      echo "    <a href=\"config.php\">Click here</a> to configure the environment.</td>\n";
      echo "  </tr>\n";
      echo "</table>\n";
      break;      
    default:
      $db->query("select * from config");
      if ($db->num_rows() == 0){
        $db->query("select * from accounts");
        if ($db->num_rows() == 0){
          echo "<table border=\"0\" align=\"center\">\n";
          echo "  <tr bgcolor=\"486591\">\n";
          echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Analysis</b></font></td>\n";
          echo "  </tr>\n";
          echo "  <tr bgcolor=\"e6e6e6\">\n";
          echo "    <td>You appear to be running a new install of phpGroupWare, so the tables will be created for you.</td>\n";
          echo "  </tr>\n";
          include ("createtables_".$phpgw_info["server"]["db_type"].".inc.php");
          include ("default_records.inc.php");
          include ("lang_records.inc.php");
          echo "  <tr bgcolor=\"486591\">\n";
          echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Status</b></font></td>\n";
          echo "  </tr>\n";
          echo "  <tr bgcolor=\"e6e6e6\">\n";
          echo "    <td>If you did not recieve any errors, your tables have been created.<br>\n";
          echo "    <a href=\"config.php\">Click here</a> to configure the environment.</td>\n";
          echo "  </tr>\n";
          echo "</table>\n";
        }else{
          Header("Location: $PHP_SELF?action=askforupgrade");
        }
      }else{
        echo "<table border=\"0\" align=\"center\">\n";
        echo "  <tr bgcolor=\"486591\">\n";
        echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Analysis</b></font></td>\n";
        echo "  </tr>\n";
        echo "  <tr bgcolor=\"e6e6e6\">\n";
        echo "    <td>Your database seems to be current.<br>\n";
        echo "    <a href=\"config.php\">Click here</a> to configure the environment.</td>\n";
        echo "  </tr>\n";
        echo "</table>\n";
      }
  }

  //db->disconnect();
?>