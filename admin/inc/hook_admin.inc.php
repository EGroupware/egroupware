<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

{
// Only Modify the $file and $title variables.....
	$title = $appname;
	$file = Array(
		'User Accounts'	=> $phpgw->link('/admin/accounts.php'),
		'User Groups'	=> $phpgw->link('/admin/groups.php'),
		'Applications'		=> $phpgw->link('/admin/applications.php'),
		'Global Categories'		=> $phpgw->link('/admin/categories.php'),
		'Change Main Screen Message'		=> $phpgw->link('/admin/mainscreen_message.php'),
		'View Sessions'		=> $phpgw->link('/admin/currentusers.php'),
		'View Access Log'		=> $phpgw->link('/admin/accesslog.php')
	);
//Do not modify below this line
	display_section($appname,$title,$file);
}
?>
