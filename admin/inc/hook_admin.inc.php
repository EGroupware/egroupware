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
	$imgpath = $phpgw->common->image($appname,'navbar.gif');
	section_start(ucfirst($appname),$imgpath);

	section_item($phpgw->link('/admin/accounts.php'),lang('User accounts'));
	section_item($phpgw->link('/admin/groups.php'),lang('User groups'));
	section_item($phpgw->link('/admin/applications.php'),lang('Applications'));
	section_item($phpgw->link('/admin/categories.php'),lang('Global Categories'));
	section_item($phpgw->link('/admin/mainscreen_message.php'),lang('Change main screen message'));
	section_item($phpgw->link('/admin/currentusers.php'),lang('View sessions'));
	section_item($phpgw->link('/admin/accesslog.php'),lang('View Access Log'));

	section_end(); 
}
?>
