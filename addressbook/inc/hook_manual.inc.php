<?php
  /**************************************************************************\
  * phpGroupWare - Calendar Holidays                                         *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	if (floor(phpversion()) == 4) {
		global $phpgw, $phpgw_info, $treemenu;
	}

// Only Modify the $file variable.....
	$file = Array();
//Do not modify below this line
	$font = $phpgw_info['theme']['font'];
	$navbar = $phpgw_info['user']['preferences']['common']['navbar_format'];
	$lang = strtoupper($phpgw_info['user']['preferences']['common']['lang']);
	$treemenu[] = '..'.($navbar != 'text'?'<img src="'.$phpgw->common->image($appname,'navbar.gif').'" border="0" alt="'.ucwords($appname).'">':'').($navbar != 'icons'?'<font face="'.$font.'">'.ucwords($appname).'</font>':'').'|'.$phpgw->link('/'.$appname.'/help/index.php');

	$help_file = check_help_file($appname,$lang,$appname.'.php');
	if($help_file != '')
	{
		$treemenu[] = '...<font face="'.$font.'">Overview</font>|'.$phpgw->link($help_file);
	}
?>
