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

  if (! $included) {

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("./inc/functions.inc.php");
  include("../header.inc.php");
  // Authorize the user to use setup app and load the database
  // Does not return unless user is authorized
  if (!$phpgw_setup->auth("Config")){
    Header("Location: index.php");
    exit;
  }
  $phpgw_setup->loaddb();

     include(PHPGW_API_INC."/class.common.inc.php");
     $common = new common;
     // this is not used
     //$sep = $common->filesystem_separator();
  } else {
     $newinstall             = True;
     $lang_selected["en"]    = "en";
     $submit                 = True;
  }

  if ($submit) {
     if (count($lang_selected)) {
        if ($upgrademethod == "dumpold") {
           $phpgw_setup->db->query("delete from lang");
           //echo "<br>Test: dumpold";
        }
        while (list($null,$lang) = each($lang_selected)) {
           $addlang = False;
           if ($upgrademethod == "addonlynew") {
              //echo "<br>Test: addonlynew - select count(*) from lang where lang='$lang'";
              $phpgw_setup->db->query("select count(*) from lang where lang='$lang'");
              $phpgw_setup->db->next_record();
              
              if ($phpgw_setup->db->f(0) == 0) {
                 //echo "<br>Test: addonlynew - True";
                 $addlang = True;
              }
           }
           if (($addlang && $upgrademethod == "addonlynew") || ($upgrademethod != "addonlynew")) {
              //echo '<br>Test: loop above file()';
              $raw_file = file(PHPGW_SERVER_ROOT . "/setup/phpgw_" . strtolower($lang) . ".lang");
              while (list($null,$line) = each($raw_file)) {
                $addit = False;
                list($message_id,$app_name,$phpgw_setup->db_lang,$content) = explode("\t",$line);
                $message_id = addslashes(chop($message_id));
                $app_name   = addslashes(chop($app_name));
                $phpgw_setup->db_lang    = addslashes(chop($phpgw_setup->db_lang));
                $content    = addslashes(chop($content));
                if ($upgrademethod == "addmissing") {
                   //echo "<br>Test: addmissing";
                   $phpgw_setup->db->query("select count(*) from lang where message_id='$message_id' and lang='$phpgw_setup->db_lang'");
                   $phpgw_setup->db->next_record();
                
                   if ($phpgw_setup->db->f(0) == 0) {
                      //echo "<br>Test: addmissing - True - Total: " . $phpgw_setup->db->f(0);
                      $addit = True;
                   }
                }
             
                if ($addit || ($upgrademethod == "dumpold" || $newinstall || $upgrademethod == "addonlynew")) {
                   //echo "<br>adding - insert into lang values ('$message_id','$app_name','$phpgw_setup->db_lang','$content')";
                   $phpgw_setup->db->query("insert into lang values ('$message_id','$app_name','$phpgw_setup->db_lang','$content')");
                }
             }
          }
       }
    } 

    if (! $included) {
      Header("Location: index.php");
      exit;
    }

  } else {
    if (! $included) {
       $phpgw_setup->show_header();
?>
  <p><table border="0" align="center" width="<?php echo ($newinstall?"60%":"80%"); ?>">
   <tr bgcolor="486591">
    <td colspan="<?php echo ($newinstall?"1":"2"); ?>">&nbsp;<font color="fefefe">Multi-Language support setup</font></td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td colspan="<?php echo ($newinstall?"1":"2"); ?>">This program will help you upgrade or install different languages for phpGroupWare</td>
   </tr>
   <tr bgcolor="e6e6e6">
    <td<?php echo ($newinstall?' align="center"':""); ?>>Select which languages you would like to use.
     <form action="lang.php">
      <?php echo ($newinstall?'<input type="hidden" name="newinstall" value="True">':""); ?>
      <select name="lang_selected[]" multiple size="10">
       <?php
         $phpgw_setup->db->query("select lang_id,lang_name from languages where available='Yes'");
         while ($phpgw_setup->db->next_record()) {
           echo '<option value="' . $phpgw_setup->db->f("lang_id") . '">' . $phpgw_setup->db->f("lang_name") . '</option>';
         }
       ?>
      </select>
    </td>
    <?php
      if (! $newinstall) {
         echo '<td valign="top">Select which method of upgrade you would like to do'
            . '<br><input type="radio" name="upgrademethod" value="dumpold">&nbsp;Delete all old languages and install new ones'
            . '<br><input type="radio" name="upgrademethod" value="addmissing">&nbsp;Only add new phrases'
            . '<br><input type="radio" name="upgrademethod" value="addonlynew">&nbsp;only add languages that are not in the database already.'
            . '</td>';
      }
    ?>
   </tr>
  </table>
<?php
    echo '<center><input type="submit" name="submit" value="Install"> <input type="submit" name="submit" value="Cancel"></center>';
  }
  }
