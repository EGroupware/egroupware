<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* -------------------------------------------------------                  *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */
{

	echo "<p>\n";
	$imgfile = $phpgw->common->get_image_dir($appname) . '/' . $appname . '.gif';
	if (file_exists($imgfile)) {
		$imgpath = $phpgw->common->get_image_path($appname) . '/' . $appname . '.gif';
	} else {
		$imgfile = $phpgw->common->get_image_dir($appname) . '/navbar.gif';

		if (file_exists($imgfile)) {
			$imgpath = $phpgw->common->get_image_path($appname) . '/navbar.gif';
		} else {
			$imgpath = '';
		}
	}

	section_start(ucfirst($appname),$imgpath);

	section_item($phpgw->link('/preferences/acl_preferences.php','acl_app=infolog'),
					 lang('Grant InfoLog Access'));

	section_item($phpgw->link('/preferences/categories.php','cats_app=infolog'),
					 lang('InfoLog categories'));
					 
	section_end(); 
}
?>
