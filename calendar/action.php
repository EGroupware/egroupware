<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */
	$phpgw_flags = Array(
		'currentapp'	=> 'calendar',
		'noheader'	=> True,
		'nonavbar'	=> True
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	if($phpgw->calendar->check_perms(PHPGW_ACL_EDIT) != True)
	{
		echo '<center>You do not have permission to edit this appointment!</center>';
		$phpgw->common->footer();
		$phpgw->common->phpgw_exit();
	}

	$phpgw->calendar->open('INBOX',$owner,'');

	$phpgw->calendar->set_status(intval($id),$owner,intval($action));

	Header('Location: '.$phpgw->link('/calendar/index.php','owner='.$owner));
?>
