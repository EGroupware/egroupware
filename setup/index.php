<?php
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
  include("../version.inc.php");  // To set the current core version

  $phpgw_info["server"]["api_dir"] = $phpgw_info["server"]["include_root"]."/phpgwapi";

  // Authorize the user to use setup app
  include("inc/setup_auth.inc.php");
  // Does not return unless user is authorized

  /* Database setup */
  switch($phpgw_info["server"]["db_type"]){
    case "postgresql":
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_pgsql.inc.php");
      break;
    case "oracle":
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_oracle.inc.php");
      break;
    case "mysql":
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_mysql.inc.php");
      break;
    default:
      echo("<h1>Please set db_type in your header.inc.php correctly</h1>\n");
      exit;
  }

  $db             = new db;
  $db->Host       = $phpgw_info["server"]["db_host"];
  $db->Type       = $phpgw_info["server"]["db_type"];
  $db->Database   = $phpgw_info["server"]["db_name"];
  $db->User       = $phpgw_info["server"]["db_user"];
  $db->Password   = $phpgw_info["server"]["db_pass"];
//  $db->Halt_On_Error = "report";
  $db->Halt_On_Error = "no";

  if (!isset($oldversion)){
    $db->query("select app_version from applications where app_name='admin'");
    $db->next_record();
    $oldversion = $db->f("app_version");
  }



  /**********************************************************************\
   * First order of business is to upgrade or install the core.         *
   * if $ok is set to false after this include, the setup stops here    *
   * otherwise, we display the app setup form.                          *
   * This is sorta kludgy still, but until I can figure out a clean way *
   * for applications to inteact with the user, this is how it is.      *
   *                                                                    *
  \**********************************************************************/
  $ok = true;
  include("inc/core_setup.inc.php");
  if (!$ok) {
    exit;
  } else {
    echo "<table border='0' align='center' bgcolor='#e6e6e6' cellpadding='3' cellspacing='0'>\n";
    echo "<tr bgcolor='#486591'>";
    echo "<th><font color='#fefefe'>phpGroupWare Core Staus</font></th>";
    echo "</tr>\n";
    echo "<tr><td>Core version $oldversion. No updates needed.</td></tr>\n";
    echo "</table>\n\n";
  }
  // Remove the appName from all users and groupws on the system
  function removeAppPerms($appName) {
    global $db;
    
  }

  /**********************************************************************\
   * See the Developers HOWTO and the example app in phpgwapps for more  *
   * info on how to integrate your application with this system.         *
  \**********************************************************************/


  // Called by the app setup classes to add/remove lang records
  // it acts correctly according to the current action,
  // for an install/upgrade it removes the old record & installes the new one
  // for an uninstall it removes the record (as long is as it NOT in common)
  //
  // TODO: This mechanism is VERY TEMPORARY until jengo and blinky can figure
  // out a clean way to use transy for this!!!!!!!!!!!!!!!!!!!!!!!!!!!!

  function do_lang($msg_id,$app,$lang,$content) {
    global $db,$currentAction; 
   
    $msg_id = strtolower($msg_id);
    $act = strtolower($currentAction);
    //echo "Do Lang: $act, $msg_id, $app, $lang, $content<br>\n";
    // Remove the old one
    if ($act == "uninstall" || $act == "upgrade") {
      if ($app == "common") {
        echo "<!-- Not touching message_id '".$msg_id."' as it is in app common. -->\n";
      } else {
        $sql = "DELETE FROM lang WHERE message_id='" . $msg_id . "' AND app_name='". $app ."'";
        $db->query($sql);
      }
    }
    // Add the new one
    // By setting $content == "", it allows you to prune old messages from the system
    if ($content != "" && $act == "install") {
      $sql  = "INSERT INTO lang(message_id,app_name,lang,content) VALUES(";
      $sql .= "'".$msg_id."',";
      $sql .= "'".$app."',";
      $sql .= "'".$lang."',";
      $sql .= "'".$content."')";
      $db->query($sql);
    } 
    if ($content != "" && $act == "upgrade") {
      $sql  = "UPDATE lang SET ";
      $sql .= "content='".$content."' WHERE";
      $sql .= " message_id ='".$msg_id."'";
      $sql .= " AND app_name = '".$app."'";
      $sql .= " AND lang = '".$lang."'";
      $db->query($sql);
    }
  }

  /**********************************************************************\
   *                                                                    *
  \**********************************************************************/

  /**********************************************************************\
   * Apps should call this function to report errors if possible to     *
   * display them in a nice and controlled format                       *
  \**********************************************************************/
  function error($msg) {
    global $error_count;
    ++$error_count;
    echo "<tr><td><font color='#ff0000'>".$msg."</font></td></tr>\n";
  }

  /**********************************************************************\
   * Apps should call this function to report warnings if possible to   *
   * display them in a nice and controlled format                       *
  \**********************************************************************/
  function warn($msg) {
    echo "<tr><td><font color='#ff00ff'>".$msg."</font></td></tr>\n";
  }

  /**********************************************************************\
   * Applications (and the core) inherit from this class which provides  *
   * all the hooks that setup needs to call for the app.                 *
   *                                                                     *
   * Applications should call warn() or error() to communicate with      *
   * the user.                                                           *
  \**********************************************************************/
  
  // Template for the app-specific setup classes
  class Setup {
    // Is *ANY* version of the app currently installed ?
    function is_installed() {
      return false;
    }
   
    // Is the app installed and up to date?
    function is_current() {
      return true;
    } 

    // Can the installed version be upgraded to the
    // new one?
    function can_upgrade() {
      return false;
    }

    // If this application dpends on any other apps
    // this shouldd return an array of the app names (the directory name)
    // or return false if it can stand alone 
    function dependant_apps() {
      return false;
    }

    // Called to actually upgrade the app
    function upgrade() {
      return true;
    }

    function install() {
      return false;
    }
    
    // Called to uninstall the app
    // You should remove all tables and files you created,
    // and return the system to the state it was before upgrade/install was called  
    function uninstall() {
    }
  }


  // Initial HTML output
  echo "<html><head><title>Setup phpGroupWare</title></head>\n";
  echo "<body bgcolor='#ffffff'>\n";

  // Loop through all the directories looking for possible 3rd party apps
  $baseDir = $phpgw_info["server"]["server_root"];
  $setupFile = "/inc/setup.inc.php"; // File to look for to identify apps 

  $dh = opendir($baseDir);
  while ($dir = readdir($dh)) {
    $fp = $baseDir . "/" . $dir;
    if ($dir[0] != '.' && is_dir($fp)) {
      $fp .= $setupFile;
      if (is_file($fp) && $dir != "setup") {
        //echo "found a setup! in  $fp<br>\n";
        $detectedApps[$dir]["path"] = $fp;
        $detectedApps[$dir]["name"] = $dir;
        $detectedApps[$dir]["dir"] = $baseDir."/".$dir;
      }
    }
  }
  closedir($dh);


  while ($detectedApps && list($name,$app) = each($detectedApps)) {
    include($app["path"]);
    $detectedApps[$name]["setup"] = new $classname($app["dir"]);
  }

  // If the user wanted to upgrade/install/remove an app, now is the time
  if ($submit == "Perform Actions" && is_array($appAction)) {
    echo "<p>\n";
    echo "<table border='0' align='center' bgcolor='#e6e6e6' cellpadding='3' cellspacing='0'>\n";
    echo "<tr bgcolor='#486591'>";
    echo "<th><font color='#fefefe'>Making Application Changes</font></th>";
    echo "</tr>\n";
    reset($detectedApps);
    $numAltered = 0;
    while ($detectedApps && list($name,$a) = each($detectedApps)) {
      $app = $a["setup"];
      switch ($appAction[$name]) {
        case "ignore":
          break;
        case "upgrade":
          $currentAction = "upgrade";
          if ($app->upgrade()) {
            echo "<tr><td><b>$name</b> upgraded.</td></tr>\n";
          } else {
            echo "<tr bgcolor='#ff4444'><td><b>$name</b> - upgrade failed!</td></tr>\n";
          }
          ++$numAltered;
          break;
        case "install":
          $currentAction = "install";
          if ($app->install()) {
            echo "<tr><td><b>$name</b> installed.</td></tr>\n";
          } else {
            echo "<tr bgcolor='#ff4444'><td><b>$name</b> - install failed!</td></tr>\n";
          }
          ++$numAltered;
          break;
        case "uninstall":
          $currentAction = "uninstall";
          if ($app->uninstall()) {
            echo "<tr><td><b>$name</b> uninstalled.</td></tr>\n";
            removeAppPerms($name);
          } else {
            echo "<tr bgcolor='#ff4444'><td><b>$name</b> - uninstall failed!</td></tr>\n";
          }
          ++$numAltered;
          break;
      }
    }
    if ($numAltered == 0) {
      echo "<tr><td>No applications altered.</td></tr>\n";
    }
    echo "</table>\n";
  }

  echo "<form action='".$PHP_SELF."' method='POST'>\n";

  echo "<table border='0' align='center' bgcolor='#e6e6e6' cellpadding='3' cellspacing='1'>\n";
  echo "<tr bgcolor='#486591'><th colspan='7'><font size='+1' color='#fefefe'>Application Setup</font></th></tr>\n";
  echo "<tr bgcolor='#486591'>";
  echo "<th><font color='#fefefe'>Application</font></th>";
  echo "<th><font color='#fefefe'>Installed Version</font></th>";
  echo "<th><font color='#fefefe'>Detected Version</font></th>";
  echo "<th><font color='#fefefe'>Install</font></th>";
  echo "<th><font color='#fefefe'>Upgrade</font></th>";
  echo "<th><font color='#fefefe'>Remove</font></th>";
  echo "<th><font color='#fefefe'>Do Nothing</font></th>";
  echo "</tr>\n";

  $numApps = 0;
  if (isSet($detectedApps)) reset($detectedApps);
  while ($detectedApps && list($name,$a) = each($detectedApps)) {
    $app = $a["setup"];

    echo "<tr>";
    echo "  <td>".$name."</td>\n";

    if (is_object($a["setup"])) {
       $installed = $app->is_installed();
       $code = $app->code_version();
    }
    if (!$installed) {
      echo "  <td>-not yet installed-</td>\n";
    } else if ($installed == "0.0.0" || $installed == "0.0") {
      echo "  <td>Pre-Beta</td>";
    } else {
      echo "  <td>".$installed."</td>\n";
    } 
     
    echo "  <td>".$code."</td>\n";

    if ($installed && $app->is_current()) {
      echo "  <td>N/A</td>"; // Can't install
      echo "  <td>N/A</td>"; // Can't upgrade
      echo "  <td><input type='radio' name='appAction[".$name."]' value='uninstall'>"; // Can remove
      echo "  <td><input type='radio' name='appAction[".$name."]' value='ignore' checked>"; // Can ignore
    } else if ($installed) {
      echo "  <td>N/A</td>"; // Can't install
      echo "  <td><input type='radio' name='appAction[".$name."]' value='upgrade' checked>"; // Can upgrade
      echo "  <td><input type='radio' name='appAction[".$name."]' value='uninstall'>"; // Can remove
      echo "  <td><input type='radio' name='appAction[".$name."]' value='ignore'>"; // Can ignore
    } else {
      echo "  <td><input type='radio' name='appAction[".$name."]' value='install' checked>"; // Can install
      echo "  <td>N/A</td>"; // Can't upgrade
      echo "  <td>N/A</td>"; // Can't remove
      echo "  <td><input type='radio' name='appAction[".$name."]' value='ignore'>"; // Can ignore
    }
    echo "</tr>\n";
    ++$numApps;
  }
  if ($numApps) {
    echo "<tr><td colspan='7' align='right'><input type='submit' name='submit' value='Perform Actions'></td></tr>\n";
  } else {
    echo "<tr><td colspan='7' align='right'>Found no non-core applications. ";
    echo "Visit <a href='http://phpgroupware.org/'>phpGroupWare.org</a> to obtain add-on applications.</td></tr>\n";
  }
  echo "</table>\n";
  echo "</form>\n";

  echo "Applications not in the table above are either part of the phpGroupWare core, or ";
  echo "have not been upgraded to the new phpGroupWare application setup code.";

  echo "<p>When you are done installing and upgrading applications, you should ";
  echo "continue to the <a href='config.php'>Configuration Page</a>";
  echo "<br>or skip to <a href='lang.php'>Configure multi-language support</a>.\n";
?>
</body>
</html>
