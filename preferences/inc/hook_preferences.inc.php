<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
{
	echo "<p>\n";
	$imgfile = $phpgw->common->get_image_dir("preferences")."/" . $appname .".gif";
	if (file_exists($imgfile))
	{
		$imgpath = $phpgw->common->get_image_path("preferences")."/" . $appname .".gif";
	} else
	{
		$imgfile = $phpgw->common->get_image_dir("preferences")."/navbar.gif";
		if (file_exists($imgfile))
		{
			$imgpath = $phpgw->common->get_image_path("preferences")."/navbar.gif";
		} else
		{
			$imgpath = "";
		}
	}

	section_start("Account Preferences",$imgpath);


	// Actual content
	if ($phpgw->acl->check('changepassword',1))
	{
		section_item($phpgw->link('/preferences/changepassword.php'), lang("change your password"));
	}
	
	section_item($phpgw->link('/preferences/settings.php'), lang('change your settings'));

	section_end(); 
}
?>
