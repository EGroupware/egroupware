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

	Header('Cache-Control: no-cache');
	Header('Pragma: no-cache');
	//Header('Expires: Sat, Jan 01 2000 01:01:01 GMT');

	$phpgw_info = array();
	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'preferences';
	include('../header.inc.php');

	if ($GLOBALS['phpgw_info']['user']['permissions']['anonymous'])
	{
		Header('Location: ' . $GLOBALS['phpgw']->link('/'));
		$GLOBALS['phpgw']->common->phpgw_exit();
	}

	if ($submit)
	{
		if ($picture_size)
		{
			$fh = fopen($picture,'rb');
			$picture_raw = fread($fh,$picture_size);
			fclose($fh);

			$phone_number = addslashes($phone_number);
			$comments     = addslashes($comments);
			$title        = addslashes($title);

			if ($GLOBALS['phpgw_info']['server']['db_type'] == 'mysql')
			{
				$picture_raw  = addslashes($picture_raw);
			}
			else
			{
				$picture_raw = base64_encode($picture_raw);
			}

			$GLOBALS['phpgw']->db->query("delete from profiles where owner='" . $GLOBALS['phpgw_info']['user']['userid'] . "'");

			$GLOBALS['phpgw']->db->query("insert into profiles (owner,title,phone_number,comments,"
				. "picture_format,picture) values ('" . $GLOBALS['phpgw_info']['user']['userid'] . "','"
				. "$title','$phone_number','$comments','$picture_type','$picture_raw')");
		}
		else
		{
			$phone_number = addslashes($phone_number);
			$picture_raw  = addslashes($picture_raw);
			$comments     = addslashes($comments);
			$title        = addslashes($title);

			$GLOBALS['phpgw']->db->query("update profiles set title='$title',phone_number='$phone_number',"
				. "comments='$comments' where owner='" . $GLOBALS['phpgw_info']['user']['userid'] . "'");
		}
		echo '<center>Your profile has been updated</center>';
	}

	$GLOBALS['phpgw']->db->query("select * from profiles where owner='" . $GLOBALS['phpgw_info']['user']['userid'] . "'");
	$GLOBALS['phpgw']->db->next_record();
?>

  <form method="POST" ENCTYPE="multipart/form-data" action="<?php echo $GLOBALS['phpgw']->link('/preferences/changeprofile.php'); ?>">
   <table border="0">
    <tr>
     <td colspan="2"><?php echo $GLOBALS['phpgw']->common->display_fullname($GLOBALS['phpgw_info']['user']['userid'],$GLOBALS['phpgw_info']['user']['firstname'],$GLOBALS['phpgw_info']['user']['lastname']); ?></td>
     <td>&nbsp;</td>
    </tr>
    <tr>
     <td>Title:</td>
     <td><input name="title" value="<?php echo $GLOBALS['phpgw']->db->f('title'); ?>"></td>
     <td rowspan="2">
      <img src="<?php echo $GLOBALS['phpgw']->link('/hr/view_image.php','con=' . $GLOBALS['phpgw_info']['user']['con']); ?>" width="100" height="120">
     </td>
    </tr>

    <tr>
     <td>Phone number:</td>
     <td><input name="phone_number" value="<?php echo $GLOBALS['phpgw']->db->f('phone_number'); ?>"></td>
    </tr>

    <tr>
     <td>Comments:</td>
     <td><textarea cols="60" name="comments" rows="4" wrap="virtual"><?php echo $GLOBALS['phpgw']->db->f('comments'); ?></textarea></td>
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
<?php
	$GLOBALS['phpgw']->common->phpgw_footer();
?>
