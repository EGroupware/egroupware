<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
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
  
  // Authorize the user to use setup app
  include("inc/setup_auth.inc.php");
  // Does not return unless user is authorized

  /* Database setup */
  include($phpgw_info["server"]["api_dir"] . "/phpgw_db_".$phpgw_info["server"]["db_type"].".inc.php");

  $db	            = new db;
  $db->Host	      = $phpgw_info["server"]["db_host"];
  $db->Type	      = $phpgw_info["server"]["db_type"];
  $db->Database      = $phpgw_info["server"]["db_name"];
  $db->User	      = $phpgw_info["server"]["db_user"];
  $db->Password      = $phpgw_info["server"]["db_pass"];
  //$db->Halt_On_Error = "report";
  //$db->Halt_On_Error = "no";

  echo "<html><head><title>phpGroupWare Setup</title></head>\n";
  echo "<body bgcolor='#ffffff'>\n";

  include($phpgw_info["server"]["include_root"]."/phpgwapi/phpgw_common.inc.php");
  $common = new common;
  $sep = $common->filesystem_separator();

  if ($submit) {
     if (count($lang_selected)) {
        if ($upgrademethod == "dumpold") {
           $db->query("delete from lang");
           //echo "<br>Test: dumpold";
        }
        while (list($null,$lang) = each($lang_selected)) {
           $addlang = False;
           if ($upgrademethod == "addonlynew") {
              //echo "<br>Test: addonlynew - select count(*) from lang where lang='$lang'";
              $db->query("select count(*) from lang where lang='$lang'");
              $db->next_record();
              
              if ($db->f(0) == 0) {
                 //echo "<br>Test: addonlynew - True";
                 $addlang = True;
              }
           }
           if (($addlang && $upgrademethod == "addonlynew") || ($upgrademethod != "addonlynew")) {
              //echo '<br>Test: loop above file()';
              $raw_file = file($phpgw_info["server"]["server_root"] . "/setup/phpgw_" . strtolower($lang) . ".lang");
              while (list($null,$line) = each($raw_file)) {
                $addit = False;
                list($message_id,$app_name,$db_lang,$content) = explode("\t",$line);
                $message_id = addslashes(chop($message_id));
                $app_name   = addslashes(chop($app_name));
                $db_lang    = addslashes(chop($db_lang));
                $content    = addslashes(chop($content));
                if ($upgrademethod == "addmissing") {
                   //echo "<br>Test: addmissing";
                   $db->query("select count(*) from lang where message_id='$message_id' and lang='$db_lang'");
                   $db->next_record();
                
                   if ($db->f(0) == 0) {
                      //echo "<br>Test: addmissing - True - Total: " . $db->f(0);
                      $addit = True;
                   }
                }
             
                if ($addit || ($upgrademethod == "dumpold" || $newinstall || $upgrademethod == "addonlynew")) {
                   //echo "<br>adding - insert into lang values ('$message_id','$app_name','$db_lang','$content')";
                   $db->query("insert into lang values ('$message_id','$app_name','$db_lang','$content')");
                }
             }
          }
       }
    } 

    echo "<center>Language files have been installed</center>";
    exit;     
  
  } else {
?>
  <table border="0" align="center" width="<?php echo ($newinstall?"60%":"80%"); ?>">
   <tr bgcolor="486591">
    <td colspan="<?php echo ($newinstall?"1":"2"); ?>">&nbsp;<font color="fefefe">Multi-Language support setup</font></td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td colspan="<?php echo ($newinstall?"1":"2"); ?>">This program will help you upgrade or installing different languages for phpGroupWare</td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td<?php echo ($newinstall?' align="center"':""); ?>>Select which languages you would like to use.
     <form action="lang.php">
      <?php echo ($newinstall?'<input type="hidden" name="newinstall" value="True">':""); ?>
      <select name="lang_selected[]" multiple size="10">
       <?php
         $db->query("select lang_id,lang_name from languages where available='Yes'");
         while ($db->next_record()) {
           echo '<option value="' . $db->f("lang_id") . '">' . $db->f("lang_name") . '</option>';
         }
       ?>
      </select>
    </td>
    <?php
      if (! $newinstall) {
         echo '<td valign="top">Select which method of upgrade you would like to do'
            . '<br><input type="radio" name="upgrademethod" value="dumpold">&nbsp;Delete all old langagues and install new ones'
            . '<br><input type="radio" name="upgrademethod" value="addmissing">&nbsp;Only add new pharses'
            . '<br><input type="radio" name="upgrademethod" value="addonlynew">&nbsp;only add languages that are not in the database already.'
            . '</td>';
      }
    ?>
   </tr>
  </table>
<?php
    echo '<center><input type="submit" name="submit" value="Install"></center>';
  }




