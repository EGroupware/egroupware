<?php
  /**************************************************************************\
  * phpGroupWare - E-Mail                                                    *
  * http://www.phpgroupware.org                                              *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */


if ($action == "Load Vcard"){
  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "addressbook", "enable_addressbook_class" => True);
  include("../header.inc.php");
}else{
  $phpgw_info["flags"] = array("currentapp" => "addressbook", "enable_addressbook_class" => True);
  include("../header.inc.php");
  echo '<body bgcolor="' . $phpgw_info["theme"]["bg_color"] . '">';
}
  
  // Some of the methods where borrowed from
  // Squirrelmail <Luke Ehresman> http://www.squirrelmail.org

  $sep = $phpgw->common->filesystem_separator();

  $uploaddir = $phpgw_info["server"]["temp_dir"] . $sep . $phpgw_info["user"]["sessionid"] . $sep;

  if ($action == "Load Vcard") {
    if($uploadedfile == "none" || $uploadedfile == "") {
      Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] .
            "/addressbook/vcardin.php","action=GetFile"));
    } else {
      srand((double)microtime()*1000000);
      $random_number = rand(100000000,999999999);
      $newfilename = md5("$uploadedfile, $uploadedfile_name, " . $phpgw_info["user"]["sessionid"]
                     . time() . getenv("REMOTE_ADDR") . $random_number );

      copy($uploadedfile, $uploaddir . $newfilename);
      $ftp = fopen($uploaddir . $newfilename . ".info","w");
      fputs($ftp,"$uploadedfile_type\n$uploadedfile_name\n");
      fclose($ftp);

      // This has to be non-interactive in case of a multi-entry vcard.
      $filename = $uploaddir . $newfilename;
      $n_groups = $phpgw->accounts->array_to_string($access,$n_groups);
      
      if($access == "group")
        $access = $n_groups;
      //echo $access . "<BR>";

      parsevcard($filename,$access);
      // Delete the temp file.
      unlink($filename);
      unlink($filename . ".info");
      Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/", "cd=14"));
    }
  }

  if (! file_exists($phpgw_info["server"]["temp_dir"] . $sep . $phpgw_info["user"]["sessionid"]))
     mkdir($phpgw_info["server"]["temp_dir"] . $sep . $phpgw_info["user"]["sessionid"],0700);

  if ($action == "GetFile"){
    echo "<B><CENTER>You must select a vcard. (*.vcf)</B></CENTER><BR><BR>";
  }

  ?>
  
    <form ENCTYPE="multipart/form-data" method="POST" action="<?php echo $phpgw->link("vcardin.php")?>">
      <table border=0>
      <tr>
       <td>Vcard: <input type="file" name="uploadedfile"></td>
       <td><input type="submit" name="action" value="Load Vcard"></td>
      </tr>
      <tr></tr>
      <tr></tr>
      <tr></tr>
      <tr>
        <td><?php echo lang("Access");?>:</td>
        <td><?php echo lang("Which groups");?>:</td>
      </tr>
      <tr>
        <td>
          <select name="access">
            <option value="private"<?php if($access == "private") echo "selected";?>>
              <?php echo lang("private"); ?>
            </option>
            <option value="public"<?php if($access == "public") echo "selected";?>>
              <?php echo lang("Global Public"); ?>
            </option>
            <option value="group"<?php if($access != "private" && $access != "public"
                                    && $access != "") echo "selected";?>>
              <?php echo lang("Group Public"); ?>
            </option>
          </select>
        </td>
        <td colspan="3">
          <select name=n_groups[] multiple size="5">
            <?php
             $user_groups = $phpgw->accounts->read_group_names($fields["ab_owner"]);
             for ($i=0;$i<count($user_groups);$i++) {
               echo "<option value=\"" . $user_groups[$i][0] . "\"";
               if (ereg(",".$user_groups[$i][0].",",$access))
                 echo " selected";
               echo ">" . $user_groups[$i][1] . "</option>\n";
             }
            ?>
          </select>
        </td>
      </tr>
      </table>
     </form>


<?php

if ($action != "Load Vcard")
  $phpgw->common->phpgw_footer();
?>
