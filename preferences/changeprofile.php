<?php
  /**************************************************************************\
  * phpGroupWare - preferences                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  Header("Cache-Control: no-cache");
  Header("Pragma: no-cache");
  //Header("Expires: Sat, Jan 01 2000 01:01:01 GMT");

  $phpgw_flags["currentapp"] = "preferences";
  include("../header.inc.php");
  if ($phpgw_info["user"]["permissions"]["anonymous"]) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/"));
     exit;
  }

  if ($submit) {
     if ($picture_size) {
        $fh = fopen($picture,"r");
         $picture_raw = fread($fh,$picture_size);
        fclose($fh);

        $phone_number = addslashes($phone_number);
        $comments     = addslashes($comments);
        $title        = addslashes($title);

        if ($phpgw_info["server"]["db_type"] == "mysql") {
           $picture_raw  = addslashes($picture_raw);
        } else {
           $picture_raw = base64_encode($picture_raw);
        }

        $phpgw->db->query("delete from profiles where owner='" .$phpgw->session->loginid . "'");

        $phpgw->db->query("insert into profiles (owner,title,phone_number,comments,"
			   . "picture_format,picture) values ('" . $phpgw->session->loginid . "','"
			   . "$title','$phone_number','$comments','$picture_type','$picture_raw')");
     } else {
        $phone_number = addslashes($phone_number);
        $picture_raw  = addslashes($picture_raw);
        $comments     = addslashes($comments);
        $title        = addslashes($title);

        $phpgw->db->query("update profiles set title='$title',phone_number='$phone_number',"
			   . "comments='$comments' where owner='" . $phpgw->session->loginid. "'");
     }
     echo "<center>Your profile has been updated</center>";
  }

  $phpgw->db->query("select * from profiles where owner='" . $phpgw->session->loginid . "'");
  $phpgw->db->next_record();
?>

  <form method="POST" ENCTYPE="multipart/form-data" action="changeprofile.php">
   <?php echo $phpgw->session->hidden_var(); ?>

   <table border="0">
    <tr>
     <td colspan="2"><?php echo $phpgw->common->display_fullname($phpgw->session->loginid,$phpgw->session->firstname,$phpgw->session->lastname); ?></td>
     <td>&nbsp;</td>
    </tr>
    <tr>
     <td>Title:</td>
     <td><input name="title" value="<?php echo $phpgw->db->f("title"); ?>"></td>
     <td rowspan="2">
      <img src="<?php echo $phpgw->link($phpgw_info["server"]["webserver_url"] . "/hr/view_image.php","con=" . $phpgw->session->con); ?> width="100" height="120">
     </td>
    </tr>

    <tr>
     <td>Phone number:</td>
     <td><input name="phone_number" value="<?php echo $phpgw->db->f("phone_number"); ?>"></td>
    </tr>

    <tr>
     <td>Comments:</td>
     <td><textarea cols="60" name="comments" rows="4" wrap="virtual"><?php echo $phpgw->db->f("comments"); ?></textarea></td>
    </tr>

    <tr>
     <td>Picture:</td>
     <td><input type="file" name="picture"><br>Note: Pictures will be resized to 100x120.</td>
    </tr>

    <tr>
     <td colspan="3" align="center"><input type="submit" name="submit" value="Submit">
    </tr>
   </table>

  </form>
