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
// Only Modify the $file and $title variables.....
	$title = $GLOBALS['appname'];
	$file = Array(
		'Site Configuration'	=> $phpgw->link('/admin/config.php','appname=' . $GLOBALS['appname'])
	);
//Do not modify below this line
	display_section($GLOBALS['appname'],$title,$file);
}
?>
