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
	$imgfile = $phpgw->common->get_image_dir($appname) . SEP . $appname . '.gif';
	if (file_exists($imgfile)) {
		$imgpath = $phpgw->common->get_image_path($appname) . SEP . $appname . '.gif';
	} else {
		$imgfile = $phpgw->common->get_image_dir($appname) . SEP . 'navbar.gif';
		if (file_exists($imgfile)) {
			$imgpath = $phpgw->common->get_image_path($appname) . SEP . 'navbar.gif';
		} else {
			$imgpath = '';
		}
	}

	section_start(ucfirst($appname),$imgpath);

	section_item($phpgw->link('/addressbook/preferences.php'),
		lang('Preferences'));

	section_item($phpgw->link('/preferences/acl_preferences.php','acl_app='.$appname),
		lang('Grant Access'));

	section_item($phpgw->link('/preferences/categories.php','cats_app='.$appname . '&cats_level=True&global_cats=True'),
		lang('Edit Categories'));

	section_item($phpgw->link('/addressbook/fields.php'),
		lang('Edit custom fields'));

	section_end(); 
}
?>
